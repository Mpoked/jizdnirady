<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<nav aria-label="breadcrumb">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= base_url('administrace') ?>">Administrace</a></li>
        <li class="breadcrumb-item active" aria-current="page">Změna hesla</li>
    </ol>
</nav>

<?php
// Pole formuláře (ref. 08 §2.5): input group s ikonou + floating label
$pole = [
    'stare'     => ['Současné heslo', 'current-password'],
    'nove'      => ['Nové heslo', 'new-password'],
    'potvrzeni' => ['Nové heslo znovu', 'new-password'],
];
?>
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">
        <div class="card shadow-sm p-4">
            <h1 class="h3 text-center mb-4"><i class="fa-solid fa-key"></i> Změna hesla</h1>

            <?= form_open('administrace/heslo') ?>
                <?= csrf_field() ?>
                <?php foreach ($pole as $name => [$popisek, $autocomplete]) : ?>
                    <?php
                    $atributy = [
                        'name'         => $name,
                        'id'           => $name,
                        'class'        => 'form-control',
                        'placeholder'  => $popisek,
                        'autocomplete' => $autocomplete,
                        'required'     => 'required',
                    ];
                    // U nového hesla hlídá délku už prohlížeč, server ji kontroluje znovu
                    if ($name !== 'stare') {
                        $atributy['minlength'] = $minDelka;
                    }
                    ?>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <div class="form-floating">
                            <?= form_password($atributy) ?>
                            <label for="<?= $name ?>"><?= esc($popisek) ?></label>
                        </div>
                    </div>
                <?php endforeach ?>

                <p class="small text-body-secondary">Nové heslo musí mít aspoň <?= esc($minDelka) ?> znaků.</p>

                <button class="btn btn-primary w-100" type="submit">Změnit heslo</button>
            <?= form_close() ?>

            <a href="<?= base_url('administrace') ?>" class="btn btn-outline-secondary w-100 mt-3">Zpět do administrace</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
