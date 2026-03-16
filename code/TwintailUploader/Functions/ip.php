<?php
namespace TwintailUploader\Functions;

function getUserIP() {
	return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
}