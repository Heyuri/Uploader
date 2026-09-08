<?php
namespace TwintailUploader\Classes;

/**
 * Admin-set values every user board inherits, stored in data/boardDefaults.log.
 *
 * One "key<>value" line per override. A key that is absent falls back to
 * config.php, so the file is a layer between the global config and
 * board::applyToConfig(). Only KEYS may be stored — anything else on disk is
 * ignored — and a value is coerced to the type config.php gives the key both
 * when saved and when read, so a hand-edited file is no more trusted than a
 * form post.
 */
class boardDefaultsRepository {
	/** Keys boards inherit that an admin may override for all of them at once */
	public const KEYS = [
		'boardMaxAmountOfFiles',
		'boardMaxTotalSize',
		'boardMaxUploadSize',
		'coolDownTime',
		'filesPerListing',
		'maxCommentSize',
		'deleteOldestOnMaxFiles',
		'allowDisplayingAllEntries',
		'unlisted',
		'temporaryHosting',
		'temporaryHostingHours',
		'temporaryFileNameLength',
		'actionLog',
		'actionLogMaxEntries',
		'defaultTheme',
	];

	public function __construct(private string $file) {}

	/**
	 * @return array<string,string> stored overrides, whitelisted keys only
	 */
	public function getAll(): array {
		if (!is_file($this->file)) {
			return [];
		}

		$values = [];
		foreach (file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
			$parts = explode('<>', $line, 2);
			if (count($parts) === 2 && in_array($parts[0], self::KEYS, true)) {
				$values[$parts[0]] = $parts[1];
			}
		}
		return $values;
	}

	/**
	 * Lays the stored overrides over the global config, typed like it.
	 */
	public function apply(array $conf): array {
		foreach ($this->getAll() as $key => $value) {
			if (array_key_exists($key, $conf) && !is_array($conf[$key])) {
				$conf[$key] = self::coerce($conf[$key], $value);
			}
		}
		return $conf;
	}

	/**
	 * Replaces the stored overrides. An empty value drops its key back to
	 * config.php.
	 *
	 * @param array $values  key => submitted value
	 * @param array $conf    the global config, for the type of each key
	 * @return array<string,string> what was stored
	 */
	public function save(array $values, array $conf): array {
		$stored = [];
		foreach (self::KEYS as $key) {
			$value = $values[$key] ?? '';
			if (!is_string($value) || $value === '' || !array_key_exists($key, $conf) || is_array($conf[$key])) {
				continue;
			}
			$stored[$key] = self::serialize(self::coerce($conf[$key], $value));
		}

		$lines = '';
		foreach ($stored as $key => $value) {
			$lines .= $key . '<>' . $value . "\n";
		}

		$dir = dirname($this->file);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents($this->file, $lines, LOCK_EX);

		return $stored;
	}

	/**
	 * Reads $value as the type of $like, the way the config editor does.
	 */
	public static function coerce(bool|int|string|float|null $like, string $value): bool|int|string {
		if (is_bool($like)) {
			return $value === '1';
		}
		if (is_int($like)) {
			return (int) $value;
		}
		// the same scrubbing every log applies to free text
		$value = str_replace(["\r\n", "\r", "\n", "\t", "\0"], '', $value);
		return str_replace('<>', '‹›', $value);
	}

	private static function serialize(bool|int|string $value): string {
		if (is_bool($value)) {
			return $value ? '1' : '0';
		}
		return (string) $value;
	}
}
