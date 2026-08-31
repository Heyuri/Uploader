<?php
namespace TwintailUploader\Classes;

class uploadEntry {
	private $id, $fileExtension, $comment, $ip, $time, $size, $mimeType, $password, $originalFileName, $storedName, $expiresTime, $fileHash;

	public function __construct(array $postData) {
		$propertyMap = [
			'id',
			'fileExtension',
			'comment',
			'ip',
			'time',
			'size',
			'mimeType',
			'password',
			'originalFileName',
			'storedName',
			'expiresTime',
			'fileHash'
		];

		foreach ($propertyMap as $index => $property) {
			if (property_exists($this, $property)) {
				$this->$property = $postData[$index] ?? null;
			}
		}
	}

	// Getters
	public function getId(): int {
		return intval($this->id ?? 0);
	}

	public function getIdAsString(): string {
		return strval($this->id ?? 0);
	}

	public function getFileExtension(): string {
		return $this->fileExtension ?? '';
	}

	public function getComment(): string {
		return $this->comment ?? '';
	}

	public function getIp(): string {
		return $this->ip ?? '';
	}

	public function getTime(): int {
		return intval($this->time) ?? 0;
	}

	public function getSize(): int {
		return intval($this->size) ?? 0;
	}

	public function getMimeType(): string {
		return $this->mimeType ?? '';
	}

	public function getPassword(): string {
		return $this->password ?? '';
	}

	public function getOriginalFileName(): string {
		return $this->originalFileName ?? '';
	}

	/**
	 * Name the file is stored under, without extension. Empty on entries written
	 * before temporary hosting existed — those are always named after their ID.
	 */
	public function getStoredName(): string {
		return trim($this->storedName ?? '');
	}

	/**
	 * Unix time this entry is deleted at, 0 when it never expires.
	 */
	public function getExpiresTime(): int {
		return intval($this->expiresTime ?? 0);
	}

	/**
	 * SHA-256 of the stored file's contents, empty when it was never recorded.
	 * Only temporary uploads carry one — it is what lets a re-upload of the same
	 * file find the copy that is already here.
	 */
	public function getFileHash(): string {
		return trim($this->fileHash ?? '');
	}

	public function isExpired(int $now): bool {
		$expiresTime = $this->getExpiresTime();
		return $expiresTime > 0 && $expiresTime <= $now;
	}

	/**
	 * Stored name without extension, falling back to the ID-based name.
	 */
	private function getBaseName(array $conf): string {
		// The stored name becomes a filesystem path, so keep it to the safe
		// charset the app actually produces (random [a-z0-9] names). This makes
		// path traversal impossible even if a malformed log line ever carries a
		// "../" here.
		$storedName = preg_replace('/[^A-Za-z0-9_-]/', '', $this->getStoredName());
		return $storedName !== '' ? $storedName : $conf['prefix'] . sprintf("%03d", $this->id);
	}

	/**
	 * Constructs the file name with prefix and extension.
	 *
	 * @param array $conf Configuration array containing 'prefix'
	 * @return string The constructed file name (e.g., "up001.jpg")
	 */
	public function getFileName(array $conf): string {
		// Extensions are whitelisted on upload, but this reaches the filesystem,
		// so keep it to the safe charset here too — never a path separator.
		$extension = preg_replace('/[^A-Za-z0-9]/', '', $this->getFileExtension());
		return $this->getBaseName($conf) . '.' . $extension;
	}

	/**
	 * Constructs the thumbnail file name with prefix, ID, thumb suffix and extension.
	 *
	 * @param array $conf Configuration array containing 'prefix'
	 * @return string The constructed thumbnail name (e.g., "up001_thumb.jpg")
	 */
	public function getThumbName(array $conf): string {
		return $this->getBaseName($conf) . $conf['thumb_suffix'] . '.' . $conf['thumbnailExtension'];
	}

	/**
	 * Constructs the full file path for the uploaded file.
	 * 
	 * @param array $conf Configuration array containing 'uploadDir' and 'prefix'
	 * @return string The full file path (e.g., "src/up001.jpg")
	 */
	public function getFilePath(array $conf): string {
		return $conf['uploadDir'] . $this->getFileName($conf);
	}

	/**
	 * Constructs the full path for the thumbnail file.
	 * 
	 * @param array $conf Configuration array containing 'thumbDir' and 'prefix'
	 * @return string The full thumbnail path (e.g., "thmb/up001_thumb.jpg")
	 */
	public function getThumbPath(array $conf): string {
		return $conf['thumbDir'] . $this->getThumbName($conf);
	}

	/**
	 * Constructs the path for a video thumbnail with specific video extension.
	 * 
	 * @param array $conf Configuration array containing 'thumbDir', 'prefix', and 'thumbnailExtension'
	 * @return string The full video thumbnail path (e.g., "thmb/up001_thumb.jpg")
	 */
	public function getVideoThumbPath(array $conf): string {
		return $conf['thumbDir'] . $this->getBaseName($conf) . $conf['thumb_suffix'] . '.' . $conf['thumbnailExtension'];
	}
}