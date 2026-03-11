<?php

namespace HeyuriUploader\Functions;

use HeyuriUploader\Classes\languageManager;
use InvalidArgumentException;

function redirect(string $url): void {
	// Validate that the URL is not empty
	if (empty($url)) {
		throw new InvalidArgumentException("URL cannot be empty.");
	}

	// Ensure no output has been sent yet
	if (headers_sent()) {
		// If headers are already sent, use JavaScript as a fallback
		echo "<script>window.location.href='" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "';</script>";
		echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . "'></noscript>";
		exit;
	}

	// Redirect using HTTP header
	header("Location: " . $url);
	exit; // Always call exit() after redirect
}

function forceJapaneseForJpUsers(languageManager $languageManager, bool $forceJapanese): void {
	// If the option is enabled and the user has Japanese browser settings, force the language to Japanese
	if (
		$forceJapanese 
		&& file_exists(__DIR__ . '/../lang/ja.php')
		&& isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
		&& preg_match('/\bja\b/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
			// Override the configured display language
			$languageManager->setLanguage('ja');
	}
}