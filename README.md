# CodeIgniter 4 Application Starter
dalsi projekt uz zaponat nebudu

## Odjezdové tabule – data a import

Plán a rozdělení práce je v [coUdelat/00-plan.md](coUdelat/00-plan.md).

**Data platí jen do 13. 12. 2026.** Jsou to jízdní řády ze dne 1. 11. 2026
(`https://data.jr.ggu.cz/results/2026-11-01/`) a importuje se období 2. 11. – 13. 12. 2026.
Pak budou všechny tabule prázdné. Na pozdější předvádění stačí v `app/Config/Jizdnirady.php`
změnit adresy zipů, `dataZeDne`, `oknoOd` a `oknoDo`, smazat staré zipy z `writable/gtfs/`
a import spustit znovu.

```bash
composer install
php spark migrate
# + v phpMyAdminu naimportovat vendor/benedmunds/codeigniter-ion-auth/sql/ion_auth.sql
php spark db:seed TestovaciData   # malá testovací data (okolí Uherského Hradiště)
php spark jr:import               # ostrá data celé ČR (vlak i bus), nebo jen: jr:import vlak
php spark jr:odjezdy vlak uherske-hradiste   # kontrola tabule z příkazové řádky
```

Import (měřeno 9. 11. 2026, Laragon, PHP 8.5, MySQL 8.0):

| | |
|---|---|
| stažení obou zipů | ~200 MB, ukládají se do `writable/gtfs/` (v gitu nejsou) |
| doba importu | 3–5,5 min (+ stažení zipů, ve škole ~20 s) |
| databáze | ~400 MB (z toho tabulka `odjezd` ~360 MB) |
| výsledek | 40 212 zastávek, 271 663 spojů, 4,57 mil. odjezdů |
| paměť | max. ~240 MB (příkaz si nastaví `memory_limit` 1536M) |

Zipy se stahují jen jednou. Když už v `writable/gtfs/` jsou (`vlak.zip`, `bus.zip`),
import je použije. Kdyby složka `2026-11-01` ze serveru zmizela, stačí si zipy
nakopírovat od někoho z týmu, proto je dobré mít jejich zálohu mimo git.

Jak import zachází s daty:
- zastávky se spojují podle názvu (víc GTFS `stop_id` = jedna zastávka, nástupiště patří ke stanici),
  stejně pojmenované zastávky víc než 2 km od sebe zůstanou zvlášť (např. `vlachovice` a `vlachovice-2`),
- do URL a QR kódů jde `typ/slug` z názvu, nikdy GTFS id (autobusová id se s každým vydáním dat mění),
- konečná zastávka spoje a zastávky bez možnosti nástupu se do odjezdů nedávají,
- spoje, které jsou v datech víckrát (stejná linka v několika verzích se stejnými časy), se berou jednou.

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

This repository holds a composer-installable app starter.
It has been built from the
[development repository](https://github.com/codeigniter4/CodeIgniter4).

More information about the plans for version 4 can be found in [CodeIgniter 4](https://forum.codeigniter.com/forumdisplay.php?fid=28) on the forums.

You can read the [user guide](https://codeigniter.com/user_guide/)
corresponding to the latest version of the framework.

## Installation & updates

`composer create-project codeigniter4/appstarter` then `composer update` whenever
there is a new release of the framework.

When updating, check the release notes to see if there are any changes you might need to apply
to your `app` folder. The affected files can be copied or merged from
`vendor/codeigniter4/framework/app`.

## Setup

Copy `env` to `.env` and tailor for your app, specifically the baseURL
and any database settings.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.

## Server Requirements

PHP version 8.2 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

> [!WARNING]
> - The end of life date for PHP 7.4 was November 28, 2022.
> - The end of life date for PHP 8.0 was November 26, 2023.
> - The end of life date for PHP 8.1 was December 31, 2025.
> - If you are still using below PHP 8.2, you should upgrade immediately.
> - The end of life date for PHP 8.2 will be December 31, 2026.

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library
