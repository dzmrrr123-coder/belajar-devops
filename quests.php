<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_custom') {
        $title = mb_substr(clean($_POST['title'] ?? ''), 0, 255);
        $desc = mb_substr(clean($_POST['description'] ?? ''), 0, 2000);
        $week = max(1, min(12, (int)($_POST['week'] ?? 1)));
        $xp = max(5, min(20, (int)($_POST['xp_reward'] ?? 10)));
        if ($title === '') set_flash('warning', 'Judul quest wajib diisi.');
        else {
            $stmt = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward) VALUES (?, 1, ?, ?, ?, ?)");
            $stmt->bind_param("iissi", $user_id, $week, $title, $desc, $xp);
            $ok = $stmt->execute();
            $stmt->close();
            $nb = $ok ? check_and_unlock_badges($conn, $user_id) : [];
            set_flash($ok ? 'success' : 'danger', $ok ? 'Quest custom dibuat.' . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : '') : 'Gagal membuat quest.');
        }
        redirect('quests.php');
    }
    if ($action === 'delete_custom') {
        $qid = (int)($_POST['quest_id'] ?? 0);
        delete_review($conn, $user_id, 'quest', $qid);
        $stmt = $conn->prepare("DELETE FROM quests WHERE id = ? AND user_id = ? AND is_custom = 1");
        $stmt->bind_param("ii", $qid, $user_id);
        $stmt->execute();
        $stmt->close();
        set_flash('info', 'Quest custom dihapus.');
        redirect('quests.php');
    }
}

// Get all quests with user completion status (global + milik sendiri)
$stmt = $conn->prepare("
    SELECT q.*, uq.completed_at
    FROM quests q
    LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
    WHERE (q.user_id IS NULL OR q.user_id = ?)
    ORDER BY q.week ASC, q.id ASC
");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$all_quests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subtasks_by_quest = [];
$st = $conn->prepare("SELECT * FROM quest_subtasks WHERE user_id = ? ORDER BY id ASC");
$st->bind_param("i", $user_id);
$st->execute();
foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $s) $subtasks_by_quest[(int)$s['quest_id']][] = $s;
$st->close();

// Compute stats
$total_quests = count($all_quests);
$completed_quests = 0;
$total_xp_possible = 0;
$xp_earned = 0;

$quests_by_week = [];
foreach ($all_quests as $q) {
    $total_xp_possible += (int)$q['xp_reward'];
    if (!empty($q['completed_at'])) {
        $completed_quests++;
        $xp_earned += (int)$q['xp_reward'];
    }
    $quests_by_week[$q['week']][] = $q;
}

$completion_rate = $total_quests > 0 ? round(($completed_quests / $total_quests) * 100) : 0;



