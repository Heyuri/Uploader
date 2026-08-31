<?php
namespace TwintailUploader\Classes;

/**
 * Minimal Cloudflare API client.
 * Used to purge the CDN cache of files that are removed from the uploader.
 */
class cloudflareAPI {
	private const API_BASE = 'https://api.cloudflare.com/client/v4/';
	private const MAX_URLS_PER_REQUEST = 30; // cloudflare's limit per purge call
	private const TIMEOUT = 5;

	private string $apiToken;
	private string $zoneId;
	private string $baseUrl;
	private bool $enabled;

	public function __construct(array $conf) {
		$this->apiToken = trim((string) ($conf['cloudflareApiToken'] ?? ''));
		$this->zoneId = trim((string) ($conf['cloudflareZoneId'] ?? ''));
		$this->baseUrl = rtrim(trim((string) ($conf['cloudflareBaseUrl'] ?? '')), '/');
		$this->enabled = !empty($conf['cloudflareEnabled']) && $this->apiToken !== '' && $this->zoneId !== '';
	}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * Purges the cache for the given paths (relative to the uploader root, e.g. "src/up001.jpg").
	 * Failures are logged and never interrupt the caller.
	 */
	public function purgeFiles(array $paths): bool {
		if (!$this->enabled) {
			return false;
		}

		$urls = [];
		foreach ($paths as $path) {
			$url = $this->buildUrl($path);
			if ($url !== '') {
				$urls[] = $url;
			}
		}

		$urls = array_values(array_unique($urls));
		if (empty($urls)) {
			return false;
		}

		$success = true;
		foreach (array_chunk($urls, self::MAX_URLS_PER_REQUEST) as $chunk) {
			if (!$this->purgeCache(['files' => $chunk])) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Purges the entire zone cache.
	 */
	public function purgeEverything(): bool {
		if (!$this->enabled) {
			return false;
		}

		return $this->purgeCache(['purge_everything' => true]);
	}

	/**
	 * Turns a path relative to the uploader root into an absolute URL.
	 */
	public function buildUrl(string $path): string {
		$path = ltrim(trim($path), '/');
		if ($path === '') {
			return '';
		}

		$base = $this->baseUrl !== '' ? $this->baseUrl : $this->guessBaseUrl();
		if ($base === '') {
			return '';
		}

		return $base . '/' . implode('/', array_map('rawurlencode', explode('/', $path)));
	}

	/**
	 * Falls back to the URL the current request came in on when no base URL is configured.
	 */
	private function guessBaseUrl(): string {
		$host = $_SERVER['HTTP_HOST'] ?? '';
		if ($host === '') {
			return '';
		}

		$scheme = 'http';
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
			$scheme = 'https';
		} elseif (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
			$scheme = 'https';
		}

		$dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
		$dir = rtrim($dir, '/');

		return $scheme . '://' . $host . $dir;
	}

	private function purgeCache(array $body): bool {
		$response = $this->request('POST', 'zones/' . rawurlencode($this->zoneId) . '/purge_cache', $body);

		if ($response === null || empty($response['success'])) {
			$errors = isset($response['errors']) ? json_encode($response['errors']) : 'no response';
			error_log('Cloudflare cache purge failed: ' . $errors);
			return false;
		}

		return true;
	}

	/**
	 * @return array|null Decoded response, or null when the request itself failed.
	 */
	private function request(string $method, string $endpoint, array $body): ?array {
		if (!function_exists('curl_init')) {
			error_log('Cloudflare API: cURL extension is not available.');
			return null;
		}

		$payload = json_encode($body);

		$curl = curl_init(self::API_BASE . $endpoint);
		curl_setopt_array($curl, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => self::TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $this->apiToken,
				'Content-Type: application/json',
				'Content-Length: ' . strlen($payload),
			],
		]);

		$result = curl_exec($curl);
		$error = curl_error($curl);
		curl_close($curl);

		if ($result === false) {
			error_log('Cloudflare API request failed: ' . $error);
			return null;
		}

		$decoded = json_decode($result, true);
		return is_array($decoded) ? $decoded : null;
	}
}
