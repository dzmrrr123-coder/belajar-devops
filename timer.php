<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Get today's Pomodoro sessions
$stmt = $conn->prepare("
    SELECT COUNT(*) as today_sessions, COALESCE(SUM(duration_minutes), 0) as today_minutes 
    FROM pomodoro_sessions 
    WHERE user_id = ? AND completed_at >= CURDATE() AND completed_at < CURDATE() + INTERVAL 1 DAY
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get lifetime Pomodoro sessions
$stmt = $conn->prepare("
    SELECT COUNT(*) as total_sessions, COALESCE(SUM(duration_minutes), 0) as total_minutes 
    FROM pomodoro_sessions 
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$all_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent 5 sessions
$stmt = $conn->prepare("
    SELECT * FROM pomodoro_sessions
    WHERE user_id = ?
    ORDER BY completed_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$daily_target = 2;
$target_done = 0;
$weekly = [];
try {
    $q = $conn->prepare("SELECT COUNT(*) c FROM pomodoro_sessions WHERE user_id = ? AND completed_at >= CURDATE() AND completed_at < CURDATE() + INTERVAL 1 DAY AND duration_minutes >= 25 AND mode = 'focus'");
    $q->bind_param("i", $user_id); $q->execute();
    $target_done = (int)($q->get_result()->fetch_assoc()['c'] ?? 0);
    $q->close();
    $q = $conn->prepare("SELECT DATE(completed_at) d, COUNT(*) c, COALESCE(SUM(duration_minutes),0) m FROM pomodoro_sessions WHERE user_id = ? AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(completed_at)");
    $q->bind_param("i", $user_id); $q->execute();
    $rows = $q->get_result()->fetch_all(MYSQLI_ASSOC);
    $q->close();
    $map = [];
    foreach ($rows as $r) $map[$r['d']] = $r;
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $weekly[] = ['date' => $d, 'label' => date('D', strtotime($d)), 'count' => (int)($map[$d]['c'] ?? 0), 'minutes' => (int)($map[$d]['m'] ?? 0)];
    }
} catch (Throwable $e) {}
$weekly_max = max(1, max(array_column($weekly, 'count') ?: [0]));
$target_pct = min(100, round(($target_done / $daily_target) * 100));



$page_title = 'Pomodoro Focus Timer';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <div class="row g-4 justify-content-center">
        <!-- Main Timer Card -->
        <div class="col-lg-8">
            <div class="timer-container focus-workspace">
                <div class="d-flex justify-content-center gap-2 mb-4">
                    <button type="button" class="btn btn-cyber-outline btn-sm timer-mode-btn active" data-mode="focus" data-time="25">
                        <i class="fas fa-brain me-1 text-primary"></i> Fokus (25m)
                    </button>
                    <button type="button" class="btn btn-cyber-outline btn-sm timer-mode-btn" data-mode="shortBreak" data-time="5">
                        <i class="fas fa-coffee me-1 text-cyan"></i> Istirahat (5m)
                    </button>
                    <button type="button" class="btn btn-cyber-outline btn-sm timer-mode-btn" data-mode="longBreak" data-time="15">
                        <i class="fas fa-couch me-1 text-emerald"></i> Istirahat Panjang (15m)
                    </button>
                </div>

                <!-- SVG Circular Display -->
                <div class="timer-circle-wrapper">
                    <svg class="timer-svg" viewBox="0 0 220 220">
                        <defs>
                            <linearGradient id="timerGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#52796f" />
                                <stop offset="100%" stop-color="#6f9676" />
                            </linearGradient>
                        </defs>
                        <!-- Background ring -->
                        <circle class="timer-svg-bg" cx="110" cy="110" r="95"></circle>
                        <!-- Animated Progress ring -->
                        <circle class="timer-svg-progress" id="timerProgressCircle" cx="110" cy="110" r="95" stroke-dasharray="596.9" stroke-dashoffset="0"></circle>
                    </svg>

                    <div class="timer-center-text">
                        <div class="timer-digits" id="timerDisplay" role="timer" aria-label="Sisa waktu">25:00</div>
                        <div class="timer-state-label" id="timerStateLabel">Fokus Belajar</div>
                    </div>
                </div>

                <!-- Controls -->
                <div class="d-flex justify-content-center align-items-center gap-3 mb-4">
                    <button type="button" class="btn btn-cyber px-4 py-2 fs-5" id="startBtn" onclick="toggleTimer()">
                        <i class="fas fa-play me-2"></i> <span id="startBtnText">Mulai</span>
                    </button>
                    <button type="button" class="btn btn-cyber-outline px-3 py-2" id="resetBtn" onclick="resetTimer()" title="Atur ulang timer" aria-label="Atur ulang timer">
                        <i class="fas fa-redo" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn btn-cyber-outline px-3 py-2" id="skipBtn" onclick="skipTimer()" title="Lewati sesi ini" aria-label="Lewati sesi ini">
                        <i class="fas fa-forward" aria-hidden="true"></i>
                    </button>
                </div>

                <p class="text-secondary small text-center mb-3 mx-auto" style="max-width: 480px;">Selesaikan satu sesi fokus 25 menit untuk +10 XP.</p>
                <div class="mx-auto" style="max-width: 480px;">
                    <div class="row g-2 mb-3">
                        <div class="col-7"><label class="form-label" for="focusNote">Fokus saat ini (opsional)</label><input id="focusNote" class="form-control" maxlength="255" placeholder="Contoh: CRUD produk + prepared statement"></div>
                        <div class="col-5"><label class="form-label" for="customMinutes">Fokus custom (mnt)</label><div class="d-flex gap-2"><input id="customMinutes" type="number" min="5" max="120" value="25" class="form-control"><button type="button" class="btn btn-cyber-outline flex-shrink-0" onclick="setCustomFocus()" title="Pakai durasi custom" aria-label="Pakai durasi custom"><i class="fas fa-check"></i></button></div></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Statistics -->
        <div class="col-lg-4 d-flex flex-column gap-3">
            <!-- Today Stats -->
            <div class="card p-4">
                <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="fas fa-chart-line text-emerald"></i> Fokus hari ini
                </h2>
                <div class="row g-2 text-center mb-3">
                    <div class="col-6">
                        <div class="p-3 session-row">
                            <div class="fs-4 fw-bold text-emerald" id="todaySessionsCount"><?= (int)$today_stats['today_sessions'] ?></div>
                            <div class="text-secondary small">Sesi Selesai</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 session-row">
                            <div class="fs-4 fw-bold text-cyan" id="todayMinutesCount"><?= (int)$today_stats['today_minutes'] ?></div>
                            <div class="text-secondary small">Menit Fokus</div>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between text-secondary small pt-2 border-top">
                    <span>Total Sepanjang Waktu:</span>
                    <strong class="text-white"><?= (int)$all_stats['total_sessions'] ?> sesi (<?= round((int)$all_stats['total_minutes'] / 60, 1) ?> jam)</strong>
                </div>
            </div>

            <div class="card p-4">
                <h2 class="h6 fw-bold mb-1">Target harian</h2>
                <p class="text-secondary small mb-2"><?= $target_done ?>/<?= $daily_target ?> sesi fokus 25m</p>
                <div class="xp-progress-bar" role="progressbar" aria-valuenow="<?= $target_pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Progres target harian"><div class="xp-progress-fill" style="width:<?= $target_pct ?>%"></div></div>
            </div>

            <div class="card p-4">
                <h2 class="h6 fw-bold mb-3">7 hari terakhir</h2>
                <div class="chart-bars" role="img" aria-label="Grafik sesi fokus mingguan">
                    <?php foreach ($weekly as $w): $h = max(6, round(($w['count'] / $weekly_max) * 72)); ?>
                    <div class="chart-col" title="<?= $w['date'] ?> · <?= $w['count'] ?> sesi · <?= $w['minutes'] ?> mnt">
                        <span class="chart-val"><?= $w['count'] ?></span>
                        <span class="chart-bar" style="height:<?= $h ?>px"></span>
                        <span class="chart-lbl"><?= htmlspecialchars($w['label']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Recent Sessions List -->
            <div class="card p-4">
                <h2 class="h6 fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="fas fa-history text-primary"></i> Sesi terbaru
                </h2>
                <div id="recentSessionsList" class="d-flex flex-column gap-2">
                    <?php if (!empty($recent_sessions)): ?>
                        <?php foreach ($recent_sessions as $sess): ?>
                            <div class="session-row p-2 px-3 d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-2 small">
                                    <i class="fas fa-check-circle text-emerald"></i>
                                    <span><?= (int)$sess['duration_minutes'] ?> Menit Selesai<?= !empty($sess['focus_note']) ? ' · ' . htmlspecialchars(mb_strimwidth($sess['focus_note'], 0, 40, '...')) : '' ?></span>
                                </div>
                                <span class="text-muted small"><?= date('H:i, d M', strtotime($sess['completed_at'])) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted small mb-0 text-center py-2" id="noSessionsText">Belum ada sesi tercatat hari ini.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
const CIRCUMFERENCE = 2 * Math.PI * 95; // r = 95 -> ~596.90

const STORE_KEY = 'lt_timer_v1';

let timerMode = 'focus';
let totalSeconds = 25 * 60;
let remainingSeconds = 25 * 60;
let timerInterval = null;
let isRunning = false;
let endsAt = null;
let sessionId = null;
let completedFor = null;

function saveTimer() {
    try {
        const noteEl = document.getElementById('focusNote');
        const customEl = document.getElementById('customMinutes');
        localStorage.setItem(STORE_KEY, JSON.stringify({
            mode: timerMode, totalSec: totalSeconds, remainingSec: remainingSeconds,
            endsAt: endsAt, running: isRunning, sessionId: sessionId,
            finished: false, note: noteEl ? noteEl.value.slice(0, 255) : '',
            customMin: customEl ? customEl.value : '', ts: Date.now()
        }));
    } catch (e) {}
}

function readTimerStore() {
    try { return JSON.parse(localStorage.getItem(STORE_KEY) || 'null'); } catch (e) { return null; }
}

function setModeUI() {
    document.querySelectorAll('.timer-mode-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-mode') === timerMode);
    });
    let label = 'Fokus Belajar';
    if (timerMode === 'shortBreak') label = 'Istirahat Pendek';
    if (timerMode === 'longBreak') label = 'Istirahat Panjang';
    document.getElementById('timerStateLabel').textContent = label;
}

