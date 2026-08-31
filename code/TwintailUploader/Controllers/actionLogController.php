<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\actionLogEntry;
use TwintailUploader\Classes\actionLogRepository;
use TwintailUploader\Classes\board;
use TwintailUploader\Classes\fileSource;
use TwintailUploader\Classes\uploadEntry;

use function TwintailUploader\Functions\getUserIP;

/**
 * Records what happens on an instance: uploads, deletions, bans, logins, board
 * and config changes.
 *
 * One recorder per request, carrying who is acting and which scope they are
 * acting in, so the call sites only name the action. Every board keeps its own
 * data/actions.log — exactly like its upload log and ban lists — and the
 * instance-wide mod page reads them all at once through getInstanceEntries().
 *
 * Actions are recorded with the real address; hiding it from board owners is
 * the *view's* job, the same way the file listings work.
 */
class actionLogController {
	public function __construct(
		private actionLogRepository $repository,
		private bool $enabled = true,
		private string $scope = '',
		private string $actor = actionLogEntry::ACTOR_USER,
		private ?string $ip = null
	) {}

	public function isEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * Names who the rest of this request is acting as — the router calls it once
	 * a mod session has been authenticated.
	 */
	public function setActor(string $actor): void {
		$this->actor = $actor;
	}

	/**
	 * Records one action. $scope overrides the recorder's own scope for actions
	 * that reach into a board from outside it (the recent files and board
	 * management pages both do).
	 */
	public function record(string $action, string $target = '', string $details = '', ?string $scope = null): void {
		$this->write($action, $target, $details, $scope, $this->actor, $this->ip ?? getUserIP());
	}

	/**
	 * Records something the app did by itself — an expiry sweep, a file rotated
	 * out to stay under the limit. Whoever's request it ran on is not the actor,
	 * so their address is left out of it.
	 */
	public function recordSystem(string $action, string $target = '', string $details = '', ?string $scope = null): void {
		$this->write($action, $target, $details, $scope, actionLogEntry::ACTOR_SYSTEM, '');
	}

	/**
	 * Records a batch of system actions in a single write.
	 *
	 * @param array<array{0: string, 1: string}> $rows [target, details] pairs
	 */
	public function recordSystemBatch(string $action, array $rows, ?string $scope = null): void {
		if (!$this->enabled || empty($rows)) {
			return;
		}

		$now = (string) time();
		$entries = [];

		foreach ($rows as $row) {
			$entries[] = new actionLogEntry([
				$now,
				$scope ?? $this->scope,
				actionLogEntry::ACTOR_SYSTEM,
				'',
				$action,
				(string) ($row[0] ?? ''),
				(string) ($row[1] ?? ''),
			]);
		}

		$this->repository->addMany($entries);
	}

	private function write(string $action, string $target, string $details, ?string $scope, string $actor, string $ip): void {
		if (!$this->enabled) {
			return;
		}

		$this->repository->add(new actionLogEntry([
			(string) time(),
			$scope ?? $this->scope,
			$actor,
			$ip,
			$action,
			$target,
			$details,
		]));
	}

	/**
	 * Records an action on an upload, named by the file it stored.
	 */
	public function recordFile(string $action, uploadEntry $entry, array $conf, ?string $scope = null): void {
		if (!$this->enabled) {
			return;
		}

		$this->record($action, $entry->getFileName($conf), $entry->getOriginalFileName(), $scope);
	}

	/**
	 * This scope's own log, newest first.
	 *
	 * @return actionLogEntry[]
	 */
	public function getEntries(): array {
		return $this->repository->getAll();
	}

	/**
	 * Every scope's log merged, newest first, each row remembering where it came
	 * from. Global admin only — a board's page never leaves its own scope.
	 *
	 * @param fileSource[] $sources
	 * @param array $rootConf the instance configuration, never a board's
	 * @return array<array{entry: actionLogEntry, source: fileSource}>
	 */
	public function getInstanceEntries(array $sources, array $rootConf): array {
		$rows = [];

		foreach ($sources as $source) {
			$repository = new actionLogRepository(
				$source->getDataDir() . $rootConf['actionLogFile'],
				(int) $rootConf['actionLogMaxEntries']
			);

			foreach ($repository->getAll() as $entry) {
				$rows[] = ['entry' => $entry, 'source' => $source];
			}
		}

		// each log is already newest first on its own, but the sources interleave
		usort($rows, fn(array $a, array $b) => $b['entry']->getTime() <=> $a['entry']->getTime());

		return $rows;
	}

	/**
	 * A recorder that writes into a board's own log instead of this one.
	 *
	 * The instance-wide mod pages moderate boards without ever chdir()ing into
	 * one, and what happens to a board belongs in that board's history — where
	 * its owner can see it, and where the aggregated page picks it up anyway.
	 * Only ever called from the global context, so $rootConf is the instance
	 * configuration with boardsDir still in it.
	 */
	public function forBoard(board $targetBoard, array $rootConf): self {
		$logPath = \ROOT_DIR . '/' . $targetBoard->getDir($rootConf) . 'data/' . $rootConf['actionLogFile'];

		return new self(
			new actionLogRepository($logPath, (int) $rootConf['actionLogMaxEntries']),
			$this->enabled,
			$targetBoard->getUri(),
			$this->actor,
			$this->ip
		);
	}
}
