<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * Každý píše jen do svého bloku (coUdelat/00-plan.md → Společné soubory).
 */

// ===== Tomáš – veřejná část a mapa =====
$routes->get('/', 'Main::index');
$routes->get('tabule/(:segment)/(:segment)', 'Main::tabule/$1/$2');
$routes->get('mapa', 'Main::mapa');

// ===== Marek – API =====
$routes->get('api/zastavky', 'Api::zastavky');
$routes->get('api/odjezdy/(:segment)/(:segment)', 'Api::odjezdy/$1/$2');

// ===== Tomáš – administrace =====
// TOMÁŠI: filtr je zakomentovaný, protože bez aliasu 'auth' v app/Config/Filters.php
// padá i `php spark routes`. Až alias přidáš, odkomentuj ho (jinak administrace není chráněná!).
$routes->get('prihlaseni', 'Login::login');
$routes->post('prihlaseni', 'Login::loginFinish');
$routes->group('administrace', [/* 'filter' => 'auth' */], static function ($routes) {
    $routes->get('/', 'Administrace::index');
    $routes->get('qr', 'Administrace::qr');
    $routes->get('qr/tisk', 'Administrace::qrTisk');
    $routes->get('odhlaseni', 'Login::logout');
});
