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
                <span>🎮 <strong>Learn Tracker DevOps</strong></span>
                <span>•</span>
                <span>Roadmap PKL 3 Bulan</span>
                <span>•</span>
                <span>Level Up Everyday</span>
            </div>
        </div>
    </footer>

    <!-- Toast container for live notifications -->
    <div class="toast-container" aria-live="polite" aria-atomic="true"></div>

    <!-- Canvas Confetti CDN -->
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>

    <!-- Bootstrap 5.3 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- App Engine JS -->
    <script src="assets/js/app.js?v=<?= time() ?>"></script>

    <?php if ($flash): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
        });
    </script>
    <?php endif; ?>
</body>
</html>
