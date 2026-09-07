<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("SELECT onboarded FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!empty($me['onboarded'])) redirect('index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (($_POST['wiz'] ?? '') === 'skip') {
        $up = $conn->prepare("UPDATE users SET onboarded = 1 WHERE id = ?");
        $up->bind_param("i", $user_id);
        $up->execute();
        $up->close();
        set_flash('info', 'Wizard dilewati. Quest bisa dibuat manual dari Roadmap.');
        redirect('index.php');
    }
    $target = trim((string)($_POST['target'] ?? ''));
    $minutes = (int)($_POST['minutes'] ?? 25);
    $skills = array_slice((array)($_POST['skills'] ?? []), 0, 3);
    $plan = onboarding_plan($target, $minutes, $skills);
    $sdefs = skill_defs();
    $picked = [];
    foreach (array_slice((array)($_POST['skills'] ?? []), 0, 3) as $s) {
        $s = trim((string)$s);
        if (isset($sdefs[$s]) && !in_array($s, $picked, true)) $picked[] = $s;
    }
    $csv = implode(',', $picked);
    $final_target = in_array($target, onboarding_targets(), true) ? $target : 'Masih ragu';
    $final_minutes = in_array($minutes, onboarding_minutes(), true) ? $minutes : 25;
    $conn->begin_transaction();
    try {
        $up = $conn->prepare("UPDATE users SET onboarded = 1, pkl_target = ?, daily_minutes = ?, focus_skills = ? WHERE id = ?");
        $up->bind_param("sisi", $final_target, $final_minutes, $csv, $user_id);
        $up->execute();
        $up->close();
        $ins = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward) VALUES (?, 1, 1, ?, ?, 10)");
        $n = 0;
        foreach ($plan as $p) {
            $t = mb_substr($p['title'], 0, 255);
            $d = mb_substr($p['description'], 0, 2000);
            $ins->bind_param("iss", $user_id, $t, $d);
            if ($ins->execute()) $n++;
        }
        $ins->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log("onboarding: " . $e->getMessage());
        set_flash('danger', 'Gagal menyimpan. Coba lagi.');
        redirect('onboarding.php');
    }
    $nb = check_and_unlock_badges($conn, $user_id);
    \App\Analytics\Tracker::track($conn, $user_id, \App\Analytics\Events::ONBOARDING_COMPLETED, ['target' => $final_target, 'minutes' => $final_minutes, 'quests' => $n]);
    $conn->close();
    set_flash('success', "Siap! {$n} quest minggu pertama dibuat." . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : ''));
    redirect('index.php');
}
$conn->close();

$targets = onboarding_targets();
$minutes_list = onboarding_minutes();
$defs = skill_defs();
$page_title = 'Mulai Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" id="main" role="main">
    <div class="page-head">
        <div class="page-kicker">3 langkah · 1 menit</div>
        <h1 class="page-title">Atur start-mu</h1>
        <p class="page-desc">Jawab 3 hal, kami siapkan quest minggu pertama yang pas buatmu.</p>
    </div>
    <form method="POST" action="onboarding.php" id="wizForm" class="wiz">
        <?= csrf_field() ?>
        <ol class="wiz-dots" aria-label="Progres wizard">
            <li class="on" data-dot="1"><span>Langkah 1</span></li>
            <li data-dot="2"><span>Langkah 2</span></li>
            <li data-dot="3"><span>Langkah 3</span></li>
        </ol>
        <fieldset class="card p-4 wiz-step on" data-step="1">
            <legend class="h5 fw-bold mb-1">Mau PKL jadi apa?</legend>
            <p class="text-secondary small mb-3">Target ini dipakai menamai quest pertamamu.</p>
            <div class="wiz-opts" role="radiogroup" aria-label="Target PKL">
                <?php foreach ($targets as $i => $t): ?>
                <label class="wiz-opt"><input type="radio" name="target" value="<?= htmlspecialchars($t) ?>" <?= $i === 0 ? 'required' : '' ?>><span><?= htmlspecialchars($t) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="wiz-nav"><span></span><button type="button" class="btn btn-cyber" data-next>Lanjut</button></div>
        </fieldset>
        <fieldset class="card p-4 wiz-step" data-step="2">
            <legend class="h5 fw-bold mb-1">Berapa menit per hari?</legend>
            <p class="text-secondary small mb-3">Jujur saja. Kecil tapi rutin lebih menang.</p>
            <div class="wiz-opts" role="radiogroup" aria-label="Menit per hari">
                <?php foreach ($minutes_list as $m): ?>
                <label class="wiz-opt"><input type="radio" name="minutes" value="<?= $m ?>" <?= $m === 25 ? 'checked' : '' ?> required><span><?= $m ?> menit</span></label>
                <?php endforeach; ?>
            </div>
            <div class="wiz-nav"><button type="button" class="btn btn-cyber-outline" data-back>Kembali</button><button type="button" class="btn btn-cyber" data-next>Lanjut</button></div>
        </fieldset>
        <fieldset class="card p-4 wiz-step" data-step="3">
            <legend class="h5 fw-bold mb-1">Fokus ke skill apa? (maks 3)</legend>
            <p class="text-secondary small mb-3">Tiap skill jadi 1 quest fondasi. Boleh kosongkan.</p>
            <div class="wiz-opts" role="group" aria-label="Skill fokus">
                <?php foreach ($defs as $name => $d): ?>
                <label class="wiz-opt"><input type="checkbox" name="skills[]" value="<?= htmlspecialchars($name) ?>"><span><i class="<?= htmlspecialchars($d['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($name) ?></span></label>
                <?php endforeach; ?>
            </div>
            <div class="wiz-nav"><button type="button" class="btn btn-cyber-outline" data-back>Kembali</button><button type="submit" class="btn btn-cyber">Buatkan quest-ku</button></div>
        </fieldset>
    </form>
    <form method="POST" action="onboarding.php" class="m-0 mt-3 text-center">
        <?= csrf_field() ?>
        <input type="hidden" name="wiz" value="skip">
        <button type="submit" class="page-actions-link" style="border:none;background:none;">Lewati, saya atur sendiri</button>
    </form>
</main>
<script>
(function() {
    var form = document.getElementById('wizForm');
    if (!form) return;
    form.classList.add('js');
    var steps = Array.prototype.slice.call(form.querySelectorAll('.wiz-step'));
    var dots = Array.prototype.slice.call(form.querySelectorAll('.wiz-dots li'));
    var cur = 0;
    function show(i) {
        cur = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach(function(s, k) { s.classList.toggle('on', k === cur); });
        dots.forEach(function(d, k) { d.classList.toggle('on', k <= cur); });
        var h = steps[cur].querySelector('legend');
        if (h) h.setAttribute('tabindex', '-1'), h.focus({ preventScroll: true });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function valid(i) {
        var checked = steps[i].querySelectorAll('input:checked').length;
        if (i === 2) return true;
        if (!checked) {
            try { showToast('Pilih satu dulu untuk lanjut.', 'warning'); } catch (e) {}
            return false;
        }
        return true;
    }
    form.addEventListener('click', function(e) {
        if (e.target.closest('[data-next]')) { if (valid(cur)) show(cur + 1); }
        if (e.target.closest('[data-back]')) show(cur - 1);
    });
    form.addEventListener('change', function(e) {
        if (e.target.name === 'skills[]') {
            var n = form.querySelectorAll('input[name="skills[]"]:checked').length;
            if (n > 3) { e.target.checked = false; try { showToast('Maks 3 skill.', 'warning'); } catch (err) {} }
        }
    });
    show(0);
})();
</script>
<?php require_once 'includes/footer.php'; ?>
