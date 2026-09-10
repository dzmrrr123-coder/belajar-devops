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
if (!empty($me['onboarded'])) redirect('hub.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rawTrack = strtolower(trim((string)($_POST['track'] ?? '')));
    if (!\App\Domain\Track\Tracks::isValid($rawTrack)) {
        $_SESSION['onboarding_draft'] = ['track' => '', 'target' => trim((string)($_POST['target'] ?? '')), 'minutes' => (int)($_POST['minutes'] ?? 0), 'skills' => array_slice((array)($_POST['skills'] ?? []), 0, 3)];
        set_flash('warning', 'Pilih track dulu: RPL, TKJ, DKV, atau DevOps.');
        redirect('onboarding.php');
    }
    $track = \App\Domain\Track\Tracks::normalize($rawTrack);
    $target = trim((string)($_POST['target'] ?? ''));
    $minutes = (int)($_POST['minutes'] ?? 0);
    $skills = array_slice((array)($_POST['skills'] ?? []), 0, 3);
    if (!empty($_POST['skip'])) {
        $target = 'Masih ragu'; $minutes = 25; $skills = [];
    } elseif ($target === '' || !in_array($target, onboarding_targets($track), true)) {
        $_SESSION['onboarding_draft'] = ['track' => $track, 'target' => '', 'minutes' => $minutes, 'skills' => $skills];
        set_flash('warning', 'Pilih satu target PKL dulu biar quest-nya pas. Belum yakin? Pilih “Masih ragu”.');
        redirect('onboarding.php');
    } elseif (!in_array($minutes, onboarding_minutes(), true)) {
        $_SESSION['onboarding_draft'] = ['track' => $track, 'target' => $target, 'minutes' => 0, 'skills' => $skills];
        set_flash('warning', 'Pilih durasi harian: 15, 25, 45, atau 60 menit.');
        redirect('onboarding.php');
    }
    unset($_SESSION['onboarding_draft']);
    $plan = onboarding_plan($target, $minutes, $skills, $track);
    $sdefs = skill_defs($track);
    $picked = [];
    foreach (array_slice((array)($_POST['skills'] ?? []), 0, 3) as $s) {
        $s = trim((string)$s);
        if (isset($sdefs[$s]) && !in_array($s, $picked, true)) $picked[] = $s;
    }
    $csv = implode(',', $picked);
    $final_target = $target;
    $final_minutes = $minutes;
    $conn->begin_transaction();
    try {
        $up = $conn->prepare("UPDATE users SET onboarded = 1, pkl_target = ?, daily_minutes = ?, focus_skills = ?, track = ? WHERE id = ?");
        if (!$up) {
            $up = $conn->prepare("UPDATE users SET onboarded = 1, pkl_target = ?, daily_minutes = ?, focus_skills = ? WHERE id = ?");
            $up->bind_param("sisi", $final_target, $final_minutes, $csv, $user_id);
        } else {
            $up->bind_param("sissi", $final_target, $final_minutes, $csv, $track, $user_id);
        }
        $up->execute();
        $up->close();
        $ins = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward, track) VALUES (?, 1, 1, ?, ?, 10, ?)");
        $withTrack = (bool)$ins;
        if (!$withTrack) $ins = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward) VALUES (?, 1, 1, ?, ?, 10)");
        $n = 0;
        foreach ($plan as $p) {
            $t = mb_substr($p['title'], 0, 255);
            $d = mb_substr($p['description'], 0, 2000);
            if ($withTrack) $ins->bind_param("isss", $user_id, $t, $d, $track);
            else $ins->bind_param("iss", $user_id, $t, $d);
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
    \App\Analytics\Tracker::track($conn, $user_id, \App\Analytics\Events::ONBOARDING_COMPLETED, ['target' => $final_target, 'minutes' => $final_minutes, 'quests' => $n, 'track' => $track]);
    $conn->close();
    set_flash('success', "Siap! {$n} quest minggu pertama dibuat." . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : ''));
    redirect('hub.php');
}
$conn->close();

