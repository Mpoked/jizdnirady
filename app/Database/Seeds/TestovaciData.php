<?php

namespace App\Database\Seeds;

use App\Models\Zastavka;
use CodeIgniter\Database\Seeder;

/**
 * Malá testovací data kolem Uherského Hradiště, aby šlo pracovat bez ostrého importu.
 * Spuštění: php spark db:seed TestovaciData (tabulky vyprázdní a naplní znovu).
 *
 * Provoz se počítá od dnešního data (pondělí aktuálního týdne + 14 dní),
 * takže seeder funguje kdykoli.
 */
class TestovaciData extends Seeder
{
    public function run()
    {
        foreach (['odjezd', 'spoj', 'provoz', 'linka', 'zastavka', 'nastaveni'] as $tabulka) {
            $this->db->table($tabulka)->truncate();
        }

        // ===== zastávky (souřadnice ze skutečných GTFS dat) =====
        $zastavky = [
            1 => ['bus', 'Uherské Hradiště,Mařatice,škola', 49.065049, 17.480657],
            2 => ['bus', 'Uherské Hradiště,aut.nádr.', 49.069926, 17.465051],
            3 => ['bus', 'Uherské Hradiště,žel.st.', 49.067109, 17.457886],
            4 => ['bus', 'Staré Město,nám.Velké Moravy', 49.078695, 17.445780],
            5 => ['bus', 'Staré Město,žel.st.', 49.075908, 17.423137],
            6 => ['vlak', 'Staré Město u Uherského Hradiště', 49.075950, 17.423700],
            7 => ['vlak', 'Uherské Hradiště', 49.069700, 17.464400],
            8 => ['vlak', 'Kunovice', 49.044500, 17.469900],
        ];

        $radky = [];
        foreach ($zastavky as $id => [$typ, $nazev, $lat, $lon]) {
            $radky[] = [
                'id'    => $id,
                'typ'   => $typ,
                'nazev' => $nazev,
                'slug'  => Zastavka::slug($nazev),
                'lat'   => $lat,
                'lon'   => $lon,
            ];
        }
        $this->db->table('zastavka')->insertBatch($radky);

        // ===== linky =====
        $this->db->table('linka')->insertBatch([
            ['id' => 1, 'typ' => 'bus', 'cislo' => '781001', 'nazev' => 'Uherské Hradiště,Mařatice-Staré Město,žel.st.', 'dopravce' => 'ČSAD BUS Uherské Hradiště a.s.', 'route_type' => 3],
            ['id' => 2, 'typ' => 'bus', 'cislo' => '781002', 'nazev' => 'Uherské Hradiště,aut.nádr.-Staré Město,žel.st.', 'dopravce' => 'ČSAD BUS Uherské Hradiště a.s.', 'route_type' => 3],
            ['id' => 3, 'typ' => 'vlak', 'cislo' => 'Os', 'nazev' => 'Staré Město u Uherského Hradiště - Kunovice', 'dopravce' => 'České dráhy, a.s.', 'route_type' => 2],
            ['id' => 4, 'typ' => 'vlak', 'cislo' => 'R', 'nazev' => 'Staré Město u Uherského Hradiště - Kunovice', 'dopravce' => 'České dráhy, a.s.', 'route_type' => 2],
        ]);

        // ===== služby a provoz =====
        // 1 = denně, 2 = jen pracovní dny (Po–Pá), 3 = jen víkend (So–Ne)
        // provoz začíná už v neděli před pondělím, aby v pondělí ráno byly vidět i noční spoje z neděle
        $pondeli = strtotime('monday this week');
        $provoz  = [];
        for ($i = -1; $i < 14; $i++) {
            $den   = strtotime("$i day", $pondeli);
            $datum = date('Y-m-d', $den);
            $vikend = (int) date('N', $den) >= 6;

            $provoz[] = ['sluzba_id' => 1, 'datum' => $datum];
            $provoz[] = ['sluzba_id' => $vikend ? 3 : 2, 'datum' => $datum];
        }
        $this->db->table('provoz')->insertBatch($provoz);

        // ===== spoje a odjezdy =====
        // trasa = [id zastávky => minut od začátku spoje]
        $tam = [1 => 0, 2 => 4, 3 => 6, 4 => 11, 5 => 15];
        $zpet = [5 => 0, 4 => 4, 3 => 9, 2 => 11, 1 => 16];
        $vlakTam = [6 => 0, 7 => 4, 8 => 9];
        $vlakZpet = [8 => 0, 7 => 5, 6 => 9];
        $rychlikTam = [6 => 0, 8 => 7];
        $rychlikZpet = [8 => 0, 6 => 7];

        // [linka, služba, číslo spoje, odjezd z výchozí zastávky 'H:i', trasa]
        $spoje = [];
        $cislo = 1;
        // 781001: denně každé dvě hodiny oběma směry
        for ($h = 6; $h <= 22; $h += 2) {
            $spoje[] = [1, 1, (string) $cislo++, sprintf('%02d:10', $h), $tam];
            $spoje[] = [1, 1, (string) $cislo++, sprintf('%02d:40', $h), $zpet];
        }
        // noční spoj přes půlnoc (poslední zastávky mají čas po 24:00)
        $spoje[] = [1, 1, (string) $cislo++, '23:50', $tam];
        // 781002: jen v pracovní dny
        foreach (['06:00', '07:00', '14:30'] as $cas) {
            $spoje[] = [2, 2, (string) $cislo++, $cas, array_slice($tam, 1, null, true)];
            $spoje[] = [2, 2, (string) $cislo++, $cas, array_slice($zpet, 0, 4, true)];
        }
        // osobní vlaky: v pracovní dny častěji než o víkendu
        foreach (['05:32', '07:32', '13:32', '16:32'] as $i => $cas) {
            $spoje[] = [3, 2, (string) (13101 + 2 * $i), $cas, $vlakTam];
            $spoje[] = [3, 2, (string) (13102 + 2 * $i), $cas, $vlakZpet];
        }
        foreach (['08:32', '17:32'] as $i => $cas) {
            $spoje[] = [3, 3, (string) (13201 + 2 * $i), $cas, $vlakTam];
            $spoje[] = [3, 3, (string) (13202 + 2 * $i), $cas, $vlakZpet];
        }
        // rychlík denně
        $spoje[] = [4, 1, '871', '10:05', $rychlikTam];
        $spoje[] = [4, 1, '872', '18:20', $rychlikZpet];

        $spojRadky   = [];
        $odjezdRadky = [];
        foreach ($spoje as $i => [$linka, $sluzba, $cisloSpoje, $start, $trasa]) {
            $spojId = $i + 1;
            [$h, $m] = explode(':', $start);
            $zacatek = (int) $h * 3600 + (int) $m * 60;

            // konečná zastávka se do odjezdů nedává, jen z ní je směr (stejně jako v importu)
            $konecna = array_key_last($trasa);
            $spojRadky[] = [
                'id'        => $spojId,
                'linka_id'  => $linka,
                'sluzba_id' => $sluzba,
                'cislo'     => $cisloSpoje,
                'cil'       => $zastavky[$konecna][1],
            ];

            foreach ($trasa as $zastavkaId => $minut) {
                if ($zastavkaId === $konecna) {
                    continue;
                }
                $odjezdRadky[] = [
                    'spoj_id'     => $spojId,
                    'zastavka_id' => $zastavkaId,
                    'cas'         => $zacatek + $minut * 60,
                ];
            }
        }
        $this->db->table('spoj')->insertBatch($spojRadky);
        $this->db->table('odjezd')->insertBatch($odjezdRadky);

        // ===== nastaveni (stejné klíče, jaké zapisuje import) =====
        $this->db->table('nastaveni')->insertBatch([
            ['nazev' => 'data_ze_dne', 'hodnota' => date('Y-m-d')],
            ['nazev' => 'okno_od', 'hodnota' => date('Y-m-d', $pondeli)],
            ['nazev' => 'okno_do', 'hodnota' => date('Y-m-d', strtotime('13 day', $pondeli))],
            ['nazev' => 'importovano', 'hodnota' => date('Y-m-d H:i:s')],
            ['nazev' => 'pocet_zastavek', 'hodnota' => (string) count($zastavky)],
            ['nazev' => 'pocet_spoju', 'hodnota' => (string) count($spojRadky)],
            ['nazev' => 'pocet_odjezdu', 'hodnota' => (string) count($odjezdRadky)],
            ['nazev' => 'zdroj', 'hodnota' => 'testovací data (seeder)'],
        ]);
    }
}
