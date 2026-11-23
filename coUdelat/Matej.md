# Matěj – mapa a administrace

> **Matěj na projektu nepracuje (od 9. 11. 2026).** Úkoly z tohoto souboru převzali:
> - **Marek:** `Config/IonAuth.php` + tabulky Ion Auth (fáze 1), controller `Api` (§3.1)
> - **Tomáš:** všechno ostatní, tedy alerty a `BaseController` (fáze 1), filtr, přihlášení, administrace a QR (fáze 2), mapa (§3.2)
>
> Soubor zůstává jako podrobný popis úkolů. Kde se níž píše „Tomáš“ nebo „tvůj blok“, platí rozdělení výše a bloky rout podle `00-plan.md`.

> **Stav 23. 11. 2026: hotovo a otestováno** na ostrých datech (přihlášení, filtr, administrace, tisk QR do PDF, API, mapa). Nevyzkoušené zůstalo jen skenování QR telefonem. Při testu se opravily 4 věci: popup na mapě se hned zavíral, v Praze chyběly zastávky v severní části výřezu, odkazy v patičce byly bílé na bílém a chyběla stránka pro změnu hesla (nově `administrace/heslo`). **Každý si ve své DB změní výchozí heslo `password`**, administrace na něj upozorní. Jak to celé funguje: [jak-to-funguje.md](jak-to-funguje.md).

Tvoje část: přihlášení a administrace s QR kódy a mapa zastávek s odjezdy (včetně JSON API, které mapa volá).

Společné věci (smlouvy, git, nastavení `.env`) jsou v [00-plan.md](00-plan.md).

Materiály: ref. 10 (administrace, Ion Auth, filtr), 08 §4.5 (alerty, session), 11 (initController), 09 (konfigurace), 06 + 07 (tabulka a stránkování v seznamu pro QR). Vzor přihlášení máš v `../administrace` (pozor na chybu ve filtru, viz níž).

---

## Fáze 1 – základ, který potřebují ostatní

- [x] Pracuje se rovnou v `master` (žádná větev): před prací `git pull`, commitovat jen svoje soubory. Pravidla jsou v `00-plan.md` → Git.
- [x] Pullnout Markův základ a spustit `composer install` (Ion Auth už nainstaloval Marek).
- [x] **Ion Auth tabulky**: obsah `vendor/benedmunds/codeigniter-ion-auth/sql/ion_auth.sql` naimportovat v phpMyAdminu do DB `jizdnirady` (ref. 10 §2.1). Vznikne účet `admin@admin.com` / `password`, **hned po prvním přihlášení heslo změnit**.
- [x] `app/Config/IonAuth.php` (ref. 10 §2.2): `class IonAuth extends \IonAuth\Config\IonAuth`, nastavit `$identity = 'email'` a `$siteTitle`.
- [x] `app/Config/Alert.php`: třída s polem hlášek (ref. 08 §4.5). Klíče ve tvaru `{typ}success` / `{typ}danger`:
  ```php
  public array $alertMessage = [
      'loginsuccess'  => 'Přihlášení proběhlo úspěšně.',
      'logindanger'   => 'Špatný e-mail nebo heslo.',
      'logoutsuccess' => 'Byl(a) jste odhlášen(a).',
      'logoutdanger'  => 'Odhlášení se nepovedlo.',
      'filterdanger'  => 'Do administrace se musíte přihlásit.',
  ];
  ```
  Pozor: v materiálu se pole jednou jmenuje `alertMessage` a jednou `errorMessage`. Vyber jeden název a drž se ho.
- [x] `app/Libraries/Alert.php`: metoda `makeMessage($status, $type)` vrací objekt `{class, message}` (ref. 08 §4.5).
- [x] `app/Controllers/BaseController.php` (ref. 08 §4.5, 10 §2.3, 11):
  - `$this->helpers = ['form', 'url', 'text'];`
  - `$this->session = \Config\Services::session();`
  - `$this->ionAuth = new \IonAuth\Libraries\IonAuth();`
  - `$this->alert = new Alert();` a prázdný alert do `$this->data['alert']`. Když je v session flashdata `alert`, dát do `$this->data['alert']` tu.
  - deklarovat vlastnosti `protected $session, $ionAuth, $alert; protected array $data = [];`
- [x] **Commit a push co nejdřív.** Tomáš potřebuje `$this->data['alert']`.

---

## Fáze 2 – přihlášení a administrace

