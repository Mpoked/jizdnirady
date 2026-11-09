<?php

namespace App\Controllers;

use App\Libraries\Alert;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use IonAuth\Libraries\IonAuth;
use Psr\Log\LoggerInterface;

/**
 * Rodič všech controllerů (ref. 11): session, Ion Auth, alerty a obálka $data pro view.
 */
abstract class BaseController extends Controller
{
    protected $session;
    protected $ionAuth;
    protected $alert;

    // Obálka pro view, vždy obsahuje 'alert'
    protected array $data = [];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Helpery musí být nastavené před parent::initController()
        $this->helpers = ['form', 'url', 'text'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        $this->session = \Config\Services::session();
        $this->ionAuth = new IonAuth();
        $this->alert   = new Alert();

        // Prázdný alert, případně alert z flashdat (po přesměrování)
        $this->data['alert'] = $this->alert->empty();
        if (isset($this->session->alert)) {
            $this->data['alert'] = $this->session->alert;
        }

        // Navbar ukazuje „Odhlásit“ jen přihlášenému
        $this->data['prihlasen'] = $this->ionAuth->loggedIn();
    }
}
