<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Odjezdy') ?></title>
    <?= $this->include('layout/css') ?>
</head>
<body class="d-flex flex-column min-vh-100">
    <?= $this->include('layout/navbar') ?>

    <main class="container my-4 flex-grow-1">
        <?= $this->include('layout/alert') ?>

        <!-- Dynamický obsah stránky -->
        <?= $this->renderSection('content') ?>
    </main>

    <footer class="border-top py-3 small text-body-secondary">
        <div class="container">
            Jízdní řád ze dne 1. 11. 2026, platný do 13. 12. 2026. Statický, bez zpoždění.
            Autor dat upozorňuje, že nemusí být dost přesná pro plánování cest.
            Data: CIS JŘ (Ministerstvo dopravy) přes
            <a href="https://data.jr.ggu.cz/" class="link-secondary">data.jr.ggu.cz</a>,
            mapa © přispěvatelé <a href="https://www.openstreetmap.org/copyright" class="link-secondary">OpenStreetMap</a>.
        </div>
    </footer>

    <script src="<?= base_url('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js') ?>"></script>
    <!-- JS konkrétní stránky (Chart.js, Leaflet …) -->
    <?= $this->renderSection('skripty') ?>
</body>
</html>
