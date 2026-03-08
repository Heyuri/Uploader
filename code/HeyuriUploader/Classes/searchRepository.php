<?php

namespace HeyuriUploader\Classes;

use HeyuriUploader\Classes\logFile;

/**
 * Flat-file search repository
 *
 * Reads the same "<>"-delimited log used by HeyuriUploader\Classes\logFile
 * and applies filters against its fields.
 *
 * Supported $searchParameters (all optional):
 * - 'fileExtension' (string) exact match (case-insensitive)
 * - 'comment' (string) substring match (case-insensitive)
 * - 'mimeType' (string) exact match (case-insensitive)
 * - 'originalFileName' (string) substring match (case-insensitive)
 *
 * Return value:
 * - array of associative arrays keyed by the log fields, possibly empty
 * - null if the log file cannot be opened
 */
class searchRepository {
	private $logPath;

	public function __construct(logFile $logFile) {
		$this->logPath = $this->resolveLogPath($logFile);
	}

	public function getSearchResults(array $searchParameters): ?array {
		$params = $this->normalizeParams($searchParameters);

		$fileHandle = @fopen($this->logPath, 'r');
		if ($fileHandle === false) {
			return null;
		}

		$results = [];

		while (($line = fgets($fileHandle)) !== false) {
			if (trim($line) === '') {
				continue;
			}

			$entry = $this->parseLogLine($line);
			if ($entry === null) {
				continue;
			}

			if ($this->entryMatches($entry, $params)) {
				$results[] = $entry;
			}
		}

		fclose($fileHandle);

		// Sort by upload date
		$sortDir = $params['sortDir'];
		usort($results, function ($a, $b) use ($sortDir) {
			$cmp = $a['dateUploaded'] <=> $b['dateUploaded'];
			return $sortDir === 'asc' ? $cmp : -$cmp;
		});

		return $results;
	}

	private function resolveLogPath(logFile $logFile): string {
		$refObj = new \ReflectionObject($logFile);
		if ($refObj->hasProperty('conf')) {
			$prop = $refObj->getProperty('conf');
			$conf = $prop->getValue($logFile);
			if (is_array($conf) && isset($conf['logFile']) && is_string($conf['logFile'])) {
				return \DATA_DIR . $conf['logFile'];
			}
		}
		return '';
	}

	private function normalizeParams(array $in): array {
		$sortDir = isset($in['sortDir']) ? strtolower((string)$in['sortDir']) : 'desc';
		if ($sortDir !== 'asc' && $sortDir !== 'desc') {
			$sortDir = 'desc';
		}

		return [
			'fileExtension' => !empty($in['fileExtension']) ? (string)$in['fileExtension'] : null,
			'comment' => !empty($in['comment']) ? (string)$in['comment'] : null,
			'mimeType' => !empty($in['mimeType']) ? (string)$in['mimeType'] : null,
			'originalFileName' => !empty($in['originalFileName']) ? (string)$in['originalFileName'] : null,
			'sortDir' => $sortDir,
		];
	}

	private function parseLogLine(string $line): ?array {
		$parts = explode('<>', rtrim($line, "\r\n"));

		if (count($parts) !== 9) {
			return null;
		}

		return [
			'id' => (string)$parts[0],
			'fileExtension' => (string)$parts[1],
			'comment' => (string)$parts[2],
			'host' => (string)$parts[3],
			'dateUploaded' => (int)$parts[4],
			'sizeInBytes' => (int)$parts[5],
			'mimeType' => (string)$parts[6],
			'password' => (string)$parts[7],
			'originalFileName' => (string)$parts[8],
		];
	}

	private function entryMatches(array $e, array $p): bool {
		// If all parameters are null, return false (no results)
		if (
			$p['fileExtension'] === null &&
			$p['mimeType'] === null &&
			$p['comment'] === null &&
			$p['originalFileName'] === null
		) {
			return false;
		}
		// Exact matches (case-insensitive)
		if ($p['fileExtension'] !== null && strcasecmp($e['fileExtension'], $p['fileExtension']) !== 0) {
			return false;
		}
		if ($p['mimeType'] !== null && strcasecmp($e['mimeType'], $p['mimeType']) !== 0) {
			return false;
		}

		// Substring matches (case-insensitive)
		if ($p['comment'] !== null && stripos($e['comment'], $p['comment']) === false) {
			return false;
		}
		if ($p['originalFileName'] !== null && stripos($e['originalFileName'], $p['originalFileName']) === false) {
			return false;
		}

		return true;
	}
}
