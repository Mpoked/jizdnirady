<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Index provoz(sluzba_id, datum).
 *
 * Primární klíč (datum, sluzba_id) neumí rychle najít dny jedné služby, takže MySQL
 * u velkých zastávek (Praha,Florenc) začínal od tabulky provoz a týdenní počty trvaly 30 s.
 * S tímto indexem jde dotaz od odjezdů zastávky přes spoj rovnou do provozu.
 */
class ProvozIndex extends Migration
{
    public function up()
    {
        $this->forge->addKey(['sluzba_id', 'datum'], false, false, 'sluzba_datum');
        $this->forge->processIndexes('provoz');
    }

    public function down()
    {
        $this->forge->dropKey('provoz', 'sluzba_datum', false);
    }
}
