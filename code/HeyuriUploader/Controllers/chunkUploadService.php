<?php
namespace HeyuriUploader\Controllers;

use HeyuriUploader\Classes\uploadedFileRepository;
use HeyuriUploader\Classes\uploadEntryRepository;
use HeyuriUploader\Classes\logFile;
use HeyuriUploader\Classes\uploadEntry;
use HeyuriUploader\Classes\uploaderHTML;
use HeyuriUploader\Classes\banChecker;

use function HeyuriUploader\Functions\getUserIP;

class chunkUploadService {
	private $conf;
	private $uploadedFileRepository;
	private $uploadEntryRepository;
	private $logFile;
	private $banChecker;
	private string $chunkDir;

	public function __construct(
		array $conf,
		uploadedFileRepository $uploadedFileRepository,
		uploadEntryRepository $uploadEntryRepository,
		logFile $logFile,
		banChecker $banChecker
	) {
		$this->conf = $conf;
		$this->uploadedFileRepository = $uploadedFileRepository;
		$this->uploadEntryRepository = $uploadEntryRepository;
		$this->logFile = $logFile;
		$this->banChecker = $banChecker;

		// Ensure chunk directory exists, default to data/chunks/ if not configured
		$this->chunkDir = !empty($this->conf['chunkDir']) ? DATA_DIR . $this->conf['chunkDir'] : DATA_DIR . 'chunks/';
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
			echo json_encode(['error' => 'No chunk data received.']);
			return;
		}

		$chunkIndex = filter_var($_POST['chunkIndex'] ?? -1, FILTER_VALIDATE_INT);
		$totalChunks = filter_var($_POST['totalChunks'] ?? 0, FILTER_VALIDATE_INT);
		$fileName = $_POST['fileName'] ?? '';
		$fileSize = filter_var($_POST['fileSize'] ?? 0, FILTER_VALIDATE_INT);

		if ($chunkIndex === false || $totalChunks === false || $fileSize === false
			|| $chunkIndex < 0 || $totalChunks <= 0 || empty($fileName) || $fileSize <= 0) {
			http_response_code(400);
			echo json_encode(['error' => 'Invalid chunk parameters.']);
			return;
		}

		if ($chunkIndex >= $totalChunks) {
			http_response_code(400);
			echo json_encode(['error' => 'Chunk index out of range.']);
			return;
		}

		// Enforce max file size
		if ($fileSize > $this->conf['maxUploadSize'] * 1024 * 1024) {
			http_response_code(413);
			echo json_encode(['error' => 'File exceeds maximum upload size.']);
			return;
		}

		// First chunk: generate upload ID and create session directory
		if ($chunkIndex === 0) {
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
				echo json_encode(['error' => 'Invalid upload ID.']);
				return;
			}

			$uploadDir = $this->chunkDir . $uploadId . '/';
			if (!is_dir($uploadDir) || !file_exists($uploadDir . 'meta.json')) {
				http_response_code(404);
				echo json_encode(['error' => 'Upload session not found.']);
				return;
			}

