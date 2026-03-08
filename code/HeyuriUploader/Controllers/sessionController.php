<?php
namespace HeyuriUploader\Controllers;

use HeyuriUploader\Classes\session;

class sessionController {
    private $session;

    public function __construct(session $session) {
        $this->session = $session;
    }

    public function isLoggedIn(): bool {
        $modId = $this->session->get('mod_id');
        return ($modId ? true : false);
    }

    public function logIn(): void { //to do
	    $this->session->set('mod_id', 1337);
    }
}