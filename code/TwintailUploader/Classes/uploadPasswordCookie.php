<?php
namespace TwintailUploader\Classes;

/**
 * The deletion password a visitor last uploaded with, kept in its own cookie so
 * the upload form can prefill it.
 *
 * It lives apart from cookieSettingsManager because that cookie's vocabulary is
 * checkbox state ('checked' or ''), while this is free text the visitor typed.
 * Like the settings cookie it is written with the default path, so a board
 * keeps its own.
 */
class uploadPasswordCookie {
	private const COOKIE_NAME = 'uploadPassword';
	private const COOKIE_LIFETIME = 365 * 24 * 3600;
	private const MAX_LENGTH = 64;

	private const GENERATED_LENGTH = 10;

	/**
	 * Alphabet for a generated password. Deliberately missing the characters
	 * that look like each other (0/O, 1/l/I), since the whole point of showing
	 * one in the clear is that a visitor can copy it down and read it back.
	 */
	private const GENERATED_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';

	/**
	 * Remember the password an upload was just made with. An empty password
	 * leaves whatever is stored alone — uploading without one isn't a request
	 * to forget the old one.
	 */
	public function remember(string $password): void {
		$password = $this->sanitize($password);

		if ($password === '') {
			return;
		}

		$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
			|| ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

		// a page that has already started printing can't be given a cookie —
		// remember it for the rest of this request rather than warn into the
		// middle of the markup
		if (!headers_sent()) {
			setcookie(self::COOKIE_NAME, $password, [
				'expires' => time() + self::COOKIE_LIFETIME,
				'httponly' => true,
				'samesite' => 'Lax',
				'secure' => $https,
			]);
		}

		$_COOKIE[self::COOKIE_NAME] = $password;
	}

	/** The stored password, or '' when there isn't one. */
	public function get(): string {
		return $this->sanitize($_COOKIE[self::COOKIE_NAME] ?? '');
	}

	/**
	 * The password to offer on the upload form: the stored one, or a fresh
	 * random one when the visitor hasn't got one yet.
	 *
	 * A generated password is remembered straight away, so it is the same on
	 * the next visit and a visitor who wrote it down can still delete with it.
	 * That means this **must be called before any output** — it sets a cookie.
	 * The first password a visitor types replaces it like any other.
	 */
	public function ensure(): string {
		$stored = $this->get();

		if ($stored !== '') {
			return $stored;
		}

		$generated = $this->generate();
		$this->remember($generated);

		return $generated;
	}

	/** A random password, from the unambiguous alphabet above. */
	private function generate(): string {
		$alphabet = self::GENERATED_ALPHABET;
		$lastIndex = strlen($alphabet) - 1;

		$password = '';
		for ($i = 0; $i < self::GENERATED_LENGTH; $i++) {
			$password .= $alphabet[random_int(0, $lastIndex)];
		}

		return $password;
	}

	/**
	 * Control characters would break out of the value attribute's line and a
	 * hand-edited cookie shouldn't be able to hand the form an essay, so both
	 * are dealt with on the way in and again on the way out.
	 */
	private function sanitize(string $password): string {
		$password = preg_replace('/[\x00-\x1F\x7F]/', '', $password) ?? '';

		return mb_substr($password, 0, self::MAX_LENGTH, 'UTF-8');
	}
}
