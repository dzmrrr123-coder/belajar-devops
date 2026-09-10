<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$track = user_track($conn, $uid);

define('LAB_SHARED_CAP', 50);
define('QUIZ_DAILY_XP_CAP', 20);
define('QUIZ_TODAY_DONE_SQL', "NOT EXISTS (SELECT 1 FROM xp_events e WHERE e.user_id = c.user_id AND e.ref_type = 'quiz' AND e.ref_id = c.id AND e.amount > 0 AND e.created_at >= CURDATE() AND e.created_at < CURDATE() + INTERVAL 1 DAY)");

function lab_shared_left(\mysqli $conn, int $uid): int {
    try {
        $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type IN ('quiz','lab','playground','terminal') AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
        $c->bind_param("i", $uid); $c->execute();
        $n = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close();
        return max(0, LAB_SHARED_CAP - $n);
    } catch (Throwable $e) { return LAB_SHARED_CAP; }
}

$LAB_ALATS = [
    'lab' => ['title' => 'Lab Soal', 'icon' => 'fas fa-flask', 'tracks' => null],
    'playground' => ['title' => 'Playground', 'icon' => 'fas fa-code', 'tracks' => ['rpl', 'devops']],
    'terminal' => ['title' => 'Terminal', 'icon' => 'fas fa-terminal', 'tracks' => ['tkj', 'devops']],
    'incident' => ['title' => 'Incident', 'icon' => 'fas fa-fire-extinguisher', 'tracks' => ['devops', 'tkj']],
    'topologi' => ['title' => 'Topologi', 'icon' => 'fas fa-network-wired', 'tracks' => ['tkj']],
];
$LAB_DEFAULT_ALAT = ['rpl' => 'playground', 'tkj' => 'topologi', 'devops' => 'incident', 'dkv' => 'lab'];

$tab = in_array($_GET['tab'] ?? '', ['kuis', 'praktik', 'mentor'], true) ? $_GET['tab'] : 'praktik';
$alat = $_GET['alat'] ?? ($LAB_DEFAULT_ALAT[$track] ?? 'lab');
if (!isset($LAB_ALATS[$alat])) $alat = 'lab';
if (isset($_GET['slug']) && $tab === 'praktik' && $alat === 'lab') { $_GET['lab_slug'] = $_GET['slug']; }

function lab_url(string $tab, string $alat = '', array $extra = []): string {
    $q = ['tab' => $tab];
    if ($tab === 'praktik' && $alat !== '') $q['alat'] = $alat;
    foreach ($extra as $k => $v) $q[$k] = $v;
    return 'lab.php?' . http_build_query($q);
}

if ($tab === 'praktik' && !empty($LAB_ALATS[$alat]['tracks'])) {
    $need = $LAB_ALATS[$alat]['tracks'];
    $titles = ['playground' => 'Coding Playground', 'terminal' => 'Terminal Linux', 'incident' => 'Incident Simulator', 'topologi' => 'Topologi & Subnet'];
    enforce_track_access($conn, $uid, $need, $titles[$alat] ?? 'Praktik');
}

