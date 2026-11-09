# Odjezdové tabule – společný plán

Tým: **Marek** (data, import, API), **Tomáš** (veřejná část, administrace, mapa).
Každý má vlastní soubor s úkoly: [Marek.md](Marek.md), [Tomas.md](Tomas.md).

> **Změna (9. 11. 2026):** Matěj na projektu nepracuje. Jeho úkoly převzali Marek a Tomáš (rozdělení je v tabulce níž a na konci jejich souborů). [Matej.md](Matej.md) zůstává jako podrobný popis těch úkolů, odkazy „Matej.md §…“ vedou tam.

## Zadání

Aplikace v CodeIgniter 4, která z otevřených jízdních řádů https://data.jr.ggu.cz/ zobrazuje odjezdové tabule zastávek (autobusy i vlaky):

1. **Odjezdová tabule**: 10 nejbližších odjezdů ze zastávky v aktuálním čase.
2. **Administrace**: generování QR kódů k vytištění a vylepení na zastávku. QR otevře tabuli dané zastávky. Knihovna [qr-creator](https://github.com/nimiq/qr-creator).
3. **Graf**: počet odjezdů ze zastávky v jednotlivých dnech aktuálního týdne (Po–Ne). Knihovna Chart.js.
4. **Mapa**: zastávky na mapě, u každé přehled nejbližších odjezdů. Knihovna Leaflet.

Rozsah dat: **celá ČR**.

Píšeme podle skillu/materiálů `codeigniter4` z kurzu (layout, modely, routy s parametrem, tabulky, stránkování, alerty, administrace s Ion Auth, vlastní konfigurace). Komentáře v kódu česky.

## Zdroj dat – co tam doopravdy je

Žádné API. Každou noc (22:00–24:00) se vygenerují nové GTFS zipy a starší zůstávají ve složkách podle data.

**Bereme jen dnešní data, jednou, a každý den nové nestahujeme.** Konkrétně složku `https://data.jr.ggu.cz/results/2026-11-01/` (vygenerovaná v noci 1. 11. 2026, stejné soubory jako dnešní `latest`). Pevná adresa se složkou zaručí, že všichni tři budou mít stejná data, i když se k importu dostanou až za pár dní.

| soubor | obsah | velikost zipu | největší části (rozbalené) |
|---|---|---|---|
| `JDF_merged_GTFS.zip` | autobusy (vše mimo železnici) | 131 MB | `stop_times.txt` 1,18 GB, `calendar_dates.txt` 186 MB, `trips.txt` 83 MB, `calendar.txt` 49 MB, `stops.txt` 4,5 MB |
| `CZPTT_GTFS.zip` | vlaky | 69 MB | `calendar_dates.txt` 620 MB, `stop_times.txt` 238 MB, `trips.txt` 26 MB (**nemá** `calendar.txt`) |

Důležité vlastnosti:
- GTFS časy můžou být přes půlnoc, např. `25:10:00` = 1:10 následujícího dne.
- Vlakové stanice mají `location_type=1` a nástupiště odkazují přes `parent_station`.
- **Autobusové `stop_id` (`JDFS-1`, `JDFS-2`…) se v každém novém vydání dat čísluje znovu.** Kdybychom data někdy obnovili, vytištěné QR s GTFS id by přestaly fungovat. Do URL a QR kódů proto GTFS id nedáváme. Používáme `typ` + `slug` z názvu zastávky (např. `tabule/vlak/uherske-hradiste`).
- Celou ČR nejde do MySQL nahrát „jen tak“ (řádově 20 mil. řádků `stop_times`). Proto import bere jen **období 2. 11. – 13. 12. 2026** (od dnešního pondělí do změny jízdního řádu) a ukládá data kompaktně (viz níže).
- Aplikace pracuje se skutečným dnešním datem a časem. **Po 13. 12. 2026 budou tabule prázdné.** Kdybyste projekt předváděli později, stačí v configu změnit adresu složky a období a spustit import znovu.

## Architektura

```
php spark jr:import          ← spustí se JEDNOU (každý u sebe), žádné noční aktualizace
  1. stáhne oba zipy ze složky results/2026-11-01/ do writable/gtfs/ (když tam ještě nejsou)
  2. čte CSV přímo ze zipu (zip://), nic nerozbaluje
  3. nechá jen spoje jedoucí v období 2. 11. – 13. 12. 2026
  4. vyprázdní tabulky (TRUNCATE) a naplní je přes insertBatch

Web:
  Main         /                        hledání zastávky (stránkování)
               /tabule/{typ}/{slug}     odjezdová tabule + týdenní graf
               /mapa                    mapa zastávek
  Api          /api/zastavky?s=&w=&n=&e=      zastávky ve výřezu mapy (JSON)
               /api/odjezdy/{typ}/{slug}      5 nejbližších odjezdů (JSON)
  Login        /prihlaseni
  Administrace /administrace, /administrace/qr, /administrace/qr/tisk  (filtr auth)
```

## Databáze `jizdnirady`

Samostatná databáze, protože bude mít stovky MB. Kolace `utf8mb4_czech_ci`, aby se názvy řadily česky.

| tabulka | sloupce | indexy | poznámka |
|---|---|---|---|
| `zastavka` | `id` INT PK, `typ` ENUM('bus','vlak'), `nazev`, `slug`, `lat`, `lon` | UNIQUE(`typ`,`slug`), INDEX(`lat`,`lon`), INDEX(`nazev`) | jedna řádka = jedna zastávka podle názvu (víc GTFS stop_id → jedna zastávka) |
| `linka` | `id` INT PK, `typ`, `cislo`, `nazev`, `dopravce`, `route_type` | – | z `routes.txt` + `agency.txt` |
| `spoj` | `id` INT PK, `linka_id`, `sluzba_id` INT, `cislo`, `cil` | INDEX(`sluzba_id`) | z `trips.txt`, `cil` = směr (headsign nebo název konečné) |
| `odjezd` | `spoj_id` INT, `zastavka_id` INT, `cas` MEDIUMINT | INDEX(`zastavka_id`,`cas`) | `cas` = sekundy od půlnoci (může být > 86 400). Jen skutečné odjezdy: bez konečné a bez `pickup_type=1` |
| `provoz` | `sluzba_id` INT, `datum` DATE | PK(`datum`,`sluzba_id`) | ve kterých dnech období spoj jede. `sluzba` = **jedinečný vzor dnů** (např. „pracovní dny kromě svátků“). Spoje se stejnými dny sdílí jednu službu, takže tabulka zůstane malá. |
| `nastaveni` | `nazev` PK, `hodnota` | – | `data_ze_dne` (2026-11-01), `okno_od`, `okno_do`, `importovano`, `pocet_zastavek`, `pocet_spoju`, `pocet_odjezdu` |

Ion Auth přidá své tabulky (`users`, `groups`, `users_groups`, `login_attempts`).

## Rozdělení práce

| | Marek: data, import a API | Tomáš: veřejná část, administrace a mapa |
|---|---|---|
| Co dělá | příprava projektu (balíčky npm i composer), `Config/Jizdnirady.php`, migrace, modely, testovací seeder, import (`GtfsImport` + `php spark jr:import`), dotazy na odjezdy a graf, kostra `Routes.php`, **Ion Auth (konfigurace + tabulky), JSON API pro mapu** | layout, úvodní stránka s hledáním a stránkováním, odjezdová tabule, graf, **alerty, `BaseController`, filtr, přihlášení, administrace s QR, mapa** |
| Jeho soubory | `Config/Jizdnirady.php`, `Config/IonAuth.php`, `Database/**`, `Models/**`, `Libraries/GtfsImport.php`, `Commands/**`, `Controllers/Api.php`, `package.json`, `composer.json`, `.gitignore` | `Views/layout/**`, `Views/index.php`, `Views/tabule.php`, `Views/mapa.php`, `Views/login/**`, `Views/administrace/**`, `Views/Pager/**`, `Config/{Pager,Alert,Filters}.php`, `Controllers/{BaseController,Main,Login,Administrace}.php`, `Filters/**`, `Libraries/Alert.php` |

**Pravidlo: do cizích souborů nesahej.** Platí to i pro soubory projektu, které v tabulce nejsou u nikoho (konfigurace CI4, `Home.php`, `welcome_message.php` …). Když potřebuješ změnu v cizím souboru, napiš to tomu, komu patří. Protože děláme všichni v jedné větvi, je tohle pravidlo to jediné, co nás chrání před konflikty.

### Společné soubory a jak je sdílet

- **`app/Config/Routes.php`**: Marek založí kostru se třemi bloky. Každý píše jen do svého bloku.
  ```php
  // ===== Tomáš – veřejná část a mapa =====
  // ===== Marek – API =====
  // ===== Tomáš – administrace =====
  ```
- **`app/Controllers/Main.php`**: celý patří Tomášovi (včetně `mapa()`).
- **`package.json`, `composer.json`**: instaluje **jen Marek**, a to všechno hned na začátku. Když někdo potřebuje další balíček, řekne Markovi.

### Smlouvy mezi částmi (na tohle se ostatní spoléhají)

**Modely (Marek):**
- `Zastavka`: sloupce `id, typ ('bus'|'vlak'), nazev, slug, lat, lon`
  - `najdi(string $typ, string $slug): ?object`: zastávka, nebo `null`
  - `vyrez(float $s, float $w, float $n, float $e, int $limit): array`: zastávky v obdélníku mapy
- `Odjezd::nejblizsi(int $zastavkaId, int $pocet): array`: pole objektů seřazených podle času:
  ```
  cas        '14:35'         (H:i)
  datum      '2026-11-02'
  zaMinut    7               (int)
  typ        'bus' | 'vlak'
  linka      '345063' / 'Os'
  cisloSpoje '24140' / ''    (u vlaků číslo vlaku)
  cil        'Uherské Hradiště,žel.st.'
  dopravce   'ČSAD …'
  ```
- `Odjezd::tydenniPocty(int $zastavkaId): array`: vždy 7 položek Po–Ne aktuálního týdne, např. `['2026-11-02' => 412, '2026-11-03' => 398, …]`
- `Nastaveni::hodnota(string $nazev): ?string`

**BaseController (Tomáš):**
- `$this->data`: pole pro view, už obsahuje `alert` (objekt s `message` a `class`; když je `message` prázdné, nic se nezobrazí)
- `$this->session`, `$this->ionAuth`
- helpery `form`, `url`, `text`

Controllery proto posílají do view `$this->data` (ref. 11 Pokročilá práce s controllery):
```php
$this->data += ['zastavka' => $zastavka];
return view('tabule', $this->data);
```

**Layout (Tomáš):**
- `layout/template.php` má sekce `content` a `skripty` (JS konkrétní stránky, např. Chart.js nebo Leaflet):
  ```php
  <?= $this->section('skripty') ?> … <?= $this->endSection() ?>
  ```
- `layout/alert.php` vypisuje `$alert`.

**URL tabule:** `tabule/{typ}/{slug}` přes `site_url("tabule/{$z->typ}/{$z->slug}")`. Používá ji QR kód, mapa i výsledky hledání.

## Nastavení, které si dělá každý sám

`.env` a `app/Config/App.php` jsou v `.gitignore`, takže je git nepřenese.

`.env`:
```
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost/pokorny/jizdnirady/'
database.default.hostname = localhost
database.default.database = jizdnirady
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.DBCollat = utf8mb4_czech_ci
```

`app/Config/App.php`:
```php
public string $indexPage   = '';
public string $appTimezone = 'Europe/Prague';
public string $defaultLocale = 'cs';
```

Po každém pullu: `composer install` (`vendor/` je v `.gitignore`). `node_modules/` je v gitu, ten přijde s pullem.

Databáze: v phpMyAdminu vytvořit `jizdnirady` (`utf8mb4_czech_ci`), pak `php spark migrate` a naimportovat `vendor/benedmunds/codeigniter-ion-auth/sql/ion_auth.sql` (tabulky pro přihlášení, účet `admin@admin.com` / `password`, heslo hned změnit). Pak:
- během práce stačí `php spark db:seed TestovaciData` (rychlé testovací údaje),
- ostrá data **jednou** přes `php spark jr:import` (stáhne ~200 MB a trvá desítky minut). Všichni stahují ze stejné složky `2026-11-01`, takže budou mít stejná data.

## Git – všichni v jedné větvi `master`

Žádné další větve. Aby se to nepohádalo:
1. **Než začneš pracovat:** `git pull`
2. **Commituj jen svoje soubory:** `git add app/Models/Zastavka.php …`. Nikdy `git add .` ani `git add -A`, přidal by sis i cizí rozdělanou práci.
3. **Commituj často a po malých kouscích**, ať se změny nehromadí.
4. **Před pushem:** `git pull`, a když projde, `git push`. Když git hlásí konflikt, nic nepřepisuj a domluvte se. Konflikt může vzniknout jen ve sdíleném `Routes.php` nebo `Main.php`.
5. Necommitovat `.env`, `App.php` ani `writable/gtfs/` (zipy, 200 MB). Marek přidá `writable/gtfs/` do `.gitignore`.

## Harmonogram

| fáze | Marek | Tomáš |
|---|---|---|
| **1. Základ** (nejdřív, Tomáš čeká) | příprava projektu, balíčky, `Config/Jizdnirady`, `Config/IonAuth`, migrace, modely se smlouvami, seeder, kostra rout → **push** | ✅ layout; `Config/Alert`, `Libraries/Alert`, `BaseController` |
| **2. Hlavní práce** | import vlaků, pak autobusů, dotazy `nejblizsi` a `tydenniPocty` | úvodní stránka (hledání + stránkování), tabule, graf; pak přihlášení, filtr, administrace, QR |
| **3. Dodělávky** | API pro mapu, výkon dotazů, záloha zipů mimo git | mapa, vzhled, prázdné stavy |
| **4. Spolu** | každý jednou `php spark jr:import`, testování podle sekce Ověření, nasazení (ref. 12) | |

Do konce fáze 2 pracuje Tomáš na datech ze seederu, ostrý import nepotřebuje.

## Ověření (na konci společně)

- [ ] `php spark migrate` + `php spark jr:import` doběhne. Zapsat si čas a počty řádků v tabulkách (čekáme ~50 tis. zastávek, miliony odjezdů).
- [ ] Tabule známé stanice (např. `tabule/vlak/uherske-hradiste`) sedí s jízdním řádem na idos.cz.
- [ ] Ve 23:50 tabule ukazuje i spoje po půlnoci.
- [ ] Graf: součet sloupců odpovídá SQL dotazu, o víkendu je méně spojů.
- [ ] Mapa: při malém přiblížení ukáže hlášku, po přiblížení značky, popup s 5 odjezdy a odkazem na tabuli.
- [ ] `/administrace/qr` bez přihlášení přesměruje na `/prihlaseni` s hláškou.
- [ ] Vytištěný QR naskenovaný telefonem otevře správnou tabuli. `localhost` na telefonu nefunguje, testovat přes IP počítače v síti nebo po nasazení.
- [ ] Administrace ukazuje, ze kterého dne data jsou a do kdy platí.

## Omezení (dát do patičky webu)

Jízdní řád ze dne 1. 11. 2026, platný do 13. 12. 2026. Statický, bez zpoždění. Autor dat upozorňuje, že nemusí být dost přesná pro plánování cest. Data: CIS JŘ (Ministerstvo dopravy) přes data.jr.ggu.cz, mapa © přispěvatelé OpenStreetMap.
