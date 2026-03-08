<?php
namespace HeyuriUploader\Classes;

use HeyuriUploader\Controllers\sessionController;
use HeyuriUploader\Controllers\uploadEntryController;
use HeyuriUploader\Classes\uploadEntryRepository;
use HeyuriUploader\Controllers\uploadedFileService;
use HeyuriUploader\Controllers\chunkUploadService;

use function HeyuriUploader\Functions\getUserIP;
use function HeyuriUploader\Functions\redirect;

class requestHandler {
	private $conf, $uploadEntryRepository, $uploaderHTML, $banChecker, $floodControls, $logFile, $cookieSettingsManager, $uploadedFileRepository, $searchRepository, $lang;

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

	public function __construct(array $config) {
		$this->conf = $config;
		$this->uploadEntryRepository = new uploadEntryRepository(\DATA_DIR . $this->conf['logFile']);
		$this->uploaderHTML = new uploaderHTML($config);
		$this->lang = $this->uploaderHTML->getLang();
		$this->banChecker = new banChecker();
		$this->floodControls = new floodControls($config['coolDownTime'], $this->uploadEntryRepository);
		$this->logFile = new logFile($config);
		$this->cookieSettingsManager = new cookieSettingsManager($config['defaultCookieValues']);
		$this->uploadedFileRepository = new uploadedFileRepository($config, $this->uploaderHTML, $this->banChecker, $this->floodControls, $this->logFile, $this->uploadEntryRepository);
		$this->searchRepository = new searchRepository($this->logFile);
	}


	public function handleRequest(): void {
		$pageRequest = $_REQUEST['request'] ?? self::REQUEST_INDEX;

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
					$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.failedToDelete'), $this->lang->get('errors.invalidFileID'));
				}
				
				// Retrieve post data
				$uploadEntry = $this->uploadEntryRepository->getDataByID((int) $fileID);

				$uploadEntryController = new uploadEntryController($uploadEntry, $this->uploadEntryRepository, $this->uploadedFileRepository, $this->uploaderHTML, $this->conf['thumbDir'], $this->conf['prefix'], $this->conf['adminPassword'], $this->conf['thumbnailExtension']);
				$uploadEntryController->userDeletePost();
			break;

			case self::REQUEST_SETTINGS_FORM:
				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawSettingsForm();
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_SEARCH:
				// url of the search page
				$url = $this->conf['mainScript'];
				$page = max(1, (int)($_GET['pageNumber'] ?? 1));

				$searchParameters = [
					'originalFileName' => $_GET['originalFileName'] ?? null,
					'comment' => $_GET['comment'] ?? null,
					'fileExtension' => $_GET['fileExtension'] ?? null,
					'mimeType' => $_GET['mimeType'] ?? null,
					'sortDir' => $_GET['sortDir'] ?? 'desc'
				];

				// get the search results from the log file
				$searchResults = $this->searchRepository->getSearchResults($searchParameters);

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawSearchForm($url, $searchParameters);
				$this->uploaderHTML->drawSearchResults($searchResults, $page, $searchParameters);
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_DELETE_FORM:
				$fileID = $_GET['deleteFileID'] ?? '';
				if (!$fileID) $this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.noFileIDSelected'));

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawDeletionForm(htmlspecialchars($fileID, ENT_QUOTES, 'UTF-8'));
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_INDEX:
				$pageNumber = $_GET['pageNumber'] ?? 1;

				$url = htmlspecialchars($this->conf['mainScript']) . '?request=' . self::REQUEST_INDEX;
				$uploadUrl = htmlspecialchars($this->conf['mainScript']) . '?request=' . self::REQUEST_UPLOAD;

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawUploadForm($uploadUrl);
				$this->uploaderHTML->drawPageingBar($url, $pageNumber);
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawFileListing($pageNumber);
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_CATALOG:
				$pageNumber = $_GET['pageNumber'] ?? 1;

				$url = htmlspecialchars($this->conf['mainScript']) . '?request=catalog';
				$uploadUrl = htmlspecialchars($this->conf['mainScript']) . '?request=' . self::REQUEST_UPLOAD;

				$this->uploaderHTML->drawHeader();
				$this->uploaderHTML->drawUploadForm($uploadUrl);
				$this->uploaderHTML->drawPageingBar($url, $pageNumber);
				$this->uploaderHTML->drawActionLinks();
				$this->uploaderHTML->drawCatalog($pageNumber);
				$this->uploaderHTML->drawFooter();
			break;

			case self::REQUEST_UPLOAD:
				if ($this->banChecker->isBanned(getUserIP())) {
					$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.bannedFromUploading'));
				}

