<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $username = clean($_POST['username'] ?? '');
        $email = clean($_POST['email'] ?? '');
        if (strlen($username) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) set_flash('warning', 'Username minimal 3 karakter (huruf/angka/underscore).');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) set_flash('warning', 'Email tidak valid.');
        else {
            $chk = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ?");
            $chk->bind_param("ssi", $username, $email, $user_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) set_flash('danger', 'Username atau email sudah dipakai.');
            else {
                $up = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
                $up->bind_param("ssi", $username, $email, $user_id);
                $up->execute(); $up->close();
                $_SESSION['username'] = $username;
                set_flash('success', 'Profil diperbarui.');
            }
            $chk->close();
        }
        redirect('profile.php');
    }
    if ($action === 'visibility') {
        $board = !empty($_POST['show_on_board']) ? 1 : 0;
        $pub = !empty($_POST['public_profile']) ? 1 : 0;
        $up = $conn->prepare("UPDATE users SET show_on_board = ?, public_profile = ? WHERE id = ?");
        $up->bind_param("iii", $board, $pub, $user_id);
        $up->execute(); $up->close();
        set_flash('success', 'Visibilitas diperbarui.');
        redirect('profile.php');
    }
    if ($action === 'delete_account') {
        $pw = $_POST['confirm_password'] ?? '';
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id); $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$row || !password_verify($pw, $row['password'])) {
            set_flash('danger', 'Password salah. Akun batal dihapus.');
            redirect('profile.php');
        }
        clear_remember_token($conn);
        $del = $conn->prepare("DELETE FROM users WHERE id = ?");
        $del->bind_param("i", $user_id); $del->execute(); $del->close();
        $conn->close();
        session_destroy();
        session_start();
        set_flash('info', 'Akun dihapus. Sampai jumpa!');
        redirect('register.php');
    }
    if ($action === 'change_password') {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (strlen($new) < 6) set_flash('warning', 'Password baru minimal 6 karakter.');
        else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row || !password_verify($old, $row['password'])) set_flash('danger', 'Password lama salah.');
            else {
                $hash = password_hash($new, PASSWORD_BCRYPT);
                $up = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $up->bind_param("si", $hash, $user_id);
                $up->execute(); $up->close();
                set_flash('success', 'Password diganti.');
            }
        }
        redirect('profile.php');
    }
}

