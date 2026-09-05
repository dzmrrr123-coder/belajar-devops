<?php
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/../config.php';
}
$page_title = $page_title ?? 'Gamified DevOps Learning';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - Learn Tracker DevOps</title>
    
    <meta name="description" content="Learn Tracker - platform belajar DevOps terstruktur 12 minggu.">
    <meta name="theme-color" content="#f7f8f6">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="assets/css/app.css?v=<?= filemtime(__DIR__ . '/../assets/css/app.css') ?>" rel="stylesheet">
</head>
<body class="<?= is_logged_in() ? 'has-tabbar' : '' ?>">
<a class="skip-link" href="#main">Lewati ke konten utama</a>
