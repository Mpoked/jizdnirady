<?php

namespace App\Libraries;

use App\Models\Zastavka;
use Config\Jizdnirady;
use RuntimeException;

/**
 * Import jízdních řádů z GTFS zipů (data.jr.ggu.cz) do databáze.
 *
 * Pouští se jednou přes `php spark jr:import`. Tabulky vždy vyprázdní a naplní znovu,
 * takže když import spadne, stačí chybu opravit a pustit ho znovu.
 * Zipy se nerozbalují, CSV se čtou přímo ze zipu přes zip://.
 */
class GtfsImport
{
    protected $db;
    protected Jizdnirady $config;

    /** @var callable(string): void vypisuje průběh (command posílá CLI::write) */
    protected $vypis;

    // čítače id, pokračují přes oba feedy
    protected int $posledniZastavka = 0;
    protected int $posledniLinka    = 0;
    protected int $posledniSpoj     = 0;
    protected int $posledniSluzba   = 0;

    // počty do tabulky nastaveni
    protected int $pocetZastavek = 0;
    protected int $pocetSpoju    = 0;
    protected int $pocetOdjezdu  = 0;

    // dny importovaného období: 'Ymd' => pořadí dne (bit v masce), pořadí => 'Y-m-d'
    protected array $denIndex = [];
    protected array $dnyOkna  = [];
    // den v týdnu pro každý den období (0 = pondělí)
    protected array $denTydne = [];

    // maska dnů v období => sluzba_id (jedinečné vzory dnů, společné pro oba feedy)
    protected array $vzory = [];

    // stejně pojmenované zastávky dál od sebe než tolik km jsou dvě různé zastávky (např. dvě „Vlachovice“)
    protected float $maxVzdalenostKm = 2.0;

    // řádky čekající na insertBatch
    protected array $davkaOdjezdu = [];

    // otisky už zpracovaných spojů: některé linky jsou v datech ve více verzích
    // se stejnými spoji (např. 000293 jako -CISR-000293-1 až -4), na tabuli by byly 2–4×
    protected array $otiskySpoju = [];
    protected int $duplicit = 0;

    public function __construct(?callable $vypis = null)
    {
        $this->db     = db_connect();
        $this->config = config('Jizdnirady');
        $this->vypis  = $vypis ?? static function (string $text): void {};

        $this->pripravOkno();
    }

    /**
     * Celý import. $typy = ['vlak', 'bus'] nebo jen jeden z nich (na testování).
     * Vrací počty, které zapíše i do tabulky nastaveni.
     */
    public function spust(array $typy): array
    {
        $zacatek = microtime(true);

        // 0. vyprázdnit tabulky
        foreach (['odjezd', 'spoj', 'provoz', 'linka', 'zastavka', 'nastaveni'] as $tabulka) {
            $this->db->table($tabulka)->truncate();
        }
        $this->vypis('Tabulky vyprázdněny.');

        foreach ($typy as $typ) {
            $this->importujFeed($typ, $this->config->zdroje[$typ]);
        }

        $pocty = [
            'pocet_zastavek' => $this->pocetZastavek,
            'pocet_spoju'    => $this->pocetSpoju,
            'pocet_odjezdu'  => $this->pocetOdjezdu,
        ];

        $nastaveni = [
            'data_ze_dne' => $this->config->dataZeDne,
            'okno_od'     => $this->config->oknoOd,
            'okno_do'     => $this->config->oknoDo,
            'importovano' => date('Y-m-d H:i:s'),
            'zdroj'       => 'data.jr.ggu.cz (' . implode(', ', $typy) . ')',
        ] + $pocty;

        $radky = [];
        foreach ($nastaveni as $nazev => $hodnota) {
            $radky[] = ['nazev' => $nazev, 'hodnota' => (string) $hodnota];
        }
        $this->db->table('nastaveni')->insertBatch($radky);

        $this->vypis(sprintf('Hotovo za %.1f min.', (microtime(true) - $zacatek) / 60));

        return $pocty;
    }

