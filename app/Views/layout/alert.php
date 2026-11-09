<?php if (isset($alert) && ! empty($alert->message)) : ?>
    <!-- Alert z flashdat (plní ho BaseController) -->
    <div class="alert alert-<?= esc($alert->class) ?> alert-dismissible fade show" role="alert">
        <?= esc($alert->message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Zavřít"></button>
    </div>
<?php endif ?>