$page_title = 'Quest Board - Roadmap 12 Minggu';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Roadmap persiapan PKL · <span id="roadmapDone"><?= $completed_quests ?></span> dari <span id="roadmapTotal"><?= $total_quests ?></span> quest</div>
        <h1 class="page-title">Roadmap DevOps 12 minggu</h1>
        <p class="page-desc"><?= $xp_earned ?> dari <?= $total_xp_possible ?> XP quest terkumpul (<span id="roadmapPct"><?= $completion_rate ?></span>%).</p>
        <div class="xp-progress-bar" id="roadmapBarWrap" role="progressbar" aria-valuenow="<?= $completion_rate ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres roadmap"><div class="xp-progress-fill" id="roadmapBar" style="width: <?= $completion_rate ?>%;"></div></div>
    </div>

    <div class="mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <input type="search" id="questSearch" class="form-control" placeholder="Cari quest…" aria-label="Cari quest" oninput="filterQuests()">
                </div>
            </div>
            <div class="col-md-7">
                <div class="d-flex justify-content-md-end">
                    <div class="segmented" role="group" aria-label="Filter status quest">
                        <button type="button" class="filter-pill active" onclick="filterByStatus('all', this)">Semua</button>
                        <button type="button" class="filter-pill" onclick="filterByStatus('todo', this)">Belum selesai</button>
                        <button type="button" class="filter-pill" onclick="filterByStatus('done', this)">Selesai</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 filter-pills" role="group" aria-label="Filter minggu">
            <button type="button" class="filter-pill active" onclick="filterByWeek('all', this)">Semua minggu</button>
            <?php for ($w = 1; $w <= 12; $w++): ?>
                <button type="button" class="filter-pill" onclick="filterByWeek(<?= $w ?>, this)">M-<?= $w ?></button>
            <?php endfor; ?>
        </div>
    </div>

    <div id="questsContainer">
        <?php foreach ($quests_by_week as $week_num => $week_quests): ?>
            <section class="week-block week-section" data-week="<?= $week_num ?>" aria-label="Minggu <?= $week_num ?>">
                    <div class="week-block-head">
                        <span class="week-tag">Minggu <?= $week_num ?></span>
                        <span class="week-count"><?= count($week_quests) ?> quest</span>
                        <span class="rule" aria-hidden="true"></span>
                        <a href="resources.php?week=<?= $week_num ?>" class="small text-secondary text-decoration-none">Materi</a>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($week_quests as $q): 
                            $is_done = !empty($q['completed_at']);
                        ?>
                            <div class="quest-item <?= $is_done ? 'completed' : '' ?>" data-status="<?= $is_done ? 'done' : 'todo' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Interactive Checkbox Form -->
                                    <form method="POST" action="complete_quest.php" class="quest-toggle-form m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quest_id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="quest-check-btn" title="<?= $is_done ? 'Batalkan selesai' : 'Tandai selesai (+'.$q['xp_reward'].' XP)' ?>" aria-label="<?= $is_done ? 'Batalkan quest selesai: ' : 'Tandai quest selesai: ' ?><?= htmlspecialchars($q['title']) ?>">
                                            <i class="fas <?= $is_done ? 'fa-check' : 'fa-circle' ?>"></i>
                                        </button>
                                    </form>

                                    <!-- Quest Info -->
                                    <div class="flex-grow-1 min-w-0">
                                        <div class="quest-title-row">
                                            <h2 class="h6 fw-bold quest-title"><?= htmlspecialchars($q['title']) ?> <?php if (!empty($q['is_custom'])): ?><span class="quest-pending">Custom</span><?php endif; ?></h2>
                                            <span class="quest-badge-xp">
                                                <i class="fas fa-bolt" aria-hidden="true"></i> +<?= (int)$q['xp_reward'] ?> XP
                                            </span>
                                        </div>
                                        <p class="text-secondary small mb-2 quest-desc"><?= htmlspecialchars($q['description']) ?></p>
                                        <?php $subs = $subtasks_by_quest[(int)$q['id']] ?? []; $sdone = count(array_filter($subs, fn($s) => !empty($s['done_at']))); ?>
                                        <div class="d-flex align-items-center flex-wrap gap-2 quest-status-badge mb-1">
                                            <?php if ($is_done): ?>
                                                <span class="quest-done">
                                                    <i class="fas fa-check" aria-hidden="true"></i>Selesai <?= date('d M Y', strtotime($q['completed_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($subs): ?><span class="small text-muted"><?= $sdone ?>/<?= count($subs) ?> langkah</span><?php endif; ?>
                                            <?php if (!empty($q['is_custom']) && (int)$q['user_id'] === $user_id): ?>
                                            <form method="POST" action="quests.php" class="m-0 ms-auto" onsubmit="return confirm('Hapus quest custom ini?')">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="delete_custom">
                                                <input type="hidden" name="quest_id" value="<?= (int)$q['id'] ?>">
                                                <button type="submit" class="btn btn-cyber-danger btn-sm py-1" aria-label="Hapus quest custom"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                        <details class="subtask-box">
                                            <summary class="small text-secondary">Langkah kecil (<?= $sdone ?>/<?= count($subs) ?>)</summary>
                                            <div class="subtask-list mt-2">
                                                <?php foreach ($subs as $s): ?>
                                                <form method="POST" action="subtask.php" class="subtask-toggle-form d-flex align-items-center gap-2">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="toggle">
                                                    <input type="hidden" name="quest_id" value="<?= (int)$q['id'] ?>">
                                                    <input type="hidden" name="subtask_id" value="<?= (int)$s['id'] ?>">
                                                    <button type="submit" class="subtask-check <?= !empty($s['done_at']) ? 'done' : '' ?>" aria-label="Toggle subtask"><i class="fas <?= !empty($s['done_at']) ? 'fa-check' : 'fa-circle' ?>"></i></button>
                                                    <span class="flex-grow-1 <?= !empty($s['done_at']) ? 'text-decoration-line-through text-muted' : '' ?>"><?= htmlspecialchars($s['title']) ?></span>
                                                </form>
                                                <?php endforeach; ?>
                                                <form method="POST" action="subtask.php" class="subtask-add-form d-flex gap-2 mt-2">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="create">
                                                    <input type="hidden" name="quest_id" value="<?= (int)$q['id'] ?>">
                                                    <input name="title" class="form-control form-control-sm" maxlength="255" placeholder="+ Tambah langkah…" aria-label="Tambah langkah">
                                                    <button type="submit" class="btn btn-cyber-outline btn-sm flex-shrink-0">Tambah</button>
                                                </form>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
            </section>
        <?php endforeach; ?>
    </div>

    <button type="button" class="fab-add" data-bs-toggle="modal" data-bs-target="#customQuestModal" aria-label="Tambah quest custom"><i class="fas fa-plus" aria-hidden="true"></i></button>

    <div class="modal fade" id="customQuestModal" tabindex="-1" aria-labelledby="customQuestLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-bottom">
            <div class="modal-content">
                <div class="modal-header border-bottom">
                    <h2 class="modal-title h6 fw-bold mb-0" id="customQuestLabel">Quest custom</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST" action="quests.php">
                    <div class="modal-body">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_custom">
                        <div class="mb-3"><label class="form-label" for="cq-title">Judul</label><input id="cq-title" name="title" class="form-control" required maxlength="255" placeholder="Contoh: Latihan JOIN 30 menit"></div>
                        <div class="mb-3"><label class="form-label" for="cq-desc">Deskripsi</label><textarea id="cq-desc" name="description" class="form-control" rows="3" placeholder="Target kecil yang jelas…"></textarea></div>
                        <div class="row g-3"><div class="col-6"><label class="form-label" for="cq-week">Minggu</label><select id="cq-week" name="week" class="form-select"><?php for ($w = 1; $w <= 12; $w++): ?><option value="<?= $w ?>">Minggu <?= $w ?></option><?php endfor; ?></select></div><div class="col-6"><label class="form-label" for="cq-xp">Reward (5–20 XP)</label><input id="cq-xp" name="xp_reward" type="number" min="5" max="20" value="10" class="form-control"></div></div>
                    </div>
                    <div class="modal-footer border-top sticky-bottom-bar">
                        <button type="button" class="btn btn-cyber-outline" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-cyber">Simpan quest</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Empty state for search filter -->
    <div id="noQuestsMessage" class="empty-state card p-5 d-none">
        <div class="empty-state-icon"><i class="fas fa-search-minus"></i></div>
        <h2 class="h5 fw-bold mb-2">Tidak ada quest yang cocok</h2>
        <p class="text-secondary small mb-3">Coba ubah kata kunci pencarian atau reset filter minggu.</p>
        <div>
            <button class="btn btn-cyber-outline btn-sm" onclick="resetFilters()">
                <i class="fas fa-redo me-1"></i> Reset Semua Filter
            </button>
        </div>
    </div>
</main>

<script>
let activeWeek = 'all';
let activeStatus = 'all';

function filterByWeek(week, btn) {
    activeWeek = String(week);
    document.querySelectorAll('.filter-pills button').forEach(b => {
        const label = (b.innerText || '').trim().toLowerCase();
        if (label.startsWith('m-') || label === 'semua minggu') {
            b.classList.remove('active');
        }
    });
    btn.classList.add('active');
    applyFilters();
}

function filterByStatus(status, btn) {
    activeStatus = status;
    btn.parentElement.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyFilters();
}

function filterQuests() {
    applyFilters();
}

function applyFilters() {
    const query = (document.getElementById('questSearch').value || '').toLowerCase().trim();
    const sections = document.querySelectorAll('.week-section');
    let visibleSectionCount = 0;

    sections.forEach(section => {
        const weekNum = section.getAttribute('data-week');
        const matchWeek = (activeWeek === 'all' || activeWeek === weekNum);

        const items = section.querySelectorAll('.quest-item');
        let visibleItemsInSection = 0;

        items.forEach(item => {
            const status = item.getAttribute('data-status');
            const title = (item.querySelector('.quest-title')?.textContent || '').toLowerCase();
            const desc = (item.querySelector('p')?.textContent || '').toLowerCase();

            const matchStatus = (activeStatus === 'all' || activeStatus === status);
            const matchSearch = (!query || title.includes(query) || desc.includes(query));

            if (matchStatus && matchSearch) {
                item.style.display = '';
                visibleItemsInSection++;
            } else {
                item.style.display = 'none';
            }
        });

        if (matchWeek && visibleItemsInSection > 0) {
            section.style.display = '';
            visibleSectionCount++;
        } else {
            section.style.display = 'none';
        }
    });

    const noQuestsMsg = document.getElementById('noQuestsMessage');
    if (visibleSectionCount === 0) {
        noQuestsMsg.classList.remove('d-none');
    } else {
        noQuestsMsg.classList.add('d-none');
    }
}

function resetFilters() {
    document.getElementById('questSearch').value = '';
    activeWeek = 'all';
    activeStatus = 'all';
    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    document.querySelector('.filter-pill:first-child').classList.add('active');
    applyFilters();
}
</script>

<?php require_once 'includes/footer.php'; ?>
