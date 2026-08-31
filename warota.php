<?php
/***************************************************************************
	PHPぁぷろだ by ToR(http://php.s3.to)
	source by ずるぽん(http://zurubon.virtualave.net/)
	English translation & various modifications by Heyuri (https://www.heyuri.net/)

	Heyuri updates (edition 20260326)
		This uploader is a custom version of PHPぁぷろだ.
		Many thanks to ずるぼん-sama for the original source and レッツPHP-sama for the PHP conversion.
		The last update before Heyuri took over was Yakuba modifications (edition 20090922)

■Terms and Conditions
	・We give no guarantees on its operation. Don’t cry if anything bad happens!
	・Commercial use is allowed, but do not use it for illegal purposes.
	・You are free to redistribute and modify. However, you can not remove the links.
	・These rules are in accordance with レッツPHP-sama's standards...

■History
	2001/08/30
	2001/09/04 v1.1 Cookies enabled for preferences, FTP transfer (deletion not yet works)
	2002/06/12 v1.2 Changed to move_uploaded_file (line 215)
	2002/07/23 v1.3 Some CSS measures for deletion (line 147)
	2002/08/06 v2.0 Slight changes in specifications (about allowed extensions, original file name display)
	2004/10/10 v2.2 Various fixes
	2005/01/10 v2.3 Removed line breaks
	2009/09/20 Revision   Major modifications commented by Yakuba
		・Check if the log files etc exist.
		・Display total size of the board
		・Total capacity limit (cannot post if the limit is exceeded)
		・Slightly adjusted the layout to resemble SnUploader
		・Fixed a problem in certain environments where the log file 
			disappears when the uploaded file is deleted and the log is empty
	2009/09/22 Revision   Fixed a bug about forced extension conversions and F5'ing
		・Forced conversion of specified extensions during upload.
		・When the extension of a file is converted, display its original extension in its comment.
		・Fixed a bug where the same operation was repeated if F5 was pressed immediately after
			the operation, such as uploading duplicate files.
	2020/06/?? Nakura from Heyuri has partially translated it to English
	2024/04/20 v3.0 The software is uploaded to github and shared with Hachikuji and Penman, who started working on it to make major changes
	2024/05/17 Revision   Major changes were made to the Uploader's code
		・Changed all deprecated PHP codes into modern ones
		・English translation is completed
		・It displays total board and file sizes in the proper storage units now
		・Fixed the bug where it didn't check if the board's file size limit was exceeded
		・Thumbnails implemented. Files larger than 1MB will get thumbnailed. Can be enabled from settings
		・Brought back sam.php as images.php
		・User boards (user/) are now an "extra" part of the software. People can create their own boards
		・Fixed an issue where the server was getting into an error loop if log file didn't exist
		・Configurations are now in a separate file. Main script doesn't need to be edited by default anymore (unless path of config.php is changed)
		・User boards are now an "extra" part of the software. Users can create their own boards. They can have custom CSS for their boards too
		・Configurable cooldown added against flooding
		・It's now anonymous by default, but can have a setting to log IPs of uploaders
		・If logging IPs, there are other settings to block IPs from viewing the board & uploading files
		・Some default CSS fixes
		・If user didn't enter any password for a file, only the administrator can delete the file
	2024/05/19 v3.1 Fixed a bug about not loading if the user had invalid cookies
	2024/08/03 v3.2 IP bans can now work without logging setting turned on as well
	2024/10/08 v3.3 Fixed a bug where files could be deleted with empty password
	2024/12/31 v3.4 Fixed the bug about video thumbnails not getting deleted with videos
	2026/03/08 v4.0 Major code refactor, added chunked uploads, and various other features and fixes.
	2026/03/16 v4.1 Misc improvements including some front-end updates, Japanese language option added. A homepage for the software is created.
	2026/03/26 v4.2 Minor tweaks to table HTML and CSS.
	2026/07/07 v4.3 AJAX file uploading without a full page refresh, track own uploaded files with a button to copy their links to clipboard.
■Installation
	・Clone repo into web directory (or unzip it there)
	・Make data/, src/ and thmb/ writable by the web user; the uploader creates its own
	  log, counter and ban files in data/ on the first request
	・If you rename any of them, change them in the configuration file too
	・Set owner of all files in the directory to web user by "sudo chown -R webuser:webuser /path/to/Uploader"

■Cautions (it is recommended to check these)
	・These variables in php.ini may need to be changed if you want to allow files larger than 2MBs to get uploaded:
			「upload_max_filesize」「post_max_size」「memory_limit」「max_execution_time」
	・And these variables in php.ini may be related to uploading process itself:
			「file_uploads」「upload_tmp_dir」
	・You can check your server's PHP settings with <?php phpinfo(); ?> (Some servers may not allow this)
	・Make sure uploaded .php files (and other potentially dangerous extensions) are properly converted to .txt
	・Hide the log files from displaying from internet with .htaccess, or change their default names so users don't know
**************************************************************************/

