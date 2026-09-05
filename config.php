<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

set_exception_handler(function(Throwable $e) {
    error_log("Uncaught Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Application Error - Learn Tracker</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #0b0f19; color: #cbd5e1; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .card { background-color: #131b2e; border: 1px solid #1e293b; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
            .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        </style>
    </head>
    <body class="p-3">
        <div class="container" style="max-width: 680px;">
            <div class="card p-4 p-md-5">
                <div class="d-flex align-items-center mb-4">
                    <span class="me-3 d-inline-flex align-items-center justify-content-center fw-bold text-white bg-danger rounded-circle flex-shrink-0" style="width:44px;height:44px;font-size:1.3rem;" aria-hidden="true">!</span>
                    <div>
                        <h2 class="h4 text-danger mb-1 fw-bold">Terjadi Kesalahan Aplikasi</h2>
                        <p class="text-secondary small mb-0">Learn Tracker &bull; Error Diagnostic</p>
                    </div>
                </div>
                <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger-emphasis mb-4">
                    <strong>Pesan Error:</strong><br>
                    <span class="font-mono small"><?= htmlspecialchars($e->getMessage()) ?></span>
                </div>
                <p class="small text-secondary mb-3">File: <code><?= htmlspecialchars(basename($e->getFile())) ?>:<?= $e->getLine() ?></code></p>
                <div class="text-center">
                    <a href="login.php" class="btn btn-outline-light btn-sm">Refresh Halaman</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
});

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        error_log("Fatal Error: " . print_r($err, true));
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo "<pre style='color:#f87171;background:#0f172a;padding:20px;border-radius:8px;font-family:monospace;'>FATAL ERROR: " . htmlspecialchars($err['message'] ?? '') . " in " . htmlspecialchars(basename($err['file'] ?? '')) . ":" . ($err['line'] ?? '') . "</pre>";
    }
});

// 1. Native .env loader if .env file exists
if (file_exists(__DIR__ . '/.env')) {
    $env_lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($env_key, $env_val) = explode('=', $line, 2);
            $env_key = trim($env_key);
            $env_val = trim($env_val, " \t\n\r\0\x0B\"'");
            if (getenv($env_key) === false) {
                putenv("$env_key=$env_val");
                $_ENV[$env_key] = $env_val;
                $_SERVER[$env_key] = $env_val;
            }
        }
    }
}

// 2. Set session save path if writable /tmp/sessions exists
if (is_dir('/tmp/sessions') && is_writable('/tmp/sessions')) {
    ini_set('session.save_path', '/tmp/sessions');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
}

// 3. Resolve Database configuration (Supports standard, Railway native, and URL connection strings)
$db_host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: '');
$db_port = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 0));
$db_user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: '');
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : (getenv('MYSQL_ROOT_PASSWORD') !== false ? getenv('MYSQL_ROOT_PASSWORD') : null));
$db_name = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: ''));

$db_url = getenv('MYSQL_URL') ?: (getenv('MYSQL_PRIVATE_URL') ?: getenv('DATABASE_URL'));
if ($db_url) {
    $parsed_url = parse_url($db_url);
    if ($parsed_url) {
        if (empty($db_host) && !empty($parsed_url['host'])) $db_host = $parsed_url['host'];
        if (empty($db_port) && !empty($parsed_url['port'])) $db_port = (int)$parsed_url['port'];
        if (empty($db_user) && !empty($parsed_url['user'])) $db_user = $parsed_url['user'];
        if ($db_pass === null && isset($parsed_url['pass'])) $db_pass = $parsed_url['pass'];
        if (empty($db_name) && !empty($parsed_url['path'])) $db_name = ltrim($parsed_url['path'], '/');
    }
}

// Fallback defaults
$db_host = $db_host ?: (getenv('RAILWAY_TCP_PROXY_DOMAIN') ?: 'localhost');
$db_port = $db_port ?: (int)(getenv('RAILWAY_TCP_PROXY_PORT') ?: 3306);
$db_user = $db_user ?: 'root';
$db_pass = $db_pass !== null ? $db_pass : '';
$db_name = $db_name ?: 'railway';