### 2.1 Filtr (ref. 10 §3.4)
- [x] `php spark make:filter LoginFilter`. Kód jako `../administrace/app/Filters/LoginFilter.php`, **ale tam je řádek `$this->alert = new Alert();` zakomentovaný, takže `makeMessage()` spadne na `null`**. Tady ho nech funkční (`use App\Libraries\Alert;`).
- [x] Alias `'auth' => LoginFilter::class` v `app/Config/Filters.php`.

### 2.2 Přihlášení – controller `Login` (ref. 10 §3)
- [x] `login()` → view `login/login.php` (můžeš vzít vzhled z `../administrace/app/Views/login/Login.php`, ale s `<?= $this->extend('layout/template') ?>` od Tomáše)
- [x] `loginFinish()` → `$this->ionAuth->login($email, $heslo)`, alert do flashdat, redirect na `administrace` nebo zpět na `prihlaseni`
- [x] `logout()` → `$this->ionAuth->logout()`, alert, redirect na `/`
- [x] Routy ve tvém bloku „administrace“ v `Routes.php`:
  ```php
  $routes->get('prihlaseni', 'Login::login');
  $routes->post('prihlaseni', 'Login::loginFinish');
  $routes->group('administrace', ['filter' => 'auth'], static function ($routes) {
      $routes->get('/', 'Administrace::index');
      $routes->get('qr', 'Administrace::qr');
      $routes->get('qr/tisk', 'Administrace::qrTisk');
      $routes->get('odhlaseni', 'Login::logout');
  });
  ```
- [x] Napsat Tomášovi, ať do navbaru přidá „Odhlásit“, když `$this->ionAuth->loggedIn()`. Nebo mu pošli hotový kousek kódu.

### 2.3 `Administrace::index` – přehled
- [x] Karty s čísly z tabulky `nastaveni` (`Nastaveni::hodnota()`): počet zastávek, spojů, odjezdů, „data ze dne“ (`data_ze_dne`), platnost (`okno_od`–`okno_do`) a kdy proběhl import (`importovano`). **Nepočítej `COUNT(*)` nad `odjezd`**, má miliony řádků a čísla ti připraví Markův import.
- [x] Varování, když je dnešek po `okno_do` (data už neplatí, tabule budou prázdné)
- [x] Odkaz na „QR kódy zastávek“

### 2.4 `Administrace::qr` – výběr zastávek
- [x] Formulář GET s hledáním `q` + stránkovaný seznam (`paginate(25)`, ref. 07, Tomášova Pager šablona)
- [x] Ke každé zastávce checkbox `name="z[]" value="{typ}/{slug}"`, tlačítko „Vytisknout vybrané“ → formulář GET na `administrace/qr/tisk`
- [x] U každého řádku i odkaz na tisk jedné zastávky (`qr/tisk?z[]=vlak/uherske-hradiste`)

### 2.5 `Administrace::qrTisk` – tiskový arch
- [x] Z `$this->request->getGet('z')` vzít pole `typ/slug`, rozdělit, každou zastávku dohledat přes `Zastavka::najdi()`, neexistující přeskočit
- [x] View `administrace/qr_tisk.php`: pro každou zastávku kartička s:
  - názvem zastávky a ikonou bus/vlak
  - textem „Aktuální odjezdy – naskenujte telefonem“
  - QR kódem v `<div class="qr" data-url="<?= esc(site_url("tabule/{$z->typ}/{$z->slug}")) ?>">`
  - URL pod QR malým písmem (kdyby čtečka nefungovala)
- [x] V sekci `skripty`:
  ```html
  <script src="<?= base_url('node_modules/qr-creator/dist/qr-creator.min.js') ?>"></script>
  <script>
  document.querySelectorAll('.qr').forEach(el => {
      QrCreator.render({ text: el.dataset.url, radius: 0.3, ecLevel: 'M', fill: '#000', background: '#fff', size: 300 }, el);
  });
  </script>
  ```
- [x] Tlačítko „Vytisknout“ → `window.print()`. V `@media print` schovat navbar, patičku a tlačítka, kartičky nerozdělovat mezi stránky (`break-inside: avoid`), 2 kartičky na A4.
- [x] **QR obsahuje `typ/slug`, nikdy GTFS id.** Autobusová id se v každém vydání dat mění, takže kdybychom data někdy obnovili, vytištěný kód by přestal fungovat.

---

## Fáze 3 – API a mapa

