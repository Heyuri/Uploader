<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\actionLogEntry;
use TwintailUploader\Classes\banChecker;
use TwintailUploader\Classes\cloudflareAPI;
use TwintailUploader\Classes\fileSource;
use TwintailUploader\Classes\languageManager;
use TwintailUploader\Classes\uploadedFileRepository;
use TwintailUploader\Classes\uploadEntry;
use TwintailUploader\Classes\uploadEntryRepository;
use TwintailUploader\Classes\uploaderHTML;

/**
 * Mod actions for the instance-wide recent files page.
 *
 * A deletion runs against the source the file came from — every board keeps its
 * own log, counter and directories — while bans go on the instance-wide lists,
 * which every board reads as inherited bans. The page is global-admin only, so
 * uploader addresses are never hidden here.
 */
class recentFilesController {
	public function __construct(
		private banChecker $banChecker,
		private uploaderHTML $uploaderHTML,
		private languageManager $lang,
		private actionLogController $actionLog,
		private array $conf
	) {}

	public function deleteFile(fileSource $source, int $fileID): void {
		$entry = $this->requireEntry($source, $fileID, $this->lang->get('errors.failedToDelete'));

		$this->removeEntry($source, $entry);
	}

	/**
	 * Bans the uploader behind an entry instance-wide, leaving the file alone.
	 *
	 * @return string the address that was banned
	 */
	public function banUploader(fileSource $source, int $fileID): string {
		$entry = $this->requireEntry($source, $fileID, $this->lang->get('errors.banError'));
		$ip = $this->requireIp($entry);

		$this->banChecker->addBan($ip);

		return $ip;
	}

	/**
	 * Bans the file's contents instance-wide, deletes it, and bans its uploader.
	 *
	 * @return string the address that was banned
	 */
	public function banFile(fileSource $source, int $fileID): string {
		$entry = $this->requireEntry($source, $fileID, $this->lang->get('errors.banError'));
		$ip = $entry->getIp();

		// hash the file before it goes
		$filePath = $entry->getFilePath($source->getConf());
		if (file_exists($filePath)) {
			$fileHash = hash_file('sha256', $filePath);
			if ($fileHash !== false) {
				$this->banChecker->addBannedFileHash($fileHash);
			}
		}

		$this->removeEntry($source, $entry);

		if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
			$this->banChecker->addBan($ip);
		}

		return $ip;
	}

	private function removeEntry(fileSource $source, uploadEntry $entry): void {
		// the deletion is recorded where the file lived, so the board it was
		// posted on keeps it in its own history
		$this->actionLogFor($source)->recordFile(actionLogEntry::DELETE_MOD, $entry, $source->getConf());

		$this->entryRepository($source)->deleteDataFromLogByID($entry->getId());
		$this->fileRepository($source)->deleteFileByData($entry);
	}

	/**
	 * The recorder writing into the log of wherever the file lives.
	 */
	private function actionLogFor(fileSource $source): actionLogController {
		$board = $source->getBoard();

		return $board !== null ? $this->actionLog->forBoard($board, $this->conf) : $this->actionLog;
	}

	/**
	 * Loads the entry, or draws an error page and exits. A missing log or an ID
	 * that isn't in it both mean the file is gone.
	 */
	private function requireEntry(fileSource $source, int $fileID, string $errorHeading): uploadEntry {
		if ($fileID > 0 && file_exists($source->getLogPath())) {
			$entry = $this->entryRepository($source)->getDataByID($fileID);

			if ($entry->getId() === $fileID) {
				return $entry;
			}
		}

		$this->uploaderHTML->drawErrorPageAndExit($errorHeading, $this->lang->get('errors.fileNotFound'));
	}

	private function requireIp(uploadEntry $entry): string {
		$ip = $entry->getIp();

		if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
			$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.banError'), $this->lang->get('errors.invalidIPAddress'));
		}

		return $ip;
	}

	private function entryRepository(fileSource $source): uploadEntryRepository {
		return new uploadEntryRepository($source->getLogPath(), $source->getCounterPath());
	}

	private function fileRepository(fileSource $source): uploadedFileRepository {
		return new uploadedFileRepository($source->getConf(), new cloudflareAPI($source->getConf()));
	}
}