define('DB_HOST', $db_host);
define('DB_PORT', $db_port);
define('DB_USER', $db_user);
define('DB_PASS', $db_pass);
define('DB_NAME', $db_name);

// Auto-initialize schema & seed data safely without multi_query
function ensure_database_schema($conn) {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        $probe = $conn->query("SHOW TABLES LIKE 'users'");
        $has_users = $probe && $probe->num_rows > 0;
        if ($probe) $probe->free();
        if ($has_users) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            return;
        }
        // 1. Create users table
        $conn->query("CREATE TABLE IF NOT EXISTS `users` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(100) NOT NULL UNIQUE,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password` VARCHAR(255) NOT NULL,
            `xp` INT NOT NULL DEFAULT 0,
            `streak` INT NOT NULL DEFAULT 0,
            `last_active_date` DATE NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 2. Create quests table
        $conn->query("CREATE TABLE IF NOT EXISTS `quests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `week` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NOT NULL,
            `xp_reward` INT NOT NULL DEFAULT 10,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 3. Create user_quests table
        $conn->query("CREATE TABLE IF NOT EXISTS `user_quests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `quest_id` INT NOT NULL,
            `completed_at` DATE NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `uq_user_quest` UNIQUE (`user_id`, `quest_id`),
            CONSTRAINT `fk_user_quests_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_user_quests_quest` FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 4. Create errors table
        $conn->query("CREATE TABLE IF NOT EXISTS `errors` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `category` VARCHAR(50) NOT NULL DEFAULT 'General',
            `error_message` TEXT NOT NULL,
            `solution` TEXT NULL,
            `reference_link` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_errors_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 5. Create resources table
        $conn->query("CREATE TABLE IF NOT EXISTS `resources` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `week` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `type` VARCHAR(50) NOT NULL,
            `url` VARCHAR(500) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 6. Create pomodoro_sessions table
        $conn->query("CREATE TABLE IF NOT EXISTS `pomodoro_sessions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `duration_minutes` INT NOT NULL DEFAULT 25,
            `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_pomodoro_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // 7. Create questions table
        $conn->query("CREATE TABLE IF NOT EXISTS `questions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `quest_id` INT NULL,
            `title` VARCHAR(255) NOT NULL,
            `description` TEXT NULL,
            `topic` VARCHAR(100) NULL,
            `status` ENUM('open', 'in_review', 'answered', 'archived') NOT NULL DEFAULT 'open',
            `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
            `answer` TEXT NULL,
            `reference_link` VARCHAR(500) NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `answered_at` DATETIME NULL,
            CONSTRAINT `fk_questions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_questions_quest` FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        @$conn->query("CREATE INDEX idx_quests_week ON `quests` (`week`)");
        @$conn->query("CREATE INDEX idx_resources_week ON `resources` (`week`)");
        @$conn->query("CREATE INDEX idx_errors_user ON `errors` (`user_id`, `created_at`)");
        @$conn->query("CREATE INDEX idx_pomodoro_user ON `pomodoro_sessions` (`user_id`, `completed_at`)");
        @$conn->query("CREATE INDEX idx_questions_user ON `questions` (`user_id`, `status`, `created_at`)");

        // 8. Seed default quests and resources if quests table is empty
        $checkQuests = $conn->query("SELECT COUNT(*) AS total FROM `quests`");
        if ($checkQuests) {
            $row = $checkQuests->fetch_assoc();
            if ((int)($row['total'] ?? 0) === 0) {
                $seedFile = __DIR__ . '/database.sql';
                if (file_exists($seedFile)) {
                    $seedSql = file_get_contents($seedFile);
                    if (preg_match('/(INSERT INTO `quests`[\s\S]+?;)/i', $seedSql, $mQuests)) {
                        @$conn->query($mQuests[1]);
                    }
                    if (preg_match('/(INSERT INTO `resources`[\s\S]+?;)/i', $seedSql, $mRes)) {
                        @$conn->query($mRes[1]);
                    }
                }
            }
        }
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    } catch (Throwable $e) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        error_log("Schema auto-init: " . $e->getMessage() . " | DB error: " . ($conn->error ?? ''));
    }

    $missing = [];
    foreach (['users','quests','user_quests','errors','resources','pomodoro_sessions','questions'] as $t) {
        try {
            $chk = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($t) . "'");
            if (!$chk || $chk->num_rows === 0) $missing[] = $t;
            if ($chk) $chk->free();
        } catch (Throwable $t2) {
            $missing[] = $t;
        }
    }
    if ($missing) {
        throw new Exception("Inisialisasi database gagal, tabel belum ada: " . implode(', ', $missing) . ". Info DB: " . ($conn->error ?: 'user DB mungkin tanpa hak CREATE, atau versi server menolak sintaks') . ". Solusi cepat: import schema.sql manual via tab Query di dashboard MySQL Railway.");
    }
}

// Render friendly database diagnosis page
function render_db_error_page($error_msg, $host, $port, $user, $db) {
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Database Connection Issue - Learn Tracker</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body { background-color: #0b0f19; color: #cbd5e1; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
            .card { background-color: #131b2e; border: 1px solid #1e293b; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
            .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        </style>
    </head>
    <body class="p-3">
        <div class="container" style="max-width: 680px;">
            <div class="card p-4 p-md-5">
                <div class="d-flex align-items-center mb-4">
                    <span class="me-3 d-inline-flex align-items-center justify-content-center fw-bold text-white bg-danger rounded-circle flex-shrink-0" style="width:44px;height:44px;font-size:1.3rem;" aria-hidden="true">!</span>
                    <div>
                        <h2 class="h4 text-danger mb-1 fw-bold">Koneksi Database Belum Terhubung</h2>
                        <p class="text-secondary small mb-0">Learn Tracker &bull; Railway DevOps Diagnostic</p>
                    </div>
                </div>

                <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 text-danger-emphasis mb-4">
                    <strong>Detail Error:</strong><br>
                    <span class="font-mono small"><?= htmlspecialchars($error_msg) ?></span>
                </div>

                <h6 class="fw-bold text-white mb-2">Parameter Koneksi yang Terdeteksi:</h6>
                <div class="bg-black bg-opacity-50 p-3 rounded-3 mb-4 border border-secondary border-opacity-25 small font-mono">
                    <div>DB_HOST : <span class="text-warning"><?= htmlspecialchars($host) ?></span></div>
                    <div>DB_PORT : <span class="text-warning"><?= htmlspecialchars((string)$port) ?></span></div>
                    <div>DB_USER : <span class="text-warning"><?= htmlspecialchars($user) ?></span></div>
                    <div>DB_NAME : <span class="text-warning"><?= htmlspecialchars($db) ?></span></div>
                </div>

                <?php if ($host === 'localhost'): ?>
                <div class="p-3 rounded-3 border border-warning bg-warning bg-opacity-10 mb-3">
                    <h6 class="text-warning fw-bold mb-2">Cara Mengatasi di Railway:</h6>
                    <p class="small mb-2 text-white">Nilai <code>DB_HOST</code> masih <code>localhost</code> karena variabel environment belum dimasukkan ke <strong>Web Service</strong>.</p>
                    <ol class="small mb-0 ps-3 text-secondary">
                        <li>Buka dashboard Railway &gt; klik service web <strong>belajar-devops</strong>.</li>
                        <li>Pilih tab <strong>Variables</strong>.</li>
                        <li>Tambahkan variabel environment MySQL Anda (<code>MYSQLHOST</code>, <code>MYSQLPORT</code>, <code>MYSQLUSER</code>, <code>MYSQLPASSWORD</code>, <code>MYSQLDATABASE</code>).</li>
                        <li>Atau klik <strong>New Variable</strong> &gt; <strong>Add Reference</strong> &gt; pilih service MySQL Anda.</li>
                    </ol>
                </div>
                <?php else: ?>
                <div class="p-3 rounded-3 border border-info bg-info bg-opacity-10 mb-3">
                    <h6 class="text-info fw-bold mb-2">Cara Mengatasi:</h6>
                    <p class="small mb-2 text-white">Aplikasi mencoba terhubung ke <code><?= htmlspecialchars($host) ?>:<?= htmlspecialchars((string)$port) ?></code> namun tidak merespons.</p>
                    <ul class="small mb-0 ps-3 text-secondary">
                        <li>Pastikan service Web dan MySQL berada di <strong>Project & Environment yang sama</strong> di Railway.</li>
                        <li>Jika menggunakan host internal, pastikan nama host sesuai (misal: <code>mysql.railway.internal</code>).</li>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="mt-4 text-center">
                    <a href="login.php" class="btn btn-outline-light btn-sm">Refresh Halaman</a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}

// Connect database (one shared connection per request)
function db_connect() {
    static $shared = null;
    if ($shared instanceof mysqli) {
        try {
            if (@$shared->ping()) return $shared;
        } catch (Throwable $e) {}
        $shared = null;
    }
    $conn = mysqli_init();
    if (!$conn) {
        throw new Exception("Gagal menginisialisasi MySQLi driver.");
    }

    // Set 5 detik connection timeout agar tidak hanging
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

    try {
        $connected = @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        if (!$connected || $conn->connect_error) {
            throw new Exception($conn->connect_error ?: "Gagal terhubung ke MySQL pada " . DB_HOST . ":" . DB_PORT);
        }
        $conn->set_charset("utf8mb4");
        ensure_database_schema($conn);
        $shared = $conn;
        return $conn;
    } catch (Throwable $e) {
        http_response_code(500);
        render_db_error_page($e->getMessage(), DB_HOST, DB_PORT, DB_USER, DB_NAME);
        exit();
    }
}

// Helper: redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

function clean($data) {
    if ($data === null) return '';
    if (is_array($data)) return '';
    $data = trim((string)$data);
    $data = stripslashes($data);
    return mb_substr($data, 0, 5000);
}

function esc($data) {
    return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
}

function valid_url($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (!preg_match('#^https?://#i', $url)) return '';
    if (strlen($url) > 500) return '';
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

// Level calculation: level = floor(sqrt(xp / 100)) + 1
function calculate_level($xp) {
    $xp = max(0, (int)$xp);
    return (int)(floor(sqrt($xp / 100)) + 1);
}

// Base XP for a given level
function level_base_xp($level) {
    $level = max(1, (int)$level);
    return ($level - 1) * ($level - 1) * 100;
}

// XP needed to reach next level
function xp_to_next_level($xp) {
    $current_level = calculate_level($xp);
    return ($current_level * $current_level * 100);
}

// Level progress percentage (0 - 100%)
function level_progress_percent($xp) {
    $xp = max(0, (int)$xp);
    $level = calculate_level($xp);
    $base = level_base_xp($level);
    $next = xp_to_next_level($xp);
    $range = $next - $base;
    if ($range <= 0) return 100;
    $progress = $xp - $base;
    return min(100, max(0, round(($progress / $range) * 100)));
}

// Gamification Rank title based on level
function get_user_rank($level) {
    $ranks = [
        1 => 'Terminal Cadet',
        2 => 'Junior Scripter',
        3 => 'Git Wrangler',
        4 => 'Backend Craftsman',
        5 => 'Docker Apprentice',
        6 => 'Container Captain',
        7 => 'Cloud Pioneer',
        8 => 'DevOps Specialist',
        9 => 'CI/CD Architect',
        10 => 'Site Reliability Engineer',
        11 => 'Cloud Guru',
        12 => 'DevOps Legend'
    ];
    return $ranks[min(12, max(1, (int)$level))] ?? 'DevOps Grandmaster';
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login
function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk melanjutkan.');
        redirect('login.php');
    }
}

// Daily streak updater
function update_user_streak($conn, $user_id) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $stmt = $conn->prepare("SELECT streak, last_active_date FROM users WHERE id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) return 0;

    $last_active = $res['last_active_date'];
    $streak = (int)$res['streak'];

    if ($last_active === $today) {
        return $streak;
    } elseif ($last_active === $yesterday) {
        $streak++;
    } else {
        $streak = 1;
    }

    $stmt = $conn->prepare("UPDATE users SET streak = ?, last_active_date = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("isi", $streak, $today, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    return $streak;
}

// Flash messages
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// CSRF tokens
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die("Error 403: Invalid CSRF Token request.");
        }
    }
}