// ---------- POST dispatcher ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paction = $_POST['action'] ?? '';

    // Kuis: jawab / buat kartu (dipakai juga oleh sync.js offline + cards.js AJAX)
    if ($paction === 'answer' || $paction === 'create') {
        require __DIR__ . '/includes/lab/post_kuis.php';
    } elseif (isset($_POST['lab_submit'])) {
        if (rate_limit_hit('lab_submit', 20, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect(lab_url('praktik', 'lab', ['lab_slug' => $_POST['lab_slug'] ?? ''])); }
        $lab = \App\Domain\Lab\LabBank::find(trim($_POST['lab_slug'] ?? ''));
        if (!$lab) { set_flash('warning', 'Lab tidak ditemukan.'); redirect(lab_url('praktik', 'lab')); }
        $input = mb_substr(trim((string)($_POST['answer'] ?? '')), 0, 50);
        $ok = \App\Domain\Lab\LabBank::grade($lab, $input);
        $gain = 0;
        if ($ok) {
            $cap = 0;
            $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'lab' AND amount > 0 AND created_at >= CURDATE()");
            if ($c) { $c->bind_param("i", $uid); $c->execute(); $cap = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close(); }
            $gain = min(capped_xp_gain((int)$lab['xp'], $cap, 30), lab_shared_left($conn, $uid));
            if ($gain > 0) {
                award_xp($conn, $uid, $gain, 'lab', 'lab', crc32($lab['slug']) % 100000);
                \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$lab['skill']), 5, 'lab', 'lab', crc32($lab['slug']) % 100000);
            }
            check_and_unlock_badges($conn, $uid);
        }
        $_SESSION['lab_bank_result'] = ['lab' => $lab, 'ok' => $ok, 'gain' => $gain, 'input' => $input];
        redirect(lab_url('praktik', 'lab', ['lab_slug' => $lab['slug']]));
    } elseif (isset($_POST['pg_submit'])) {
        if (rate_limit_hit('pg_submit', 20, 3600)) { set_flash('warning', 'Terlalu sering. Coba lagi nanti.'); redirect(lab_url('praktik', 'playground', ['pg_slug' => $_POST['pg_slug'] ?? ''])); }
        $pgslug = trim($_POST['pg_slug'] ?? 'php-diskon');
        $task = \App\Domain\Playground::find($pgslug) ?: \App\Domain\Playground::find('php-diskon');
        $code = substr((string)($_POST['code'] ?? ''), 0, 2000);
        $ok = \App\Domain\Playground::grade($task['slug'], $code);
        $gain = 0; $msg = '';
        if (($task['lang'] ?? '') === 'php') {
            $r = \App\Domain\Playground::safePhpOutput($code);
            $msg = $r['ok'] ? ('output: ' . $r['output']) : $r['msg'];
        }
        if ($ok) {
            $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'playground' AND amount > 0 AND created_at >= CURDATE()");
            $sum = 0;
            if ($c) { $c->bind_param("i", $uid); $c->execute(); $sum = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close(); }
            $gain = min(capped_xp_gain((int)$task['xp'], $sum, 30), lab_shared_left($conn, $uid));
            if ($gain > 0) {
                award_xp($conn, $uid, $gain, 'playground', 'playground', crc32($task['slug']) % 100000);
                \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$task['skill']), 5, 'playground', 'playground', crc32($task['slug']) % 100000);
            }
            check_and_unlock_badges($conn, $uid);
        }
        $_SESSION['lab_pg_result'] = ['ok' => $ok, 'gain' => $gain, 'msg' => $msg, 'code' => $code, 'slug' => $task['slug']];
        redirect(lab_url('praktik', 'playground', ['pg_slug' => $task['slug']]));
    } elseif (isset($_POST['tm_op'])) {
        $tm_slug = trim($_POST['tm_m'] ?? 'fix-www');
        $mission = \App\Domain\Tkj\Terminal::find($tm_slug) ?: \App\Domain\Tkj\Terminal::find('fix-www');
        if ($_POST['tm_op'] === 'reset') { unset($_SESSION['term_' . $mission['slug']]); redirect(lab_url('praktik', 'terminal', ['tm_m' => $mission['slug']])); }
        if (rate_limit_hit('term_cmd', 60, 3600)) { set_flash('warning', 'Terlalu sering.'); redirect(lab_url('praktik', 'terminal', ['tm_m' => $mission['slug']])); }
        $st = $_SESSION['term_' . $mission['slug']] ?? [];
        $r = \App\Domain\Tkj\Terminal::exec($st, (string)($_POST['tm_cmd'] ?? ''));
        $st = $r['state'];
        $_SESSION['term_' . $mission['slug']] = $st;
        if (\App\Domain\Tkj\Terminal::missionDone($st, $mission) && empty($st['claimed'])) {
            $st['claimed'] = true;
            $_SESSION['term_' . $mission['slug']] = $st;
            $c = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = 'terminal' AND amount > 0 AND created_at >= CURDATE()");
            $sum = 0;
            if ($c) { $c->bind_param("i", $uid); $c->execute(); $sum = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close(); }
            $gain = min(capped_xp_gain((int)$mission['xp'], $sum, 30), lab_shared_left($conn, $uid));
            if ($gain > 0) {
                award_xp($conn, $uid, $gain, 'terminal', 'terminal', crc32($mission['slug']) % 100000);
                \App\Domain\Skill\Mastery::award($conn, $uid, \App\Domain\Skill\Mastery::nodeForSkill((string)$mission['skill']), 5, 'terminal', 'terminal', crc32($mission['slug']) % 100000);
            }
            check_and_unlock_badges($conn, $uid);
        }
        redirect(lab_url('praktik', 'terminal', ['tm_m' => $mission['slug']]));
    } elseif (isset($_POST['inc_op'])) {
        require __DIR__ . '/includes/lab/post_incident.php';
    } elseif (isset($_POST['topo_op'])) {
        $cidr_back = trim($_POST['cidr'] ?? '192.168.1.0/24');
        $name = mb_substr(trim(clean($_POST['name'] ?? 'topologi')), 0, 80);
        $payload = substr((string)($_POST['payload'] ?? ''), 0, 8000);
        if ($name === '' || $payload === '') set_flash('warning', 'Nama + payload wajib.');
        else {
            $s = $conn->prepare("INSERT INTO topo_saves (user_id, name, payload) VALUES (?, ?, ?)");
            if ($s) { $s->bind_param("iss", $uid, $name, $payload); $s->execute(); $s->close(); \App\Domain\Track\Hub::forget($conn, $uid); set_flash('success', 'Topologi tersimpan.'); }
        }
        redirect(lab_url('praktik', 'topologi', ['cidr' => $cidr_back]));
    }
}

