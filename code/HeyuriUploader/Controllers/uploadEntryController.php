<?php
namespace HeyuriUploader\Controllers;

use HeyuriUploader\Classes\uploadEntry;
use HeyuriUploader\Classes\uploadEntryRepository;
use HeyuriUploader\Classes\uploadedFileRepository;
use HeyuriUploader\Classes\uploaderHTML;

class uploadEntryController {
	private $lang;

	public function __construct(
		private uploadEntry $uploadEntry,
		private uploadEntryRepository $uploadEntryRepository, 
		private uploadedFileRepository $uploadedFileRepository, 
		private uploaderHTML $uploaderHTML,
		private string $thumbDir, 
		private string $prefix, 
		private string $adminPassword, 
		private string $videoThumbnailExtension
	) {
		$this->lang = $this->uploaderHTML->getLang();
	}

	public function adminDeletePost(bool $showMessage = true): void {
		if ($this->uploadEntry === null) {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.deletionError'), $this->lang->get('errors.fileNotFound'));
		}

		// Delete entry from log
		$this->uploadEntryRepository->deleteDataFromLogByID($this->uploadEntry->getId());

		// Determine thumbnail path - use video extension for videos, regular extension otherwise
		if (preg_match('/video/i', $this->uploadEntry->getMimeType())) {
			$conf = ['thumbDir' => $this->thumbDir, 'prefix' => $this->prefix, 'thumbnailExtension' => $this->videoThumbnailExtension];
			$thumbPath = $this->uploadEntry->getVideoThumbPath($conf);
		} else {
			$conf = ['thumbDir' => $this->thumbDir, 'prefix' => $this->prefix, 'uploadDir' => ''];
			$thumbPath = $this->uploadEntry->getThumbPath($conf);
		}

		// Secure file deletion to prevent unauthorized access
		if (file_exists($thumbPath) && strpos(realpath($thumbPath), realpath($this->thumbDir)) === 0) {
			unlink($thumbPath);
		}

		// Delete the actual file
		$this->uploadedFileRepository->deleteFileByData($this->uploadEntry);

		if ($showMessage) {
			$this->uploaderHTML->drawMessageAndRedirectHome($this->lang->get('messages.fileDeleted'), $this->lang->get('messages.pageNoChange'));
		}
	}

	public function userDeletePost(): void {
		$password = $_POST['password'] ?? '';


		if (empty($password)) {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.deletionError'), $this->lang->get('errors.passwordBlank'));
		}


		if ($this->uploadEntry === null) {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.deletionError'), $this->lang->get('errors.fileNotFound'));
		}

		$postPassword = $this->uploadEntry->getPassword();

		// Check if password matches or if admin password is used
		if (password_verify($password, $postPassword) || $password === $this->adminPassword) {
			// Delete entry from log
			$this->uploadEntryRepository->deleteDataFromLogByID($this->uploadEntry->getId());

			// Determine thumbnail path - use video extension for videos, regular extension otherwise
			if (preg_match('/video/i', $this->uploadEntry->getMimeType())) {
				// Build config array for video thumbnail
				$conf = ['thumbDir' => $this->thumbDir, 'prefix' => $this->prefix, 'thumbnailExtension' => $this->videoThumbnailExtension];
				$thumbPath = $this->uploadEntry->getVideoThumbPath($conf);
			} else {
				// Use regular thumbnail path
				$conf = ['thumbDir' => $this->thumbDir, 'prefix' => $this->prefix, 'uploadDir' => ''];
				$thumbPath = $this->uploadEntry->getThumbPath($conf);
			}

			// Secure file deletion to prevent unauthorized access
			if (file_exists($thumbPath) && strpos(realpath($thumbPath), realpath($this->thumbDir)) === 0) {
				unlink($thumbPath);
			}

			// Delete the actual file
			$this->uploadedFileRepository->deleteFileByData($this->uploadEntry);

			$this->uploaderHTML->drawMessageAndRedirectHome($this->lang->get('messages.fileDeleted'), $this->lang->get('messages.pageNoChange'));
		} elseif (empty($postPassword) && $password !== $this->adminPassword) {
			$this->uploaderHTML->drawErrorPageAndExit(
				$this->lang->get('errors.deletionError'), 
				$this->lang->get('errors.noPasswordOnPost')
			);
		} else {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.deletionError'), $this->lang->get('errors.passwordIncorrect'));
		}
	}
}
