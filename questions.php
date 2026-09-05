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
        if ($status === 'answered' && $answer !== '') {
            $qq = $conn->prepare("SELECT title, description, topic, reference_link, linked_error_id FROM questions WHERE id = ? AND user_id = ?");
            $qq->bind_param("ii", $question_id, $user_id);
            $qq->execute();
            $qr = $qq->get_result()->fetch_assoc();
            $qq->close();
            if ($qr && empty($qr['linked_error_id'])) schedule_review($conn, $user_id, 'question', $question_id, $qr['title'], $answer);
        }
        set_flash('success', 'Status pertanyaan diperbarui.');
        redirect('questions.php');
    }

    if ($action === 'to_error' && $question_id > 0) {
        $qq = $conn->prepare("SELECT * FROM questions WHERE id = ? AND user_id = ?");
        $qq->bind_param("ii", $question_id, $user_id);
        $qq->execute();
        $qr = $qq->get_result()->fetch_assoc();
        $qq->close();
        if (!$qr) set_flash('danger', 'Pertanyaan tidak ditemukan.');
        elseif (!empty($qr['linked_error_id'])) set_flash('info', 'Sudah terhubung ke Error Log.');
        else {
            $cat = in_array($qr['topic'] ?? '', ['MySQL','PHP','Laravel','Docker','Linux','Git','AWS'], true) ? $qr['topic'] : 'General';
            $emsg = mb_substr(trim(($qr['title'] ?? '') . "\n" . ($qr['description'] ?? '')), 0, 2000);
            $sol = mb_substr($qr['answer'] ?? '', 0, 2000);
            $ref = valid_url($qr['reference_link'] ?? '');
            $ins = $conn->prepare("INSERT INTO errors (user_id, category, error_message, solution, reference_link) VALUES (?, ?, ?, ?, ?)");
            $ins->bind_param("issss", $user_id, $cat, $emsg, $sol, $ref);
            if ($ins->execute()) {
                $eid = $ins->insert_id;
                $up = $conn->prepare("UPDATE questions SET linked_error_id = ?, status = 'answered', answered_at = IF(answered_at IS NULL, NOW(), answered_at) WHERE id = ? AND user_id = ?");
                $up->bind_param("iii", $eid, $question_id, $user_id);
                $up->execute(); $up->close();
                $xp = $conn->prepare("UPDATE users SET xp = xp + 5 WHERE id = ?");
                $xp->bind_param("i", $user_id); $xp->execute(); $xp->close();
                update_user_streak($conn, $user_id);
                schedule_review($conn, $user_id, 'error', (int)$eid, $emsg, $sol);
                set_flash('success', 'Dipindah ke Error Log! +5 XP.');
            } else set_flash('danger', 'Gagal memindah.');
            $ins->close();
        }
        redirect('questions.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $question_id = (int)($_POST['question_id'] ?? 0);
    if ($question_id > 0) {
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
                <?php foreach ($questions as $question): ?><article class="question-card card p-4"><div class="d-flex justify-content-between gap-3"><div><div class="d-flex flex-wrap gap-2 mb-2"><span class="question-status status-<?= htmlspecialchars($question['status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', ucfirst($question['status']))) ?></span><span class="question-priority priority-<?= htmlspecialchars($question['priority']) ?>"><?= htmlspecialchars(ucfirst($question['priority'])) ?></span><?php if ($question['topic']): ?><span class="text-secondary small">#<?= htmlspecialchars($question['topic']) ?></span><?php endif; ?></div><h2 class="h5 mb-2"><?= htmlspecialchars($question['title']) ?></h2><?php if ($question['description']): ?><p class="text-secondary mb-2"><?= nl2br(htmlspecialchars($question['description'])) ?></p><?php endif; ?><div class="small text-muted"><?php if ($question['quest_title']): ?>Quest: <?= htmlspecialchars($question['quest_title']) ?> · <?php endif; ?><?= htmlspecialchars(date('d M Y', strtotime($question['created_at']))) ?></div></div><div class="dropdown"><button class="btn btn-cyber-outline btn-sm" data-bs-toggle="dropdown" aria-label="Aksi pertanyaan"><i class="fas fa-ellipsis"></i></button><ul class="dropdown-menu dropdown-menu-end"><li><button type="button" class="dropdown-item" onclick="openEditQuestion(<?= htmlspecialchars(json_encode($question)) ?>)">Ubah detail</button></li><li><form method="post" action="questions.php" class="m-0"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>"><input type="hidden" name="action" value="to_error"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><button type="submit" class="dropdown-item">Jadikan Error Log (+5 XP)</button></form></li><li><hr class="dropdown-divider"></li><li><form method="post" action="questions.php" class="px-3 py-2"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><select name="status" class="form-select form-select-sm mb-2" aria-label="Status pertanyaan"><option value="open"<?= $question['status'] === 'open' ? ' selected' : '' ?>>Open</option><option value="in_review"<?= $question['status'] === 'in_review' ? ' selected' : '' ?>>In review</option><option value="answered"<?= $question['status'] === 'answered' ? ' selected' : '' ?>>Answered</option><option value="archived"<?= $question['status'] === 'archived' ? ' selected' : '' ?>>Archived</option></select><textarea name="answer" class="form-control form-control-sm mb-2" rows="2" placeholder="Jawaban atau catatan review..." aria-label="Jawaban atau catatan review"><?= htmlspecialchars($question['answer'] ?? '') ?></textarea><button class="btn btn-cyber btn-sm w-100" type="submit">Simpan status</button></form></li><li><hr class="dropdown-divider"></li><li><form method="post" action="questions.php" onsubmit="return confirm('Hapus pertanyaan ini?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>"><button type="submit" class="dropdown-item text-danger">Hapus pertanyaan</button></form></li></ul></div></div><?php if ($question['status'] === 'answered' && $question['answer']): ?><div class="question-answer mt-3"><strong>Jawaban</strong><p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($question['answer'])) ?></p></div><?php endif; ?></article><?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<div class="modal fade" id="editQuestionModal" tabindex="-1" aria-labelledby="editQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h2 class="modal-title h6 fw-bold mb-0" id="editQuestionModalLabel">Ubah pertanyaan</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form method="post" action="questions.php">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="question_id" id="editQuestionId">
                    <div class="mb-3"><label class="form-label" for="editQuestionTitle">Pertanyaan</label><input id="editQuestionTitle" name="title" class="form-control" required maxlength="255"></div>
                    <div class="mb-3"><label class="form-label" for="editQuestionDescription">Konteks <span class="text-muted">(opsional)</span></label><textarea id="editQuestionDescription" name="description" class="form-control" rows="3"></textarea></div>
                    <div class="row g-3 mb-3"><div class="col-6"><label class="form-label" for="editQuestionTopic">Topik</label><input id="editQuestionTopic" name="topic" class="form-control" maxlength="100"></div><div class="col-6"><label class="form-label" for="editQuestionPriority">Prioritas</label><select id="editQuestionPriority" name="priority" class="form-select"><option value="low">Rendah</option><option value="medium">Sedang</option><option value="high">Tinggi</option></select></div></div>
                    <div class="mb-3"><label class="form-label" for="editQuestionQuest">Quest terkait</label><select id="editQuestionQuest" name="quest_id" class="form-select"><option value="0">Tanpa quest</option><?php foreach ($quests as $quest): ?><option value="<?= (int)$quest['id'] ?>">M<?= (int)$quest['week'] ?> · <?= htmlspecialchars($quest['title']) ?></option><?php endforeach; ?></select></div>
                    <div class="mb-1"><label class="form-label" for="editQuestionReference">Referensi <span class="text-muted">(opsional)</span></label><input id="editQuestionReference" type="url" name="reference_link" class="form-control" placeholder="https://..."></div>
                    <p class="form-text mb-0">Status dan jawaban diubah lewat menu aksi di kartu pertanyaan.</p>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-cyber-outline btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-cyber btn-sm">Simpan perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openEditQuestion(q) {
    document.getElementById('editQuestionId').value = q.id || '';
    document.getElementById('editQuestionTitle').value = q.title || '';
    document.getElementById('editQuestionDescription').value = q.description || '';
    document.getElementById('editQuestionTopic').value = q.topic || '';
    document.getElementById('editQuestionPriority').value = q.priority || 'medium';
    document.getElementById('editQuestionQuest').value = q.quest_id || '0';
    document.getElementById('editQuestionReference').value = q.reference_link || '';
    new bootstrap.Modal(document.getElementById('editQuestionModal')).show();
}
</script>

<?php require_once 'includes/footer.php'; ?>