$lab_quota_left = lab_shared_left($conn, $uid);
$trackInfo = \App\Domain\Track\Tracks::all()[$track] ?? ['name' => strtoupper($track), 'icon' => 'fas fa-flask'];
$page_title = 'Lab Praktik';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head mb-4">
    <div class="page-kicker eyebrow"><i class="<?= htmlspecialchars($trackInfo['icon'] ?? 'fas fa-flask') ?> me-1"></i> Jurusan <?= htmlspecialchars($trackInfo['name']) ?> · Lab Praktikum</div>
    <h1 class="page-title">Lab praktik</h1>
    <p class="page-desc">Kuis kilat, praktik jurusan, dan mentor dalam satu tempat. Kuota latihan: <strong>+<?= (int)$lab_quota_left ?> XP</strong> tersisa hari ini (maks 50, kuis ≤20).</p>
    <div class="segmented mt-2" role="group" aria-label="Tab lab">
        <a href="<?= lab_url('kuis') ?>" class="filter-pill <?= $tab === 'kuis' ? 'active' : '' ?>"><i class="fas fa-bolt me-1"></i>Kuis Kilat</a>
        <a href="<?= lab_url('praktik', $alat) ?>" class="filter-pill <?= $tab === 'praktik' ? 'active' : '' ?>"><i class="fas fa-flask me-1"></i>Praktik</a>
        <a href="<?= lab_url('mentor') ?>" class="filter-pill <?= $tab === 'mentor' ? 'active' : '' ?>"><i class="fas fa-robot me-1"></i>Mentor</a>
    </div>
    <?php if ($tab === 'praktik'): ?>
    <div class="segmented mt-2" role="group" aria-label="Alat praktik">
        <?php foreach ($LAB_ALATS as $ak => $ai): if (!empty($ai['tracks']) && !in_array($track, $ai['tracks'], true)) continue; ?>
        <a href="<?= lab_url('praktik', $ak) ?>" class="filter-pill <?= $alat === $ak ? 'active' : '' ?>"><i class="<?= htmlspecialchars($ai['icon']) ?> me-1"></i><?= htmlspecialchars($ai['title']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php
if ($tab === 'kuis') require __DIR__ . '/includes/lab/tab_kuis.php';
elseif ($tab === 'mentor') require __DIR__ . '/includes/lab/tab_mentor.php';
else {
    $alat_file = __DIR__ . '/includes/lab/tab_' . preg_replace('/[^a-z]/', '', $alat) . '.php';
    if ($alat === 'lab') $alat_file = __DIR__ . '/includes/lab/tab_bank.php';
    if ($alat === 'playground') $alat_file = __DIR__ . '/includes/lab/tab_pg.php';
    if ($alat === 'terminal') $alat_file = __DIR__ . '/includes/lab/tab_term.php';
    if ($alat === 'incident') $alat_file = __DIR__ . '/includes/lab/tab_inc.php';
    if ($alat === 'topologi') $alat_file = __DIR__ . '/includes/lab/tab_topo.php';
    require $alat_file;
}
?>
</main>
<?php $conn->close(); require_once 'includes/footer.php'; ?>
