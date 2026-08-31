<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\actionLogEntry;
use TwintailUploader\Classes\board;
use TwintailUploader\Classes\boardRepository;
use TwintailUploader\Classes\themeManager;
use TwintailUploader\Classes\uploaderHTML;

use function TwintailUploader\Functions\generatePasswordHash;
use function TwintailUploader\Functions\getUserIP;

/**
 * Creation, deletion and settings changes for user boards.
 *
 * Owns everything that touches a board's directory on disk; the registry
 * itself lives in boardRepository.
 */
class boardController {
	private $lang;

	/** Heading and return link fail() uses, set by whichever entry point is running */
	private string $failHeading = '';
	private string $failReturnUrl = '';

	/** URIs that would collide with the repository layout or with routes */
	private const RESERVED_URIS = [
		'index', 'admin', 'api', 'boards', 'code', 'data', 'lang',
		'src', 'static', 'templates', 'thmb', 'user',
	];

	public function __construct(
		private array $conf,
		private boardRepository $boardRepository,
		private uploaderHTML $uploaderHTML,
		private actionLogController $actionLog,
	) {
		$this->lang = $this->uploaderHTML->getLang();
	}

	/**
	 * Validates the creation form, scaffolds the board directory and
	 * registers the board. Draws an error page and exits on bad input.
	 */
	public function createFromRequest(array $input): board {
		$this->failsReturnTo('boards.creationError', $this->conf['mainScript'] . '?request=createBoard');

		if (empty($this->conf['allowUserBoards'])) {
			$this->fail($this->lang->get('boards.creationDisabled'));
		}

		if ($this->boardRepository->countBoards() >= $this->conf['maxUserBoards']) {
			$this->fail($this->lang->get('boards.limitReached'));
		}

		$uri = strtolower(trim($input['uri'] ?? ''));
		if (!preg_match('/^[a-z0-9_-]{1,16}$/', $uri)) {
			$this->fail($this->lang->get('boards.invalidUri'));
		}
		if (in_array($uri, self::RESERVED_URIS, true)) {
			$this->fail($this->lang->get('boards.reservedUri'));
		}
		if ($this->boardRepository->uriExists($uri) || is_dir($this->boardDirPath($uri))) {
			$this->fail($this->lang->get('boards.uriTaken'));
		}

		$title = $this->cleanText($input['title'] ?? '', 64);
		if ($title === '') {
			$this->fail($this->lang->get('boards.titleRequired'));
		}

		$password = (string) ($input['ownerPassword'] ?? '');
		if (strlen($password) < 4 || strlen($password) > 64) {
			$this->fail($this->lang->get('boards.passwordLength'));
		}

		$prefix = strtolower(trim($input['prefix'] ?? ''));
		if ($prefix === '') {
			$prefix = 'up';
		}
		if (!preg_match('/^[a-z0-9_-]{1,10}$/', $prefix)) {
			$this->fail($this->lang->get('boards.invalidPrefix'));
		}

		$board = new board([
			(string) $this->boardRepository->getNextID(),
			$uri,
			$title,
			$this->cleanText($input['subTitle'] ?? '', 256),
			generatePasswordHash($password),
			bin2hex(random_bytes(16)),
			(string) time(),
			getUserIP(),
			!empty($input['listed']) ? '1' : '0',
			'0',
			!empty($input['commentRequired']) ? '1' : '0',
			$this->cleanText($input['defaultComment'] ?? '', 128),
			$prefix,
		]);

		if (!$this->scaffoldBoardDirectory($board)) {
			$this->fail($this->lang->get('boards.scaffoldFailed'));
		}

		if (!$this->boardRepository->add($board)) {
			$this->deleteBoardDirectory($board->getUri());
			$this->fail($this->lang->get('boards.registerFailed'));
		}

		// the board's history starts in its own log
		$this->actionLog->forBoard($board, $this->conf)->record(actionLogEntry::BOARD_CREATED, $board->getUri(), $board->getTitle());

		return $board;
	}

