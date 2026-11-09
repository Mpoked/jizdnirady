<?php

namespace App\Models;

use CodeIgniter\Model;

class Zastavka extends Model
{
    protected $table            = 'zastavka';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'object';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['typ', 'nazev', 'slug', 'lat', 'lon'];

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
     * Slug do URL z názvu zastávky: 'Uherské Hradiště,aut.nádr.' → 'uherske-hradiste-aut-nadr'.
     * Používá ho seeder i import, aby vycházel všude stejně.
     */
    public static function slug(string $nazev): string
    {
        helper(['url', 'text']);

        // čárky a tečky oddělují části názvu, url_title() by je jen smazal a slova slepil
        $nazev = str_replace([',', '.', '/', '(', ')'], ' ', $nazev);

        return url_title(convert_accented_characters($nazev), '-', true);
    }

    /**
     * Najde zastávku podle typu a slugu z URL (tabule/{typ}/{slug}).
     * Vrací objekt zastávky, nebo null, když neexistuje.
     */
    public function najdi(string $typ, string $slug): ?object
    {
        return $this->where('typ', $typ)->where('slug', $slug)->first();
    }

    /**
     * Zastávky v obdélníku mapy (jih, západ, sever, východ), nejvýš $limit kusů.
     */
    public function vyrez(float $s, float $w, float $n, float $e, int $limit): array
    {
        return $this->where('lat >=', $s)
            ->where('lat <=', $n)
            ->where('lon >=', $w)
            ->where('lon <=', $e)
            ->findAll($limit);
    }
}
