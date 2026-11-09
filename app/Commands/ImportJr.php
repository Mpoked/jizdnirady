<?php

namespace App\Commands;

use App\Libraries\GtfsImport;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Import jízdních řádů: php spark jr:import [bus|vlak]
 * Bez argumentu importuje oba feedy (nejdřív vlaky, jsou menší).
 */
class ImportJr extends BaseCommand
{
    protected $group       = 'Jizdni rady';
    protected $name        = 'jr:import';
    protected $description = 'Stáhne GTFS data z data.jr.ggu.cz a naplní jimi databázi (tabulky vyprázdní).';
    protected $usage       = 'jr:import [bus|vlak]';
    protected $arguments   = [
        'typ' => 'jen jeden feed (bus nebo vlak), na testování',
    ];

    public function run(array $params)
    {
        // stop_times.txt má přes 1 GB, v paměti jsou mapy id na statisíce spojů
        ini_set('memory_limit', '1536M');
        set_time_limit(0);

        $typ = $params[0] ?? null;
        if ($typ !== null && ! in_array($typ, ['bus', 'vlak'], true)) {
            CLI::error('Neznámý typ, povolené jsou bus nebo vlak.');

            return EXIT_USAGE;
        }
        $typy = $typ === null ? ['vlak', 'bus'] : [$typ];

        CLI::write('Import jízdních řádů: ' . implode(', ', $typy), 'yellow');

        try {
            $import = new GtfsImport(static function (string $text): void {
                CLI::write('[' . date('H:i:s') . '] ' . $text);
            });
            $pocty = $import->spust($typy);
        } catch (Throwable $e) {
            CLI::error('Import spadl: ' . $e->getMessage());
            CLI::error($e->getFile() . ':' . $e->getLine());
            log_message('error', 'jr:import spadl: {msg} v {file}:{line}', [
                'msg'  => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return EXIT_ERROR;
        }

        CLI::write(sprintf(
            'Zastávek %s, spojů %s, odjezdů %s, špička paměti %.0f MB.',
            number_format($pocty['pocet_zastavek'], 0, ',', ' '),
            number_format($pocty['pocet_spoju'], 0, ',', ' '),
            number_format($pocty['pocet_odjezdu'], 0, ',', ' '),
            memory_get_peak_usage(true) / 1048576,
        ), 'green');

        return EXIT_SUCCESS;
    }
}
