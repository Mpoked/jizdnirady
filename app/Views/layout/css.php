<!-- Světlý / tmavý režim podle nastavení systému (musí být před CSS, aby stránka neblikla) -->
<script>
    document.documentElement.setAttribute('data-bs-theme',
        window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
</script>
<!-- Bootswatch téma Zephyr (obsahuje celý Bootstrap) -->
<link rel="stylesheet" href="<?= base_url('node_modules/bootswatch/dist/zephyr/bootstrap.min.css') ?>">
<link rel="stylesheet" href="<?= base_url('node_modules/@fortawesome/fontawesome-free/css/all.min.css') ?>">