$stmt = $conn->prepare("SELECT id, username, email, xp, streak, last_active_date, freeze_tokens, best_streak, show_on_board, public_profile, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) { session_destroy(); redirect('login.php'); }
$level = calculate_level($user['xp']);
$rank = get_user_rank($level);
$pct = level_progress_percent($user['xp']);

$stats = ['quest_done' => 0, 'quest_total' => 0, 'pomodoro' => 0, 'notes' => 0, 'q_open' => 0];
$stmt = $conn->prepare("SELECT COUNT(*) c FROM quests WHERE user_id IS NULL OR user_id = ?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$stats['quest_total'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$stmt = $conn->prepare("SELECT COUNT(*) c FROM user_quests uq JOIN quests q ON q.id=uq.quest_id WHERE uq.user_id=? AND (q.user_id IS NULL OR q.user_id=?)");
$stmt->bind_param("ii", $user_id, $user_id); $stmt->execute();
$stats['quest_done'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$stmt = $conn->prepare("SELECT COUNT(*) c FROM pomodoro_sessions WHERE user_id=?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$stats['pomodoro'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$stmt = $conn->prepare("SELECT COUNT(*) c FROM errors WHERE user_id=?");
$stmt->bind_param("i", $user_id); $stmt->execute();
$stats['notes'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();
$stmt = $conn->prepare("SELECT COUNT(*) c FROM questions WHERE user_id=? AND status='open'");
$stmt->bind_param("i", $user_id); $stmt->execute();
$stats['q_open'] = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$activity = [];
foreach (['user_quests' => 'completed_at', 'pomodoro_sessions' => 'completed_at', 'errors' => 'created_at', 'questions' => 'created_at'] as $tbl => $col) {
    try {
        $q = $conn->prepare("SELECT DATE($col) d, COUNT(*) c FROM $tbl WHERE user_id=? AND $col >= DATE_SUB(CURDATE(), INTERVAL 83 DAY) GROUP BY DATE($col)");
        $q->bind_param("i", $user_id); $q->execute();
        foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $activity[$r['d']] = ($activity[$r['d']] ?? 0) + (int)$r['c'];
        $q->close();
    } catch (Throwable $e) {}
}
$days = [];
for ($i = 83; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $c = $activity[$d] ?? 0;
    $lvl = $c <= 0 ? 0 : ($c === 1 ? 1 : ($c <= 3 ? 2 : ($c <= 5 ? 3 : 4)));
    $days[] = ['date' => $d, 'count' => $c, 'lvl' => $lvl];
}
$badges_owned = user_badges($conn, $user_id);
$badge_list = badge_defs();
$conn->close();
$page_title = 'Profil & Statistik';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Level <?= $level ?> · <?= htmlspecialchars($rank) ?></div>
        <h1 class="page-title"><?= htmlspecialchars($user['username']) ?></h1>
        <p class="page-desc"><?= (int)$user['xp'] ?> XP · <?= (int)$user['streak'] ?> hari konsisten (terbaik <?= (int)($user['best_streak'] ?? 0) ?>) · <i class="fas fa-snowflake"></i> <?= (int)($user['freeze_tokens'] ?? 0) ?> freeze · sejak <?= date('M Y', strtotime($user['created_at'])) ?></p>
        <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"><div class="xp-progress-fill" style="width:<?= $pct ?>%"></div></div>
    </div>
    <div class="row g-4 align-items-start">
        <div class="col-lg-7 d-flex flex-column gap-4">
            <section class="card p-4" aria-label="Aktivitas 12 minggu">
                <h2 class="h5 fw-bold mb-1">Konsistensi 12 minggu</h2>
                <p class="text-secondary small mb-3">Semakin gelap, semakin aktif. Ketuk kotak untuk detail.</p>
                <div class="heatmap" role="img" aria-label="Heatmap aktivitas">
                    <?php foreach ($days as $d): ?>
                    <span class="heat lvl-<?= $d['lvl'] ?>" title="<?= $d['date'] ?> · <?= $d['count'] ?> aktivitas" tabindex="0"></span>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-3 small text-muted mt-2"><span><strong><?= $stats['quest_done'] ?>/<?= $stats['quest_total'] ?></strong> quest</span><span><strong><?= $stats['pomodoro'] ?></strong> fokus</span><span><strong><?= $stats['notes'] ?></strong> catatan</span><span><strong><?= $stats['q_open'] ?></strong> tanya terbuka</span></div>
            </section>
            <section class="card p-4" aria-label="Badge">
                <h2 class="h5 fw-bold mb-1">Badge (<?= count($badges_owned) ?>/<?= count($badge_list) ?>)</h2>
                <p class="text-secondary small mb-3">Otomatis terbuka saat target tercapai.</p>
                <div class="badge-grid">
                    <?php foreach ($badge_list as $slug => $b): $has = isset($badges_owned[$slug]); ?>
                    <div class="badge-item <?= $has ? 'unlocked' : 'locked' ?>" title="<?= htmlspecialchars($b['desc']) ?>">
                        <i class="fas <?= htmlspecialchars($b['icon']) ?>" aria-hidden="true"></i>
                        <strong><?= htmlspecialchars($b['name']) ?></strong>
                        <span><?= $has ? date('d M', strtotime($badges_owned[$slug]['unlocked_at'])) : htmlspecialchars($b['desc']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <section class="card p-4" aria-label="Tanya dan catatan">
                <h2 class="h5 fw-bold mb-1">Lanjutan</h2>
                <p class="text-secondary small mb-3">Questions sekarang menyatu dengan Notes agar tab bawah tetap 5.</p>
                <div class="page-actions">
                    <a href="errors.php" class="btn btn-cyber-outline">Buka Notes</a>
                    <a href="questions.php" class="btn btn-cyber-outline">Questions (<?= $stats['q_open'] ?> open)</a>
                    <a href="review.php" class="btn btn-cyber-outline">Review</a>
                    <a href="leaderboard.php" class="btn btn-cyber-outline">Leaderboard</a>
                    <a href="export.php" class="btn btn-cyber-outline">Export CV</a>
                </div>
            </section>
            <section class="card p-4" aria-label="Visibilitas">
                <h2 class="h5 fw-bold mb-1">Visibilitas</h2>
                <p class="text-secondary small mb-3">Default privat. Aktifkan hanya yang kamu mau.</p>
                <form method="POST" action="profile.php" class="d-flex flex-column gap-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="visibility">
                    <label class="d-flex align-items-center gap-2 small"><input type="checkbox" name="show_on_board" value="1" <?= !empty($user['show_on_board']) ? 'checked' : '' ?>> Tampil di leaderboard</label>
                    <label class="d-flex align-items-center gap-2 small"><input type="checkbox" name="public_profile" value="1" <?= !empty($user['public_profile']) ? 'checked' : '' ?>> Profil publik bisa dibagikan</label>
                    <button class="btn btn-cyber btn-sm mt-2" type="submit">Simpan visibilitas</button>
                </form>
                <?php if (!empty($user['public_profile'])): ?><p class="small mt-2 mb-0">Link publik: <a href="u.php?u=<?= urlencode($user['username']) ?>">u.php?u=<?= htmlspecialchars($user['username']) ?></a></p><?php endif; ?>
            </section>
        </div>
        <div class="col-lg-5 d-flex flex-column gap-4">
            <section class="card p-4" aria-label="Edit profil">
                <h2 class="h5 fw-bold mb-3">Edit profil</h2>
                <form method="POST" action="profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3"><label class="form-label" for="pf-user">Username</label><input id="pf-user" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required minlength="3" maxlength="100"></div>
                    <div class="mb-3"><label class="form-label" for="pf-email">Email</label><input id="pf-email" name="email" type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required></div>
                    <button class="btn btn-cyber w-100" type="submit">Simpan</button>
                </form>
            </section>
            <section class="card p-4" aria-label="Ganti password">
                <h2 class="h5 fw-bold mb-3">Ganti password</h2>
                <form method="POST" action="profile.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3"><label class="form-label" for="pf-old">Password lama</label><input id="pf-old" name="old_password" type="password" class="form-control" required autocomplete="current-password"></div>
                    <div class="mb-3"><label class="form-label" for="pf-new">Password baru (min 6)</label><input id="pf-new" name="new_password" type="password" class="form-control" required minlength="6" autocomplete="new-password"></div>
                    <button class="btn btn-cyber-outline w-100" type="submit">Ganti password</button>
                </form>
                <a href="logout.php" class="btn btn-cyber-danger w-100 mt-3">Keluar</a>
                <button type="button" class="btn btn-cyber-outline w-100 mt-2 pwa-install-btn" onclick="installPWA()" hidden>Install Aplikasi</button>
            </section>
            <section class="card p-4" aria-label="Zona berbahaya">
                <h2 class="h5 fw-bold mb-1 text-danger">Hapus akun</h2>
                <p class="text-secondary small mb-3">Menghapus permanen semua quest, catatan, dan XP. Unduh dulu via <a href="export.php?format=json">JSON</a> bila perlu.</p>
                <form method="POST" action="profile.php" onsubmit="return confirm('Hapus akun permanen? Tindakan ini tidak bisa dibatalkan.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_account">
                    <div class="mb-3"><label class="form-label" for="pf-del-pw">Password untuk konfirmasi</label><input id="pf-del-pw" name="confirm_password" type="password" class="form-control" required autocomplete="current-password"></div>
                    <button class="btn btn-cyber-danger w-100" type="submit">Hapus permanen</button>
                </form>
            </section>
        </div>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
