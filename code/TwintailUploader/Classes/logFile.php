<?php
namespace TwintailUploader\Classes;

class logFile {
	
	// Private member variable for configuration
	private $conf;

	public function __construct($config) {
		$this->conf = $config;
	}

	// Method to write data to logs
	public function writeDataToLogs(uploadEntry $data): bool {
		$id = $data->getIdAsString();
		$fileExtention = $data->getFileExtension();
		$comment = $data->getComment();
		$host = $data->getIp();
		$dateUploaded = $data->getTime();
		$sizeInBytes = $data->getSize();
		$mimeType = $data->getMimeType();
		$password = $data->getPassword();
		$originalFileName = $data->getOriginalFileName();

		// replace dellimeter with an equivelant
		$comment = str_replace('<>', '‹›', $comment);

		$stringData = "$id<>$fileExtention<>$comment<>$host<>$dateUploaded<>$sizeInBytes<>$mimeType<>$password<>$originalFileName" . "\n";

		$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], "c+");

		if ($fileHandle === false) {
			// Handle error when file cannot be opened
			echo "Failed to open log file.";
			return false;
		}

		// Acquire an exclusive lock
		if (!flock($fileHandle, LOCK_EX)) {
			echo "Could not lock log file.";
			fclose($fileHandle);
			return false;
		}

		// Read the existing contents to prepend new data
		$existingData = stream_get_contents($fileHandle);

		// Rewind the file pointer to the beginning of the file
		rewind($fileHandle);

		// Prepend new data and write the existing data back
		if (fwrite($fileHandle, $stringData . $existingData) === false) {
			echo "Failed to write to log file.";
			flock($fileHandle, LOCK_UN);
			fclose($fileHandle);
			return false;
		}

		// Unlock the file and close
		flock($fileHandle, LOCK_UN);
		fclose($fileHandle);

		return true;
	}

	// Method to get total usage in bytes
	public function getTotalUsageInBytes() {
		// Total file size calculation
		$logFile = \DATA_DIR . $this->conf['logFile'];
		$totalSize = 0;
		$openFile = fopen($logFile, "r");

		// id<>fileExtension<>comment<>host<>dateUploaded<>sizeInBytes<>mimeType<>Password<>originalFileName
		while (!feof($openFile)) {
			$line = fgets($openFile);
			if ($line == false && trim($line) == '') {
				continue;
			}
			$array = explode("<>", $line);
			$dataEntry = new uploadEntry($array);
			$size = $dataEntry->getSize();
			$totalSize = $totalSize + $size;
		}

		fclose($openFile);
		return $totalSize;
	}

	// Method to get the total number of log lines
	public function getTotalLogLines() {
		$lineCount = 0;
		$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], 'r');

		while (!feof($fileHandle)) {
			$line = fgets($fileHandle);
			if ($line !== false && trim($line) !== '') {
				$lineCount++;
			}
		}

		fclose($fileHandle);

		return $lineCount;
	}

	public function removeLastData(): array {
		$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], 'r+'); 
		flock($fileHandle, LOCK_EX);
	
		if (!$fileHandle) {
			return [false, ""]; // Return false and an empty string if the file cannot be opened
		}
	
		$lastLine = '';
		$len = 0; // To track the length of the last line
	
		// Move to the end of the file
		fseek($fileHandle, 0, SEEK_END);
		$fileSize = ftell($fileHandle); // Get the size of the file
	
		// Read backwards to find the beginning of the last line
		while ($fileSize > 0) {
			fseek($fileHandle, --$fileSize, SEEK_SET);
			$char = fgetc($fileHandle);
			if ($char == "\n" && $len > 0) {
				break;
			}
			if ($char != "\r") {
				$lastLine = $char . $lastLine;
				$len++;
			}
		}
	
		// Truncate the file to remove the last line
		if ($fileSize == 0) { // If it's the first and only line in the file
			ftruncate($fileHandle, 0);
		} else {
			ftruncate($fileHandle, $fileSize);
		}
	
		// Close the file handle
		fclose($fileHandle);
	
		$data = explode("<>", $lastLine);
	
		return [true, $lastLine]; // Return true and the last line
	}

	public function getOldestData(): ?uploadEntry {
		$logFile = \DATA_DIR . $this->conf['logFile'];

		// Open the file for reading
		$fileHandle = fopen($logFile, 'r');
		if (!$fileHandle) {
			return null; // Return null if the file cannot be opened
		}

		$oldestLine = null;

		// Read the last line (oldest entry)
		while (($line = fgets($fileHandle)) !== false) {
			$oldestLine = $line; // Overwrite to get the last line
		}

		fclose($fileHandle);

		return $oldestLine ? new uploadEntry(explode('<>', $oldestLine)) : null;
	}
	
}