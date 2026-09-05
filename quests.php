<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Get all quests with user completion status
$stmt = $conn->prepare("
    SELECT q.*, uq.completed_at
    FROM quests q
    LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
    ORDER BY q.week ASC, q.id ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_quests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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

$conn->close();

$page_title = 'Quest Board - Roadmap 12 Minggu';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Roadmap persiapan PKL · <?= $completed_quests ?> dari <?= $total_quests ?> quest</div>
        <h1 class="page-title">Roadmap DevOps 12 minggu</h1>
        <p class="page-desc"><?= $xp_earned ?> dari <?= $total_xp_possible ?> XP quest terkumpul (<?= $completion_rate ?>%).</p>
        <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $completion_rate ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres roadmap"><div class="xp-progress-fill" style="width: <?= $completion_rate ?>%;"></div></div>
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
                                    <div class="flex-grow-1">
                                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
                                            <h2 class="h6 fw-bold mb-0 quest-title"><?= htmlspecialchars($q['title']) ?></h2>
                                            <span class="quest-badge-xp">
                                                <i class="fas fa-bolt"></i> +<?= (int)$q['xp_reward'] ?> XP
                                            </span>
                                        </div>
                                        <p class="text-secondary small mb-2"><?= htmlspecialchars($q['description']) ?></p>
                                        <div class="d-flex align-items-center gap-2 quest-status-badge">
                                            <?php if ($is_done): ?>
                                                <span class="quest-done">
                                                    <i class="fas fa-check" aria-hidden="true"></i>Selesai <?= date('d M Y', strtotime($q['completed_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
            </section>
        <?php endforeach; ?>
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
