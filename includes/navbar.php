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
    $hud_last = null;
    try {
        $ls = $nav_conn->prepare("SELECT last_login_at FROM users WHERE id = ?");
        if ($ls) {
            $ls->bind_param("i", $u_id);
            $ls->execute();
            $hud_last = $ls->get_result()->fetch_assoc()['last_login_at'] ?? null;
            $ls->close();
        }
    } catch (Throwable $e) {}
    $nav_conn->close();

    if ($hud_user) {
        $hud_level = calculate_level($hud_user['xp']);
        $hud_rank = get_user_rank($hud_level);
    }
}
?>
<nav class="lt-navbar navbar navbar-expand-lg navbar-light" aria-label="Navigasi Utama">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <span class="brand-mark" aria-hidden="true">LT</span>
            <span>Learn Tracker</span>
        </a>

        <?php if (is_logged_in()): ?>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <?php endif; ?>

        <div class="collapse navbar-collapse<?= is_logged_in() ? '' : ' show' ?>" id="navbarContent">
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

                <div class="d-flex align-items-center flex-wrap gap-2 mt-2 mt-lg-0">
                    <div class="hud-pill streak" title="Streak belajar">
                        <i class="fas fa-fire" aria-hidden="true"></i>
                        <span id="hudStreak"><?= (int)$hud_user['streak'] ?> hari</span>
                    </div>

                    <button id="ltSoundToggle" class="btn btn-cyber-outline btn-sm py-1 px-2" type="button" title="Toggle Suara" aria-label="Toggle suara">
                        <i class="fas fa-volume-up" aria-hidden="true"></i>
                    </button>

                    <!-- User Dropdown (desktop) -->
                    <div class="dropdown d-none d-lg-block">
                        <button class="btn btn-cyber-outline btn-sm dropdown-toggle py-1 px-2 d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 26px; height: 26px; font-size: 0.75rem; background: var(--primary); color: #fff;">
                                <?= strtoupper(substr($hud_user['username'], 0, 1)) ?>
                            </div>
                            <span class="d-none d-md-inline fw-semibold"><?= htmlspecialchars($hud_user['username']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark p-2" style="min-width: 240px;">
                            <li class="px-3 py-2 border-bottom border-secondary mb-2">
                                <div class="fw-bold text-white"><?= htmlspecialchars($hud_user['username']) ?></div>
                                <div class="small text-secondary"><?= htmlspecialchars($hud_user['email']) ?></div>
                                <div class="small text-secondary mb-2"><?= isset($hud_last) && $hud_last ? 'Terakhir aktif ' . htmlspecialchars(date('d M, H:i', strtotime($hud_last))) : 'Kunjungan pertama' ?></div>
                                <div class="hud-menu-stat"><div class="lbl">Level · <?= htmlspecialchars($hud_rank) ?></div><div class="val" id="hudLevel">Lv. <?= $hud_level ?></div></div>
                                <div class="hud-menu-stat mb-0"><div class="lbl">Pengalaman</div><div class="val" id="hudXp"><?= (int)$hud_user['xp'] ?> XP</div></div>
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

                    <!-- User Block (mobile): static, no floating menu -->
                    <div class="d-lg-none w-100 mt-2 pt-3 border-top">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.85rem; background: var(--primary); color: #fff;" aria-hidden="true">
                                <?= strtoupper(substr($hud_user['username'], 0, 1)) ?>
                            </div>
                            <div class="min-w-0">
                                <div class="fw-bold"><?= htmlspecialchars($hud_user['username']) ?></div>
                                <div class="small text-secondary"><?= htmlspecialchars($hud_user['email']) ?></div>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mb-3">
                            <div class="hud-menu-stat flex-fill mb-0"><div class="lbl">Level</div><div class="val">Lv. <?= $hud_level ?> · <?= htmlspecialchars($hud_rank) ?></div></div>
                            <div class="hud-menu-stat flex-fill mb-0"><div class="lbl">XP</div><div class="val"><?= (int)$hud_user['xp'] ?> XP</div></div>
                        </div>
                        <a href="logout.php" class="btn btn-cyber-danger btn-logout w-100">
                            <i class="fas fa-sign-out-alt me-2" aria-hidden="true"></i>Keluar
                        </a>
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
