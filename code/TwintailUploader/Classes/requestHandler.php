<?php
namespace TwintailUploader\Classes;

use TwintailUploader\Controllers\actionLogController;
use TwintailUploader\Controllers\sessionController;
use TwintailUploader\Controllers\uploadEntryController;
use TwintailUploader\Classes\uploadEntryRepository;
use TwintailUploader\Controllers\uploadedFileService;
use TwintailUploader\Controllers\chunkUploadService;
use TwintailUploader\Controllers\boardController;
use TwintailUploader\Controllers\recentFilesController;

use function TwintailUploader\Functions\getUserIP;
use function TwintailUploader\Functions\redirect;

class requestHandler {
	private array $conf;
	private uploadEntryRepository $uploadEntryRepository;
	private uploaderHTML $uploaderHTML;
	private floodControls $floodControls;
	private banChecker $banChecker;
	private logFile $logFile;
	private cookieSettingsManager $cookieSettingsManager;
	private uploadPasswordCookie $uploadPasswordCookie;
	private uploadedFileRepository $uploadedFileRepository;
	private searchRepository $searchRepository;
	private languageManager $languageManager;
	private boardRepository $boardRepository;
	private temporaryHosting $temporaryHosting;
	private ?board $board;
	private actionLogController $actionLog;
	private ?sessionController $sessionController = null;

	// Define request constants
	private const REQUEST_DELETE_FILE = 'deleteFile';
	private const REQUEST_DELETE_FORM = 'deleteFileForm';
	private const REQUEST_INDEX = 'index';
	private const REQUEST_SETTINGS_FORM = 'settingsForm';
	private const REQUEST_SEARCH = 'search';
	private const REQUEST_UPLOAD = 'uploadFile';
	private const REQUEST_LOGIN = 'login';
	private const REQUEST_ADMIN = 'admin';
	private const REQUEST_CATALOG = 'catalog';
	private const REQUEST_UPLOAD_CHUNK = 'uploadChunk';
	private const REQUEST_FINALIZE_CHUNK = 'finalizeChunkUpload';
	private const REQUEST_LOGOUT = 'logout';
	private const REQUEST_BOARDS = 'boards';
	private const REQUEST_CREATE_BOARD = 'createBoard';

	public function __construct(array $config, languageManager $languageManager, ?board $board = null) {
		$this->conf = $config;
		$this->languageManager = $languageManager;
		$this->board = $board;
		$this->uploadEntryRepository = new uploadEntryRepository(
			\DATA_DIR . $this->conf['logFile'],
			\DATA_DIR . $this->conf['counterFile']);
		$this->uploaderHTML = new uploaderHTML($config, $languageManager, $board);
		// a board reads the instance-wide ban lists too, but only ever writes its own
		$this->banChecker = new banChecker(\DATA_DIR, $board !== null ? \GLOBAL_DATA_DIR : null);
		$this->floodControls = new floodControls($config['coolDownTime'], $this->uploadEntryRepository);
		$this->logFile = new logFile($config);
		$this->cookieSettingsManager = new cookieSettingsManager($config['defaultCookieValues']);
		$this->uploadPasswordCookie = new uploadPasswordCookie();
		$this->uploadedFileRepository = new uploadedFileRepository($config, new cloudflareAPI($config));
		$this->searchRepository = new searchRepository($this->logFile);
		$this->boardRepository = new boardRepository(\GLOBAL_DATA_DIR . 'boards.log');
		$this->temporaryHosting = new temporaryHosting($config, $this->logFile, $this->uploadedFileRepository, $this->makeActionLog());
	}

	/**
	 * The recorder every action this request performs goes through. It writes
	 * into the scope being served — a board records into its own data/, like its
	 * upload log and ban lists do.
	 */
	private function makeActionLog(): actionLogController {
		if (!isset($this->actionLog)) {
			$this->actionLog = new actionLogController(
				new actionLogRepository(\DATA_DIR . $this->conf['actionLogFile'], (int) $this->conf['actionLogMaxEntries']),
				!empty($this->conf['actionLog']),
				$this->board !== null ? $this->board->getUri() : ''
			);
		}

		return $this->actionLog;
	}