			// Verify IP matches the one that started the upload
			$meta = json_decode(file_get_contents($uploadDir . 'meta.json'), true);
			if ($meta['ip'] !== getUserIP()) {
				http_response_code(403);
				echo json_encode(['error' => 'IP mismatch.']);
				return;
			}
		}

		// Store the chunk
		$chunkPath = $uploadDir . $chunkIndex;
		move_uploaded_file($_FILES['chunkData']['tmp_name'], $chunkPath);

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
			echo json_encode(['error' => 'Invalid upload ID.']);
			return;
		}

		$uploadDir = $this->chunkDir . $uploadId . '/';
		if (!is_dir($uploadDir) || !file_exists($uploadDir . 'meta.json')) {
			http_response_code(404);
			echo json_encode(['error' => 'Upload session not found.']);
			return;
		}

		$meta = json_decode(file_get_contents($uploadDir . 'meta.json'), true);

		// Verify IP
		if ($meta['ip'] !== getUserIP()) {
			http_response_code(403);
			echo json_encode(['error' => 'IP mismatch.']);
			return;
		}

		// Verify all chunks exist
		for ($i = 0; $i < $meta['totalChunks']; $i++) {
			if (!file_exists($uploadDir . $i)) {
				http_response_code(400);
				echo json_encode(['error' => 'Missing chunk ' . $i . '.']);
				return;
			}
		}

		// Assemble chunks into a single temp file
		$assembledPath = $uploadDir . 'assembled';
		$assembledHandle = fopen($assembledPath, 'wb');
		if (!$assembledHandle) {
			http_response_code(500);
			echo json_encode(['error' => 'Failed to assemble file.']);
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
			echo json_encode(['error' => 'Assembled file size mismatch.']);
			return;
		}

		// Now process the assembled file through the normal upload pipeline
		try {
			$this->processAssembledFile($assembledPath, $meta['fileName'], $meta['fileSize']);
		} catch (\Exception $e) {
			$this->cleanupChunks($uploadId);
			http_response_code(400);
			echo json_encode(['error' => $e->getMessage()]);
			return;
		}

		// Cleanup chunks
		$this->cleanupChunks($uploadId);

		// Determine redirect target
		$requestFrom = $_POST['requestFrom'] ?? 'index';
		$redirectUrl = $this->conf['mainScript'] . '?request=' . ($requestFrom === 'catalog' ? 'catalog' : 'index');

		echo json_encode([
			'success' => true,
			'redirect' => $redirectUrl,
		]);
	}

	/**
	 * Processes an assembled file through the same pipeline as a normal upload.
	 */
	private function processAssembledFile(string $filePath, string $originalFileName, int $fileSize): void {
		// Parse file info
		$fileInfo = pathinfo($originalFileName);
		if (!isset($fileInfo['extension'])) {
			throw new \Exception("Invalid file format.");
		}

		$fileName = $fileInfo['filename'];
		$fileExtension = strtolower($fileInfo['extension']);

		// Check extension whitelist
		if (!in_array($fileExtension, $this->conf['allowedExtensions'])) {
			throw new \Exception("Invalid file extension: $fileExtension.");
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
			throw new \Exception("This file has been banned.");
		}

		// Generate new ID and file name
		$newID = sprintf("%03d", $this->uploadEntryRepository->getNextID());
		$newFileName = $this->conf['prefix'] . $newID . '.' . $fileExtension;

		// Move assembled file to upload directory
		$destPath = $this->conf['uploadDir'] . $newFileName;
		if (!rename($filePath, $destPath)) {
			throw new \Exception("Failed to move assembled file.");
		}
		chmod($destPath, 0644);

		// Process comment
		$comment = $_POST['comment'] ?? '';
		if ($originalExtension !== $fileExtension) {
			$comment .= '[ext]' . $fileExtension . '←' . $originalExtension . '[/ext]';
		}

		// Process password
		$password = $_POST['password'] ?? '';
		$hashedPassword = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : '';

		// Build the log entry
		$data = new uploadEntry([
			$newID,
			$fileExtension,
			$comment,
			getUserIP(),
			time(),
			$fileSize,
			$realMimeType,
			$hashedPassword,
			$fileName,
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
				throw new \Exception("File limit reached, contact administrator.");
			}
		}

		// Write to log
		$this->logFile->writeDataToLogs($data);

		// Generate thumbnails
		$this->uploadedFileRepository->createThumbnails($data);
	}

	/**
	 * Validates an upload ID is a 32-character hex string.
	 */
	private function isValidUploadId(string $uploadId): bool {
		return (bool) preg_match('/^[0-9a-f]{32}$/', $uploadId);
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
		if ($realUploadDir === false || strpos($realUploadDir, $realChunkDir) !== 0) {
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
