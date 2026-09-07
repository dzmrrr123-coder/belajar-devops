<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];
use App\Domain\Gamification\Season;

$key = Season::key();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['season_action'] ?? '';
    if (rate_limit_hit('season_flip', 10, 60)) {
        set_flash('warning', 'Terlalu cepat. Tunggu sebentar.');
        redirect('season.php');
    }
    if ($action === 'buy') {
        $r = Season::buy($conn, $user_id, $key);
        set_flash($r['ok'] ? 'success' : 'warning', $r['msg']);
    } elseif ($action === 'claim') {
        $tier = (int)($_POST['tier'] ?? 0);
        $track = ($_POST['track'] ?? '') === 'premium' ? 'premium' : 'free';
        $r = Season::claim($conn, $user_id, $key, $tier, $track);
        set_flash($r['ok'] ? 'success' : 'warning', $r['msg']);
    }
    redirect('season.php');
}

$sxp = Season::seasonXp($conn, $user_id, $key);
$premium = Season::hasPremium($conn, $user_id, $key);
$claimed = Season::claimed($conn, $user_id, $key);
$tiers = Season::tiers();
$conn->close();
$page_title = 'Season Pass';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Season <?= htmlspecialchars(Season::label($key)) ?> · reset tiap bulan</div>
        <h1 class="page-title">Season Pass</h1>
        <p class="page-desc"><strong>+<?= $sxp ?> XP</strong> terkumpul musim ini · hadiah tak diklaim hangus akhir bulan.</p>
        <?php if (!$premium): ?>
        <form method="POST" action="season.php" class="m-0 mt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="season_action" value="buy">
            <button type="submit" class="btn btn-cyber btn-sm"><i class="fas fa-crown me-1" aria-hidden="true"></i>Buka track emas (<?= Season::PREMIUM_PRICE ?> XP)</button>
        </form>
        <?php else: ?>
        <p class="mt-2 mb-0"><span class="quest-done"><i class="fas fa-crown" aria-hidden="true"></i>Track emas aktif</span></p>
        <?php endif; ?>
    </div>
    <div class="card p-2">
        <?php foreach ($tiers as $t => $def): $reached = $sxp >= $def['xp']; $cf = isset($claimed[$t . ':free']); $cp = isset($claimed[$t . ':premium']); ?>
        <div class="list-row align-items-start">
            <div class="list-main">
                <p class="list-title">Tier <?= $t ?> <small class="text-muted">· <?= $def['xp'] ?> XP</small>
                    <?php if ($cf && ($cp || !$premium)): ?><span class="quest-done"><i class="fas fa-check" aria-hidden="true"></i></span><?php endif; ?>
                </p>
                <div class="d-flex flex-wrap gap-2 mt-2">
                    <?php if ($cf): ?>
                    <span class="small text-muted">Gratis: +<?= $def['free'] ?> XP diklaim</span>
                    <?php elseif ($reached): ?>
                    <form method="POST" action="season.php" class="m-0"><?= csrf_field() ?><input type="hidden" name="season_action" value="claim"><input type="hidden" name="tier" value="<?= $t ?>"><input type="hidden" name="track" value="free"><button class="btn btn-cyber btn-sm" type="submit">Klaim +<?= $def['free'] ?> XP</button></form>
                    <?php else: ?>
                    <span class="small text-muted">Gratis: +<?= $def['free'] ?> XP (<?= max(0, $def['xp'] - $sxp) ?> lagi)</span>
                    <?php endif; ?>
                    <?php if ($cp): ?>
                    <span class="small text-muted">Emas: diklaim</span>
                    <?php elseif ($premium && $reached): ?>
                    <form method="POST" action="season.php" class="m-0"><?= csrf_field() ?><input type="hidden" name="season_action" value="claim"><input type="hidden" name="tier" value="<?= $t ?>"><input type="hidden" name="track" value="premium"><button class="btn btn-cyber-outline btn-sm" type="submit">Klaim emas +<?= $def['premium'] ?> XP<?= $def['pfreeze'] ? ' + freeze' : '' ?></button></form>
                    <?php else: ?>
                    <span class="small text-muted">Emas: +<?= $def['premium'] ?> XP<?= $def['pfreeze'] ? ' + freeze' : '' ?><?= $premium ? ($reached ? '' : ' (' . max(0, $def['xp'] - $sxp) . ' lagi)') : ' (butuh pass)' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