				$uploadedFileService = new uploadedFileService($this->uploadedFileRepository, $this->uploadEntryRepository, $this->logFile, $this->uploaderHTML, $this->conf['allowedExtensions'], $this->conf['extensionsToBeConvertedToText'], $this->conf['prefix'], $this->conf['maxAmountOfFiles'], $this->conf['deleteOldestOnMaxFiles'], $this->banChecker);
				$uploadedFileService->processFiles();

				// redirect to index or catalog
				if($_POST['requestFrom'] === 'catalog') {
					redirect($this->conf['mainScript'] . '?request=catalog');
				} else {
					redirect($this->conf['mainScript'] . '?request=' . self::REQUEST_INDEX);
				}
			break;



			case self::REQUEST_LOGIN:
				$loginHandler = new loginHandler($this->conf['mainScript'], $this->conf['adminPassword'], $this->uploaderHTML);

				$loginHandler->invoke();
			break;

			case self::REQUEST_LOGOUT:
				$session = new session;
				$session->destroy();
				redirect($this->conf['mainScript']);
			break;

			case self::REQUEST_ADMIN:
				$session = new session;
                $sessionController = new sessionController($session);

				$isLoggedIn = $sessionController->isLoggedIn();

				if(!$isLoggedIn) {
					$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.notAuthorized'), $this->lang->get('errors.mustBeLoggedIn'));
				}

				// get mod page paramter
				$modPage = $_REQUEST['modPage'] ?? null;

				// handle mod pages
				if($modPage === 'manageFiles') {
					$modAction = $_REQUEST['modAction'] ?? null;

					if ($modAction === 'bulkDelete') {
						$fileIDs = $_POST['fileIDs'] ?? [];
						if (!is_array($fileIDs) || empty($fileIDs)) {
							redirect($this->conf['mainScript'] . '?request=admin&modPage=manageFiles');
							return;
						}

						foreach ($fileIDs as $fileID) {
							if (!filter_var($fileID, FILTER_VALIDATE_INT)) {
								continue;
							}
							$uploadEntry = $this->uploadEntryRepository->getDataByID((int) $fileID);
							$uploadEntryController = new uploadEntryController($uploadEntry, $this->uploadEntryRepository, $this->uploadedFileRepository, $this->uploaderHTML, $this->conf['thumbDir'], $this->conf['prefix'], $this->conf['adminPassword'], $this->conf['thumbnailExtension']);
							$uploadEntryController->adminDeletePost(false);
						}

						redirect($this->conf['mainScript'] . '?request=admin&modPage=manageFiles');
						return;
					}

					if ($modAction === 'deleteFile') {
						$fileID = $_REQUEST['fileID'] ?? '';
						if (!filter_var($fileID, FILTER_VALIDATE_INT)) {
							$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.failedToDelete'), $this->lang->get('errors.invalidFileID'));
						}

						$uploadEntry = $this->uploadEntryRepository->getDataByID((int) $fileID);
						$uploadEntryController = new uploadEntryController($uploadEntry, $this->uploadEntryRepository, $this->uploadedFileRepository, $this->uploaderHTML, $this->conf['thumbDir'], $this->conf['prefix'], $this->conf['adminPassword'], $this->conf['thumbnailExtension']);
						$uploadEntryController->adminDeletePost(false);
						redirect($this->conf['mainScript'] . '?request=admin&modPage=manageFiles');
						return;
					}

					if ($modAction === 'banIP') {
						$targetIP = $_REQUEST['targetIP'] ?? '';
						if (empty($targetIP) || !filter_var($targetIP, FILTER_VALIDATE_IP)) {
							$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.banError'), $this->lang->get('errors.invalidIPAddress'));
						}
						$this->banChecker->addBan($targetIP);
						$this->uploaderHTML->drawMessageAndRedirectHome($this->lang->get('messages.ipBanned', htmlspecialchars($targetIP)));
						return;
					}

					if ($modAction === 'banFile') {
						$fileID = $_REQUEST['fileID'] ?? '';
						if (!filter_var($fileID, FILTER_VALIDATE_INT)) {
							$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.banError'), $this->lang->get('errors.invalidFileID'));
						}

						$uploadEntry = $this->uploadEntryRepository->getDataByID((int) $fileID);
						$ip = $uploadEntry->getIp();

						// Hash the file before deleting it
						$filePath = $uploadEntry->getFilePath($this->conf);
						if (file_exists($filePath)) {
							$fileHash = hash_file('sha256', $filePath);
							if ($fileHash !== false) {
								$this->banChecker->addBannedFileHash($fileHash);
							}
						}

						// Delete the file
						$uploadEntryController = new uploadEntryController($uploadEntry, $this->uploadEntryRepository, $this->uploadedFileRepository, $this->uploaderHTML, $this->conf['thumbDir'], $this->conf['prefix'], $this->conf['adminPassword'], $this->conf['thumbnailExtension']);
						$uploadEntryController->adminDeletePost(false);

						// Also ban the uploader's IP
						if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
							$this->banChecker->addBan($ip);
						}

						$this->uploaderHTML->drawMessageAndRedirectHome($this->lang->get('messages.fileDeletedAndBanned', htmlspecialchars($ip)));
						return;
					}