function setStartBtn(text, icon) {
    document.getElementById('startBtnText').textContent = text;
    document.getElementById('startBtn').querySelector('i').className = icon;
}

const circle = document.getElementById('timerProgressCircle');
circle.style.strokeDasharray = `${CIRCUMFERENCE} ${CIRCUMFERENCE}`;
circle.style.strokeDashoffset = '0';

function setProgress(percent) {
    const offset = CIRCUMFERENCE - (percent / 100) * CIRCUMFERENCE;
    circle.style.strokeDashoffset = offset;
}

function updateDisplay() {
    const mins = Math.floor(Math.max(0, remainingSeconds) / 60);
    const secs = Math.max(0, remainingSeconds) % 60;
    const timeStr = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    document.getElementById('timerDisplay').textContent = timeStr;
    if (location.pathname.endsWith('timer.php')) document.title = `(${timeStr}) Pomodoro - Learn Tracker`;

    const progress = ((totalSeconds - remainingSeconds) / totalSeconds) * 100;
    setProgress(progress);
}

function toggleTimer() {
    if (isRunning) {
        pauseTimer();
    } else {
        startTimer();
    }
}

function tickTimer() {
    const r = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
    remainingSeconds = r;
    if (r <= 0) {
        clearInterval(timerInterval);
        timerInterval = null;
        isRunning = false;
        endsAt = null;
        updateDisplay();
        sessionCompleted();
    } else {
        updateDisplay();
    }
}

