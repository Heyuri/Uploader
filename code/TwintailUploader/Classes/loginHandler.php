<?php

namespace TwintailUploader\Classes;

use TwintailUploader\Controllers\sessionController;

use function TwintailUploader\Functions\redirect;

class loginHandler {
	public function __construct(
		private string $mainScript,
		private string $adminPassword,
		private uploaderHTML $uploaderHTML,
	) {}

	private function handleLogin(sessionController $sessionController): void {
		// get entered password
		$password = $_POST['password'] ?? null;

		// get the admin password
		$adminPassword = $this->adminPassword;

		// now check if they match exactly
		if($password === $adminPassword) {
			// set the session value
			$sessionController->logIn();
		}
		// its incorrect then throw error
		else {
			$lang = $this->uploaderHTML->getLang();
			$this->uploaderHTML->drawErrorPageAndExit($lang->get('errors.invalidPassword'), $lang->get('errors.passwordEnteredIncorrect'));
		}
	}

	public function invoke(): void {
		$session = new session;
		$sessionController = new sessionController($session);

		$isLoggedIn = $sessionController->isLoggedIn();

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