<?php
// Panel admin privat: URL ini tidak ditautkan di mana pun (kecuali untuk admin).
// Akses: login + role=admin, selain itu 404.
require_once 'config.php';
require_login();
$conn = db_connect();
require_admin($conn);
$admin_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['admin_action'] ?? '';
    $target = (int)($_POST['target_id'] ?? 0);
    if ($target <= 0) {
        set_flash('warning', 'User tidak valid.');
    } elseif ($target === $admin_id) {
        set_flash('warning', 'Tidak bisa mengubah akun sendiri dari panel ini.');
    } else {
        if ($action === 'set_role') {
            $role = ($_POST['role'] ?? '') === 'admin' ? 'admin' : 'user';
            $tq = $conn->prepare("SELECT email FROM users WHERE id = ?");
            $tq->bind_param("i", $target);
            $tq->execute();
            $temail = strtolower(trim((string)($tq->get_result()->fetch_assoc()['email'] ?? '')));
            $tq->close();
            if ($role === 'admin' && $temail !== OWNER_ADMIN_EMAIL) {
                set_flash('danger', 'Role admin dikunci hanya untuk ' . OWNER_ADMIN_EMAIL . '.');
            } else {
                $up = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
                $up->bind_param("si", $role, $target);
                $up->execute(); $up->close();
                set_flash('success', "Role user #{$target} diubah menjadi {$role}.");
            }
        } elseif ($action === 'adjust_xp') {
            $delta = max(-10000, min(10000, (int)($_POST['xp_delta'] ?? 0)));
            if ($delta !== 0) {
                award_xp($conn, $target, $delta, 'admin_adjust');
                set_flash('success', "XP user #{$target} diubah " . ($delta > 0 ? '+' : '') . "{$delta} (tercatat di ledger).");
            } else {
                set_flash('warning', 'Nominal XP tidak boleh nol.');
            }
        } elseif ($action === 'reset_streak') {
            $up = $conn->prepare("UPDATE users SET streak = 0 WHERE id = ?");
            $up->bind_param("i", $target);
            $up->execute(); $up->close();
            set_flash('success', "Streak user #{$target} direset ke 0.");
        } elseif ($action === 'delete_user') {
            clear_remember_token($conn);
            $del = $conn->prepare("DELETE FROM users WHERE id = ?");
            $del->bind_param("i", $target);
            $del->execute(); $del->close();
            set_flash('info', "User #{$target} dihapus permanen.");
        }
    }
    $back = 'kelola-p7x2qm.php?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'page' => $_GET['page'] ?? 1]));
    redirect($back);
}

$q = mb_substr(trim((string)($_GET['q'] ?? '')), 0, 100);
$page = max(1, (int)($_GET['page'] ?? 1));
$per = 20;
$off = ($page - 1) * $per;
$like = '%' . $q . '%';

try {
    if ($q !== '') {
        $c = $conn->prepare("SELECT COUNT(*) n FROM users WHERE username LIKE ? OR email LIKE ?");
        $c->bind_param("ss", $like, $like);
    } else {
        $c = $conn->prepare("SELECT COUNT(*) n FROM users");
    }
    $c->execute();
    $total = (int)($c->get_result()->fetch_assoc()['n'] ?? 0);
    $c->close();
} catch (Throwable $e) { $total = 0; }

$rows = [];
try {
    if ($q !== '') {
        $s = $conn->prepare("SELECT u.id, u.username, u.email, u.role, u.xp, u.streak, u.best_streak, u.created_at, (SELECT COUNT(*) FROM user_quests WHERE user_id = u.id) qd FROM users u WHERE u.username LIKE ? OR u.email LIKE ? ORDER BY u.id DESC LIMIT ? OFFSET ?");
        $s->bind_param("ssii", $like, $like, $per, $off);
    } else {
        $s = $conn->prepare("SELECT u.id, u.username, u.email, u.role, u.xp, u.streak, u.best_streak, u.created_at, (SELECT COUNT(*) FROM user_quests WHERE user_id = u.id) qd FROM users u ORDER BY u.id DESC LIMIT ? OFFSET ?");
        $s->bind_param("ii", $per, $off);
    }
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
} catch (Throwable $e) {}

