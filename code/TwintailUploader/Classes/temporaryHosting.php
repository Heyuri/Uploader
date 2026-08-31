<?php
namespace TwintailUploader\Classes;

use TwintailUploader\Controllers\actionLogController;

/**
 * Temporary hosting: uploads are stored under a random, unguessable name and
 * swept away once their lifetime is up.
 *
 * The name is random rather than derived from the file, so re-uploading a file
 * that already expired never brings its old URL back.
 */
class temporaryHosting {
	// how often the lazy sweep is allowed to run, in seconds
	private const SWEEP_INTERVAL = 60;
	private const NAME_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';
	private const STAMP_FILE = 'expiry.stamp';

	public function __construct(
		private array $conf,
		private logFile $logFile,
		private uploadedFileRepository $uploadedFileRepository,
		private ?actionLogController $actionLog = null
	) {}

	public function isEnabled(): bool {
		return !empty($this->conf['temporaryHosting']) && $this->getLifetime() > 0;
	}

	/**
	 * How long an upload is kept for, in seconds.
	 */
	public function getLifetime(): int {
		return max(0, (int) ($this->conf['temporaryHostingHours'] ?? 0)) * 3600;
	}

	public function getLifetimeHours(): int {
		return (int) ($this->getLifetime() / 3600);
	}

	/**
	 * Time an upload made at $uploadedAt is deleted at, 0 when it never expires.
	 */
	public function expiryTimeFor(int $uploadedAt): int {
		return $this->isEnabled() ? $uploadedAt + $this->getLifetime() : 0;
	}

	/**
	 * SHA-256 of a file's contents, or '' when there is nothing to dedupe
	 * against — permanent uploads are never hashed, so they never collapse into
	 * each other.
	 */
	public function hashFile(string $filePath): string {
		if (!$this->isEnabled() || !file_exists($filePath)) {
			return '';
		}

		$hash = hash_file('sha256', $filePath);

		return $hash === false ? '' : $hash;
	}

	/**
	 * The live entry this upload is a re-upload of, if there is one.
	 *
	 * The extension has to match too: identical bytes offered as a different
	 * type would otherwise be handed back under a URL the uploader didn't ask
	 * for. An entry that is past its expiry but not swept yet doesn't count —
	 * its file is about to go.
	 */
	public function findDuplicate(string $fileHash, string $fileExtension): ?uploadEntry {
		if (!$this->isEnabled() || $fileHash === '') {
			return null;
		}

		$now = time();

		return $this->logFile->findEntry(fn(uploadEntry $entry) =>
			$entry->getFileHash() === $fileHash
			&& $entry->getFileExtension() === $fileExtension
			&& !$entry->isExpired($now)
			&& file_exists($entry->getFilePath($this->conf))
		);
	}

	/**
	 * Name the file is stored under, without extension.
	 */
	public function storedNameFor(int $id): string {
		if (!$this->isEnabled()) {
			return $this->conf['prefix'] . sprintf('%03d', $id);
		}

		for ($attempt = 0; $attempt < 10; $attempt++) {
			$name = $this->randomName();
			if (empty(glob($this->conf['uploadDir'] . $name . '.*'))) {
				return $name;
			}
		}

		// every attempt collided somehow — the ID can't
		return $this->randomName() . $id;
	}

	private function randomName(): string {
		$length = min(32, max(4, (int) ($this->conf['temporaryFileNameLength'] ?? 8)));
		$lastIndex = strlen(self::NAME_ALPHABET) - 1;

		$name = '';
		for ($i = 0; $i < $length; $i++) {
			$name .= self::NAME_ALPHABET[random_int(0, $lastIndex)];
		}

		return $name;
	}

	/**
	 * Deletes every entry whose lifetime is up, along with its file and
	 * thumbnail. Runs at most once a minute unless forced, so it can sit on the
	 * request path without costing anything.
	 *
	 * Entries are swept on their own stored expiry, so turning temporary hosting
	 * off does not keep files that were already promised a deletion time.
	 *
	 * @return int number of entries removed
	 */
	public function sweep(bool $force = false): int {
		if (!$force && !$this->claimSweep()) {
			return 0;
		}

		$now = time();
		$expired = $this->logFile->pruneEntries(fn(uploadEntry $entry) => $entry->isExpired($now));

		if (empty($expired)) {
			return 0;
		}

		$this->uploadedFileRepository->deleteFilesByData($expired);

		$this->actionLog?->recordSystemBatch(actionLogEntry::EXPIRE, array_map(
			fn(uploadEntry $entry) => [$entry->getFileName($this->conf), $entry->getOriginalFileName()],
			$expired
		));

		return count($expired);
	}

	/**
	 * Touches the throttle stamp, telling the caller whether it won the right to
	 * sweep this time round.
	 */
	private function claimSweep(): bool {
		$stampFile = \DATA_DIR . self::STAMP_FILE;
		$now = time();

		if (file_exists($stampFile) && ($now - (int) @filemtime($stampFile)) < self::SWEEP_INTERVAL) {
			return false;
		}

		return @file_put_contents($stampFile, (string) $now, LOCK_EX) !== false;
	}
}
