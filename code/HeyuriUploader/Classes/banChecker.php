<?php
namespace HeyuriUploader\Classes;

class banChecker {
	private string $dataDir = 'data/';
	private string $banListFile;
	private string $bannedHashesFile;

	public function __construct() {
		$this->banListFile = $this->dataDir . 'banlist.dat';
		$this->bannedHashesFile = $this->dataDir . 'banned_hashes.dat';
		$this->ensureFilesExist();
	}

	private function ensureFilesExist(): void {
		if (!is_dir($this->dataDir)) {
			mkdir($this->dataDir, 0755, true);
		}

		if (!file_exists($this->banListFile)) {
			file_put_contents($this->banListFile, '');
		}

		if (!file_exists($this->bannedHashesFile)) {
			file_put_contents($this->bannedHashesFile, '');
		}
	}

	public function isBanned(string $host): bool {
		if ($host === "1337") return false;
		return $this->isInFile($this->banListFile, $host);
	}

	public function addBan(string $ip): void {
		$this->addToFile($this->banListFile, $ip);
	}

	public function addBannedFileHash(string $hash): void {
		$this->addToFile($this->bannedHashesFile, $hash);
	}

	public function isFileBanned(string $filePath): bool {
		if (!file_exists($filePath)) {
			return false;
		}

		$hash = hash_file('sha256', $filePath);
		if ($hash === false) {
			return false;
		}

		return $this->isInFile($this->bannedHashesFile, $hash);
	}

	private function isInFile(string $file, string $needle): bool {
		if (!file_exists($file)) {
			return false;
		}

		$handle = fopen($file, 'r');
		if (!$handle) {
			return false;
		}

		while (($line = fgets($handle)) !== false) {
			if (trim($line) === $needle) {
				fclose($handle);
				return true;
			}
		}

		fclose($handle);
		return false;
	}

	public function getBannedIPs(): array {
		return $this->getFileEntries($this->banListFile);
	}

	public function getBannedHashes(): array {
		return $this->getFileEntries($this->bannedHashesFile);
	}

	public function removeBans(array $entries): void {
		$this->removeFromFile($this->banListFile, $entries);
	}

	public function removeBannedHashes(array $entries): void {
		$this->removeFromFile($this->bannedHashesFile, $entries);
	}

	private function getFileEntries(string $file): array {
		if (!file_exists($file)) {
			return [];
		}

		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		return $lines !== false ? $lines : [];
	}

	private function removeFromFile(string $file, array $entries): void {
		if (!file_exists($file)) {
			return;
		}

		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		if ($lines === false) {
			return;
		}

		$lines = array_filter($lines, function($line) use ($entries) {
			return !in_array(trim($line), $entries, true);
		});

		file_put_contents($file, implode("\n", $lines) . (empty($lines) ? '' : "\n"), LOCK_EX);
	}

	private function addToFile(string $file, string $entry): void {
		if ($this->isInFile($file, $entry)) {
			return;
		}

		$dir = dirname($file);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		file_put_contents($file, $entry . "\n", FILE_APPEND | LOCK_EX);
	}
}