	public function handleRequest(): void {
		$pageRequest = $_REQUEST['request'] ?? self::REQUEST_INDEX;

		// Take out whatever expired since the last request. Throttled, so this
		// costs nothing on all but one request a minute.
		$this->temporaryHosting->sweep();

		// Handle chunk upload routes early — these return JSON and must not output HTML
		if ($pageRequest === self::REQUEST_UPLOAD_CHUNK || $pageRequest === self::REQUEST_FINALIZE_CHUNK) {
			$this->handleChunkRequest($pageRequest);
			return;
		}

		$this->cookieSettingsManager->loadCookieSettings();

		switch ($pageRequest) {
			case self::REQUEST_DELETE_FILE:
				$fileID = $_POST['deleteFileID'] ?? '';

				// Validate file ID as a proper integer
				if (!filter_var($fileID, FILTER_VALIDATE_INT)) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.failedToDelete'), $this->languageManager->get('errors.invalidFileID'));
				}

				// Retrieve post data
				$uploadEntry = $this->uploadEntryRepository->getDataByID((int) $fileID);

				$this->makeUploadEntryController($uploadEntry)->userDeletePost();
			break;

			case self::REQUEST_SETTINGS_FORM:
				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawSettingsForm();
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_SEARCH:
				$this->requireListing();

				// url of the search page
				$url = $this->conf['mainScript'];
				$page = max(1, (int)($_GET['pageNumber'] ?? 1));

				$searchParameters = [
					'originalFileName' => $_GET['originalFileName'] ?? null,
					'comment' => $_GET['comment'] ?? null,
					'fileExtension' => $_GET['fileExtension'] ?? null,
					'mimeType' => $_GET['mimeType'] ?? null,
					'sortDir' => $_GET['sortDir'] ?? null,
				];

				// an untouched form hasn't found nothing, it hasn't asked yet —
				// so there is no log to read and nothing to report
				$hasSearchCriteria = $this->hasSearchCriteria($searchParameters);

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawSearchForm($url, $searchParameters);

				if ($hasSearchCriteria) {
					$this->uploaderHTML->drawSearchResults(
						$this->searchRepository->getSearchResults($searchParameters),
						$page,
						$searchParameters
					);
				}

				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_DELETE_FORM:
				$fileID = $_GET['deleteFileID'] ?? '';
				if (!$fileID) $this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.noFileIDSelected'));

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawDeletionForm(htmlspecialchars($fileID, ENT_QUOTES, 'UTF-8'));
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_INDEX:
				$pageNumber = $_GET['pageNumber'] ?? 1;

				$url = htmlspecialchars($this->conf['mainScript']) . '?request=' . self::REQUEST_INDEX;
				$uploadUrl = htmlspecialchars($this->conf['mainScript']) . '?request=' . self::REQUEST_UPLOAD;

				$this->uploadPasswordCookie->ensure();

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawUploadForm($uploadUrl);
				$this->uploaderHTML->drawPageingBar($url, $pageNumber);

				// with no listing below them the links are the last thing on the
				// page, so they leave the closing rule to the footer
				$this->uploaderHTML->drawActionLinks(!$this->isUnlisted());

				// an unlisted uploader hands out links instead of showing files
				if (!$this->isUnlisted()) {
					$this->uploaderHTML->drawFileListing($pageNumber);
					$this->drawBottomPagingBar($url, $pageNumber);
				}

				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_CATALOG:
				$this->requireListing();

				$pageNumber = $_GET['pageNumber'] ?? 1;

				$url = htmlspecialchars($this->conf['mainScript']) . '?request=catalog';
				$uploadUrl = htmlspecialchars($this->conf['mainScript']) . '?request=' . self::REQUEST_UPLOAD;

				$this->uploadPasswordCookie->ensure();

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawUploadForm($uploadUrl);
				$this->uploaderHTML->drawPageingBar($url, $pageNumber);
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawCatalog($pageNumber);
				$this->drawBottomPagingBar($url, $pageNumber);
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_UPLOAD:
				if ($this->board !== null && $this->board->isLocked()) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.uploadRejected'), $this->languageManager->get('boards.boardLocked'));
				}

