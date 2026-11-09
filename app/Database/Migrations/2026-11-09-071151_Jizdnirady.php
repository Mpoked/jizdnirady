<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabulky pro odjezdové tabule (popis v coUdelat/00-plan.md → Databáze).
 * Cizí klíče schválně nejsou: import dělá TRUNCATE, a ten by s nimi nešel.
 */
class Jizdnirady extends Migration
{
    public function up()
    {
        // zastávka = jeden název (víc GTFS stop_id se stejným názvem = jedna zastávka)
        $this->forge->addField([
            'id'    => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'typ'   => ['type' => 'ENUM', 'constraint' => ['bus', 'vlak']],
            'nazev' => ['type' => 'VARCHAR', 'constraint' => 255],
            'slug'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'lat'   => ['type' => 'DECIMAL', 'constraint' => '9,6'],
            'lon'   => ['type' => 'DECIMAL', 'constraint' => '9,6'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['typ', 'slug']);
        $this->forge->addKey(['lat', 'lon']);
        $this->forge->addKey('nazev');
        $this->forge->createTable('zastavka');

        // linka z routes.txt + dopravce z agency.txt
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'typ'        => ['type' => 'ENUM', 'constraint' => ['bus', 'vlak']],
            'cislo'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'nazev'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'dopravce'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'route_type' => ['type' => 'SMALLINT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('linka');

        // spoj z trips.txt, cil = směr (headsign nebo název konečné)
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'linka_id'  => ['type' => 'INT', 'unsigned' => true],
            'sluzba_id' => ['type' => 'INT', 'unsigned' => true],
            'cislo'     => ['type' => 'VARCHAR', 'constraint' => 50],
            'cil'       => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('sluzba_id');
        $this->forge->createTable('spoj');

        // odjezd = spoj odjíždí ze zastávky v čase cas (sekundy od půlnoci, může být > 86 400)
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'spoj_id'     => ['type' => 'INT', 'unsigned' => true],
            'zastavka_id' => ['type' => 'INT', 'unsigned' => true],
            'cas'         => ['type' => 'MEDIUMINT', 'unsigned' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        // spoj_id je v indexu navíc, aby dotaz na tabuli nemusel sahat do celé tabulky
        $this->forge->addKey(['zastavka_id', 'cas', 'spoj_id']);
        $this->forge->createTable('odjezd');

        // provoz = ve které dny jede služba (jedinečný vzor dnů)
        $this->forge->addField([
            'sluzba_id' => ['type' => 'INT', 'unsigned' => true],
            'datum'     => ['type' => 'DATE'],
        ]);
        $this->forge->addPrimaryKey(['datum', 'sluzba_id']);
        $this->forge->createTable('provoz');

        // nastaveni = informace o importu (ref. 09 Nastavení v databázi)
        $this->forge->addField([
            'nazev'   => ['type' => 'VARCHAR', 'constraint' => 50],
            'hodnota' => ['type' => 'VARCHAR', 'constraint' => 255],
        ]);
        $this->forge->addPrimaryKey('nazev');
        $this->forge->createTable('nastaveni');
    }

    public function down()
    {
        $this->forge->dropTable('nastaveni', true);
        $this->forge->dropTable('provoz', true);
        $this->forge->dropTable('odjezd', true);
        $this->forge->dropTable('spoj', true);
        $this->forge->dropTable('linka', true);
        $this->forge->dropTable('zastavka', true);
    }
}
