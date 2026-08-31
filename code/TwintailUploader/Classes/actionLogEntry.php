<?php
namespace TwintailUploader\Classes;

/**
 * One recorded action, backed by one line of data/actions.log.
 *
 * Field order is load-bearing — see actionLogRepository::FIELDS.
 */
class actionLogEntry {
	/* Who did it. An action is only ever attributed to the role that was
	   authenticated at the time, never to a name. */
	public const ACTOR_USER = 'user';
	public const ACTOR_ADMIN = 'admin';
	public const ACTOR_OWNER = 'owner';
	public const ACTOR_SYSTEM = 'system';

	/* What they did. Every key needs a label under actionLog.actions in both
	   language files; an unknown key falls back to the key itself. */
	public const UPLOAD = 'upload';
	public const DELETE_USER = 'deleteUser';
	public const DELETE_MOD = 'deleteMod';
	public const DELETE_OLDEST = 'deleteOldest';
	public const EXPIRE = 'expire';
	public const BAN_IP = 'banIp';
	public const BAN_FILE = 'banFile';
	public const UNBAN_IP = 'unbanIp';
	public const UNBAN_FILE = 'unbanFile';
	public const LOGIN = 'login';
	public const LOGIN_FAILED = 'loginFailed';
	public const LOGOUT = 'logout';
	public const BOARD_CREATED = 'boardCreated';
	public const BOARD_DELETED = 'boardDeleted';
	public const BOARD_LOCKED = 'boardLocked';
	public const BOARD_UNLOCKED = 'boardUnlocked';
	public const BOARD_LISTED = 'boardListed';
	public const BOARD_UNLISTED = 'boardUnlisted';
	public const BOARD_PASSWORD_RESET = 'boardPasswordReset';
	public const BOARD_SETTINGS = 'boardSettings';
	public const CONFIG_SAVED = 'configSaved';

	/** Every action key, in the order the filter on the log page offers them */
	public const ACTIONS = [
		self::UPLOAD,
		self::DELETE_USER,
		self::DELETE_MOD,
		self::DELETE_OLDEST,
		self::EXPIRE,
		self::BAN_IP,
		self::BAN_FILE,
		self::UNBAN_IP,
		self::UNBAN_FILE,
		self::LOGIN,
		self::LOGIN_FAILED,
		self::LOGOUT,
		self::BOARD_CREATED,
		self::BOARD_DELETED,
		self::BOARD_LOCKED,
		self::BOARD_UNLOCKED,
		self::BOARD_LISTED,
		self::BOARD_UNLISTED,
		self::BOARD_PASSWORD_RESET,
		self::BOARD_SETTINGS,
		self::CONFIG_SAVED,
	];

	private string $time = '0';
	private string $scope = '';
	private string $actor = self::ACTOR_USER;
	private string $ip = '';
	private string $action = '';
	private string $target = '';
	private string $details = '';

	public function __construct(array $fields) {
		foreach (actionLogRepository::FIELDS as $index => $property) {
			if (isset($fields[$index])) {
				$this->$property = (string) $fields[$index];
			}
		}
	}

	public function getTime(): int { return (int) $this->time; }

	/** Board URI the action happened on, empty for the main uploader. */
	public function getScope(): string { return $this->scope; }

	public function getActor(): string { return $this->actor; }

	/** Address of whoever acted — empty for anything the app did by itself. */
	public function getIp(): string { return $this->ip; }

	public function getAction(): string { return $this->action; }

	/** What was acted on: a file name, an address, a hash, a board URI. */
	public function getTarget(): string { return $this->target; }

	public function getDetails(): string { return $this->details; }

	/**
	 * Returns the fields in log order, ready to be joined by the delimiter.
	 */
	public function toArray(): array {
		$values = [];
		foreach (actionLogRepository::FIELDS as $property) {
			$values[] = $this->$property;
		}
		return $values;
	}
}
