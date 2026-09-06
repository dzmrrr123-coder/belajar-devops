<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$q = mb_substr(trim($_GET['q'] ?? ''), 0, 100);
$quests = $resources = $errors = $questions = $cards = [];
if ($q !== '') {
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $s = $conn->prepare("SELECT id, week, title FROM quests WHERE (user_id IS NULL OR user_id = ?) AND (title LIKE ? OR description LIKE ?) ORDER BY week LIMIT 8");
    $s->bind_param("iss", $user_id, $like, $like); $s->execute();
    $quests = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $s = $conn->prepare("SELECT week, title, url, type FROM resources WHERE title LIKE ? ORDER BY week LIMIT 8");
    $s->bind_param("s", $like); $s->execute();
    $resources = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $s = $conn->prepare("SELECT id, category, error_message, created_at FROM errors WHERE user_id = ? AND (error_message LIKE ? OR solution LIKE ?) ORDER BY created_at DESC LIMIT 8");
    $s->bind_param("iss", $user_id, $like, $like); $s->execute();
    $errors = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $s = $conn->prepare("SELECT id, title, status FROM questions WHERE user_id = ? AND (title LIKE ? OR description LIKE ?) ORDER BY created_at DESC LIMIT 8");
    $s->bind_param("iss", $user_id, $like, $like); $s->execute();
    $questions = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    $s = $conn->prepare("SELECT id, topic, question FROM quiz_cards WHERE user_id = ? AND (question LIKE ? OR answer LIKE ?) ORDER BY created_at DESC LIMIT 8");
    $s->bind_param("iss", $user_id, $like, $like); $s->execute();
    $cards = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
}
$conn->close();
$page_title = 'Pencarian';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Cari di quest, materi, catatan, tanya, kuis</div>
        <h1 class="page-title">Pencarian</h1>
        <form method="GET" action="search.php" class="d-flex gap-2 mt-3" role="search">
            <input name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Contoh: JOIN, docker, auth…" maxlength="100" aria-label="Kata kunci">
            <button class="btn btn-cyber flex-shrink-0" type="submit">Cari</button>
        </form>
    </div>
    <?php if ($q === ''): ?><p class="text-secondary">Ketik kata kunci lalu tekan Cari.</p><?php else: ?>
    <div class="row g-4">
        <div class="col-lg-6">
            <h2 class="h6 fw-bold">Quest (<?= count($quests) ?>)</h2>
            <?php foreach ($quests as $r): ?><a class="list-row" href="quests.php"><div class="list-main"><p class="list-title"><?= htmlspecialchars($r['title']) ?></p><p class="list-meta">Minggu <?= (int)$r['week'] ?></p></div><i class="fas fa-chevron-right list-chev"></i></a><?php endforeach; ?>
            <?php if (!$quests): ?><p class="small text-muted">Tidak ada quest cocok.</p><?php endif; ?>
            <h2 class="h6 fw-bold mt-4">Materi (<?= count($resources) ?>)</h2>
            <?php foreach ($resources as $r): ?><a class="list-row" href="<?= htmlspecialchars($r['url']) ?>" target="_blank" rel="noopener"><div class="list-main"><p class="list-title"><?= htmlspecialchars($r['title']) ?></p><p class="list-meta"><?= htmlspecialchars($r['type']) ?> · M<?= (int)$r['week'] ?></p></div><i class="fas fa-arrow-up-right-from-square list-chev"></i></a><?php endforeach; ?>
            <?php if (!$resources): ?><p class="small text-muted">Tidak ada materi cocok.</p><?php endif; ?>
        </div>
        <div class="col-lg-6">
            <h2 class="h6 fw-bold">Catatan Error (<?= count($errors) ?>)</h2>
            <?php foreach ($errors as $r): ?><a class="list-row" href="errors.php"><div class="list-main"><p class="list-title"><?= htmlspecialchars(mb_strimwidth($r['error_message'], 0, 80, '...')) ?></p><p class="list-meta"><?= htmlspecialchars($r['category']) ?></p></div><i class="fas fa-chevron-right list-chev"></i></a><?php endforeach; ?>
            <?php if (!$errors): ?><p class="small text-muted">Tidak ada catatan cocok.</p><?php endif; ?>
            <h2 class="h6 fw-bold mt-4">Questions (<?= count($questions) ?>)</h2>
            <?php foreach ($questions as $r): ?><a class="list-row" href="questions.php"><div class="list-main"><p class="list-title"><?= htmlspecialchars($r['title']) ?></p><p class="list-meta"><?= htmlspecialchars($r['status']) ?></p></div><i class="fas fa-chevron-right list-chev"></i></a><?php endforeach; ?>
            <?php if (!$questions): ?><p class="small text-muted">Tidak ada pertanyaan cocok.</p><?php endif; ?>
            <h2 class="h6 fw-bold mt-4">Kartu Kuis (<?= count($cards) ?>)</h2>
            <?php foreach ($cards as $r): ?><a class="list-row" href="quiz.php?mode=latihan"><div class="list-main"><p class="list-title"><?= htmlspecialchars(mb_strimwidth($r['question'], 0, 80, '...')) ?></p><p class="list-meta"><?= htmlspecialchars($r['topic'] ?? 'General') ?></p></div><i class="fas fa-chevron-right list-chev"></i></a><?php endforeach; ?>
            <?php if (!$cards): ?><p class="small text-muted">Tidak ada kartu cocok.</p><?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
