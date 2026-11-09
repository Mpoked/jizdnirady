<?= $this->extend('layout/template') ?>

<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm p-4">
            <h1 class="h3 text-center mb-4"><i class="fa-solid fa-user-lock"></i> Přihlášení</h1>

            <?= form_open('prihlaseni') ?>
                <?= csrf_field() ?>
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                    <div class="form-floating">
                        <?= form_input([
                            'type'         => 'email',
                            'name'         => 'email',
                            'id'           => 'email',
                            'value'        => old('email'),
                            'class'        => 'form-control',
                            'placeholder'  => 'E-mail',
                            'autocomplete' => 'username',
                            'required'     => 'required',
                        ]) ?>
                        <label for="email">E-mail</label>
                    </div>
                </div>

                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <div class="form-floating">
                        <?= form_password([
                            'name'         => 'heslo',
                            'id'           => 'heslo',
                            'class'        => 'form-control',
                            'placeholder'  => 'Heslo',
                            'autocomplete' => 'current-password',
                            'required'     => 'required',
                        ]) ?>
                        <label for="heslo">Heslo</label>
                    </div>
                </div>

                <button class="btn btn-primary w-100" type="submit">Přihlásit se</button>
            <?= form_close() ?>

            <a href="<?= base_url('/') ?>" class="btn btn-outline-secondary w-100 mt-3">Zpět na odjezdy</a>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
