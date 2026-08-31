<?php
namespace TwintailUploader\Classes;

use DateTime;

use TwintailUploader\Controllers\boardController;

use function TwintailUploader\Functions\bytesToHumanReadable;


class uploaderHTML {
	private $conf;
	private $renderer;
	private $cookieSettingsManager;
	private uploadPasswordCookie $uploadPasswordCookie;
	private languageManager $lang;
	private ?board $board;
	private string $csrfToken = '';

	/** Every column forced on, for the mod file listings */
	private const ADMIN_COLUMNS = [
		'showDeleteButton' => false,
		'showComment' => 'checked',
		'showPreviewImage' => 'checked',
		'showFileName' => 'checked',
		'showFileSize' => 'checked',
		'showMimeType' => 'checked',
		'showDate' => 'checked',
	];

	public function __construct(array $conf, languageManager $languageManager, ?board $board = null) {
		$this->conf = $conf;
		$this->lang = $languageManager;
		$this->board = $board;
		$this->renderer = new HTMLRenderer(__DIR__ . '/../templates', $this->lang);
		$this->cookieSettingsManager = new cookieSettingsManager($conf['defaultCookieValues']);
		$this->uploadPasswordCookie = new uploadPasswordCookie();
	}

	public function getLang(): languageManager {
		return $this->lang;
	}

	/**
	 * Makes the CSRF token available to every template ({{csrfToken}}) and to
	 * the action links this class builds by hand.
	 */
	public function setCsrfToken(string $token): void {
		$this->csrfToken = $token;
		$this->renderer->addGlobal('csrfToken', $token);
	}

	/**
	 * Whether the file list is hidden from users.
	 */
	private function isUnlisted(): bool {
		return !empty($this->conf['unlisted']);
	}

	private function makeTemporaryHosting(): temporaryHosting {
		return new temporaryHosting($this->conf, new logFile($this->conf), new uploadedFileRepository($this->conf));
	}

	public function drawHeader(): void {
		$themesDir = $this->conf['staticPath'] . 'css/themes';
		$themesUrl = $this->conf['staticUrl'] . 'css/themes';
		$themeManager = new themeManager($themesDir, $themesUrl);

		// A board's palette overrides the theme it started from, and any
		// variable the owner left alone keeps that theme's value, so the page
		// never ends up with an undefined colour.
		$customVariables = $this->conf['customThemeVariables'] ?? [];
		$hasCustomTheme = $customVariables !== [];

		$defaultTheme = $themeManager->resolveThemeName((string) $this->conf['defaultTheme'], $hasCustomTheme);
		$usingCustomTheme = $hasCustomTheme && $defaultTheme === themeManager::CUSTOM_THEME;
		$paletteBase = $this->paletteBaseTheme($themeManager);
		$baseTheme = $usingCustomTheme ? $paletteBase : $defaultTheme;

		if ($hasCustomTheme) {
			$customVariables += $themeManager->getThemeVariables($paletteBase) + themeManager::DEFAULT_VARIABLES;
		}

		$themeNames = $themeManager->getThemeNames();
		if ($hasCustomTheme) {
			$themeNames[] = themeManager::CUSTOM_THEME;
		}

		$themeLink = $themeManager->generateThemeLink($baseTheme, !$usingCustomTheme);
		$customThemeStyle = $hasCustomTheme
			? themeManager::generateCustomThemeStyle($customVariables, $usingCustomTheme)
			: '';
		$availableThemes = implode(',', $themeNames);

		$preloadLinks = $themeManager->generatePreloadLinks();

		$html = $this->renderer->render('header', [
			'defaultTheme' => htmlspecialchars($defaultTheme),
			'themeLink' => $themeLink,
			'customThemeStyle' => $customThemeStyle,
			'preloadLinks' => $preloadLinks,
			'availableThemes' => htmlspecialchars($availableThemes),
			'staticUrl' => $this->conf['staticUrl'],
			'boardTitle' => htmlspecialchars($this->conf['boardTitle']),
			'boardSubTitle' => htmlspecialchars($this->conf['boardSubTitle']),
		]);

		echo $html;
	}
	
	
	public function drawPageingBar(string $url, int $page = 1): void {
		// with no listing there is nothing to page through, but the bar still
		// carries the [Home] link
		$pageLinks = '';

		if (!$this->isUnlisted()) {
			$logFile = new logFile($this->conf);
			$fileCount = $logFile->getTotalLogLines();
			$pageLinks = $this->buildPagingLinks($url, $page, $fileCount, $this->conf['filesPerListing']);
		}

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

		return '<div class="pager">' . $pageLinks . '</div>';
	}

	public function drawFileListing(int $page = 1): void {
		echo $this->renderFileListing($page);
	}

	/**
	 * Builds the file listing HTML for a page and returns it as a string.
	 * Wrapped in #fileListing so the chunk uploader can swap it in via JS.
	 */
	public function renderFileListing(int $page = 1): string {
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
			return $this->lang->get('errors.unableOpenLog');
		}

		$this->skipLines($fileHandle, $lineOffset);

		$cookie = $this->cookieSettingsManager->getSettings();

		// Build table header
		$tableHeader = $this->buildTableHeader($cookie);

		// Build table rows
		$entries = $this->processFileLines($fileHandle, $count, false);
		$tableRows = $this->buildTableRows($entries, $cookie);

		fclose($fileHandle);

		// Build usage info
		$usageInfo = $this->buildUsageInfo($logFile);

