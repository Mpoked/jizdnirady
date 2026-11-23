# Nasazení na Debian server

Postup podle ref. 12 (Projekt s CI 4 na server), upravený pro náš projekt.

**Předpoklady:** Debian 12 nebo novější, přístup přes SSH a `sudo`. Web poběží v kořeni serveru, tedy `http://IP-serveru/`. Kde je níž `192.168.1.50`, dosaď IP (nebo doménu) svého serveru.

Když už na serveru běží Apache, PHP a MariaDB, krok 1 přeskoč a jen zkontroluj, že máš všechna PHP rozšíření ze seznamu.

| server potřebuje | kolik |
|---|---|
| PHP | **8.2 a vyšší** (Debian 12 má 8.2, Debian 13 má 8.4; Debian 11 s PHP 7.4 nestačí) |
| RAM | aspoň 1 GB (import + MariaDB) |
| disk | ~1,5 GB volno: projekt 130 MB, zipy s daty 200 MB, databáze ~400 MB |
| internet | při importu se stahuje ~200 MB z `data.jr.ggu.cz` (nebo zipy nahraješ ze svého PC, viz krok 6) |

---

## 1. Instalace balíčků

```bash
sudo apt update
sudo apt install apache2 libapache2-mod-php php-cli php-intl php-mbstring php-mysql php-curl php-zip \
                 mariadb-server git composer unzip
php -v                 # musí ukázat 8.2 nebo víc
sudo a2enmod rewrite   # bez mod_rewrite nefungují hezké adresy (tabule/vlak/…)
```

K čemu jsou rozšíření:
- `intl`, `mbstring`: vyžaduje je CodeIgniter
- `mysql`: připojení k MariaDB
- `curl`: import stahuje zipy
- `zip`: import čte CSV přímo ze zipu (`zip://`)

## 2. Databáze a uživatel

Pro web vytvoř vlastního uživatele databáze, ne `root`. Heslo si vymysli a ulož, budeš ho potřebovat v `.env`.

```bash
sudo mariadb
```
```sql
CREATE DATABASE jizdnirady CHARACTER SET utf8mb4 COLLATE utf8mb4_czech_ci;
CREATE USER 'jizdnirady'@'localhost' IDENTIFIED BY 'SEM-SILNE-HESLO';
GRANT ALL PRIVILEGES ON jizdnirady.* TO 'jizdnirady'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Kolace `utf8mb4_czech_ci` je potřeba, aby se názvy zastávek řadily česky (Č za C, Ř za R).

## 3. Stažení projektu z GitHubu

Repo je veřejné, takže token není potřeba.

```bash
cd /var/www
sudo git clone https://github.com/Mpoked/jizdnirady.git
cd /var/www/jizdnirady

# soubory patří tobě (git pull a composer bez sudo), skupina www-data, aby je Apache mohl číst
sudo chown -R $USER:www-data /var/www/jizdnirady

# knihovny z composeru (vendor/ v gitu není); node_modules/ v gitu je, přijde rovnou s clone
composer install --no-dev --optimize-autoloader
```

### Práva pro `writable/` (ref. 12, body 3 a 4)

PHP pod Apachem běží jako uživatel `www-data` (ověříš: `ps aux | grep apache`). Do `writable/` zapisuje session, cache, logy a import (zipy), proto musí patřit jemu:

```bash
sudo chown -R www-data:www-data writable
sudo chmod -R 775 writable
```

## 4. Soubor `.env`

`.env` není v gitu (je v `.gitignore`), na serveru ho založíš ručně:

```bash
nano /var/www/jizdnirady/.env
```
```ini
CI_ENVIRONMENT = production

app.baseURL = 'http://192.168.1.50/'

database.default.hostname = localhost
database.default.database = jizdnirady
database.default.username = jizdnirady
database.default.password = SEM-SILNE-HESLO
database.default.DBDriver = MySQLi
database.default.DBCollat = utf8mb4_czech_ci
```

```bash
# heslo k DB nemá číst nikdo kromě tebe a Apache
sudo chown $USER:www-data .env
chmod 640 .env
```

Poznámky:
- `baseURL` musí končit lomítkem. Z něj se skládají všechny odkazy **i adresa v QR kódech**, takže QR vytiskni až tady na serveru, ne lokálně (lokální QR vedou na `localhost`).
- `production` skryje chybové hlášky i debug toolbar. Chyby se pak zapisují do `writable/logs/`.
- `app/Config/App.php` je v gitu s Tomášovou adresou (`localhost/kotuc/…`). To nevadí, `app.baseURL` z `.env` ji přepíše.

## 5. Apache – virtual host

V projektu **není složka `public/`**, `index.php` je přímo v kořeni projektu. Webem jsou proto vidět všechny soubory projektu, včetně `.env` s heslem k databázi a celé složky `.git`. Lokálně to jde vyzkoušet: `http://localhost/pokorny/jizdnirady/.env` se dá stáhnout. Na serveru to **musí** zablokovat konfigurace níž.

