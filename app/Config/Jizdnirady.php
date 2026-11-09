<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Nastavení aplikace Odjezdové tabule (ref. 09 Vlastní konfigurace).
 * V controlleru: $config = config('Jizdnirady'); $config->pocetOdjezdu
 */
class Jizdnirady extends BaseConfig
{
    // data jen z jednoho dne (složka s datem, ne "latest", aby měli všichni stejná)
    public array $zdroje = [
        'vlak' => 'https://data.jr.ggu.cz/results/2026-11-01/CZPTT_GTFS.zip',
        'bus'  => 'https://data.jr.ggu.cz/results/2026-11-01/JDF_merged_GTFS.zip',
    ];

    // ze kterého dne data jsou (ukazuje se v administraci)
    public string $dataZeDne = '2026-11-01';

    // jaké období se importuje (od pondělí 2. 11. do změny jízdního řádu)
    public string $oknoOd = '2026-11-02';
    public string $oknoDo = '2026-12-13';

    // počet odjezdů na tabuli
    public int $pocetOdjezdu = 10;

    // počet odjezdů v popupu na mapě
    public int $pocetOdjezduMapa = 5;

    // kolik řádků se vkládá jedním insertBatch při importu
    public int $davka = 5000;

    // od jakého přiblížení mapa ukazuje zastávky
    public int $mapaMinZoom = 13;

    // max. počet zastávek v jednom výřezu mapy
    public int $mapaLimit = 500;
}
