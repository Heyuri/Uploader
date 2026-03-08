<?php
namespace HeyuriUploader\Classes;

use RuntimeException;

use function HeyuriUploader\Functions\thumbnailVideo;
use function HeyuriUploader\Functions\thumbnailImage;


class uploadedFileRepository {
	private $conf;
	
	public function __construct($config) {
		$this->conf = $config;
	}

	public function deleteFileByData(uploadEntry $data) {
		$path = $data->getFilePath($this->conf);
		if (file_exists($path)) {
			unlink($path);
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
