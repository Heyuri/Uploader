<?php
namespace TwintailUploader\Classes;

class uploadEntry {
	private $id, $fileExtension, $comment, $ip, $time, $size, $mimeType, $password, $originalFileName;

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
			'originalFileName'
		];

		foreach ($propertyMap as $index => $property) {
			if (property_exists($this, $property)) {
				$this->$property = $postData[$index] ?? null;
			}
		}
	}

	// Getters
	public function getId(): int {
		return $this->id ?? '';
	}

	public function getIdAsString(): string {
		return $this->id ?? '';
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

	public function getTime(): string {
		return $this->time ?? '';
	}

	public function getSize(): int {
		return $this->size ?? 0;
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
	 * Constructs the file name with prefix and extension.
	 * 
	 * @param array $conf Configuration array containing 'prefix'
	 * @return string The constructed file name (e.g., "up001.jpg")
	 */
	public function getFileName(array $conf): string {
		return $conf['prefix'] . sprintf("%03d", $this->id) . '.' . $this->fileExtension;
	}

	/**
	 * Constructs the thumbnail file name with prefix, ID, thumb suffix and extension.
	 * 
	 * @param array $conf Configuration array containing 'prefix'
	 * @return string The constructed thumbnail name (e.g., "up001_thumb.jpg")
	 */
	public function getThumbName(array $conf): string {
		return $conf['prefix'] . sprintf("%03d", $this->id) . $conf['thumb_suffix'] . '.' . $conf['thumbnailExtension'];
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
		return $conf['thumbDir'] . $conf['prefix'] . sprintf("%03d", $this->id) . $conf['thumb_suffix'] . '.' . $conf['thumbnailExtension'];
	}
}