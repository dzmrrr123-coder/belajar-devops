<?php
$quotes = [
    "Kecil tapi rutin lebih menang daripada besar tapi sesekali.",
    "Selesaikan satu quest hari ini, besok lanjut lagi.",
    "Error itu catatan. Tulis, pahami, dapat XP.",
    "Fokus 25 menit lebih baik daripada scrolling 2 jam.",
    "Portofolio dibangun dari quest kecil yang selesai.",
];
$random_quote = $quotes[array_rand($quotes)];
$flash = get_flash();
?>
    <footer>
        <div class="container text-center">
            <p class="mb-2 text-secondary fst-italic small"><?= htmlspecialchars($random_quote) ?></p>
            <div class="d-flex justify-content-center align-items-center flex-wrap gap-3 small text-muted">
                <span><strong>Learn Tracker</strong></span>
                <span aria-hidden="true">•</span>
                <span>Roadmap 12 minggu</span>
                <span aria-hidden="true">•</span>
                <span>Level up setiap hari</span>
                <span aria-hidden="true">•</span>
                <button type="button" class="btn btn-link btn-sm text-muted p-0" data-motion-toggle aria-pressed="false" aria-label="Kurangi animasi"><i class="fas fa-person-running" aria-hidden="true"></i> <span>Animasi: aktif</span></button>
            </div>
        </div>
    </footer>

    <?php if (is_logged_in()):
        $current_page = $current_page ?? basename($_SERVER['PHP_SELF'] ?? '');
        $tabs = [
            ['index.php', 'fas fa-grid-2', 'Overview'],
            ['quests.php', 'fas fa-map', 'Roadmap'],
            ['timer.php', 'fas fa-clock', 'Fokus'],
            ['review.php', 'fas fa-rotate-right', 'Review'],
            ['errors.php', 'fas fa-note-sticky', 'Catatan'],
            ['profile.php', 'fas fa-user', 'Profil'],
        ];
    ?>
    <nav class="mobile-tabbar" aria-label="Navigasi cepat">
        <?php foreach ($tabs as [$href, $icon, $label]):
            $is_active = $current_page === $href;
        ?>
        <a href="<?= $href ?>" class="tabbar-link <?= $is_active ? 'active' : '' ?>" <?= $is_active ? 'aria-current="page"' : '' ?>>
            <i class="<?= $icon ?>" aria-hidden="true"></i><span><?= $label ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <!-- Toast container for live notifications -->
    <div class="toast-container" aria-live="polite" aria-atomic="true"></div>
    <div id="pageProgress" aria-hidden="true"></div>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php foreach (['core.js', 'lofi.js', 'quests.js', 'cards.js', 'site.js', 'sync.js', 'share-card.js', 'reactions.js', 'ambience.js', 'mascot.js'] as $js): ?>
    <script defer src="assets/js/<?= $js ?>?v=<?= filemtime(__DIR__ . '/../assets/js/' . $js) ?>"></script>
    <?php endforeach; ?>

    <script>
    if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('sw.js').catch(function() {});
        });
    }
    </script>

    <?php if ($flash): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
        });
    </script>
    <?php endif; ?>
</body>
</html>
