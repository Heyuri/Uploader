<?php
namespace TwintailUploader\Classes;

use RuntimeException;

use function TwintailUploader\Functions\thumbnailVideo;
use function TwintailUploader\Functions\thumbnailImage;


class uploadedFileRepository {
	private $conf;
	private ?cloudflareAPI $cloudflareAPI;

	public function __construct($config, ?cloudflareAPI $cloudflareAPI = null) {
		$this->conf = $config;
		$this->cloudflareAPI = $cloudflareAPI;
	}

	public function deleteFileByData(uploadEntry $data) {
		$this->deleteFilesByData([$data]);
	}

	/**
	 * Deletes several entries at once so an expiry sweep purges the CDN in one
	 * call instead of one per file.
	 *
	 * @param uploadEntry[] $entries
	 */
	public function deleteFilesByData(array $entries): void {
		$paths = [];

		foreach ($entries as $data) {
			$path = $data->getFilePath($this->conf);
			if (file_exists($path)) {
				unlink($path);
			}

			$this->deleteThumbnail($data);

			$paths[] = $path;
			$paths[] = $data->getThumbPath($this->conf);
		}

		// Drop the CDN cache so the deleted files stop being served
		if (!empty($paths) && $this->cloudflareAPI !== null && $this->cloudflareAPI->isEnabled()) {
			$this->cloudflareAPI->purgeFiles($paths);
		}
	}

	/**
	 * Removes an entry's thumbnail, never unlinking anything outside the
	 * thumbnail directory.
	 */
	public function deleteThumbnail(uploadEntry $data): void {
		// videos are thumbnailed to a still, everything else keeps the shared naming
		$thumbPath = preg_match('/video/i', $data->getMimeType())
			? $data->getVideoThumbPath($this->conf)
			: $data->getThumbPath($this->conf);

		if (!file_exists($thumbPath)) {
			return;
		}

		$realThumbPath = realpath($thumbPath);
		$realThumbDir = realpath($this->conf['thumbDir']);

		if ($realThumbPath === false || $realThumbDir === false) {
			return;
		}

		if (str_starts_with($realThumbPath, rtrim($realThumbDir, '/') . '/')) {
			unlink($realThumbPath);
		}
	}

	public function getFileMimeType($filePath): string {
		$finfo = finfo_open(FILEINFO_MIME_TYPE); 
		$mimeType = finfo_file($finfo, $filePath);
		return $mimeType;
	}

	public function moveFile($tmpName, $newName): void {
		$destPath = $this->conf['uploadDir'] . $newName;
		if (is_uploaded_file($tmpName)) {
			if (move_uploaded_file($tmpName, $destPath)) {
				chmod($destPath, 0644);
			} else {
				throw new RuntimeException("Failed to move uploaded file.");
			}
		} else {
			throw new RuntimeException("Invalid uploaded file.");
		}
	}

	public function createThumbnails(uploadEntry $data): void {
		if (preg_match('/image/i', $data->getMimeType())) {
			$imagePath = $data->getFilePath($this->conf);
			
			thumbnailImage($imagePath, $data->getThumbPath($this->conf), 200, 95);
		}

		if (preg_match('/video/i', $data->getMimeType())) {
			$videoPath = $data->getFilePath($this->conf);
			
			thumbnailVideo($videoPath, $data->getVideoThumbPath($this->conf), 200, 95);
		}
	}
}
