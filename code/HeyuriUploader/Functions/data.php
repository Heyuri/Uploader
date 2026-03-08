<?php
namespace HeyuriUploader\Functions;
use HeyuriUploader\Classes\uploadEntry;

function isDataEmpty($data) {
	if(count($data) < 8){
		return true;
	}
	return false;
}

function logFileData($newID, $fileExtension, $comment, $mimeType, $password, $fileName): uploadEntry {
	return new uploadEntry([
		$newID, 
		$fileExtension, 
		$comment, 
		getUserIP(),
		time(), 
		$_FILES['upfile']['size'], 
		$mimeType, 
		$password ? password_hash($password, PASSWORD_BCRYPT) : '', 
		$fileName
	]);
}