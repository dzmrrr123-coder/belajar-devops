<?php
// Empty-state helper — molecules. Satu pola baku untuk 5 lokasi duplikat.
function empty_state(string $icon, string $title, string $desc, string $cta_html = ''): void {
    ?>
    <div class="card p-4 p-md-5 text-center empty-state" role="status">
        <div class="empty-state-icon"><i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i></div>
        <h2 class="h5 fw-bold mb-2"><?= htmlspecialchars($title) ?></h2>
        <p class="text-secondary small mb-3"><?= htmlspecialchars($desc) ?></p>
        <?php if ($cta_html !== ''): ?><div><?= $cta_html ?></div><?php endif; ?>
    </div>
    <?php
}
