<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Flash;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;
use Throwable;

final class AuthController
{
    public function loginForm(): string
    {
        if (Auth::check()) {
            Response::redirect('/');
            return '';
        }

        $loginOptions = [];
        $loadError = null;

        try {
            $loginOptions = (new AuthService())->loginOptions();
        } catch (Throwable $exception) {
            Logger::error('Login options could not be loaded', ['message' => $exception->getMessage()]);
            $loadError = 'Die Benutzerliste konnte nicht geladen werden. Bitte Datenbank und Migrationen prüfen.';
        }

        return View::render('auth/login', [
            'title' => 'Anmelden',
            'loginOptions' => $loginOptions,
            'loadError' => $loadError,
        ], 'layouts/auth');
    }

    public function login(): string
    {
        if (!Csrf::validate((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        $personId = Request::intFromPost('person_id');
        $pin = preg_replace('/\D+/', '', (string) Request::post('pin', ''));

        if ($personId === null || $pin === '') {
            Flash::add('error', 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.');
            Response::redirect('/login');
            return '';
        }

        try {
            $result = (new AuthService())->attempt($personId, $pin);
            if (($result['ok'] ?? false) === true) {
                Response::redirect('/');
                return '';
            }

            Flash::add('error', (string) ($result['message'] ?? 'Login fehlgeschlagen. Bitte Zugangsdaten prüfen.'));
        } catch (Throwable $exception) {
            Logger::error('Login failed with technical error', ['message' => $exception->getMessage()]);
            Flash::add('error', 'Login fehlgeschlagen. Bitte später erneut versuchen.');
        }

        Response::redirect('/login');
        return '';
    }

    public function logout(): string
    {
        if (!Csrf::validate((string) Request::post('_csrf', ''))) {
            Response::html(View::render('errors/403', ['permission' => 'csrf']), 403);
            return '';
        }

        (new AuthService())->logout();
        Response::redirect('/login');
        return '';
    }
}