function startTimer() {
    if (isRunning) return;
    if (remainingSeconds <= 0) remainingSeconds = totalSeconds;
    if (!sessionId) sessionId = 't' + Date.now().toString(36);
    endsAt = Date.now() + remainingSeconds * 1000;
    isRunning = true;
    setStartBtn('Jeda', 'fas fa-pause me-2');

    // Request notification permission if needed
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    saveTimer();
    clearInterval(timerInterval);
    timerInterval = setInterval(tickTimer, 500);
    updateDisplay();
}

function pauseTimer() {
    if (isRunning && endsAt) remainingSeconds = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000));
    clearInterval(timerInterval);
    timerInterval = null;
    isRunning = false;
    endsAt = null;
    setStartBtn('Lanjut', 'fas fa-play me-2');
    saveTimer();
    updateDisplay();
}

function resetTimer() {
    clearInterval(timerInterval);
    timerInterval = null;
    isRunning = false;
    endsAt = null;
    sessionId = null;
    remainingSeconds = totalSeconds;
    setStartBtn('Mulai', 'fas fa-play me-2');
    saveTimer();
    updateDisplay();
}

function skipTimer() {
    if (confirm('Lewati sesi timer saat ini?')) {
        resetTimer();
    }
}

function applyPomodoroResult(data, minutes) {
    showToast(data.message, 'success');
    if (Array.isArray(data.new_badges) && data.new_badges.length) setTimeout(() => showToast('Badge baru: ' + data.new_badges.join(', ') + '!', 'success'), 700);
    const hudXp = document.getElementById('hudXp');
    if (hudXp) hudXp.textContent = data.xp + ' XP';
    const hudLevel = document.getElementById('hudLevel');
    if (hudLevel) hudLevel.textContent = 'Lv. ' + data.level;
    const hudStreak = document.getElementById('hudStreak');
    if (hudStreak) hudStreak.textContent = data.streak;
    const sc = document.getElementById('todaySessionsCount');
    if (sc) sc.textContent = data.today_sessions;
    const mc = document.getElementById('todayMinutesCount');
    if (mc) mc.textContent = data.today_minutes;
    const list = document.getElementById('recentSessionsList');
    if (list) {
        const noTxt = document.getElementById('noSessionsText');
        if (noTxt) noTxt.remove();
        const now = new Date();
        const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
        const item = document.createElement('div');
        item.className = 'session-row p-2 px-3 d-flex justify-content-between align-items-center';
        const label = document.createElement('div');
        label.className = 'd-flex align-items-center gap-2 small';
        const icon = document.createElement('i');
        icon.className = 'fas fa-check-circle text-emerald';
        icon.setAttribute('aria-hidden', 'true');
        const txt = document.createElement('span');
        txt.textContent = minutes + ' Menit Selesai';
        label.appendChild(icon);
        label.appendChild(txt);
        const time = document.createElement('span');
        time.className = 'text-muted small';
        time.textContent = timeStr + ', Hari ini';
        item.appendChild(label);
        item.appendChild(time);
        list.insertBefore(item, list.firstChild);
    }
}

