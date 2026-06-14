<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e(APP_COMPANY) ?> — Stock Management System">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= asset_url('img/logo.png') ?>">
    <link rel="apple-touch-icon" href="<?= asset_url('img/logo.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Noto+Sans+Myanmar:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
    (function () {
        var t = localStorage.getItem('app.theme');
        var b = localStorage.getItem('app.brand');
        if (b && b.toLowerCase() === '#087f74') b = '#545760';
        if (t) document.documentElement.setAttribute('data-theme', t);
        if (b) document.documentElement.style.setProperty('--color-primary', b);
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset_url('css/app.css') ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body class="<?= e($bodyClass ?? 'app-body') ?>">
