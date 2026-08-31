<?php
namespace TwintailUploader\Classes;

/**
 * A single user board, backed by one line of data/boards.log.
 *
 * Field order is load-bearing — see boardRepository::FIELDS.
 */
class board {
	private string $id = '0';
	private string $uri = '';
	private string $title = '';
	private string $subTitle = '';
	private string $ownerPasswordHash = '';
	private string $ipSalt = '';
	private string $createdTime = '0';
	private string $creatorIp = '';
	private string $listed = '1';
	private string $locked = '0';
	private string $commentRequired = '1';
	private string $defaultComment = '';
	private string $prefix = 'up';
	private string $theme = '';
	private string $customTheme = '';

	public function __construct(array $boardData) {
		foreach (boardRepository::FIELDS as $index => $property) {
			if (isset($boardData[$index])) {
				$this->$property = (string) $boardData[$index];
			}
		}
	}

	/* Getters */
	public function getId(): int { return (int) $this->id; }
	public function getUri(): string { return $this->uri; }
	public function getTitle(): string { return $this->title; }
	public function getSubTitle(): string { return $this->subTitle; }
	public function getOwnerPasswordHash(): string { return $this->ownerPasswordHash; }
	public function getIpSalt(): string { return $this->ipSalt; }
	public function getCreatedTime(): int { return (int) $this->createdTime; }
	public function getCreatorIp(): string { return $this->creatorIp; }
	public function isListed(): bool { return $this->listed === '1'; }
	public function isLocked(): bool { return $this->locked === '1'; }
	public function isCommentRequired(): bool { return $this->commentRequired === '1'; }
	public function getDefaultComment(): string { return $this->defaultComment; }
	public function getPrefix(): string { return $this->prefix; }
	public function getTheme(): string { return $this->theme; }
	public function getCustomTheme(): string { return $this->customTheme; }

	/** The owner's palette, revalidated on the way out of the log */
	public function getCustomThemeVariables(): array {
		return themeManager::parseVariables($this->customTheme);
	}

	/**
	 * Whether visitors are offered this board's palette. An owner who picks an
	 * installed theme keeps their colours stored but stops publishing them.
	 */
	public function usesCustomTheme(): bool {
		return $this->theme === themeManager::CUSTOM_THEME && $this->getCustomThemeVariables() !== [];
	}

	/* Setters for the fields an owner or the admin may change */
	public function setTitle(string $value): void { $this->title = $value; }
	public function setSubTitle(string $value): void { $this->subTitle = $value; }
	public function setOwnerPasswordHash(string $value): void { $this->ownerPasswordHash = $value; }
	public function setListed(bool $value): void { $this->listed = $value ? '1' : '0'; }
	public function setLocked(bool $value): void { $this->locked = $value ? '1' : '0'; }
	public function setCommentRequired(bool $value): void { $this->commentRequired = $value ? '1' : '0'; }
	public function setDefaultComment(string $value): void { $this->defaultComment = $value; }
	public function setTheme(string $value): void { $this->theme = $value; }
	public function setCustomTheme(string $value): void { $this->customTheme = $value; }

	/**
	 * Returns the fields in log order, ready to be joined by the delimiter.
	 */
	public function toArray(): array {
		$values = [];
		foreach (boardRepository::FIELDS as $property) {
			$values[] = $this->$property;
		}
		return $values;
	}

	/**
	 * Board directory relative to the repository root, e.g. "boards/foo/".
	 */
	public function getDir(array $conf): string {
		return $conf['boardsDir'] . $this->uri . '/';
	}

	/**
	 * URL of the board's index, relative to the repository root.
	 */
	public function getUrl(array $conf): string {
		return $this->getDir($conf);
	}

	/**
	 * Salted, truncated hash of an IP. This is all a board owner ever sees of
	 * an uploader — the salt is per board, so hashes can't be correlated
	 * across boards.
	 */
	public function hashIp(string $ip): string {
		if ($ip === '') {
			return '';
		}
		return substr(hash('sha256', $this->ipSalt . $ip), 0, 12);
	}

