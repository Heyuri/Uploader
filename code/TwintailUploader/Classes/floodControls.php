<?php
namespace TwintailUploader\Classes;


class floodControls {
	public function __construct(
		private int $coolDownTime,
		private uploadEntryRepository $uploadEntryRepository
	) {}

	public function isFlooding(): bool {
		$lastPostID = $this->uploadEntryRepository->getLastID();
		$lastPost = $this->uploadEntryRepository->getDataByID($lastPostID);
		if (!$lastPost) {
			// Can't flood if there isn't even a single post
			return false;
		}

		$lastTime = $lastPost->getTime();
		if ((int)$lastTime + $this->coolDownTime > time()) {
			return true;
		} else {
			return false;
		}
	}
}