<?php
namespace HeyuriUploader\Classes;

class session {
	public function __construct() {
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}
	}

	// Set a session value
	public function set(string $key, mixed $value) {
		$_SESSION[$key] = $value;
	}

	// Get a session value
	public function get(string $key) {
		return $_SESSION[$key] ?? null;
	}

	// Check if session exists
	public function has(string $key) {
		return isset($_SESSION[$key]);
	}

	// Destroy session
	public function destroy() {
		session_unset();
		session_destroy();
	}
}
?>
