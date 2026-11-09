<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<style>
    .qr-karta { break-inside: avoid; page-break-inside: avoid; }
    .qr-karta .qr canvas { width: 100%; max-width: 300px; height: auto; }
    @media print {
        /* Na papíře jen kartičky: bez navbaru, patičky a tlačítek */
        nav, footer, .no-print { display: none !important; }
        main.container { max-width: none; margin: 0 !important; padding: 0; }
        body { background: #fff !important; color: #000 !important; }
        .qr-karta { height: 13.5cm; border: 1px dashed #999 !important; } /* 2 kartičky na A4 */
        @page { size: A4; margin: 1cm; }
    }
</style>

<div class="no-print d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <h1 class="mb-0"><i class="fa-solid fa-print"></i> Tisk QR kódů</h1>
    <div>
        <a href="<?= base_url('administrace/qr') ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left"></i> Zpět</a>
        <?php if (! empty($zastavky)) : ?>
            <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fa-solid fa-print"></i> Vytisknout</button>
        <?php endif ?>
    </div>
</div>

<?php if (empty($zastavky)) : ?>
    <div class="alert alert-warning no-print">Nevybrali jste žádnou zastávku.</div>
<?php endif ?>

<?php foreach ($zastavky as $z) : ?>
    <?php $url = site_url("tabule/{$z->typ}/{$z->slug}") ?>
    <div class="qr-karta card mb-4 bg-white text-dark">
        <div class="card-body text-center d-flex flex-column justify-content-center">
            <h2 class="h3 fw-bold">
                <i class="fa-solid <?= $z->typ === 'vlak' ? 'fa-train' : 'fa-bus' ?>"></i>
                <?= esc($z->nazev) ?>
            </h2>
            <p class="fs-5 mb-3">Aktuální odjezdy – naskenujte telefonem</p>
            <!-- QR obsahuje typ/slug, nikdy GTFS id (to se při nových datech mění) -->
            <div class="qr mx-auto mb-2" data-url="<?= esc($url) ?>"></div>
            <p class="small text-break mb-0"><?= esc($url) ?></p>
        </div>
    </div>
<?php endforeach ?>
<?= $this->endSection() ?>

<?= $this->section('skripty') ?>
<script src="<?= base_url('node_modules/qr-creator/dist/qr-creator.min.js') ?>"></script>
<script>
    document.querySelectorAll('.qr').forEach(el => {
        QrCreator.render({ text: el.dataset.url, radius: 0.3, ecLevel: 'M', fill: '#000', background: '#fff', size: 300 }, el);
    });
</script>
<?= $this->endSection() ?>