```bash
sudo nano /etc/apache2/sites-available/jizdnirady.conf
```
```apache
<VirtualHost *:80>
    ServerName 192.168.1.50
    DocumentRoot /var/www/jizdnirady

    <Directory /var/www/jizdnirady>
        # bez AllowOverride All Apache ignoruje .htaccess: nefungují routy a app/ a writable/ jsou vidět
        AllowOverride All
        Require all granted
    </Directory>

    # Složky, které z webu nikdo stahovat nesmí (celý kód z .git, knihovny, plány, testy)
    <DirectoryMatch "^/var/www/jizdnirady/(\.git|vendor|coUdelat|tests|builds)(/|$)">
        Require all denied
    </DirectoryMatch>

    # Soubory, které z webu nikdo stahovat nesmí (.env s heslem k DB, .gitignore, composer…)
    <FilesMatch "^(\..*|composer\.(json|lock)|package(-lock)?\.json|spark|preload\.php|phpunit\.dist\.xml|README\.md|LICENSE)$">
        Require all denied
    </FilesMatch>

    ErrorLog ${APACHE_LOG_DIR}/jizdnirady-error.log
    CustomLog ${APACHE_LOG_DIR}/jizdnirady-access.log combined
</VirtualHost>
```

Komentáře v konfiguraci Apache musí být vždycky na samostatném řádku, ne za direktivou.

```bash
sudo a2ensite jizdnirady
sudo a2dissite 000-default        # výchozí stránka „It works!“ by jinak měla přednost
sudo apache2ctl configtest        # musí vypsat „Syntax OK“
sudo systemctl reload apache2
```

Když je na serveru zapnutý firewall `ufw`, povol web: `sudo ufw allow 80/tcp`.

## 6. Tabulky a data

Příkazy `spark` pouštěj jako `www-data` (`sudo -u www-data …`). Kdybys je pouštěl pod sebou, vytvořily by se logy a cache pod tvým uživatelem a web by do nich pak nemohl zapisovat.

```bash
cd /var/www/jizdnirady

# 1) tabulky projektu (zastavka, linka, spoj, odjezd, provoz, nastaveni)
sudo -u www-data php spark migrate

# 2) tabulky pro přihlášení (users, groups, …) + účet admin@admin.com / password
sudo mariadb jizdnirady < vendor/benedmunds/codeigniter-ion-auth/sql/ion_auth.sql

# 3) ostrá data celé ČR (stáhne ~200 MB a naplní DB, na notebooku to trvalo 3–5 min, na slabém serveru déle)
sudo -u www-data php spark jr:import
```

Import se pouští jen jednou. Aby ho nepřerušilo spadlé SSH, můžeš ho spustit v `tmux` (`sudo apt install tmux`, pak `tmux`, a po odpojení se vrátíš přes `tmux attach`).

**Zipy můžeš nahrát ze svého PC** místo stahování (rychlejší, a funguje to i kdyby složka `2026-11-01` z `data.jr.ggu.cz` zmizela). Na Windows v PowerShellu:
```powershell
scp C:\laragon\www\pokorny\jizdnirady\writable\gtfs\*.zip uzivatel@192.168.1.50:/tmp/
```
a na serveru:
```bash
sudo mkdir -p /var/www/jizdnirady/writable/gtfs
sudo mv /tmp/bus.zip /tmp/vlak.zip /var/www/jizdnirady/writable/gtfs/
sudo chown -R www-data:www-data /var/www/jizdnirady/writable/gtfs
```
Import pak zipy najde a nic nestahuje. Totéž jde naklikat ve WinSCP.

Rychlá kontrola z příkazové řádky:
```bash
sudo -u www-data php spark jr:odjezdy vlak uherske-hradiste
```

Proč databázi nepřenášet exportem z phpMyAdminu: má ~400 MB, phpMyAdmin obvykle bere jen pár MB a dump z MySQL 8 (Laragon) nemusí jít načíst do MariaDB (jiné kolace). Import na serveru je jednodušší.

## 7. Hned po spuštění: heslo admina

Web je teď vidět v síti a výchozí účet `admin@admin.com` / `password` zná každý, kdo kdy viděl Ion Auth.

1. `http://192.168.1.50/prihlaseni` → přihlásit se `admin@admin.com` / `password`
2. V administraci svítí červené varování → **Změnit heslo** (`/administrace/heslo`)

## 8. Kontrola

Z jiného počítače (nebo na serveru) vyzkoušej:

