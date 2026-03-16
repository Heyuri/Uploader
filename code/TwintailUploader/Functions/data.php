<?php
namespace TwintailUploader\Functions;
use TwintailUploader\Classes\uploadEntry;

function isDataEmpty($data) {
	if(count($data) < 8){
		return true;
	}
	return false;
}

function logFileData($newID, $fileExtension, $comment, $mimeType, $passwordHash, $fileName): uploadEntry {
	return new uploadEntry([
		$newID, 
		$fileExtension, 
		$comment, 
		getUserIP(),
		time(), 
		$_FILES['upfile']['size'], 
		$mimeType, 
		$passwordHash, 
		$fileName
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