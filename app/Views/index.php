<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<h1 class="mb-4"><i class="fa-solid fa-magnifying-glass"></i> Najít zastávku</h1>

<?= form_open('/', ['method' => 'get', 'class' => 'mb-4', 'role' => 'search']) ?>
<div class="input-group">
    <?= form_input([
        'name'        => 'q',
        'value'       => $q,
        'class'       => 'form-control',
        'placeholder' => 'Název zastávky nebo stanice, např. Uherské Hradiště',
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
    $table->setHeading('Typ', 'Název');

    foreach ($zastavky as $z) {
        $ikona = $z->typ === 'vlak'
            ? '<i class="fa-solid fa-train fa-fw" title="Vlak"></i>'
            : '<i class="fa-solid fa-bus fa-fw" title="Autobus"></i>';
        $table->addRow($ikona, anchor("tabule/{$z->typ}/{$z->slug}", esc($z->nazev)));
    }

    echo $table->generate();
    ?>

    <?= $pager->links() ?>
<?php endif ?>
<?= $this->endSection() ?>