	/**
	 * Applies the settings a board owner is allowed to change.
	 *
	 * $input is raw request data, so every field is pulled out by name and run
	 * through a setter. Never loop over it: uri, prefix, ipSalt, locked and
	 * creatorIp are all board fields an owner must not be able to touch.
	 */
	public function updateSettings(board $board, array $input): void {
		$this->failsReturnTo('boards.settingsError', $this->conf['mainScript'] . '?request=admin&modPage=settings');

		$title = $this->cleanText($input['title'] ?? '', 64);
		if ($title === '') {
			$this->fail($this->lang->get('boards.titleRequired'));
		}

		$board->setTitle($title);
		$board->setSubTitle($this->cleanText($input['subTitle'] ?? '', 256));
		$board->setDefaultComment($this->cleanText($input['defaultComment'] ?? '', 128));
		$board->setCommentRequired(!empty($input['commentRequired']));
		$board->setListed(!empty($input['listed']));

		$this->applyThemeSettings($board, $input);

		$newPassword = (string) ($input['newPassword'] ?? '');
		if ($newPassword !== '') {
			if (strlen($newPassword) < 4 || strlen($newPassword) > 64) {
				$this->fail($this->lang->get('boards.passwordLength'));
			}
			$board->setOwnerPasswordHash(generatePasswordHash($newPassword));
		}

		$this->boardRepository->update($board);

		// already running inside the board, so this lands in its own log
		$this->actionLog->record(actionLogEntry::BOARD_SETTINGS, $board->getUri());
	}

	/**
	 * Stores the board's default theme and its custom palette.
	 *
	 * The palette never reaches the page as CSS: only names from
	 * themeManager::VARIABLES survive, and only values matching that class's
	 * patterns, so an owner cannot write a rule, a url(), or anything else of
	 * their own choosing into a stylesheet. The theme name is checked against
	 * the themes actually installed for the same reason.
	 *
	 * Colours are kept even when an installed theme is selected, so switching
	 * back and forth doesn't lose the owner's work.
	 */
	private function applyThemeSettings(board $board, array $input): void {
		$variables = themeManager::sanitizeVariables((array) ($input['themeVariables'] ?? []));
		$board->setCustomTheme(themeManager::serializeVariables($variables));

		$themeManager = new themeManager(
			$this->conf['staticPath'] . 'css/themes',
			$this->conf['staticUrl'] . 'css/themes'
		);

		$allowedThemes = $themeManager->getThemeNames();
		if ($variables !== []) {
			$allowedThemes[] = themeManager::CUSTOM_THEME;
		}

		$theme = $input['theme'] ?? '';
		// anything unrecognised — including a name posted as an array — means
		// the instance default, never a name of the submitter's own making
		$theme = is_string($theme) ? $theme : '';
		$board->setTheme(in_array($theme, $allowedThemes, true) ? $theme : '');
	}

	/**
	 * Sets a new owner password without knowing the old one (admin only).
	 */
	public function resetOwnerPassword(board $board, string $newPassword): void {
		$this->failsReturnTo('boards.manageError', $this->conf['mainScript'] . '?request=admin&modPage=manageBoards');

		if (strlen($newPassword) < 4 || strlen($newPassword) > 64) {
			$this->fail($this->lang->get('boards.passwordLength'));
		}

		$board->setOwnerPasswordHash(generatePasswordHash($newPassword));
		$this->boardRepository->update($board);

		// the owner has to be able to see that their password was changed
		$this->actionLog->forBoard($board, $this->conf)->record(actionLogEntry::BOARD_PASSWORD_RESET, $board->getUri());
	}

	/**
	 * Removes a board from the registry along with everything it stored.
	 */
	public function deleteBoard(board $board): void {
		// the board's own log goes with its directory, so this one is instance-wide
		$this->actionLog->record(actionLogEntry::BOARD_DELETED, $board->getUri(), $board->getTitle());

		$this->boardRepository->delete($board->getUri());
		$this->deleteBoardDirectory($board->getUri());
	}

