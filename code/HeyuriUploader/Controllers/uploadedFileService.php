<?php
namespace HeyuriUploader\Controllers;

use HeyuriUploader\Classes\uploadedFileRepository;
use HeyuriUploader\Classes\uploadEntryRepository;
use HeyuriUploader\Classes\logFile; // Assuming this is the correct class
use HeyuriUploader\Classes\uploadEntry;
use HeyuriUploader\Classes\uploaderHTML; // Assuming this class handles UI output
use HeyuriUploader\Classes\banChecker;
use function HeyuriUploader\Functions\logFileData;

class uploadedFileService {
	private $uploadedFileRepository;
	private $uploadEntryRepository;
	private $logFile;
	private $uploaderHTML;
	private $lang;
	private $allowedExtensions;
	private $extensionsToBeConvertedToText;
	private $prefix;
	private $maxAmountOfFiles;
	private $deleteOldestOnMaxFiles;
	private $banChecker;

	public function __construct(
		uploadedFileRepository $uploadedFileRepository,
		uploadEntryRepository $uploadEntryRepository,
		logFile $logFile,
		uploaderHTML $uploaderHTML,
		array $allowedExtensions,
		array $extensionsToBeConvertedToText,
		string $prefix,
		int $maxAmountOfFiles,
		bool $deleteOldestOnMaxFiles,
		banChecker $banChecker
	) {
		$this->uploadedFileRepository = $uploadedFileRepository;
		$this->uploadEntryRepository = $uploadEntryRepository;
		$this->logFile = $logFile;
		$this->uploaderHTML = $uploaderHTML;
		$this->lang = $this->uploaderHTML->getLang();
		$this->allowedExtensions = $allowedExtensions;
		$this->extensionsToBeConvertedToText = $extensionsToBeConvertedToText;
		$this->prefix = $prefix;
		$this->maxAmountOfFiles = $maxAmountOfFiles;
		$this->deleteOldestOnMaxFiles = $deleteOldestOnMaxFiles;
		$this->banChecker = $banChecker;
	}

	/**
	 * Handles the file upload process
	 */
	public function processFiles(): void {
		// Ensure a file is uploaded
		$file = $this->validateUpload();

		$fullFileName = $file["name"];
		$fileTmpName = $file["tmp_name"];

		// Validate file info
		[$fileName, $fileExtension] = $this->getFileInfo($fullFileName);

		// Check if the extension is allowed
		$this->ensureAllowedExtension($fileExtension);

		// Handle potential dangerous extensions
		[$originalExtension, $fileExtension] = $this->handlePotentialDangerousExtensions($fileExtension);

		// Determine MIME type
		$realMimeType = $this->determineMimeType($fileTmpName);

		// Check if the file is banned by hash
		if ($this->banChecker->isFileBanned($fileTmpName)) {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.uploadRejected'), $this->lang->get('errors.fileBanned'));
			return;
		}

		// Generate new ID and new file name
		[$newID, $newFileName] = $this->generateNewIDAndFileName($fileExtension);

		// Move the file to the upload directory
		$this->uploadedFileRepository->moveFile($fileTmpName, $newFileName);

		// Process comment safely
		$comment = $_POST['comment'] ?? '';

		// If the extension was converted, append a notice
		$comment = $this->appendConversionNoticeIfNeeded($comment, $originalExtension, $fileExtension);

		// Process password (optional)
		$password = $this->processPassword();

		// Log data
		$data = logFileData($newID, $fileExtension, $comment, $realMimeType, $password, $fileName);

		// Check file limit
		if (!$this->enforceFileLimit()) {
			return;
		}

		// Write data to logs
		$this->writeDataToLogs($data);

		// Generate thumbnail if applicable
		$this->createFileThumbnails($data);
	}

	private function validateUpload(): array {
		if (!isset($_FILES['upfile']) || $_FILES['upfile']['error'] !== UPLOAD_ERR_OK) {
			throw new \Exception("No file uploaded or an error occurred during upload.");
		}
		return $_FILES['upfile'];
	}

	private function getFileInfo(string $fullFileName): array {
		$fileInfo = pathinfo($fullFileName);
		if (!isset($fileInfo['extension'])) {
			throw new \Exception("Invalid file format.");
		}
		$fileName = $fileInfo['filename'];
		$fileExtension = strtolower($fileInfo['extension']);
		return [$fileName, $fileExtension];
	}

	private function ensureAllowedExtension(string $fileExtension): void {
		if (!in_array($fileExtension, $this->allowedExtensions)) {
			throw new \Exception("Invalid file extension: $fileExtension.");
		}
	}

	private function handlePotentialDangerousExtensions(string $fileExtension): array {
		$originalExtension = $fileExtension;
		if (in_array($fileExtension, $this->extensionsToBeConvertedToText)) {
			$fileExtension = "txt";
		}
		return [$originalExtension, $fileExtension];
	}

	private function determineMimeType(string $fileTmpName): string {
		return $this->uploadedFileRepository->getFileMimeType($fileTmpName);
	}

	private function generateNewIDAndFileName(string $fileExtension): array {
		$newID = sprintf("%03d", $this->uploadEntryRepository->getNextID());
		$newFileName = $this->prefix . $newID . "." . $fileExtension;
		return [$newID, $newFileName];
	}

	private function appendConversionNoticeIfNeeded(string $comment, string $originalExtension, string $fileExtension): string {
		if ($originalExtension !== $fileExtension) {
			$comment .= '[ext]' . $fileExtension . '←' . $originalExtension . '[/ext]';
		}
		return $comment;
	}

	private function processPassword(): string {
		$password = $_POST['password'];

		if(empty($password)) {
			return '';
		}

		return password_hash($password, PASSWORD_DEFAULT) ?? '';
	}

	private function enforceFileLimit(): bool {
		if ($this->logFile->getTotalLogLines() >= $this->maxAmountOfFiles) {
	        if ($this->deleteOldestOnMaxFiles) {
	            $this->removeOldestFile();
	        } else {
	            $this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.fileLimitReached'));
	            return false;
	        }
	    }
		return true;
	}

	private function writeDataToLogs(uploadEntry $data): void {
		$this->logFile->writeDataToLogs($data);
	}

	private function createFileThumbnails(uploadEntry $data): void {
		$this->uploadedFileRepository->createThumbnails($data);
	}

	/**
	 * Removes the oldest file when the limit is exceeded
	 */
	private function removeOldestFile(): void {
		$oldestFileData = $this->logFile->getOldestData();
		if ($oldestFileData) {
			$this->logFile->removeLastData();
			$this->uploadedFileRepository->deleteFileByData($oldestFileData);
		}
	}
}

?>
