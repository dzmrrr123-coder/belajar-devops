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
        ];
    } else {
        $nav_conn = db_connect();
        $stmt = $nav_conn->prepare("SELECT id, username, email, xp, streak, last_login_at FROM users WHERE id = ?");
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

$more_active = in_array($current_script, ['resources.php', 'questions.php', 'quiz.php', 'skills.php', 'digest.php', 'leaderboard.php'], true);
?>
<nav class="lt-navbar navbar navbar-expand-lg" aria-label="Navigasi Utama">
    <div class="container lt-navbar-inner">
        <a class="navbar-brand" href="index.php">
            <span class="brand-mark" aria-hidden="true">LT</span>
            <span class="brand-text">Learn Tracker</span>
        </a>

        <?php if (is_logged_in() && $hud_user): ?>
        <div class="d-flex align-items-center gap-2 ms-auto order-lg-2">
            <span class="hud-streak" title="Streak belajar">
                <i class="fas fa-fire" aria-hidden="true"></i>
                <span id="hudStreak"><?= (int)$hud_user['streak'] ?></span>
            </span>

            <div class="dropdown">
                <button class="avatar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu pengguna">
                    <span class="avatar-circle" aria-hidden="true"><?= strtoupper(substr($hud_user['username'], 0, 1)) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end lt-menu p-2">
                    <li class="lt-menu-head">
                        <span class="avatar-circle avatar-lg" aria-hidden="true"><?= strtoupper(substr($hud_user['username'], 0, 1)) ?></span>
                        <span class="lt-menu-id">
                            <strong><?= htmlspecialchars($hud_user['username']) ?></strong>
                            <small>Lv. <?= $hud_level ?> · <?= htmlspecialchars($hud_rank) ?> · <?= (int)$hud_user['xp'] ?> XP</small>
                        </span>
                    </li>
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i>Profil &amp; badge</a></li>
                    <li><a class="dropdown-item" href="leaderboard.php"><i class="fas fa-trophy"></i>Leaderboard</a></li>
                    <li><a class="dropdown-item" href="export.php"><i class="fas fa-file-export"></i>Export CV</a></li>
                    <li><button type="button" class="dropdown-item ltThemeToggle"><i class="fas fa-moon" aria-hidden="true"></i><span class="theme-toggle-label">Mode gelap</span></button></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item is-danger" href="logout.php"><i class="fas fa-arrow-right-from-bracket"></i>Keluar</a></li>
                </ul>
            </div>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Buka navigasi">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse order-lg-1" id="navbarContent">
            <ul class="navbar-nav lt-nav mx-lg-auto">
                <li class="nav-item"><a class="lt-nav-link <?= $current_script === 'index.php' ? 'active' : '' ?>" <?= $current_script === 'index.php' ? 'aria-current="page"' : '' ?> href="index.php">Overview</a></li>
                <li class="nav-item"><a class="lt-nav-link <?= $current_script === 'quests.php' ? 'active' : '' ?>" <?= $current_script === 'quests.php' ? 'aria-current="page"' : '' ?> href="quests.php">Roadmap</a></li>
                <li class="nav-item"><a class="lt-nav-link <?= $current_script === 'timer.php' ? 'active' : '' ?>" <?= $current_script === 'timer.php' ? 'aria-current="page"' : '' ?> href="timer.php">Fokus</a></li>
                <li class="nav-item"><a class="lt-nav-link <?= $current_script === 'review.php' ? 'active' : '' ?>" <?= $current_script === 'review.php' ? 'aria-current="page"' : '' ?> href="review.php">Review</a></li>
                <li class="nav-item"><a class="lt-nav-link <?= $current_script === 'errors.php' ? 'active' : '' ?>" <?= $current_script === 'errors.php' ? 'aria-current="page"' : '' ?> href="errors.php">Catatan</a></li>
                <li class="nav-item dropdown">
                    <a class="lt-nav-link dropdown-toggle <?= $more_active ? 'active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Lainnya</a>
                    <ul class="dropdown-menu lt-menu p-2">
                        <li><a class="dropdown-item <?= $current_script === 'resources.php' ? 'active' : '' ?>" href="resources.php"><i class="fas fa-book-open"></i>Resources</a></li>
                        <li><a class="dropdown-item <?= $current_script === 'questions.php' ? 'active' : '' ?>" href="questions.php"><i class="fas fa-circle-question"></i>Questions</a></li>
                        <li><a class="dropdown-item <?= $current_script === 'quiz.php' ? 'active' : '' ?>" href="quiz.php"><i class="fas fa-brain"></i>Kuis</a></li>
                        <li><a class="dropdown-item <?= $current_script === 'skills.php' ? 'active' : '' ?>" href="skills.php"><i class="fas fa-layer-group"></i>Skill tree</a></li>
                        <li><a class="dropdown-item <?= $current_script === 'digest.php' ? 'active' : '' ?>" href="digest.php"><i class="fas fa-calendar-week"></i>Ringkasan</a></li>
                        <li><a class="dropdown-item <?= $current_script === 'leaderboard.php' ? 'active' : '' ?>" href="leaderboard.php"><i class="fas fa-trophy"></i>Leaderboard</a></li>
                    </ul>
                </li>
            </ul>
            <form method="GET" action="search.php" class="lt-search" role="search">
                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                <input name="q" placeholder="Cari…" maxlength="100" aria-label="Cari" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
            </form>
        </div>
        <?php else: ?>
        <div class="ms-auto d-flex align-items-center gap-2">
            <button class="theme-toggle ltThemeToggle" type="button" title="Mode gelap / terang" aria-label="Ganti tema gelap atau terang">
                <i class="fas fa-moon" aria-hidden="true"></i>
            </button>
            <a href="login.php" class="btn btn-cyber-outline btn-sm">Masuk</a>
            <a href="register.php" class="btn btn-cyber btn-sm">Daftar</a>
        </div>
        <?php endif; ?>
    </div>
</nav>