### 3.1 Controller `Api`
- [x] Routy ve tvém bloku „mapa a API“:
  ```php
  $routes->get('mapa', 'Main::mapa');
  $routes->get('api/zastavky', 'Api::zastavky');
  $routes->get('api/odjezdy/(:segment)/(:segment)', 'Api::odjezdy/$1/$2');
  ```
- [x] `zastavky()`: GET `s, w, n, e` převést na `float` a zkontrolovat, že jsou zadané. Pak `Zastavka::vyrez(…, $config->mapaLimit)` a vrátit `$this->response->setJSON(...)` s poli `typ, slug, nazev, lat, lon`.
- [x] `odjezdy($typ, $slug)`: `najdi()` (když není, vrátit 404 JSON), pak `Odjezd::nejblizsi($id, $config->pocetOdjezduMapa)` a vrátit `{ zastavka: {...}, url: site_url('tabule/…'), odjezdy: [...] }`.

### 3.2 Mapa – `Main::mapa()` + `app/Views/mapa.php`
- [x] Metodu `mapa()` přidej **na konec** Tomášova `Main.php` a nic jiného v souboru neměň. Do view pošli `mapaMinZoom` z configu a případné `lat`/`lon` z GET (Tomáš na mapu odkazuje z tabule).
- [x] V sekci `skripty` načíst Leaflet a markercluster z `node_modules`:
  `leaflet/dist/leaflet.css`, `leaflet/dist/leaflet.js`, `leaflet.markercluster/dist/MarkerCluster.css`, `MarkerCluster.Default.css`, `leaflet.markercluster.js`
- [x] Mapa: střed ČR `[49.8, 15.5]`, zoom 7, dlaždice OpenStreetMap s atribucí „© přispěvatelé OpenStreetMap“. Když přijde `lat`/`lon`, vycentrovat na ně se zoomem 16.
- [x] Na `moveend`:
  - zoom < `mapaMinZoom` → vyčistit značky a ukázat hlášku „Přibližte mapu, aby se zobrazily zastávky“
  - jinak `fetch('api/zastavky?s=…&w=…&n=…&e=…')` z `map.getBounds()` → vyčistit cluster a přidat značky (bus a vlak jinou ikonou nebo barvou)
  - zastávek je ~50 tisíc, proto se nikdy nenačítají všechny najednou
- [x] Klik na značku → `fetch('api/odjezdy/{typ}/{slug}')` → popup: název, malá tabulka 5 odjezdů (čas, linka, směr, za X min) a odkaz „Celá tabule →“. Dokud se data načítají, ukázat v popupu „Načítám…“.
- [x] Volitelně tlačítko „Najdi mě“ (`map.locate()`) na nejbližší zastávky v okolí

## Jak otestovat
- [x] `/administrace` a `/administrace/qr` bez přihlášení → přesměruje na `/prihlaseni` s hláškou
- [x] Špatné heslo → hláška, správné → přehled; odhlášení funguje
- [x] Tisk 3 vybraných zastávek: náhled tisku (Ctrl+P) bez navbaru, QR čitelné
- [ ] QR naskenovat telefonem → otevře správnou tabuli. `localhost` z telefonu nefunguje, dočasně dej do `.env` `app.baseURL` s IP adresou počítače v síti (např. `http://192.168.1.20/pokorny/jizdnirady/`).
- [x] `/api/zastavky?s=49.0&w=17.4&n=49.1&e=17.5` vrací JSON. Bez parametrů vrací chybu, ne výjimku.
- [x] Mapa: oddálená → hláška; přiblížená na Uherské Hradiště → značky; klik → popup s odjezdy; odkaz vede na tabuli
- [x] Po Markově ostrém importu: přiblížit Prahu a ověřit, že se mapa neseká (max 500 značek na výřez)

## Co potřebuješ od ostatních
| od koho | co |
|---|---|
| Marek | Ion Auth v `composer.json`, balíčky `leaflet`, `leaflet.markercluster`, `qr-creator`, modely `Zastavka` (`najdi`, `vyrez`), `Odjezd::nejblizsi`, `Nastaveni::hodnota`, seeder |
| Tomáš | layout se sekcí `skripty` a `layout/alert.php`, Pager šablona, `Main.php` (přidáš do něj `mapa()`) |

## Co předáváš
| komu | co | kdy |
|---|---|---|
| Tomáš | `BaseController` s `$this->data['alert']`, `$this->ionAuth` | fáze 1 |
| Tomáš | kousek kódu do navbaru (přihlášen/odhlásit) | fáze 2 |
