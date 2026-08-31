<?php
namespace TwintailUploader\Classes;

class banChecker {
	private string $dataDir;
	private string $banListFile;
	private string $bannedHashesFile;

	/** Ban files of a wider scope that are checked but never written to */
	private array $inheritedBanListFiles = [];
	private array $inheritedBannedHashesFiles = [];

	/**
	 * @param string  $dataDir        scope bans are read from and written to
	 * @param ?string $inheritedFromDir wider scope that is only read (a board
	 *                                  inherits the instance-wide ban lists)
	 */
	public function __construct(string $dataDir, ?string $inheritedFromDir = null) {
		$this->dataDir = rtrim($dataDir, '/') . '/';
		$this->banListFile = $this->dataDir . 'banlist.dat';
		$this->bannedHashesFile = $this->dataDir . 'banned_hashes.dat';

		if ($inheritedFromDir !== null && rtrim($inheritedFromDir, '/') . '/' !== $this->dataDir) {
			$inheritedDir = rtrim($inheritedFromDir, '/') . '/';
			$this->inheritedBanListFiles[] = $inheritedDir . 'banlist.dat';
			$this->inheritedBannedHashesFiles[] = $inheritedDir . 'banned_hashes.dat';
		}

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
		foreach (array_merge([$this->banListFile], $this->inheritedBanListFiles) as $file) {
			if ($this->isInFile($file, $host)) {
				return true;
			}
		}

		return false;
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

		foreach (array_merge([$this->bannedHashesFile], $this->inheritedBannedHashesFiles) as $file) {
			if ($this->isInFile($file, $hash)) {
				return true;
			}
		}

		return false;
	}

	private function isInFile(string $file, string $needle): bool {
		// An empty needle would match a blank line in the file, so an unparseable
		// address or a stray newline in the ban list must never count as a hit.
		if ($needle === '' || !file_exists($file)) {
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
		// one entry per line — never let a value smuggle in extra lines
		$entry = str_replace(["\r", "\n", "\t", "\0"], '', $entry);
		if ($entry === '' || $this->isInFile($file, $entry)) {
			return;
		}

		$dir = dirname($file);
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		file_put_contents($file, $entry . "\n", FILE_APPEND | LOCK_EX);
	}
}