```bash
curl -I http://192.168.1.50/                                  # 200
curl -I http://192.168.1.50/tabule/vlak/uherske-hradiste      # 200
curl -I http://192.168.1.50/mapa                              # 200
curl -I http://192.168.1.50/node_modules/leaflet/dist/leaflet.js   # 200
curl "http://192.168.1.50/api/zastavky?s=49.0&w=17.4&n=49.1&e=17.5"   # JSON se zastávkami

# tohle všechno MUSÍ vrátit 403 (nebo 404), jinak není hotový krok 5
curl -I http://192.168.1.50/.env
curl -I http://192.168.1.50/.git/config
curl -I http://192.168.1.50/vendor/autoload.php
curl -I http://192.168.1.50/app/Config/Database.php
curl -I http://192.168.1.50/writable/logs/
curl -I http://192.168.1.50/coUdelat/00-plan.md
```

V prohlížeči:
- [ ] `/administrace` bez přihlášení přesměruje na přihlášení
- [ ] tabule ukazuje odjezdy a **správný čas**. Hodiny serveru musí jít přesně: `timedatectl` má ukázat `System clock synchronized: yes`. Časové pásmo bere PHP z `App.php` (`Europe/Prague`).
- [ ] mapa: po přiblížení značky, klik ukáže odjezdy
- [ ] **QR z telefonu:** v administraci vytiskni QR a naskenuj ho telefonem připojeným do stejné sítě. Musí otevřít tabuli na adrese serveru. Tohle lokálně nešlo, na serveru už ano.

## 9. Aktualizace po změnách v gitu

```bash
cd /var/www/jizdnirady
git pull
composer install --no-dev --optimize-autoloader
sudo -u www-data php spark migrate        # jen když přibyla migrace, jinak nic neudělá
sudo -u www-data php spark cache:clear
```

Import se znovu nepouští, data v DB zůstávají. `.env` se při `git pull` nepřepíše, protože není v gitu.

## 10. Když něco nejde

| příznak | příčina a řešení |
|---|---|
| úvodní stránka jde, ale `/tabule/…`, `/mapa` nebo `/prihlaseni` hlásí 404 Apache | nefunguje `.htaccess`: chybí `AllowOverride All` nebo `sudo a2enmod rewrite` + `systemctl reload apache2` |
| místo webu stránka „It works!“ | `sudo a2dissite 000-default` a reload |
| „Whoops! We seem to have hit a snag“ | chyba v produkčním režimu, detail je v `writable/logs/log-RRRR-MM-DD.log`. Na chvíli jde dát do `.env` `CI_ENVIRONMENT = development`, ale chyby pak uvidí každý, takže to vrať zpět |
| `Unable to connect to the database` | jméno, heslo nebo název DB v `.env` neodpovídá kroku 2 |
| chyby se zápisem do `writable/…` (session, cache) | práva: `sudo chown -R www-data:www-data writable` a `sudo chmod -R 775 writable` |
| `Class "IonAuth\…" not found` | chybí `vendor/`, je potřeba spustit `composer install --no-dev` |
| hláška o chybějícím `intl` | `sudo apt install php-intl` a `sudo systemctl restart apache2` |
| import spadne na `zip://` | chybí `php-zip` |
| import spadne na stahování | server nemá internet nebo zdroj zmizel, nahraj zipy ze svého PC (krok 6) |
| nejde se přihlásit ani správným heslem | po 3 špatných pokusech je přihlášení na 10 min zablokované. Počkej, nebo `sudo mariadb jizdnirady -e "TRUNCATE login_attempts"` |
| tabule jsou prázdné | data platí jen do 13. 12. 2026 (administrace pak ukáže varování), nebo neběžel `jr:import` |
| mapa nemá podklad | dlaždice si stahuje prohlížeč přímo z OpenStreetMap, počítač s prohlížečem potřebuje internet |
| tlačítko „Najdi mě“ nefunguje | prohlížeč polohu dá jen přes HTTPS, viz další krok |

## 11. HTTPS (volitelné, jen s doménou)

Certifikát Let's Encrypt jde vydat jen pro doménu, která míří na server:

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d jizdnirady.example.cz
```

V `.env` pak změň `app.baseURL = 'https://jizdnirady.example.cz/'` a QR kódy vytiskni znovu.

## Bez SSH (jen FTP a phpMyAdmin)

Postup přímo podle ref. 12: přes FTP nebo WinSCP nahrát celou složku projektu včetně `vendor/`, upravit `.env`, nastavit práva `writable/` a přenést databázi přes phpMyAdmin. S plnými daty to ale prakticky nejde: databáze má ~400 MB a `php spark jr:import` bez příkazové řádky nespustíš. Taky nejde nastavit Apache z kroku 5, takže by šel `.env` stáhnout. Bez SSH je potřeba požádat správce serveru o import databáze a o zablokování souborů z kroku 5.