    /**
     * Import jednoho feedu (vlak nebo bus).
     */
    protected function importujFeed(string $typ, string $url): void
    {
        $this->vypis("=== $typ ===");
        $zip = $this->stahni($typ, $url);

        $stopZastavka  = $this->nactiZastavky($zip, $typ);
        $sluzbaGtfs    = $this->nactiSluzby($zip);
        $linkaGtfs     = $this->nactiLinky($zip, $typ);
        $spoje         = $this->nactiSpoje($zip, $typ, $sluzbaGtfs, $linkaGtfs);
        unset($sluzbaGtfs, $linkaGtfs);

        $this->nactiOdjezdy($zip, $stopZastavka, $spoje);
    }

    /**
     * 1. Stáhne zip do writable/gtfs/{typ}.zip, jen když tam ještě není.
     */
    protected function stahni(string $typ, string $url): string
    {
        $slozka = WRITEPATH . 'gtfs';
        $cesta  = $slozka . DIRECTORY_SEPARATOR . $typ . '.zip';

        if (is_file($cesta)) {
            $this->vypis(sprintf('Zip %s už je stažený (%.0f MB).', $cesta, filesize($cesta) / 1048576));

            return $cesta;
        }

        if (! is_dir($slozka)) {
            mkdir($slozka, 0775, true);
        }

        $this->vypis("Stahuji $url …");
        $odpoved = service('curlrequest')->get($url, ['timeout' => 600]);

        if ($odpoved->getStatusCode() !== 200) {
            throw new RuntimeException("Stažení $url se nepovedlo (HTTP {$odpoved->getStatusCode()}).");
        }

        // nejdřív do .part, aby se nedokončené stažení nepovažovalo za hotový zip
        file_put_contents($cesta . '.part', $odpoved->getBody());
        rename($cesta . '.part', $cesta);
        unset($odpoved);

        $this->vypis(sprintf('Staženo %.0f MB.', filesize($cesta) / 1048576));

        return $cesta;
    }

