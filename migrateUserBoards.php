<?php
/**
 * Migrates pre-4.3 user boards into the current layout.
 *
 * Old boards were standalone copies of the uploader living in
 * user/boards/<uri>/, each with its own config.php. They now become a line in
 * data/boards.log plus a boards/<uri>/ directory served by the main script.
 *
 * The source is only read, never modified, so a failed run leaves the old
 * boards untouched and a partially migrated board can be removed from the
 * admin's Boards page and migrated again.
 *
 * Usage:
 *   php migrateUserBoards.php [--dry-run] [--source=path/to/user/boards]
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit("This script may only be run from the command line.\n");
}

define('ROOT_DIR', __DIR__);
define('GLOBAL_DATA_DIR', ROOT_DIR . '/data/');
define('DATA_DIR', GLOBAL_DATA_DIR);

require __DIR__ . '/code/TwintailUploader/include.php';
require __DIR__ . '/autoloader.php';

use TwintailUploader\Classes\board;
use TwintailUploader\Classes\boardRepository;
use TwintailUploader\Classes\themeManager;

use function TwintailUploader\Functions\generatePasswordHash;

/** Board fields the old config.php could not express */
const DROPPED_SETTINGS = ['deletionPassword', 'passwordRequired', 'maxAmountOfFiles', 'maxUploadSize', 'maxTotalSize'];

/** URIs that would collide with the repository layout or with routes */
const RESERVED_URIS = ['index', 'admin', 'api', 'boards', 'code', 'data', 'lang', 'src', 'static', 'templates', 'thmb', 'user'];

/**
 * Sentinel for a value we refuse to evaluate (marks it "skip this key").
 *
 * Declared up here with the others because `const` is not hoisted the way a
 * function declaration is: the migration loop below runs before any statement
 * further down the file, so a sentinel defined next to the parser that uses it
 * would not exist yet when the parser first ran.
 */
const MIGRATE_COMPLEX_VALUE = "\0__complex__\0";

$options = parseOptions($argv);
$conf = require ROOT_DIR . '/config.php';

$sourceDir = rtrim($options['source'] ?? (ROOT_DIR . '/user/boards'), '/');
$boardsRoot = ROOT_DIR . '/' . $conf['boardsDir'];

if (!is_dir($sourceDir)) {
	exit("Nothing to migrate: $sourceDir does not exist.\n");
}

if ($options['dry-run']) {
	echo "DRY RUN — nothing will be written.\n\n";
}

$boardRepository = new boardRepository(GLOBAL_DATA_DIR . 'boards.log');

$migrated = 0;
$skipped = 0;

foreach (findOldBoards($sourceDir) as $uri => $oldConfigFile) {
	echo "[$uri]\n";

	$result = migrateBoard($uri, $oldConfigFile, $conf, $boardsRoot, $boardRepository, $options['dry-run']);

	foreach ($result['messages'] as $message) {
		echo "  $message\n";
	}

	$result['migrated'] ? $migrated++ : $skipped++;
	echo "\n";
}

echo "Migrated $migrated board(s), skipped $skipped.\n";

if ($migrated > 0 && !$options['dry-run']) {
	echo "The old boards were left in place — remove $sourceDir once you've checked the new ones.\n";
}

/**
 * @return array<string,string> board uri => path to its old config.php
 */
function findOldBoards(string $sourceDir): array {
	$boards = [];

	foreach (scandir($sourceDir) ?: [] as $entry) {
		if ($entry === '.' || $entry === '..') continue;

		$configFile = $sourceDir . '/' . $entry . '/config.php';
		if (is_dir($sourceDir . '/' . $entry) && file_exists($configFile)) {
			$boards[$entry] = $configFile;
		}
	}

	ksort($boards);
	return $boards;
}

/**
 * @return array{migrated:bool,messages:string[]}
 */
