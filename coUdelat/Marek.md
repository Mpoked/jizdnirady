# Marek – data a import

Tvoje část: aby v databázi byla data a aby šlo zjistit nejbližší odjezdy a počty na graf. Na tobě stojí ostatní, takže **fáze 1 má přednost**.

Společné věci (smlouvy, git, nastavení `.env`) jsou v [00-plan.md](00-plan.md).

Materiály: ref. 01 (instalace), 03 a 04 (modely, join), 09 (vlastní konfigurace).

---

## Fáze 1 – základ (push co nejdřív, ostatní na tobě čekají)

### 1.1 Příprava projektu
- [ ] Pracuje se rovnou v `master` (žádná větev), pravidla jsou v `00-plan.md` → Git.
- [ ] Z `package.json` smazat omylem nainstalovaný balíček `"5"` (`npm uninstall 5`).
- [ ] Nainstalovat **všechny** JS balíčky pro celý tým:
  ```bash
  npm i chart.js leaflet leaflet.markercluster qr-creator @fortawesome/fontawesome-free
  ```
- [ ] Ion Auth přes composer (pro Matěje), stejně jako v `../administrace/composer.json`. Do `composer.json` přidat:
  ```json
  "minimum-stability": "dev",
  "prefer-stable": true,
  "repositories": {
      "ionAuth": { "type": "vcs", "url": "https://github.com/benedmunds/CodeIgniter-Ion-Auth.git" }
  }
  ```
  a pak `composer require benedmunds/codeigniter-ion-auth:4.x-dev`. URL je přes `https`, ne `git@`, aby ostatní nepotřebovali SSH klíč. Když si GitHub řekne o token, postup je v ref. 01.
- [ ] Do `.gitignore` přidat `/writable/gtfs/` (zipy mají 200 MB).
- [ ] Nastavit si `.env` a `App.php` podle `00-plan.md`, vytvořit DB `jizdnirady` (`utf8mb4_czech_ci`).

### 1.2 Konfigurace – `app/Config/Jizdnirady.php` (ref. 09)
```php
namespace Config;
use CodeIgniter\Config\BaseConfig;

class Jizdnirady extends BaseConfig
{
    // data jen z jednoho dne (složka s datem, ne "latest", aby měli všichni stejná)
    public array $zdroje = [
        'bus'  => 'https://data.jr.ggu.cz/results/2026-11-01/JDF_merged_GTFS.zip',
        'vlak' => 'https://data.jr.ggu.cz/results/2026-11-01/CZPTT_GTFS.zip',
    ];
    public string $dataZeDne = '2026-11-01';
    // jaké období se importuje (od dnešního pondělí do změny jízdního řádu)
    public string $oknoOd = '2026-11-02';
    public string $oknoDo = '2026-12-13';
    public int $pocetOdjezdu     = 10;   // tabule
    public int $pocetOdjezduMapa = 5;    // popup na mapě
    public int $davka            = 5000; // řádků na jeden insertBatch
    public int $mapaMinZoom      = 13;
    public int $mapaLimit        = 500;
}
```

### 1.3 Migrace – `php spark make:migration Jizdnirady`
Tabulky podle `00-plan.md` → sekce Databáze: `zastavka`, `linka`, `spoj`, `odjezd`, `provoz`, `nastaveni`, se všemi indexy.
- [ ] `odjezd.cas` = `MEDIUMINT UNSIGNED` (sekundy, vejde se i 30:00:00)
- [ ] `zastavka.lat/lon` = `DECIMAL(9,6)`
- [ ] `odjezd` bez primárního klíče stačí, ale přidej `id` BIGINT AUTO_INCREMENT, ať CI4 model nekřičí
- [ ] Ověřit: `php spark migrate`, pak `php spark migrate:rollback` a znovu `migrate`

### 1.4 Modely – `php spark make:model …` (ref. 03, styl `../pocasi/app/Models/Station.php`)
`Zastavka`, `Linka`, `Spoj`, `Odjezd`, `Provoz`, `Nastaveni`, všechny s `$returnType = 'object'`.

Hned napsat metody ze smlouvy v `00-plan.md`, i kdyby uvnitř zatím byl jednoduchý dotaz. Ostatní je volají od začátku:
- [ ] `Zastavka::najdi($typ, $slug)` → `where('typ',…)->where('slug',…)->first()`
- [ ] `Zastavka::vyrez($s, $w, $n, $e, $limit)` → `where('lat >=', $s)->where('lat <=', $n)->where('lon >=', $w)->where('lon <=', $e)->findAll($limit)`
- [ ] `Odjezd::nejblizsi($zastavkaId, $pocet)`: viz fáze 2
- [ ] `Odjezd::tydenniPocty($zastavkaId)`: viz fáze 2
- [ ] `Nastaveni::hodnota($nazev)`