    /**
     * 3. stops.txt → zastávky. Vrací pole GTFS stop_id => zastavka_id.
     *
     * Zastávky se spojují podle slugu z názvu (víc stop_id se stejným názvem = jedna zastávka,
     * „Dýšina,náves“ a „Dýšina, náves“ taky). Stejně pojmenované zastávky daleko od sebe
     * (různé vesnice) zůstanou zvlášť a druhá dostane slug s číslem, např. vlachovice-2.
     * Nástupiště vlaků (parent_station) patří ke své stanici.
     */
    protected function nactiZastavky(string $zip, string $typ): array
    {
        [$f, $s] = $this->otevri($zip, 'stops.txt');

        $radky = [];
        while (($r = $this->radek($f)) !== null) {
            $lokace = $r[$s['location_type'] ?? -1] ?? '';
            // vchody, uzly a místa na nástupišti nás nezajímají
            if ($lokace !== '' && $lokace !== '0' && $lokace !== '1') {
                continue;
            }
            $radky[] = [
                $r[$s['stop_id']],
                trim($r[$s['stop_name']]),
                (float) $r[$s['stop_lat']],
                (float) $r[$s['stop_lon']],
                $r[$s['parent_station'] ?? -1] ?? '',
            ];
        }
        fclose($f);

        $jeStopId = array_flip(array_column($radky, 0));

        // shluky: základní slug => seznam shluků [název, součet lat, součet lon, počet, první lat, první lon]
        $shluky   = [];
        $stopShluk = []; // stop_id => [slug, pořadí shluku]
        $deti     = []; // stop_id => parent stop_id

        foreach ($radky as [$stopId, $nazev, $lat, $lon, $rodic]) {
            if ($rodic !== '' && isset($jeStopId[$rodic])) {
                $deti[$stopId] = $rodic;

                continue;
            }

            $slug = Zastavka::slug($nazev);
            if ($slug === '') {
                $slug = 'zastavka';
            }
            $platne = $this->platneSouradnice($lat, $lon);

            // najít shluk se stejným slugem, který je blízko
            $kam = null;
            foreach ($shluky[$slug] ?? [] as $i => $sh) {
                if (! $platne || $this->vzdalenostKm($lat, $lon, $sh[4], $sh[5]) <= $this->maxVzdalenostKm) {
                    $kam = $i;
                    break;
                }
            }

            if ($kam === null) {
                $shluky[$slug][] = [$nazev, 0.0, 0.0, 0, $lat, $lon];
                $kam = array_key_last($shluky[$slug]);
            }
            if ($platne) {
                $shluky[$slug][$kam][1] += $lat;
                $shluky[$slug][$kam][2] += $lon;
                $shluky[$slug][$kam][3]++;
                // první platné souřadnice určují, co je „blízko“
                if (! $this->platneSouradnice($shluky[$slug][$kam][4], $shluky[$slug][$kam][5])) {
                    $shluky[$slug][$kam][4] = $lat;
                    $shluky[$slug][$kam][5] = $lon;
                }
            }
            $stopShluk[$stopId] = [$slug, $kam];
        }

        // přidělit id a konečné slugy (druhý shluk se stejným slugem dostane -2 atd.)
        $pouzite = array_fill_keys(array_keys($shluky), true);
        $idShluku = [];
        $zapis    = [];

        foreach ($shluky as $slug => $seznam) {
            foreach ($seznam as $i => [$nazev, $sumLat, $sumLon, $pocet, $lat, $lon]) {
                $konecny = $slug;
                if ($i > 0) {
                    $n = $i + 1;
                    while (isset($pouzite["$slug-$n"])) {
                        $n++;
                    }
                    $konecny = "$slug-$n";
                    $pouzite[$konecny] = true;
                }

                $id = ++$this->posledniZastavka;
                $idShluku[$slug][$i] = $id;
                $zapis[] = [
                    'id'    => $id,
                    'typ'   => $typ,
                    'nazev' => mb_substr($nazev, 0, 255),
                    'slug'  => substr($konecny, 0, 255),
                    'lat'   => round($pocet > 0 ? $sumLat / $pocet : $lat, 6),
                    'lon'   => round($pocet > 0 ? $sumLon / $pocet : $lon, 6),
                ];
            }
        }

        foreach (array_chunk($zapis, $this->config->davka) as $davka) {
            $this->db->table('zastavka')->insertBatch($davka, null, $this->config->davka);
        }
        $this->pocetZastavek += count($zapis);

        // stop_id => zastavka_id (nástupiště podle své stanice)
        $mapa = [];
        foreach ($stopShluk as $stopId => [$slug, $i]) {
            $mapa[$stopId] = $idShluku[$slug][$i];
        }
        foreach ($deti as $stopId => $rodic) {
            // kdyby měl rodič zase rodiče, vylézt až nahoru (max. 5 úrovní, ať se nezacyklí)
            for ($n = 0; isset($deti[$rodic]) && $n < 5; $n++) {
                $rodic = $deti[$rodic];
            }
            if (isset($mapa[$rodic])) {
                $mapa[$stopId] = $mapa[$rodic];
            }
        }

        $this->vypis(sprintf('Zastávky: %d řádků stops.txt → %d zastávek.', count($radky), count($zapis)));

        return $mapa;
    }

