<?php
namespace TwintailUploader\Classes;

/**
 * The visitor's display preferences, stored in one cookie as a JSON object
 * keyed by setting name.
 *
 * The set of settings is whatever $conf['defaultCookieValues'] declares — add a
 * key there and it is read, written and defaulted automatically. Values keep
 * that array's vocabulary ('checked' or ''), so they can be dropped straight
 * into a checkbox attribute.
 */
class cookieSettingsManager {
	private const COOKIE_NAME = 'settings';
	private const COOKIE_LIFETIME = 365 * 24 * 3600;

	private array $defaultCookieValues;

	public function __construct(array $defaultCookieValues) {
		$this->defaultCookieValues = $defaultCookieValues;
	}

	public function loadCookieSettings(): void {
		$settings = $this->getSettings();

		if (isset($_POST['action']) && $_POST['action'] === "setUserSettings") {
			// an unchecked box posts nothing, so every known key is read back
			foreach (array_keys($this->defaultCookieValues) as $key) {
				$settings[$key] = $this->normalize($_POST[$key] ?? '');
			}
		}

		$encoded = json_encode($settings);

		setcookie(self::COOKIE_NAME, $encoded, time() + self::COOKIE_LIFETIME);
		$_COOKIE[self::COOKIE_NAME] = $encoded;
	}

	/**
	 * Every setting keyed by name, falling back to the configured default for
	 * anything the cookie doesn't carry.
	 */
	public function getSettings(): array {
		$stored = $this->decode($_COOKIE[self::COOKIE_NAME] ?? '');

		$settings = [];
		foreach ($this->defaultCookieValues as $key => $default) {
			$settings[$key] = $this->normalize($stored[$key] ?? $default);
		}

		return $settings;
	}

	private function decode(string $rawCookie): array {
		if ($rawCookie === '') {
			return [];
		}

		$decoded = json_decode($rawCookie, true);

		return is_array($decoded) ? $decoded : $this->decodeLegacyCookie($rawCookie);
	}

	/**
	 * Cookies written before this was JSON were "<>"-delimited and positional,
	 * in defaultCookieValues order. Visitors still carrying one keep their
	 * preferences; the next write replaces it with JSON.
	 */
	private function decodeLegacyCookie(string $rawCookie): array {
		$values = explode('<>', $rawCookie);
		$keys = array_keys($this->defaultCookieValues);

		if (count($values) !== count($keys)) {
			return [];
		}

		return array_combine($keys, $values);
	}

	/**
	 * Reduces anything a cookie or form might carry to the two values the
	 * templates expect, so a hand-edited cookie can't reach the markup.
	 */
	private function normalize(mixed $value): string {
		if (is_string($value)) {
			return $value === '' ? '' : 'checked';
		}

		return $value ? 'checked' : '';
	}
}
