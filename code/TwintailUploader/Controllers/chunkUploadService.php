<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\uploadedFileRepository;
use TwintailUploader\Classes\uploadEntryRepository;
use TwintailUploader\Classes\logFile;
use TwintailUploader\Classes\uploadEntry;
use TwintailUploader\Classes\banChecker;
use TwintailUploader\Classes\languageManager;
use TwintailUploader\Classes\uploaderHTML;
use TwintailUploader\Classes\temporaryHosting;
use TwintailUploader\Classes\actionLogEntry;
use TwintailUploader\Classes\uploadPasswordCookie;

use function TwintailUploader\Functions\generatePasswordHash;
use function TwintailUploader\Functions\getUserIP;

class chunkUploadService {
	private string $chunkDir;

	public function __construct(
		private array $conf,
		private uploadedFileRepository $uploadedFileRepository,
		private uploadEntryRepository $uploadEntryRepository,
		private logFile $logFile,
		private banChecker $banChecker,
		private languageManager $languageManager,
		private uploaderHTML $uploaderHTML,
		private temporaryHosting $temporaryHosting,
		private actionLogController $actionLog,
	) {
		// Ensure chunk directory exists, default to system temp directory if not configured
		$this->chunkDir = sys_get_temp_dir() . '/';
		
		// Normalize trailing slash
		if (substr($this->chunkDir, -1) !== '/') {
			$this->chunkDir .= '/';
		}

		if (!is_dir($this->chunkDir)) {
			mkdir($this->chunkDir, 0755, true);
		}
	}

	/**
	 * Handles receiving a single chunk from the client.
	 * Returns JSON response.
	 */
	public function handleChunk(): void {
		header('Content-Type: application/json');

		if (!isset($_FILES['chunkData']) || $_FILES['chunkData']['error'] !== UPLOAD_ERR_OK) {
			http_response_code(400);
			echo json_encode(['error' => $this->languageManager->get('upload.chunkUploadFailed')]);
			return;
		}

		$chunkIndex = filter_var($_POST['chunkIndex'] ?? -1, FILTER_VALIDATE_INT);
		$totalChunks = filter_var($_POST['totalChunks'] ?? 0, FILTER_VALIDATE_INT);
		$fileName = $_POST['fileName'] ?? '';
		$fileSize = filter_var($_POST['fileSize'] ?? 0, FILTER_VALIDATE_INT);

		if ($chunkIndex === false || $totalChunks === false || $fileSize === false
			|| $chunkIndex < 0 || $totalChunks <= 0 || empty($fileName) || $fileSize <= 0) {
			http_response_code(400);
			echo json_encode(['error' => $this->languageManager->get('upload.invalidChunkParameters')]);
			return;
		}

		if ($chunkIndex >= $totalChunks) {
			http_response_code(400);
			echo json_encode(['error' => $this->languageManager->get('upload.chunkIndexOutOfRange')]);
			return;
		}

		// Enforce max file size
		if ($fileSize > $this->conf['maxUploadSize'] * 1024 * 1024) {
			http_response_code(413);
			echo json_encode(['error' => $this->languageManager->get('upload.fileExceedsMaxSize')]);
			return;
		}

		// First chunk: generate upload ID and create session directory
		if ($chunkIndex === 0) {
			// Take out sessions a client started and never finalized, so
			// abandoned chunks can't pile up and exhaust the disk.
			$this->cleanupStaleSessions();

			$uploadId = bin2hex(random_bytes(16));
			$uploadDir = $this->chunkDir . $uploadId . '/';
			mkdir($uploadDir, 0755, true);

			// Store metadata
			$meta = [
				'totalChunks' => $totalChunks,
				'fileName' => basename($fileName),
				'fileSize' => $fileSize,
				'ip' => getUserIP(),
				'timestamp' => time(),
			];
			file_put_contents($uploadDir . 'meta.json', json_encode($meta));
		} else {
			// Subsequent chunks: validate upload ID
			$uploadId = $_POST['uploadId'] ?? '';
			if (!$this->isValidUploadId($uploadId)) {
				http_response_code(400);
				echo json_encode(['error' => $this->languageManager->get('upload.invalidUploadId')]);
				return;
			}

			$uploadDir = $this->chunkDir . $uploadId . '/';
			if (!is_dir($uploadDir) || !file_exists($uploadDir . 'meta.json')) {
				http_response_code(404);
				echo json_encode(['error' => $this->languageManager->get('upload.uploadSessionNotFound')]);
				return;
			}

			// Verify IP matches the one that started the upload
			$meta = json_decode(file_get_contents($uploadDir . 'meta.json'), true);
			if ($meta['ip'] !== getUserIP()) {
				http_response_code(403);
				echo json_encode(['error' => $this->languageManager->get('upload.ipMismatch')]);
				return;
			}
		}

		// Store the chunk
		$chunkPath = $uploadDir . $chunkIndex;
		if (!move_uploaded_file($_FILES['chunkData']['tmp_name'], $chunkPath)) {
			http_response_code(500);
			echo json_encode(['error' => $this->languageManager->get('upload.chunkUploadFailed')]);
			return;
		}

		echo json_encode([
			'success' => true,
			'uploadId' => $uploadId,
			'chunkIndex' => $chunkIndex,
		]);
	}

