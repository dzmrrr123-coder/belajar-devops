<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
$track = user_track($conn, $user_id);
$trackInfo = \App\Domain\Track\Tracks::all()[$track] ?? ['name' => strtoupper($track), 'icon' => 'fas fa-graduation-cap', 'desc' => ''];
$widgetData = \App\Domain\Track\Hub::getWidgetData($conn, $user_id, $track);
$today_streak = 0; $today_chest_opened = false; $today_due = 0; $today_freeze = 0;
$next_quest = null;
try {
    $hq = $conn->prepare("SELECT streak, freeze_tokens FROM users WHERE id = ?");
    if ($hq) { $hq->bind_param("i", $user_id); $hq->execute(); $urow = $hq->get_result()->fetch_assoc() ?: []; $hq->close(); $today_streak = (int)($urow['streak'] ?? 0); $today_freeze = (int)($urow['freeze_tokens'] ?? 0); }
    $hq = $conn->prepare("SELECT 1 FROM daily_chests WHERE user_id = ? AND chest_date = CURDATE()");
    if ($hq) { $hq->bind_param("i", $user_id); $hq->execute(); $today_chest_opened = (bool)$hq->get_result()->fetch_assoc(); $hq->close(); }
    $hq = $conn->prepare("SELECT COUNT(*) c FROM reviews WHERE user_id = ? AND next_due <= CURDATE()");
    if ($hq) { $hq->bind_param("i", $user_id); $hq->execute(); $today_due = (int)($hq->get_result()->fetch_assoc()['c'] ?? 0); $hq->close(); }
    $nq = $conn->prepare("SELECT q.id, q.title, q.xp_reward FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE uq.quest_id IS NULL AND ((q.user_id IS NULL AND (q.track = ? OR q.track = 'all' OR q.track IS NULL OR q.track = '')) OR (q.user_id = ? AND (q.track = ? OR q.track IS NULL OR q.track = ''))) ORDER BY q.week ASC, q.id ASC LIMIT 1");
    if ($nq) { $nq->bind_param("isiss", $user_id, $track, $user_id, $track); $nq->execute(); $next_quest = $nq->get_result()->fetch_assoc() ?: null; $nq->close(); }
} catch (Throwable $e) {}
$conn->close();

