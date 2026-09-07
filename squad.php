<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
use App\Domain\Social\Squads;
use App\Domain\Gamification\Badges;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['squad_action'] ?? '';
    if (rate_limit_hit('squad_flip', 10, 60)) {
        set_flash('warning', 'Terlalu cepat. Tunggu sebentar.');
        redirect('squad.php');
    }
    if ($action === 'create') {
        $r = Squads::create($conn, $user_id, (string)($_POST['name'] ?? ''));
        if ($r['ok']) {
            Badges::check($conn, $user_id);
            set_flash('success', 'Squad dibuat! Kode undangan: ' . $r['code']);
        } else {
            set_flash('warning', $r['msg']);
        }
    } elseif ($action === 'join') {
        $r = Squads::joinByCode($conn, $user_id, (string)($_POST['code'] ?? ''));
        if ($r['ok']) {
            Badges::check($conn, $user_id);
            set_flash('success', 'Gabung squad! Gas bareng.');
        } else {
            set_flash('warning', $r['msg']);
        }
    } elseif ($action === 'leave') {
        if (Squads::leave($conn, $user_id)) set_flash('info', 'Keluar dari squad.');
        else set_flash('warning', 'Gagal keluar squad.');
    }
    redirect('squad.php');
}

$my_squad = null;
$sid = Squads::mySquadId($conn, $user_id);
if ($sid !== null) $my_squad = Squads::detail($conn, $sid);
$conn->close();
$page_title = 'Squad';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Maks 5 orang · XP mingguan gabungan</div>
        <h1 class="page-title">Squad</h1>
        <p class="page-desc">Belajar bareng lebih nempel. Ajak teman, kejar XP bareng.</p>
    </div>
    <?php if ($my_squad): ?>
    <section class="card p-4 mb-3" aria-label="Squad saya">
        <div class="ana-head">
            <div>
                <h2><?= htmlspecialchars($my_squad['name']) ?></h2>
                <p>Kode: <strong><?= htmlspecialchars($my_squad['code']) ?></strong> · oleh <?= htmlspecialchars($my_squad['creator']) ?> · <strong>+<?= (int)$my_squad['total_wxp'] ?> XP</strong> minggu ini</p>
            </div>
        </div>
        <div class="race-list">
            <?php $sp = 0; foreach ($my_squad['members'] as $m): $sp++; $isme = ((int)$m['id'] === $user_id); ?>
            <div class="race-row<?= $isme ? ' me' : '' ?>">
                <span class="race-rank">#<?= $sp ?></span>
                <span class="avatar-circle avatar-sm frame-default" aria-hidden="true"><?= strtoupper(substr($m['username'], 0, 1)) ?></span>
                <span class="race-name"><?= htmlspecialchars($m['username']) ?><?= $isme ? ' (kamu)' : '' ?></span>
                <span class="ana-val">+<?= (int)$m['wxp'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <form method="POST" action="squad.php" class="m-0 mt-2" onsubmit="return confirm('Keluar dari squad ini?')">
            <?= csrf_field() ?>
            <input type="hidden" name="squad_action" value="leave">
            <button type="submit" class="btn btn-cyber-outline btn-sm">Keluar squad</button>
        </form>
    </section>
    <?php else: ?>
    <section class="card p-4 mb-3" aria-label="Buat squad">
        <h2 class="h5 fw-bold mb-1">Buat squad baru</h2>
        <p class="text-secondary small mb-3">Dapat kode undangan 6 karakter untuk dibagikan.</p>
        <form method="POST" action="squad.php" class="d-flex gap-2 m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="squad_action" value="create">
            <input name="name" class="form-control" placeholder="Nama squad…" maxlength="40" required aria-label="Nama squad">
            <button class="btn btn-cyber btn-sm flex-shrink-0" type="submit">Buat</button>
        </form>
    </section>
    <section class="card p-4 mb-3" aria-label="Gabung squad">
        <h2 class="h5 fw-bold mb-1">Gabung pakai kode</h2>
        <p class="text-secondary small mb-3">Minta kode ke teman satu squad.</p>
        <form method="POST" action="squad.php" class="d-flex gap-2 m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="squad_action" value="join">
            <input name="code" class="form-control" placeholder="Kode 6 karakter…" maxlength="8" required aria-label="Kode squad" style="text-transform:uppercase">
            <button class="btn btn-cyber-outline btn-sm flex-shrink-0" type="submit">Gabung</button>
        </form>
    </section>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>