### 1.5 Testovací data – `php spark make:seeder TestovaciData`
Aby Tomáš a Matěj mohli pracovat, než napíšeš import.
- [ ] 6–8 zastávek kolem Uherského Hradiště (bus i vlak, skutečné souřadnice, např. `Uherské Hradiště` vlak 49.0697, 17.4644)
- [ ] 3–4 linky, ~30 spojů, odjezdy rozprostřené přes celý den, aspoň jeden spoj přes půlnoc (`cas` > 86 400)
- [ ] `provoz` **počítat od dnešního data** (pondělí aktuálního týdne + 14 dní), aby seeder fungoval kdykoli. Některé služby jen v pracovní dny, ať je v grafu vidět víkend.
- [ ] `nastaveni` vyplnit (`data_ze_dne`, `okno_od`, `okno_do`, `importovano` = teď, počty)

### 1.6 Kostra rout – `app/Config/Routes.php`
Tři komentované bloky podle `00-plan.md` (Tomáš / Matěj – mapa a API / Matěj – administrace). Rovnou do nich napiš routy z plánu, ať je každý jen upravuje.

### 1.7 Push a dát vědět
- [ ] Commit (jen svoje soubory) a push do `master` a **napsat Tomášovi a Matějovi**, ať pullnou a spustí `composer install`, `php spark migrate`, `php spark db:seed TestovaciData`.

---

## Fáze 2 – import

Import se pouští **jednou** (každý u sebe, až bude potřebovat ostrá data). Žádné noční aktualizace, žádné plánování. Proto je jednoduchý: vyprázdní tabulky a naplní je znovu.

### 2.1 `app/Libraries/GtfsImport.php` + `app/Commands/ImportJr.php`
- [ ] Command `php spark make:command ImportJr` → `$group = 'Jizdni rady'`, `$name = 'jr:import'`, volitelný argument `bus|vlak` (pro testování jen jednoho feedu). Jen zavolá knihovnu a vypisuje průběh přes `CLI::write()`.
- [ ] Na začátku `ini_set('memory_limit', '1536M'); set_time_limit(0);`

Postup knihovny pro každý feed (nejdřív `vlak`, je menší, pak `bus`; čítače id pokračují):

0. **Na začátku** `TRUNCATE` všech 6 tabulek (`$this->db->table('odjezd')->truncate()` …).
1. **Stáhnout** zip do `writable/gtfs/{typ}.zip`, **jen když tam ještě není**: `service('curlrequest')->get($url, ['sink' => $cesta, 'timeout' => 600])`. Druhý běh importu (třeba po opravě chyby) tak nic znovu nestahuje.
2. **Čtení bez rozbalování**: `fopen('zip://' . str_replace('\\', '/', realpath($zip)) . '#stops.txt', 'r')` + `fgetcsv()`.
   - Hlavičku mapovat podle názvů sloupců (`array_flip($hlavicka)`), ne podle pořadí.
   - Z prvního názvu sloupce odstranit případný BOM `"\xEF\xBB\xBF"`.
3. **stops.txt** → zastávky. Klíč `typ + stop_name`, víc `stop_id` se stejným názvem = jedna zastávka. U vlaků nástupiště (`parent_station` vyplněné) mapovat na stanici. Slug = `url_title(convert_accented_characters($nazev), '-', true)` (helper `text` + `url`). V paměti pole `stop_id → zastavka_id`.
4. **calendar.txt** (jen bus) + **calendar_dates.txt** → pro každou GTFS službu seznam dnů v období `oknoOd`–`oknoDo` z configu.
   - `calendar`: den v rozsahu `start_date`–`end_date` a sloupec dne v týdnu (`monday`…) = 1
   - `calendar_dates`: `exception_type` 1 = přidat den, 2 = odebrat den. Řádky mimo období rovnou přeskočit (u vlaků je to 620 MB, většinou starých dat).
   - Služby bez dne v období zahodit.
   - **Deduplikace:** stejné seznamy dnů = jedna naše služba. Klíč třeba `implode(',', $dny)` → `sluzba_id`. GTFS služeb jsou statisíce, vzorů jen pár tisíc, takže `provoz` bude malý i na 6 týdnů. Do paměti si ulož `GTFS service_id → sluzba_id`.
   - Každý vzor zapsat do `provoz` (`sluzba_id`, `datum`).