$tracks = \App\Domain\Track\Tracks::all();
$targetsByTrack = []; $skillsByTrack = [];
foreach (array_keys($tracks) as $tslug) { $targetsByTrack[$tslug] = onboarding_targets($tslug); $skillsByTrack[$tslug] = skill_defs($tslug); }
$minutes_list = onboarding_minutes();
$draft = $_SESSION['onboarding_draft'] ?? ['track' => 'devops', 'target' => '', 'minutes' => 25, 'skills' => []];
$draft_track = (string)($draft['track'] ?? 'devops');
if (!\App\Domain\Track\Tracks::isValid($draft_track)) $draft_track = 'devops';
$page_title = 'Mulai Belajar';
$minimal_nav = true;
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" id="main">
    <div class="page-head">
        <div class="page-kicker">2 langkah · 1 menit · hasil: 1–4 quest minggu pertama</div>
        <h1 class="page-title">Atur start-mu</h1>
        <p class="page-desc">Pilih jurusan + target, lalu atur ritme harian. Selesai = quest minggu pertama langsung jadi + XP masuk. <button type="submit" form="wizForm" name="skip" value="1" class="btn btn-link btn-sm text-secondary text-decoration-none p-0 align-baseline" formnovalidate>Lewati, isi nanti</button></p>
    </div>
    <form method="POST" action="onboarding.php" id="wizForm" class="wiz">
        <?= csrf_field() ?>
        <p class="visually-hidden" role="status" id="wizStatus">Langkah 1 dari 2: pilih jurusan dan target</p>
        <div class="wiz-progress" role="progressbar" aria-valuemin="1" aria-valuemax="2" aria-valuenow="1" aria-label="Progres pengaturan awal" id="wizBar"><span></span></div>
        <ol class="wiz-dots" aria-hidden="true">
            <li class="on" data-dot="1"><span>Langkah 1</span></li>
            <li data-dot="2"><span>Langkah 2</span></li>
        </ol>
        <fieldset class="card p-4 wiz-step on" data-step="1">
            <legend class="h5 fw-bold mb-1">Jurusan + target PKL</legend>
            <p class="text-secondary small mb-3">Track menentukan quest mingguanmu. Pilihan tiap track tersimpan otomatis saat ganti.</p>
            <h2 class="h6 fw-bold mt-3 mb-2">Track mana yang paling dekat denganmu?</h2>
            <div class="wiz-opts" role="radiogroup" aria-label="Track">
                <?php foreach ($tracks as $slug => $tr): ?>
                <label class="wiz-opt"><input type="radio" name="track" value="<?= htmlspecialchars($slug) ?>" <?= $draft_track === $slug ? 'checked' : '' ?> required><span><i class="<?= htmlspecialchars($tr['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($tr['name']) ?> · <?= htmlspecialchars($tr['desc']) ?></span></label>
                <?php endforeach; ?>
            </div>
            <h2 class="h6 fw-bold mt-4 mb-2">Mau PKL jadi apa?</h2>
            <p class="text-secondary small mb-2">Wajib pilih satu. Target ini dipakai menamai quest pertamamu.</p>
            <div class="wiz-opts" role="radiogroup" aria-label="Target PKL">
                <?php foreach ($targetsByTrack as $tslug => $tlist): foreach ($tlist as $i => $t): ?>
                <label class="wiz-opt" data-track-opt="<?= htmlspecialchars($tslug) ?>" <?= $tslug !== $draft_track ? 'hidden' : '' ?>><input type="radio" name="target" value="<?= htmlspecialchars($t) ?>" <?= ($draft['target'] ?? '') === $t ? 'checked' : '' ?>><span><?= htmlspecialchars($t) ?></span></label>
                <?php endforeach; endforeach; ?>
            </div>
            <div class="wiz-nav"><span></span><button type="button" class="btn btn-cyber" data-next>Lanjut: ritme harian</button></div>
        </fieldset>
        <fieldset class="card p-4 wiz-step" data-step="2">
            <legend class="h5 fw-bold mb-1">Ritme harian + skill fokus</legend>
            <h2 class="h6 fw-bold mt-2 mb-2">Berapa menit per hari?</h2>
            <p class="text-secondary small mb-2">Jujur saja. Kecil tapi rutin lebih menang.</p>
            <div class="wiz-opts" role="radiogroup" aria-label="Menit per hari">
                <?php foreach ($minutes_list as $m): ?>
                <label class="wiz-opt"><input type="radio" name="minutes" value="<?= $m ?>" <?= (int)($draft['minutes'] ?? 25) === (int)$m ? 'checked' : '' ?> required><span><?= $m ?> menit</span></label>
                <?php endforeach; ?>
            </div>
            <h2 class="h6 fw-bold mt-4 mb-2">Fokus ke skill apa? (maks 3, boleh kosong)</h2>
            <p class="text-secondary small mb-2">Tiap skill jadi 1 quest fondasi.</p>
            <div class="wiz-opts" role="group" aria-label="Skill fokus">
                <?php $draft_skills = (array)($draft['skills'] ?? []); foreach ($skillsByTrack as $tslug => $sdefs): foreach ($sdefs as $name => $d): ?>
                <label class="wiz-opt" data-track-opt="<?= htmlspecialchars($tslug) ?>" <?= $tslug !== $draft_track ? 'hidden' : '' ?>><input type="checkbox" name="skills[]" value="<?= htmlspecialchars($name) ?>" <?= in_array($name, $draft_skills, true) ? 'checked' : '' ?>><span><i class="<?= htmlspecialchars($d['icon']) ?>" aria-hidden="true"></i> <?= htmlspecialchars($name) ?></span></label>
                <?php endforeach; endforeach; ?>
            </div>
            <div class="card bg-body-tertiary border-0 p-3 mt-3" aria-live="polite">
                <strong class="small d-block mb-1">Ringkasan start-mu</strong>
                <p class="small text-secondary mb-1" id="wizSummary">Pilih track dan target dulu.</p>
                <p class="small text-secondary mb-0" id="wizPreview" role="status">Akan dibuat: 1 quest pembuka.</p>
            </div>
            <div class="wiz-nav mt-3"><button type="button" class="btn btn-cyber-outline" data-back>Kembali</button><button type="submit" class="btn btn-cyber">Buatkan quest-ku</button></div>
            <div class="text-center mt-3"><button type="submit" name="skip" value="1" class="btn btn-link btn-sm text-secondary text-decoration-none" formnovalidate>Lewati, isi nanti</button></div>
        </fieldset>
    </form>
