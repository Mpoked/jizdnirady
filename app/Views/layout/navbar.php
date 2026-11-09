<nav class="navbar navbar-expand-md bg-primary" data-bs-theme="dark">
    <div class="container">
        <a class="navbar-brand" href="<?= base_url('/') ?>"><i class="fa-solid fa-signs-post"></i> Odjezdy</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#hlavniMenu"
                aria-controls="hlavniMenu" aria-expanded="false" aria-label="Menu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="hlavniMenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="<?= base_url('/') ?>"><i class="fa-solid fa-clock"></i> Odjezdy</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('mapa') ?>"><i class="fa-solid fa-map-location-dot"></i> Mapa</a></li>
                <li class="nav-item"><a class="nav-link" href="<?= base_url('administrace') ?>"><i class="fa-solid fa-gear"></i> Administrace</a></li>
                <?php if (! empty($prihlasen)) : ?>
                    <li class="nav-item"><a class="nav-link" href="<?= base_url('administrace/odhlaseni') ?>"><i class="fa-solid fa-right-from-bracket"></i> Odhlásit</a></li>
                <?php endif ?>
            </ul>
        </div>
    </div>
</nav>
