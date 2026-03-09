<?php
namespace HeyuriUploader\Classes;

class cookieSettingsManager {
	private $defaultCookieValues;

	public function __construct(array $defaultCookieValues) {
		$this->defaultCookieValues = $defaultCookieValues;
	}

	public function loadCookieSettings(): void {
		if (!isset($_COOKIE['settings'])) {
			$cookie = implode("<>", $this->defaultCookieValues);
		} else {
			$cookie = $_COOKIE['settings'];
		}

		if (isset($_POST['action']) && $_POST['action'] === "setUserSettings") {
			// The order of this array must be the same as $config['defaultCookieValues']
			$cookie = implode("<>", array(
				$_POST['showDeleteButton'] ?? "",
				$_POST['showComment'] ?? "",
				$_POST['showPreviewImage'] ?? "",
				$_POST['showFileName'] ?? "",
				$_POST['showFileSize'] ?? "",
				$_POST['showMimeType'] ?? "",
				$_POST['showDate'] ?? ""
			));
		}

		setcookie("settings", $cookie, time() + 365 * 24 * 3600);
		$_COOKIE['settings'] = $cookie;
	}

	public function getSplitCookie(): array {
		$cookieKeys = ['showDeleteButton', 'showComment', 'showPreviewImage', 'showFileName', 'showFileSize', 'showMimeType', 'showDate'];
		$cookieData = explode("<>", $_COOKIE['settings'] ?? []);

		if (sizeof($cookieData) !== sizeof($cookieKeys)) {
			$_COOKIE['settings'] = implode("<>", $this->defaultCookieValues);
			$cookieData = $this->defaultCookieValues;
		}

		// Ensure arrays have matching lengths before combining
		if (sizeof($cookieData) !== sizeof($cookieKeys)) {
			$cookieData = array_pad($cookieData, sizeof($cookieKeys), "");
		}

		return array_combine($cookieKeys, $cookieData);
	}
}

