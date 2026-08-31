<?php
namespace TwintailUploader\Classes;

class uploadEntryRepository {
	public function __construct(
		private string $logFile,
		private string $counterFile
	) {}

	/* Data Getters */
	public function getLastID() {
		$openFile = @fopen($this->logFile, "r");
		if ($openFile === false) {
			return 1;
		}

		$firstLine = fgets($openFile);
		fclose($openFile);

		// An empty log (no first line) has no last ID
		if ($firstLine === false) {
			return 1;
		}

		$array = explode("<>", $firstLine);
		return $array[0] ?? 1;
	}

	public function getNextID(): int {
		if (!file_exists($this->counterFile)) {
			file_put_contents($this->counterFile, (string) $this->getHighestIDFromLog());
		}

		$fp = fopen($this->counterFile, 'r+');
		flock($fp, LOCK_EX);
		$count = (int) fgets($fp, 64);

		// Never hand out an ID the log already uses: a stale-but-positive counter
		// (restore, manual edit, corruption) would otherwise overwrite a file.
		$count = max($count, $this->getHighestIDFromLog());

		$count++;
		fseek($fp, 0);
		ftruncate($fp, 0);
		fwrite($fp, (string) $count);
		fclose($fp);

		return $count;
	}

	private function getHighestIDFromLog(): int {
		if (!file_exists($this->logFile) || filesize($this->logFile) === 0) {
			return 0;
		}

		$highest = 0;
		$fp = fopen($this->logFile, 'r');
		while (($line = fgets($fp)) !== false) {
			if (trim($line) === '') continue;
			$parts = explode('<>', $line);
			$id = (int) $parts[0];
			if ($id > $highest) {
				$highest = $id;
			}
		}
		fclose($fp);

		return $highest;
	}

	public function getDataByID($id): uploadEntry {
		// An unknown ID comes back as an empty entry rather than a fatal — the
		// constructor needs an array, never null.
		$data = [];

		$openFile = @fopen($this->logFile, "r");
		if ($openFile === false) {
			return new uploadEntry($data);
		}

		while (($line = fgets($openFile)) !== false) {
			$array = explode("<>", $line);
			if ($array[0] == $id) {
				$data = $array;
				break;
			}
		}
		fclose($openFile);

		return new uploadEntry($data);
	}

	public function deleteDataFromLogByID(int $id): bool {
		// Hold one exclusive lock across the whole read-filter-rewrite so a
		// concurrent upload can't be lost between the read and the truncate.
		$openLogFile = fopen($this->logFile, "c+");
		if ($openLogFile === false) {
			return false;
		}
		if (!flock($openLogFile, LOCK_EX)) {
			fclose($openLogFile);
			return false;
		}

		$dataIsFoundInFile = false;
		$newFileContent = [];

		foreach (explode("\n", stream_get_contents($openLogFile)) as $line) {
			if ($line === '') {
				continue;
			}
			$data = explode("<>", $line);
			if ($data[0] == $id) {
				$dataIsFoundInFile = true;
			} else {
				$newFileContent[] = $line;
			}
		}

		if ($dataIsFoundInFile === false) {
			flock($openLogFile, LOCK_UN);
			fclose($openLogFile);
			return false;
		}

		rewind($openLogFile);
		ftruncate($openLogFile, 0);
		if (!empty($newFileContent)) {
			fwrite($openLogFile, implode("\n", $newFileContent) . "\n");
		}

		flock($openLogFile, LOCK_UN);
		fclose($openLogFile);

		return true;
	}
}
