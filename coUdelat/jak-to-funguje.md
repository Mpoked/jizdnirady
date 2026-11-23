# Jak to funguje: přihlášení, administrace, QR, API a mapa

Popis části, kterou měl původně Matěj ([Matej.md](Matej.md)). Stav k 23. 11. 2026.

## Soubory

| soubor | co dělá |
|---|---|
| `app/Config/Alert.php` | texty hlášek, klíč = `{typ}success` / `{typ}danger` |
| `app/Libraries/Alert.php` | `makeMessage($status, $typ)` vrátí objekt `{class, message}` pro Bootstrap alert |
| `app/Controllers/BaseController.php` | společný základ: helpery, session, Ion Auth, alert z flashdat, `$this->data` |
| `app/Views/layout/alert.php` | vypíše `$alert` pod navbarem (když `message` není prázdné) |
| `app/Config/IonAuth.php` | nastavení Ion Auth, přihlašuje se e-mailem |
| `app/Filters/LoginFilter.php` + alias `auth` v `Config/Filters.php` | nepřihlášeného pošle na `/prihlaseni` |
| `app/Controllers/Login.php`, `Views/login/login.php` | přihlášení a odhlášení |
| `app/Controllers/Administrace.php`, `Views/administrace/*` | přehled dat, výběr a tisk QR, změna hesla |
| `app/Controllers/Api.php` | JSON pro mapu |
| `Main::mapa()`, `app/Views/mapa.php` | mapa zastávek (Leaflet) |
| `app/Config/Routes.php` | bloky „Tomáš – administrace“ a „Marek – API“ |

## Routy

```
GET  prihlaseni                   Login::login            formulář
POST prihlaseni         [csrf]    Login::loginFinish      ověření, redirect
--- skupina administrace, filtr auth ---
GET  administrace                 Administrace::index     přehled
GET  administrace/qr              Administrace::qr        výběr zastávek
GET  administrace/qr/tisk?z[]=…   Administrace::qrTisk    tiskový arch
GET  administrace/heslo           Administrace::heslo     formulář změny hesla
POST administrace/heslo [csrf]    Administrace::zmenitHeslo
GET  administrace/odhlaseni       Login::logout
---
GET  mapa                         Main::mapa
GET  api/zastavky?s=&w=&n=&e=     Api::zastavky
GET  api/odjezdy/{typ}/{slug}     Api::odjezdy
```

Filtr skupiny a filtr u jednotlivé routy se v CI 4.7 **sčítají**, takže `POST administrace/heslo` má `auth` i `csrf` (ověřeno přes `php spark routes`).

## 1. Alerty (hlášky po přesměrování)

Postup podle ref. 08 §4.5:

1. Controller něco udělá (přihlásí, odhlásí, změní heslo) a dostane `true`/`false`.
2. `$this->alert->makeMessage($vysledek, 'login')` složí klíč `login` + `success`/`danger`, najde text v `Config\Alert::$alertMessage` a vrátí `{class: 'success', message: 'Přihlášení proběhlo úspěšně.'}`.
3. Objekt se uloží do **flashdat** (`$this->session->setFlashdata('alert', $alert)`). Flashdata vydrží jen do dalšího požadavku.
4. Redirect. Na nové stránce `BaseController::initController()` dá do `$this->data['alert']` prázdný alert, nebo ten z flashdat, když tam je.
5. Layout includuje `layout/alert.php` a ten alert vykreslí (`alert-success` / `alert-danger`, dá se zavřít křížkem).

Nová hláška = nový klíč v `Config/Alert.php`, nic dalšího se neupravuje.

## 2. BaseController

Každý controller dědí z `BaseController`, takže má vždycky:
- helpery `form`, `url`, `text`
- `$this->session` (session v souborech `writable/session`)
- `$this->ionAuth` (knihovna pro přihlášení)
- `$this->alert` (knihovna pro hlášky)
- `$this->data`: pole pro view, obsahuje `alert` a `prihlasen` (navbar podle toho ukáže „Odhlásit“)

Controllery do view posílají `$this->data += [...]` a `view('…', $this->data)` (ref. 11).

## 3. Přihlášení a odhlášení (Ion Auth)

- Tabulky `users`, `groups`, `users_groups`, `login_attempts` jsou z `vendor/benedmunds/codeigniter-ion-auth/sql/ion_auth.sql`. Výchozí účet je `admin@admin.com` / `password`.
- `Login::login()`: když už je uživatel přihlášený, pošle ho rovnou do administrace, jinak ukáže formulář.
- Formulář posílá `email`, `heslo` a **CSRF token** (`csrf_field()`). Bez platného tokenu vrátí CI chybu 403. Chrání to proti tomu, aby cizí web odeslal formulář za přihlášeného uživatele.
- `Login::loginFinish()`: `$this->ionAuth->login($email, $heslo)` porovná heslo s hashem v DB. Hashování řeší Ion Auth, my heslo nikde v čitelné podobě neukládáme. Při úspěchu jde redirect do administrace, při neúspěchu zpět na formulář s `withInput()` (e-mail zůstane vyplněný přes `old('email')`).
- Ion Auth počítá neúspěšné pokusy v `login_attempts`. Po **3 špatných heslech** zablokuje přihlášení na **10 minut** (`maximumLoginAttempts`, `lockoutTime`), a to i se správným heslem. Hláška je pořád „Špatný e-mail nebo heslo.“, takže když se při testování nejde přihlásit, chvíli počkej, nebo vymaž tabulku `login_attempts`.
- `Login::logout()`: `$this->ionAuth->logout()`, hláška a redirect na `/`.