	/**
	 * Assembles all chunks and processes the file like a normal upload.
	 * Returns JSON response.
	 */
	public function finalizeUpload(): void {
		header('Content-Type: application/json');

		$uploadId = $_POST['uploadId'] ?? '';
		if (!$this->isValidUploadId($uploadId)) {
			http_response_code(400);
			echo json_encode(['error' => $this->languageManager->get('upload.invalidUploadId')]);
			return;
		}

		$uploadDir = $this->chunkDir . $uploadId . '/';
		if (!is_dir($uploadDir) || !file_exists($uploadDir . 'meta.json')) {
			http_response_code(404);
			echo json_encode(['error' => $this->languageManager->get('upload.uploadSessionNotFound')]);
			return;
		}

		$meta = json_decode(file_get_contents($uploadDir . 'meta.json'), true);

		// Verify IP
		if ($meta['ip'] !== getUserIP()) {
			http_response_code(403);
			echo json_encode(['error' => $this->languageManager->get('upload.ipMismatch')]);
			return;
		}

		// Verify all chunks exist
		for ($i = 0; $i < $meta['totalChunks']; $i++) {
			if (!file_exists($uploadDir . $i)) {
				http_response_code(400);
				echo json_encode(['error' => $this->languageManager->get('upload.missingChunk', ['chunk' => $i])]);
				return;
			}
		}

		// Assemble chunks into a single temp file
		$assembledPath = $uploadDir . 'assembled';
		$assembledHandle = fopen($assembledPath, 'wb');
		if (!$assembledHandle) {
			http_response_code(500);
			echo json_encode(['error' => $this->languageManager->get('upload.failedToAssembleFile')]);
			return;
		}

		for ($i = 0; $i < $meta['totalChunks']; $i++) {
			$chunkPath = $uploadDir . $i;
			$chunkHandle = fopen($chunkPath, 'rb');
			while (!feof($chunkHandle)) {
				fwrite($assembledHandle, fread($chunkHandle, 8192));
			}
			fclose($chunkHandle);
		}
		fclose($assembledHandle);

		// Verify assembled file size
		$actualSize = filesize($assembledPath);
		if ($actualSize !== $meta['fileSize']) {
			$this->cleanupChunks($uploadId);
			http_response_code(400);
			echo json_encode(['error' => $this->languageManager->get('upload.fileSizeMismatch')]);
			return;
		}

		// Now process the assembled file through the normal upload pipeline
		try {
			$entry = $this->processAssembledFile($assembledPath, $meta['fileName'], $meta['fileSize']);
		} catch (\Exception $e) {
			$this->cleanupChunks($uploadId);
			http_response_code(400);
			echo json_encode(['error' => $e->getMessage()]);
			return;
		}

		// Cleanup chunks
		$this->cleanupChunks($uploadId);

		// Re-render the listing for whichever view/page the uploader is on so the
		// client can update it in place instead of reloading the page.
		$requestFrom = $_POST['requestFrom'] ?? 'index';
		$pageNumber = filter_var($_POST['pageNumber'] ?? 1, FILTER_VALIDATE_INT) ?: 1;

		$response = [
			'success' => true,
			'file' => [
				'name' => $entry->getFileName($this->conf),
				'path' => $entry->getFilePath($this->conf),
			],
		];

		// an unlisted uploader has no listing to swap in — the uploader only
		// ever gets a link to their own file
		if (empty($this->conf['unlisted'])) {
			$response['listingHtml'] = $requestFrom === 'catalog'
				? $this->uploaderHTML->renderCatalog($pageNumber)
				: $this->uploaderHTML->renderFileListing($pageNumber);
		}

		echo json_encode($response);
	}