$page_title = 'Hub Jurusan ' . $trackInfo['name'];
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" id="main">
    <!-- Header Banner -->
    <div class="page-head mb-4">
        <div class="page-kicker eyebrow"><i class="<?= htmlspecialchars($trackInfo['icon']) ?> me-1"></i> <?= htmlspecialchars($trackInfo['name']) ?> · Hub</div>
        <h1 class="page-title">Hub <?= htmlspecialchars($trackInfo['name']) ?></h1>
        <p class="page-desc mb-3"><?= htmlspecialchars($trackInfo['desc']) ?></p>

        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="progress flex-grow-1" style="height: 10px; max-width: 300px; background: var(--surface-2);">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= $widgetData['track_progress']['percent'] ?>%" aria-valuenow="<?= $widgetData['track_progress']['percent'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <span class="small font-monospace text-muted"><?= $widgetData['track_progress']['percent'] ?>% Selesai (<?= $widgetData['track_progress']['done'] ?>/<?= $widgetData['track_progress']['total'] ?>)</span>
        </div>
        <?php if ($next_quest): ?>
        <div class="mt-3">
            <a href="quests.php#next" class="btn btn-cyber"><i class="fas fa-play me-1"></i>Lanjut: <?= htmlspecialchars(mb_strimwidth($next_quest['title'], 0, 48, '…')) ?> (+<?= (int)$next_quest['xp_reward'] ?> XP)</a>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-3 flex-wrap mt-2 small">
            <a href="timer.php" class="text-decoration-none">Fokus 25 menit</a>
            <a href="review.php" class="text-decoration-none">Review<?= $today_due > 0 ? ' (' . (int)$today_due . ' antre)' : '' ?></a>
            <a href="profile.php#shop" class="text-decoration-none" title="Freeze melindungi streak"><i class="fas fa-fire me-1"></i><?= (int)$today_streak ?> hari<?= $today_freeze > 0 ? ' · ' . (int)$today_freeze . ' freeze' : '' ?></a>
        </div>
        <div class="mt-2" aria-live="polite">
            <?php if (!$today_chest_opened): ?>
            <form method="POST" action="claim_chest.php" class="m-0" id="chestForm"><?= csrf_field() ?>
                <button class="chest-btn" type="submit" id="chestBtn"><i class="fas fa-gift me-1"></i><span>Peti harian: buka +8 XP</span></button>
            </form>
            <?php else: ?><span class="stat-chip">Peti hari ini dibuka</span><?php endif; ?>
        </div>
        <script>
        document.getElementById('chestForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('chestBtn');
            const fd = new FormData(this);
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>Membuka…</span>';
            try {
                const r = await fetch('claim_chest.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
                const d = await r.json();
                showToast(d.message || 'Peti dibuka!', d.status === 'success' ? 'success' : 'info');
                if (d.status === 'success') btn.parentElement.innerHTML = '<span class="stat-chip">Peti hari ini dibuka</span>';
                else { btn.disabled = false; btn.innerHTML = '<i class="fas fa-gift me-1"></i><span>Peti harian: buka +8 XP</span>'; }
            } catch (err) { this.submit(); }
        });
        </script>
    </div>

    <!-- Track Specific Content -->
    <?php if ($track === 'rpl'): ?>
    <div class="row g-4">
        <div class="col-lg-8 d-flex flex-column gap-4">
            <section class="card border-0 shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h5 fw-bold mb-0"><i class="fas fa-terminal me-2 text-primary"></i>Code Studio</h2>
                    <a href="lab.php?tab=praktik&alat=playground" class="btn btn-sm btn-cyber-outline">Buka Playground <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6">
                        <a href="lab.php?tab=praktik" class="text-decoration-none">
                            <div class="p-3 rounded border h-100 dash-hover" style="background:var(--surface-2)">
                                <h3 class="h6 mb-1"><i class="fas fa-flask text-warning me-2"></i>Algorithmic Labs</h3>
                                <p class="small text-secondary mb-0">Tantangan koding harian</p>
                            </div>
                        </a>
                    </div>
                    <div class="col-sm-6">
                        <a href="quests.php" class="text-decoration-none">
                            <div class="p-3 rounded border h-100 dash-hover" style="background:var(--surface-2)">
                                <h3 class="h6 mb-1"><i class="fas fa-code-branch text-success me-2"></i>Project Roadmap</h3>
                                <p class="small text-secondary mb-0">Lanjutkan sprint mingguanmu</p>
                            </div>
                        </a>
                    </div>
                </div>
            </section>
            
            <section class="card border-0 shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0"><i class="fas fa-bug text-danger me-2"></i>Bug Tracker (Recent Errors)</h2>
                    <a href="errors.php" class="small text-decoration-none">Semua Catatan</a>
                </div>
                <?php if (!empty($widgetData['recent_bugs'])): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($widgetData['recent_bugs'] as $bug): ?>
                        <div class="list-group-item px-0 bg-transparent border-secondary-subtle">
                            <div class="d-flex justify-content-between w-100">
                                <h6 class="mb-1 small font-monospace fw-bold"><?= htmlspecialchars($bug['category']) ?></h6>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('d M', strtotime($bug['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 small text-secondary text-truncate"><?= htmlspecialchars($bug['error_message']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Belum ada catatan error. Sistem bersih!</p>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-4">
            <!-- Mentor Sidebar Widget -->
            <section class="card border-0 shadow-sm p-4 h-100 bg-body-tertiary">
                <h2 class="h6 fw-bold mb-3"><i class="fas fa-robot text-primary me-2"></i>AI Code Reviewer</h2>
                <p class="small text-muted mb-4">Tanyakan struktur database, refactoring kode, atau arsitektur Laravel ke mentormu.</p>
                <a href="lab.php?tab=mentor" class="btn btn-cyber w-100 mt-auto"><i class="fas fa-message me-2"></i>Tanya Mentor</a>
            </section>
        </div>
    </div>

    <?php elseif ($track === 'tkj'): ?>
    <div class="row g-4">
        <div class="col-lg-8 d-flex flex-column gap-4">
            <section class="card border-0 shadow-sm overflow-hidden">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center" style="background:var(--surface-2)">
                    <h2 class="h5 fw-bold mb-0"><i class="fas fa-server me-2 text-success"></i>Network Operations Center</h2>
                    <span class="badge bg-success-subtle text-success"><i class="fas fa-circle-check me-1"></i>Semua sistem normal</span>
                </div>
                <div class="p-0 row g-0">
                    <div class="col-sm-6 border-end p-4">
                        <h3 class="h6 mb-3"><i class="fas fa-network-wired me-2 text-primary"></i>Topology Canvas</h3>
                        <p class="small text-secondary mb-3">Rancang topologi jaringan interaktif dengan router, switch, dan PC.</p>
                        <a href="lab.php?tab=praktik&alat=topologi" class="btn btn-sm btn-cyber-outline w-100">Buka Kanvas</a>
                    </div>
                    <div class="col-sm-6 p-4">
                        <h3 class="h6 mb-3"><i class="fas fa-fire-extinguisher me-2 text-warning"></i>Incident Simulator</h3>
                        <p class="small text-secondary mb-3">Simulasikan server down dan pelajari cara memperbaikinya.</p>
                        <a href="lab.php?tab=praktik&alat=incident" class="btn btn-sm btn-cyber w-100">Simulasi Sekarang</a>
                    </div>
                </div>
            </section>
            
            <section class="card border-0 shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0"><i class="fas fa-folder-open text-primary me-2"></i>Desain Topologi Tersimpan</h2>
                </div>
                <?php if (!empty($widgetData['recent_topologies'])): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($widgetData['recent_topologies'] as $topo): ?>
                        <a href="lab.php?tab=praktik&alat=topologi" class="p-3 rounded border border-secondary-subtle bg-surface text-decoration-none d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-body"><?= htmlspecialchars($topo['name']) ?></span>
                            <span class="small text-muted font-monospace"><?= date('d M Y', strtotime($topo['created_at'])) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-4 bg-body-tertiary rounded">
                        <p class="text-muted small mb-0">Belum ada topologi tersimpan.</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        <div class="col-lg-4 d-flex flex-column gap-4">
            <section class="card border-0 shadow-sm p-4 bg-body-tertiary">
                <h2 class="h6 fw-bold mb-3"><i class="fas fa-calculator text-info me-2"></i>Quick Subnet</h2>
                <form action="lab.php" method="GET" class="m-0">
                    <input type="hidden" name="tab" value="praktik">
                    <input type="hidden" name="alat" value="topologi">
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" name="cidr" class="form-control font-monospace" placeholder="192.168.1.0/24" aria-label="CIDR, contoh 192.168.1.0/24">
                        <button class="btn btn-cyber" type="submit">Hitung</button>
                    </div>
                </form>
            </section>
            
            <section class="card border-0 shadow-sm p-4 flex-grow-1">
                <h2 class="h6 fw-bold mb-3"><i class="fas fa-terminal text-secondary me-2"></i>Lab Linux</h2>
                <p class="small text-muted mb-4">Akses terminal virtual untuk berlatih perintah dasar sysadmin.</p>
                <a href="lab.php?tab=praktik&alat=terminal" class="btn btn-cyber-outline w-100 mt-auto"><i class="fas fa-arrow-right me-2"></i>Akses Terminal</a>
            </section>
        </div>
    </div>

    <?php elseif ($track === 'dkv'): ?>
    <div class="row g-4">
        <div class="col-lg-12">
            <section class="card border-0 shadow-sm overflow-hidden arena-banner">
                <div class="row g-0">
                    <div class="col-md-8 p-5 d-flex flex-column justify-content-center">
                        <h2 class="display-6 fw-bold mb-2">Creative Atelier</h2>
                        <p class="lead text-secondary mb-4">Selesaikan design brief mingguan, kumpulkan portofolio, dan asah skill visualmu.</p>
                        <div class="d-flex gap-3">
                            <a href="quests.php" class="btn btn-cyber px-4">Lihat Design Brief</a>
                            <a href="lab.php?tab=praktik" class="btn btn-cyber-outline px-4">Tantangan Cepat</a>
                        </div>
                    </div>
                    <div class="col-md-4 d-none d-md-flex align-items-center justify-content-center p-4">
                        <i class="fas fa-bezier-curve opacity-25" style="font-size: 8rem;"></i>
                    </div>
                </div>
            </section>
        </div>
        
        <div class="col-lg-12">
            <h2 class="h6 fw-bold text-uppercase tracking-wider text-muted mb-3"><i class="fas fa-images me-2"></i>Portofolio Terakhir</h2>
            <?php if (!empty($widgetData['recent_karya'])): ?>
                <div class="row g-3">
                    <?php foreach ($widgetData['recent_karya'] as $karya): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card border-0 shadow-sm overflow-hidden h-100">
                            <img src="<?= htmlspecialchars($karya['url']) ?>" class="card-img-top" alt="Karya" loading="lazy" decoding="async" width="400" height="140" style="height: 140px; object-fit: cover;" onerror="this.src='https://placehold.co/400x300/e2e8f0/64748b?text=Karya'">
                            <div class="card-body p-2">
                                <h3 class="h6 mb-0 small text-truncate" title="<?= htmlspecialchars($karya['title']) ?>"><?= htmlspecialchars($karya['title']) ?></h3>
                                <small class="text-muted" style="font-size: 0.65rem;"><?= date('d M Y', strtotime($karya['created_at'])) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm p-5 text-center bg-body-tertiary">
                    <i class="fas fa-image text-muted mb-3 fs-2"></i>
                    <p class="text-muted small mb-0">Belum ada karya yang diunggah. Selesaikan quest untuk membangun portofolio!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($track === 'devops'): ?>
    <div class="row g-4">
        <div class="col-lg-8 d-flex flex-column gap-4">
            <section class="card border-0 shadow-sm overflow-hidden">
                <div class="p-4 border-bottom d-flex justify-content-between align-items-center" style="background:var(--surface-2)">
                    <h2 class="h5 fw-bold mb-0"><i class="fas fa-rocket me-2 text-primary"></i>DevOps Command Center</h2>
                    <span class="badge bg-primary-subtle text-primary"><i class="fas fa-circle-check me-1"></i>Pipeline siap</span>
                </div>
                <div class="p-0 row g-0">
                    <div class="col-sm-4 border-end p-4 text-center">
                        <a href="lab.php?tab=praktik&alat=incident" class="text-decoration-none d-block h-100 text-body">
                            <i class="fas fa-fire-extinguisher fs-2 text-danger mb-3"></i>
                            <h3 class="h6 fw-bold mb-1">Incident Sim</h3>
                            <p class="small text-secondary mb-0">Latih penanganan error</p>
                        </a>
                    </div>
                    <div class="col-sm-4 border-end p-4 text-center">
                        <a href="lab.php?tab=praktik&alat=terminal" class="text-decoration-none d-block h-100 text-body">
                            <i class="fas fa-terminal fs-2 text-success mb-3"></i>
                            <h3 class="h6 fw-bold mb-1">Terminal</h3>
                            <p class="small text-secondary mb-0">Linux & Git cli</p>
                        </a>
                    </div>
                    <div class="col-sm-4 p-4 text-center">
                        <a href="lab.php?tab=praktik&alat=playground" class="text-decoration-none d-block h-100 text-body">
                            <i class="fas fa-code fs-2 text-info mb-3"></i>
                            <h3 class="h6 fw-bold mb-1">Playground</h3>
                            <p class="small text-secondary mb-0">Test scripts</p>
                        </a>
                    </div>
                </div>
            </section>

            <section class="card border-0 shadow-sm p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h6 fw-bold mb-0"><i class="fas fa-clipboard-list text-warning me-2"></i>Recent Incidents (Catatan)</h2>
                    <a href="errors.php" class="small text-decoration-none">Lihat Semua</a>
                </div>
                <?php if (!empty($widgetData['recent_bugs'])): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($widgetData['recent_bugs'] as $bug): ?>
                        <div class="list-group-item px-0 bg-transparent border-secondary-subtle">
                            <div class="d-flex justify-content-between w-100">
                                <h6 class="mb-1 small font-monospace fw-bold text-danger"><i class="fas fa-triangle-exclamation me-1"></i> <?= htmlspecialchars($bug['category']) ?></h6>
                                <small class="text-muted" style="font-size: 0.7rem;"><?= date('d M H:i', strtotime($bug['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 small text-secondary text-truncate"><?= htmlspecialchars($bug['error_message']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Belum ada insiden. Produksi aman.</p>
                <?php endif; ?>
            </section>
        </div>
        
        <div class="col-lg-4 d-flex flex-column gap-4">
            <!-- Mentor Sidebar Widget -->
            <section class="card border-0 shadow-sm p-4 h-100 bg-body-tertiary">
                <h2 class="h6 fw-bold mb-3"><i class="fas fa-robot text-primary me-2"></i>AI Ops Assistant</h2>
                <p class="small text-muted mb-4">Tanyakan konfigurasi Nginx, Dockerfile, atau pipeline CI/CD ke asisten AI-mu.</p>
                <a href="lab.php?tab=mentor" class="btn btn-cyber w-100 mt-auto"><i class="fas fa-message me-2"></i>Tanya Assistant</a>
            </section>
        </div>
    </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>
