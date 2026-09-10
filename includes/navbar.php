<?php
$current_script = basename($_SERVER['PHP_SELF']);
$hud_user = null;
$hud_level = 1;
$hud_rank = '';

if (is_logged_in()) {
    $u_id = (int)$_SESSION['user_id'];
    if (isset($user) && is_array($user) && (int)($user['id'] ?? 0) === $u_id && isset($user['username'], $user['xp'], $user['streak'])) {
        $hud_user = [
            'username' => $user['username'],
            'email' => $user['email'] ?? '',
            'xp' => $user['xp'],
            'streak' => $user['streak'],
            'last_login_at' => $user['last_login_at'] ?? null,
            'avatar_frame' => $user['avatar_frame'] ?? 'default',
            'track' => $user['track'] ?? 'devops',
        ];
    } else {
        $nav_conn = db_connect();
        $stmt = $nav_conn->prepare("SELECT id, username, email, xp, streak, last_login_at, avatar_frame, track FROM users WHERE id = ?");
        $stmt->bind_param("i", $u_id);
        $stmt->execute();
        $hud_user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if ($hud_user) {
        $hud_level = calculate_level($hud_user['xp']);
        $hud_rank = get_user_rank($hud_level);
    }
}

$minimal_nav = !empty($minimal_nav) || $current_script === 'onboarding.php';
if ($minimal_nav && is_logged_in()):
?>
<nav class="lt-navbar navbar navbar-expand-lg" aria-label="Navigasi minimal">
    <div class="container lt-navbar-inner">
        <span class="navbar-brand"><span class="brand-mark" aria-hidden="true">LT</span><span class="brand-text">Learn Tracker</span></span>
        <div class="ms-auto d-flex align-items-center gap-2">
            <span class="small text-secondary d-none d-sm-inline">Langkah awal · 1 menit</span>
            <a href="logout.php" class="btn btn-cyber-outline btn-sm">Keluar</a>
        </div>
    </div>
</nav>
<?php return; endif; ?>

<?php
if (is_logged_in() && $hud_user):
    $layout_opened = true;
?>
<div class="app-layout">
    <!-- Desktop Sidebar -->
    <aside class="app-sidebar">
        <a class="sidebar-brand" href="hub.php">
            <span class="brand-mark" aria-hidden="true">LT</span>
            <span class="brand-text">Learn Tracker</span>
        </a>
        <div class="sidebar-nav">
            <div class="sidebar-section">Belajar</div>
            <a class="sidebar-link <?= $current_script === 'hub.php' ? 'active' : '' ?>" href="hub.php"><i class="fas fa-desktop"></i>Hub Jurusan</a>
            <a class="sidebar-link <?= $current_script === 'quests.php' ? 'active' : '' ?>" href="quests.php"><i class="fas fa-map"></i>Roadmap</a>
            <a class="sidebar-link <?= $current_script === 'timer.php' ? 'active' : '' ?>" href="timer.php"><i class="fas fa-clock"></i>Fokus</a>
            <a class="sidebar-link <?= $current_script === 'review.php' ? 'active' : '' ?>" href="review.php"><i class="fas fa-rotate-right"></i>Review</a>
            <a class="sidebar-link <?= $current_script === 'errors.php' ? 'active' : '' ?>" href="errors.php"><i class="fas fa-note-sticky"></i>Catatan Error</a>
            <a class="sidebar-link <?= $current_script === 'lab.php' ? 'active' : '' ?>" href="lab.php"><i class="fas fa-flask"></i>Lab Praktik</a>

            <div class="sidebar-section">Kamu</div>
            <a class="sidebar-link <?= $current_script === 'leaderboard.php' ? 'active' : '' ?>" href="leaderboard.php"><i class="fas fa-trophy"></i>Leaderboard</a>
            <a class="sidebar-link <?= $current_script === 'squad.php' ? 'active' : '' ?>" href="squad.php"><i class="fas fa-users"></i>Squad</a>
            <a class="sidebar-link <?= $current_script === 'profile.php' ? 'active' : '' ?>" href="profile.php"><i class="fas fa-user"></i>Profil &amp; Toko</a>
        </div>
        <div class="sidebar-footer">
            <a href="profile.php" class="sidebar-profile">
                <span class="avatar-circle avatar-sm frame-<?= htmlspecialchars($hud_user['avatar_frame'] ?? 'default') ?>" aria-hidden="true"><?= strtoupper(substr($hud_user['username'], 0, 1)) ?></span>
                <div class="sidebar-profile-info">
                    <strong><?= htmlspecialchars($hud_user['username']) ?></strong>
                    <small>Lv. <?= $hud_level ?> · <?= (int)$hud_user['xp'] ?> XP</small>
                </div>
            </a>
            <div class="d-flex align-items-center gap-2 mt-2">
                <a href="logout.php" class="btn btn-cyber-outline btn-sm flex-grow-1">Keluar</a>
            </div>
        </div>
    </aside>

    <div class="app-content">
        <!-- Mobile Topbar -->
        <header class="app-topbar">
            <a class="app-topbar-brand" href="hub.php">
                <span class="brand-mark" aria-hidden="true">LT</span>
                Learn Tracker
            </a>
            <div class="d-flex align-items-center gap-2">
                <span class="hud-streak" title="Streak belajar">
                    <i class="fas fa-fire" aria-hidden="true"></i>
                    <span id="hudStreak"><?= (int)$hud_user['streak'] ?></span>
                </span>
                <a href="profile.php">
                    <span class="avatar-circle avatar-sm frame-<?= htmlspecialchars($hud_user['avatar_frame'] ?? 'default') ?>" aria-hidden="true"><?= strtoupper(substr($hud_user['username'], 0, 1)) ?></span>
                </a>
            </div>
        </header>

<?php else: ?>
<!-- Fallback for non-logged-in users -->
<nav class="lt-navbar navbar navbar-expand-lg" aria-label="Navigasi Utama">
    <div class="container lt-navbar-inner">
        <a class="navbar-brand" href="hub.php">
            <span class="brand-mark" aria-hidden="true">LT</span>
            <span class="brand-text">Learn Tracker</span>
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="pricing.php" class="btn btn-cyber-outline btn-sm">Harga</a>
            <a href="login.php" class="btn btn-cyber-outline btn-sm">Masuk</a>
            <a href="register.php" class="btn btn-cyber btn-sm">Daftar</a>
        </div>
    </div>
</nav>
<?php endif; ?>
