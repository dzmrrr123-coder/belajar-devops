<?php
require_once __DIR__ . '/config.php';

http_response_code(404);
$page_title = 'Halaman tidak ditemukan';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/navbar.php';
?>
<main class="container py-5" role="main">
    <div class="page-head">
        <div class="page-kicker">Error 404</div>
        <h1 class="page-title">Halaman tidak ditemukan</h1>
        <p class="page-desc">Alamat yang kamu buka tidak ada atau sudah dipindahkan. Kembali ke tempat yang jelas.</p>
        <div class="page-actions">
            <button type="button" class="btn btn-cyber-outline" onclick="history.back()">Kembali</button>
            <a href="hub.php" class="btn btn-cyber">Ke hub</a>
            <a href="quests.php" class="btn btn-cyber-outline">Roadmap</a>
            <a href="lab.php" class="btn btn-cyber-outline">Lab</a>
            <a href="review.php" class="btn btn-cyber-outline">Review</a>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
