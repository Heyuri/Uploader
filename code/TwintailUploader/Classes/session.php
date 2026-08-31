<?php
namespace TwintailUploader\Classes;

class session {
	public function __construct() {
		if (session_status() == PHP_SESSION_NONE) {
			$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
				|| ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

			session_set_cookie_params([
				'httponly' => true,
				'samesite' => 'Lax',
				'secure' => $https,
			]);

			session_start();
		}
	}

	// Rotate the session ID (e.g. right after a privilege change) to defeat fixation
	public function regenerate(): void {
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_regenerate_id(true);
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
