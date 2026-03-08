<?php
namespace HeyuriUploader\Functions;

function getUserIP() {
	return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
}