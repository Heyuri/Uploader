<?php
namespace TwintailUploader\Classes;

class uploadEntryRepository {
	public function __construct(
		private string $logFile,
		private string $counterFile
	) {}

	/* Data Getters */
	public function getLastID() {
		$openFile = fopen($this->logFile, "r");

		$firstLine = fgets($openFile);
		$array = explode("<>", $firstLine);
		fclose($openFile);

		return $array[0] ?? 1;
	}

	public function getNextID(): int {
		if (!file_exists($this->counterFile)) {
			file_put_contents($this->counterFile, (string) $this->getHighestIDFromLog());
		}

		$fp = fopen($this->counterFile, 'r+');
		flock($fp, LOCK_EX);
		$count = (int) fgets($fp, 64);

		// If counter was reset or is behind the log, recover from the log
		if ($count <= 0) {
			$count = $this->getHighestIDFromLog();
		}

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
		$openFile = fopen($this->logFile, "r");
		$data = null;

		while (!feof($openFile)) {
			$line = fgets($openFile);
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
		$openLogFile = fopen($this->logFile, "r");
		$dataIsFoundInFile = false;
		$newFileContent = [];
	
		// while not at the end of the file.
		while (!feof($openLogFile)) {
			$line = fgets($openLogFile);
			$data = explode("<>", $line);
	
			if ($data[0] == $id) {
				$dataIsFoundInFile = true;
			} else {
				$newFileContent[] = $line;
			}
		}
		fclose($openLogFile);
	
		// data was not found.
		if ($dataIsFoundInFile == false) {
			return false;
		}
	
	
		$openLogFile = fopen($this->logFile, "w");
		flock($openLogFile, LOCK_EX);
	
		foreach ($newFileContent as $line) {
			fwrite($openLogFile, $line);
		}
		fclose($openLogFile);
		
	
		return true;
	}
}