function migrateBoard(string $uri, string $oldConfigFile, array $conf, string $boardsRoot, boardRepository $boardRepository, bool $dryRun): array {
	$messages = [];

	if (!preg_match('/^[a-z0-9_-]{1,16}$/', $uri)) {
		return ['migrated' => false, 'messages' => ['SKIPPED: the directory name is not a usable board URL (letters, numbers, dashes and underscores, up to 16 characters).']];
	}

	if (in_array($uri, RESERVED_URIS, true)) {
		return ['migrated' => false, 'messages' => ['SKIPPED: that board URL is reserved. Rename the directory and run again.']];
	}

	if ($boardRepository->uriExists($uri)) {
		return ['migrated' => false, 'messages' => ['SKIPPED: already registered in data/boards.log.']];
	}

	$boardDir = $boardsRoot . $uri;
	if (is_dir($boardDir)) {
		return ['migrated' => false, 'messages' => ["SKIPPED: $boardDir already exists."]];
	}

	$oldConf = loadOldConfig($oldConfigFile);
	if ($oldConf === null) {
		return ['migrated' => false, 'messages' => ['SKIPPED: could not read the old config.php.']];
	}

	// The prefix is baked into every stored file name, so a prefix we can't
	// reproduce safely means the files would stop resolving
	$prefix = (string) ($oldConf['prefix'] ?? '');
	if (!preg_match('/^[A-Za-z0-9_-]{0,10}$/', $prefix)) {
		return ['migrated' => false, 'messages' => ["SKIPPED: the file prefix " . var_export($prefix, true) . " isn't safe to reuse. Rename the files and set a plain prefix first."]];
	}

	$oldBoardDir = dirname($oldConfigFile);
	$oldUploadDir = $oldBoardDir . '/' . ($oldConf['uploadDir'] ?? 'src/');
	$oldThumbDir = $oldBoardDir . '/' . ($oldConf['thumbDir'] ?? 'thmb/');
	$oldLogFile = $oldBoardDir . '/' . ($oldConf['logFile'] ?? 'userPosts.block');

	$entries = readOldLog($oldLogFile, $messages);

	$board = new board([
		(string) $boardRepository->getNextID(),
		$uri,
		cleanText((string) ($oldConf['boardTitle'] ?? $uri), 64) ?: $uri,
		cleanText((string) ($oldConf['boardSubTitle'] ?? ''), 256),
		hashOwnerPassword((string) ($oldConf['adminPassword'] ?? ''), $messages),
		bin2hex(random_bytes(16)),
		(string) @filemtime($oldConfigFile) ?: (string) time(),
		'',
		!empty($oldConf['boardListed']) ? '1' : '0',
		'0',
		!empty($oldConf['commentRequired']) ? '1' : '0',
		cleanText((string) ($oldConf['defaultComment'] ?? ''), 128),
		$prefix,
		// the old config named a theme file; keep it only if that file is
		// still installed, and start the board with no custom palette
		installedTheme((string) ($oldConf['defaultTheme'] ?? '')),
		'',
	]);

	$bans = collectOldBans($oldConf);

	$messages[] = count($entries) . ' upload(s), ' . count($bans) . ' ban(s).';
	reportDroppedSettings($oldConf, $conf, $messages);

	if ($dryRun) {
		$messages[] = "would create $boardDir and register it as [" . $uri . '].';
		return ['migrated' => true, 'messages' => $messages];
	}

	if (!scaffold($boardDir, $conf, $messages)) {
		return ['migrated' => false, 'messages' => $messages];
	}

	[$copied, $skipped] = copyDirContents($oldUploadDir, $boardDir . '/' . rtrim($conf['uploadDir'], '/'), $conf['allowedExtensions'] ?? [], $conf['extensionsToBeConvertedToText'] ?? []);
	$messages[] = "copied $copied file(s) into " . $conf['uploadDir'];
	if ($skipped > 0) {
		$messages[] = "NOTE: skipped $skipped file(s) with a disallowed extension (would have been web-served).";
	}

	$thumbs = copyThumbnails($oldThumbDir, $boardDir . '/' . rtrim($conf['thumbDir'], '/'), $entries, $prefix, $conf);
	$messages[] = "copied $thumbs thumbnail(s), renamed to the current scheme.";

	writeLog($boardDir . '/data/' . $conf['logFile'], $entries);
	file_put_contents($boardDir . '/data/' . $conf['counterFile'], (string) highestID($entries));
	file_put_contents($boardDir . '/data/banlist.dat', $bans ? implode("\n", $bans) . "\n" : '');

	if (!$boardRepository->add($board)) {
		return ['migrated' => false, 'messages' => array_merge($messages, ['FAILED: could not register the board in data/boards.log.'])];
	}

	$messages[] = "migrated to $boardDir";
	return ['migrated' => true, 'messages' => $messages];
}

/**
 * The old config.php is owner-supplied code, so it is never require()'d — that
 * would run whatever the owner put in it with the migrating admin's privileges.
 * Instead the `return [...]` literal is parsed statically: only string, number,
 * bool, null and (nested) arrays of those are read; anything else — a function
 * call, an expression, a variable — is skipped, never evaluated.
 */
