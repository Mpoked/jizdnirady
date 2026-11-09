<?php

namespace Config;

/**
 * Nastavení knihovny Ion Auth (ref. 10 §2.2).
 * Výchozí hodnoty jsou ve vendor/benedmunds/codeigniter-ion-auth/Config/IonAuth.php,
 * tady se přepisuje jen to, co potřebujeme jinak.
 */
class IonAuth extends \IonAuth\Config\IonAuth
{
    // přihlašuje se e-mailem
    public $identity = 'email';

    public $siteTitle  = 'Odjezdové tabule';
    public $adminEmail = 'admin@admin.com';
}
