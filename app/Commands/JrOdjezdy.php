<?php

namespace App\Commands;

use App\Models\Odjezd;
use App\Models\Zastavka;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Kontrola dotazů bez webu: php spark jr:odjezdy vlak uherske-hradiste
 * Vypíše nejbližší odjezdy, týdenní počty a jak dlouho dotazy trvaly.
 */
class JrOdjezdy extends BaseCommand
{
    protected $group       = 'Jizdni rady';
    protected $name        = 'jr:odjezdy';
    protected $description = 'Vypíše nejbližší odjezdy a týdenní počty jedné zastávky (test dotazů).';
    protected $usage       = 'jr:odjezdy <typ> <slug> [pocet] [--cas "2026-11-10 23:50"]';
    protected $arguments   = [
        'typ'   => 'bus nebo vlak',
        'slug'  => 'slug zastávky, např. uherske-hradiste',
        'pocet' => 'kolik odjezdů vypsat (výchozí z configu)',
    ];
    protected $options = [
        '--cas' => 'předstírat jiný okamžik "teď" (test spojů přes půlnoc)',
    ];

    public function run(array $params)
    {
        $typ  = $params[0] ?? '';
        $slug = $params[1] ?? '';

        $zastavka = (new Zastavka())->najdi($typ, $slug);
        if ($zastavka === null) {
            CLI::error("Zastávka $typ/$slug neexistuje.");

            return EXIT_ERROR;
        }

        $pocet  = (int) ($params[2] ?? config('Jizdnirady')->pocetOdjezdu);
        $odjezd = new Odjezd();
        $cas    = CLI::getOption('cas');
        $ted    = is_string($cas) ? strtotime($cas) : time();

        CLI::write("{$zastavka->nazev} (id {$zastavka->id}), teď je " . date('j. n. Y H:i:s', $ted), 'yellow');

        $start   = microtime(true);
        $odjezdy = $odjezd->nejblizsi((int) $zastavka->id, $pocet, $ted);
        $msOdjezdy = round((microtime(true) - $start) * 1000);

        $radky = [];
        foreach ($odjezdy as $o) {
            $radky[] = [$o->datum, $o->cas, $o->zaMinut, $o->typ, trim($o->linka . ' ' . $o->cisloSpoje), $o->cil, $o->dopravce];
        }
        CLI::table($radky, ['datum', 'čas', 'za min', 'typ', 'linka', 'směr', 'dopravce']);
        CLI::write("nejblizsi(): $msOdjezdy ms");

        $start = microtime(true);
        $pocty = $odjezd->tydenniPocty((int) $zastavka->id);
        $msPocty = round((microtime(true) - $start) * 1000);

        CLI::table([array_values($pocty)], array_keys($pocty));
        CLI::write("tydenniPocty(): $msPocty ms");

        return EXIT_SUCCESS;
    }
}
