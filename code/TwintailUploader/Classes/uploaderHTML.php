<?php
namespace TwintailUploader\Classes;

use DateTime;

use function TwintailUploader\Functions\bytesToHumanReadable;


class uploaderHTML {
	private $conf;
	private $renderer;
	private $cookieSettingsManager;
	private languageManager $lang;

	public function __construct(array $conf, languageManager $languageManager) {
		$this->conf = $conf;
		$this->lang = $languageManager;
		$this->renderer = new HTMLRenderer(__DIR__ . '/../templates', $this->lang);
		$this->cookieSettingsManager = new cookieSettingsManager($conf['defaultCookieValues']);
	}

	public function getLang(): languageManager {
		return $this->lang;
	}

	public function drawHeader(): void {
		$themesDir = $this->conf['staticPath'] . 'css/themes';
		$themesUrl = $this->conf['staticUrl'] . 'css/themes';
		$themeManager = new themeManager($themesDir, $themesUrl);

		$themeLink = $themeManager->generateThemeLink($this->conf['defaultTheme']);
		$availableThemes = implode(',', $themeManager->getThemeNames());

		$preloadLinks = $themeManager->generatePreloadLinks();

		$html = $this->renderer->render('header', [
			'defaultTheme' => $this->conf['defaultTheme'],
			'themeLink' => $themeLink,
			'preloadLinks' => $preloadLinks,
			'availableThemes' => $availableThemes,
			'staticUrl' => $this->conf['staticUrl'],
			'boardTitle' => $this->conf['boardTitle'],
			'boardSubTitle' => $this->conf['boardSubTitle'],
		]);

		echo $html;
	}
	
	
	public function drawPageingBar(string $url, int $page = 1): void {
		$logFile = new logFile($this->conf);
		$fileCount = $logFile->getTotalLogLines();
		$pageLinks = $this->buildPagingLinks($url, $page, $fileCount, $this->conf['filesPerListing']);

		$html = $this->renderer->render('paging-bar', [
			'homeUrl' => $this->conf['home'],
			'pageLinks' => $pageLinks,
		]);

		echo $html;
	}

	/**
	 * Builds HTML for paging links.
	 */
	private function buildPagingLinks(string $url, int $currentPage, int $fileCount, int $filesPerListing): string {
		$pages = (int)ceil($fileCount / $filesPerListing);
		$pageLinks = '';

		// Add [ALL] link if allowed
		if (!empty($this->conf['allowDisplayingAllEntries']) && $this->conf['allowDisplayingAllEntries']) {
			if ($currentPage === -1) {
				$pageLinks .= ' [<b>ALL</b>]';
			} else {
				$pageLinks .= ' [<a href="' . $url . '&pageNumber=-1">ALL</a>]';
			}
		}

		for ($i = 1; $i <= $pages; $i++) {
			if ($i == $currentPage) {
				$pageLinks .= '[<b>' . $i . '</b>]';
			} else {
				$pageLinks .= '[<a href="' . $url . '&pageNumber=' . htmlspecialchars($i) . '">' . htmlspecialchars($i) . '</a>]';
			}
		}

		return $pageLinks;
	}

