<?php
	$flood_cooldown = ROOTPATH.'mod/cooldown.log';
	$file_content = file_get_contents($flood_cooldown);
	$f_buffer = fopen($flood_cooldown, 'w') or error("Could not open anti-flood cooldown log file!");
	$currentTime = time();
	if(!($currentTime - intval($file_content) > $cooldown)){
		error("Upload caught by anti flood script: script ran too many times in past ".$cooldown." seconds");
	} else {
		fwrite($f_buffer, $currentTime);
		fclose($f_buffer);
	}
