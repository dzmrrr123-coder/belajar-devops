<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    redirect('login.php');
}

// Calculate level & gamification stats
$level = calculate_level($user['xp']);
$rank_title = get_user_rank($level);
$next_level_xp = xp_to_next_level($user['xp']);
$base_level_xp = level_base_xp($level);
$progress_percent = level_progress_percent($user['xp']);
$xp_needed = max(0, $next_level_xp - $user['xp']);

// Calculate roadmap week based on user registration
$created = new DateTime($user['created_at']);
$now = new DateTime();
$days_diff = (int)$created->diff($now)->days;
$auto_week = min(12, max(1, (int)floor($days_diff / 7) + 1));

// Allow manual week preview if selected
$selected_week = isset($_GET['week']) ? max(1, min(12, (int)$_GET['week'])) : $auto_week;

// Quests for selected week
$stmt = $conn->prepare("
    SELECT q.*, uq.completed_at
    FROM quests q
    LEFT JOIN user_quests uq ON q.id = uq.quest_id AND uq.user_id = ?
    WHERE q.week = ?
    ORDER BY q.id ASC
");
$stmt->bind_param("ii", $user_id, $selected_week);
$stmt->execute();
$quests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Total completed quests count
$stmt = $conn->prepare("SELECT COUNT(*) as total_done FROM user_quests WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_done_res = $stmt->get_result()->fetch_assoc();
$total_completed = (int)($total_done_res['total_done'] ?? 0);
$stmt->close();

// Total quests available
$total_quests_cnt = (int)($conn->query("SELECT COUNT(*) as cnt FROM quests")->fetch_assoc()['cnt'] ?? 14);
$overall_quest_percent = $total_quests_cnt > 0 ? round(($total_completed / $total_quests_cnt) * 100) : 0;

// Recent errors
$stmt = $conn->prepare("SELECT * FROM errors WHERE user_id = ? ORDER BY created_at DESC LIMIT 4");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_errors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Resources for selected week
$stmt = $conn->prepare("SELECT * FROM resources WHERE week = ? ORDER BY type ASC, id ASC");
$stmt->bind_param("i", $selected_week);
$stmt->execute();
$resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Today pomodoro count
$stmt = $conn->prepare("SELECT COUNT(*) as pomodoro_today FROM pomodoro_sessions WHERE user_id = ? AND DATE(completed_at) = CURDATE()");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$pomodoro_today = (int)($stmt->get_result()->fetch_assoc()['pomodoro_today'] ?? 0);
$stmt->close();

$conn->close();

$page_title = 'Dashboard Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Minggu <?= $selected_week ?> dari 12 · <?= count($quests) ?> quest</div>
        <h1 class="page-title"><?= !empty($quests) ? 'Fokus: ' . htmlspecialchars($quests[0]['title']) : 'Belum ada quest minggu ini' ?></h1>
        <p class="page-desc">Selesaikan satu target kecil hari ini. Progres tersimpan otomatis.</p>
        <div class="page-actions">
            <a href="timer.php" class="btn btn-cyber"><i class="fas fa-play me-1" aria-hidden="true"></i> Mulai sesi fokus</a>
            <a href="quests.php" class="btn btn-cyber-outline">Lihat roadmap</a>
        </div>
    </div>

    <section class="progress-strip" aria-label="Ringkasan progres belajar">
        <div class="strip-main">
            <div class="strip-level"><strong>Level <?= $level ?></strong><span><?= htmlspecialchars($rank_title) ?></span></div>
            <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $progress_percent ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres menuju level berikutnya"><div class="xp-progress-fill" id="levelProgressBar" style="width: <?= $progress_percent ?>%;"></div></div>
            <div class="strip-meta"><span><span id="statTotalXp"><?= (int)$user['xp'] ?></span> XP</span><span id="nextLevelXpText"><?= $xp_needed ?> XP lagi</span></div>
        </div>
        <div class="strip-side">
            <span><strong><?= (int)$user['streak'] ?></strong> hari konsisten</span>
            <span><strong><?= $total_completed ?>/<?= $total_quests_cnt ?></strong> quest (<?= $overall_quest_percent ?>%)</span>
        </div>
    </section>

    <!-- Main Content Area -->
    <div class="row g-4">
        <!-- Left Column: Quest Board for Selected Week -->
        <div class="col-lg-7">
            <section aria-labelledby="week-target-heading">
                <div class="quest-section-head">
                    <div>
                        <h2 id="week-target-heading">Target minggu ini</h2>
                        <p>Pilih satu target berikutnya.</p>
                    </div>

                    <!-- Week selector quick dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-cyber-outline btn-sm dropdown-toggle py-1 px-3" type="button" data-bs-toggle="dropdown">
                            Minggu <?= $selected_week ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end p-2" style="max-height: 280px; overflow-y: auto;">
                            <?php for ($w = 1; $w <= 12; $w++): ?>
                                <li>
                                    <a class="dropdown-item rounded py-1 px-3 small <?= $w === $selected_week ? 'active' : '' ?>" href="index.php?week=<?= $w ?>">
                                        Minggu <?= $w ?> <?= $w === $auto_week ? ' (Minggu Kamu)' : '' ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </div>
                </div>

                <!-- Quest items list -->
                <div class="quest-list d-flex flex-column gap-2">
                    <?php if (!empty($quests)): ?>
                        <?php foreach ($quests as $q): 
                            $is_done = !empty($q['completed_at']);
                        ?>
                            <div class="quest-item <?= $is_done ? 'completed' : '' ?>">
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
                                            <h3 class="h6 fw-bold mb-0 quest-title"><?= htmlspecialchars($q['title']) ?></h3>
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
                    <?php else: ?>
                        <div class="empty-state py-4">
                            <div class="empty-state-icon"><i class="fas fa-clipboard-check"></i></div>
                            <h3 class="h6 text-secondary">Tidak ada quest untuk minggu ini.</h3>
                            <p class="small text-muted">Silakan pilih minggu lainnya melalui tombol di atas.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-3">
                    <a href="quests.php" class="btn btn-cyber-outline w-100">Buka seluruh roadmap</a>
                </div>
            </section>
        </div>

        <!-- Right Column: Continue -->
        <div class="col-lg-5 d-flex flex-column gap-4">
            <section aria-labelledby="continue-material-heading">
                <div class="quest-section-head">
                    <div>
                        <h2 id="continue-material-heading">Materi minggu ini</h2>
                        <p>Referensi pendukung quest.</p>
                    </div>
                    <a href="resources.php?week=<?= $selected_week ?>" class="small text-secondary text-decoration-none">Lihat semua <i class="fas fa-chevron-right ms-1" aria-hidden="true"></i></a>
                </div>

                <?php if (!empty($resources)): ?>
                    <div>
                        <?php foreach (array_slice($resources, 0, 3) as $res): ?>
                            <a class="list-row" href="<?= htmlspecialchars($res['url']) ?>" target="_blank" rel="noopener noreferrer">
                                <div class="list-main">
                                    <p class="list-title"><?= htmlspecialchars($res['title']) ?></p>
                                    <p class="list-meta"><?= htmlspecialchars(ucfirst($res['type'])) ?> · Minggu <?= (int)$res['week'] ?></p>
                                </div>
                                <i class="fas fa-arrow-up-right-from-square list-chev" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary small mb-0">Belum ada materi untuk minggu ini.</p>
                <?php endif; ?>
            </section>

            <section aria-labelledby="continue-notes-heading">
                <div class="quest-section-head">
                    <div>
                        <h2 id="continue-notes-heading">Catatan terbaru</h2>
                        <p>Error dan solusi yang tersimpan.</p>
                    </div>
                    <a href="errors.php" class="small text-secondary text-decoration-none">Lihat semua <i class="fas fa-chevron-right ms-1" aria-hidden="true"></i></a>
                </div>

                <?php if (!empty($recent_errors)): ?>
                    <div>
                        <?php foreach ($recent_errors as $err): ?>
                            <a class="list-row" href="errors.php">
                                <div class="list-main">
                                    <p class="list-title"><?= htmlspecialchars(mb_strimwidth($err['error_message'], 0, 75, '...')) ?></p>
                                    <p class="list-meta"><?= htmlspecialchars($err['category'] ?? 'General') ?> · <?= !empty($err['solution']) ? 'Ada solusi' : 'Belum ada solusi' ?> · <?= date('d M', strtotime($err['created_at'])) ?></p>
                                </div>
                                <i class="fas fa-chevron-right list-chev" aria-hidden="true"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="fas fa-note-sticky" aria-hidden="true"></i></div>
                        <p class="text-secondary small mb-0">Belum ada catatan. Temui error saat coding? Catat untuk +5 XP.</p>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <a href="errors.php" class="btn btn-cyber-outline btn-sm w-100">Tulis catatan baru (+5 XP)</a>
                </div>
            </section>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