function loadOldConfig(string $configFile): ?array {
	$contents = file_get_contents($configFile);
	if ($contents === false) {
		return null;
	}

	$parsed = parseReturnedArray($contents);
	return is_array($parsed) ? $parsed : null;
}

/**
 * Statically parses the array literal a PHP file returns, without executing it.
 */
function parseReturnedArray(string $code): ?array {
	$stream = [];
	foreach (token_get_all($code) as $token) {
		if (is_array($token)) {
			if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG], true)) {
				continue;
			}
			$stream[] = [$token[0], $token[1]];
		} else {
			$stream[] = [null, $token];
		}
	}

	$n = count($stream);
	$pos = 0;
	while ($pos < $n && $stream[$pos][0] !== T_RETURN) {
		$pos++;
	}
	if ($pos >= $n) {
		return null;
	}
	$pos++;

	// tolerate `return $conf = [ ... ]` as well as `return [ ... ]`
	if ($pos < $n && $stream[$pos][0] === T_VARIABLE && ($pos + 1) < $n && $stream[$pos + 1][1] === '=') {
		$pos += 2;
	}

	$value = parseConfigValue($stream, $pos);
	return is_array($value) ? $value : null;
}

/** Reads one literal value at $pos, advancing it. Returns the sentinel if complex. */
function parseConfigValue(array $stream, int &$pos) {
	$n = count($stream);
	if ($pos >= $n) {
		return MIGRATE_COMPLEX_VALUE;
	}

	[$id, $text] = $stream[$pos];

	// array literal — [ ... ] or array( ... )
	if ($text === '[' || $id === T_ARRAY) {
		$close = ']';
		if ($id === T_ARRAY) {
			$pos++;                 // skip 'array'
			$close = ')';
		}
		$pos++;                     // skip opening bracket

		$result = [];
		$autoIndex = 0;
		while ($pos < $n && $stream[$pos][1] !== $close) {
			$key = parseConfigValue($stream, $pos);
			$keyed = false;
			$value = $key;

			if ($pos < $n && $stream[$pos][0] === T_DOUBLE_ARROW) {
				$keyed = true;
				$pos++;             // skip =>
				$value = parseConfigValue($stream, $pos);
			}

			// A clean element leaves $pos on a separator. Anything else means we
			// only parsed the prefix of an expression (e.g. 20*1024) — skip the
			// rest and drop the element rather than storing a wrong value.
			if ($pos < $n && $stream[$pos][1] !== ',' && $stream[$pos][1] !== $close) {
				skipConfigValue($stream, $pos);
			} elseif ($keyed) {
				if ((is_string($key) || is_int($key)) && $value !== MIGRATE_COMPLEX_VALUE) {
					$result[$key] = $value;
				}
			} elseif ($value !== MIGRATE_COMPLEX_VALUE) {
				$result[$autoIndex++] = $value;
			} else {
				$autoIndex++;
			}

			if ($pos < $n && $stream[$pos][1] === ',') {
				$pos++;
			} else {
				break;
			}
		}
		if ($pos < $n && $stream[$pos][1] === $close) {
			$pos++;
		}
		return $result;
	}

	if ($id === T_CONSTANT_ENCAPSED_STRING) {
		$pos++;
		return unquotePhpString($text);
	}
	if ($id === T_LNUMBER) {
		$pos++;
		return (int) $text;
	}
	if ($id === T_DNUMBER) {
		$pos++;
		return (float) $text;
	}
	if ($id === T_STRING) {
		$lower = strtolower($text);
		if ($lower === 'true' || $lower === 'false') {
			$pos++;
			return $lower === 'true';
		}
		if ($lower === 'null') {
			$pos++;
			return null;
		}
	}
	if ($text === '-' && ($pos + 1) < $n && in_array($stream[$pos + 1][0], [T_LNUMBER, T_DNUMBER], true)) {
		$num = $stream[$pos + 1];
		$pos += 2;
		return $num[0] === T_LNUMBER ? -(int) $num[1] : -(float) $num[1];
	}

	// Anything else (function call, expression, constant) — skip it unevaluated.
	skipConfigValue($stream, $pos);
	return MIGRATE_COMPLEX_VALUE;
}

