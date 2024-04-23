<?php
/* IP Check Module */

// get user's current IP address
function getIP() {
	return $_SERVER['REMOTE_ADDR'] ?? 1337; //if _SERVER returns null or is empty it'll return 1337 instead
}

// displays error if $ip is in denylist
function matchIP_to_denylist(string $ip) {
	global $banlist;
	if(in_array($ip, $banlist)) {
		error("Your IP has been banned from uploading images.");
	}
}

// more will be added later

