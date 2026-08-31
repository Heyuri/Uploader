<?php
namespace TwintailUploader\Classes;

/**
 * Every upload on the instance, newest first, across the main uploader and all
 * user boards.
 *
 * Read-only, and it opens each source's log where it lies instead of going
 * through DATA_DIR — that constant is bound to the one board being served.
 */
class recentFilesRepository {
	/** @var fileSource[]|null */
	private ?array $sources = null;

	public function __construct(
		private array $conf,
		private boardRepository $boardRepository
	) {}

	/**
	 * @return fileSource[] the main uploader first, then every board
	 */
	public function getSources(): array {
		if ($this->sources === null) {
			$this->sources = [fileSource::main($this->conf)];

			foreach ($this->boardRepository->getAll() as $board) {
				$this->sources[] = fileSource::forBoard($this->conf, $board);
			}
		}

		return $this->sources;
	}

	public function getSource(string $key): ?fileSource {
		foreach ($this->getSources() as $source) {
			if ($source->getKey() === $key) {
				return $source;
			}
		}

		return null;
	}

	/**
	 * @return array<array{entry: uploadEntry, source: fileSource}> newest first
	 */
	public function getAllEntries(): array {
		$rows = [];

		foreach ($this->getSources() as $source) {
			$logPath = $source->getLogPath();
			if (!file_exists($logPath)) {
				continue;
			}

			$fileHandle = fopen($logPath, 'r');
			if ($fileHandle === false) {
				continue;
			}

			while (($line = fgets($fileHandle)) !== false) {
				if (trim($line) === '') {
					continue;
				}

				$rows[] = [
					'entry' => new uploadEntry(explode('<>', rtrim($line, "\r\n"))),
					'source' => $source,
				];
			}

			fclose($fileHandle);
		}

		// each log is already newest-first on its own, but the sources interleave
		usort($rows, fn(array $a, array $b) => $b['entry']->getTime() <=> $a['entry']->getTime());

		return $rows;
	}
}
