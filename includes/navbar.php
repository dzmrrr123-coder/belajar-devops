<?php
$current_script = basename($_SERVER['PHP_SELF']);
$hud_user = null;
$hud_level = 1;

if (is_logged_in()) {
    $nav_conn = db_connect();
    $u_id = (int)$_SESSION['user_id'];
    $stmt = $nav_conn->prepare("SELECT id, username, email, xp, streak FROM users WHERE id = ?");
    $stmt->bind_param("i", $u_id);
    $stmt->execute();
    $hud_user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $nav_conn->close();

    if ($hud_user) {
        $hud_level = calculate_level($hud_user['xp']);
        $hud_rank = get_user_rank($hud_level);
    }
}
?>
<nav class="lt-navbar navbar navbar-expand-lg navbar-dark" aria-label="Navigasi Utama">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="brand-mark" aria-hidden="true">LT</span>
            <span>Learn Tracker</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <?php if (is_logged_in() && $hud_user): ?>
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3 gap-1">
                    <li class="nav-item">
                        <a class="lt-nav-link <?= $current_script === 'index.php' ? 'active' : '' ?>" href="index.php">
                            <i class="fas fa-grid-2"></i> Overview
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="lt-nav-link <?= $current_script === 'quests.php' ? 'active' : '' ?>" href="quests.php">
                            <i class="fas fa-map"></i> Roadmap
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="lt-nav-link <?= $current_script === 'resources.php' ? 'active' : '' ?>" href="resources.php">
                            <i class="fas fa-book-open"></i> Resources
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="lt-nav-link <?= $current_script === 'timer.php' ? 'active' : '' ?>" href="timer.php">
                            <i class="fas fa-clock"></i> Focus
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="lt-nav-link <?= $current_script === 'questions.php' ? 'active' : '' ?>" href="questions.php">
                            <i class="fas fa-circle-question"></i> Questions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="lt-nav-link <?= $current_script === 'errors.php' ? 'active' : '' ?>" href="errors.php">
                            <i class="fas fa-note-sticky"></i> Notes
                        </a>
                    </li>
                </ul>

                <!-- HUD Status & User Profile -->
                <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-lg-0">
                    <!-- Streak HUD -->
                    <div class="hud-pill streak" title="Streak Belajar Berkelanjutan">
                        <span class="flame-icon">🔥</span>
                        <span id="hudStreak"><?= (int)$hud_user['streak'] ?> Hari</span>
                    </div>

                    <!-- Level HUD -->
                    <div class="hud-pill level" title="Level Anda">
                        <i class="fas fa-shield-alt text-emerald"></i>
                        <span id="hudLevel">Lv. <?= $hud_level ?></span>
                    </div>

                    <!-- XP HUD -->
                    <div class="hud-pill xp" title="Total Pengalaman (XP)">
                        <i class="fas fa-bolt text-gold"></i>
                        <span id="hudXp"><?= (int)$hud_user['xp'] ?> XP</span>
                    </div>

                    <!-- Audio Toggle Button -->
                    <button id="ltSoundToggle" class="btn btn-cyber-outline btn-sm py-1 px-2" type="button" title="Toggle Suara">
                        <i class="fas fa-volume-up text-cyan"></i>
                    </button>

                    <!-- User Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-cyber-outline btn-sm dropdown-toggle py-1 px-2 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width: 26px; height: 26px; font-size: 0.75rem;">
                                <?= strtoupper(substr($hud_user['username'], 0, 1)) ?>
                            </div>
                            <span class="d-none d-md-inline fw-semibold"><?= htmlspecialchars($hud_user['username']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-2" style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); min-width: 220px;">
                            <li class="px-3 py-2 border-bottom border-secondary mb-2">
                                <div class="fw-bold text-white"><?= htmlspecialchars($hud_user['username']) ?></div>
                                <div class="small text-secondary"><?= htmlspecialchars($hud_user['email']) ?></div>
                                <div class="badge bg-primary text-white mt-1"><?= $hud_rank ?></div>
                            </li>
                            <li>
                                <a class="dropdown-item rounded py-2 small" href="index.php"><i class="fas fa-chart-simple me-2 text-primary"></i>Overview saya</a>
                            </li>
                            <li>
                                <a class="dropdown-item rounded py-2 small" href="quests.php"><i class="fas fa-map me-2 text-emerald"></i>Roadmap saya</a>
                            </li>
                            <li><hr class="dropdown-divider border-secondary"></li>
                            <li>
                                <a class="dropdown-item rounded py-2 small text-danger" href="logout.php">
                                    <i class="fas fa-sign-out-alt me-2"></i>Keluar (Logout)
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php else: ?>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <a href="login.php" class="btn btn-cyber-outline btn-sm"><i class="fas fa-sign-in-alt me-1"></i> Masuk</a>
                    <a href="register.php" class="btn btn-cyber btn-sm"><i class="fas fa-user-plus me-1"></i> Daftar Akun</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>
