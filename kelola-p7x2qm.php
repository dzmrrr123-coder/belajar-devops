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
    if ($action === 'make_voucher') {
        $plan = \App\Domain\Pro::plan((string)($_POST['pro_plan'] ?? 'monthly')) ? (string)$_POST['pro_plan'] : 'monthly';
        $days = max(1, min(3650, (int)($_POST['pro_days'] ?? 30)));
        $maxUses = max(1, min(10000, (int)($_POST['max_uses'] ?? 1)));
        $expDays = max(0, min(3650, (int)($_POST['exp_days'] ?? 0)));
        $exp = $expDays > 0 ? date('Y-m-d H:i:s', strtotime("+$expDays days")) : null;
        $r = \App\Domain\ProVoucher::create($conn, $admin_id, $plan, $days, $maxUses, $exp);
        set_flash($r['ok'] ? 'success' : 'danger', $r['ok'] ? 'Voucher dibuat: ' . $r['code'] : $r['msg']);
        $back = 'kelola-p7x2qm.php?' . http_build_query(array_filter(['q' => $_GET['q'] ?? '', 'page' => $_GET['page'] ?? 1]));
        redirect($back);
    }
    if ($action === 'make_voucher_bulk') {
        $plan = \App\Domain\Pro::plan((string)($_POST['pro_plan'] ?? 'monthly')) ? (string)$_POST['pro_plan'] : 'monthly';
        $days = max(1, min(3650, (int)($_POST['pro_days'] ?? 30)));
        $maxUses = max(1, min(10000, (int)($_POST['max_uses'] ?? 1)));
        $expDays = max(0, min(3650, (int)($_POST['exp_days'] ?? 0)));
        $exp = $expDays > 0 ? date('Y-m-d H:i:s', strtotime("+$expDays days")) : null;
        $qty = max(1, min(100, (int)($_POST['qty'] ?? 10)));
        if (rate_limit_hit('voucher_bulk', 5, 3600)) { set_flash('warning', 'Bulk dibatasi 5x/jam.'); redirect('kelola-p7x2qm.php'); }
        $codes = \App\Domain\ProVoucher::createBulk($conn, $admin_id, $plan, $days, $maxUses, $exp, $qty);
        $_SESSION['bulk_codes'] = $codes;
        set_flash(count($codes) === $qty ? 'success' : 'warning', 'Bulk selesai: ' . count($codes) . '/' . $qty . ' kode.');
        redirect('kelola-p7x2qm.php');
    }
    if ($action === 'approve_waitlist') {
        $wid = (int)($_POST['wait_id'] ?? 0);
        $w = null;
        try { $g = $conn->prepare("SELECT id, plan, user_id FROM pro_waitlist WHERE id = ?"); if ($g) { $g->bind_param("i", $wid); $g->execute(); $w = $g->get_result()->fetch_assoc(); $g->close(); } } catch (Throwable $e) {}
        if (!$w) { set_flash('warning', 'Waitlist tidak ada.'); redirect('kelola-p7x2qm.php'); }
        $plan = \App\Domain\Pro::plan((string)$w['plan']) ? (string)$w['plan'] : 'monthly';
        $days = (int)(\App\Domain\Pro::plan($plan)['days'] ?? 30);
        $target = (int)($w['user_id'] ?? 0);
        if ($target > 0 && \App\Domain\ProVoucher::grantPro($conn, $target, $plan, $days)) {
            try { $d = $conn->prepare("DELETE FROM pro_waitlist WHERE id = ?"); if ($d) { $d->bind_param("i", $wid); $d->execute(); $d->close(); } } catch (Throwable $e) {}
            set_flash('success', "Waitlist #{$wid} disetujui: Pro {$plan} {$days} hari.");
        } else set_flash('warning', 'Butuh user_id valid untuk aktivasi otomatis.');
        redirect('kelola-p7x2qm.php');
    }
    if ($action === 'decide_payment') {
        $pid = (int)($_POST['pay_id'] ?? 0);
        $dec = ($_POST['decision'] ?? '') === 'paid' ? 'paid' : 'rejected';
        $p = null;
        try { $g = $conn->prepare("SELECT id, user_id, plan FROM pro_payments WHERE id = ? AND status = 'pending'"); if ($g) { $g->bind_param("i", $pid); $g->execute(); $p = $g->get_result()->fetch_assoc(); $g->close(); } } catch (Throwable $e) {}
        if (!$p) { set_flash('warning', 'Payment tidak pending.'); redirect('kelola-p7x2qm.php'); }
        try { $u = $conn->prepare("UPDATE pro_payments SET status = ?, decided_at = NOW() WHERE id = ?"); if ($u) { $u->bind_param("si", $dec, $pid); $u->execute(); $u->close(); } } catch (Throwable $e) {}
        if ($dec === 'paid') {
            $plan = \App\Domain\Pro::plan((string)$p['plan']) ? (string)$p['plan'] : 'monthly';
            \App\Domain\ProVoucher::grantPro($conn, (int)$p['user_id'], $plan, (int)(\App\Domain\Pro::plan($plan)['days'] ?? 30));
        }
        set_flash('success', "Payment #{$pid}: {$dec}.");
        redirect('kelola-p7x2qm.php');
    }
    if ($action === 'add_sponsor') {
        \App\Domain\Sponsor::ensureTables($conn);
        $nm = \App\Domain\Sponsor::clean($_POST['sponsor_name'] ?? '');
        $url = mb_substr(trim((string)($_POST['sponsor_url'] ?? '')), 0, 255);
        if ($nm === '') set_flash('warning', 'Nama sponsor wajib.');
        else { try { $s = $conn->prepare("INSERT INTO sponsors (name, url) VALUES (?, ?) ON DUPLICATE KEY UPDATE url = VALUES(url), active = 1"); if ($s) { $s->bind_param("ss", $nm, $url); $s->execute(); $s->close(); set_flash('success', 'Sponsor tersimpan.'); } } catch (Throwable $e) { set_flash('danger', 'Gagal simpan sponsor.'); } }
        redirect('kelola-p7x2qm.php');
    }
    $target = (int)($_POST['target_id'] ?? 0);
    if ($target <= 0) {
        set_flash('warning', 'User tidak valid.');
    } elseif ($target === $admin_id) {
        set_flash('warning', 'Tidak bisa mengubah akun sendiri dari panel ini.');
    } else {
        if ($action === 'set_role') {
            $role = (string)($_POST['role'] ?? 'user');
            if (!in_array($role, ['admin', 'guru', 'user'], true)) $role = 'user';
            if ($role === 'admin') {
                if (\App\Domain\Auth\Roles::grant($conn, $target, 'admin')) {
                    set_flash('success', "User #{$target} dijadikan admin.");
                } else {
                    set_flash('danger', 'Gagal memberi role admin.');
                }
            } elseif ($role === 'guru') {
                if (\App\Domain\Auth\Roles::isAdmin($conn, $target) && \App\Domain\Auth\Roles::countAdmins($conn) <= 1) {
                    set_flash('danger', 'Admin terakhir tidak bisa dijadikan guru.');
                } else {
                    \App\Domain\Auth\Roles::revoke($conn, $target, 'admin');
                    if (\App\Domain\Auth\Roles::grant($conn, $target, 'guru')) {
                        set_flash('success', "User #{$target} dijadikan guru.");
                    } else {
                        set_flash('danger', 'Gagal memberi role guru.');
                    }
                }
            } else {
                \App\Domain\Auth\Roles::revoke($conn, $target, 'guru');
                if (\App\Domain\Auth\Roles::revoke($conn, $target, 'admin')) {
                    set_flash('success', "Role user #{$target} dikembalikan ke user.");
                } else {
                    set_flash('danger', 'Gagal mencabut role (minimal 1 admin harus tersisa).');
                }
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
        } elseif ($action === 'grant_pro') {
            $days = max(1, min(3650, (int)($_POST['pro_days'] ?? 30)));
            $plan = \App\Domain\Pro::plan((string)($_POST['pro_plan'] ?? 'monthly')) ? (string)$_POST['pro_plan'] : 'monthly';
            if (\App\Domain\ProVoucher::grantPro($conn, $target, $plan, $days)) {
                set_flash('success', "Pro {$plan} {$days} hari diberikan ke user #{$target}.");
            } else {
                set_flash('danger', 'Gagal memberi Pro.');
            }
        } elseif ($action === 'revoke_pro') {
            if (\App\Domain\ProVoucher::revokePro($conn, $target)) {
                set_flash('info', "Pro user #{$target} dicabut.");
            } else {
                set_flash('danger', 'Gagal mencabut Pro.');
            }
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
        $s = $conn->prepare("SELECT u.id, u.username, u.email, u.role, u.xp, u.streak, u.best_streak, u.created_at, u.is_pro, u.pro_until, (SELECT COUNT(*) FROM user_quests WHERE user_id = u.id) qd FROM users u WHERE u.username LIKE ? OR u.email LIKE ? ORDER BY u.id DESC LIMIT ? OFFSET ?");
        $s->bind_param("ssii", $like, $like, $per, $off);
    } else {
        $s = $conn->prepare("SELECT u.id, u.username, u.email, u.role, u.xp, u.streak, u.best_streak, u.created_at, u.is_pro, u.pro_until, (SELECT COUNT(*) FROM user_quests WHERE user_id = u.id) qd FROM users u ORDER BY u.id DESC LIMIT ? OFFSET ?");
        $s->bind_param("ii", $per, $off);
    }
    $s->execute();
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
} catch (Throwable $e) {}
$admin_ids = [];
try {
    $ar = $conn->query("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'admin'");
    if ($ar) { foreach ($ar->fetch_all(MYSQLI_ASSOC) as $arow) $admin_ids[(int)$arow['user_id']] = true; $ar->free(); }
} catch (Throwable $e) {}
$is_row_admin = function (array $row) use ($admin_ids): bool {
    if (isset($admin_ids[(int)($row['id'] ?? 0)])) return true;
    return ($row['role'] ?? '') === 'admin';
};
$guru_ids = [];
try {
    $gr = $conn->query("SELECT ur.user_id FROM user_roles ur JOIN roles r ON r.id = ur.role_id WHERE r.slug = 'guru'");
    if ($gr) { foreach ($gr->fetch_all(MYSQLI_ASSOC) as $grow) $guru_ids[(int)$grow['user_id']] = true; $gr->free(); }
} catch (Throwable $e) {}
$is_row_guru = function (array $row) use ($guru_ids): bool {
    if (isset($guru_ids[(int)($row['id'] ?? 0)])) return true;
    return ($row['role'] ?? '') === 'guru';
};

$stat_all = ['users' => 0, 'admins' => 0, 'xp' => 0];
try {
    $r = $conn->query("SELECT COUNT(*) u, COALESCE(SUM(xp), 0) x FROM users");
    if ($r) { $d = $r->fetch_assoc(); $stat_all['users'] = (int)($d['u'] ?? 0); $stat_all['xp'] = (int)($d['x'] ?? 0); $r->free(); }
    $stat_all['admins'] = \App\Domain\Auth\Roles::countAdmins($conn);
} catch (Throwable $e) {}
$vouchers = \App\Domain\ProVoucher::list($conn, 30);
$waitlist = [];
try { $r = $conn->query("SELECT id, contact, plan, note, user_id, created_at FROM pro_waitlist ORDER BY id DESC LIMIT 20"); if ($r) { $waitlist = $r->fetch_all(MYSQLI_ASSOC); $r->free(); } } catch (Throwable $e) {}
$payments = [];
try { $r = $conn->query("SELECT p.id, p.user_id, u.username, p.plan, p.amount, p.status, p.created_at FROM pro_payments p LEFT JOIN users u ON u.id = p.user_id WHERE p.status = 'pending' ORDER BY p.id DESC LIMIT 20"); if ($r) { $payments = $r->fetch_all(MYSQLI_ASSOC); $r->free(); } } catch (Throwable $e) {}
\App\Domain\Sponsor::ensureTables($conn);
$sponsors = [];
try { $r = $conn->query("SELECT name, url FROM sponsors WHERE active = 1 ORDER BY name ASC"); if ($r) { $sponsors = $r->fetch_all(MYSQLI_ASSOC); $r->free(); } } catch (Throwable $e) {}
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
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" action="kelola-p7x2qm.php" class="d-flex gap-2" role="search" style="max-width:420px">
                <input name="q" class="form-control" placeholder="Cari username / email…" value="<?= htmlspecialchars($q) ?>" maxlength="100" aria-label="Cari user">
                <button class="btn btn-cyber-outline flex-shrink-0" type="submit"><i class="fas fa-search" aria-hidden="true"></i></button>
            </form>
            <form method="POST" action="backup.php" class="m-0">
                <?= csrf_field() ?>
                <button class="btn btn-cyber btn-sm" type="submit"><i class="fas fa-database me-1" aria-hidden="true"></i>Backup DB (.sql)</button>
            </form>
        </div>
    </div>

    <div class="card p-4 mb-3" aria-label="Voucher Pro">
        <h2 class="h5 fw-bold mb-1">Voucher Pro (<?= count($vouchers) ?>)</h2>
        <p class="text-secondary small mb-3">Bagikan kode ke siswa / tim. Satu kode satu akun per user.</p>
        <form method="POST" action="kelola-p7x2qm.php" class="row g-2 m-0 mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="admin_action" value="make_voucher">
            <div class="col-md-2"><select name="pro_plan" class="form-select form-select-sm"><option value="monthly">Bulanan</option><option value="yearly">Tahunan</option><option value="team">Tim</option></select></div>
            <div class="col-md-2"><input name="pro_days" type="number" class="form-control form-control-sm" value="30" min="1" max="3650" aria-label="Hari"></div>
            <div class="col-md-2"><input name="max_uses" type="number" class="form-control form-control-sm" value="1" min="1" max="10000" aria-label="Kuota pakai"></div>
            <div class="col-md-3"><input name="exp_days" type="number" class="form-control form-control-sm" value="0" min="0" max="3650" placeholder="Kedaluwarsa (hari, 0 = tanpa batas)" aria-label="Kedaluwarsa"></div>
            <div class="col-md-3"><button class="btn btn-cyber btn-sm w-100" type="submit">Buat voucher</button></div>
        </form>
        <form method="POST" action="kelola-p7x2qm.php" class="row g-2 m-0 mb-3" aria-label="Bulk voucher kelas">
            <?= csrf_field() ?>
            <input type="hidden" name="admin_action" value="make_voucher_bulk">
            <input type="hidden" name="pro_plan" value="team">
            <input type="hidden" name="pro_days" value="365">
            <input type="hidden" name="max_uses" value="1">
            <input type="hidden" name="exp_days" value="90">
            <div class="col-md-3"><input name="qty" type="number" class="form-control form-control-sm" value="20" min="1" max="100" aria-label="Jumlah kode"></div>
            <div class="col-md-9"><button class="btn btn-cyber-outline btn-sm w-100" type="submit">Bulk lisensi kelas (Tim 1 thn, 1 pakai, 90 hari)</button></div>
        </form>
        <?php $bulk = $_SESSION['bulk_codes'] ?? []; unset($_SESSION['bulk_codes']); if ($bulk): ?>
        <div class="alert alert-success small" role="status">Batch baru (<?= count($bulk) ?>): <?= htmlspecialchars(implode(', ', $bulk)) ?></div>
        <?php endif; ?>
        <?php foreach ($vouchers as $v): ?>
        <div class="list-row"><div class="list-main"><p class="list-title"><?= htmlspecialchars($v['code']) ?></p><p class="list-meta"><?= htmlspecialchars($v['plan']) ?> · <?= (int)$v['days'] ?> hari · dipakai <?= (int)$v['used_count'] ?>/<?= (int)$v['max_uses'] ?><?= !empty($v['expires_at']) ? ' · exp ' . htmlspecialchars($v['expires_at']) : ' · tanpa batas' ?> · oleh <?= htmlspecialchars($v['by_name'] ?? '-') ?></p></div></div>
        <?php endforeach; ?>
        <?php if (!$vouchers): ?><p class="small text-muted mb-0">Belum ada voucher.</p><?php endif; ?>
    </div>

    <div class="card p-4 mb-3"><h2 class="h5">Waitlist sekolah (<?= count($waitlist) ?>)</h2>
    <?php foreach ($waitlist as $w): ?><div class="list-row"><div class="list-main"><p class="list-title">#<?= (int)$w['id'] ?> <?= htmlspecialchars($w['contact']) ?> · <?= htmlspecialchars($w['plan']) ?></p><p class="list-meta"><?= htmlspecialchars($w['note'] ?? '') ?> · user <?= (int)($w['user_id'] ?? 0) ?> · <?= htmlspecialchars($w['created_at']) ?></p></div>
    <form method="POST" class="m-0"><?= csrf_field() ?><input type="hidden" name="admin_action" value="approve_waitlist"><input type="hidden" name="wait_id" value="<?= (int)$w['id'] ?>"><button class="btn btn-cyber btn-sm" type="submit">Setujui + aktifkan</button></form></div><?php endforeach; ?>
    <?php if (!$waitlist): ?><p class="small text-muted mb-0">Kosong.</p><?php endif; ?></div>

    <div class="card p-4 mb-3"><h2 class="h5">Payment pending (<?= count($payments) ?>)</h2>
    <?php foreach ($payments as $p): ?><div class="list-row"><div class="list-main"><p class="list-title">#<?= (int)$p['id'] ?> <?= htmlspecialchars($p['username'] ?? '') ?> · <?= htmlspecialchars($p['plan']) ?> · Rp<?= (int)$p['amount'] ?></p></div>
    <form method="POST" class="d-flex gap-1 m-0"><?= csrf_field() ?><input type="hidden" name="admin_action" value="decide_payment"><input type="hidden" name="pay_id" value="<?= (int)$p['id'] ?>"><button name="decision" value="paid" class="btn btn-cyber btn-sm" type="submit">Paid</button><button name="decision" value="rejected" class="btn btn-cyber-outline btn-sm" type="submit">Tolak</button></form></div><?php endforeach; ?>
    <?php if (!$payments): ?><p class="small text-muted mb-0">Kosong.</p><?php endif; ?></div>

    <div class="card p-4 mb-3"><h2 class="h5">Sponsor (<?= count($sponsors) ?>)</h2>
    <form method="POST" class="row g-2 m-0 mb-2"><?= csrf_field() ?><input type="hidden" name="admin_action" value="add_sponsor"><div class="col-md-5"><input name="sponsor_name" class="form-control form-control-sm" maxlength="80" placeholder="Nama sponsor" required></div><div class="col-md-5"><input name="sponsor_url" class="form-control form-control-sm" maxlength="255" placeholder="https://… (opsional)"></div><div class="col-md-2"><button class="btn btn-cyber btn-sm w-100" type="submit">Simpan</button></div></form>
    <?php foreach ($sponsors as $s): ?><p class="small mb-1"><?= htmlspecialchars($s['name']) ?><?= !empty($s['url']) ? ' · ' . htmlspecialchars($s['url']) : '' ?></p><?php endforeach; ?></div>

    <div class="card p-2">
        <?php if (!$rows): ?>
            <p class="text-secondary small p-3 mb-0">Tidak ada user yang cocok.</p>
        <?php endif; ?>
        <?php foreach ($rows as $r): $is_self = ((int)$r['id'] === $admin_id); ?>
        <div class="list-row align-items-start">
            <div class="list-main">
                <p class="list-title">#<?= (int)$r['id'] ?> <?= htmlspecialchars($r['username']) ?>
                    <?php if ($is_row_admin($r)): ?><span class="quest-pending">Admin</span><?php endif; ?>
                    <?php if ($is_row_guru($r)): ?><span class="quest-pending">Guru</span><?php endif; ?>
                    <?php if ($is_self): ?><span class="quest-pending">Kamu</span><?php endif; ?>
                </p>
                <p class="list-meta"><?= htmlspecialchars($r['email']) ?> · Lv <?= calculate_level((int)$r['xp']) ?> · <?= (int)$r['xp'] ?> XP · streak <?= (int)$r['streak'] ?> (terbaik <?= (int)$r['best_streak'] ?>) · <?= (int)$r['qd'] ?> quest · gabung <?= date('d M Y', strtotime($r['created_at'])) ?><?= !empty($r['is_pro']) ? ' · Pro s/d ' . htmlspecialchars($r['pro_until'] ?? '') : '' ?></p>
                <?php if (!$is_self): ?>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <form method="POST" action="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page])) ?>" class="d-flex gap-1 m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="grant_pro">
                        <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                        <select name="pro_plan" class="form-select form-select-sm" style="max-width:110px" aria-label="Paket Pro"><option value="monthly">Bulanan</option><option value="yearly">Tahunan</option><option value="team">Tim</option></select>
                        <input name="pro_days" type="number" class="form-control form-control-sm" style="max-width:70px" value="30" min="1" max="3650" aria-label="Hari Pro">
                        <button class="btn btn-cyber btn-sm" type="submit">Pro</button>
                    </form>
                    <form method="POST" action="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page])) ?>" class="m-0" onsubmit="return confirm('Cabut Pro user ini?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="revoke_pro">
                        <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-cyber-outline btn-sm" type="submit">Cabut Pro</button>
                    </form>
                    <form method="POST" action="kelola-p7x2qm.php?<?= http_build_query(array_filter(['q' => $q, 'page' => $page])) ?>" class="d-flex gap-1 m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="set_role">
                        <input type="hidden" name="target_id" value="<?= (int)$r['id'] ?>">
                        <select name="role" class="form-select form-select-sm" style="max-width:110px" aria-label="Role user">
                            <option value="user" <?= !$is_row_admin($r) && !$is_row_guru($r) ? 'selected' : '' ?>>User</option>
                            <option value="guru" <?= $is_row_guru($r) ? 'selected' : '' ?>>Guru</option>
                            <option value="admin" <?= $is_row_admin($r) ? 'selected' : '' ?>>Admin</option>
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