    /**
     * 4. calendar.txt + calendar_dates.txt → služby.
     * Vrací pole GTFS service_id => sluzba_id, jen pro služby, které v období jedou.
     */
    protected function nactiSluzby(string $zip): array
    {
        // maska dnů pro každou GTFS službu (bit i = i-tý den období)
        $maska = [];

        // calendar.txt (vlaky ho nemají)
        $soubor = $this->otevri($zip, 'calendar.txt', false);
        if ($soubor !== null) {
            [$f, $s] = $soubor;
            $dnyTydne = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            while (($r = $this->radek($f)) !== null) {
                $od = $r[$s['start_date']];
                $do = $r[$s['end_date']];
                $m  = 0;
                foreach ($this->denIndex as $den => $i) {
                    if ($den >= $od && $den <= $do && $r[$s[$dnyTydne[$this->denTydne[$i]]]] === '1') {
                        $m |= 1 << $i;
                    }
                }
                $maska[$r[$s['service_id']]] = $m;
            }
            fclose($f);
        }

        // calendar_dates.txt: 1 = jede navíc, 2 = nejede
        [$f, $s] = $this->otevri($zip, 'calendar_dates.txt');
        $iSluzba = $s['service_id'];
        $iDatum  = $s['date'];
        $iTyp    = $s['exception_type'];

        while (($r = $this->radek($f)) !== null) {
            $i = $this->denIndex[$r[$iDatum]] ?? null;
            if ($i === null) {
                continue; // mimo období (většina řádků)
            }
            $sluzba = $r[$iSluzba];
            if ($r[$iTyp] === '1') {
                $maska[$sluzba] = ($maska[$sluzba] ?? 0) | (1 << $i);
            } else {
                $maska[$sluzba] = ($maska[$sluzba] ?? 0) & ~(1 << $i);
            }
        }
        fclose($f);

        // stejné vzory dnů = jedna naše služba
        $sluzbaGtfs = [];
        $provoz     = [];
        $novych     = 0;

        foreach ($maska as $sluzba => $m) {
            if ($m === 0) {
                continue; // v období nejede
            }
            if (! isset($this->vzory[$m])) {
                $this->vzory[$m] = ++$this->posledniSluzba;
                $novych++;
                foreach ($this->dnyOkna as $i => $datum) {
                    if ($m & (1 << $i)) {
                        $provoz[] = ['sluzba_id' => $this->vzory[$m], 'datum' => $datum];
                    }
                }
            }
            $sluzbaGtfs[$sluzba] = $this->vzory[$m];
        }

        foreach (array_chunk($provoz, $this->config->davka) as $davka) {
            $this->db->table('provoz')->insertBatch($davka, null, $this->config->davka);
        }

        $this->vypis(sprintf(
            'Služby: %d GTFS služeb, %d jede v období, %d nových vzorů dnů (%d řádků provoz).',
            count($maska),
            count($sluzbaGtfs),
            $novych,
            count($provoz),
        ));

        return $sluzbaGtfs;
    }

    /**
     * 5. agency.txt + routes.txt → linky. Vrací pole GTFS route_id => [linka_id, číslo vlaku, číslo linky].
     *
     * U vlaků je v route_short_name kategorie i číslo vlaku ('Os 24140'): do linky jde
     * kategorie 'Os', číslo vlaku si vezme spoj. U autobusů je číslo linky celé ('345063').
     */
    protected function nactiLinky(string $zip, string $typ): array
    {
        [$f, $s] = $this->otevri($zip, 'agency.txt');
        $dopravci = [];
        while (($r = $this->radek($f)) !== null) {
            $dopravci[$r[$s['agency_id'] ?? -1] ?? ''] = trim($r[$s['agency_name']]);
        }
        fclose($f);
        // když je dopravce jen jeden, routes.txt nemusí agency_id vyplňovat
        $jedinyDopravce = count($dopravci) === 1 ? reset($dopravci) : '';

        [$f, $s] = $this->otevri($zip, 'routes.txt');
        $linky = [];
        $zapis = [];
        while (($r = $this->radek($f)) !== null) {
            $id     = ++$this->posledniLinka;
            $cislo  = trim($r[$s['route_short_name']] ?? '');
            $nazev  = trim($r[$s['route_long_name']] ?? '');
            $agency = $r[$s['agency_id'] ?? -1] ?? '';

            $cisloVlaku = '';
            if ($typ === 'vlak' && preg_match('/^(\S+)\s+(\S.*)$/u', $cislo, $m)) {
                [, $cislo, $cisloVlaku] = $m;
            }

            $linky[$r[$s['route_id']]] = [$id, $cisloVlaku, $cislo];
            $zapis[] = [
                'id'         => $id,
                'typ'        => $typ,
                'cislo'      => mb_substr($cislo, 0, 50),
                'nazev'      => mb_substr($nazev, 0, 255),
                'dopravce'   => mb_substr($dopravci[$agency] ?? $jedinyDopravce, 0, 255),
                'route_type' => (int) $r[$s['route_type']],
            ];
        }
        fclose($f);

        foreach (array_chunk($zapis, $this->config->davka) as $davka) {
            $this->db->table('linka')->insertBatch($davka, null, $this->config->davka);
        }

        $this->vypis(sprintf('Linky: %d.', count($zapis)));

        return $linky;
    }