	/**
	 * Processes an assembled file through the same pipeline as a normal upload.
	 */
	private function processAssembledFile(string $filePath, string $originalFileName, int $fileSize): uploadEntry {
		// Parse file info
		$fileInfo = pathinfo($originalFileName);
		if (!isset($fileInfo['extension'])) {
			throw new \Exception($this->languageManager->get('upload.invalidFileFormat'));
		}

		$fileName = $fileInfo['filename'];
		$fileExtension = strtolower($fileInfo['extension']);

		// Check extension whitelist
		if (!in_array($fileExtension, $this->conf['allowedExtensions'])) {
			throw new \Exception($this->languageManager->get('errors.invalidExtension', htmlspecialchars($fileExtension)));
		}

		// Handle dangerous extensions
		$originalExtension = $fileExtension;
		if (in_array($fileExtension, $this->conf['extensionsToBeConvertedToText'])) {
			$fileExtension = 'txt';
		}

		// Determine MIME type from assembled file
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$realMimeType = finfo_file($finfo, $filePath);

		// Check if the file is banned by hash
		if ($this->banChecker->isFileBanned($filePath)) {
			throw new \Exception($this->languageManager->get('errors.fileBanned'));
		}

		// A temporary upload we are already hosting is handed back as-is: same
		// URL, same expiry, no second copy on disk. The assembled file goes with
		// the rest of the chunk session.
		$fileHash = $this->temporaryHosting->hashFile($filePath);
		$duplicate = $this->temporaryHosting->findDuplicate($fileHash, $fileExtension);
		if ($duplicate !== null) {
			return $duplicate;
		}

		// Generate new ID and file name
		$id = $this->uploadEntryRepository->getNextID();
		$storedName = $this->temporaryHosting->storedNameFor($id);
		$newID = sprintf("%03d", $id);
		$newFileName = $storedName . '.' . $fileExtension;

		// Move assembled file to upload directory
		$destPath = $this->conf['uploadDir'] . $newFileName;
		if (!rename($filePath, $destPath)) {
			throw new \Exception($this->languageManager->get('errors.failedToSaveFile'));
		}
		chmod($destPath, 0644);

		// Process comment
		$comment = $_POST['comment'] ?? '';
		if ($originalExtension !== $fileExtension) {
			$comment .= '[ext]' . $fileExtension . '←' . $originalExtension . '[/ext]';
		}

		// Process password
		$password = $_POST['password'] ?? '';
		$hashedPassword = !empty($password) ? generatePasswordHash($password) : '';

		// keep it around so the next upload form can offer it back
		(new uploadPasswordCookie())->remember($password);

		// Build the log entry
		$uploadedAt = time();
		$data = new uploadEntry([
			$newID,
			$fileExtension,
			$comment,
			getUserIP(),
			$uploadedAt,
			$fileSize,
			$realMimeType,
			$hashedPassword,
			$fileName,
			$storedName,
			$this->temporaryHosting->expiryTimeFor($uploadedAt),
			$fileHash,
		]);

		// Check file limit
		if ($this->logFile->getTotalLogLines() >= $this->conf['maxAmountOfFiles']) {
			if ($this->conf['deleteOldestOnMaxFiles']) {
				$oldestFileData = $this->logFile->getOldestData();
				if ($oldestFileData) {
					$this->logFile->removeLastData();
					$this->uploadedFileRepository->deleteFileByData($oldestFileData);
				}
			} else {
				// Remove the file we just moved since we can't log it
				if (file_exists($destPath)) {
					unlink($destPath);
				}
				throw new \Exception($this->languageManager->get('errors.fileLimitReached'));
			}
		}

		// Write to log
		$this->logFile->writeDataToLogs($data);

		// Generate thumbnails
		$this->uploadedFileRepository->createThumbnails($data);

		$this->actionLog->recordFile(actionLogEntry::UPLOAD, $data, $this->conf);

		return $data;
	}

	/**
	 * Validates an upload ID is a 32-character hex string.
	 */
	private function isValidUploadId(string $uploadId): bool {
		return (bool) preg_match('/^[0-9a-f]{32}$/', $uploadId);
	}

	/**
	 * Drops upload session directories whose metadata is older than an hour —
	 * clients that started an upload and never finalized it.
	 */
	private function cleanupStaleSessions(): void {
		$cutoff = time() - 3600;

		foreach (glob($this->chunkDir . '*', GLOB_ONLYDIR) as $dir) {
			$uploadId = basename($dir);
			if (!$this->isValidUploadId($uploadId)) {
				continue;
			}

			$meta = $dir . '/meta.json';
			// no metadata, or metadata older than the cutoff → abandoned
			if (!file_exists($meta) || filemtime($meta) < $cutoff) {
				$this->cleanupChunks($uploadId);
			}
		}
	}

	/**
	 * Removes all chunk files and the upload session directory.
	 */
	private function cleanupChunks(string $uploadId): void {
		if (!$this->isValidUploadId($uploadId)) {
			return;
		}

		$uploadDir = $this->chunkDir . $uploadId . '/';
		if (!is_dir($uploadDir)) {
			return;
		}

		// Resolve real path and verify it's within the chunk directory
		$realChunkDir = realpath($this->chunkDir);
		$realUploadDir = realpath($uploadDir);
		if ($realUploadDir === false || str_contains($realUploadDir, $realChunkDir) === false) {
			return;
		}

		$files = glob($uploadDir . '*');
		foreach ($files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
		rmdir($uploadDir);
	}
}

?>