document.addEventListener('lt:pomodoro-synced', function(e) {
    if (e.detail && e.detail.data) applyPomodoroResult(e.detail.data, e.detail.minutes || 25);
});

function sessionCompleted() {
    if (sessionId && completedFor === sessionId) return;
    const store = readTimerStore();
    if (store && store.recorded && store.sessionId === sessionId) {
        completedFor = sessionId;
        applyFinishedMode(store.mode || timerMode);
        return;
    }
    completedFor = sessionId;
    const doneMode = timerMode;
    const doneMinutes = Math.round(totalSeconds / 60);
    const noteEl = document.getElementById('focusNote');
    const doneNote = noteEl ? noteEl.value.slice(0, 255) : '';
    try {
        localStorage.setItem(STORE_KEY, JSON.stringify({
            finished: true, recorded: true, sessionId: sessionId,
            mode: doneMode, totalSec: totalSeconds, ts: Date.now()
        }));
    } catch (e) {}
    sessionId = null;

    SoundEffects.pomodoroAlarm();

    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Pomodoro selesai', {
            body: doneMode === 'focus' ? 'Waktu fokus selesai. Istirahat sejenak.' : 'Istirahat selesai. Siap fokus kembali?'
        });
    }

    if (doneMode === 'focus') {
        // Record focus session via AJAX
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('duration', doneMinutes);
        formData.append('mode', doneMode);
        formData.append('focus_note', doneNote);

        fetch('record_pomodoro.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') applyPomodoroResult(data, doneMinutes);
        })
        .catch(() => {
            if (!navigator.onLine && window.LTOutbox) {
                window.LTOutbox.enqueue({ type: 'pomodoro', minutes: doneMinutes, csrf: CSRF_TOKEN });
                showToast('Offline. Sesi masuk antrean, terkirim saat online.', 'warning');
            } else {
                showToast('Sesi selesai, tetapi gagal tercatat. Refresh halaman.', 'warning');
            }
        });

        // Switch to Short Break automatically
        switchMode('shortBreak', 5);
    } else {
        showToast('Waktu istirahat selesai. Waktunya kembali fokus.', 'info');
        switchMode('focus', 25);
    }
}

