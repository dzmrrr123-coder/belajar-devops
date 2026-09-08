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

    <?php if (!empty($layout_opened)): ?>
        </div> <!-- end app-content -->
    </div> <!-- end app-layout -->
    <?php endif; ?>

    <?php if (is_logged_in()):
        $current_page = $current_page ?? basename($_SERVER['PHP_SELF'] ?? '');
        $tabs = [
            ['hub.php', 'fas fa-desktop', 'Workspace'],
            ['index.php', 'fas fa-chart-line', 'Overview'],
            ['quests.php', 'fas fa-map', 'Roadmap'],
            ['timer.php', 'fas fa-clock', 'Fokus'],
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

    <?php
    $logged = is_logged_in();
    $pg = $current_page ?? basename($_SERVER['PHP_SELF'] ?? '');
    if ($logged): ?>
    <!-- Bootstrap JS hanya untuk user login (dropdown/collapse navbar) -->
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>

    <?php
    $page_js = ['core.js', 'site.js', 'sync.js', 'ambience.js'];
    if ($logged) $page_js[] = 'lofi.js';
    if (in_array($pg, ['index.php', 'quests.php'], true)) $page_js[] = 'quests.js';
    if ($pg === 'review.php') $page_js[] = 'cards.js';
    if (in_array($pg, ['index.php', 'quests.php', 'onboarding.php'], true)) $page_js[] = 'mascot.js';
    if (in_array($pg, ['index.php', 'profile.php'], true)) $page_js[] = 'share-card.js';
    if (in_array($pg, ['leaderboard.php', 'u.php'], true)) $page_js[] = 'reactions.js';
    foreach ($page_js as $js):
        $jsp = __DIR__ . '/../assets/js/' . $js;
        $jsv = is_file($jsp) ? (int)@filemtime($jsp) : 0;
    ?>
    <script defer src="assets/js/<?= $js ?><?= $jsv ? '?v=' . $jsv : '' ?>"></script>
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
