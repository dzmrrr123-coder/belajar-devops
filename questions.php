<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $question_id = (int)($_POST['question_id'] ?? 0);

    if ($action === 'create' || $action === 'update') {
        $title = mb_substr(clean($_POST['title'] ?? ''), 0, 255);
        $description = mb_substr(clean($_POST['description'] ?? ''), 0, 2000);
        $topic = mb_substr(clean($_POST['topic'] ?? ''), 0, 100);
        $priority = in_array($_POST['priority'] ?? 'medium', ['low', 'medium', 'high'], true) ? $_POST['priority'] : 'medium';
        $quest_raw = (int)($_POST['quest_id'] ?? 0);
        $quest_id = $quest_raw > 0 ? $quest_raw : null;
        $reference_link = valid_url($_POST['reference_link'] ?? '');

        if ($quest_id !== null) {
            $qc = $conn->prepare('SELECT id FROM quests WHERE id = ?');
            $qc->bind_param('i', $quest_id);
            $qc->execute();
            if (!$qc->get_result()->fetch_assoc()) $quest_id = null;
            $qc->close();
        }

        if ($title === '') {
            set_flash('warning', 'Pertanyaan wajib memiliki judul.');
        } elseif ($action === 'create') {
            if ($quest_id === null) {
                $stmt = $conn->prepare('INSERT INTO questions (user_id, quest_id, title, description, topic, priority, reference_link) VALUES (?, NULL, ?, ?, ?, ?, ?)');
                $stmt->bind_param('isssss', $user_id, $title, $description, $topic, $priority, $reference_link);
            } else {
                $stmt = $conn->prepare('INSERT INTO questions (user_id, quest_id, title, description, topic, priority, reference_link) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('iisssss', $user_id, $quest_id, $title, $description, $topic, $priority, $reference_link);
            }
            $saved = $stmt->execute();
            set_flash($saved ? 'success' : 'danger', $saved ? 'Pertanyaan disimpan.' : 'Gagal menyimpan.');
            $stmt->close();
        } else {
            if ($question_id <= 0) {
                set_flash('warning', 'ID pertanyaan tidak valid.');
            } else {
                if ($quest_id === null) {
                    $stmt = $conn->prepare('UPDATE questions SET quest_id = NULL, title = ?, description = ?, topic = ?, priority = ?, reference_link = ? WHERE id = ? AND user_id = ?');
                    $stmt->bind_param('sssssii', $title, $description, $topic, $priority, $reference_link, $question_id, $user_id);
                } else {
                    $stmt = $conn->prepare('UPDATE questions SET quest_id = ?, title = ?, description = ?, topic = ?, priority = ?, reference_link = ? WHERE id = ? AND user_id = ?');
                    $stmt->bind_param('isssssii', $quest_id, $title, $description, $topic, $priority, $reference_link, $question_id, $user_id);
                }
                $saved = $stmt->execute();
                set_flash($saved ? 'success' : 'danger', $saved ? 'Pertanyaan diperbarui.' : 'Gagal memperbarui.');
                $stmt->close();
            }
        }
        redirect('questions.php');
    }

    if ($action === 'status' && $question_id > 0) {
        $status = in_array($_POST['status'] ?? 'open', ['open', 'in_review', 'answered', 'archived'], true) ? $_POST['status'] : 'open';
        $answer = clean($_POST['answer'] ?? '');
        $stmt = $conn->prepare("UPDATE questions SET status = ?, answer = ?, answered_at = IF(? = 'answered', NOW(), NULL) WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sssii', $status, $answer, $status, $question_id, $user_id);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Status pertanyaan diperbarui.');
        redirect('questions.php');
    }
}

if (isset($_GET['delete'])) {
    $question_id = (int)$_GET['delete'];
    if (hash_equals($_SESSION['csrf_token'] ?? '', $_GET['token'] ?? '')) {
        $stmt = $conn->prepare('DELETE FROM questions WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $question_id, $user_id);
        $stmt->execute();
        $stmt->close();
        set_flash('info', 'Pertanyaan dihapus.');
    }
    redirect('questions.php');
}

$status_filter = $_GET['status'] ?? 'all';
$allowed_statuses = ['open', 'in_review', 'answered', 'archived'];
$questions = [];
if (in_array($status_filter, $allowed_statuses, true)) {
    $stmt = $conn->prepare('SELECT q.*, quests.title AS quest_title FROM questions q LEFT JOIN quests ON quests.id = q.quest_id WHERE q.user_id = ? AND q.status = ? ORDER BY q.created_at DESC');
    $stmt->bind_param('is', $user_id, $status_filter);
} else {
    $stmt = $conn->prepare('SELECT q.*, quests.title AS quest_title FROM questions q LEFT JOIN quests ON quests.id = q.quest_id WHERE q.user_id = ? ORDER BY q.created_at DESC');
    $stmt->bind_param('i', $user_id);
}
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$quests_stmt = $conn->prepare('SELECT id, title, week FROM quests ORDER BY week, id');
$quests_stmt->execute();
$quests = $quests_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$quests_stmt->close();
$conn->close();

$page_title = 'Questions';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker"><?= count($questions) ?> pertanyaan ditampilkan</div>
        <h1 class="page-title">Questions</h1>
        <p class="page-desc">Simpan hal yang belum jelas tanpa memutus fokus. Review kembali saat siap.</p>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-lg-4">
            <section class="card p-4 sticky-top" style="top: 80px;" aria-labelledby="new-question-heading">
                <h2 id="new-question-heading" class="h5 fw-bold mb-1">Catat pertanyaan</h2>
                <p class="text-secondary small mb-4">Tuliskan dengan konteks yang cukup agar mudah direview nanti.</p>
                <form method="post" action="questions.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3"><label class="form-label" for="question-title">Pertanyaan</label><input id="question-title" name="title" class="form-control" required maxlength="255" placeholder="Contoh: Kapan memakai LEFT JOIN?"></div>
                    <div class="mb-3"><label class="form-label" for="question-description">Konteks <span class="text-muted">(opsional)</span></label><textarea id="question-description" name="description" class="form-control" rows="4" placeholder="Apa yang sudah kamu coba atau bagian yang membingungkan?"></textarea></div>
                    <div class="row g-3 mb-3"><div class="col-6"><label class="form-label" for="question-topic">Topik</label><input id="question-topic" name="topic" class="form-control" maxlength="100" placeholder="MySQL"></div><div class="col-6"><label class="form-label" for="question-priority">Prioritas</label><select id="question-priority" name="priority" class="form-select"><option value="low">Rendah</option><option value="medium" selected>Sedang</option><option value="high">Tinggi</option></select></div></div>
                    <div class="mb-3"><label class="form-label" for="question-quest">Quest terkait</label><select id="question-quest" name="quest_id" class="form-select"><option value="0">Tanpa quest</option><?php foreach ($quests as $quest): ?><option value="<?= (int)$quest['id'] ?>">M<?= (int)$quest['week'] ?> · <?= htmlspecialchars($quest['title']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-4"><label class="form-label" for="question-reference">Referensi <span class="text-muted">(opsional)</span></label><input id="question-reference" type="url" name="reference_link" class="form-control" placeholder="https://..."></div>
                    <button class="btn btn-cyber w-100" type="submit"><i class="fas fa-plus me-2"></i>Simpan pertanyaan</button>
                </form>
            </section>
        </div>
        <div class="col-lg-8">
            <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Filter status">
                <a class="filter-pill <?= $status_filter === 'all' ? 'active' : '' ?>" href="questions.php">Semua</a>
                <a class="filter-pill <?= $status_filter === 'open' ? 'active' : '' ?>" href="questions.php?status=open">Open</a>
                <a class="filter-pill <?= $status_filter === 'in_review' ? 'active' : '' ?>" href="questions.php?status=in_review">In review</a>
                <a class="filter-pill <?= $status_filter === 'answered' ? 'active' : '' ?>" href="questions.php?status=answered">Answered</a>
                <a class="filter-pill <?= $status_filter === 'archived' ? 'active' : '' ?>" href="questions.php?status=archived">Archived</a>
            </div>
            <?php if (!$questions): ?><div class="card p-5 text-center"><div class="empty-state-icon"><i class="fas fa-circle-question"></i></div><h2 class="h5 fw-bold mt-3">Belum ada pertanyaan</h2><p class="text-secondary mb-0">Catat hal yang belum jelas agar tidak hilang saat kamu belajar.</p></div><?php endif; ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($questions as $question): ?><article class="question-card card p-4"><div class="d-flex justify-content-between gap-3"><div><div class="d-flex flex-wrap gap-2 mb-2"><span class="question-status status-<?= htmlspecialchars($question['status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($question['status']))) ?></span><span class="question-priority priority-<?= htmlspecialchars($question['priority']) ?>"><?= htmlspecialchars(ucfirst($question['priority'])) ?></span><?php if ($question['topic']): ?><span class="text-secondary small">#<?= htmlspecialchars($question['topic']) ?></span><?php endif; ?></div><h2 class="h5 mb-2"><?= htmlspecialchars($question['title']) ?></h2><?php if ($question['description']): ?><p class="text-secondary mb-2"><?= nl2br(htmlspecialchars($question['description'])) ?></p><?php endif; ?><div class="small text-muted"><?php if ($question['quest_title']): ?>Quest: <?= htmlspecialchars($question['quest_title']) ?> · <?php endif; ?><?= htmlspecialchars(date('d M Y', strtotime($question['created_at']))) ?></div></div><div class="dropdown"><button class="btn btn-cyber-outline btn-sm" data-bs-toggle="dropdown" aria-label="Aksi pertanyaan"><i class="fas fa-ellipsis"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><form method="post" action="questions.php" class="px-3 py-2"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><select name="status" class="form-select form-select-sm mb-2"><option value="open">Open</option><option value="in_review">In review</option><option value="answered">Answered</option><option value="archived">Archived</option></select><textarea name="answer" class="form-control form-control-sm mb-2" rows="2" placeholder="Jawaban atau catatan review..."><?= htmlspecialchars($question['answer'] ?? '') ?></textarea><button class="btn btn-cyber btn-sm w-100" type="submit">Simpan status</button></form></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-danger" href="questions.php?delete=<?= (int)$question['id'] ?>&token=<?= urlencode(csrf_token()) ?>" onclick="return confirm('Hapus pertanyaan ini?')">Hapus pertanyaan</a></li></ul></div></div><?php if ($question['status'] === 'answered' && $question['answer']): ?><div class="question-answer mt-3"><strong>Jawaban</strong><p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($question['answer'])) ?></p></div><?php endif; ?></article><?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
