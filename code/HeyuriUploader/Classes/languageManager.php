<?php
namespace HeyuriUploader\Classes;

class languageManager {
	private string $langDir;
	private string $language;
	private array $strings = [];

	public function __construct(string $langDir, string $language = 'en') {
		$this->langDir = rtrim($langDir, '/');
		$this->language = $language;
		$this->loadLanguage();
	}

	private function loadLanguage(): void {
		$filePath = $this->langDir . '/' . basename($this->language) . '.php';

		if (!file_exists($filePath)) {
			throw new \Exception("Language file not found: {$this->language}");
		}

		$decoded = require $filePath;

		if (!is_array($decoded)) {
			throw new \Exception("Invalid language file format: {$this->language}");
		}

		$this->strings = $this->flatten($decoded);
	}

	public function setLanguage(string $language): void {
		$this->language = $language;
		$this->loadLanguage();
	}

	/**
	 * Flatten a nested array into dot-notation keys.
	 * e.g. ['nav' => ['back' => 'Back']] becomes ['nav.back' => 'Back']
	 */
	private function flatten(array $array, string $prefix = ''): array {
		$result = [];
		foreach ($array as $key => $value) {
			$fullKey = $prefix === '' ? $key : $prefix . '.' . $key;
			if (is_array($value)) {
				$result = array_merge($result, $this->flatten($value, $fullKey));
			} else {
				$result[$fullKey] = $value;
			}
		}
		return $result;
	}

	/**
	 * Get a translated string by dot-notation key.
	 * Supports sprintf-style replacement when extra args are passed.
	 */
	public function get(string $key, ...$args): string {
		$value = $this->strings[$key] ?? $key;
		if (!empty($args)) {
			$value = sprintf($value, ...$args);
		}
		return $value;
	}

	/**
	 * Return all flattened strings (for template injection).
	 */
	public function getAll(): array {
		$result = [];
		foreach ($this->strings as $key => $value) {
			$result['lang.' . $key] = $value;
		}
		return $result;
	}

	public function getLanguage(): string {
		return $this->language;
	}
}
