<?php
namespace TwintailUploader\Classes;

/**
 * One place uploads live: the main uploader, or a user board.
 *
 * The instance-wide mod pages never chdir() into a board, so a source carries
 * its own config with every path rewritten relative to the repository root —
 * which doubles as the URL the browser needs, the same trick the rest of the
 * app relies on. DATA_DIR is bound to whichever board is being served, so the
 * data directory is carried here rather than read from the constant.
 */
class fileSource {
	public function __construct(
		private string $key,
		private string $label,
		private array $conf,
		private string $dataDir,
		private ?board $board = null
	) {}

	/**
	 * The instance's own uploader. Its key is empty, so a board can never
	 * collide with it — board URIs are always at least one character.
	 */
	public static function main(array $conf): self {
		return new self('', $conf['boardTitle'], $conf, \GLOBAL_DATA_DIR);
	}

	public static function forBoard(array $conf, board $board): self {
		return new self(
			$board->getUri(),
			$board->getTitle(),
			$board->applyToRootConfig($conf),
			\ROOT_DIR . '/' . $board->getDir($conf) . 'data/',
			$board
		);
	}

	public function getKey(): string { return $this->key; }
	public function getLabel(): string { return $this->label; }
	public function getConf(): array { return $this->conf; }
	public function getDataDir(): string { return $this->dataDir; }
	public function getBoard(): ?board { return $this->board; }
	public function isBoard(): bool { return $this->board !== null; }

	/**
	 * Where this source is browsed, relative to the repository root.
	 */
	public function getUrl(array $rootConf): string {
		return $this->board !== null ? $this->board->getUrl($rootConf) : $rootConf['mainScript'];
	}

	public function getLogPath(): string {
		return $this->dataDir . $this->conf['logFile'];
	}

	public function getCounterPath(): string {
		return $this->dataDir . $this->conf['counterFile'];
	}
}
