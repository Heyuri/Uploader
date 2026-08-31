<?php
namespace TwintailUploader\Classes;

/**
 * The action log of one scope — the main uploader, or a single user board.
 *
 * Another flat, "<>"-delimited file. Unlike souko.log this one is *appended*,
 * oldest first, and capped: once it holds more than $maxEntries lines the
 * oldest ones fall off the front.
 *
 * The path is passed in rather than read from DATA_DIR, so the instance-wide
 * mod page can read every board's log without being the board being served.
 */
class actionLogRepository {
	/** Field order in the log line — load-bearing, see actionLogEntry */
	public const FIELDS = ['time', 'scope', 'actor', 'ip', 'action', 'target', 'details'];

	private const DELIMITER = '<>';

	public function __construct(
		private string $logPath,
		private int $maxEntries = 2000
	) {}

	/**
	 * Appends an action and drops whatever no longer fits under the cap.
	 */
	public function add(actionLogEntry $entry): bool {
		return $this->addMany([$entry]);
	}

	/**
	 * Appends several actions in one go — an expiry sweep can take hundreds of
	 * files at once, and every write rewrites the log.
	 *
	 * Read-modify-write under one exclusive lock, like every other write in the
	 * app: a concurrent action must never be lost between the read and the
	 * truncate.
	 *
	 * @param actionLogEntry[] $entries
	 */
	public function addMany(array $entries): bool {
		if (empty($entries) || !is_dir(dirname($this->logPath))) {
			return false;
		}

		$fileHandle = fopen($this->logPath, 'c+');
		if ($fileHandle === false) {
			return false;
		}

		if (!flock($fileHandle, LOCK_EX)) {
			fclose($fileHandle);
			return false;
		}

		$lines = [];
		foreach (explode("\n", stream_get_contents($fileHandle)) as $line) {
			if (trim($line) !== '') {
				$lines[] = rtrim($line, "\r\n");
			}
		}

		foreach ($entries as $entry) {
			$lines[] = $this->toLine($entry);
		}

		if ($this->maxEntries > 0 && count($lines) > $this->maxEntries) {
			$lines = array_slice($lines, -$this->maxEntries);
		}

		rewind($fileHandle);
		ftruncate($fileHandle, 0);
		$written = fwrite($fileHandle, implode("\n", $lines) . "\n");

		flock($fileHandle, LOCK_UN);
		fclose($fileHandle);

		return $written !== false;
	}

	/**
	 * Everything the log holds, newest first.
	 *
	 * @return actionLogEntry[]
	 */
	public function getAll(): array {
		if (!file_exists($this->logPath)) {
			return [];
		}

		$fileHandle = fopen($this->logPath, 'r');
		if ($fileHandle === false) {
			return [];
		}

		$entries = [];

		while (($line = fgets($fileHandle)) !== false) {
			if (trim($line) === '') {
				continue;
			}

			// short lines parse fine — every field past the end defaults
			$entries[] = new actionLogEntry(explode(self::DELIMITER, rtrim($line, "\r\n")));
		}

		fclose($fileHandle);

		// the file is oldest first, the pages that read it want the opposite
		return array_reverse($entries);
	}

	/**
	 * Builds the log line. Free text is stripped of control characters first and
	 * only then of the delimiter: the other way round a "<\r>" would re-form a
	 * literal "<>" once the \r went, smuggling in an extra field or line.
	 */
	private function toLine(actionLogEntry $entry): string {
		$fields = [];
		foreach ($entry->toArray() as $value) {
			$fields[] = str_replace(self::DELIMITER, '‹›', str_replace(["\r", "\n", "\t", "\0"], '', (string) $value));
		}

		return implode(self::DELIMITER, $fields);
	}
}