/** Advances $pos past a value we won't evaluate, to the next top-level ',' or close. */
function skipConfigValue(array $stream, int &$pos): void {
	$n = count($stream);
	$depth = 0;
	while ($pos < $n) {
		$tok = $stream[$pos][1];
		if ($tok === '[' || $tok === '(' || $tok === '{') {
			$depth++;
		} elseif ($tok === ']' || $tok === ')' || $tok === '}') {
			if ($depth === 0) {
				return;
			}
			$depth--;
		} elseif ($tok === ',' && $depth === 0) {
			return;
		}
		$pos++;
	}
}

/** Decodes a single- or double-quoted PHP string literal token. */
function unquotePhpString(string $literal): string {
	if (strlen($literal) < 2) {
		return $literal;
	}
	$quote = $literal[0];
	$inner = substr($literal, 1, -1);
	if ($quote === "'") {
		return str_replace(['\\\\', "\\'"], ['\\', "'"], $inner);
	}
	return stripcslashes($inner);
}

/**
 * Reads the old log, which uses the same nine "<>" fields as souko.log, and
 * re-hashes the deletion passwords — the old boards stored them in the clear,
 * and password_verify() would reject every one of them.
 *
 * @return array<int,string[]> parsed entries, newest first
 */
function readOldLog(string $logFile, array &$messages): array {
	if (!file_exists($logFile)) {
		$messages[] = 'NOTE: no upload log found, the board will start empty.';
		return [];
	}

	$entries = [];
	$rehashed = 0;
	$malformed = 0;

	foreach (file($logFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
		if (trim($line) === '') continue;

		$fields = explode('<>', $line);
		if (count($fields) !== 9) {
			$malformed++;
			continue;
		}

		if ($fields[7] !== '' && !isHashed($fields[7])) {
			$fields[7] = generatePasswordHash($fields[7]);
			$rehashed++;
		}

		$entries[] = $fields;
	}

	if ($rehashed > 0) {
		$messages[] = "re-hashed $rehashed plaintext deletion password(s).";
	}

	if ($malformed > 0) {
		$messages[] = "WARNING: dropped $malformed malformed log line(s).";
	}

	return $entries;
}

function isHashed(string $password): bool {
	return password_get_info($password)['algo'] !== null;
}

function hashOwnerPassword(string $oldPassword, array &$messages): string {
	if ($oldPassword === '') {
		$messages[] = 'WARNING: the old board had no admin password. Set one from the admin Boards page before handing it back to its owner.';
		return generatePasswordHash(bin2hex(random_bytes(16)));
	}

	$messages[] = 'owner password carried over (the old one still works).';
	return generatePasswordHash($oldPassword);
}

/**
 * The old denylist and hardBanList both become plain board bans — the new
 * per-board ban list is the only ban scope a board owner has.
 *
 * @return string[]
 */
function collectOldBans(array $oldConf): array {
	$bans = [];

	foreach (array_merge($oldConf['denylist'] ?? [], $oldConf['hardBanList'] ?? []) as $ip) {
		$ip = trim((string) $ip);

		// 0.0.0.0 was the placeholder the old config shipped with
		if ($ip === '' || $ip === '0.0.0.0' || !filter_var($ip, FILTER_VALIDATE_IP)) {
			continue;
		}

		$bans[$ip] = true;
	}

	return array_keys($bans);
}

/**
 * The old board's theme, if the current instance still ships it. Anything else
 * is dropped so a board can't name a stylesheet that isn't there.
 */
function installedTheme(string $themeName): string {
	if ($themeName === '') {
		return '';
	}

	$themeManager = new themeManager(ROOT_DIR . '/static/css/themes', 'static/css/themes');

	return in_array($themeName, $themeManager->getThemeNames(), true) ? $themeName : '';
}

/**
 * Points out old per-board settings the current layout has no place for, so
 * whoever runs this knows what changed rather than finding out later.
 */
function reportDroppedSettings(array $oldConf, array $conf, array &$messages): void {
	$dropped = [];
	foreach (DROPPED_SETTINGS as $key) {
		if (isset($oldConf[$key])) {
			$dropped[] = $key;
		}
	}

	if ($dropped) {
		$messages[] = 'NOTE: these old settings have no equivalent and were not carried over: ' . implode(', ', $dropped) . '.';
	}

	// old limits were bytes, the current per-board limits are instance-wide and in megabytes
	if (isset($oldConf['maxAmountOfFiles']) && (int) $oldConf['maxAmountOfFiles'] > (int) $conf['boardMaxAmountOfFiles']) {
		$messages[] = 'NOTE: the old board allowed ' . (int) $oldConf['maxAmountOfFiles'] . ' files, boardMaxAmountOfFiles is ' . (int) $conf['boardMaxAmountOfFiles'] . '.';
	}
}

function scaffold(string $boardDir, array $conf, array &$messages): bool {
	$directories = [
		$boardDir,
		$boardDir . '/' . rtrim($conf['uploadDir'], '/'),
		$boardDir . '/' . rtrim($conf['thumbDir'], '/'),
		$boardDir . '/data',
	];

	foreach ($directories as $directory) {
		if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
			$messages[] = "FAILED: could not create $directory.";
			return false;
		}
	}

	file_put_contents($boardDir . '/data/.htaccess', "Order Deny,Allow\nDeny from all\n");
	file_put_contents($boardDir . '/data/banned_hashes.dat', '');

	$stub = "<?php\n"
		. "// Generated board stub — hands the request to the main uploader script.\n"
		. "\$boardUri = basename(__DIR__);\n"
		. "require dirname(__DIR__, 2) . '/" . $conf['mainScript'] . "';\n";

	return file_put_contents($boardDir . '/index.php', $stub) !== false;
}

