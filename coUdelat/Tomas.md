# Tomáš – veřejná část, administrace a mapa

Tvoje část: to, co uvidí cestující (layout, hledání zastávky, odjezdová tabule, týdenní graf) a od 9. 11. i většina Matějových úkolů: alerty, `BaseController`, přihlášení, administrace s QR a mapa. Viz [konec souboru](#převzato-od-matěje).

Společné věci (smlouvy, git, nastavení `.env`) jsou v [00-plan.md](00-plan.md).

Materiály: ref. 02 (layout), 15 (stránka s obsahem z DB), 05 (stránky s parametrem), 06 (tabulky), 07 (stránkování), 11 (initController, `$this->data`).

---

## Fáze 1 – layout (DB nepotřebuješ, začni hned)

- [x] Pracuje se rovnou v `master` (žádná větev): před prací `git pull`, commitovat jen svoje soubory. Pravidla jsou v `00-plan.md` → Git.
- [x] `app/Views/layout/template.php`: stejný styl jako v `../administrace/app/Views/layout/template.php`, ale:
  - `<html lang="cs">`, `<title><?= esc($title ?? 'Odjezdy') ?></title>`
  - `<?= $this->include('layout/alert') ?>` hned pod navbarem (ref. 08 §4.5)
  - na konci `<body>` sekce pro JS konkrétní stránky: `<?= $this->renderSection('skripty') ?>`
  - patička s upozorněním o datech: jízdní řád ze dne 1. 11. 2026, platný do 13. 12. 2026, bez zpoždění (celý text je v `00-plan.md` → Omezení)
- [x] `layout/css.php`: Bootstrap, Bootswatch téma (vyber si), Font Awesome. Všechno z `node_modules` přes `base_url()` jako v sousedních projektech. **Chart.js ani Leaflet sem nedávej**, ty patří do sekce `skripty` jen na stránkách, kde jsou potřeba.
- [x] `layout/navbar.php`: odkazy Odjezdy (`/`), Mapa (`/mapa`), Administrace (`/administrace`). Odkaz na odhlášení přidáš s přihlášením (viz Převzato od Matěje).
- [x] `layout/alert.php`: vypíše `$alert`, když `$alert->message` není prázdné (Bootstrap `alert-dismissible`, ref. 08 §4.5). Proměnnou plní `BaseController` (taky tvůj).
- [x] **Commit a push layoutu co nejdřív** (hotovo, commit `13ca35c`)

---

## Fáze 2 – stránky (po pullu Markova základu a seederu)

> Kód fáze 2 a grafu z fáze 3 je napsaný lokálně (`Main.php`, `index.php`, `tabule.php`, `Pager/`), necommitnutý. Čeká na Markovy modely, pak otestovat a odškrtat.

### 2.1 Controller `Main` (ref. 15)
- [ ] `app/Controllers/Main.php`: v `initController()` vytvořit modely `Zastavka` a `Odjezd` a načíst `config('Jizdnirady')`.
- [ ] Do view posílat `$this->data` (obsahuje už `alert`, viz smlouva v `00-plan.md`).

### 2.2 Úvodní stránka – hledání zastávky (`/`)
- [ ] Routa `$routes->get('/', 'Main::index');` (ve svém bloku v `Routes.php`)
- [ ] Formulář GET s polem `q` (form helper)
- [ ] `$this->zastavka->like('nazev', $q)->orderBy('nazev')->paginate(25)` + `$this->zastavka->pager` (ref. 07). Bez `q` vypsat všechny (stránkované).
- [ ] Tabulka přes `\CodeIgniter\View\Table` s Bootstrap šablonou (ref. 06): Typ (ikona `fa-bus` / `fa-train`), Název (odkaz `anchor("tabule/{$z->typ}/{$z->slug}", esc($z->nazev))`)
- [ ] Vlastní šablona stránkování `app/Views/Pager/default_full.php` + nastavení v `app/Config/Pager.php` (ref. 07). Hledaný výraz se musí při stránkování zachovat (`$pager->links()` query string přenáší, ověř to).
- [ ] Prázdný výsledek: hláška „Žádná zastávka neodpovídá…“

### 2.3 Odjezdová tabule (`/tabule/{typ}/{slug}`) (ref. 05)
- [ ] Routa `$routes->get('tabule/(:segment)/(:segment)', 'Main::tabule/$1/$2');`
- [ ] `tabule($typ, $slug)`:
  - `$typ` musí být `bus` nebo `vlak`, jinak 404
  - `$zastavka = $this->zastavka->najdi($typ, $slug)`, při `null`: `throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();`
  - `$odjezdy = $this->odjezd->nejblizsi($zastavka->id, $this->config->pocetOdjezdu)`
  - `$pocty = $this->odjezd->tydenniPocty($zastavka->id)`
- [ ] View `app/Views/tabule.php`, vzhled jako skutečná nádražní tabule (tmavé pozadí, žluté/bílé písmo, velký čas):
  - nadpis = název zastávky + ikona typu, vpravo aktuální čas
  - sloupce: **Odjezd** (`cas`) · **Linka** (`linka` + `cisloSpoje`) · **Směr** (`cil`) · **Dopravce** · **Za** (`zaMinut` min; 0 → „odjíždí“)
  - odjezd zítra (jiné `datum` než dnes) označit, např. „zítra 05:12“
  - prázdný stav: „Z této zastávky teď nic nejede“. Po 13. 12. 2026 budou prázdné všechny tabule (data končí), proto v tom případě napsat „Jízdní řád platí jen do 13. 12. 2026“.
  - automatické obnovení: `<meta http-equiv="refresh" content="60">` nebo JS `setTimeout(() => location.reload(), 60000)`
  - musí být čitelné na mobilu (sem vede QR kód ze zastávky), takže se sloupce na úzkém displeji schovají (`d-none d-md-table-cell` u dopravce)
- [ ] Odkaz „Zobrazit na mapě“ → `/mapa?lat=…&lon=…` (mapa je teď tvoje, tak ať tyhle parametry přečte)

---

## Fáze 3 – graf a doladění

### 3.1 Týdenní graf (Chart.js), pod tabulí
- [ ] V sekci `skripty` načíst `node_modules/chart.js/dist/chart.umd.js`
- [ ] Data z controlleru: popisky `['Po','Út','St','Čt','Pá','So','Ne']` (případně s datem `Po 2. 11.`) a hodnoty `array_values($pocty)`, do JS přes `json_encode`
- [ ] Sloupcový graf, dnešní sloupec jinou barvou, nadpis „Počet odjezdů tento týden“, osa Y od nuly s celými čísly
- [ ] Graf se nesmí znovu animovat při každém obnovení stránky (`animation: false`), jinak to každou minutu bliká

### 3.2 Doladění
- [ ] `<title>` stránky = název zastávky
- [ ] Ověřit vzhled ve světlém i tmavém režimu Bootswatch tématu, které jsi vybral
- [ ] Ošetřit `esc()` u všeho, co jde z dat (názvy zastávek a směrů jsou z cizího zdroje)

## Jak otestovat
- [ ] Na datech ze seederu: hledání „hradi“ najde zastávky Uherského Hradiště, stránkování funguje i s hledaným výrazem
- [ ] Neexistující slug nebo typ `tramvaj` vrátí 404
- [ ] Tabule ukazuje 10 odjezdů seřazených podle času. Pozdě večer musí být vidět i spoje po půlnoci a ranní spoje „zítra“.
- [ ] Graf má 7 sloupců a víkend se liší (seeder má služby jen v pracovní dny)
- [ ] Mobil (DevTools, šířka 375 px): tabule je čitelná bez vodorovného posouvání
- [ ] Po Markově ostrém importu: porovnat jednu tabuli s idos.cz

## Co potřebuješ od ostatních
| od koho | co |
|---|---|
| Marek | modely `Zastavka`, `Odjezd` (metody podle smlouvy), seeder, kostra rout |
| Marek | balíčky `chart.js`, `@fortawesome/fontawesome-free`, `leaflet`, `leaflet.markercluster`, `qr-creator`, Ion Auth v composeru + `Config/IonAuth.php` |
| Marek | `Nastaveni::hodnota()` a vyplněná `nastaveni` (administrace), `Zastavka::vyrez` |
| Marek | JSON API `api/zastavky`, `api/odjezdy/{typ}/{slug}` (mapa, fáze 3) |

## Co předáváš
| komu | co | kdy |
|---|---|---|
| Marek | layout se sekcí `skripty` a `alert` (hotovo) | fáze 1 |
| Marek | Pager šablona (použije ji i seznam v administraci) | fáze 2 |

---

## Převzato od Matěje

Podrobné zadání je v [Matej.md](Matej.md) (oddíly v závorce). Pořadí:

### A. Hned (bez DB): alerty a `BaseController` (Matej.md fáze 1)
- [ ] `app/Config/Alert.php` s polem `$alertMessage` (klíče `loginsuccess`, `logindanger`, `logoutsuccess`, `logoutdanger`, `filterdanger`)
- [ ] `app/Libraries/Alert.php`: `makeMessage($status, $type)` → objekt `{class, message}` (ref. 08 §4.5)
- [ ] `app/Controllers/BaseController.php`: helpery `form`, `url`, `text`; `$this->session`; `$this->alert`; `protected array $data = []` s prázdným alertem, případně alertem z flashdat. `$this->ionAuth = new \IonAuth\Libraries\IonAuth();` až po Markově `composer require`.
- [ ] Pak z `Main.php` smazat dočasnou deklaraci `$data` a prázdný alert

### B. Po Markově základu: přihlášení a administrace (Matej.md fáze 2)
- [ ] Naimportovat `ion_auth.sql`, změnit heslo adminovi
- [ ] Filtr `LoginFilter` (§2.1). **Pozor na zakomentovaný řádek `$this->alert = new Alert();` ve vzoru `../administrace`.** Alias `auth` v `Config/Filters.php`
- [ ] Controller `Login` + view `login/login.php` (§2.2), routy ve bloku „Tomáš – administrace“
- [ ] Navbar: když `loggedIn()`, ukázat „Odhlásit“ (`administrace/odhlaseni`)
- [ ] `Administrace::index`: karty z `nastaveni`, varování po `okno_do` (§2.3)
- [ ] `Administrace::qr`: hledání + stránkovaný seznam s checkboxy (§2.4), použij svou Pager šablonu
- [ ] `Administrace::qrTisk` + `administrace/qr_tisk.php`: QR přes qr-creator, tisk 2 kartičky na A4 (§2.5). **QR = `tabule/{typ}/{slug}`, nikdy GTFS id**

### C. Po Markově API: mapa (Matej.md §3.2)
- [ ] `Main::mapa()` + `app/Views/mapa.php` (Leaflet + markercluster v sekci `skripty`), parametry `lat`/`lon` z odkazu na tabuli
- [ ] Načítání zastávek podle výřezu, hláška při malém zoomu, popup s 5 odjezdy přes `api/odjezdy/…`

### Jak otestovat (převzaté)
- [ ] Testy v Matej.md → „Jak otestovat“: přesměrování bez přihlášení, špatné heslo, tisk QR, QR z telefonu přes IP počítače, mapa
