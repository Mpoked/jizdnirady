<?php

namespace App\Models;

use CodeIgniter\Model;

class Odjezd extends Model
{
    protected $table            = 'odjezd';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['spoj_id', 'zastavka_id', 'cas'];

    protected bool $allowEmptyInserts = false;
    protected bool $updateOnlyChanged = true;

    protected array $casts = [];
    protected array $castHandlers = [];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Nejbližší odjezdy ze zastávky od tohoto okamžiku.
     *
     * Kvůli spojům přes půlnoc se berou tři dny provozu: včera (časy > 24:00,
     * které jedou dnes v noci), dnes (od teď) a zítra (celý den).
     * Vrací pole objektů podle smlouvy v coUdelat/00-plan.md:
     * cas, datum, zaMinut, typ, linka, cisloSpoje, cil, dopravce.
     *
     * $ted (timestamp) je jen pro testování, web ho nevyplňuje.
     */
    public function nejblizsi(int $zastavkaId, int $pocet, ?int $ted = null): array
    {
        $ted ??= time();
        $dnes  = date('Y-m-d', $ted);
        $vcera = date('Y-m-d', strtotime('-1 day', $ted));
        $zitra = date('Y-m-d', strtotime('+1 day', $ted));
        // sekundy od půlnoci podle hodin na zdi (stejně jako časy v GTFS)
        $s = (int) date('G', $ted) * 3600 + (int) date('i', $ted) * 60 + (int) date('s', $ted);

        $d = $this->db->escape($dnes);
        $v = $this->db->escape($vcera);
        $z = $this->db->escape($zitra);

        // poradi = sekundy od dnešní půlnoci (včerejší noční spoje i zítřek v jedné řadě).
        // DISTINCT: některé linky jsou v datech ve více verzích se stejnými časy,
        // stejný odjezd (linka, číslo, směr, čas) se tak ukáže jen jednou.
        $radky = $this->distinct()
            ->select("DATEDIFF(provoz.datum, $d) * 86400 + odjezd.cas AS poradi", false)
            ->select('linka.typ, linka.cislo AS linka, spoj.cislo AS cisloSpoje, spoj.cil, linka.dopravce')
            ->join('spoj', 'spoj.id = odjezd.spoj_id', 'inner')
            ->join('linka', 'linka.id = spoj.linka_id', 'inner')
            ->join('provoz', 'provoz.sluzba_id = spoj.sluzba_id', 'inner')
            ->where('odjezd.zastavka_id', $zastavkaId)
            ->whereIn('provoz.datum', [$vcera, $dnes, $zitra])
            ->where("((provoz.datum = $d AND odjezd.cas >= $s)"
                . " OR (provoz.datum = $v AND odjezd.cas >= " . ($s + 86400) . ')'
                . " OR provoz.datum = $z)", null, false)
            ->orderBy('poradi')
            ->findAll($pocet);

        $odjezdy = [];
        [$rok, $mesic, $den] = array_map('intval', explode('-', $dnes));

        foreach ($radky as $r) {
            // 25:10 od dnešní půlnoci je zítra 1:10 (mktime si s tím poradí i při změně času)
            $p   = (int) $r->poradi;
            $kdy = mktime(intdiv($p, 3600), intdiv($p % 3600, 60), $p % 60, $mesic, $den, $rok);

            $odjezdy[] = (object) [
                'cas'        => date('H:i', $kdy),
                'datum'      => date('Y-m-d', $kdy),
                'zaMinut'    => max(0, intdiv($kdy - $ted, 60)),
                'typ'        => $r->typ,
                'linka'      => $r->linka,
                'cisloSpoje' => $r->cisloSpoje,
                'cil'        => $r->cil,
                'dopravce'   => $r->dopravce,
            ];
        }

        return $odjezdy;
    }

    /**
     * Počty odjezdů ze zastávky v jednotlivých dnech aktuálního týdne (Po–Ne).
     * Vrací vždy 7 položek, např. ['2026-11-02' => 412, '2026-11-03' => 398, …].
     */
    public function tydenniPocty(int $zastavkaId): array
    {
        $pondeli = strtotime('monday this week');

        // nejdřív všechny dny s nulou, aby jich bylo vždy 7
        $pocty = [];
        for ($i = 0; $i < 7; $i++) {
            $pocty[date('Y-m-d', strtotime("+$i day", $pondeli))] = 0;
        }

        // stejný odjezd z více verzí linky se počítá jednou (stejně jako na tabuli)
        $radky = $this->select('provoz.datum, COUNT(DISTINCT odjezd.cas, linka.cislo, spoj.cislo, spoj.cil) AS pocet', false)
            ->join('spoj', 'spoj.id = odjezd.spoj_id', 'inner')
            ->join('linka', 'linka.id = spoj.linka_id', 'inner')
            ->join('provoz', 'provoz.sluzba_id = spoj.sluzba_id', 'inner')
            ->where('odjezd.zastavka_id', $zastavkaId)
            ->where('provoz.datum >=', array_key_first($pocty))
            ->where('provoz.datum <=', array_key_last($pocty))
            ->groupBy('provoz.datum')
            ->findAll();

        foreach ($radky as $r) {
            $pocty[$r->datum] = (int) $r->pocet;
        }

        return $pocty;
    }
}