		// Render template
		return $this->renderer->render('file-listing', [
			'tableHeader' => $tableHeader,
			'tableRows' => $tableRows,
			'usageInfo' => $usageInfo,
		]);
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
	 *
	 * $context is the same per-listing context buildTableRow takes; only its
	 * 'sourceLabel' matters here, and only to name the extra leading column.
	 */
	private function buildTableHeader(array $cookie, bool $isAdmin = false, bool $hideIPs = false, array $context = []): string {
		$deleteButtonHeader = $cookie['showDeleteButton'] ? '<th class="deleteColumn">' . $this->lang->get('table.delete') . '</th>' : '';
		$commentHeader = $cookie['showComment'] ? '<th class="commentColumn">' . $this->lang->get('table.comment') . '</th>' : '';
		$fileNameHeader = $cookie['showFileName'] ? '<th class="fileNameColumn">' . $this->lang->get('table.fileName') . '</th>' : '';
		$fileSizeHeader = $cookie['showFileSize'] ? '<th class="fileSizeColumn">' . $this->lang->get('table.size') . '</th>' : '';
		$mimeTypeHeader = $cookie['showMimeType'] ? '<th class="mimeTypeColumn">' . $this->lang->get('table.mime') . '</th>' : '';
		$dateHeader = $cookie['showDate'] ? '<th class="dateColumn">' . $this->lang->get('table.date') . '</th>' : '';

		$adminHeaders = '';
		if ($isAdmin) {
			$posterHeading = $hideIPs ? $this->lang->get('table.poster') : $this->lang->get('table.ip');
			$adminHeaders = '<th class="ipColumn">' . $posterHeading . '</th><th class="adminActionsColumn">' . $this->lang->get('table.actions') . '</th><th class="selectColumn">' . $this->lang->get('table.select') . '</th>';
		}

		$sourceHeader = isset($context['sourceLabel'])
			? '<th class="sourceColumn">' . $this->lang->get('table.source') . '</th>'
			: '';

		return $this->renderer->render('table-header', [
			'sourceHeader' => $sourceHeader,
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
	private function buildTableRows(array $entries, array $cookie, bool $isAdmin = false, bool $hideIPs = false): string {
		$rows = '';
		foreach ($entries as $data) {
			$rows .= $this->buildTableRow($data, $cookie, $isAdmin, $hideIPs);
		}
		return $rows;
	}

	/**
	 * Builds a single row in the file listing table.
	 *
	 * $context lets a row describe a file that doesn't belong to the instance
	 * this request is serving — the recent files page lists every board at once:
	 *   'conf'        path/prefix config the file's paths resolve against
	 *   'actionQuery' query string the mod action links hang off, already naming
	 *                 the file (defaults to this instance's own fileID link)
	 *   'selectValue' value of the bulk-action checkbox (defaults to the ID)
	 *   'sourceLabel' name of where the file lives, shown in a leading column
	 *   'filterUrl'   listing the uploader's address links back to, narrowed to
	 *                 that address (defaults to this instance's file manager)
	 */
	private function buildTableRow(uploadEntry $data, array $cookie, bool $isAdmin = false, bool $hideIPs = false, array $context = []): string {
		$rowConf = $context['conf'] ?? $this->conf;

		// Get display name, file path, and resolve the appropriate thumbnail
		$fileName = $data->getFileName($rowConf);
		$path = $data->getFilePath($rowConf);
		$thumbPath = $this->getThumbnailPath($data, $path, $rowConf);

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
			$deleteButton = '
			<td>
				<div class="deletionButton centerItem">
					<a href="' . htmlspecialchars($this->conf['mainScript']) . '?request=deleteFileForm&deleteFileID=' . htmlspecialchars($data->getId()) . '">■</a>
				</div>
			</td>';
		}

		// Name cell — shows either a thumbnail preview with the file name, or just the file name as a link
		$nameCell = '';
		$hrefPath = htmlspecialchars($path);
		$hrefThumb = htmlspecialchars($thumbPath);
		if ($cookie['showPreviewImage']) {
			$nameCell = '<td class="previewContainer"><a href="' . $hrefPath . '"> <img class="imagePreview" loading="lazy" src="' . $hrefThumb . '" width="' . $width . '" height="' . $height . '" alt="' . htmlspecialchars($fileName) . '"><br>' . htmlspecialchars($fileName) . '</a></td>';
		} else {
			$nameCell = '<td><a href="' . $hrefPath . '">' . htmlspecialchars($fileName) . '</a></td>';
		}

		// Optional metadata cells — each is toggled by the user's cookie settings
		$commentCell = $cookie['showComment'] ? '<td><span class="comment">' . $this->renderComment($data->getComment()) . '</span></td>' : '';
		$fileNameCell = $cookie['showFileName'] ? '<td><span class="fileName">' . htmlspecialchars($data->getOriginalFileName()) . '</span></td>' : '';
		$fileSizeCell = $cookie['showFileSize'] ? '<td><span class="fileSize">' . bytesToHumanReadable($data->getSize()) . '</span></td>' : '';
		$mimeTypeCell = $cookie['showMimeType'] ? '<td><span class="grayText mimeTypeColumn">' . htmlspecialchars($data->getMimeType()) . '</span></td>' : '';

		// Timestamp for date cell
		$timestamp = $data->getTime();

		// Date cell with formatted timestamp, also toggled by cookie settings
		$dateCell = $cookie['showDate'] ? '<td><span class="grayText dateColumn">' . ($timestamp ? htmlspecialchars(date('Y-m-d H:i:s', (int)$timestamp)) : '') . '</span></td>' : '';

		// Admin-only cells: uploader identity and action links
		$adminCells = '';
		if ($isAdmin) {
			$self = htmlspecialchars($this->conf['mainScript']);
			$fileId = htmlspecialchars($data->getId());
			$actionQuery = $context['actionQuery'] ?? '?request=admin&modPage=manageFiles&fileID=' . $fileId;
			$actionUrl = $self . $actionQuery . '&csrfToken=' . urlencode($this->csrfToken);
			$selectValue = htmlspecialchars($context['selectValue'] ?? (string) $data->getId());

			// board owners only ever see a per-board salted hash of the uploader
			$poster = ($hideIPs && $this->board !== null)
				? $this->board->hashIp($data->getIp())
				: $data->getIp();

			$filterUrl = $context['filterUrl'] ?? $self . '?request=admin&modPage=manageFiles';

			$adminCells .= '<td class="ipCell">' . $this->addressFilterLink($poster, $filterUrl) . '</td>';
			$adminCells .= '<td class="adminActionsCell">';
			$adminCells .= '[<a href="' . $actionUrl . '&modAction=deleteFile">' . $this->lang->get('admin.deleteAction') . '</a>] ';
			$adminCells .= '[<a href="' . $actionUrl . '&modAction=banIP">' . $this->lang->get('admin.banUser') . '</a>] ';
			$adminCells .= '[<a href="' . $actionUrl . '&modAction=banFile">' . $this->lang->get('admin.banFile') . '</a>]';
			$adminCells .= '</td>';
			$adminCells .= '<td><input type="checkbox" name="fileIDs[]" value="' . $selectValue . '"></td>';
		}

		// Names where the file lives, on listings that span more than one source
		$sourceCell = '';
		if (isset($context['sourceLabel'])) {
			$sourceLabel = htmlspecialchars($context['sourceLabel']);

			$sourceCell = '<td class="sourceCell">' . (isset($context['sourceUrl'])
				? '<a href="' . htmlspecialchars($context['sourceUrl']) . '">' . $sourceLabel . '</a>'
				: $sourceLabel) . '</td>';
		}

		// Render the assembled cells into the table-row template
		return $this->renderer->render('table-row', [
			'sourceCell' => $sourceCell,
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

	private function getThumbnailPath(uploadEntry $data, string $defaultPath, ?array $conf = null): string {
		$conf = $conf ?? $this->conf;
		$mimeType = $data->getMimeType();

		// File exists but thumbnail wasn't generated
		$thumbPath = $data->getThumbPath($conf);

		// For image types: check if file exists at all
		if (!file_exists($defaultPath)) {
			return $this->conf['staticUrl'] . 'images/nofile.gif';
		}

		// Flash file (SWF)
		else if (preg_match('/x-shockwave-flash|flash|swf/i', $mimeType) || preg_match('/\.swf$/i', $data->getOriginalFileName())) {
			return $this->conf['staticUrl'] . 'images/swf_thumb.png';
		}

		// Application types
		else if (preg_match('/application/i', $mimeType)) {
			return $this->conf['staticUrl'] . 'images/archive.png';
		}

		// Audio file types
		else if (preg_match('/audio/i', $mimeType)) {
			return $this->conf['staticUrl'] . 'images/audio.png';
		}

		// If it's an image type but the thumbnail doesn't exist, show a "no thumbnail" image instead of a broken image link/preview
		else if (!file_exists($thumbPath)) {
			return $this->conf['staticUrl'] . 'images/nothumb.gif';
		}

		// Video file types
		else if (preg_match('/video/i', $mimeType)) {
			$videoThumbPath = $data->getVideoThumbPath($conf);
			return file_exists($videoThumbPath) ? $videoThumbPath : $this->conf['staticUrl'] . 'images/nothumb.gif';
		}
		
		// Non-image types that weren't caught above: use archive icon as fallback
		else if (!preg_match('/image/i', $mimeType)) {
			return $this->conf['staticUrl'] . 'images/archive.png';
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
		echo $this->renderCatalog($page);
	}

	/**
	 * Builds the catalog HTML for a page and returns it as a string.
	 * Wrapped in #fileListing so the chunk uploader can swap it in via JS.
	 */
	public function renderCatalog(int $page = 1): string {
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
			return $this->lang->get('errors.unableOpenLog');
		}

		$this->skipLines($fileHandle, $lineOffset);

		// get an array of data's at a time (batched rows)
		$batchedData = $this->processFileLines($fileHandle, $count, true);

		fclose($fileHandle);

		// get cookie settings for which columns to show
		$cookie = $this->cookieSettingsManager->getSettings();

		// Build catalog rows
		$catalogRows = $this->buildCatalogRows($batchedData, $cookie);

		// Render template
		return $this->renderer->render('catalog', [
			'catalogRows' => $catalogRows,
		]);
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
			'aiHelperUrl' => $this->conf['staticUrl'] . 'images/aihelper.png',
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

		// tell people up front that their upload has a lifetime
		$temporaryHosting = $this->makeTemporaryHosting();
		$temporaryNotice = $temporaryHosting->isEnabled()
			? '<p class="temporaryNotice">' . $this->lang->get('upload.temporaryNotice', $temporaryHosting->getLifetimeHours()) . '</p>'
			: '';

		$html = $this->renderer->render('upload-form', [
		'action' => $url,
		'temporaryNotice' => $temporaryNotice,
		'maxFileSize' => ($this->lang->get('upload.maxFileSize', htmlspecialchars(bytesToHumanReadable($this->conf['maxUploadSize'] * 1024 * 1024)))),
		'defaultComment' => htmlspecialchars($this->conf['defaultComment']),
		'allowedExtensions' => htmlspecialchars(implode(", ", $this->conf['allowedExtensions'])),
		'capacityWarning' => $capacityWarning,
		'requestFrom' => isset($_GET['request']) && $_GET['request'] === 'catalog' ? 'catalog' : 'index',
		'chunkSize' => $this->conf['chunkSize'] ?? 2 * 1024 * 1024,
		'mainScript' => htmlspecialchars($this->conf['mainScript']),
		'staticUrl' => $this->conf['staticUrl'],
		'uploadPassword' => htmlspecialchars($this->uploadPasswordCookie->get()),
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
		$cookie = $this->cookieSettingsManager->getSettings();

		$html = $this->renderer->render('settings-form', [
			'action' => htmlspecialchars($this->conf['mainScript']) . '?request=settingsForm',
			'showDeleteButton' => $cookie['showDeleteButton'],
			'showComment' => $cookie['showComment'],
			'showPreviewImage' => $cookie['showPreviewImage'],
			'showFileName' => $cookie['showFileName'],
			'showDate' => $cookie['showDate'],
			'showFileSize' => $cookie['showFileSize'],
			'showMimeType' => $cookie['showMimeType'],
			'showBottomPager' => $cookie['showBottomPager'],
		]);

		echo $html;
	}

	/**
	 * $trailingSeparator closes the links off with a rule; pages that end on
	 * the links themselves pass false so the footer's own rule isn't doubled.
	 */
	public function drawActionLinks(bool $trailingSeparator = true): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		// the listing is a route on the instance script, which a board reaches
		// through rootScript rather than its own stub
		$boardsUrl = htmlspecialchars($this->conf['rootScript'] ?? $this->conf['mainScript']) . '?request=boards';

		// the catalog and the search only browse the listing, so they go with it
		$browseLinks = $this->isUnlisted() ? '' :
			'<a href="' . $self . '?request=catalog">' . $this->lang->get('nav.catalog') . '</a> |' .
			'<a href="' . $self . '?request=search">' . $this->lang->get('nav.search') . '</a> |';

		$html = $this->renderer->render('action-links', [
			'settingsUrl' => $self . '?request=settingsForm',
			'indexUrl' => $self,
			'browseLinks' => $browseLinks,
			'boardsUrl' => $boardsUrl,
			'adminUrl' => $self . '?request=login',
			'trailingSeparator' => $trailingSeparator ? '<hr class="lineSeparator">' : '',
		]);

		echo $html;
	}

	/**
	 * Shown after an upload when there is no listing to return to: the
	 * uploader's own link, and when it stops working.
	 */
	public function drawUploadCompletePage(uploadEntry $entry): void {
		$expiresTime = $entry->getExpiresTime();

		$expiryNotice = $expiresTime > 0
			? '<p class="temporaryNotice">' . $this->lang->get('upload.expiresAt', htmlspecialchars(date('Y-m-d H:i:s', $expiresTime))) . '</p>'
			: '';

		echo $this->renderer->render('upload-complete', [
			'fileUrl' => htmlspecialchars($entry->getFilePath($this->conf)),
			'fileName' => htmlspecialchars($entry->getFileName($this->conf)),
			'expiryNotice' => $expiryNotice,
			'backUrl' => htmlspecialchars($this->conf['mainScript']),
		]);
	}

	public function drawManageFilesPage(int $page = 1, bool $hideIPs = false, string $ipFilter = ''): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		$logFile = new logFile($this->conf);
		$count = $this->conf['filesPerListing'];
		$pageUrl = $self . '?request=admin&modPage=manageFiles';

		$currentPage = max(1, $page);

		if ($ipFilter !== '') {
			// narrowed to one uploader, so the whole log has to be looked at
			$matching = $this->readEntriesForAddress($ipFilter, $hideIPs);
			$totalEntries = count($matching);
			$entries = array_slice($matching, ($currentPage - 1) * $count, $count);
		} else {
			$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], 'r');
			if (!$fileHandle) {
				echo $this->lang->get('errors.unableOpenLog');
				return;
			}

			$this->skipLines($fileHandle, $count * ($currentPage - 1));
			$entries = $this->processFileLines($fileHandle, $count, false);
			fclose($fileHandle);

			$totalEntries = $logFile->getTotalLogLines();
		}

		$cookie = self::ADMIN_COLUMNS;

		$tableHeader = $this->buildTableHeader($cookie, true, $hideIPs);
		$tableRows = $this->buildTableRowsForListing($entries, $cookie, $hideIPs, $pageUrl);

		$usageInfo = $this->buildUsageInfo($logFile);

		$pagingBar = $this->buildModPagingBar(
			$pageUrl . $this->addressFilterQuery($ipFilter),
			$currentPage,
			(int) ceil($totalEntries / $count)
		);

		$html = $this->renderer->render('admin-manage-files', [
			'mainScript' => $self,
			'backUrl' => $self . '?request=admin',
			'bulkDeleteUrl' => $self . '?request=admin&modPage=manageFiles&modAction=bulkDelete',
			'filterNotice' => $this->buildAddressFilterNotice($ipFilter, $pageUrl),
			'pagingBar' => $pagingBar,
			'manageTableHeader' => $tableHeader,
			'manageTableRows' => $tableRows,
			'usageInfo' => $usageInfo,
		]);

		echo $html;
	}

	/**
	 * Rows for a mod file listing, with each uploader's address linking back to
	 * the same listing narrowed to it.
	 *
	 * @param uploadEntry[] $entries
	 */
	private function buildTableRowsForListing(array $entries, array $cookie, bool $hideIPs, string $pageUrl): string {
		$rows = '';
		foreach ($entries as $entry) {
			$rows .= $this->buildTableRow($entry, $cookie, true, $hideIPs, ['filterUrl' => $pageUrl]);
		}
		return $rows;
	}

	/**
	 * Every entry whose uploader matches the filter, newest first.
	 *
	 * Only reached when a filter is active — an unfiltered listing streams the
	 * log from an offset rather than holding it all in memory.
	 *
	 * @return uploadEntry[]
	 */
	private function readEntriesForAddress(string $ipFilter, bool $hideIPs): array {
		$fileHandle = fopen(\DATA_DIR . $this->conf['logFile'], 'r');
		if (!$fileHandle) {
			return [];
		}

		$entries = [];
		while (($line = fgets($fileHandle)) !== false) {
			if (trim($line) === '') {
				continue;
			}

			$entry = new uploadEntry(explode('<>', $line));
			if ($this->matchesAddressFilter($entry->getIp(), $ipFilter, $hideIPs)) {
				$entries[] = $entry;
			}
		}
		fclose($fileHandle);

		return $entries;
	}

	/** The active address filter as a query fragment for paging links. */
	private function addressFilterQuery(string $ipFilter): string {
		return $ipFilter === '' ? '' : '&ipFilter=' . urlencode($ipFilter);
	}

	/**
	 * Every upload on the instance — the main uploader and every user board —
	 * newest first. Global admin only, so uploaders are shown as real addresses.
	 *
	 * @param array<array{entry: uploadEntry, source: fileSource}> $rows newest first
	 */
	public function drawRecentFilesPage(array $rows, int $page = 1, string $ipFilter = ''): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		$perPage = $this->conf['filesPerListing'];
		$pageUrl = $self . '?request=admin&modPage=recentFiles';

		if ($ipFilter !== '') {
			// this page is global admin only, so addresses are never masked here
			$rows = array_values(array_filter(
				$rows,
				fn(array $row) => $this->matchesAddressFilter($row['entry']->getIp(), $ipFilter, false)
			));
		}

		$totalFiles = count($rows);
		$totalPages = max(1, (int) ceil($totalFiles / $perPage));
		$page = max(1, min($page, $totalPages));

		$cookie = self::ADMIN_COLUMNS;

		// the source column only exists here, so the header needs to know too
		$tableHeader = $this->buildTableHeader($cookie, true, false, ['sourceLabel' => '']);

		$tableRows = '';
		foreach (array_slice($rows, ($page - 1) * $perPage, $perPage) as $row) {
			$source = $row['source'];
			// the plain integer, never the log's zero-padded form — the reference
			// is validated as an int on the way back in
			$reference = $source->getKey() . ':' . $row['entry']->getId();

			$tableRows .= $this->buildTableRow($row['entry'], $cookie, true, false, [
				'conf' => $source->getConf(),
				'actionQuery' => '?request=admin&modPage=recentFiles&fileRef=' . urlencode($reference),
				'selectValue' => $reference,
				'sourceLabel' => $source->getLabel(),
				'sourceUrl' => $source->getUrl($this->conf),
				'filterUrl' => $pageUrl,
			]);
		}

		if ($tableRows === '') {
			$tableRows = '<tr><td colspan="10"><i>' . $this->lang->get('admin.noFiles') . '</i></td></tr>';
		}

		$pagingBar = $this->buildModPagingBar($pageUrl . $this->addressFilterQuery($ipFilter), $page, $totalPages);

		echo $this->renderer->render('admin-recent-files', [
			'backUrl' => $self . '?request=admin',
			'bulkDeleteUrl' => $self . '?request=admin&modPage=recentFiles&modAction=bulkDelete',
			'filterNotice' => $this->buildAddressFilterNotice($ipFilter, $pageUrl),
			'pagingBar' => $pagingBar,
			'manageTableHeader' => $tableHeader,
			'manageTableRows' => $tableRows,
			'fileCount' => $this->lang->get('usage.used') . ' ' . $totalFiles . ' ' . $this->lang->get('usage.files'),
		]);
	}

	/**
	 * The action log: what has been uploaded, deleted, banned and changed.
	 *
	 * $context:
	 *   'showSource'   name the scope each action happened in — the instance-wide
	 *                  page merges the main uploader and every board
	 *   'hideIPs'      a board owner is looking, so every address is shown as this
	 *                  board's poster hash. It covers the target column too: the
	 *                  address a ban was placed on is an address like any other
	 *   'actionFilter' the action the list is narrowed to, '' for everything
	 *   'ipFilter'     the address the list is narrowed to, as it is displayed:
	 *                  a poster hash when 'hideIPs' is on, an address otherwise
	 *
	 * @param array<array{entry: actionLogEntry, source?: fileSource}> $rows newest first
	 */
	public function drawActionLogPage(array $rows, int $page = 1, array $context = []): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		$showSource = !empty($context['showSource']);
		$hideIPs = !empty($context['hideIPs']);
		$actionFilter = (string) ($context['actionFilter'] ?? '');
		$ipFilter = (string) ($context['ipFilter'] ?? '');
		$pageUrl = $self . '?request=admin&modPage=actionLog';

		// an address matches whether it acted or was acted on, so following one
		// from either column shows everything the log has on it
		if ($ipFilter !== '') {
			$rows = array_values(array_filter($rows, function (array $row) use ($ipFilter, $hideIPs) {
				$entry = $row['entry'];
				return $this->matchesAddressFilter($entry->getIp(), $ipFilter, $hideIPs)
					|| $this->matchesAddressFilter($entry->getTarget(), $ipFilter, $hideIPs);
			}));
		}

		$perPage = max(1, (int) $this->conf['filesPerListing']);
		$totalEntries = count($rows);
		$totalPages = max(1, (int) ceil($totalEntries / $perPage));
		$page = max(1, min($page, $totalPages));

		$tableRows = '';
		foreach (array_slice($rows, ($page - 1) * $perPage, $perPage) as $row) {
			$tableRows .= $this->buildActionLogRow($row, $showSource, $hideIPs, $pageUrl);
		}

		if ($tableRows === '') {
			$tableRows = '<tr><td colspan="7"><i>' . $this->lang->get('actionLog.noActions') . '</i></td></tr>';
		}

		// both filters have to survive paging, so they ride along in the links
		$pagedUrl = $pageUrl;
		if ($actionFilter !== '') {
			$pagedUrl .= '&actionFilter=' . urlencode($actionFilter);
		}
		$pagedUrl .= $this->addressFilterQuery($ipFilter);

		$pagingBar = $this->buildModPagingBar($pagedUrl, $page, $totalPages);

		echo $this->renderer->render('action-log', [
			'mainScript' => $self,
			'backUrl' => $self . '?request=admin',
			'description' => $showSource
				? $this->lang->get('actionLog.instanceDescription')
				: $this->lang->get('actionLog.boardDescription'),
			'scopeNote' => $hideIPs
				? '<p class="grayText">' . $this->lang->get('boards.ownerScopeNote') . '</p>'
				: '',
			'filterOptions' => $this->buildActionFilterOptions($actionFilter),
			// picking an action must not drop the address the list is on
			'ipFilterField' => $ipFilter === ''
				? ''
				: '<input type="hidden" name="ipFilter" value="' . htmlspecialchars($ipFilter) . '">',
			'filterNotice' => $this->buildAddressFilterNotice($ipFilter, $pageUrl),
			'tableHeader' => $this->buildActionLogHeader($showSource, $hideIPs),
			'tableRows' => $tableRows,
			'pagingBar' => $pagingBar,
			'entryCount' => $this->lang->get('actionLog.entryCount', $totalEntries),
		]);
	}

	private function buildActionLogHeader(bool $showSource, bool $hideIPs): string {
		$header = '<thead><tr>';
		$header .= '<th class="dateColumn">' . $this->lang->get('table.date') . '</th>';

		if ($showSource) {
			$header .= '<th class="sourceColumn">' . $this->lang->get('table.source') . '</th>';
		}

		$header .= '<th>' . $this->lang->get('actionLog.action') . '</th>';
		$header .= '<th>' . $this->lang->get('actionLog.actor') . '</th>';
		$header .= '<th class="ipColumn">' . $this->lang->get($hideIPs ? 'table.poster' : 'table.ip') . '</th>';
		$header .= '<th>' . $this->lang->get('actionLog.target') . '</th>';
		$header .= '<th class="commentColumn">' . $this->lang->get('actionLog.details') . '</th>';
		$header .= '</tr></thead>';

		return $header;
	}

	/**
	 * @param array{entry: actionLogEntry, source?: fileSource} $row
	 */
	private function buildActionLogRow(array $row, bool $showSource, bool $hideIPs, string $pageUrl): string {
		$entry = $row['entry'];

		$cells = '<td><span class="grayText dateColumn">' . htmlspecialchars(date('Y-m-d H:i:s', $entry->getTime())) . '</span></td>';

		if ($showSource) {
			$source = $row['source'] ?? null;
			$label = $source !== null ? $source->getLabel() : $entry->getScope();

			$cells .= '<td class="sourceCell">' . ($source !== null
				? '<a href="' . htmlspecialchars($source->getUrl($this->conf)) . '">' . htmlspecialchars($label) . '</a>'
				: htmlspecialchars($label)) . '</td>';
		}

		$cells .= '<td><span class="actionName">' . htmlspecialchars($this->actionLogLabel('actions', $entry->getAction())) . '</span></td>';
		$cells .= '<td>' . htmlspecialchars($this->actionLogLabel('actors', $entry->getActor())) . '</td>';
		$cells .= '<td class="ipCell">' . $this->addressFilterLink($this->maskAddress($entry->getIp(), $hideIPs), $pageUrl) . '</td>';

		// a target that is an address filters like the actor column does; file
		// names, hashes and board URIs are just text
		$target = $this->maskAddress($entry->getTarget(), $hideIPs);
		$targetCell = filter_var($entry->getTarget(), FILTER_VALIDATE_IP)
			? $this->addressFilterLink($target, $pageUrl)
			: htmlspecialchars($target);

		$cells .= '<td><span class="fileName">' . $targetCell . '</span></td>';
		$cells .= '<td><span class="comment">' . htmlspecialchars($entry->getDetails()) . '</span></td>';

		return '<tr>' . $cells . '</tr>';
	}

	/**
	 * Label for an action or actor key, falling back to the key itself so a log
	 * line written by a newer version still reads as something.
	 */
	private function actionLogLabel(string $group, string $key): string {
		if ($key === '') {
			return '';
		}

		$languageKey = 'actionLog.' . $group . '.' . $key;
		$label = $this->lang->get($languageKey);

		return $label === $languageKey ? $key : $label;
	}

	/**
	 * Anything that is an address becomes this board's poster hash when an owner
	 * is looking. Values that aren't addresses (file names, hashes, board URIs)
	 * pass through untouched.
	 */
	private function maskAddress(string $value, bool $hideIPs): string {
		if (!$hideIPs || $this->board === null || !filter_var($value, FILTER_VALIDATE_IP)) {
			return $value;
		}

		return $this->board->hashIp($value);
	}

	/**
	 * An address rendered as a link that narrows the listing to it.
	 *
	 * The link carries exactly what is on screen — a poster hash for a board
	 * owner, a real address for the admin — so following one never puts an
	 * address in front of someone who isn't allowed to see one. The pages that
	 * read it back compare the same masked form.
	 */
	private function addressFilterLink(string $displayed, string $pageUrl): string {
		if ($displayed === '') {
			return '';
		}

		return '<a href="' . $pageUrl . '&ipFilter=' . urlencode($displayed) . '">'
			. htmlspecialchars($displayed) . '</a>';
	}

	/** Whether an entry belongs to the address a listing is filtered to. */
	private function matchesAddressFilter(string $ip, string $ipFilter, bool $hideIPs): bool {
		return $ipFilter === '' || $this->maskAddress($ip, $hideIPs) === $ipFilter;
	}

	/**
	 * The "showing only this address" line, with the way back to the whole
	 * listing. Empty when nothing is filtered.
	 */
	private function buildAddressFilterNotice(string $ipFilter, string $pageUrl): string {
		if ($ipFilter === '') {
			return '';
		}

		return '<p class="filterNotice">'
			. $this->lang->get('admin.filteredByAddress', htmlspecialchars($ipFilter))
			. ' [<a href="' . $pageUrl . '">' . $this->lang->get('admin.clearFilter') . '</a>]</p>';
	}

	private function buildActionFilterOptions(string $selected): string {
		$options = '<option value="">' . htmlspecialchars($this->lang->get('actionLog.allActions')) . '</option>';

		foreach (actionLogEntry::ACTIONS as $action) {
			$options .= '<option value="' . htmlspecialchars($action) . '"'
				. ($action === $selected ? ' selected' : '') . '>'
				. htmlspecialchars($this->actionLogLabel('actions', $action)) . '</option>';
		}

		return $options;
	}

	/**
	 * The [Home]-and-page-numbers bar mod listings page through. Mod pages show
	 * it above and below the table, so it is built once and rendered twice.
	 */
	private function buildModPagingBar(string $url, int $currentPage, int $totalPages): string {
		$pageLinks = '';

		for ($i = 1; $i <= $totalPages; $i++) {
			if ($i === $currentPage) {
				$pageLinks .= '[<b>' . $i . '</b>]';
			} else {
				$pageLinks .= '[<a href="' . $url . '&pageNumber=' . $i . '">' . $i . '</a>]';
			}
		}

		return $this->renderer->render('paging-bar', [
			'homeUrl' => htmlspecialchars($this->conf['mainScript']) . '?request=admin',
			'pageLinks' => $pageLinks,
		]);
	}

	public function drawAdminDashboard(): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		$html = $this->renderer->render('admin-dashboard', [
			'manageFilesUrl' => $self . '?request=admin&modPage=manageFiles',
			'recentFilesUrl' => $self . '?request=admin&modPage=recentFiles',
			'actionLogUrl' => $self . '?request=admin&modPage=actionLog',
			'manageBansUrl' => $self . '?request=admin&modPage=manageBans',
			'manageBoardsUrl' => $self . '?request=admin&modPage=manageBoards',
			'configUrl' => $self . '?request=admin&modPage=config',
			'logoutUrl' => $self . '?request=logout',
			'tenmaWelcomeImageUrl' => $this->conf['staticUrl'] . 'images/tenma.jpg',
			'tenmaBananaUrl' => $this->conf['staticUrl'] . 'images/tenma_banana_mascot.png',
			'backUrl' => $self,
		]);

		echo $html;
	}

