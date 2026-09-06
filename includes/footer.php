<?php
$quotes = [
    "“The only way to go fast, is to go well.” – Robert C. Martin",
    "“Automate everything you can, measure everything that moves.” – DevOps Mantra",
    "“Talk is cheap. Show me the code.” – Linus Torvalds",
    "“First, solve the problem. Then, write the code.” – John Johnson",
    "“Continuous improvement is better than delayed perfection.” – Mark Twain",
    "“It's not a bug – it's an undocumented feature.” – Anonymous"
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
            </div>
        </div>
    </footer>

    <?php if (is_logged_in()):
        $current_page = $current_page ?? basename($_SERVER['PHP_SELF'] ?? '');
        $tabs = [
            ['index.php', 'fas fa-grid-2', 'Overview'],
            ['quests.php', 'fas fa-map', 'Roadmap'],
            ['timer.php', 'fas fa-clock', 'Fokus'],
            ['errors.php', 'fas fa-note-sticky', 'Notes'],
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

    <script defer src="assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
    <script defer src="assets/js/sync.js?v=<?= filemtime(__DIR__ . '/../assets/js/sync.js') ?>"></script>

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
