<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Texty alertů (ref. 08 §4.5). Klíče ve tvaru {typ}success / {typ}danger.
 */
class Alert extends BaseConfig
{
    public array $alertMessage = [
        'loginsuccess'  => 'Přihlášení proběhlo úspěšně.',
        'logindanger'   => 'Špatný e-mail nebo heslo.',
        'logoutsuccess' => 'Byl(a) jste odhlášen(a).',
        'logoutdanger'  => 'Odhlášení se nepovedlo.',
        'filterdanger'  => 'Do administrace se musíte přihlásit.',
        'heslosuccess'  => 'Heslo bylo změněno.',
        'heslodanger'   => 'Heslo se nezměnilo, současné heslo není správně.',
        // kontrola nového hesla před voláním Ion Auth (délka a shoda obou polí)
        'heslonovedanger' => 'Nové heslo musí mít aspoň 8 znaků a v obou polích musí být stejné.',
    ];
}