	/**
	 * $hideIPs swaps in the owner-facing page, where banned uploaders appear as
	 * this board's salted hashes and there are no forms taking a raw address.
	 */
	public function drawManageBansPage(banChecker $banChecker, bool $hideIPs = false): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		$bannedIPsList = $this->buildBanList($banChecker->getBannedIPs(), 'ip', $self, $hideIPs);
		$bannedHashesList = $this->buildBanList($banChecker->getBannedHashes(), 'hash', $self);

		$template = $hideIPs ? 'board-manage-bans' : 'admin-manage-bans';

		$html = $this->renderer->render($template, [
			'backUrl' => $self . '?request=admin',
			'addBanUrl' => $self . '?request=admin&modPage=manageBans&modAction=addBan',
			'bannedIPsList' => $bannedIPsList,
			'bannedHashesList' => $bannedHashesList,
		]);

		echo $html;
	}

	/**
	 * When $hashValues is set the stored IPs are only ever rendered as hashes,
	 * including as the checkbox value that gets posted back for removal.
	 */
	private function buildBanList(array $entries, string $banType, string $self, bool $hashValues = false): string {
		if (empty($entries)) {
			return '<p><i>' . $this->lang->get('admin.none') . '</i></p>';
		}

		$html = '<form method="post" action="' . $self . '?request=admin&modPage=manageBans&modAction=removeBans">';
		$html .= '<input type="hidden" name="banType" value="' . htmlspecialchars($banType) . '">';
		$html .= '<input type="hidden" name="csrfToken" value="' . htmlspecialchars($this->csrfToken) . '">';
		$html .= '<ul class="banList banList-' . htmlspecialchars($banType) . '">';

		foreach ($entries as $entry) {
			if ($hashValues && $this->board !== null) {
				$entry = $this->board->hashIp($entry);
			}

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
			'heading' => $this->board !== null ? $this->lang->get('boards.ownerLogin') : $this->lang->get('admin.login'),
			'loginHint' => $this->board !== null ? '<p class="grayText">' . $this->lang->get('boards.ownerLoginHint') . '</p>' : '',
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
				$result['storedName'] ?? '',
				$result['expiresTime'] ?? 0,
				$result['fileHash'] ?? '',
			]);
		}

		$cookie = $this->cookieSettingsManager->getSettings();
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

	/* ---------------------------------------------------------------- boards */

	/**
	 * Public index of user boards.
	 *
	 * @param board[] $boards
	 */
	public function drawBoardListing(array $boards): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		$boardRows = '';
		foreach ($boards as $board) {
			$url = htmlspecialchars($board->getUrl($this->conf));
			$lockedNote = $board->isLocked() ? ' <span class="redText">[' . $this->lang->get('boards.locked') . ']</span>' : '';

			$boardRows .= '<tr>';
			$boardRows .= '<td class="boardUriCell">[<a href="' . $url . '">' . htmlspecialchars($board->getUri()) . '</a>]' . $lockedNote . '</td>';
			$boardRows .= '<td><a href="' . $url . '">' . htmlspecialchars($board->getTitle()) . '</a></td>';
			$boardRows .= '<td><span class="comment">' . htmlspecialchars($board->getSubTitle()) . '</span></td>';
			$boardRows .= '<td><span class="grayText dateColumn">' . htmlspecialchars(date('Y-m-d', $board->getCreatedTime())) . '</span></td>';
			$boardRows .= '</tr>';
		}

		if ($boardRows === '') {
			$boardRows = '<tr><td colspan="4"><i>' . $this->lang->get('boards.noBoards') . '</i></td></tr>';
		}

		$createLink = !empty($this->conf['allowUserBoards'])
			? '[<a href="' . $self . '?request=createBoard">' . $this->lang->get('boards.createBoard') . '</a>]'
			: '<i>' . $this->lang->get('boards.creationDisabled') . '</i>';

		echo $this->renderer->render('board-list', [
			'boardRows' => $boardRows,
			'createLink' => $createLink,
			'backUrl' => $self,
		]);
	}

	public function drawBoardCreationForm(): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		echo $this->renderer->render('board-create-form', [
			'action' => $self . '?request=createBoard',
			'backUrl' => $self . '?request=boards',
			'defaultComment' => htmlspecialchars($this->conf['defaultComment']),
			'limitsNote' => $this->lang->get(
				'boards.limitsNote',
				(int) $this->conf['boardMaxAmountOfFiles'],
				bytesToHumanReadable($this->conf['boardMaxUploadSize'] * 1024 * 1024),
				bytesToHumanReadable($this->conf['boardMaxTotalSize'] * 1024 * 1024)
			),
		]);
	}

	/**
	 * Board equivalent of drawErrorPageAndExit — Ai explains what went wrong.
	 */
	public function drawBoardErrorPageAndExit(string $message1, string $message2, string $returnUrl): void {
		$this->drawHeader();

		echo $this->renderer->render('board-error-page', [
			'message1' => $message1,
			'message2' => $message2,
			'aiHelperUrl' => $this->conf['staticUrl'] . 'images/aihelper.png',
			'returnUrl' => htmlspecialchars($returnUrl),
		]);

		$this->drawFooter();
		exit;
	}

	/**
	 * Shown after a board is created, on its own route so a refresh doesn't
	 * resubmit the form.
	 */
	public function drawBoardCreatedPage(board $board): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		echo $this->renderer->render('board-created', [
			'aiYayUrl' => $this->conf['staticUrl'] . 'images/aiyay.png',
			'boardUrl' => htmlspecialchars($board->getUrl($this->conf)),
			'boardUri' => htmlspecialchars($board->getUri()),
			'boardsUrl' => $self . '?request=boards',
		]);
	}

	/**
	 * Mod dashboard a board owner (or a visiting global admin) lands on.
	 */
	public function drawBoardAdminDashboard(bool $isGlobalAdmin): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		$scopeNote = $isGlobalAdmin
			? '<p class="redText">' . $this->lang->get('boards.adminScopeNote') . '</p>'
			: '<p class="grayText">' . $this->lang->get('boards.ownerScopeNote') . '</p>';

		echo $this->renderer->render('board-admin-dashboard', [
			'boardTitle' => htmlspecialchars($this->conf['boardTitle']),
			'scopeNote' => $scopeNote,
			'manageFilesUrl' => $self . '?request=admin&modPage=manageFiles',
			'manageBansUrl' => $self . '?request=admin&modPage=manageBans',
			'actionLogUrl' => $self . '?request=admin&modPage=actionLog',
			'settingsUrl' => $self . '?request=admin&modPage=settings',
			'logoutUrl' => $self . '?request=logout',
			'backUrl' => $self,
		]);
	}

	public function drawBoardSettingsPage(): void {
		$self = htmlspecialchars($this->conf['mainScript']);

		$themeManager = new themeManager(
			$this->conf['staticPath'] . 'css/themes',
			$this->conf['staticUrl'] . 'css/themes'
		);

		echo $this->renderer->render('board-settings', [
			'backUrl' => $self . '?request=admin',
			'saveUrl' => $self . '?request=admin&modPage=settings&modAction=saveSettings',
			'boardUri' => htmlspecialchars($this->board->getUri()),
			'title' => htmlspecialchars($this->board->getTitle()),
			'subTitle' => htmlspecialchars($this->board->getSubTitle()),
			'defaultComment' => htmlspecialchars($this->board->getDefaultComment()),
			'commentRequiredChecked' => $this->board->isCommentRequired() ? 'checked' : '',
			'listedChecked' => $this->board->isListed() ? 'checked' : '',
			'themeOptions' => $this->buildThemeOptions($themeManager),
			'themeVariableRows' => $this->buildThemeVariableRows($themeManager),
		]);
	}

	/**
	 * The installed themes plus the board's own palette, for the settings
	 * page. An empty value means "whatever the instance default is".
	 */
	private function buildThemeOptions(themeManager $themeManager): string {
		$selected = $this->board->getTheme();

		$options = '<option value=""' . ($selected === '' ? ' selected' : '') . '>'
			. $this->lang->get('theme.instanceDefault') . '</option>';

		foreach ($themeManager->getThemeNames() as $name) {
			$options .= '<option value="' . htmlspecialchars($name) . '"'
				. ($selected === $name ? ' selected' : '') . '>' . htmlspecialchars($name) . '</option>';
		}

		$options .= '<option value="' . themeManager::CUSTOM_THEME . '"'
			. ($selected === themeManager::CUSTOM_THEME ? ' selected' : '') . '>'
			. $this->lang->get('theme.customTheme') . '</option>';

		return $options;
	}

	/**
	 * One input per variable the owner may set, pre-filled with the palette
	 * they saved, falling back to the theme the board currently shows.
	 *
	 * The inputs are named themeVariables[<name>]; nothing else in the post is
	 * read as a variable, and every value is validated again on save.
	 */
	private function buildThemeVariableRows(themeManager $themeManager): string {
		$base = $themeManager->getThemeVariables($this->paletteBaseTheme($themeManager));
		$values = $this->board->getCustomThemeVariables() + $base + themeManager::DEFAULT_VARIABLES;

		$rows = '';
		foreach (themeManager::VARIABLES as $name => $type) {
			$id = 'themeVar-' . $name;
			$value = $values[$name] ?? '';

			if ($type === 'fontSize') {
				$input = '<input type="number" id="' . $id . '" name="themeVariables[' . $name . ']"'
					. ' min="8" max="24" step="1" value="' . htmlspecialchars((string) (int) $value) . '">';
			} else {
				$input = '<input type="color" id="' . $id . '" name="themeVariables[' . $name . ']"'
					. ' value="' . htmlspecialchars($this->colorPickerValue($value)) . '">'
					. ' <code class="themeVarValue">' . htmlspecialchars($value) . '</code>';
			}

			$rows .= '<tr>';
			$rows .= '<td class="postblock"><label for="' . $id . '">'
				. $this->lang->get('theme.variables.' . $name) . '</label></td>';
			$rows .= '<td>' . $input . '</td>';
			$rows .= '</tr>';
		}

		return $rows;
	}

	/**
	 * The theme a custom palette is layered on: the instance's own default,
	 * which board::applyToConfig() keeps aside before a board overrides it.
	 * Both the settings form and the rendered page start from this, so what an
	 * owner sees in the colour pickers is what visitors get.
	 */
	private function paletteBaseTheme(themeManager $themeManager): string {
		$name = (string) ($this->conf['instanceDefaultTheme'] ?? $this->conf['defaultTheme']);
		return $themeManager->resolveThemeName($name);
	}

	/**
	 * A colour input only accepts #rrggbb, so shorthand and alpha forms are
	 * widened or trimmed for the picker. The stored value is shown beside it.
	 */
	private function colorPickerValue(string $value): string {
		$hex = ltrim($value, '#');

		if (strlen($hex) === 3 || strlen($hex) === 4) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return '#' . substr($hex, 0, 6);
	}

	/**
	 * Instance-wide board management.
	 *
	 * @param board[] $boards
	 */
	public function drawManageBoardsPage(array $boards, boardController $boardController): void {
		$self = htmlspecialchars($this->conf['mainScript']);
		$modUrl = $self . '?request=admin&modPage=manageBoards&modAction=';

		$boardRows = '';
		foreach ($boards as $board) {
			[$usedBytes, $fileCount] = $boardController->getBoardUsage($board);

			$uri = htmlspecialchars($board->getUri());
			$url = htmlspecialchars($board->getUrl($this->conf));

			$boardRows .= '<tr>';
			$boardRows .= '<td class="boardUriCell">[<a href="' . $url . '">' . $uri . '</a>]</td>';
			$boardRows .= '<td>' . htmlspecialchars($board->getTitle()) . '</td>';
			$boardRows .= '<td>' . $fileCount . '</td>';
			$boardRows .= '<td>' . bytesToHumanReadable($usedBytes) . '</td>';
			$boardRows .= '<td><span class="grayText dateColumn">' . htmlspecialchars(date('Y-m-d', $board->getCreatedTime())) . '</span></td>';
			$boardRows .= '<td class="ipCell">' . htmlspecialchars($board->getCreatorIp()) . '</td>';
			$boardRows .= '<td>' . ($board->isListed() ? $this->lang->get('boards.listed') : $this->lang->get('boards.unlisted')) . '</td>';
			$boardRows .= '<td>' . ($board->isLocked() ? $this->lang->get('boards.locked') : $this->lang->get('boards.open')) . '</td>';

			$csrf = '&csrfToken=' . urlencode($this->csrfToken);
			$csrfField = '<input type="hidden" name="csrfToken" value="' . htmlspecialchars($this->csrfToken) . '">';

			// the lock link names what it will do, not the state it is in
			$lockLabel = $board->isLocked() ? $this->lang->get('boards.unlock') : $this->lang->get('boards.lock');

			$boardRows .= '<td class="adminActionsCell">';
			$boardRows .= '[<a href="' . $modUrl . 'toggleListed&boardUri=' . $uri . $csrf . '">' . $this->lang->get('boards.toggleListed') . '</a>] ';
			$boardRows .= '[<a href="' . $modUrl . 'toggleLock&boardUri=' . $uri . $csrf . '">' . $lockLabel . '</a>] ';
			$boardRows .= '[<a href="' . $url . '?request=admin&modPage=manageFiles">' . $this->lang->get('boards.moderate') . '</a>] ';
			$boardRows .= '[<a href="' . $url . '?request=admin&modPage=settings">' . $this->lang->get('boards.boardSettings') . '</a>]';
			$boardRows .= '</td>';

			$boardRows .= '<td><form method="post" action="' . $modUrl . 'resetPassword">';
			$boardRows .= '<input type="hidden" name="boardUri" value="' . $uri . '">' . $csrfField;
			$boardRows .= '<input type="password" name="newPassword" size="10" required>';
			$boardRows .= '<button type="submit">' . $this->lang->get('boards.resetPassword') . '</button>';
			$boardRows .= '</form></td>';

			$boardRows .= '<td><form method="post" action="' . $modUrl . 'deleteBoard" onsubmit="return confirm(\'' . $this->lang->get('boards.confirmDelete') . '\');">';
			$boardRows .= '<input type="hidden" name="boardUri" value="' . $uri . '">' . $csrfField;
			$boardRows .= '<button type="submit">' . $this->lang->get('admin.deleteAction') . '</button>';
			$boardRows .= '</form></td>';

			$boardRows .= '</tr>';
		}

		if ($boardRows === '') {
			$boardRows = '<tr><td colspan="11"><i>' . $this->lang->get('boards.noBoards') . '</i></td></tr>';
		}

		echo $this->renderer->render('admin-manage-boards', [
			'backUrl' => $self . '?request=admin',
			'boardsUrl' => $self . '?request=boards',
			'boardRows' => $boardRows,
		]);
	}
}
