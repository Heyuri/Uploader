<?php

namespace TwintailUploader\Classes;

use TwintailUploader\Controllers\actionLogController;
use TwintailUploader\Controllers\sessionController;

use function TwintailUploader\Functions\redirect;

class loginHandler {
	public function __construct(
		private string $mainScript,
		private string $adminPassword,
		private uploaderHTML $uploaderHTML,
		private ?board $board = null,
		private ?actionLogController $actionLog = null,
	) {}

	private function handleLogin(sessionController $sessionController): void {
		// get entered password
		$password = $_POST['password'] ?? null;

		// on a user board, the owner password gets in as well as the global admin one
		if ($this->board !== null && $password !== null && $password !== ''
			&& password_verify($password, $this->board->getOwnerPasswordHash())) {
			$sessionController->logInBoard($this->board->getUri());

			$this->actionLog?->setActor(actionLogEntry::ACTOR_OWNER);
			$this->actionLog?->record(actionLogEntry::LOGIN, $this->board->getUri());
			return;
		}

		// now check if they match exactly (constant-time; password stays plaintext in config)
		if(is_string($password) && hash_equals($this->adminPassword, $password)) {
			// set the session value
			$sessionController->logIn();

			$this->actionLog?->setActor(actionLogEntry::ACTOR_ADMIN);
			$this->actionLog?->record(actionLogEntry::LOGIN);
		}
		// its incorrect then throw error
		else {
			// a failed attempt is worth more than a successful one — it is the
			// only sign of someone guessing at the password
			$this->actionLog?->record(actionLogEntry::LOGIN_FAILED, $this->board !== null ? $this->board->getUri() : '');

			$lang = $this->uploaderHTML->getLang();
			$this->uploaderHTML->drawErrorPageAndExit($lang->get('errors.invalidPassword'), $lang->get('errors.passwordEnteredIncorrect'));
		}
	}

	public function invoke(): void {
		$session = new session;
		$sessionController = new sessionController($session);

		$isLoggedIn = $sessionController->isLoggedIn()
			|| ($this->board !== null && $sessionController->isBoardOwner($this->board->getUri()));

		// if the user is already logged in - redirect to the admin dashboard
		if($isLoggedIn) {
			redirect($this->mainScript . '?request=admin');
		}
		// handle authentication
		elseif(isset($_POST['password'])) {
			// authenticate
			$this->handleLogin($sessionController);

			// now redirect
			redirect($this->mainScript . '?request=admin');
		}

		// render the form
		$this->uploaderHTML->drawHeader();
		$this->uploaderHTML->drawActionLinks();
		$this->uploaderHTML->drawAdminLoginForm();
		$this->uploaderHTML->drawFooter();
	}
}