// repository root and the instance-wide data directory
define('ROOT_DIR', __DIR__);
define('GLOBAL_DATA_DIR', ROOT_DIR . '/data/');

error_reporting(E_ALL);
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', GLOBAL_DATA_DIR . 'error.log');

require __DIR__.'/code/TwintailUploader/include.php';
require __DIR__.'/autoloader.php';

use TwintailUploader\Classes\boardRepository;
use TwintailUploader\Classes\languageManager;
use TwintailUploader\Classes\requestHandler;
use TwintailUploader\Classes\uploaderHTML;

use function TwintailUploader\Functions\ensureDataFiles;
use function TwintailUploader\Functions\forceJapaneseForJpUsers;

// Set by boards/<uri>/index.php before it requires this file
$boardUri = $boardUri ?? null;
$board = null;

try {
	// Load configuration
	$configFile = ROOT_DIR . '/config.php';

	// Check if config file exists
	if(!file_exists($configFile)) throw new \Exception("Configuration file <i>$configFile</i> is missing.");

	// Load config
	$conf = require $configFile;

	// Keys the action log added, defaulted here so an instance whose config.php
	// predates it still runs
	$conf['actionLog'] = $conf['actionLog'] ?? true;
	$conf['actionLogFile'] = $conf['actionLogFile'] ?? 'actions.log';
	$conf['actionLogMaxEntries'] = (int) ($conf['actionLogMaxEntries'] ?? 2000);

	// Resolve the board being served, if any, and scope every relative path to it.
	// chdir() means "src/", "thmb/" and "data/" resolve inside the board directory
	// while still doubling as URLs relative to boards/<uri>/index.php.
	if ($boardUri !== null) {
		if (!preg_match('/^[a-z0-9_-]{1,16}$/', $boardUri)) throw new \Exception("Invalid board.");

		$board = (new boardRepository(GLOBAL_DATA_DIR . 'boards.log'))->getByUri($boardUri);
		if ($board === null) throw new \Exception("This board does not exist.");

		chdir(ROOT_DIR . '/' . $conf['boardsDir'] . $boardUri);
		define('DATA_DIR', getcwd() . '/data/');

		$conf = $board->applyToConfig($conf);
	} else {
		chdir(ROOT_DIR);
		define('DATA_DIR', GLOBAL_DATA_DIR);
	}

	// Which proxies getUserIP() may believe about the real client address.
	$GLOBALS['TWINTAIL_TRUSTED_PROXIES'] = $conf['trustedProxies'] ?? [];

	// Create this scope's flat files if they aren't there yet, so an install
	// only has to provide a writable data/ directory
	ensureDataFiles($conf, DATA_DIR);
	ensureDataFiles($conf, GLOBAL_DATA_DIR);

	// load language manager
	$languageManager = new languageManager(__DIR__ . '/lang', $conf['language'] ?? 'en');

	// handle force japanese option if the user has japanese browser settings, even if the default language is not japanese
	forceJapaneseForJpUsers($languageManager, $conf['forceJapaneseForJpUsers'] ?? false);

	// Configuration check
	if (!isset($conf['timeZone'])) {
		$conf['timeZone'] = 'UTC'; // Default timezone
	}

	date_default_timezone_set($conf['timeZone']);

	// Main logic
	$requestHandler = new requestHandler($conf, $languageManager, $board);
	$requestHandler->handleRequest();

} catch (\Exception $e) {
	// Handle exceptions, logging, and displaying user-friendly error messages
	if (!isset($conf) || !is_array($conf)) {
		$conf = [];
	}
	if (!isset($languageManager) || !$languageManager instanceof languageManager) {
		$languageManager = new languageManager(__DIR__ . '/lang', 'en');
	}

	// a board request that failed before its config was derived still renders
	// from boards/<uri>/, so point the assets and the return link back up
	if ($boardUri !== null && $board === null && !empty($conf)) {
		$conf['staticUrl'] = '../../static/';
		$conf['staticPath'] = ROOT_DIR . '/static/';
		$conf['mainScript'] = '../../' . $conf['mainScript'];
	}

	// display a user-friendly error page with the error message
	$uploaderHTML = new uploaderHTML($conf, $languageManager);
	$uploaderHTML->drawErrorPageAndExit($languageManager->get('errors.generalError'), $e->getMessage());
}