					$pageNumber = $_GET['pageNumber'] ?? 1;

					$this->uploaderHTML->drawHeader();
					$this->uploaderHTML->drawManageFilesPage($pageNumber);
					$this->uploaderHTML->drawFooter();
				}
				else if($modPage === 'manageBans') {
					$modAction = $_REQUEST['modAction'] ?? null;

					if ($modAction === 'addBan') {
						$banValue = trim($_POST['banValue'] ?? '');
						$banType = $_POST['banType'] ?? '';

						if ($banType === 'ip') {
							if (empty($banValue) || !filter_var($banValue, FILTER_VALIDATE_IP)) {
								$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.banError'), $this->lang->get('errors.invalidIPAddress'));
							}
							$this->banChecker->addBan($banValue);
						} elseif ($banType === 'hash') {
							if (empty($banValue) || !preg_match('/^[a-f0-9]{64}$/i', $banValue)) {
								$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.banError'), $this->lang->get('errors.invalidSHA256'));
							}
							$this->banChecker->addBannedFileHash($banValue);
						} else {
							$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.banError'), $this->lang->get('errors.invalidBanType'));
						}

						redirect($this->conf['mainScript'] . '?request=admin&modPage=manageBans');
						return;
					}

					if ($modAction === 'removeBans') {
						$entries = $_POST['entries'] ?? [];
						$banType = $_POST['banType'] ?? '';

						if (!is_array($entries) || empty($entries)) {
							redirect($this->conf['mainScript'] . '?request=admin&modPage=manageBans');
							return;
						}

						if ($banType === 'ip') {
							$this->banChecker->removeBans($entries);
						} elseif ($banType === 'hash') {
							$this->banChecker->removeBannedHashes($entries);
						}

						redirect($this->conf['mainScript'] . '?request=admin&modPage=manageBans');
						return;
					}

					$this->uploaderHTML->drawHeader();
					$this->uploaderHTML->drawManageBansPage();
					$this->uploaderHTML->drawFooter();
				}
				else if($modPage === 'config') {
					$modAction = $_REQUEST['modAction'] ?? null;

					if ($modAction === 'saveConfig') {
						$newValues = $_POST['conf'] ?? [];
						if (!is_array($newValues)) {
							$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.configError'), $this->lang->get('errors.invalidFormData'));
						}

						$configFile = 'config.php';
						$conf = require $configFile;

						foreach ($newValues as $key => $value) {
							if (!array_key_exists($key, $conf) || is_array($conf[$key])) {
								continue;
							}

							if (is_bool($conf[$key])) {
								$conf[$key] = ($value === '1');
							} elseif (is_int($conf[$key])) {
								$conf[$key] = (int) $value;
							} else {
								$conf[$key] = (string) $value;
							}
						}

						$this->writeConfig($configFile, $conf);
						$this->conf = $conf;

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
			break;

			default:
				$this->uploaderHTML->drawErrorPageAndExit($this->lang->get('errors.pageNotFound'), $this->lang->get('errors.contactAdmin'));
			break;
		}
	}

	/**
	 * Handles chunk upload requests in a JSON-safe context.
	 * Suppresses HTML output and catches exceptions as JSON errors.
	 */
	private function handleChunkRequest(string $pageRequest): void {
		// Suppress PHP error output so it doesn't corrupt the JSON response
		ini_set('display_errors', '0');

		// Check ban before allowing chunk uploads
		if ($this->banChecker->isBanned(getUserIP())) {
			header('Content-Type: application/json');
			http_response_code(403);
			echo json_encode(['error' => 'You have been banned.']);
			return;
		}

		try {
			$chunkService = new chunkUploadService($this->conf, $this->uploadedFileRepository, $this->uploadEntryRepository, $this->logFile, $this->uploaderHTML, $this->banChecker);

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
