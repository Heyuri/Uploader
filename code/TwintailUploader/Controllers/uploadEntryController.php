<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\actionLogEntry;
use TwintailUploader\Classes\uploadEntry;
use TwintailUploader\Classes\uploadEntryRepository;
use TwintailUploader\Classes\uploadedFileRepository;
use TwintailUploader\Classes\uploaderHTML;

class uploadEntryController {
	private $lang;

	public function __construct(
		private uploadEntry $uploadEntry,
		private uploadEntryRepository $uploadEntryRepository,
		private uploadedFileRepository $uploadedFileRepository,
		private uploaderHTML $uploaderHTML,
		private array $conf,
		private actionLogController $actionLog
	) {
		$this->lang = $this->uploaderHTML->getLang();
	}

	public function adminDeletePost(bool $showMessage = true): void {
		if ($this->uploadEntry === null) {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.deletionError'), $this->lang->get('errors.fileNotFound'));
		}

		// recorded as whoever the request authenticated as — admin or board owner
		$this->actionLog->recordFile(actionLogEntry::DELETE_MOD, $this->uploadEntry, $this->conf);

		$this->deleteEntry();

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

		// Check if password matches or if admin password is used (constant-time)
		$isAdmin = hash_equals((string) $this->conf['adminPassword'], (string) $password);
		if (password_verify($password, $postPassword) || $isAdmin) {
			$this->actionLog->recordFile(actionLogEntry::DELETE_USER, $this->uploadEntry, $this->conf);

			$this->deleteEntry();

			$this->uploaderHTML->drawMessageAndRedirectHome($this->lang->get('messages.fileDeleted'), $this->lang->get('messages.pageNoChange'));
		} elseif (empty($postPassword) && !$isAdmin) {
			$this->uploaderHTML->drawErrorPageAndExit(
				$this->lang->get('errors.deletionError'),
				$this->lang->get('errors.noPasswordOnPost')
			);
		} else {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.deletionError'), $this->lang->get('errors.passwordIncorrect'));
		}
	}

	/**
	 * Removes the entry from the log and takes its file and thumbnail with it.
	 */
	private function deleteEntry(): void {
		// Delete entry from log
		$this->uploadEntryRepository->deleteDataFromLogByID($this->uploadEntry->getId());

		// Delete the actual file and its thumbnail
		$this->uploadedFileRepository->deleteFileByData($this->uploadEntry);
	}
}