## 4. Filtr `auth`

`LoginFilter::before()` běží **před** controllerem u každé routy ze skupiny `administrace`. Filtr není potomek `BaseController`, proto si Ion Auth, session a Alert vytváří sám. Když `loggedIn()` vrátí `false`, uloží hlášku „Do administrace se musíte přihlásit.“ a přesměruje na `/prihlaseni`. Controller se vůbec nespustí.

Ve vzoru `../administrace` je řádek `$this->alert = new Alert();` zakomentovaný, takže by filtr spadl. Tady je funkční.

## 5. Administrace

### Přehled (`/administrace`)
- Čísla (zastávky, spoje, odjezdy) a údaje o datech se berou z tabulky `nastaveni`, kterou vyplnil import (`Nastaveni::hodnota()`). `COUNT(*)` nad `odjezd` (4,5 mil. řádků) by byl pomalý.
- Červené varování, když má účet pořád heslo `password`. Kontroluje se přes `$this->ionAuth->verifyPassword('password', $hash)`, heslo se nikde neukládá.
- Žluté varování, když je dnešek po `okno_do` (13. 12. 2026). Pak jsou všechny tabule prázdné a je potřeba nový import.

### Výběr zastávek (`/administrace/qr`)
- Formulář GET s polem `q`, `like('nazev', $q)->orderBy('nazev')->paginate(25)` a Tomášova Pager šablona. Hledaný výraz zůstává i při stránkování (`?q=hradi&page=2`).
- Tabulka (`CodeIgniter\View\Table`, ref. 06): checkbox `z[]` s hodnotou `typ/slug`, ikona bus/vlak, název a tlačítko „Tisk“ pro jednu zastávku.
- Tlačítko „Vytisknout vybrané“ odešle zaškrtnuté zastávky GETem na `administrace/qr/tisk?z[]=vlak/uherske-hradiste&z[]=…` (nová záložka).

### Tiskový arch (`/administrace/qr/tisk`)
- `qrTisk()` vezme pole `z`, každou položku rozdělí na `typ` a `slug`, dohledá ji přes `Zastavka::najdi()` a neexistující přeskočí.
- Každá zastávka dostane kartičku: název s ikonou, text „Aktuální odjezdy – naskenujte telefonem“, QR kód a pod ním adresu malým písmem.
- QR vykresluje v prohlížeči knihovna **qr-creator**: najde každý `<div class="qr" data-url="…">` a vloží do něj `<canvas>` s kódem (`ecLevel: 'M'` = asi 15 % kódu může být poškozeno a pořád se přečte).
- Tisk: tlačítko volá `window.print()`. CSS v `@media print` schová navbar, patičku a tlačítka. Kartička má výšku 13,5 cm a `break-inside: avoid`, takže vyjdou **2 na A4** a žádná se nerozdělí mezi stránky (ověřeno tiskem do PDF).
- **QR obsahuje `tabule/{typ}/{slug}`, ne GTFS id.** Autobusová `stop_id` (`JDFS-1`…) se v každém vydání dat čísluje znovu, slug z názvu zůstává stejný. Vytištěné kódy tak přežijí i nový import.

### Změna hesla (`/administrace/heslo`), nové
1. Formulář: současné heslo, nové heslo a nové heslo znovu, plus CSRF token. Prohlížeč hlídá `required` a `minlength=8`.
2. `zmenitHeslo()` to zkontroluje znovu na serveru, protože prohlížeč se dá obejít: nové heslo musí mít aspoň `minPasswordLength` (8, z configu Ion Auth) znaků a obě pole musí být stejná. Jinak hláška `heslonove` a návrat na formulář.
3. `$this->ionAuth->changePassword($email, $stare, $nove)` ověří současné heslo a uloží hash nového. Při špatném současném heslu hláška `heslo` + danger, při úspěchu redirect do administrace s hláškou „Heslo bylo změněno.“

## 6. JSON API pro mapu (`Api.php`)

### `GET api/zastavky?s=&w=&n=&e=`
- `s, w, n, e` = jih, západ, sever, východ, tedy hranice výřezu mapy (`map.getBounds()`).
- Když některý chybí nebo to není číslo, vrátí **400** a `{"chyba": "…"}`, ne výjimku.
- `Zastavka::vyrez()` vybere zastávky v obdélníku, **nejvýš 500** (`mapaLimit` v `Config/Jizdnirady.php`). Když jich je ve výřezu víc, vrátí **ty nejblíž středu**: řadí podle vzdálenosti od středu, délka se násobí 0,64, protože stupeň délky je v ČR kratší než stupeň šířky. Dřív bral prvních 500 podle indexu, tedy od jihu, a v Praze na zoomu 13 pak chyběl celý sever výřezu.
- Vrací pole `{typ, slug, nazev, lat, lon}`.

