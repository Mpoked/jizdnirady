<?php

namespace App\Filters;

use App\Libraries\Alert;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use IonAuth\Libraries\IonAuth;

/**
 * Pustí do administrace jen přihlášeného uživatele (ref. 10 §3.4)
 */
class LoginFilter implements FilterInterface
{
    private $ionAuth;
    private $session;
    private $alert;

    public function before(RequestInterface $request, $arguments = null)
    {
        // Filtr není potomek BaseControlleru, knihovny si načte sám
        $this->ionAuth = new IonAuth();
        $this->session = \Config\Services::session();
        $this->alert   = new Alert();

        if (! $this->ionAuth->loggedIn()) {
            $alert = $this->alert->makeMessage(false, 'filter');
            $this->session->setFlashdata('alert', $alert);

            return redirect()->to('prihlaseni');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
