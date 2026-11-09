<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<?php
// Datum z DB (Y-m-d) do českého zápisu
$cesky = static fn ($d) => $d ? date('j. n. Y', strtotime($d)) : '–';
$cislo = static fn ($c) => $c !== null ? number_format((int) $c, 0, ',', ' ') : '–';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="mb-0"><i class="fa-solid fa-gear"></i> Administrace</h1>
    <span class="text-body-secondary"><i class="fa-solid fa-user"></i> <?= esc($uzivatel->email ?? '') ?></span>
</div>

<?php if ($neplatne) : ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Data platila jen do <?= $cesky($n->okno_do) ?>. Všechny tabule jsou teď prázdné. Je potřeba změnit období v configu a spustit znovu <code>php spark jr:import</code>.
    </div>
<?php endif ?>

<div class="row row-cols-1 row-cols-sm-3 g-3 mb-4">
    <div class="col">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small"><i class="fa-solid fa-location-dot"></i> Zastávek</div>
            <div class="fs-2 fw-bold"><?= $cislo($n->pocet_zastavek) ?></div>
        </div></div>
    </div>
    <div class="col">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small"><i class="fa-solid fa-route"></i> Spojů</div>
            <div class="fs-2 fw-bold"><?= $cislo($n->pocet_spoju) ?></div>
        </div></div>
    </div>
    <div class="col">
        <div class="card h-100"><div class="card-body">
            <div class="text-body-secondary small"><i class="fa-solid fa-clock"></i> Odjezdů</div>
            <div class="fs-2 fw-bold"><?= $cislo($n->pocet_odjezdu) ?></div>
        </div></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h2 class="h5 card-title">Data</h2>
        <dl class="row mb-0">
            <dt class="col-sm-4">Jízdní řád ze dne</dt>
            <dd class="col-sm-8"><?= $cesky($n->data_ze_dne) ?></dd>
            <dt class="col-sm-4">Platnost</dt>
            <dd class="col-sm-8"><?= $cesky($n->okno_od) ?> – <?= $cesky($n->okno_do) ?></dd>
            <dt class="col-sm-4">Import proběhl</dt>
            <dd class="col-sm-8 mb-0"><?= $n->importovano ? date('j. n. Y H:i', strtotime($n->importovano)) : 'zatím ne' ?></dd>
        </dl>
    </div>
</div>

<a href="<?= base_url('administrace/qr') ?>" class="btn btn-primary btn-lg">
    <i class="fa-solid fa-qrcode"></i> QR kódy zastávek
</a>
<?= $this->endSection() ?>
