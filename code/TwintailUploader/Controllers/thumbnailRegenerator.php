<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\fileSource;
use TwintailUploader\Classes\languageManager;
use TwintailUploader\Classes\uploadedFileRepository;
use TwintailUploader\Classes\uploadEntry;

/**
 * Rebuilds the thumbnails of one source, a batch at a time.
 *
 * A whole board can hold thousands of images and every video means an ffmpeg
 * run, so one request can never do it all inside max_execution_time. Each
 * call walks the log from an offset and stops after a time budget or an entry
 * cap, whichever comes first; the page keeps calling until "done" comes back.
 * The offset is a position in the log, so an upload or deletion between two
 * batches shifts the walk by one entry at most — harmless for this job.
 */
class thumbnailRegenerator {
	public const MODE_MISSING = 'missing';
	public const MODE_ALL = 'all';

	/** seconds of work per batch, well under a default max_execution_time of 30 */
	private const TIME_BUDGET = 10;
	/** entries walked per batch at most, so one response stays small */
	private const MAX_ENTRIES = 100;

	public function __construct(
		private fileSource $source,
		private languageManager $lang
	) {}

	/**
	 * @return array{total:int, processed:int, created:int, failed:int, failures:string[], nextOffset:int, done:bool}
	 */
	public function runBatch(string $mode, int $offset): array {
		$conf = $this->source->getConf();
		$entries = $this->readEntries();
		$offset = max(0, min($offset, count($entries)));

		$result = [
			'total' => count($entries),
			'processed' => 0,
			'created' => 0,
			'failed' => 0,
			'failures' => [],
			'nextOffset' => $offset,
			'done' => false,
		];

		$thumbDir = rtrim($conf['thumbDir'], '/');
		if (!is_dir($thumbDir)) {
			mkdir($thumbDir, 0755, true);
		}

		$repository = new uploadedFileRepository($conf);
		$started = microtime(true);
		$batchTime = time();

		foreach (array_slice($entries, $offset) as $entry) {
			if ($result['processed'] >= self::MAX_ENTRIES || microtime(true) - $started > self::TIME_BUDGET) {
				return $result;
			}

			$result['processed']++;
			$result['nextOffset']++;

			if (!preg_match('/image|video/i', $entry->getMimeType())) {
				continue;
			}

			$fileName = $entry->getFileName($conf);
			$thumbPath = $entry->getThumbPath($conf);

			if (!file_exists($entry->getFilePath($conf))) {
				$result['failed']++;
				$result['failures'][] = $this->lang->get('admin.thumbnailSourceMissing', $fileName);
				continue;
			}

			if (file_exists($thumbPath)) {
				if ($mode !== self::MODE_ALL) {
					continue;
				}
				// ffmpeg won't overwrite, and a stale thumbnail must not pass as a new one
				$repository->deleteThumbnail($entry);
			}

			$repository->createThumbnails($entry);
			clearstatcache(true, $thumbPath);

			if (file_exists($thumbPath) && filemtime($thumbPath) >= $batchTime) {
				$result['created']++;
			} else {
				$result['failed']++;
				$result['failures'][] = $this->lang->get('admin.thumbnailFailed', $fileName);
			}
		}

		$result['done'] = true;
		return $result;
	}

	/**
	 * @return uploadEntry[] in log order
	 */
	private function readEntries(): array {
		$logPath = $this->source->getLogPath();
		if (!file_exists($logPath)) {
			return [];
		}

		$entries = [];
		foreach (file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
			if (trim($line) !== '') {
				$entries[] = new uploadEntry(explode('<>', $line));
			}
		}

		return $entries;
	}
}
