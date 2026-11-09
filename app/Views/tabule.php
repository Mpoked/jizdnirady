<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<style>
    /* Vzhled nádražní tabule - tmavá v obou režimech */
    .tabule {
        background: #0b1a33;
        color: #fff;
        border-radius: .5rem;
        font-family: "Segoe UI", Roboto, Arial, sans-serif;
    }
    .tabule .table {
        --bs-table-bg: transparent;
        --bs-table-color: #fff;
        --bs-table-border-color: #23365a;
        --bs-table-hover-bg: #142a4f;
        --bs-table-hover-color: #fff;
        margin-bottom: 0;
    }
    .tabule th { color: #9fb3d9; font-weight: 600; font-size: .8rem; text-transform: uppercase; }
    .tabule .cas { color: #ffd23f; font-size: 1.5rem; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .tabule .zitra { display: block; color: #ffd23f; font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; }
    .tabule .linka { font-weight: 700; white-space: nowrap; }
    .tabule .spoj { display: block; color: #9fb3d9; font-size: .75rem; font-weight: 400; }
    .tabule .smer { font-weight: 600; overflow-wrap: anywhere; }
    .tabule .za { color: #ffd23f; white-space: nowrap; text-align: end; font-variant-numeric: tabular-nums; }
    .tabule .hodiny { color: #ffd23f; font-size: 1.75rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    @media (max-width: 575.98px) {
        .tabule .cas { font-size: 1.2rem; }
        .tabule td, .tabule th { padding: .5rem .35rem; }
    }
</style>

<div class="tabule p-3 p-md-4 mb-3">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <h1 class="h3 mb-0">
            <i class="fa-solid <?= $zastavka->typ === 'vlak' ? 'fa-train' : 'fa-bus' ?>"></i>
            <?= esc($zastavka->nazev) ?>
        </h1>
        <div class="hodiny" id="hodiny"><?= date('H:i') ?></div>
    </div>

    <?php if (empty($odjezdy)) : ?>
        <p class="fs-5 mb-0 py-4 text-center">
            <?php if ($dnes > $platiDo) : ?>
                Jízdní řád platí jen do <?= date('j. n. Y', strtotime($platiDo)) ?>.
            <?php else : ?>
                Z této zastávky teď nic nejede.
            <?php endif ?>
        </p>
    <?php else : ?>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Odjezd</th>
                    <th>Linka</th>
                    <th>Směr</th>
                    <th class="d-none d-md-table-cell">Dopravce</th>
                    <th class="text-end">Za</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($odjezdy as $o) : ?>
                    <tr>
                        <td class="cas">
                            <?php if ($o->datum !== $dnes) : ?><span class="zitra">zítra</span><?php endif ?>
                            <?= esc($o->cas) ?>
                        </td>
                        <td class="linka">
                            <?= esc($o->linka) ?>
                            <?php if (! empty($o->cisloSpoje)) : ?><span class="spoj"><?= esc($o->cisloSpoje) ?></span><?php endif ?>
                        </td>
                        <td class="smer"><?= esc($o->cil) ?></td>
                        <td class="d-none d-md-table-cell"><?= esc($o->dopravce) ?></td>
                        <td class="za">
                            <?php if ($o->zaMinut <= 0) : ?>
                                odjíždí
                            <?php elseif ($o->zaMinut < 60) : ?>
                                <?= (int) $o->zaMinut ?> min
                            <?php else : ?>
                                <?= intdiv((int) $o->zaMinut, 60) ?> h <?= (int) $o->zaMinut % 60 ?> min
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    <?php endif ?>
</div>

<p class="d-flex flex-wrap gap-2 justify-content-between">
    <a href="<?= site_url('mapa') . '?' . http_build_query(['lat' => $zastavka->lat, 'lon' => $zastavka->lon]) ?>" class="btn btn-outline-primary">
        <i class="fa-solid fa-map-location-dot"></i> Zobrazit na mapě
    </a>
    <a href="<?= base_url('/') ?>" class="btn btn-outline-secondary">
        <i class="fa-solid fa-magnifying-glass"></i> Jiná zastávka
    </a>
</p>

<!-- Týdenní graf odjezdů -->
<div class="card mb-3">
    <div class="card-body">
        <h2 class="h5 card-title">Počet odjezdů tento týden</h2>
        <div style="position: relative; height: 260px;">
            <canvas id="graf" role="img"
                    aria-label="Počet odjezdů: <?= esc(implode(', ', array_map(fn ($p, $h) => "$p $h", $popisky, $hodnoty))) ?>"></canvas>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('skripty') ?>
<script>
    // Hodiny vpravo nahoře
    const hodiny = document.getElementById('hodiny');
    setInterval(() => {
        hodiny.textContent = new Date().toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
    }, 1000);

    // Obnovení tabule každou minutu
    setTimeout(() => location.reload(), 60000);
</script>
<script src="<?= base_url('node_modules/chart.js/dist/chart.umd.js') ?>"></script>
<script>
    // Data z controlleru
    const popisky   = <?= json_encode($popisky) ?>;
    const hodnoty   = <?= json_encode($hodnoty) ?>;
    const dnesniDen = <?= json_encode($dnesniDen) ?>;

    // Barvy z aktuálního tématu (světlý / tmavý režim)
    const styl      = getComputedStyle(document.documentElement);
    const primarni  = styl.getPropertyValue('--bs-primary').trim();
    const ostatni   = styl.getPropertyValue('--bs-secondary-color').trim();
    const textBarva = styl.getPropertyValue('--bs-body-color').trim();
    const mrizka    = styl.getPropertyValue('--bs-border-color-translucent').trim();

    // Dnešní den má v popisku „dnes“, aby nebyl odlišený jen barvou
    const popiskyOsy = popisky.map((p, i) => i === dnesniDen ? [p, 'dnes'] : p);

    new Chart(document.getElementById('graf'), {
        type: 'bar',
        data: {
            labels: popiskyOsy,
            datasets: [{
                label: 'Odjezdy',
                data: hodnoty,
                backgroundColor: hodnoty.map((_, i) => i === dnesniDen ? primarni : ostatni),
                borderRadius: 4,
                borderSkipped: 'bottom',
                maxBarThickness: 48,
            }],
        },
        options: {
            animation: false,          // při obnovení stránky každou minutu nesmí graf blikat
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { title: (p) => popisky[p[0].dataIndex] } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: textBarva } },
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: textBarva },
                    grid: { color: mrizka },
                    border: { display: false },
                },
            },
        },
    });
</script>
<?= $this->endSection() ?>