    /**
     * 6. trips.txt → spoje, jen ty, které v období jedou.
     * Data spojů jsou v plochých polích indexovaných id spoje (pole polí by snědlo moc paměti).
     */
    protected function nactiSpoje(string $zip, string $typ, array $sluzbaGtfs, array $linkaGtfs): array
    {
        [$f, $s] = $this->otevri($zip, 'trips.txt');
        $iTrip     = $s['trip_id'];
        $iSluzba   = $s['service_id'];
        $iLinka    = $s['route_id'];
        $iHeadsign = $s['trip_headsign'] ?? null;
        $iCislo    = $s['trip_short_name'] ?? null;

        $spoje = [
            'id'     => [], // trip_id => spoj_id
            'linka'  => [], // spoj_id => linka_id
            'sluzba' => [], // spoj_id => sluzba_id
            'cislo'  => [], // spoj_id => číslo spoje / vlaku
            'cil'    => [], // spoj_id => směr (prázdný se doplní z konečné)
            'klic'   => [], // spoj_id => číslo linky|číslo spoje|služba (k hledání duplicit)
        ];
        $celkem = 0;

        while (($r = $this->radek($f)) !== null) {
            $celkem++;
            $sluzba = $sluzbaGtfs[$r[$iSluzba]] ?? null;
            $linka  = $linkaGtfs[$r[$iLinka]] ?? null;
            if ($sluzba === null || $linka === null) {
                continue; // v období nejede
            }

            $id = ++$this->posledniSpoj;
            $spoje['id'][$r[$iTrip]] = $id;
            $spoje['linka'][$id]     = $linka[0];
            $spoje['sluzba'][$id]    = $sluzba;
            // číslo vlaku (u autobusů prázdné, cestující znají jen číslo linky)
            $spoje['cislo'][$id] = $typ === 'vlak'
                ? (trim($iCislo === null ? '' : $r[$iCislo]) ?: $linka[1])
                : '';
            $spoje['cil'][$id]       = $iHeadsign === null ? '' : trim($r[$iHeadsign]);
            $spoje['klic'][$id]      = $linka[2] . '|' . $spoje['cislo'][$id] . '|' . $sluzba;
        }
        fclose($f);

        $this->vypis(sprintf('Spoje: %d v trips.txt, %d jede v období.', $celkem, count($spoje['id'])));

        return $spoje;
    }

