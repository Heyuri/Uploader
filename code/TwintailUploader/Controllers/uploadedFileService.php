<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\uploadedFileRepository;
use TwintailUploader\Classes\uploadEntryRepository;
use TwintailUploader\Classes\logFile; // Assuming this is the correct class
use TwintailUploader\Classes\uploadEntry;
use TwintailUploader\Classes\uploaderHTML; // Assuming this class handles UI output
use TwintailUploader\Classes\banChecker;
use TwintailUploader\Classes\temporaryHosting;
use TwintailUploader\Classes\actionLogEntry;
use TwintailUploader\Classes\uploadPasswordCookie;

use function TwintailUploader\Functions\generatePasswordHash;
use function TwintailUploader\Functions\logFileData;

class uploadedFileService {
	private $uploadedFileRepository;
	private $uploadEntryRepository;
	private $logFile;
	private $uploaderHTML;
	private $lang;
	private $allowedExtensions;
	private $extensionsToBeConvertedToText;
	private $maxAmountOfFiles;
	private $deleteOldestOnMaxFiles;
	private $banChecker;
	private $temporaryHosting;
	private $maxUploadSize;
	private $actionLog;
	private $conf;

	public function __construct(
		uploadedFileRepository $uploadedFileRepository,
		uploadEntryRepository $uploadEntryRepository,
		logFile $logFile,
		uploaderHTML $uploaderHTML,
		array $allowedExtensions,
		array $extensionsToBeConvertedToText,
		int $maxAmountOfFiles,
		bool $deleteOldestOnMaxFiles,
		banChecker $banChecker,
		temporaryHosting $temporaryHosting,
		int $maxUploadSize,
		actionLogController $actionLog,
		array $conf
	) {
		$this->uploadedFileRepository = $uploadedFileRepository;
		$this->uploadEntryRepository = $uploadEntryRepository;
		$this->logFile = $logFile;
		$this->uploaderHTML = $uploaderHTML;
		$this->lang = $this->uploaderHTML->getLang();
		$this->allowedExtensions = $allowedExtensions;
		$this->extensionsToBeConvertedToText = $extensionsToBeConvertedToText;
		$this->maxAmountOfFiles = $maxAmountOfFiles;
		$this->deleteOldestOnMaxFiles = $deleteOldestOnMaxFiles;
		$this->banChecker = $banChecker;
		$this->temporaryHosting = $temporaryHosting;
		$this->maxUploadSize = $maxUploadSize;
		$this->actionLog = $actionLog;
		$this->conf = $conf;
	}

	/**
	 * Handles the file upload process
	 *
	 * @return uploadEntry|null the stored entry, or null when the upload was rejected
	 */
	public function processFiles(): ?uploadEntry {
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
			return null;
		}

		// A temporary upload we are already hosting is handed back as-is: same
		// URL, same expiry, no second copy on disk.
		$fileHash = $this->temporaryHosting->hashFile($fileTmpName);
		$duplicate = $this->temporaryHosting->findDuplicate($fileHash, $fileExtension);
		if ($duplicate !== null) {
			return $duplicate;
		}

		// Generate new ID and new file name
		[$newID, $storedName, $newFileName] = $this->generateNewIDAndFileName($fileExtension);

		// Move the file to the upload directory
		$this->uploadedFileRepository->moveFile($fileTmpName, $newFileName);

		// Process comment safely
		$comment = $_POST['comment'] ?? '';

		// If the extension was converted, append a notice
		$comment = $this->appendConversionNoticeIfNeeded($comment, $originalExtension, $fileExtension);

		// Process password
		$password = $_POST['password'] ?? '';
		$passwordHash = generatePasswordHash($password);

		// keep it around so the next upload form can offer it back
		(new uploadPasswordCookie())->remember($password);

		// Log data
		$data = logFileData($newID, $fileExtension, $comment, $realMimeType, $passwordHash, $fileName, $storedName, $this->temporaryHosting->expiryTimeFor(time()), $fileHash);

		// Check file limit
		if (!$this->enforceFileLimit()) {
			return null;
		}

		// Write data to logs
		$this->writeDataToLogs($data);

		// Generate thumbnail if applicable
		$this->createFileThumbnails($data);

		$this->actionLog->recordFile(actionLogEntry::UPLOAD, $data, $this->conf);

		return $data;
	}

	private function validateUpload(): array {
		if (!isset($_FILES['upfile']) || $_FILES['upfile']['error'] !== UPLOAD_ERR_OK) {
			throw new \Exception($this->lang->get('errors.noFileUploaded'));
		}

		// Enforce the size cap at the app level too — php.ini alone isn't the
		// board's limit, and the chunked path already checks this.
		if ($this->maxUploadSize > 0 && $_FILES['upfile']['size'] > $this->maxUploadSize * 1024 * 1024) {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.uploadRejected'), $this->lang->get('upload.fileExceedsMaxSize'));
		}

		return $_FILES['upfile'];
	}

	private function getFileInfo(string $fullFileName): array {
		$fileInfo = pathinfo($fullFileName);
		if (!isset($fileInfo['extension'])) {
			throw new \Exception($this->lang->get('errors.invalidFileFormat'));
		}
		$fileName = $fileInfo['filename'];
		$fileExtension = strtolower($fileInfo['extension']);
		return [$fileName, $fileExtension];
	}

	private function ensureAllowedExtension(string $fileExtension): void {
		if (!in_array($fileExtension, $this->allowedExtensions)) {
			throw new \Exception($this->lang->get('errors.invalidExtension', htmlspecialchars($fileExtension)));
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

	/**
	 * @return array [padded ID for the log, stored name, file name on disk]
	 */
	private function generateNewIDAndFileName(string $fileExtension): array {
		$id = $this->uploadEntryRepository->getNextID();
		$storedName = $this->temporaryHosting->storedNameFor($id);

		return [sprintf("%03d", $id), $storedName, $storedName . "." . $fileExtension];
	}

	private function appendConversionNoticeIfNeeded(string $comment, string $originalExtension, string $fileExtension): string {
		if ($originalExtension !== $fileExtension) {
			$comment .= '[ext]' . $fileExtension . '←' . $originalExtension . '[/ext]';
		}
		return $comment;
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

			// the app rotating a file out, not the visitor who happened to trigger it
			$this->actionLog->recordSystem(
				actionLogEntry::DELETE_OLDEST,
				$oldestFileData->getFileName($this->conf),
				$oldestFileData->getOriginalFileName()
			);
		}
	}
}

?>
