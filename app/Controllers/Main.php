<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\Odjezd;
use App\Models\Zastavka;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

class Main extends BaseController
{
    // Obálka pro view; alert do ní dává BaseController (Matěj)
    protected array $data = [];

    private Zastavka $zastavka;
    private Odjezd $odjezd;
    private \Config\Jizdnirady $config;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->zastavka = new Zastavka();
        $this->odjezd   = new Odjezd();
        $this->config   = config('Jizdnirady');

        // Dokud BaseController neplní alert, nastavíme prázdný
        $this->data['alert'] ??= (object) ['message' => '', 'class' => ''];

        helper(['form', 'url']);
    }

    /**
     * Úvodní stránka - hledání zastávky se stránkováním
     */
    public function index()
    {
        $q = trim((string) $this->request->getGet('q'));

        // Bez hledaného výrazu vypíšeme všechny zastávky
        if ($q !== '') {
            $this->zastavka->like('nazev', $q);
        }

        $this->data += [
            'q'        => $q,
            'zastavky' => $this->zastavka->orderBy('nazev')->paginate(25),
            'pager'    => $this->zastavka->pager,
        ];

        return view('index', $this->data);
    }

    /**
     * Odjezdová tabule zastávky + týdenní graf
     *
     * @param string $typ  bus | vlak
     * @param string $slug slug názvu zastávky
     */
    public function tabule($typ, $slug)
    {
        if (! in_array($typ, ['bus', 'vlak'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $zastavka = $this->zastavka->najdi($typ, $slug);
        if ($zastavka === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $odjezdy = $this->odjezd->nejblizsi($zastavka->id, $this->config->pocetOdjezdu);
        $pocty   = $this->odjezd->tydenniPocty($zastavka->id);

        // Popisky grafu ve tvaru „Po 2. 11.“
        $dny     = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
        $popisky = [];
        foreach (array_keys($pocty) as $i => $datum) {
            $popisky[] = $dny[$i] . ' ' . date('j. n.', strtotime($datum));
        }

        $this->data += [
            'title'      => $zastavka->nazev,
            'zastavka'   => $zastavka,
            'odjezdy'    => $odjezdy,
            'dnes'       => date('Y-m-d'),
            'platiDo'    => $this->config->oknoDo,
            'popisky'    => $popisky,
            'hodnoty'    => array_values($pocty),
            'dnesniDen'  => (int) date('N') - 1, // index dnešního sloupce (0 = pondělí)
        ];

        return view('tabule', $this->data);
    }
}