5. **agency.txt + routes.txt** → `linka` (všechny, je jich málo).
6. **trips.txt** → nechat jen spoje, jejichž služba jede v okně. Pole `trip_id → spoj_id`. Data spoje (linka, služba, číslo, headsign) drž v **plochých polích indexovaných číslem** (`$spojLinka[$id] = …`), ne v poli polí, jinak dojde paměť.
7. **stop_times.txt** (1,2 GB, je setříděný podle `trip_id`):
   - bufferuj řádky jednoho spoje; když se `trip_id` změní, zpracuj buffer
   - čas `HH:MM:SS` → sekundy (pozor, `25:10:00` je platný čas)
   - poslední zastávku spoje (konečnou) do `odjezd` **nedávat**, jen si z ní vzít `cil`, pokud je prázdný `trip_headsign`
   - řádky s `pickup_type = 1` (nelze nastoupit) nedávat
   - do `odjezd` přes `insertBatch` po `$davka` řádcích
8. **spoj** zapsat až na konci (až znáš `cil`).

Na konci importu:
- [ ] Do `nastaveni` zapsat `data_ze_dne`, `okno_od`, `okno_do`, `importovano` (kdy import doběhl), `pocet_zastavek`, `pocet_spoju`, `pocet_odjezdu`. Matěj je ukazuje v administraci a `COUNT(*)` nad miliony řádků by byl pomalý.
- [ ] Když import spadne, vypsat chybu přes `CLI::error()` a zalogovat přes `log_message('error', …)`. Po opravě se pustí znovu, tabulky se na začátku stejně vyprázdní.

### 2.2 Dotazy pro Tomáše a Matěje (model `Odjezd`, ref. 03/04)

**`nejblizsi($zastavkaId, $pocet)`**: D = dnes, S = sekundy od půlnoci teď. Kvůli spojům přes půlnoc se berou tři dny:
```
JOIN spoj ON spoj.id = odjezd.spoj_id
JOIN linka ON linka.id = spoj.linka_id
JOIN provoz ON provoz.sluzba_id = spoj.sluzba_id
WHERE odjezd.zastavka_id = ?
  AND (   (provoz.datum = D   AND odjezd.cas >= S)
       OR (provoz.datum = D-1 AND odjezd.cas >= S + 86400)
       OR (provoz.datum = D+1) )
ORDER BY DATEDIFF(provoz.datum, D) * 86400 + odjezd.cas
LIMIT ?
```
Hodnoty vkládej přes `$this->db->escape()`. V PHP pak dopočítej `cas` ve formátu `H:i`, `datum` a `zaMinut` a vrať objekty přesně podle smlouvy v `00-plan.md`.

**`tydenniPocty($zastavkaId)`**: `COUNT(*)` … `WHERE zastavka_id = ? AND provoz.datum BETWEEN pondělí AND neděle GROUP BY provoz.datum`. V PHP doplnit dny s nulou, aby bylo vždy 7 položek.

- [ ] Ověřit rychlost na ostrých datech pro velkou zastávku (Praha hl.n., Brno ÚAN Zvonařka): musí to být pod 1 s. Když ne, `EXPLAIN` a upravit indexy.

---

## Fáze 3 – dokončení
- [ ] **Zálohovat oba stažené zipy** z `writable/gtfs/` mimo git (školní disk, OneDrive…). Server neslibuje, že složka `2026-11-01` vydrží napořád. Když zmizí, ostatní si zipy nakopírují do `writable/gtfs/` a import je použije, protože už existují.
- [ ] Zapsat do README, jak dlouho import trvá, kolik zabere DB a že data platí jen do 13. 12. 2026
- [ ] Kdyby `insertBatch` byl moc pomalý (> 1 h): zapisovat do dočasného CSV a použít `LOAD DATA LOCAL INFILE`

## Jak otestovat
- [ ] `php spark jr:import vlak` doběhne, počty v tabulkách dávají smysl
- [ ] `php spark jr:import` (oba feedy). Zapsat si čas a velikost DB.
- [ ] Dvě náhodné zastávky porovnat s idos.cz (časy, směr)
- [ ] Spustit import podruhé: nic se nestahuje (zipy už jsou), počty v tabulkách vyjdou stejně a slug stejné zastávky se nezmění

## Co předáváš
| komu | co | kdy |
|---|---|---|
| oba | balíčky, migrace, modely se smlouvami, seeder, kostra rout | fáze 1 |
| Tomáš | funkční `nejblizsi` a `tydenniPocty` na seederu | začátek fáze 2 |
| Matěj | `Zastavka::vyrez`, `nastaveni` s počty | fáze 2 |
| oba | ostrá data | konec fáze 2 |
