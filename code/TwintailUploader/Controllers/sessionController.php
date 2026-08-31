<?php
namespace TwintailUploader\Controllers;

use TwintailUploader\Classes\session;

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
	    $this->session->regenerate();
	    $this->session->set('mod_id', 1337);
    }

    /**
     * Board owner sessions are tracked per board URI, so being the owner of
     * one board grants nothing anywhere else.
     */
    public function logInBoard(string $boardUri): void {
        $boards = $this->session->get('board_owner') ?? [];
        if (!in_array($boardUri, $boards, true)) {
            $this->session->regenerate();
            $boards[] = $boardUri;
        }
        $this->session->set('board_owner', $boards);
    }

    /**
     * Per-session CSRF token, generated lazily. Same value for the life of the
     * session, so a rendered form and its later submission agree.
     */
    public function getCsrfToken(): string {
        $token = $this->session->get('csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set('csrf_token', $token);
        }
        return $token;
    }

    public function verifyCsrfToken(?string $token): bool {
        $expected = $this->session->get('csrf_token');
        return is_string($expected) && $expected !== ''
            && is_string($token) && hash_equals($expected, $token);
    }

    public function isBoardOwner(string $boardUri): bool {
        $boards = $this->session->get('board_owner') ?? [];
        return in_array($boardUri, $boards, true);
    }

    public function logOutBoard(string $boardUri): void {
        $boards = $this->session->get('board_owner') ?? [];
        $this->session->set('board_owner', array_values(array_filter($boards, fn($uri) => $uri !== $boardUri)));
    }
}
