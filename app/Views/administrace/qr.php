<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('administrace') ?>">Administrace</a></li>
        <li class="breadcrumb-item active" aria-current="page">QR kódy</li>
    </ol>
</nav>

<h1 class="mb-4"><i class="fa-solid fa-qrcode"></i> QR kódy zastávek</h1>

<?= form_open('administrace/qr', ['method' => 'get', 'class' => 'mb-4', 'role' => 'search']) ?>
<div class="input-group">
    <?= form_input([
        'name'        => 'q',
        'value'       => $q,
        'class'       => 'form-control',
        'placeholder' => 'Název zastávky',
        'aria-label'  => 'Název zastávky',
    ]) ?>
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Hledat</button>
</div>
<?= form_close() ?>

<?php if (empty($zastavky)) : ?>
    <div class="alert alert-warning">
        <?php if ($q !== '') : ?>
            Žádná zastávka neodpovídá výrazu „<?= esc($q) ?>“.
        <?php else : ?>
            V databázi zatím nejsou žádné zastávky.
        <?php endif ?>
    </div>
<?php else : ?>
    <!-- Formulář pro tisk vybraných zastávek (GET na tiskový arch) -->
    <?= form_open('administrace/qr/tisk', ['method' => 'get', 'target' => '_blank']) ?>
    <?php
    $table = new \CodeIgniter\View\Table();
    $table->setTemplate([
        'table_open'         => '<table class="table table-hover align-middle">',
        'thead_open'         => '<thead>',
        'thead_close'        => '</thead>',
        'heading_row_start'  => '<tr>',
        'heading_row_end'    => '</tr>',
        'heading_cell_start' => '<th>',
        'heading_cell_end'   => '</th>',
        'tbody_open'         => '<tbody>',
        'tbody_close'        => '</tbody>',
        'row_start'          => '<tr>',
        'row_end'            => '</tr>',
        'cell_start'         => '<td>',
        'cell_end'           => '</td>',
        'row_alt_start'      => '<tr>',
        'row_alt_end'        => '</tr>',
        'cell_alt_start'     => '<td>',
        'cell_alt_end'       => '</td>',
        'table_close'        => '</table>',
    ]);
    $table->setHeading('<span class="visually-hidden">Vybrat</span>', 'Typ', 'Název', '');

    foreach ($zastavky as $z) {
        $hodnota = "{$z->typ}/{$z->slug}";
        $id      = 'z-' . $z->id;
        $ikona   = $z->typ === 'vlak'
            ? '<i class="fa-solid fa-train fa-fw" title="Vlak"></i>'
            : '<i class="fa-solid fa-bus fa-fw" title="Autobus"></i>';

        $table->addRow(
            form_checkbox(['name' => 'z[]', 'value' => $hodnota, 'id' => $id, 'class' => 'form-check-input']),
            $ikona,
            '<label for="' . $id . '">' . esc($z->nazev) . '</label>',
            anchor('administrace/qr/tisk?' . http_build_query(['z' => [$hodnota]]), '<i class="fa-solid fa-print"></i> Tisk', ['class' => 'btn btn-sm btn-outline-secondary', 'target' => '_blank'])
        );
    }

    echo $table->generate();
    ?>

    <button type="submit" class="btn btn-primary mb-3"><i class="fa-solid fa-print"></i> Vytisknout vybrané</button>
    <?= form_close() ?>

    <?= $pager->links() ?>
<?php endif ?>
<?= $this->endSection() ?>
