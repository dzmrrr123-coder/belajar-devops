<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
use App\Domain\Social\Duels;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['duel_action'] ?? '';
    $did = (int)($_POST['duel_id'] ?? 0);
    if (rate_limit_hit('duel_flip', 10, 60)) {
        set_flash('warning', 'Terlalu cepat. Tunggu sebentar.');
        redirect('duels.php');
    }
    if ($action === 'challenge') {
        $r = Duels::challenge($conn, $user_id, (string)($_POST['username'] ?? ''));
        set_flash($r['ok'] ? 'success' : 'warning', $r['msg']);
    } elseif ($action === 'accept' && $did > 0) {
        $ok = Duels::accept($conn, $user_id, $did);
        set_flash($ok ? 'success' : 'warning', $ok ? 'Duel diterima! Kumpulkan XP minggu ini.' : 'Gagal menerima duel.');
    } elseif ($action === 'decline' && $did > 0) {
        Duels::decline($conn, $user_id, $did);
        set_flash('info', 'Tantangan ditolak.');
    } elseif ($action === 'finish' && $did > 0) {
        $r = Duels::finish($conn, $user_id, $did);
        set_flash($r['ok'] ? 'success' : 'warning', $r['msg']);
    }
    redirect('duels.php');
}

$duels = Duels::myDuels($conn, $user_id);
$week_key = challenge_week_key();
$scores = [];
foreach ($duels as $d) {
    if (($d['status'] ?? '') === 'finished') continue;
    $yw = Duels::yearWeek((string)$d['week_key']);
    $scores[(int)$d['id']] = [
        'c' => Duels::weekXp($conn, (int)$d['challenger_id'], $yw),
        'o' => Duels::weekXp($conn, (int)$d['opponent_id'], $yw),
        'can_finish' => Duels::finishable($d),
    ];
}
$conn->close();
$page_title = 'Duel 1v1';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Minggu <?= htmlspecialchars($week_key) ?> · pemenang +15 XP</div>
        <h1 class="page-title">Duel 1v1</h1>
        <p class="page-desc">Tantang teman, adu XP seminggu. Selesaikan setelah minggunya berakhir.</p>
    </div>
    <section class="card p-4 mb-3" aria-label="Tantang duel">
        <h2 class="h5 fw-bold mb-1">Tantang teman</h2>
        <p class="text-secondary small mb-3">Satu duel aktif per lawan per minggu.</p>
        <form method="POST" action="duels.php" class="d-flex gap-2 m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="duel_action" value="challenge">
            <input name="username" class="form-control" placeholder="Username lawan…" maxlength="100" required aria-label="Username lawan">
            <button class="btn btn-cyber btn-sm flex-shrink-0" type="submit">Tantang</button>
        </form>
    </section>
    <div class="card p-2">
        <?php if (!$duels): ?><p class="text-secondary small p-3 mb-0">Belum ada duel. Tantang seseorang!</p><?php endif; ?>
        <?php foreach ($duels as $d): $did = (int)$d['id']; $is_ch = ((int)$d['challenger_id'] === $user_id); $foe = $is_ch ? $d['oname'] : $d['cname']; $sc = $scores[$did] ?? null; ?>
        <div class="list-row align-items-start">
            <div class="list-main">
                <p class="list-title">vs <?= htmlspecialchars($foe) ?>
                    <?php if (($d['status'] ?? '') === 'pending'): ?><span class="quest-pending">Menunggu</span>
                    <?php elseif (($d['status'] ?? '') === 'active'): ?><span class="quest-pending">Berjalan</span>
                    <?php else: ?><span class="quest-done"><i class="fas fa-check" aria-hidden="true"></i>Selesai<?= $d['winner_id'] ? (((int)$d['winner_id'] === $user_id) ? ' · Kamu menang!' : ' · Kamu kalah') : ' · Seri' ?></span><?php endif; ?>
                </p>
                <p class="list-meta"><?= htmlspecialchars($d['week_key']) ?> · <?= $is_ch ? 'kamu menantang' : 'kamu ditantang' ?><?php if ($sc): ?> · <?= (int)$sc['c'] ?> vs <?= (int)$sc['o'] ?> XP<?php endif; ?></p>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php if (($d['status'] ?? '') === 'pending' && !$is_ch): ?>
                    <form method="POST" action="duels.php" class="m-0"><<?= csrf_field() ?><input type="hidden" name="duel_action" value="accept"><input type="hidden" name="duel_id" value="<?= $did ?>"><button class="btn btn-cyber btn-sm" type="submit">Terima</button></form>
                    <form method="POST" action="duels.php" class="m-0"><<?= csrf_field() ?><input type="hidden" name="duel_action" value="decline"><input type="hidden" name="duel_id" value="<?= $did ?>"><button class="btn btn-cyber-outline btn-sm" type="submit">Tolak</button></form>
                    <?php elseif (($d['status'] ?? '') === 'active' && $sc && !empty($sc['can_finish'])): ?>
                    <form method="POST" action="duels.php" class="m-0"><<?= csrf_field() ?><input type="hidden" name="duel_action" value="finish"><input type="hidden" name="duel_id" value="<?= $did ?>"><button class="btn btn-cyber btn-sm" type="submit">Selesaikan duel</button></form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
