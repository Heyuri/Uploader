<?php
/**
 * Deletes uploads whose temporary hosting period is over.
 *
 * The uploader already sweeps lazily while it serves requests, so this is only
 * needed when files have to go on time even on a quiet instance.
 *
 *   php expireFiles.php              sweep the main uploader
 *   php expireFiles.php <boardUri>   sweep one user board
 *   php expireFiles.php --all        sweep the main uploader and every user board
 *
 * DATA_DIR can only be defined once per process, so --all runs one child
 * process per board.
 */

if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	exit("This script may only be run from the command line.\n");
}

define('ROOT_DIR', __DIR__);
define('GLOBAL_DATA_DIR', ROOT_DIR . '/data/');

require __DIR__ . '/code/TwintailUploader/include.php';
require __DIR__ . '/autoloader.php';

use TwintailUploader\Classes\actionLogRepository;
use TwintailUploader\Classes\boardRepository;
use TwintailUploader\Classes\cloudflareAPI;
use TwintailUploader\Classes\logFile;
use TwintailUploader\Classes\temporaryHosting;
use TwintailUploader\Classes\uploadedFileRepository;
use TwintailUploader\Controllers\actionLogController;

use function TwintailUploader\Functions\ensureDataFiles;

$conf = require ROOT_DIR . '/config.php';
$conf['actionLog'] = $conf['actionLog'] ?? true;
$conf['actionLogFile'] = $conf['actionLogFile'] ?? 'actions.log';
$conf['actionLogMaxEntries'] = (int) ($conf['actionLogMaxEntries'] ?? 2000);

$target = $argv[1] ?? null;

// hand every board to a child process, then sweep the main uploader here
if ($target === '--all') {
	$target = null;

	foreach ((new boardRepository(GLOBAL_DATA_DIR . 'boards.log'))->getAll() as $board) {
		passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($board->getUri()));
	}
}

$board = null;
if ($target !== null) {
	if (!preg_match('/^[a-z0-9_-]{1,16}$/', $target)) {
		exit("Invalid board.\n");
	}

	$board = (new boardRepository(GLOBAL_DATA_DIR . 'boards.log'))->getByUri($target);
	if ($board === null) {
		exit("This board does not exist.\n");
	}
}

// same path resolution the entry point does — everything is relative to the
// instance being swept
if ($board !== null) {
	chdir(ROOT_DIR . '/' . $conf['boardsDir'] . $board->getUri());
	define('DATA_DIR', getcwd() . '/data/');

	$conf = $board->applyToConfig($conf);
	$label = rtrim($conf['boardsDir'], '/') . '/' . $board->getUri();
} else {
	chdir(ROOT_DIR);
	define('DATA_DIR', GLOBAL_DATA_DIR);

	$label = 'main';
}

// same as the uploader: a writable data/ is all an install has to provide
ensureDataFiles($conf, DATA_DIR);

date_default_timezone_set($conf['timeZone'] ?? 'UTC');

// sweeps are recorded in the same log the uploader writes to
$actionLog = new actionLogController(
	new actionLogRepository(DATA_DIR . $conf['actionLogFile'], $conf['actionLogMaxEntries']),
	!empty($conf['actionLog']),
	$board !== null ? $board->getUri() : ''
);

$temporaryHosting = new temporaryHosting(
	$conf,
	new logFile($conf),
	new uploadedFileRepository($conf, new cloudflareAPI($conf)),
	$actionLog
);

echo $label . ': ' . $temporaryHosting->sweep(true) . " expired file(s) removed\n";
