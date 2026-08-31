<?php
namespace TwintailUploader\Functions;
use TwintailUploader\Classes\uploadEntry;

function isDataEmpty($data) {
	if(count($data) < 8){
		return true;
	}
	return false;
}

function logFileData($newID, $fileExtension, $comment, $mimeType, $passwordHash, $fileName, string $storedName = '', int $expiresTime = 0, string $fileHash = ''): uploadEntry {
	return new uploadEntry([
		$newID,
		$fileExtension,
		$comment,
		getUserIP(),
		time(),
		$_FILES['upfile']['size'],
		$mimeType,
		$passwordHash,
		$fileName,
		$storedName,
		$expiresTime,
		$fileHash
	]);
}

function generatePasswordHash(string $password): string {
	// If password is empty, return an empty string (can't be deleted by users either)
	if(empty($password)) {
		return '';
	}

	// cost of the password
	// the higher the cost - the longer it takes to generate, but harder to bruteforce
	// since a password is generated for everyone post, we'll keep the cost low
	$cost = 8;

	// options for the bcrypt hash
	$options = [
		'cost' => $cost,
	];

	// hash the password
	$passwordHash = password_hash($password, PASSWORD_BCRYPT, $options);

	// return hash
	return $passwordHash;
}
/**
 * Creates the flat files a scope needs, so a fresh install only has to provide
 * a writable data/ rather than a list of files to touch by hand.
 *
 * Every one of these is a file the uploader appends to; starting them empty is
 * the same as having no uploads, no bans and no history yet. count.log starts
 * at 0 and is reconciled against the log's highest ID on the next call, so an
 * existing log never has its IDs reused.
 *
 * Only missing files are touched — an existing one is never truncated — and a
 * file that can't be created is left to the code that actually needs it to
 * report, since not every request needs every file.
 */
function ensureDataFiles(array $conf, string $dataDir): void {
	if (!is_dir($dataDir)) {
		return;
	}

	$files = [
		$conf['logFile'] => '',
		$conf['counterFile'] => '0',
		'banlist.dat' => '',
		'banned_hashes.dat' => '',
		$conf['actionLogFile'] ?? 'actions.log' => '',
	];

	foreach ($files as $name => $initialContents) {
		if ($name === '' || file_exists($dataDir . $name)) {
			continue;
		}

		@file_put_contents($dataDir . $name, $initialContents);
	}
}