	public function drawFileListing(int $page = 1): void {
		$logFile = new LogFile($this->conf);
		$count = $this->conf['filesPerListing'];

		if ($page === -1 && $this->conf['allowDisplayingAllEntries']) {
			$count = $logFile->getTotalLogLines();
			$lineOffset = 0;
		} else {
			$page--;
			$lineOffset = $count * $page;
		}

		$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], 'r');
		if (!$fileHandle) {
			echo $this->lang->get('errors.unableOpenLog');
			return;
		}

		$this->skipLines($fileHandle, $lineOffset);

		$cookie = $this->cookieSettingsManager->getSplitCookie();
        
		// Build table header
		$tableHeader = $this->buildTableHeader($cookie);
        
		// Build table rows
		$entries = $this->processFileLines($fileHandle, $count, false);
		$tableRows = $this->buildTableRows($entries, $cookie);

		fclose($fileHandle);

		// Build usage info
		$usageInfo = $this->buildUsageInfo($logFile);

		// Render template
		$html = $this->renderer->render('file-listing', [
			'tableHeader' => $tableHeader,
			'tableRows' => $tableRows,
			'usageInfo' => $usageInfo,
		]);

		echo $html;
	}

	/**
	 * Skips a given number of lines in the file.
	 */
	private function skipLines($fileHandle, int $linesToSkip): void {
		$currentLine = 0;
		while ($currentLine < $linesToSkip && !feof($fileHandle)) {
			fgets($fileHandle);
			$currentLine++;
		}
	}
	
	/**
	 * Reads and processes a specific number of lines from the file.
	 */
	private function processFileLines($fileHandle, int $lineCount, bool $isCatalog = false) {
		$currentLine = 0;
		$entries = [];

		while ($currentLine < $lineCount && !feof($fileHandle)) {
			$line = fgets($fileHandle);
			if ($line === false || trim($line) === '') {
				continue;
			}

			$data = new uploadEntry(explode("<>", $line));
			$entries[] = $data;

			$currentLine++;
		}

		// for catalog views, return arrays-of-arrays (batches) instead of single entries
		if ($isCatalog) {
			$batchSize = (isset($this->conf['catalogColumns']) && is_int($this->conf['catalogColumns']) && $this->conf['catalogColumns'] > 0)
				? $this->conf['catalogColumns']
				: 4;

			$batched = [];
			$row = [];
			foreach ($entries as $data) {
				$row[] = $data;
				if (count($row) >= $batchSize) {
					$batched[] = $row;
					$row = [];
				}
			}
			if (!empty($row)) {
				$batched[] = $row;
			}

			return $batched;
		}

		// default: return a flat array of entries
		return $entries;
	}

	/**
	 * Renders the table header based on cookie settings.
	 */
	private function buildTableHeader(array $cookie, bool $isAdmin = false): string {
		$deleteButtonHeader = $cookie['showDeleteButton'] ? '<th class="deleteColumn">' . $this->lang->get('table.delete') . '</th>' : '';
		$commentHeader = $cookie['showComment'] ? '<th class="commentColumn">' . $this->lang->get('table.comment') . '</th>' : '';
		$fileNameHeader = $cookie['showFileName'] ? '<th class="fileNameColumn">' . $this->lang->get('table.fileName') . '</th>' : '';
		$fileSizeHeader = $cookie['showFileSize'] ? '<th class="fileSizeColumn">' . $this->lang->get('table.size') . '</th>' : '';
		$mimeTypeHeader = $cookie['showMimeType'] ? '<th class="mimeTypeColumn">' . $this->lang->get('table.mime') . '</th>' : '';
		$dateHeader = $cookie['showDate'] ? '<th class="dateColumn">' . $this->lang->get('table.date') . '</th>' : '';

		$adminHeaders = '';
		if ($isAdmin) {
			$adminHeaders = '<th class="ipColumn">' . $this->lang->get('table.ip') . '</th><th class="adminActionsColumn">' . $this->lang->get('table.actions') . '</th><th class="selectColumn">' . $this->lang->get('table.select') . '</th>';
		}

		return $this->renderer->render('table-header', [
			'deleteButtonHeader' => $deleteButtonHeader,
			'commentHeader' => $commentHeader,
			'fileNameHeader' => $fileNameHeader,
			'fileSizeHeader' => $fileSizeHeader,
			'mimeTypeHeader' => $mimeTypeHeader,
			'dateHeader' => $dateHeader,
			'adminHeaders' => $adminHeaders,
		]);
	}

	/**
	 * Builds all table rows from entries
	 */
	private function buildTableRows(array $entries, array $cookie, bool $isAdmin = false): string {
		$rows = '';
		foreach ($entries as $data) {
			$rows .= $this->buildTableRow($data, $cookie, $isAdmin);
		}
		return $rows;
	}

	/**
	 * Builds a single row in the file listing table.
	 */
	private function buildTableRow(uploadEntry $data, array $cookie, bool $isAdmin = false): string {
		// Get display name, file path, and resolve the appropriate thumbnail
		$fileName = $data->getFileName($this->conf);
		$path = $data->getFilePath($this->conf);
		$thumbPath = $this->getThumbnailPath($data, $path);

		// Fetch thumbnail dimensions so the img element can be sized correctly
		$width = '';
		$height = '';
		if (file_exists($thumbPath)) {
			$imageSize = getimagesize($thumbPath);
			if ($imageSize) {
				$width = $imageSize[0];
				$height = $imageSize[1];
			}
		}

		// Delete button cell — links to the deletion confirmation form
		$deleteButton = '';
		if ($cookie['showDeleteButton']) {
			$deleteButton = '<td><a href="' . htmlspecialchars($this->conf['mainScript']) . '?request=deleteFileForm&deleteFileID=' . htmlspecialchars($data->getId()) . '">■</a></td>';
		}

		// Name cell — shows either a thumbnail preview with the file name, or just the file name as a link
		$nameCell = '';
		if ($cookie['showPreviewImage']) {
			$nameCell = '<td class="previewContainer"><a href="' . $path . '"> <img class="imagePreview" loading="lazy" src="' . $thumbPath . '" width="' . $width . '" height="' . $height . '" alt="' . htmlspecialchars($fileName) . '"><br>' . htmlspecialchars($fileName) . '</a></td>';
		} else {
			$nameCell = '<td><a href="' . $path . '">' . htmlspecialchars($fileName) . '</a></td>';
		}

		// Optional metadata cells — each is toggled by the user's cookie settings
		$commentCell = $cookie['showComment'] ? '<td><span class="comment">' . $this->renderComment($data->getComment()) . '</span></td>' : '';
		$fileNameCell = $cookie['showFileName'] ? '<td><span class="fileName">' . htmlspecialchars($data->getOriginalFileName()) . '</span></td>' : '';
		$fileSizeCell = $cookie['showFileSize'] ? '<td><span class="fileSize">' . bytesToHumanReadable($data->getSize()) . '</span></td>' : '';
		$mimeTypeCell = $cookie['showMimeType'] ? '<td><span class="grayText">' . htmlspecialchars($data->getMimeType()) . '</span></td>' : '';

		// Timestamp for date cell
		$timestamp = $data->getTime();

		// Date cell with formatted timestamp, also toggled by cookie settings
		$dateCell = $cookie['showDate'] ? '<td><span class="grayText">' . ($timestamp ? htmlspecialchars(date('Y-m-d H:i:s', (int)$timestamp)) : '') . '</span></td>' : '';

		// Admin-only cells: IP address and action links
		$adminCells = '';
		if ($isAdmin) {
			$self = htmlspecialchars($this->conf['mainScript']);
			$ip = htmlspecialchars($data->getIp());
			$fileId = htmlspecialchars($data->getId());

			$adminCells .= '<td class="ipCell">' . $ip . '</td>';
			$adminCells .= '<td class="adminActionsCell">';
			$adminCells .= '[<a href="' . $self . '?request=admin&modPage=manageFiles&modAction=deleteFile&fileID=' . $fileId . '">' . $this->lang->get('admin.deleteAction') . '</a>] ';
			$adminCells .= '[<a href="' . $self . '?request=admin&modPage=manageFiles&modAction=banIP&targetIP=' . urlencode($ip) . '">' . $this->lang->get('admin.banUser') . '</a>] ';
			$adminCells .= '[<a href="' . $self . '?request=admin&modPage=manageFiles&modAction=banFile&fileID=' . $fileId . '">' . $this->lang->get('admin.banFile') . '</a>]';
			$adminCells .= '<td><input type="checkbox" name="fileIDs[]" value="' . $fileId . '"></td>';
			$adminCells .= '</td>';
		}

		// Render the assembled cells into the table-row template
		return $this->renderer->render('table-row', [
			'deleteButton' => $deleteButton,
			'nameCell' => $nameCell,
			'commentCell' => $commentCell,
			'fileNameCell' => $fileNameCell,
			'fileSizeCell' => $fileSizeCell,
			'mimeTypeCell' => $mimeTypeCell,
			'dateCell' => $dateCell,
			'adminCells' => $adminCells,
		]);
	}
	
	/**
	 * Determines the correct thumbnail path for a file.
	 */
	private function renderComment(string $comment): string {
		// Escape HTML special characters to prevent XSS, then apply custom markup parsing for file conversion notices
		$escaped = htmlspecialchars($comment);

		// Custom markup parsing: [ext]newExt←oldExt[/ext] will be rendered as a notice about the file being converted to a different extension
		$escaped = preg_replace(
			'/\[ext\](.*?)\[\/ext\]/',
			'<span class="redText">($1)</span>',
			$escaped
		);

		// You can add more custom markup parsing here if needed
		return $escaped;
	}

	private function getThumbnailPath(uploadEntry $data, string $defaultPath): string {
		$mimeType = $data->getMimeType();

		if (preg_match('/audio/i', $mimeType)) {
			return $this->conf['staticUrl'] . 'images/audio.png';
		}
		// Flash file (SWF)
		if (preg_match('/x-shockwave-flash|flash|swf/i', $mimeType) || preg_match('/\.swf$/i', $data->getOriginalFileName())) {
			return $this->conf['staticUrl'] . 'images/swf_thumb.png';
		}
		if (preg_match('/video/i', $mimeType)) {
			$videoThumbPath = $data->getVideoThumbPath($this->conf);
			return file_exists($videoThumbPath) ? $videoThumbPath : $this->conf['staticUrl'] . 'images/video_overlay.png';
		}
		if (preg_match('/application/i', $mimeType)) {
			return $this->conf['staticUrl'] . 'images/archive.png';
		}

		// Non-image types that weren't caught above: use archive icon as fallback
		if (!preg_match('/image/i', $mimeType)) {
			return $this->conf['staticUrl'] . 'images/archive.png';
		}

		// For image types: check if file exists at all
		if (!file_exists($defaultPath)) {
			return $this->conf['staticUrl'] . 'images/nofile.gif';
		}

		// File exists but thumbnail wasn't generated
		$thumbPath = $data->getThumbPath($this->conf);
		if (!file_exists($thumbPath)) {
			return $this->conf['staticUrl'] . 'images/nothumb.gif';
		}

		return $thumbPath;
	}
	
	/**
	 * Builds the total usage and file count information.
	 */
	private function buildUsageInfo(logFile $logFile): string {
		$used = $this->lang->get('usage.used');
		$files = $this->lang->get('usage.files');
		$usage1 = $used . ' ' . bytesToHumanReadable($logFile->getTotalUsageInBytes()) . ' / ' . bytesToHumanReadable($this->conf['maxTotalSize'] * 1024 * 1024) . '<br>';
		$usage2 = $used . ' ' . $logFile->getTotalLogLines() . ' ' . $files . ' / ' . $this->conf['maxAmountOfFiles'] . ' ' . $files . '<br>';
		return $usage1 . $usage2;
	}
	
	public function drawCatalog(int $page = 1): void {
		$logFile = new LogFile($this->conf);
		$count = $this->conf['filesPerListing'];

		if ($page === -1 && $this->conf['allowDisplayingAllEntries']) {
			$count = $logFile->getTotalLogLines();
			$lineOffset = 0;
		} else if ($page === 0) {
			$count = $logFile->getTotalLogLines();
			$page = 0;
			$lineOffset = 0;
		} else {
			$page--;
			$lineOffset = $count * $page;
		}

		$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], 'r');
		if (!$fileHandle) {
			echo $this->lang->get('errors.unableOpenLog');
			return;
		}

		$this->skipLines($fileHandle, $lineOffset);

		// get an array of data's at a time (batched rows)
		$batchedData = $this->processFileLines($fileHandle, $count, true);

		fclose($fileHandle);

		// get cookie settings for which columns to show
		$cookie = $this->cookieSettingsManager->getSplitCookie();

		// Build catalog rows
		$catalogRows = $this->buildCatalogRows($batchedData, $cookie);

		// Render template
		$html = $this->renderer->render('catalog', [
			'catalogRows' => $catalogRows,
		]);

		echo $html;
	}

	/**
	 * Builds all catalog rows from batched data
	 */
	private function buildCatalogRows(array $batchedData, array $cookie): string {
		$result = '';
		foreach ($batchedData as $dataArray) {
			$result .= $this->buildCatalogRow($dataArray, $cookie);
		}
		return $result;
	}

	/**
	 * Builds a catalog row with multiple columns
	 */
	private function buildCatalogRow(array $dataArray, array $cookie): string {
		$columns = '';
		foreach ($dataArray as $data) {
			// File meta data
			$fileSize = $cookie['showFileSize'] ? bytesToHumanReadable($data->getSize()) : '';

			// File paths
			$path = $data->getFilePath($this->conf);
			$thumbPath = $this->getThumbnailPath($data, $path);

			// unix timestamp
			$timestamp = $data->getTime();

			// date
			$date = new DateTime();
			$date->setTimestamp($timestamp);

			// formatted time
			$formattedDate = $cookie['showDate'] ? $date->format('Y-m-d H:i:s') : '';

			// comment
			$comment = $cookie['showComment'] ? $data->getComment() : '';

			// url to use for src
			$thumbUrl = $thumbPath ?: $path;

			// Fetch image dimensions
			$width = '';
			$height = '';
			if (file_exists($thumbPath)) {
				$imageSize = getimagesize($thumbPath);
				if ($imageSize) {
					$width = $imageSize[0];
					$height = $imageSize[1];
				}
			}

			// original file name
			$fileName = $data->getFileName($this->conf);

			// render the column using the catalog-column template
			$columns .= $this->renderer->render('catalog-column', [
				'thumbUrl' => htmlspecialchars($thumbUrl),
				'fileUrl' => htmlspecialchars($path),
				'width' => $width,
				'height' => $height,
				'fileName' => $fileName,
				'formattedDate' => htmlspecialchars($formattedDate),
				'fileSize' => htmlspecialchars($fileSize),
				'comment' => $this->renderComment($comment),
			]);
		}

		return $this->renderer->render('catalog-row', [
			'columns' => $columns,
		]);
	}

	public function drawFooter(): void {
		$html = $this->renderer->render('footer');
		echo $html;
	}

	public function drawErrorPageAndExit(string $mes1, string $mes2 = ""): void {
		$this->drawHeader();
		
		$html = $this->renderer->render('error-page', [
			'message1' => $mes1,
			'message2' => $mes2,
			'returnUrl' => htmlspecialchars($this->conf['mainScript']),
		]);
		
		echo $html;
		$this->drawFooter();
		exit;
	}

	public function drawMessageAndRedirectHome(string $mes1, string $mes2 = ""): void {
		$this->drawHeader();
		
		$html = $this->renderer->render('message', [
			'message1' => $mes1,
			'message2' => $mes2,
			'backUrl' => htmlspecialchars($this->conf['mainScript']),
		]);
		
		echo $html;
		$this->drawFooter();
		exit;
	}

	public function drawBackButton(): void {
		$html = $this->renderer->render('back-button', [
			'backUrl' => htmlspecialchars($this->conf['mainScript']),
		]);
		echo $html;
	}

	public function drawUploadForm(string $url): void {
		$logFile = new logFile($this->conf);

		// Check capacity
		$capacityWarning = '';
		if ($logFile->getTotalUsageInBytes() >= $this->conf['maxTotalSize'] * 1024 * 1024) {
			$capacityWarning = '<p>' . $this->lang->get('upload.capacityExceeded') . '</p><p>' . $this->lang->get('upload.notifyAdmin') . '</p>';
		}

		$html = $this->renderer->render('upload-form', [
		'action' => $url,
		'maxFileSize' => ($this->lang->get('upload.maxFileSize', htmlspecialchars(bytesToHumanReadable($this->conf['maxUploadSize'] * 1024 * 1024)))),
		'defaultComment' => htmlspecialchars($this->conf['defaultComment']),
		'allowedExtensions' => htmlspecialchars(implode(", ", $this->conf['allowedExtensions'])),
		'capacityWarning' => $capacityWarning,
		'requestFrom' => isset($_GET['request']) && $_GET['request'] === 'catalog' ? 'catalog' : 'index',
		'chunkSize' => $this->conf['chunkSize'] ?? 2 * 1024 * 1024,
		'mainScript' => htmlspecialchars($this->conf['mainScript']),
		]);

		echo $html;
	}
	

	public function drawDeletionForm(int $fileID): void {
		$html = $this->renderer->render('delete-form', [
			'action' => htmlspecialchars($this->conf['mainScript']),
			'fileID' => $fileID,
		]);
		echo $html;
	}

	public function drawSettingsForm(): void {
		$cookie = $this->cookieSettingsManager->getSplitCookie();

		$html = $this->renderer->render('settings-form', [
			'action' => htmlspecialchars($this->conf['mainScript']) . '?request=settingsForm',
			'showDeleteButton' => $cookie['showDeleteButton'],
			'showComment' => $cookie['showComment'],
			'showPreviewImage' => $cookie['showPreviewImage'],
			'showFileName' => $cookie['showFileName'],
			'showDate' => $cookie['showDate'],
			'showFileSize' => $cookie['showFileSize'],
			'showMimeType' => $cookie['showMimeType'],
		]);

		echo $html;
	}

	public function drawActionLinks(): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		
		$html = $this->renderer->render('action-links', [
			'settingsUrl' => $self . '?request=settingsForm',
			'indexUrl' => $self,
			'catalogUrl' => $self . '?request=catalog',
			'searchUrl' => $self . '?request=search',
			'adminUrl' => $self . '?request=login',
		]);

		echo $html;
	}

	public function drawManageFilesPage(int $page = 1): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		$logFile = new logFile($this->conf);
		$count = $this->conf['filesPerListing'];

		$currentPage = $page;
		$page--;
		$lineOffset = $count * $page;

		$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], 'r');
		if (!$fileHandle) {
			echo $this->lang->get('errors.unableOpenLog');
			return;
		}

		$this->skipLines($fileHandle, $lineOffset);

		// Force all columns visible for admin view
		$cookie = [
			'showDeleteButton' => false,
			'showComment' => 'checked',
			'showPreviewImage' => 'checked',
			'showFileName' => 'checked',
			'showFileSize' => 'checked',
			'showMimeType' => 'checked',
			'showDate' => 'checked',
		];

		$tableHeader = $this->buildTableHeader($cookie, true);

		$entries = $this->processFileLines($fileHandle, $count, false);
		$tableRows = $this->buildTableRows($entries, $cookie, true);

		fclose($fileHandle);

		$usageInfo = $this->buildUsageInfo($logFile);

		// Build paging bar
		$url = $self . '?request=admin&modPage=manageFiles';
		$fileCount = $logFile->getTotalLogLines();
		$pages = ceil($fileCount / $this->conf['filesPerListing']) + 1;

		$pageLinks = '';
		for ($i = 1; $i < $pages; $i++) {
			if ($i == $currentPage) {
				$pageLinks .= '[<b>' . $i . '</b>]';
			} else {
				$pageLinks .= '[<a href="' . $url . '&pageNumber=' . htmlspecialchars($i) . '">' . htmlspecialchars($i) . '</a>]';
			}
		}

		$pagingBar = $this->renderer->render('paging-bar', [
			'homeUrl' => $self . '?request=admin',
			'pageLinks' => $pageLinks,
		]);

		$html = $this->renderer->render('admin-manage-files', [
			'mainScript' => $self,
			'backUrl' => $self . '?request=admin',
			'bulkDeleteUrl' => $self . '?request=admin&modPage=manageFiles&modAction=bulkDelete',
			'pagingBar' => $pagingBar,
			'manageTableHeader' => $tableHeader,
			'manageTableRows' => $tableRows,
			'usageInfo' => $usageInfo,
		]);

		echo $html;
	}

	public function drawAdminDashboard(): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		
		$html = $this->renderer->render('admin-dashboard', [
			'manageFilesUrl' => $self . '?request=admin&modPage=manageFiles',
			'manageBansUrl' => $self . '?request=admin&modPage=manageBans',
			'configUrl' => $self . '?request=admin&modPage=config',
			'logoutUrl' => $self . '?request=logout',
			'tenmaWelcomeImageUrl' => $this->conf['staticUrl'] . 'images/tenma.jpg',
			'tenmaBananaUrl' => $this->conf['staticUrl'] . 'images/tenma_banana_mascot.png',
			'backUrl' => $self,
		]);

		echo $html;
	}

	public function drawManageBansPage(): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		$banChecker = new banChecker();

		$bannedIPs = $banChecker->getBannedIPs();
		$bannedHashes = $banChecker->getBannedHashes();

		$bannedIPsList = $this->buildBanList($bannedIPs, 'ip', $self);
		$bannedHashesList = $this->buildBanList($bannedHashes, 'hash', $self);

		$html = $this->renderer->render('admin-manage-bans', [
			'backUrl' => $self . '?request=admin',
			'addBanUrl' => $self . '?request=admin&modPage=manageBans&modAction=addBan',
			'bannedIPsList' => $bannedIPsList,
			'bannedHashesList' => $bannedHashesList,
		]);

		echo $html;
	}

	private function buildBanList(array $entries, string $banType, string $self): string {
		if (empty($entries)) {
			return '<p><i>' . $this->lang->get('admin.none') . '</i></p>';
		}

		$html = '<form method="post" action="' . $self . '?request=admin&modPage=manageBans&modAction=removeBans">';
		$html .= '<input type="hidden" name="banType" value="' . htmlspecialchars($banType) . '">';
		$html .= '<ul class="banList banList-' . htmlspecialchars($banType) . '">';

		foreach ($entries as $entry) {
			$escaped = htmlspecialchars($entry);
			$html .= '<li class="banListEntry">';
			$html .= '<label class="banEntryLabel"><input type="checkbox" name="entries[]" value="' . $escaped . '"> ';
			$html .= $escaped;
			$html .= '</label></li>';
		}

		$html .= '</ul>';
		$html .= '<button type="submit">' . $this->lang->get('admin.removeSelected') . '</button>';
		$html .= '</form>';

		return $html;
	}

	public function drawAdminLoginForm(): void {
		$html = $this->renderer->render('admin-login-form', [
			'mainScript' => htmlspecialchars($this->conf['mainScript']),
		]);

		echo $html;
	}

	public function drawConfigEditor(string $statusMessage = ''): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		$configRows = '';
		foreach ($this->conf as $key => $value) {
			if (is_array($value)) {
				continue;
			}

			$escapedKey = htmlspecialchars($key);
			$escapedValue = htmlspecialchars((string) $value);

			$configRows .= '<tr>';
			$configRows .= '<td class="postblock"><label for="conf_' . $escapedKey . '">' . $escapedKey . '</label></td>';

			if (is_bool($value)) {
				$checked = $value ? ' checked' : '';
				$configRows .= '<td><input type="hidden" name="conf[' . $escapedKey . ']" value="0">';
				$configRows .= '<input type="checkbox" id="conf_' . $escapedKey . '" name="conf[' . $escapedKey . ']" value="1"' . $checked . '></td>';
			} elseif (is_int($value)) {
				$configRows .= '<td><input type="number" id="conf_' . $escapedKey . '" name="conf[' . $escapedKey . ']" value="' . $escapedValue . '"></td>';
			} else {
				$configRows .= '<td><input type="text" id="conf_' . $escapedKey . '" name="conf[' . $escapedKey . ']" value="' . $escapedValue . '" size="40"></td>';
			}

			$configRows .= '</tr>';
		}

		$html = $this->renderer->render('admin-config-editor', [
			'backUrl' => $self . '?request=admin',
			'saveUrl' => $self . '?request=admin&modPage=config&modAction=saveConfig',
			'configRows' => $configRows,
			'statusMessage' => $statusMessage ? '<p><b>' . htmlspecialchars($statusMessage) . '</b></p>' : '',
		]);

		echo $html;
	}

	public function drawSearchForm(string $url, array $parameters): void {
		$sortDir = $parameters['sortDir'] ?? 'desc';

		$html = $this->renderer->render('search-form', [
			'action' => htmlspecialchars($url),
			'originalFileName' => htmlspecialchars($parameters['originalFileName'] ?? ''),
			'comment' => htmlspecialchars($parameters['comment'] ?? ''),
			'fileExtension' => htmlspecialchars($parameters['fileExtension'] ?? ''),
			'mimeType' => htmlspecialchars($parameters['mimeType'] ?? ''),
			'sortDescSelected' => $sortDir === 'desc' ? 'selected' : '',
			'sortAscSelected' => $sortDir === 'asc' ? 'selected' : '',
		]);

		echo $html;
	}

	public function drawSearchResults(?array $searchResults, int $page = 1, array $searchParameters = []): void {
		if ($searchResults === null) {
			echo '<p>' . $this->lang->get('search.errorReadLog') . '</p>';
			return;
		}

		if (empty($searchResults)) {
			echo '<p>' . $this->lang->get('search.noResults') . '</p>';
			return;
		}

		$totalResults = count($searchResults);
		$perPage = $this->conf['filesPerListing'];
		$totalPages = (int)ceil($totalResults / $perPage);
		$page = max(1, min($page, $totalPages));
		$offset = ($page - 1) * $perPage;

		// Slice to current page
		$pageResults = array_slice($searchResults, $offset, $perPage);

		// Convert associative search results to uploadEntry objects
		$entries = [];
		foreach ($pageResults as $result) {
			$entries[] = new uploadEntry([
				$result['id'],
				$result['fileExtension'],
				$result['comment'],
				$result['host'],
				$result['dateUploaded'],
				$result['sizeInBytes'],
				$result['mimeType'],
				$result['password'],
				$result['originalFileName'],
			]);
		}

		$cookie = $this->cookieSettingsManager->getSplitCookie();
		$tableHeader = $this->buildTableHeader($cookie);
		$tableRows = $this->buildTableRows($entries, $cookie);

		echo '<p>' . $totalResults . ' ' . $this->lang->get('search.resultsFound') . '</p>';

		$html = $this->renderer->render('search-results', [
			'tableHeader' => $tableHeader,
			'tableRows' => $tableRows,
		]);

		echo $html;

		if ($totalPages > 1) {
			$this->drawSearchPagingBar($page, $totalPages, $searchParameters);
		}
	}

	private function drawSearchPagingBar(int $currentPage, int $totalPages, array $searchParameters): void {
		$baseUrl = htmlspecialchars($this->conf['mainScript']) . '?request=search';
		foreach ($searchParameters as $key => $value) {
			if ($value !== null && $value !== '') {
				$baseUrl .= '&' . htmlspecialchars($key) . '=' . urlencode($value);
			}
		}

		$pageLinks = '';
		for ($i = 1; $i <= $totalPages; $i++) {
			if ($i == $currentPage) {
				$pageLinks .= '[<b>' . $i . '</b>]';
			} else {
				$pageLinks .= '[<a href="' . $baseUrl . '&pageNumber=' . $i . '">' . $i . '</a>]';
			}
		}

		$html = $this->renderer->render('paging-bar', [
			'homeUrl' => $this->conf['home'],
			'pageLinks' => $pageLinks,
		]);

		echo $html;
	}
}