function setCustomFocus() {
    const v = Math.max(5, Math.min(120, parseInt(document.getElementById('customMinutes').value, 10) || 25));
    document.getElementById('customMinutes').value = v;
    switchMode('focus', v);
    showToast('Fokus custom ' + v + ' menit siap.', 'info');
}

function applyFinishedMode(doneMode) {
    sessionId = null;
    endsAt = null;
    isRunning = false;
    if (doneMode === 'focus') {
        timerMode = 'shortBreak';
        totalSeconds = 5 * 60;
    } else {
        timerMode = 'focus';
        totalSeconds = 25 * 60;
    }
    remainingSeconds = totalSeconds;
    setModeUI();
    setStartBtn('Mulai', 'fas fa-play me-2');
    saveTimer();
    updateDisplay();
}

function switchMode(mode, minutes) {
    clearInterval(timerInterval);
    timerInterval = null;
    isRunning = false;
    endsAt = null;
    sessionId = null;
    timerMode = mode;
    totalSeconds = minutes * 60;
    remainingSeconds = totalSeconds;

    setModeUI();
    setStartBtn('Mulai', 'fas fa-play me-2');
    saveTimer();
    updateDisplay();
}

document.querySelectorAll('.timer-mode-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const mode = this.getAttribute('data-mode');
        const minutes = parseInt(this.getAttribute('data-time'), 10);
        switchMode(mode, minutes);
    });
});

function restoreTimer() {
    const s = readTimerStore();
    if (!s) { updateDisplay(); return; }
    if (s.finished) {
        if (s.recorded) {
            completedFor = s.sessionId || completedFor;
            applyFinishedMode(s.mode || 'focus');
        } else {
            sessionId = s.sessionId || null;
            timerMode = s.mode || 'focus';
            totalSeconds = s.totalSec || 25 * 60;
            remainingSeconds = 0;
            setModeUI();
            updateDisplay();
            sessionCompleted();
        }
        return;
    }
    timerMode = s.mode || 'focus';
    totalSeconds = s.totalSec || 25 * 60;
    sessionId = s.sessionId || null;
    const noteEl = document.getElementById('focusNote');
    if (noteEl && typeof s.note === 'string') noteEl.value = s.note;
    const customEl = document.getElementById('customMinutes');
    if (customEl && timerMode === 'focus' && s.customMin) customEl.value = s.customMin;
    setModeUI();
    if (s.running && s.endsAt) {
        const r = Math.max(0, Math.ceil((s.endsAt - Date.now()) / 1000));
        if (r <= 0) {
            remainingSeconds = 0;
            endsAt = null;
            isRunning = false;
            updateDisplay();
            sessionCompleted();
            return;
        }
        remainingSeconds = r;
        endsAt = s.endsAt;
        isRunning = true;
        setStartBtn('Jeda', 'fas fa-pause me-2');
        clearInterval(timerInterval);
        timerInterval = setInterval(tickTimer, 500);
    } else {
        remainingSeconds = (typeof s.remainingSec === 'number') ? Math.max(0, Math.min(s.remainingSec, totalSeconds)) : totalSeconds;
        endsAt = null;
        isRunning = false;
        setStartBtn(remainingSeconds < totalSeconds ? 'Lanjut' : 'Mulai', 'fas fa-play me-2');
    }
    updateDisplay();
}

restoreTimer();

window.addEventListener('storage', function(e) {
    if (e.key !== STORE_KEY || isRunning) return;
    restoreTimer();
});

['focusNote', 'customMinutes'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', () => { if (!isRunning) saveTimer(); });
});
</script>

<?php require_once 'includes/footer.php'; ?>
