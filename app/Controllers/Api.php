<?php

namespace App\Controllers;

use App\Models\Odjezd;
use App\Models\Zastavka;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Jizdnirady;
use Psr\Log\LoggerInterface;

/**
 * JSON API pro mapu (Tomášova mapa ho volá přes fetch).
 *   GET api/zastavky?s=&w=&n=&e=   zastávky ve výřezu mapy
 *   GET api/odjezdy/{typ}/{slug}   nejbližší odjezdy jedné zastávky
 */
class Api extends BaseController
{
    protected Zastavka $zastavka;
    protected Odjezd $odjezd;
    protected Jizdnirady $config;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        // site_url() pro odkaz na tabuli
        helper('url');

        $this->zastavka = new Zastavka();
        $this->odjezd   = new Odjezd();
        $this->config   = config('Jizdnirady');
    }

    /**
     * Zastávky v obdélníku mapy: s, w, n, e = jih, západ, sever, východ (z map.getBounds()).
     */
    public function zastavky()
    {
        $hranice = [];
        foreach (['s', 'w', 'n', 'e'] as $nazev) {
            $hodnota = $this->request->getGet($nazev);
            if (! is_numeric($hodnota)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'chyba' => 'Chybí parametry s, w, n, e (čísla, hranice výřezu mapy).',
                ]);
            }
            $hranice[$nazev] = (float) $hodnota;
        }

        $zastavky = $this->zastavka->vyrez($hranice['s'], $hranice['w'], $hranice['n'], $hranice['e'], $this->config->mapaLimit);

        $vystup = [];
        foreach ($zastavky as $z) {
            $vystup[] = [
                'typ'   => $z->typ,
                'slug'  => $z->slug,
                'nazev' => $z->nazev,
                'lat'   => (float) $z->lat,
                'lon'   => (float) $z->lon,
            ];
        }

        return $this->response->setJSON($vystup);
    }

    /**
     * Nejbližší odjezdy jedné zastávky do popupu na mapě.
     */
    public function odjezdy(string $typ, string $slug)
    {
        $zastavka = $this->zastavka->najdi($typ, $slug);
        if ($zastavka === null) {
            return $this->response->setStatusCode(404)->setJSON([
                'chyba' => 'Zastávka neexistuje.',
            ]);
        }

        return $this->response->setJSON([
            'zastavka' => [
                'typ'   => $zastavka->typ,
                'slug'  => $zastavka->slug,
                'nazev' => $zastavka->nazev,
                'lat'   => (float) $zastavka->lat,
                'lon'   => (float) $zastavka->lon,
            ],
            'url'     => site_url("tabule/{$zastavka->typ}/{$zastavka->slug}"),
            'odjezdy' => $this->odjezd->nejblizsi((int) $zastavka->id, $this->config->pocetOdjezduMapa),
        ]);
    }
}
