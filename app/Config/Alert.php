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
    ];
}