				if ($this->banChecker->isBanned(getUserIP())) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.bannedFromUploading'));
				}

				if ($this->floodControls->isFlooding(getUserIP())) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.uploadRejected'), $this->languageManager->get('errors.mustWaitBeforePosting'));
				}

				$uploadedFileService = new uploadedFileService($this->uploadedFileRepository, $this->uploadEntryRepository, $this->logFile, $this->uploaderHTML, $this->conf['allowedExtensions'], $this->conf['extensionsToBeConvertedToText'], $this->conf['maxAmountOfFiles'], $this->conf['deleteOldestOnMaxFiles'], $this->banChecker, $this->temporaryHosting, $this->conf['maxUploadSize'], $this->actionLog, $this->conf);
				$uploadEntry = $uploadedFileService->processFiles();

				// there is no listing to send an unlisted uploader back to, so
				// hand them the link to their own file instead
				if ($this->isUnlisted() && $uploadEntry !== null) {
					$this->uploaderHTML->drawHeader();
					$this->uploaderHTML->drawUploadCompletePage($uploadEntry);
					$this->uploaderHTML->drawFooter();
					return;
				}

				// redirect to index or catalog
				if(($_POST['requestFrom'] ?? '') === 'catalog') {
					redirect($this->conf['mainScript'] . '?request=catalog');
				} else {
					redirect($this->conf['mainScript'] . '?request=' . self::REQUEST_INDEX);
				}
			break;

			case self::REQUEST_BOARDS:
				$this->requireGlobalContext($pageRequest);

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawBoardListing($this->boardRepository->getListed());
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_CREATE_BOARD:
				$this->requireGlobalContext($pageRequest);

				if (empty($this->conf['allowUserBoards'])) {
					$this->uploaderHTML->drawBoardErrorPageAndExit($this->languageManager->get('boards.creationError'), $this->languageManager->get('boards.creationDisabled'), $this->conf['mainScript'] . '?request=boards');
				}

				// the creation form posts back to this same route
				if (($_POST['action'] ?? '') === 'createBoard') {
					$boardController = new boardController($this->conf, $this->boardRepository, $this->uploaderHTML, $this->actionLog);
					$newBoard = $boardController->createFromRequest($_POST);

					// redirect to the confirmation so a refresh can't resubmit
					redirect($this->conf['mainScript'] . '?request=createBoard&created=' . urlencode($newBoard->getUri()));
				}

				$createdBoard = $this->boardRepository->getByUri($_GET['created'] ?? '');

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();

				if ($createdBoard !== null) {
					$this->uploaderHTML->drawBoardCreatedPage($createdBoard);
				} else {
					$this->uploaderHTML->drawBoardCreationForm();
				}

				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_LOGIN:
				$loginHandler = new loginHandler($this->conf['mainScript'], $this->conf['adminPassword'], $this->uploaderHTML, $this->board, $this->actionLog);

				$loginHandler->invoke();
			break;

			case self::REQUEST_LOGOUT:
				$session = new session;
				$sessionController = new sessionController($session);

				// on a board only the owner session for that board is dropped —
				// a global admin logs out from the main admin room
				if ($this->board !== null) {
					// only a session that was actually logged in is worth recording,
					// or anyone could fill the log by asking to log out
					if ($sessionController->isBoardOwner($this->board->getUri())) {
						$this->actionLog->setActor(actionLogEntry::ACTOR_OWNER);
						$this->actionLog->record(actionLogEntry::LOGOUT, $this->board->getUri());
					}

					$sessionController->logOutBoard($this->board->getUri());
				} else {
					if ($sessionController->isLoggedIn()) {
						$this->actionLog->setActor(actionLogEntry::ACTOR_ADMIN);
						$this->actionLog->record(actionLogEntry::LOGOUT);
					}

					$session->destroy();
				}

				redirect($this->conf['mainScript']);
			break;

			case self::REQUEST_ADMIN:
				$session = new session;
				$sessionController = new sessionController($session);

				$isGlobalAdmin = $sessionController->isLoggedIn();

				// remember the session for CSRF checks and expose the token to
				// the admin forms/links this request is about to render
				$this->sessionController = $sessionController;
				$this->uploaderHTML->setCsrfToken($sessionController->getCsrfToken());

				// everything this request records from here on is a mod action
				$this->actionLog->setActor($isGlobalAdmin ? actionLogEntry::ACTOR_ADMIN : actionLogEntry::ACTOR_OWNER);

				if ($this->board !== null) {
					$isOwner = $sessionController->isBoardOwner($this->board->getUri());

					if (!$isGlobalAdmin && !$isOwner) {
						$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.notAuthorized'), $this->languageManager->get('errors.mustBeLoggedIn'));
					}

					// global admins moderate boards with real IPs, owners only ever see hashes
					$this->handleBoardAdmin($isGlobalAdmin);
					return;
				}

				if(!$isGlobalAdmin) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.notAuthorized'), $this->languageManager->get('errors.mustBeLoggedIn'));
				}

				$this->handleGlobalAdmin();
			break;

			default:
				$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.pageNotFound'), $this->languageManager->get('errors.contactAdmin'));
			break;
		}
	}

	/**
	 * Mod pages for the instance as a whole.
	 */
	private function handleGlobalAdmin(): void {
		// These pages read and rewrite the instance config — which holds
		// adminPassword and every path the app uses — so they must never run
		// against a board, whatever the request asks for.
		if ($this->board !== null) {
			$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.notAuthorized'), $this->languageManager->get('errors.contactAdmin'));
		}

		// get mod page paramter
		$modPage = $_REQUEST['modPage'] ?? null;
		$modAction = $_REQUEST['modAction'] ?? null;

		// handle mod pages
		if($modPage === 'manageFiles') {
			if ($this->handleFileModAction($modAction, false)) {
				return;
			}

			$pageNumber = $_GET['pageNumber'] ?? 1;

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawManageFilesPage($pageNumber, false, $this->addressFilterFromRequest(false));
			$this->uploaderHTML->drawFooter();
		}
		else if($modPage === 'recentFiles') {
			if ($this->handleRecentFileModAction($modAction)) {
				return;
			}

			$pageNumber = max(1, (int) ($_GET['pageNumber'] ?? 1));

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawRecentFilesPage(
				$this->makeRecentFilesRepository()->getAllEntries(),
				$pageNumber,
				$this->addressFilterFromRequest(false)
			);
			$this->uploaderHTML->drawFooter();
		}
		else if($modPage === 'manageBans') {
			if ($this->handleBanModAction($modAction, false)) {
				return;
			}

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawManageBansPage($this->banChecker);
			$this->uploaderHTML->drawFooter();
		}
		else if($modPage === 'actionLog') {
			$this->drawActionLogPage(false);
		}
		else if($modPage === 'manageBoards') {
			if ($this->handleBoardModAction($modAction)) {
				return;
			}

			$boardController = new boardController($this->conf, $this->boardRepository, $this->uploaderHTML, $this->actionLog);

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawManageBoardsPage($this->boardRepository->getAll(), $boardController);
			$this->uploaderHTML->drawFooter();
		}
		else if($modPage === 'config') {
			if ($modAction === 'saveConfig') {
				$this->requireCsrf();

				$newValues = $_POST['conf'] ?? [];
				if (!is_array($newValues)) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.configError'), $this->languageManager->get('errors.invalidFormData'));
				}

				$configFile = \ROOT_DIR . '/config.php';
				$conf = require $configFile;
				$changedKeys = [];

				foreach ($newValues as $key => $value) {
					if (!array_key_exists($key, $conf) || is_array($conf[$key])) {
						continue;
					}

					$oldValue = $conf[$key];

					if (is_bool($conf[$key])) {
						$conf[$key] = ($value === '1');
					} elseif (is_int($conf[$key])) {
						$conf[$key] = (int) $value;
					} else {
						$conf[$key] = (string) $value;
					}

					if ($conf[$key] !== $oldValue) {
						$changedKeys[] = $key;
					}
				}

				$this->writeConfig($configFile, $conf);
				$this->conf = $conf;

				// names only: a config value can be the admin password
				if (!empty($changedKeys)) {
					$this->actionLog->record(actionLogEntry::CONFIG_SAVED, '', implode(', ', $changedKeys));
				}

				redirect($this->conf['mainScript'] . '?request=admin&modPage=config');
				return;
			}

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawConfigEditor();
			$this->uploaderHTML->drawFooter();
		}
		else {
			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawAdminDashboard();
			$this->uploaderHTML->drawFooter();
		}
	}

	/**
	 * Mod pages scoped to a single user board.
	 *
	 * $isGlobalAdmin decides whether uploader IPs are shown as-is or as this
	 * board's salted hashes — an owner never gets the real address.
	 */
	private function handleBoardAdmin(bool $isGlobalAdmin): void {
		$modPage = $_REQUEST['modPage'] ?? null;
		$modAction = $_REQUEST['modAction'] ?? null;
		$hideIPs = !$isGlobalAdmin;

		if ($modPage === 'manageFiles') {
			if ($this->handleFileModAction($modAction, $hideIPs)) {
				return;
			}

			$pageNumber = $_GET['pageNumber'] ?? 1;

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawManageFilesPage($pageNumber, $hideIPs, $this->addressFilterFromRequest($hideIPs));
			$this->uploaderHTML->drawFooter();
		}
		else if ($modPage === 'manageBans') {
			if ($this->handleBanModAction($modAction, $hideIPs)) {
				return;
			}

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawManageBansPage($this->banChecker, $hideIPs);
			$this->uploaderHTML->drawFooter();
		}
		else if ($modPage === 'actionLog') {
			$this->drawActionLogPage($hideIPs);
		}
		else if ($modPage === 'settings') {
			if ($modAction === 'saveSettings') {
				$this->requireCsrf();

				$boardController = new boardController($this->conf, $this->boardRepository, $this->uploaderHTML, $this->actionLog);
				$boardController->updateSettings($this->board, $_POST);

				redirect($this->conf['mainScript'] . '?request=admin&modPage=settings');
				return;
			}

			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawBoardSettingsPage();
			$this->uploaderHTML->drawFooter();
		}
		// Anything else, including the instance-wide mod pages, is not a board
		// page — fall back to the dashboard rather than dispatching it.
		else {
			$this->uploaderHTML->drawHeader();
			$this->uploaderHTML->drawBoardAdminDashboard($isGlobalAdmin);
			$this->uploaderHTML->drawFooter();
		}
	}

	/**
	 * Actions shared by the instance-wide and the per-board file manager. Their
	 * scope follows the collaborators, which are already board-aware.
	 *
	 * @return bool true when the action was handled and a response was sent
	 */
	private function handleFileModAction(?string $modAction, bool $hideIPs): bool {
		$manageFilesUrl = $this->conf['mainScript'] . '?request=admin&modPage=manageFiles';

		// every branch below mutates state
		if ($modAction !== null && $modAction !== '') {
			$this->requireCsrf();
		}

		if ($modAction === 'bulkDelete') {
			$fileIDs = $_POST['fileIDs'] ?? [];
			if (!is_array($fileIDs) || empty($fileIDs)) {
				redirect($manageFilesUrl);
				return true;
			}

			foreach ($fileIDs as $fileID) {
				if (!filter_var($fileID, FILTER_VALIDATE_INT)) {
					continue;
				}
				$uploadEntry = $this->uploadEntryRepository->getDataByID((int) $fileID);
				$this->makeUploadEntryController($uploadEntry)->adminDeletePost(false);
			}

			redirect($manageFilesUrl);
			return true;
		}

		if ($modAction === 'deleteFile') {
			$uploadEntry = $this->requireEntryFromRequest($this->languageManager->get('errors.failedToDelete'));

			$this->makeUploadEntryController($uploadEntry)->adminDeletePost(false);

			redirect($manageFilesUrl);
			return true;
		}

		// Bans the uploader behind an entry. Takes the file ID rather than the
		// address itself, so an IP never has to appear in a link a board owner sees.
		if ($modAction === 'banIP') {
			$uploadEntry = $this->requireEntryFromRequest($this->languageManager->get('errors.banError'));
			$ip = $uploadEntry->getIp();

			if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
				$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.banError'), $this->languageManager->get('errors.invalidIPAddress'));
			}

			$this->banChecker->addBan($ip);
			$this->actionLog->record(actionLogEntry::BAN_IP, $ip, $uploadEntry->getFileName($this->conf));
			$this->uploaderHTML->drawMessageAndRedirectHome($this->banMessage('messages.ipBanned', 'messages.posterBanned', $ip, $hideIPs));
			return true;
		}

		if ($modAction === 'banFile') {
			$uploadEntry = $this->requireEntryFromRequest($this->languageManager->get('errors.banError'));
			$ip = $uploadEntry->getIp();

			// Hash the file before deleting it
			$filePath = $uploadEntry->getFilePath($this->conf);
			if (file_exists($filePath)) {
				$fileHash = hash_file('sha256', $filePath);
				if ($fileHash !== false) {
					$this->banChecker->addBannedFileHash($fileHash);
					$this->actionLog->record(actionLogEntry::BAN_FILE, $fileHash, $uploadEntry->getFileName($this->conf));
				}
			}

			// Delete the file
			$this->makeUploadEntryController($uploadEntry)->adminDeletePost(false);

			// Also ban the uploader's IP
			if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
				$this->banChecker->addBan($ip);
				$this->actionLog->record(actionLogEntry::BAN_IP, $ip, $uploadEntry->getFileName($this->conf));
			}

			$this->uploaderHTML->drawMessageAndRedirectHome($this->banMessage('messages.fileDeletedAndBanned', 'messages.fileDeletedAndPosterBanned', $ip, $hideIPs));
			return true;
		}

		return false;
	}

	/**
	 * @return bool true when the action was handled and a response was sent
	 */
	private function handleBanModAction(?string $modAction, bool $hideIPs): bool {
		$manageBansUrl = $this->conf['mainScript'] . '?request=admin&modPage=manageBans';

		if ($modAction !== null && $modAction !== '') {
			$this->requireCsrf();
		}

		if ($modAction === 'addBan') {
			// board owners have no way to type an address they can't see
			if ($hideIPs) {
				$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.banError'), $this->languageManager->get('errors.notAuthorized'));
			}

			$banValue = trim($_POST['banValue'] ?? '');
			$banType = $_POST['banType'] ?? '';

			if ($banType === 'ip') {
				if (empty($banValue) || !filter_var($banValue, FILTER_VALIDATE_IP)) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.banError'), $this->languageManager->get('errors.invalidIPAddress'));
				}
				$this->banChecker->addBan($banValue);
				$this->actionLog->record(actionLogEntry::BAN_IP, $banValue);
			} elseif ($banType === 'hash') {
				if (empty($banValue) || !preg_match('/^[a-f0-9]{64}$/i', $banValue)) {
					$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.banError'), $this->languageManager->get('errors.invalidSHA256'));
				}
				$this->banChecker->addBannedFileHash($banValue);
				$this->actionLog->record(actionLogEntry::BAN_FILE, $banValue);
			} else {
				$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.banError'), $this->languageManager->get('errors.invalidBanType'));
			}

			redirect($manageBansUrl);
			return true;
		}

		if ($modAction === 'removeBans') {
			$entries = $_POST['entries'] ?? [];
			$banType = $_POST['banType'] ?? '';

			if (!is_array($entries) || empty($entries)) {
				redirect($manageBansUrl);
				return true;
			}

			if ($banType === 'ip') {
				// owners submit poster hashes, so map them back to the addresses they stand for
				$addresses = $hideIPs ? $this->resolveHashedIPs($entries) : $entries;

				$this->banChecker->removeBans($addresses);
				$this->recordEach(actionLogEntry::UNBAN_IP, $addresses);
			} elseif ($banType === 'hash') {
				$this->banChecker->removeBannedHashes($entries);
				$this->recordEach(actionLogEntry::UNBAN_FILE, $entries);
			}

			redirect($manageBansUrl);
			return true;
		}

		return false;
	}

	/**
	 * Actions on the instance-wide recent files page, which lists the main
	 * uploader and every board at once. Each file is named by a "<source>:<id>"
	 * reference, so a deletion goes through the board the file actually lives on
	 * while the bans land on the instance-wide lists every board inherits.
	 *
	 * @return bool true when the action was handled and a response was sent
	 */
	private function handleRecentFileModAction(?string $modAction): bool {
		if ($modAction === null || $modAction === '') {
			return false;
		}

		// every branch below mutates state
		$this->requireCsrf();

		$recentFilesUrl = $this->conf['mainScript'] . '?request=admin&modPage=recentFiles';
		$repository = $this->makeRecentFilesRepository();
		$recentFilesController = new recentFilesController($this->banChecker, $this->uploaderHTML, $this->languageManager, $this->actionLog, $this->conf);

		if ($modAction === 'bulkDelete') {
			foreach ((array) ($_POST['fileIDs'] ?? []) as $reference) {
				[$source, $fileID] = $this->parseFileReference($repository, (string) $reference);

				if ($source !== null) {
					$recentFilesController->deleteFile($source, $fileID);
				}
			}

			redirect($recentFilesUrl);
			return true;
		}

		[$source, $fileID] = $this->parseFileReference($repository, (string) ($_REQUEST['fileRef'] ?? ''));
		if ($source === null) {
			$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.failedToDelete'), $this->languageManager->get('errors.invalidFileID'));
		}

		if ($modAction === 'deleteFile') {
			$recentFilesController->deleteFile($source, $fileID);

			redirect($recentFilesUrl);
			return true;
		}

		if ($modAction === 'banIP') {
			$ip = $recentFilesController->banUploader($source, $fileID);

			// the ban itself is instance-wide, so it belongs in the instance log
			$this->actionLog->record(actionLogEntry::BAN_IP, $ip, $source->getLabel());

			$this->uploaderHTML->drawMessageAndRedirectHome($this->languageManager->get('messages.ipBanned', htmlspecialchars($ip)));
			return true;
		}

		if ($modAction === 'banFile') {
			$ip = $recentFilesController->banFile($source, $fileID);

			$this->actionLog->record(actionLogEntry::BAN_IP, $ip, $source->getLabel());

			$this->uploaderHTML->drawMessageAndRedirectHome($this->languageManager->get('messages.fileDeletedAndBanned', htmlspecialchars($ip)));
			return true;
		}

		return false;
	}

	/**
	 * Splits a "<source>:<id>" reference from the recent files page. The source
	 * is empty for the main uploader and a board URI otherwise; anything that
	 * doesn't name a real source comes back as null rather than a guess.
	 *
	 * @return array{0: ?fileSource, 1: int}
	 */
	private function parseFileReference(recentFilesRepository $repository, string $reference): array {
		$parts = explode(':', $reference, 2);

		if (count($parts) !== 2 || !filter_var($parts[1], FILTER_VALIDATE_INT)) {
			return [null, 0];
		}

		return [$repository->getSource($parts[0]), (int) $parts[1]];
	}

	private function makeRecentFilesRepository(): recentFilesRepository {
		return new recentFilesRepository($this->conf, $this->boardRepository);
	}

	/**
	 * Board management for the global admin.
	 *
	 * @return bool true when the action was handled and a response was sent
	 */
	private function handleBoardModAction(?string $modAction): bool {
		if ($modAction === null) {
			return false;
		}

		$this->requireCsrf();

		$targetBoard = $this->boardRepository->getByUri($_REQUEST['boardUri'] ?? '');
		if ($targetBoard === null) {
			$this->uploaderHTML->drawBoardErrorPageAndExit($this->languageManager->get('boards.manageError'), $this->languageManager->get('boards.notFound'), $this->conf['mainScript'] . '?request=admin&modPage=manageBoards');
		}

		$boardController = new boardController($this->conf, $this->boardRepository, $this->uploaderHTML, $this->actionLog);

		// what happens to a board belongs in that board's own history
		$boardActionLog = $this->actionLog->forBoard($targetBoard, $this->conf);

		switch ($modAction) {
			case 'toggleLock':
				$targetBoard->setLocked(!$targetBoard->isLocked());
				$this->boardRepository->update($targetBoard);
				$boardActionLog->record($targetBoard->isLocked() ? actionLogEntry::BOARD_LOCKED : actionLogEntry::BOARD_UNLOCKED, $targetBoard->getUri());
			break;

			case 'toggleListed':
				$targetBoard->setListed(!$targetBoard->isListed());
				$this->boardRepository->update($targetBoard);
				$boardActionLog->record($targetBoard->isListed() ? actionLogEntry::BOARD_LISTED : actionLogEntry::BOARD_UNLISTED, $targetBoard->getUri());
			break;

			case 'resetPassword':
				$boardController->resetOwnerPassword($targetBoard, (string) ($_POST['newPassword'] ?? ''));
			break;

			case 'deleteBoard':
				$boardController->deleteBoard($targetBoard);
			break;

			default:
				return false;
		}

		redirect($this->conf['mainScript'] . '?request=admin&modPage=manageBoards');
		return true;
	}

	/**
	 * Rejects a state-changing admin request that doesn't carry a valid CSRF
	 * token — the token only ever reaches pages the real admin/owner loaded.
	 */
	private function requireCsrf(): void {
		if ($this->sessionController === null
			|| !$this->sessionController->verifyCsrfToken($_REQUEST['csrfToken'] ?? null)) {
			$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('errors.notAuthorized'), $this->languageManager->get('errors.invalidRequest'));
		}
	}

	/**
	 * Loads the entry named by the request's fileID, or draws an error page.
	 */
	private function requireEntryFromRequest(string $errorHeading): uploadEntry {
		$fileID = $_REQUEST['fileID'] ?? '';
		if (!filter_var($fileID, FILTER_VALIDATE_INT)) {
			$this->uploaderHTML->drawErrorPageAndExit($errorHeading, $this->languageManager->get('errors.invalidFileID'));
		}

		return $this->uploadEntryRepository->getDataByID((int) $fileID);
	}

	private function makeUploadEntryController(uploadEntry $uploadEntry): uploadEntryController {
		return new uploadEntryController($uploadEntry, $this->uploadEntryRepository, $this->uploadedFileRepository, $this->uploaderHTML, $this->conf, $this->actionLog);
	}

	/**
	 * Records the same action once per value — the ban lists are edited in bulk.
	 */
	private function recordEach(string $action, array $targets): void {
		foreach ($targets as $target) {
			$this->actionLog->record($action, (string) $target);
		}
	}

	/**
	 * The action log page.
	 *
	 * On the instance it merges the log of every scope, boards included; on a
	 * board it only ever shows that board's own. $hideIPs is the same rule the
	 * file listings follow — an owner sees poster hashes, never an address, and
	 * that covers banned addresses appearing as the target of a ban.
	 */
	private function drawActionLogPage(bool $hideIPs): void {
		if (!$this->actionLog->isEnabled()) {
			$this->uploaderHTML->drawErrorPageAndExit($this->languageManager->get('actionLog.title'), $this->languageManager->get('actionLog.disabled'));
		}

		$isInstanceWide = $this->board === null;

		if ($isInstanceWide) {
			$rows = $this->actionLog->getInstanceEntries($this->makeRecentFilesRepository()->getSources(), $this->conf);
		} else {
			$rows = array_map(fn(actionLogEntry $entry) => ['entry' => $entry], $this->actionLog->getEntries());
		}

		// an unknown filter shows everything rather than nothing
		$actionFilter = (string) ($_GET['actionFilter'] ?? '');
		if (!in_array($actionFilter, actionLogEntry::ACTIONS, true)) {
			$actionFilter = '';
		}

		if ($actionFilter !== '') {
			$rows = array_values(array_filter($rows, fn(array $row) => $row['entry']->getAction() === $actionFilter));
		}

		$this->uploaderHTML->drawHeader();
		$this->uploaderHTML->drawActionLogPage($rows, max(1, (int) ($_GET['pageNumber'] ?? 1)), [
			'showSource' => $isInstanceWide,
			'hideIPs' => $hideIPs,
			'actionFilter' => $actionFilter,
			'ipFilter' => $this->addressFilterFromRequest($hideIPs),
		]);
		$this->uploaderHTML->drawFooter();
	}

	/**
	 * The address a mod listing is narrowed to, taken from a link in the
	 * listing itself.
	 *
	 * It is whatever the page displayed, so an owner can only ever pass one of
	 * their own board's poster hashes back — never an address, and never one
	 * they were not already shown. Anything else is no filter at all.
	 */
	private function addressFilterFromRequest(bool $hideIPs): string {
		$filter = (string) ($_GET['ipFilter'] ?? '');

		if ($hideIPs) {
			return preg_match('/^[0-9a-f]{12}$/', $filter) ? $filter : '';
		}

		return filter_var($filter, FILTER_VALIDATE_IP) ? $filter : '';
	}

	/**
	 * Whether the visitor actually asked the search for something.
	 *
	 * `sortDir` is how results are ordered rather than something to match, so a
	 * form carrying only that is still an empty form. A term counts when it is
	 * a non-empty string — the same test `searchRepository::normalizeParams()`
	 * applies, so the two can't disagree about whether a search happened.
	 */
	private function hasSearchCriteria(array $parameters): bool {
		foreach (['originalFileName', 'comment', 'fileExtension', 'mimeType'] as $field) {
			$value = $parameters[$field] ?? null;

			if (is_scalar($value) && (string) $value !== '') {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the file list is hidden from users. Mod pages are unaffected.
	 */
	private function isUnlisted(): bool {
		return !empty($this->conf['unlisted']);
	}

	/**
	 * Second pager under a user-facing listing. It is one of the visitor's own
	 * display settings, defaulting to $conf['defaultCookieValues'] like the
	 * rest — mod pages get one either way, this is only for the pages visitors
	 * see.
	 */
	private function drawBottomPagingBar(string $url, int $page): void {
		$settings = $this->cookieSettingsManager->getSettings();

		if (($settings['showBottomPager'] ?? '') === 'checked') {
			$this->uploaderHTML->drawPageingBar($url, $page);
		}
	}

	/**
	 * Pages that only exist to browse other people's files — send them back to
	 * the upload form when the listing is off.
	 */
	private function requireListing(): void {
		if ($this->isUnlisted()) {
			redirect($this->conf['mainScript'] . '?request=' . self::REQUEST_INDEX);
		}
	}

	/**
	 * Maps poster hashes submitted by a board owner back to the banned
	 * addresses they were derived from.
	 */
	private function resolveHashedIPs(array $hashes): array {
		if ($this->board === null) {
			return [];
		}

		$resolved = [];
		foreach ($this->banChecker->getBannedIPs() as $bannedIP) {
			if (in_array($this->board->hashIp($bannedIP), $hashes, true)) {
				$resolved[] = $bannedIP;
			}
		}

		return $resolved;
	}

	/**
	 * Picks the address- or poster-worded confirmation depending on who is
	 * looking, and fills in whichever identifier they are allowed to see.
	 */
	private function banMessage(string $ipKey, string $posterKey, string $ip, bool $hideIPs): string {
		$asPoster = $hideIPs && $this->board !== null;
		$identifier = $asPoster ? $this->board->hashIp($ip) : $ip;

		return $this->languageManager->get($asPoster ? $posterKey : $ipKey, htmlspecialchars($identifier));
	}

	/**
	 * Board listing and creation only exist outside of a board — send those
	 * requests up to the instance script instead.
	 */
	private function requireGlobalContext(string $pageRequest): void {
		if ($this->board !== null) {
			redirect($this->conf['rootScript'] . '?request=' . $pageRequest);
		}
	}

	/**
	 * Handles chunk upload requests in a JSON-safe context.
	 * Suppresses HTML output and catches exceptions as JSON errors.
	 */
	private function handleChunkRequest(string $pageRequest): void {
		// Suppress PHP error output so it doesn't corrupt the JSON response
		ini_set('display_errors', '0');

		// A locked board takes no new uploads
		if ($this->board !== null && $this->board->isLocked()) {
			header('Content-Type: application/json');
			http_response_code(403);
			echo json_encode(['error' => $this->languageManager->get('boards.boardLocked')]);
			return;
		}

		// Check ban before allowing chunk uploads
		if ($this->banChecker->isBanned(getUserIP())) {
			header('Content-Type: application/json');
			http_response_code(403);
			echo json_encode(['error' => $this->languageManager->get('errors.bannedFromUploading')]);
			return;
		}

		// Check flood control before allowing chunk uploads
		if ($this->floodControls->isFlooding(getUserIP())) {
			header('Content-Type: application/json');
			http_response_code(429);
			echo json_encode(['error' => $this->languageManager->get('errors.mustWaitBeforePosting')]);
			return;
		}

		try {
			$chunkService = new chunkUploadService($this->conf, $this->uploadedFileRepository, $this->uploadEntryRepository, $this->logFile, $this->banChecker, $this->languageManager, $this->uploaderHTML, $this->temporaryHosting, $this->actionLog);

			if ($pageRequest === self::REQUEST_UPLOAD_CHUNK) {
				$chunkService->handleChunk();
			} else {
				$chunkService->finalizeUpload();
			}
		} catch (\Exception $e) {
			header('Content-Type: application/json');
			http_response_code(500);
			echo json_encode(['error' => $e->getMessage()]);
		}
	}

	private function writeConfig(string $configFile, array $conf): void {
		$output = "<?php\n";
		$output .= "/* MAIN CONFIGURATION FILE FOR WAROTA.PHP*/\n\n";
		$output .= "//Paths\n";
		$output .= "return \$conf = [\n";

		foreach ($conf as $key => $value) {
			$output .= "    " . var_export($key, true) . " => ";

			if (is_array($value)) {
				$output .= $this->exportArray($value);
			} elseif (is_bool($value)) {
				$output .= $value ? 'true' : 'false';
			} elseif (is_int($value)) {
				$output .= $value;
			} else {
				$output .= var_export($value, true);
			}

			$output .= ",\n";
		}

		$output .= "];\n";

		file_put_contents($configFile, $output, LOCK_EX);

		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($configFile, true);
		}
	}

	private function exportArray(array $arr): string {
		$items = [];
		foreach ($arr as $key => $value) {
			if (is_int($key)) {
				$items[] = var_export($value, true);
			} else {
				$items[] = var_export($key, true) . ' => ' . var_export($value, true);
			}
		}
		return "[\n        " . implode(",\n        ", $items) . "\n    ]";
	}
}