    /**
     * 7. stop_times.txt → odjezdy, 8. pak spoje (až je známý cíl).
     * Soubor je setříděný podle trip_id, takže se řádky jednoho spoje bufferují
     * a zpracují, když se trip_id změní.
     */
    protected function nactiOdjezdy(string $zip, array $stopZastavka, array $spoje): void
    {
        $zacatek = microtime(true);

        // názvy zastávek kvůli cíli spoje, když chybí trip_headsign
        $nazvy = array_column(
            $this->db->table('zastavka')->select('id, nazev')->get()->getResultArray(),
            'nazev',
            'id',
        );

        [$f, $s] = $this->otevri($zip, 'stop_times.txt');
        $iTrip   = $s['trip_id'];
        $iPoradi = $s['stop_sequence'];
        $iStop   = $s['stop_id'];
        $iOdjezd = $s['departure_time'];
        $iPrijezd = $s['arrival_time'];
        $iNastup = $s['pickup_type'] ?? null;

        $odjezduPred = $this->pocetOdjezdu;
        $maOdjezd    = []; // spoj_id => true, když má aspoň jeden odjezd
        $hotove      = []; // spoj_id => true, kvůli kontrole setřídění
        $nesetridenych = 0;
        $radku   = 0;
        $trip    = null;
        $buffer  = [];

        while (true) {
            $r = $this->radek($f);

            if ($r === null || $r[$iTrip] !== $trip) {
                // konec spoje: zpracovat buffer
                if ($buffer !== [] && isset($spoje['id'][$trip])) {
                    $spojId = $spoje['id'][$trip];
                    if (isset($hotove[$spojId])) {
                        $nesetridenych++;
                    }
                    $hotove[$spojId] = true;

                    if ($this->zpracujSpoj($spojId, $buffer, $stopZastavka, $spoje, $nazvy)) {
                        $maOdjezd[$spojId] = true;
                    }
                }
                if ($r === null) {
                    break;
                }
                $trip   = $r[$iTrip];
                $buffer = [];
            }

            $radku++;
            if ($radku % 2000000 === 0) {
                $this->vypis(sprintf('  stop_times: %.0f mil. řádků, %.1f min', $radku / 1e6, (microtime(true) - $zacatek) / 60));
            }

            // spoje mimo období se ani nebufferují
            if (! isset($spoje['id'][$trip])) {
                continue;
            }

            $buffer[] = [
                (int) $r[$iPoradi],
                $r[$iStop],
                $r[$iOdjezd] !== '' ? $r[$iOdjezd] : $r[$iPrijezd],
                $iNastup === null ? '' : $r[$iNastup],
            ];
        }
        fclose($f);
        $this->ulozOdjezdy();

        if ($nesetridenych > 0) {
            log_message('warning', "stop_times.txt není setříděný podle trip_id ($nesetridenych spojů na víc místech).");
            $this->vypis("  Pozor: $nesetridenych spojů je ve stop_times.txt na víc místech.");
        }

        // 8. spoje až teď, kdy je známý cíl; jen ty, které mají aspoň jeden odjezd
        $zapis = [];
        foreach ($maOdjezd as $spojId => $ano) {
            $zapis[] = [
                'id'        => $spojId,
                'linka_id'  => $spoje['linka'][$spojId],
                'sluzba_id' => $spoje['sluzba'][$spojId],
                'cislo'     => mb_substr($spoje['cislo'][$spojId], 0, 50),
                'cil'       => mb_substr($spoje['cil'][$spojId], 0, 255),
            ];
            if (count($zapis) >= $this->config->davka) {
                $this->db->table('spoj')->insertBatch($zapis, null, $this->config->davka);
                $zapis = [];
            }
        }
        if ($zapis !== []) {
            $this->db->table('spoj')->insertBatch($zapis, null, $this->config->davka);
        }
        $this->pocetSpoju += count($maOdjezd);

        $this->vypis(sprintf(
            'Odjezdy: %d řádků stop_times.txt → %d odjezdů, %d spojů, %d duplicitních spojů vynecháno (%.1f min).',
            $radku,
            $this->pocetOdjezdu - $odjezduPred,
            count($maOdjezd),
            $this->duplicit,
            (microtime(true) - $zacatek) / 60,
        ));

        // otisky jsou jen v rámci jednoho feedu
        $this->otiskySpoju = [];
        $this->duplicit    = 0;
    }

    /**
     * Zpracuje zastávky jednoho spoje. Vrací true, když spoj má aspoň jeden odjezd.
     *
     * @param list<array{int, string, string, string}> $buffer [pořadí, stop_id, čas, pickup_type]
     */
    protected function zpracujSpoj(int $spojId, array $buffer, array $stopZastavka, array &$spoje, array $nazvy): bool
    {
        // obvykle už jsou seřazené podle stop_sequence
        usort($buffer, static fn ($a, $b) => $a[0] <=> $b[0]);

        // konečná se do odjezdů nedává, jen z ní je směr, když chybí trip_headsign
        $konecna = array_pop($buffer);
        if ($spoje['cil'][$spojId] === '') {
            $spoje['cil'][$spojId] = $nazvy[$stopZastavka[$konecna[1]] ?? 0] ?? '';
        }

        $odjezdy = [];
        $otisk   = $spoje['klic'][$spojId] . '|' . $spoje['cil'][$spojId];
        foreach ($buffer as [$poradi, $stopId, $cas, $nastup]) {
            if ($nastup === '1' || $cas === '' || ! isset($stopZastavka[$stopId])) {
                continue; // nelze nastoupit / bez času / neznámá zastávka
            }
            $zastavkaId = $stopZastavka[$stopId];
            $sekundy    = $this->sekundy($cas);
            $odjezdy[]  = [
                'spoj_id'     => $spojId,
                'zastavka_id' => $zastavkaId,
                'cas'         => $sekundy,
            ];
            $otisk .= "|$zastavkaId:$sekundy";
        }

        if ($odjezdy === []) {
            return false;
        }

        // stejná linka, číslo, směr, dny i zastávky s časy = duplicitní spoj z jiné verze linky
        $otisk = md5($otisk, true);
        if (isset($this->otiskySpoju[$otisk])) {
            $this->duplicit++;

            return false;
        }
        $this->otiskySpoju[$otisk] = true;

        array_push($this->davkaOdjezdu, ...$odjezdy);
        if (count($this->davkaOdjezdu) >= $this->config->davka) {
            $this->ulozOdjezdy();
        }

        return true;
    }

