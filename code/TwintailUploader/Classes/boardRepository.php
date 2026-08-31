<?php
namespace TwintailUploader\Classes;

/**
 * Flat-file registry of user boards, stored in data/boards.log.
 *
 * One line per board, fields joined by "<>" in FIELDS order — the same
 * delimited-log convention souko.log uses. Unlike souko.log, boards are
 * appended, so the file reads oldest-first.
 */
class boardRepository {
	public const FIELDS = [
		'id',
		'uri',
		'title',
		'subTitle',
		'ownerPasswordHash',
		'ipSalt',
		'createdTime',
		'creatorIp',
		'listed',
		'locked',
		'commentRequired',
		'defaultComment',
		'prefix',
		'theme',
		'customTheme',
	];

	public function __construct(private string $boardsLogFile) {
		if (!file_exists($this->boardsLogFile)) {
			$dir = dirname($this->boardsLogFile);
			if (!is_dir($dir)) {
				mkdir($dir, 0755, true);
			}
			file_put_contents($this->boardsLogFile, '');
		}
	}

	/**
	 * @return board[]
	 */
	public function getAll(): array {
		$boards = [];

		$fileHandle = fopen($this->boardsLogFile, 'r');
		if (!$fileHandle) {
			return $boards;
		}

		while (($line = fgets($fileHandle)) !== false) {
			if (trim($line) === '') continue;
			$boards[] = new board(explode('<>', rtrim($line, "\r\n")));
		}
		fclose($fileHandle);

		return $boards;
	}

	/**
	 * @return board[] boards that opted into the public listing
	 */
	public function getListed(): array {
		return array_values(array_filter($this->getAll(), fn(board $b) => $b->isListed()));
	}

	public function getByUri(string $uri): ?board {
		foreach ($this->getAll() as $board) {
			if ($board->getUri() === $uri) {
				return $board;
			}
		}
		return null;
	}

	public function uriExists(string $uri): bool {
		return $this->getByUri($uri) !== null;
	}

	public function countBoards(): int {
		return count($this->getAll());
	}

	public function getNextID(): int {
		$highest = 0;
		foreach ($this->getAll() as $board) {
			if ($board->getId() > $highest) {
				$highest = $board->getId();
			}
		}
		return $highest + 1;
	}

	public function add(board $board): bool {
		return $this->withLock(function () use ($board) {
			$boards = $this->getAll();

			// a concurrent creation may have already taken this URI
			foreach ($boards as $stored) {
				if ($stored->getUri() === $board->getUri()) {
					return false;
				}
			}

			$boards[] = $board;
			return $this->writeAll($boards);
		});
	}

	/**
	 * Replaces the stored board that shares this board's URI.
	 */
	public function update(board $board): bool {
		return $this->withLock(function () use ($board) {
			$boards = $this->getAll();
			$found = false;

			foreach ($boards as $index => $stored) {
				if ($stored->getUri() === $board->getUri()) {
					$boards[$index] = $board;
					$found = true;
					break;
				}
			}

			return $found ? $this->writeAll($boards) : false;
		});
	}

	public function delete(string $uri): bool {
		return $this->withLock(function () use ($uri) {
			$boards = $this->getAll();
			$remaining = array_values(array_filter($boards, fn(board $b) => $b->getUri() !== $uri));

			if (count($remaining) === count($boards)) {
				return false;
			}

			return $this->writeAll($remaining);
		});
	}

	/**
	 * Serializes a read-modify-write against the registry so concurrent
	 * mutations can't lose each other's changes. A separate lock file keeps the
	 * whole cycle exclusive, not just the final rewrite.
	 */
	private function withLock(callable $fn) {
		$lock = fopen($this->boardsLogFile . '.lock', 'c');
		if ($lock === false) {
			return $fn(); // best effort if the lock file can't be opened
		}

		flock($lock, LOCK_EX);
		try {
			return $fn();
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/**
	 * Rewrites the whole registry under an exclusive lock.
	 *
	 * @param board[] $boards
	 */
	private function writeAll(array $boards): bool {
		$lines = '';
		foreach ($boards as $board) {
			$fields = array_map([$this, 'sanitizeField'], $board->toArray());
			$lines .= implode('<>', $fields) . "\n";
		}

		$fileHandle = fopen($this->boardsLogFile, 'c+');
		if ($fileHandle === false) {
			return false;
		}

		if (!flock($fileHandle, LOCK_EX)) {
			fclose($fileHandle);
			return false;
		}

		ftruncate($fileHandle, 0);
		rewind($fileHandle);
		$written = fwrite($fileHandle, $lines);

		flock($fileHandle, LOCK_UN);
		fclose($fileHandle);

		return $written !== false;
	}

	/**
	 * Keeps a value from breaking the delimiter or the one-line-per-board rule.
	 */
	private function sanitizeField(string $value): string {
		// Strip control chars first: otherwise "<\r>" outlives the delimiter
		// replacement and re-forms a literal "<>" once the \r is stripped,
		// letting a field value forge extra fields on the row.
		$value = str_replace(["\r\n", "\r", "\n", "\t", "\0"], '', $value);
		return str_replace('<>', '‹›', $value);
	}
}
