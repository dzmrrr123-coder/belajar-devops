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
    <!-- Header & Progress Card -->
    <div class="roadmap-hero card p-4 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.4);">
                        <i class="fas fa-flag-checkered me-1"></i> Roadmap Persiapan PKL
                    </span>
                    <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #6ee7b7;">
                        <?= $completed_quests ?> dari <?= $total_quests ?> Quest Selesai
                    </span>
                </div>
                <h1 class="h3 fw-bold mb-2">Roadmap belajar <span class="text-gradient">DevOps Engineer</span></h1>
                <p class="text-secondary small mb-3">
                    Setiap quest mewakili kompetensi teknis dari database, backend OOP, containerization Docker, hingga cloud deployment AWS.
                </p>

                <div class="d-flex justify-content-between text-secondary small mb-1">
                    <span>Progress Kelulusan Roadmap</span>
                    <span class="fw-bold text-emerald"><?= $completion_rate ?>%</span>
                </div>
                <div class="xp-progress-bar" style="height: 10px;">
                    <div class="xp-progress-fill" style="width: <?= $completion_rate ?>%; background: linear-gradient(90deg, #10b981 0%, #06b6d4 100%);"></div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-gold fw-bold fs-4"><?= $xp_earned ?> / <?= $total_xp_possible ?></div>
                            <div class="text-secondary small">XP dari Quest</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-cyan fw-bold fs-4">12 Minggu</div>
                            <div class="text-secondary small">Durasi Program</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="card p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5">
                <div class="input-group">
                    <span class="input-group-text border-0" style="background: rgba(15, 23, 42, 0.9); color: var(--text-muted);"><i class="fas fa-search"></i></span>
                    <input type="text" id="questSearch" class="form-control" placeholder="Cari judul quest (misal: Docker, OOP, MySQL)..." oninput="filterQuests()">
                </div>
            </div>
            <div class="col-md-7">
                <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                    <button type="button" class="filter-pill active" onclick="filterByStatus('all', this)">Semua</button>
                    <button type="button" class="filter-pill" onclick="filterByStatus('todo', this)"><i class="far fa-circle me-1"></i> Belum Selesai</button>
                    <button type="button" class="filter-pill" onclick="filterByStatus('done', this)"><i class="fas fa-check me-1 text-emerald"></i> Selesai</button>
                </div>
            </div>
        </div>

        <!-- Week tabs filter -->
        <div class="mt-3 pt-3 border-top filter-pills" style="border-color: var(--border-subtle) !important;">
            <button type="button" class="filter-pill active" onclick="filterByWeek('all', this)">Semua Minggu</button>
            <?php for ($w = 1; $w <= 12; $w++): ?>
                <button type="button" class="filter-pill" onclick="filterByWeek(<?= $w ?>, this)">M-<?= $w ?></button>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Quest Board Groups -->
    <div class="row g-4" id="questsContainer">
        <?php foreach ($quests_by_week as $week_num => $week_quests): ?>
            <div class="col-lg-6 week-section" data-week="<?= $week_num ?>">
                <div class="card p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom" style="border-color: var(--border-subtle) !important;">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge" style="background: var(--primary-gradient); color: #fff; font-size: 0.8rem; padding: 6px 12px; border-radius: 8px;">
                                Minggu <?= $week_num ?>
                            </span>
                            <span class="text-secondary small fw-semibold">
                                <?= count($week_quests) ?> Quest Tersedia
                            </span>
                        </div>
                        <a href="resources.php?week=<?= $week_num ?>" class="btn btn-link btn-sm text-secondary p-0 text-decoration-none small">
                            <i class="fas fa-book-open me-1 text-cyan"></i> Materi M-<?= $week_num ?>
                        </a>
                    </div>

                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($week_quests as $q): 
                            $is_done = !empty($q['completed_at']);
                        ?>
                            <div class="quest-item <?= $is_done ? 'completed' : '' ?>" data-status="<?= $is_done ? 'done' : 'todo' ?>">
                                <div class="d-flex align-items-start gap-3">
                                    <!-- Interactive Checkbox Form -->
                                    <form method="POST" action="complete_quest.php" class="quest-toggle-form m-0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="quest_id" value="<?= $q['id'] ?>">
                                        <button type="submit" class="quest-check-btn" title="<?= $is_done ? 'Batalkan selesai' : 'Tandai selesai (+'.$q['xp_reward'].' XP)' ?>">
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
                                                <span class="badge bg-success" style="font-size: 0.72rem;">
                                                    <i class="fas fa-check me-1"></i>Selesai <?= date('d M Y', strtotime($q['completed_at'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
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
        if (b.innerText.startsWith('M-') || b.innerText === 'Semua Minggu') {
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