    /**
     * Zapíše nasbírané odjezdy jedním insertBatch.
     */
    protected function ulozOdjezdy(): void
    {
        if ($this->davkaOdjezdu === []) {
            return;
        }
        $this->db->table('odjezd')->insertBatch($this->davkaOdjezdu, null, $this->config->davka);
        $this->pocetOdjezdu += count($this->davkaOdjezdu);
        $this->davkaOdjezdu = [];
    }

    // ===== pomocné metody =====

    /**
     * Otevře CSV uvnitř zipu. Vrací [soubor, název sloupce => pořadí], nebo null, když soubor chybí a není povinný.
     */
    protected function otevri(string $zip, string $soubor, bool $povinny = true): ?array
    {
        $cesta = 'zip://' . str_replace('\\', '/', realpath($zip)) . '#' . $soubor;
        $f     = @fopen($cesta, 'r');

        if ($f === false) {
            if ($povinny) {
                throw new RuntimeException("V $zip chybí $soubor.");
            }

            return null;
        }

        $hlavicka = $this->radek($f) ?? [];
        // první sloupec může začínat neviditelným BOM
        if (isset($hlavicka[0])) {
            $hlavicka[0] = preg_replace('/^\xEF\xBB\xBF/', '', $hlavicka[0]);
        }
        $hlavicka = array_map('trim', $hlavicka);

        return [$f, array_flip($hlavicka)];
    }

    /**
     * Jeden řádek CSV, nebo null na konci souboru.
     */
    protected function radek($f): ?array
    {
        $r = fgetcsv($f, null, ',', '"', '');

        return $r === false ? null : $r;
    }

    /**
     * 'HH:MM:SS' → sekundy od půlnoci. '25:10:00' je platný čas (1:10 dalšího dne).
     */
    protected function sekundy(string $cas): int
    {
        $c = explode(':', $cas);

        return (int) $c[0] * 3600 + (int) ($c[1] ?? 0) * 60 + (int) ($c[2] ?? 0);
    }

    protected function platneSouradnice(float $lat, float $lon): bool
    {
        // široké okolí ČR, vylučuje hlavně nulové a prohozené souřadnice
        return $lat > 40 && $lat < 60 && $lon > 0 && $lon < 30;
    }

    protected function vzdalenostKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // na pár km stačí rovinná aproximace
        $dx = ($lon1 - $lon2) * 111.32 * cos(deg2rad(($lat1 + $lat2) / 2));
        $dy = ($lat1 - $lat2) * 110.57;

        return sqrt($dx * $dx + $dy * $dy);
    }

    /**
     * Dny importovaného období z configu (oknoOd–oknoDo). Každý den je jeden bit v masce.
     */
    protected function pripravOkno(): void
    {
        $od = strtotime($this->config->oknoOd);
        $do = strtotime($this->config->oknoDo);

        for ($i = 0, $den = $od; $den <= $do; $i++, $den = strtotime('+1 day', $den)) {
            $this->denIndex[date('Ymd', $den)] = $i;
            $this->dnyOkna[$i]  = date('Y-m-d', $den);
            $this->denTydne[$i] = (int) date('N', $den) - 1;
        }

        if ($this->dnyOkna === [] || count($this->dnyOkna) > 62) {
            throw new RuntimeException('Období oknoOd–oknoDo musí mít 1 až 62 dní (bitová maska v int).');
        }
    }

    protected function vypis(string $text): void
    {
        ($this->vypis)($text);
    }
}