/**
 * Copies a directory's files, skipping any extension that isn't on the upload
 * whitelist or that should be served as text — the target is web-served, so a
 * stray .php/.svg in an old src/ must not carry over as executable/renderable.
 *
 * @return array{0:int,1:int} [copied, skipped]
 */
function copyDirContents(string $sourceDir, string $targetDir, array $allowedExtensions, array $blockedExtensions): array {
	if (!is_dir($sourceDir)) {
		return [0, 0];
	}

	$allowed = array_map('strtolower', $allowedExtensions);
	$blocked = array_map('strtolower', $blockedExtensions);

	$copied = 0;
	$skipped = 0;
	foreach (scandir($sourceDir) ?: [] as $entry) {
		$source = $sourceDir . '/' . $entry;
		if ($entry === '.' || $entry === '..' || !is_file($source)) {
			continue;
		}

		$ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
		if ($ext === '' || !in_array($ext, $allowed, true) || in_array($ext, $blocked, true)) {
			$skipped++;
			continue;
		}

		if (copy($source, $targetDir . '/' . $entry)) {
			$copied++;
		}
	}

	return [$copied, $skipped];
}

/**
 * Old thumbnails were "<prefix><id>_thumb.<source extension>" but always held
 * JPEG data; the current scheme is "<prefix><id><thumb_suffix>.<thumbnailExtension>".
 */
function copyThumbnails(string $sourceDir, string $targetDir, array $entries, string $prefix, array $conf): int {
	if (!is_dir($sourceDir)) {
		return 0;
	}

	$copied = 0;
	foreach ($entries as $fields) {
		$id = sprintf('%03d', (int) $fields[0]);

		$matches = glob($sourceDir . '/' . $prefix . $id . '_thumb.*') ?: [];
		if (!$matches) {
			continue;
		}

		$newName = $prefix . $id . $conf['thumb_suffix'] . '.' . $conf['thumbnailExtension'];
		if (copy($matches[0], $targetDir . '/' . $newName)) {
			$copied++;
		}
	}

	return $copied;
}

function writeLog(string $logFile, array $entries): void {
	$lines = '';
	foreach ($entries as $fields) {
		$lines .= implode('<>', $fields) . "\n";
	}

	file_put_contents($logFile, $lines, LOCK_EX);
}

function highestID(array $entries): int {
	$highest = 0;
	foreach ($entries as $fields) {
		$highest = max($highest, (int) $fields[0]);
	}
	return $highest;
}

function cleanText(string $value, int $maxLength): string {
	$value = strip_tags(str_replace(["\r\n", "\r", "\n", "\t", "\0"], '', $value));
	return trim(mb_substr($value, 0, $maxLength));
}

function parseOptions(array $argv): array {
	$options = ['dry-run' => false, 'source' => null];

	foreach (array_slice($argv, 1) as $argument) {
		if ($argument === '--dry-run') {
			$options['dry-run'] = true;
		} elseif (str_starts_with($argument, '--source=')) {
			$options['source'] = substr($argument, strlen('--source='));
		} else {
			exit("Unknown option: $argument\nUsage: php migrateUserBoards.php [--dry-run] [--source=path]\n");
		}
	}

	return $options;
}