</main>
<script>
(function() {
    var form = document.getElementById('wizForm');
    if (!form) return;
    form.classList.add('js');
    var steps = Array.prototype.slice.call(form.querySelectorAll('.wiz-step'));
    var dots = Array.prototype.slice.call(form.querySelectorAll('.wiz-dots li'));
    var bar = document.getElementById('wizBar');
    var status = document.getElementById('wizStatus');
    var preview = document.getElementById('wizPreview');
    var titles = ['pilih jurusan dan target', 'atur ritme harian'];
    var cur = 0;
    function mem() {
        try { return JSON.parse(localStorage.getItem('lt_onboarding_v2') || '{}'); }
        catch (e) { return {}; }
    }
    function saveMem(o) {
        try { localStorage.setItem('lt_onboarding_v2', JSON.stringify(o)); } catch (e) {}
    }
    try {
        var saved = mem();
        var keys = Object.keys(saved);
        if (keys.length) {
            var first = keys[0];
            var r = form.querySelector('input[name="track"][value="' + first + '"]');
            if (r && !form.querySelector('input[name="track"]:checked')) r.checked = true;
        }
    } catch (e) {}
    function persist() {
        try {
            var t = form.querySelector('input[name="track"]:checked');
            if (!t) return;
            var all = mem();
            var tg = form.querySelector('input[name="target"]:checked');
            var mn = form.querySelector('input[name="minutes"]:checked');
            var sk = [];
            form.querySelectorAll('input[name="skills[]"]:checked').forEach(function(c) { sk.push(c.value); });
            all[t.value] = { target: tg && tg.value, minutes: mn && mn.value, skills: sk };
            saveMem(all);
        } catch (e) {}
    }
    function summary() {
        var box = document.getElementById('wizSummary');
        if (!box) return;
        var t = form.querySelector('input[name="track"]:checked');
        var tg = form.querySelector('input[name="target"]:checked');
        var mn = form.querySelector('input[name="minutes"]:checked');
        var n = form.querySelectorAll('input[name="skills[]"]:checked').length;
        var tn = t ? t.value.toUpperCase() : '–';
        box.textContent = 'Track ' + tn + ' · Target: ' + (tg ? tg.value : '–') + ' · ' + (mn ? mn.value + ' menit/hari' : '–') + ' · ' + n + ' skill.';
    }
    function updatePreview() {
        if (preview) {
            var n = form.querySelectorAll('input[name="skills[]"]:checked').length;
            preview.textContent = 'Akan dibuat: ' + (1 + n) + ' quest minggu pertama (' + (n ? n + ' fondasi + ' : '') + '1 pembuka).';
        }
        summary();
    }
    function show(i) {
        cur = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach(function(s, k) { s.classList.toggle('on', k === cur); });
        dots.forEach(function(d, k) { d.classList.toggle('on', k <= cur); });
        if (bar) { bar.setAttribute('aria-valuenow', String(cur + 1)); var f = bar.querySelector('span'); if (f) f.style.width = ((cur + 1) / steps.length * 100) + '%'; }
        if (status) status.textContent = 'Langkah ' + (cur + 1) + ' dari ' + steps.length + ': ' + titles[cur];
        persist();
    }
    function curTrack() {
        var t = form.querySelector('input[name="track"]:checked');
        return t ? t.value : 'devops';
    }
    function applyTrack(announce) {
        var t = curTrack();
        form.querySelectorAll('[data-track-opt]').forEach(function(el) {
            el.hidden = el.getAttribute('data-track-opt') !== t;
        });
        var all = mem();
        var sv = all[t] || {};
        var tg = form.querySelector('input[name="target"]:checked');
        var curTg = tg && tg.value;
        form.querySelectorAll('input[name="target"]').forEach(function(inp) { inp.checked = false; });
        form.querySelectorAll('input[name="skills[]"]').forEach(function(inp) { inp.checked = false; });
        if (sv.target) { var nt = form.querySelector('input[name="target"][value="' + sv.target + '"]'); if (nt && !nt.hidden && nt.closest('[data-track-opt]').hidden === false) nt.checked = true; }
        if (sv.minutes) { var nm = form.querySelector('input[name="minutes"][value="' + sv.minutes + '"]'); if (nm) nm.checked = true; }
        if (sv.skills) {
            sv.skills.forEach(function(v) {
                var c = form.querySelector('input[name="skills[]"][value="' + v + '"]');
                if (c && c.closest('[data-track-opt]').hidden === false) c.checked = true;
            });
        }
        if (announce && curTg && curTg !== (sv.target || null)) { try { showToast('Pilihan track sebelumnya tersimpan. Menampilkan pilihan tersimpan.', 'info'); } catch (e) {} }
        updatePreview();
    }
    function valid(i) {
        if (i === 0) {
            var hasTrack = !!form.querySelector('input[name="track"]:checked');
            var hasTarget = !!form.querySelector('input[name="target"]:checked');
            if (!hasTrack || !hasTarget) {
                try { showToast('Pilih track dan satu target PKL dulu.', 'warning'); } catch (e) {}
                return false;
            }
            return true;
        }
        return true;
    }
    form.addEventListener('click', function(e) {
        if (e.target.closest('[data-next]')) { if (valid(cur)) show(cur + 1); }
        if (e.target.closest('[data-back]')) show(cur - 1);
    });
    form.addEventListener('change', function(e) {
        if (e.target.name === 'track') applyTrack(true);
        if (e.target.name === 'skills[]') {
            var n = form.querySelectorAll('input[name="skills[]"]:checked').length;
            if (n > 3) { e.target.checked = false; try { showToast('Maks 3 skill.', 'warning'); } catch (err) {} }
        }
        persist(); updatePreview();
    });
    form.addEventListener('submit', function() { try { localStorage.removeItem('lt_onboarding'); } catch (e) {} });
    applyTrack(false);
    updatePreview();
    show(0);
})();
</script>
<?php require_once 'includes/footer.php'; ?>
