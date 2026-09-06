<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

define('SHOP_FREEZE_PRICE', 100);
define('SHOP_FLAIR_PRICE', 150);
define('SHOP_FLAIR_EDIT_PRICE', 50);
define('SHOP_REROLL_PRICE', 20);
define('SHOP_FREEZE_MAX', 3);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['shop_action'] ?? '';
    $stmt = $conn->prepare("SELECT xp, freeze_tokens, flair FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $me = $stmt->get_result()->fetch_assoc() ?: ['xp' => 0, 'freeze_tokens' => 0, 'flair' => null];
    $stmt->close();
    $balance = (int)$me['xp'];

    $conn->begin_transaction();
    try {
        if ($action === 'buy_freeze') {
            if ((int)$me['freeze_tokens'] >= SHOP_FREEZE_MAX) throw new Exception('Freeze sudah penuh (' . SHOP_FREEZE_MAX . ').');
            if ($balance < SHOP_FREEZE_PRICE) throw new Exception('XP kurang. Butuh ' . SHOP_FREEZE_PRICE . ' XP.');
            $up = $conn->prepare("UPDATE users SET freeze_tokens = freeze_tokens + 1 WHERE id = ?");
            $up->bind_param("i", $user_id); $up->execute(); $up->close();
            award_xp($conn, $user_id, -SHOP_FREEZE_PRICE, 'shop_freeze');
            $msg = 'Freeze +1! Streak-mu lebih aman.';
        } elseif ($action === 'buy_flair') {
            $flair = mb_substr(trim(strip_tags((string)($_POST['flair'] ?? ''))), 0, 24);
            if ($flair === '') throw new Exception('Tulis dulu teks flair-nya.');
            $price = !empty($me['flair']) ? SHOP_FLAIR_EDIT_PRICE : SHOP_FLAIR_PRICE;
            if ($balance < $price) throw new Exception('XP kurang. Butuh ' . $price . ' XP.');
            $up = $conn->prepare("UPDATE users SET flair = ? WHERE id = ?");
            $up->bind_param("si", $flair, $user_id); $up->execute(); $up->close();
            award_xp($conn, $user_id, -$price, 'shop_flair');
            $msg = 'Flair dipasang: ' . $flair;
        } elseif ($action === 'buy_reroll') {
            if (rate_limit_hit('shop_reroll', 10, 60)) throw new Exception('Pelan-pelan. Maks 10 kocokan per menit.');
            if ($balance < SHOP_REROLL_PRICE) throw new Exception('XP kurang. Butuh ' . SHOP_REROLL_PRICE . ' XP.');
            try { $r = random_int(1, 100); } catch (Throwable $e) { $r = 50; }
            if ($r <= 60) { try { $w = random_int(5, 10); } catch (Throwable $e) { $w = 7; } }
            elseif ($r <= 90) { try { $w = random_int(11, 20); } catch (Throwable $e) { $w = 15; } }
            else { try { $w = random_int(21, 30); } catch (Throwable $e) { $w = 25; } }
            $win = shop_reroll_win($r, $w);
            award_xp($conn, $user_id, -SHOP_REROLL_PRICE, 'shop_reroll');
            award_xp($conn, $user_id, $win, 'shop_reroll_win');
            $msg = 'Kocokan: +' . $win . ' XP! (modal ' . SHOP_REROLL_PRICE . ' XP)';
        } else {
            throw new Exception('Aksi tidak dikenal.');
        }
        $conn->commit();
        set_flash('success', $msg);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("shop: " . $e->getMessage());
        set_flash('warning', $e->getMessage());
    }
    redirect('shop.php');
}

$stmt = $conn->prepare("SELECT xp, freeze_tokens, flair FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc() ?: ['xp' => 0, 'freeze_tokens' => 0, 'flair' => null];
$stmt->close();
$conn->close();
$flair_price = !empty($me['flair']) ? SHOP_FLAIR_EDIT_PRICE : SHOP_FLAIR_PRICE;

$page_title = 'Toko XP';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Saldo: <?= (int)$me['xp'] ?> XP · freeze <?= (int)$me['freeze_tokens'] ?>/<?= SHOP_FREEZE_MAX ?></div>
        <h1 class="page-title">Toko XP</h1>
        <p class="page-desc">Belanjakan XP: freeze penyelamat streak, flair nama, atau kocokan untung-untungan.</p>
    </div>

    <div class="skill-grid">
        <section class="card skill-card" aria-label="Beli freeze">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-snowflake"></i></span><div class="skill-id"><strong>Freeze +1</strong><small>selamatkan streak 1 hari · maks <?= SHOP_FREEZE_MAX ?></small></div></div>
            <form method="POST" action="shop.php" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="shop_action" value="buy_freeze">
                <button class="btn btn-cyber w-100 btn-sm" type="submit" <?= (int)$me['freeze_tokens'] >= SHOP_FREEZE_MAX ? 'disabled' : '' ?>>Beli · <?= SHOP_FREEZE_PRICE ?> XP</button>
            </form>
        </section>
        <section class="card skill-card" aria-label="Flair profil">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-tag"></i></span><div class="skill-id"><strong>Flair profil</strong><small>tampil di leaderboard &amp; profil · maks 24 karakter</small></div></div>
            <form method="POST" action="shop.php" class="m-0 d-flex flex-column gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="shop_action" value="buy_flair">
                <input name="flair" class="form-control form-control-sm" maxlength="24" placeholder="cth: Begadang enjoyer" value="<?= htmlspecialchars($me['flair'] ?? '') ?>" aria-label="Teks flair">
                <button class="btn btn-cyber w-100 btn-sm" type="submit">Pasang · <?= $flair_price ?> XP</button>
            </form>
        </section>
        <section class="card skill-card" aria-label="Kocok untung">
            <div class="skill-top"><span class="skill-icon" aria-hidden="true"><i class="fas fa-dice"></i></span><div class="skill-id"><strong>Kocok untung</strong><small>20 XP → 5–30 XP acak · bandar selalu menang</small></div></div>
            <form method="POST" action="shop.php" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="shop_action" value="buy_reroll">
                <button class="btn btn-cyber w-100 btn-sm" type="submit">Kocok · <?= SHOP_REROLL_PRICE ?> XP</button>
            </form>
        </section>
    </div>
</main>
<?php require_once 'includes/footer.php'; ?>
