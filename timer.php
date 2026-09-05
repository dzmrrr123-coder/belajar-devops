<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Get today's Pomodoro sessions
$stmt = $conn->prepare("
    SELECT COUNT(*) as today_sessions, COALESCE(SUM(duration_minutes), 0) as today_minutes 
    FROM pomodoro_sessions 
    WHERE user_id = ? AND DATE(completed_at) = CURDATE()
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

                <p class="text-secondary small text-center mb-0 mx-auto" style="max-width: 480px;">Selesaikan satu sesi fokus 25 menit untuk +10 XP.</p>
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
                                    <span><?= (int)$sess['duration_minutes'] ?> Menit Selesai</span>
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

let timerMode = 'focus';
let totalSeconds = 25 * 60;
let remainingSeconds = 25 * 60;
let timerInterval = null;
let isRunning = false;

const circle = document.getElementById('timerProgressCircle');
circle.style.strokeDasharray = `${CIRCUMFERENCE} ${CIRCUMFERENCE}`;
circle.style.strokeDashoffset = '0';

function setProgress(percent) {
    const offset = CIRCUMFERENCE - (percent / 100) * CIRCUMFERENCE;
    circle.style.strokeDashoffset = offset;
}

function updateDisplay() {
    const mins = Math.floor(remainingSeconds / 60);
    const secs = remainingSeconds % 60;
    const timeStr = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    document.getElementById('timerDisplay').textContent = timeStr;
    document.title = `(${timeStr}) Pomodoro - Learn Tracker`;

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

function startTimer() {
    if (isRunning) return;
    isRunning = true;
    document.getElementById('startBtnText').textContent = 'Jeda';
    document.getElementById('startBtn').querySelector('i').className = 'fas fa-pause me-2';

    // Request notification permission if needed
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    timerInterval = setInterval(() => {
        if (remainingSeconds > 0) {
            remainingSeconds--;
            updateDisplay();
        } else {
            clearInterval(timerInterval);
            isRunning = false;
            sessionCompleted();
        }
    }, 1000);
}

function pauseTimer() {
    clearInterval(timerInterval);
    isRunning = false;
    document.getElementById('startBtnText').textContent = 'Lanjut';
    document.getElementById('startBtn').querySelector('i').className = 'fas fa-play me-2';
}

function resetTimer() {
    clearInterval(timerInterval);
    isRunning = false;
    remainingSeconds = totalSeconds;
    document.getElementById('startBtnText').textContent = 'Mulai';
    document.getElementById('startBtn').querySelector('i').className = 'fas fa-play me-2';
    updateDisplay();
}

function skipTimer() {
    if (confirm('Lewati sesi timer saat ini?')) {
        resetTimer();
    }
}

function sessionCompleted() {
    SoundEffects.pomodoroAlarm();

    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('Pomodoro selesai', {
            body: timerMode === 'focus' ? 'Waktu fokus selesai. Istirahat sejenak.' : 'Istirahat selesai. Siap fokus kembali?'
        });
    }

    if (timerMode === 'focus') {
        // Record focus session via AJAX
        const formData = new FormData();
        formData.append('csrf_token', CSRF_TOKEN);
        formData.append('duration', Math.round(totalSeconds / 60));

        fetch('record_pomodoro.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast(data.message, 'success');
                // Update HUD
                const hudXp = document.getElementById('hudXp');
                if (hudXp) hudXp.textContent = data.xp + ' XP';

                const hudLevel = document.getElementById('hudLevel');
                if (hudLevel) hudLevel.textContent = 'Lv. ' + data.level;

                const hudStreak = document.getElementById('hudStreak');
                if (hudStreak) hudStreak.textContent = data.streak + ' Hari';

                // Update Session counters
                document.getElementById('todaySessionsCount').textContent = data.today_sessions;
                document.getElementById('todayMinutesCount').textContent = data.today_minutes;

                // Add to recent list
                const list = document.getElementById('recentSessionsList');
                const noTxt = document.getElementById('noSessionsText');
                if (noTxt) noTxt.remove();

                const now = new Date();
                const timeStr = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
                const item = document.createElement('div');
                item.className = 'session-row p-2 px-3 d-flex justify-content-between align-items-center';
                item.innerHTML = `
                    <div class="d-flex align-items-center gap-2 small">
                        <i class="fas fa-check-circle text-emerald"></i>
                        <span>${Math.round(totalSeconds / 60)} Menit Selesai</span>
                    </div>
                    <span class="text-muted small">${timeStr}, Hari ini</span>
                `;
                list.insertBefore(item, list.firstChild);
            }
        })
        .catch(() => showToast('Sesi selesai, tetapi gagal tercatat. Refresh halaman.', 'warning'));

        // Switch to Short Break automatically
        switchMode('shortBreak', 5);
    } else {
        showToast('Waktu istirahat selesai. Waktunya kembali fokus.', 'info');
        switchMode('focus', 25);
    }
}

function switchMode(mode, minutes) {
    timerMode = mode;
    totalSeconds = minutes * 60;
    remainingSeconds = totalSeconds;

    document.querySelectorAll('.timer-mode-btn').forEach(btn => {
        btn.classList.toggle('active', btn.getAttribute('data-mode') === mode);
    });

    let label = 'Fokus Belajar';
    if (mode === 'shortBreak') label = 'Istirahat Pendek';
    if (mode === 'longBreak') label = 'Istirahat Panjang';
    document.getElementById('timerStateLabel').textContent = label;

    resetTimer();
}

document.querySelectorAll('.timer-mode-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const mode = this.getAttribute('data-mode');
        const minutes = parseInt(this.getAttribute('data-time'), 10);
        switchMode(mode, minutes);
    });
});

updateDisplay();
</script>

<?php require_once 'includes/footer.php'; ?>
