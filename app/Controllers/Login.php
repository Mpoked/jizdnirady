<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 * Přihlášení a odhlášení přes Ion Auth (ref. 10 §3)
 */
class Login extends BaseController
{
    /**
     * Přihlašovací formulář
     */
    public function login()
    {
        // Přihlášeného rovnou do administrace
        if ($this->ionAuth->loggedIn()) {
            return redirect()->to('administrace');
        }

        $this->data += ['title' => 'Přihlášení'];

        return view('login/login', $this->data);
    }

    /**
     * Zpracování přihlašovacího formuláře
     */
    public function loginFinish()
    {
        $email = $this->request->getPost('email');
        $heslo = $this->request->getPost('heslo');

        $logged = $this->ionAuth->login((string) $email, (string) $heslo);

        $alert = $this->alert->makeMessage($logged, 'login');
        $this->session->setFlashdata('alert', $alert);

        if ($logged) {
            return redirect()->to('administrace');
        }

        return redirect()->to('prihlaseni')->withInput();
    }

    /**
     * Odhlášení
     */
    public function logout()
    {
        $logout = $this->ionAuth->logout();

        $alert = $this->alert->makeMessage($logout, 'logout');
        $this->session->setFlashdata('alert', $alert);

        if ($logout) {
            return redirect()->to('/');
        }

        return redirect()->to('administrace');
    }
}