### `GET api/odjezdy/{typ}/{slug}`
- `najdi()`, a když zastávka není, **404** a `{"chyba": "Zastávka neexistuje."}`.
- Jinak `{zastavka: {…}, url: "…/tabule/typ/slug", odjezdy: [...]}`. Odjezdy jsou z `Odjezd::nejblizsi($id, 5)`, stejná funkce jako pro tabuli (čas, datum, zaMinut, linka, cíl…). Spoje přes půlnoc se počítají správně.

## 7. Mapa (`/mapa`)

- `Main::mapa()` pošle do view `mapaMinZoom` (13) a `stred`, když přišlo platné `?lat=…&lon=…` (odkaz „Zobrazit na mapě“ z tabule). Neplatné souřadnice se ignorují.
- Ve view se v sekci `skripty` načte **Leaflet** a **Leaflet.markercluster** z `node_modules`. Dlaždice jsou z OpenStreetMap s atribucí „© přispěvatelé OpenStreetMap“.
- Start: střed ČR `[49.8, 15.5]`, zoom 7. S `lat/lon` střed na zastávce a zoom 16.
- Po každém posunu nebo zoomu (`moveend`) běží `nactiZastavky()`:
  - zoom < 13: smaže značky a ukáže „Přibližte mapu, aby se zobrazily zastávky.“ Zastávek je přes 40 tisíc, všechny najednou by mapu zasekly.
  - jinak `fetch('api/zastavky?s=…&w=…&n=…&e=…')`. Předchozí rozběhnutý požadavek se zruší (`AbortController`), aby pozdě došlá stará data nepřepsala nová.
  - **značky se nemažou všechny**: nové se přidají, ty mimo výřez se odeberou, ale ne ta s otevřeným popupem. Původně se volalo `clearLayers()` a popup se hned zavíral: otevření popupu posune mapu, tím přijde `moveend` a značka s popupem zmizela.
  - bus = modrá značka s autobusem, vlak = červená s vlakem. Blízké značky se při oddálení slučují do kroužků s počtem (markercluster), od zoomu 16 se zobrazí každá zvlášť.
- Klik na značku: popup ukáže „Načítám…“, pak `fetch('api/odjezdy/typ/slug')` a malá tabulka 5 odjezdů (čas, „zítra“ u odjezdu po půlnoci, linka, směr, za kolik minut) a odkaz „Celá tabule →“.
- Texty z dat jdou do HTML přes JS funkci `esc()`, protože názvy zastávek jsou z cizího zdroje (ochrana proti XSS).
- Tlačítko „Najdi mě“ (`map.locate()`) vycentruje mapu na polohu telefonu. Prohlížeč polohu dá jen na `https` nebo `localhost`.

## 8. Jak otestovat

| co | jak | výsledek 23. 11. |
|---|---|---|
| filtr | `/administrace/qr` bez přihlášení | redirect na `/prihlaseni` + hláška ✅ |
| špatné heslo | přihlásit se s jiným heslem | hláška, e-mail zůstane vyplněný ✅ |
| přihlášení / odhlášení | `admin@admin.com` | přehled s čísly, „Odhlásit“ v navbaru ✅ |
| změna hesla | neshoda, krátké, špatné současné, bez CSRF, správně | 3× hláška, 403, změněno ✅ |
| QR | 3 zastávky (2 existující + nesmysl) → tisk | nesmysl přeskočen, 2 kartičky na A4 ✅ |
| API | `/api/zastavky?s=49.0&w=17.4&n=49.1&e=17.5` | JSON ✅, bez parametrů 400 ✅, neexistující slug 404 ✅ |
| mapa | zoom 7 → hláška; Uherské Hradiště → značky; klik → popup | ✅, popup zůstává i po posunu mapy |
| výkon | Praha zoom 13 | 500 značek rovnoměrně kolem středu, API ~0,5 s ✅ |
| QR telefonem | — | **nezkoušeno** |

**QR z telefonu:** `localhost` v telefonu ukazuje na telefon, ne na počítač. Dočasně dej do `.env` `app.baseURL = 'http://192.168.x.x/pokorny/jizdnirady/'` (IP počítače: `ipconfig`), vytiskni QR znovu a telefon musí být ve stejné Wi-Fi. Po testu baseURL vrať.

## 9. Omezení, o kterých je dobré vědět

- Každý má vlastní DB, takže **výchozí heslo `password` si musí změnit každý u sebe** (administrace na to upozorní).
- Na velkém monitoru v Praze je ve výřezu na zoomu 13 víc než 500 zastávek a na krajích pak některé chybí. Po přiblížení se zobrazí. Limit jde zvednout v `Config/Jizdnirady.php` (`mapaLimit`).
- Data platí do 13. 12. 2026, potom jsou tabule i popupy prázdné a administrace ukáže varování.
- API dědí z `BaseController`, takže při každém volání nastartuje session a Ion Auth. Pro školní projekt to nevadí (~0,5 s v development režimu).