	/**
	 * Derives this board's configuration from the global one.
	 *
	 * Paths stay relative because the entry point chdir()s into the board
	 * directory, so every relative path doubles as a URL relative to
	 * boards/<uri>/index.php, exactly like the main uploader.
	 *
	 * Security-relevant keys (allowedExtensions, extensionsToBeConvertedToText,
	 * adminPassword, chunkSize) are deliberately inherited and never overridden.
	 */
	public function applyToConfig(array $conf): array {
		// number of "../" needed to climb from the board dir back to the root
		$depth = substr_count(trim($conf['boardsDir'], '/'), '/') + 2;
		$toRoot = str_repeat('../', $depth);

		$conf['boardTitle'] = $this->title;
		$conf['boardSubTitle'] = $this->subTitle;
		// the instance's own script, for links that leave the board — kept
		// before mainScript is rebound to this board's stub
		$conf['rootScript'] = $toRoot . $conf['mainScript'];
		$conf['mainScript'] = 'index.php';
		$conf['staticUrl'] = $toRoot . 'static/';
		$conf['staticPath'] = $toRoot . 'static/';
		$conf['home'] = $conf['rootScript'] . '?request=boards';
		$conf['uploadDir'] = 'src/';
		$conf['thumbDir'] = 'thmb/';
		$conf['prefix'] = $this->prefix;
		$conf['defaultComment'] = $this->defaultComment;
		$conf['commentRequired'] = $this->isCommentRequired();
		$conf['maxAmountOfFiles'] = $conf['boardMaxAmountOfFiles'];
		$conf['maxTotalSize'] = $conf['boardMaxTotalSize'];
		$conf['maxUploadSize'] = min($conf['maxUploadSize'], $conf['boardMaxUploadSize']);

		// Theming is cosmetic, so it is one of the few things an owner does
		// override. The palette is handed over as validated variables rather
		// than as CSS — drawHeader() is what turns it into a stylesheet.
		$customVariables = $this->getCustomThemeVariables();

		// what the board would show with no theme of its own; the palette
		// fills its unset variables from it, so a partial one is still a
		// complete theme
		$conf['instanceDefaultTheme'] = $conf['defaultTheme'];

		if ($this->theme !== '' && ($this->theme !== themeManager::CUSTOM_THEME || $customVariables !== [])) {
			$conf['defaultTheme'] = $this->theme;
		}
		$conf['customThemeVariables'] = $this->usesCustomTheme() ? $customVariables : [];

		// keep CDN purges pointed at this board's own directory
		if (!empty($conf['cloudflareBaseUrl'])) {
			$conf['cloudflareBaseUrl'] = rtrim($conf['cloudflareBaseUrl'], '/') . '/' . $conf['boardsDir'] . $this->uri;
		}

		return $conf;
	}

	/**
	 * Derives a config that reads this board's files from the repository root —
	 * the same paths applyToConfig() leaves relative, prefixed with the board
	 * directory.
	 *
	 * Instance-wide mod pages never chdir() into a board, so this is what lets
	 * them resolve a board's files and thumbnails. Only the path- and
	 * naming-related keys change: nothing here decides what may be uploaded.
	 */
	public function applyToRootConfig(array $conf): array {
		$boardDir = $this->getDir($conf);

		$conf['boardTitle'] = $this->title;
		$conf['boardSubTitle'] = $this->subTitle;
		$conf['prefix'] = $this->prefix;
		$conf['uploadDir'] = $boardDir . $conf['uploadDir'];
		$conf['thumbDir'] = $boardDir . $conf['thumbDir'];

		if (!empty($conf['cloudflareBaseUrl'])) {
			$conf['cloudflareBaseUrl'] = rtrim($conf['cloudflareBaseUrl'], '/') . '/' . $conf['boardsDir'] . $this->uri;
		}

		return $conf;
	}
}
