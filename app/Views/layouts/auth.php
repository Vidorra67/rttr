<?php
/** @var string $content */
$title = $title ?? 'Anmelden';
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2B49E0">
    <meta name="color-scheme" content="light">
    <title><?= e($title) ?> · Ritterlager Manager</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/assets/icons/icon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body class="auth-body">
    <?= $content ?>
</body>
</html>