$stat_all = ['users' => 0, 'admins' => 0, 'xp' => 0];
try {
    $r = $conn->query("SELECT COUNT(*) u, SUM(role = 'admin') a, COALESCE(SUM(xp), 0) x FROM users");
    if ($r) { $d = $r->fetch_assoc(); $stat_all = ['users' => (int)($d['u'] ?? 0), 'admins' => (int)($d['a'] ?? 0), 'xp' => (int)($d['x'] ?? 0)]; $r->free(); }
} catch (Throwable $e) {}
$conn->close();
$pages = max(1, (int)ceil($total / $per));
$page_title = 'Kelola User';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Area privat · admin saja</div>
        <h1 class="page-title">Kelola user</h1>
        <p class="page-desc"><?= $stat_all['users'] ?> user · <?= $stat_all['admins'] ?> admin · <?= number_format($stat_all['xp']) ?> total XP</p>
        <form method="GET" action="kelola-p7x2qm.php" class="d-flex gap-2" role="search" style="max-width:420px">
            <input name="q" class="form-control" placeholder="Cari username / email…" value="<?= htmlspecialchars($q) ?>" maxlength="100" aria-label="Cari user">
            <button class="btn btn-cyber-outline flex-shrink-0" type="submit"><i class="fas fa-search" aria-hidden="true"></i></button>
        </form>
    </div>

    <div class="card p-2">
        <?php if (!$rows): ?>
            <p class="text-secondary small p-3 mb-0">Tidak ada user yang cocok.</p>
        <?php endif; ?>
        <?php foreach ($rows as $r): $is_self = ((int)$r['id'] === $admin_id); ?>
        <div class="list-row align-items-start">
            <div class="list-main">
                <p class="list-title">#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['username']) ?>
                    <?php if (($r['role'] ?? '') === 'admin'): ?><span class="quest-pending">Admin</span><?php endif; ?>
                    <?php if ($is_self): ?><span class="quest-pending">Kamu</span><?php endif; ?>
                </p>
                <p class="list-meta"><?= htmlspecialchars($r['email']) ?> · Lv <?= calculate_level((int)$r['xp']) ?> · <?= (int)$r['xp'] ?> XP · streak <?= (int)$r['streak'] ?> (terbaik <?= (int)$r['best_streak'] ?>) · <?= (int)$r['qd'] ?> quest · gabung <?= date('d M Y', strtotime($r['created_at'])) ?></p>
                <?php if (!$is_self): ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <form method="POST" action="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page])) ?>" class="d-flex gap-1 m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="set_role">
                        <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                        <select name="role" class="form-select form-select-sm" style="max-width:110px" aria-label="Role user">
                            <option value="user" <?= ($r['role'] ?? '') !== 'admin' ? 'selected' : '' ?>>User</option>
                            <option value="admin" <?= ($r['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                        <button class="btn btn-cyber-outline btn-sm" type="submit">Role</button>
                    </form>
                    <form method="POST" action="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page])) ?>" class="d-flex gap-1 m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="adjust_xp">
                        <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                        <input name="xp_delta" type="number" class="form-control form-control-sm" style="max-width:90px" placeholder="+/- XP" min="-10000" max="10000" required aria-label="Delta XP">
                        <button class="btn btn-cyber-outline btn-sm" type="submit">XP</button>
                    </form>
                    <form method="POST" action="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page])) ?>" class="m-0" onsubmit="return confirm('Reset streak user ini ke 0?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="reset_streak">
                        <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-cyber-outline btn-sm" type="submit">Reset streak</button>
                    </form>
                    <form method="POST" action="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page])) ?>" class="m-0" onsubmit="return confirm('Hapus user <?= htmlspecialchars($r['username']) ?> permanen?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="delete_user">
                        <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-cyber-danger btn-sm" type="submit">Hapus</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <div class="d-flex gap-2 mt-3 align-items-center">
        <?php if ($page > 1): ?><a class="btn btn-cyber-outline btn-sm" href="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page - 1])) ?>">‹ Sebelumnya</a><?php endif; ?>
        <span class="small text-muted">Hal <?= $page ?>/<?= $pages ?></span>
        <?php if ($page < $pages): ?><a class="btn btn-cyber-outline btn-sm" href="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page + 1])) ?>">Berikutnya ›</a><?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