	/**
	 * Total bytes and file count a board is using, for the admin listing.
	 *
	 * @return array{0:int,1:int} [total bytes, file count]
	 */
	public function getBoardUsage(board $board): array {
		$logPath = $this->boardDirPath($board->getUri()) . '/data/' . $this->conf['logFile'];
		if (!file_exists($logPath)) {
			return [0, 0];
		}

		$totalSize = 0;
		$fileCount = 0;

		$fileHandle = fopen($logPath, 'r');
		if (!$fileHandle) {
			return [0, 0];
		}

		while (($line = fgets($fileHandle)) !== false) {
			if (trim($line) === '') continue;
			$fields = explode('<>', $line);
			$totalSize += (int) ($fields[5] ?? 0);
			$fileCount++;
		}
		fclose($fileHandle);

		return [$totalSize, $fileCount];
	}

	/**
	 * Creates boards/<uri>/ with its upload, thumbnail and data directories,
	 * plus the stub that hands the request to the main script.
	 */
	private function scaffoldBoardDirectory(board $board): bool {
		$boardDir = $this->boardDirPath($board->getUri());

		$directories = [
			$boardDir,
			$boardDir . '/' . rtrim($this->conf['uploadDir'], '/'),
			$boardDir . '/' . rtrim($this->conf['thumbDir'], '/'),
			$boardDir . '/data',
		];

		foreach ($directories as $directory) {
			if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
				return false;
			}
		}

		// Keep the board's flat files unreadable over HTTP, like the root data dir
		file_put_contents($boardDir . '/data/.htaccess', "Order Deny,Allow\nDeny from all\n");
		file_put_contents($boardDir . '/data/' . $this->conf['logFile'], '');
		file_put_contents($boardDir . '/data/' . $this->conf['counterFile'], '0');
		file_put_contents($boardDir . '/data/' . $this->conf['actionLogFile'], '');
		file_put_contents($boardDir . '/data/banlist.dat', '');
		file_put_contents($boardDir . '/data/banned_hashes.dat', '');

		$stub = "<?php\n"
			. "// Generated board stub — hands the request to the main uploader script.\n"
			. "\$boardUri = basename(__DIR__);\n"
			. "require dirname(__DIR__, 2) . '/" . $this->conf['mainScript'] . "';\n";

		return file_put_contents($boardDir . '/index.php', $stub) !== false;
	}

	/**
	 * Recursively removes a board directory, after checking it really is one.
	 */
	private function deleteBoardDirectory(string $uri): void {
		if (!preg_match('/^[a-z0-9_-]{1,16}$/', $uri)) {
			return;
		}

		$boardsRoot = realpath(\ROOT_DIR . '/' . $this->conf['boardsDir']);
		$boardDir = realpath($this->boardDirPath($uri));

		// Refuse anything that isn't a direct child of the boards directory
		if ($boardsRoot === false || $boardDir === false || dirname($boardDir) !== $boardsRoot) {
			return;
		}

		$this->removeDirectoryTree($boardDir);
	}

	private function removeDirectoryTree(string $directory): void {
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			$item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}

		rmdir($directory);
	}

	private function boardDirPath(string $uri): string {
		return \ROOT_DIR . '/' . $this->conf['boardsDir'] . $uri;
	}

	private function cleanText(string $value, int $maxLength): string {
		$value = strip_tags(str_replace(["\r\n", "\r", "\n", "\t", "\0"], '', $value));
		return trim(mb_substr($value, 0, $maxLength));
	}

	/**
	 * Names the error heading and the page to go back to for whatever operation
	 * is about to run, so every fail() below reads as just the reason.
	 */
	private function failsReturnTo(string $headingKey, string $returnUrl): void {
		$this->failHeading = $this->lang->get($headingKey);
		$this->failReturnUrl = $returnUrl;
	}

	private function fail(string $message): void {
		$this->uploaderHTML->drawBoardErrorPageAndExit($this->failHeading, $message, $this->failReturnUrl);
	}
}
