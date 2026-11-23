<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\Nastaveni;
use App\Models\Zastavka;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Administrace - přehled dat a tisk QR kódů (chrání ji filtr auth)
 */
class Administrace extends BaseController
{
    private Zastavka $zastavka;
    private Nastaveni $nastaveni;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->zastavka  = new Zastavka();
        $this->nastaveni = new Nastaveni();
    }

    /**
     * Přehled - čísla připravená importem v tabulce nastaveni (žádné COUNT(*) nad odjezdy)
     */
    public function index()
    {
        $hodnoty = [];
        foreach (['data_ze_dne', 'okno_od', 'okno_do', 'importovano', 'pocet_zastavek', 'pocet_spoju', 'pocet_odjezdu'] as $nazev) {
            $hodnoty[$nazev] = $this->nastaveni->hodnota($nazev);
        }

        $uzivatel = $this->ionAuth->user()->row();

        $this->data += [
            'title'    => 'Administrace',
            'n'        => (object) $hodnoty,
            'uzivatel' => $uzivatel,
            // Po konci období jsou všechny tabule prázdné
            'neplatne' => $hodnoty['okno_do'] !== null && date('Y-m-d') > $hodnoty['okno_do'],
            // Pořád výchozí heslo z ion_auth.sql → upozornit, ať si ho změní
            'vychoziHeslo' => $this->ionAuth->verifyPassword('password', (string) $uzivatel->password),
        ];

        return view('administrace/index', $this->data);
    }

    /**
     * Výběr zastávek pro tisk QR - hledání + stránkování
     */
    public function qr()
    {
        $q = trim((string) $this->request->getGet('q'));

        if ($q !== '') {
            $this->zastavka->like('nazev', $q);
        }

        $this->data += [
            'title'    => 'QR kódy zastávek',
            'q'        => $q,
            'zastavky' => $this->zastavka->orderBy('nazev')->paginate(25),
            'pager'    => $this->zastavka->pager,
        ];

        return view('administrace/qr', $this->data);
    }

    /**
     * Tiskový arch s QR kódy vybraných zastávek (?z[]=vlak/uherske-hradiste&z[]=…)
     */
    public function qrTisk()
    {
        $vybrane  = (array) $this->request->getGet('z');
        $zastavky = [];

        foreach ($vybrane as $polozka) {
            $casti = explode('/', (string) $polozka, 2);
            if (count($casti) !== 2) {
                continue;
            }
            // Neexistující zastávky přeskočíme
            $zastavka = $this->zastavka->najdi($casti[0], $casti[1]);
            if ($zastavka !== null) {
                $zastavky[] = $zastavka;
            }
        }

        $this->data += [
            'title'    => 'Tisk QR kódů',
            'zastavky' => $zastavky,
        ];

        return view('administrace/qr_tisk', $this->data);
    }

    /**
     * Formulář pro změnu hesla přihlášeného uživatele
     */
    public function heslo()
    {
        $this->data += [
            'title'    => 'Změna hesla',
            'minDelka' => config('IonAuth')->minPasswordLength,
        ];

        return view('administrace/heslo', $this->data);
    }

    /**
     * Zpracování formuláře pro změnu hesla (Ion Auth changePassword)
     */
    public function zmenitHeslo()
    {
        $stare     = (string) $this->request->getPost('stare');
        $nove      = (string) $this->request->getPost('nove');
        $potvrzeni = (string) $this->request->getPost('potvrzeni');

        // Ion Auth délku hesla sám nekontroluje (viz minPasswordLength v jeho configu), proto tady
        if (mb_strlen($nove) < config('IonAuth')->minPasswordLength || $nove !== $potvrzeni) {
            $alert = $this->alert->makeMessage(false, 'heslonove');
            $this->session->setFlashdata('alert', $alert);

            return redirect()->to('administrace/heslo');
        }

        // changePassword() ověří současné heslo a uloží nové (zahashované)
        $uzivatel = $this->ionAuth->user()->row();
        $zmeneno  = $this->ionAuth->changePassword($uzivatel->email, $stare, $nove);

        $alert = $this->alert->makeMessage($zmeneno, 'heslo');
        $this->session->setFlashdata('alert', $alert);

        if ($zmeneno) {
            return redirect()->to('administrace');
        }

        return redirect()->to('administrace/heslo');
    }
}
