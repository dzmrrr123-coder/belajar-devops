<?php
// Learn Tracker Configuration & Core Helpers

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
            if (!getenv($env_key)) {
                putenv("$env_key=$env_val");
                $_ENV[$env_key] = $env_val;
                $_SERVER[$env_key] = $env_val;
            }
        }
    }
}

// 2. Set session save path on serverless environment (Vercel)
if (getenv('VERCEL') && !is_dir('/tmp/sessions')) {
    @mkdir('/tmp/sessions', 0777, true);
    ini_set('session.save_path', '/tmp/sessions');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Support Railway MYSQL_URL if provided
if (getenv('MYSQL_URL') && !getenv('DB_HOST') && !getenv('MYSQLHOST')) {
    $parsed_url = parse_url(getenv('MYSQL_URL'));
    if ($parsed_url) {
        if (isset($parsed_url['host'])) putenv("DB_HOST=" . $parsed_url['host']);
        if (isset($parsed_url['port'])) putenv("DB_PORT=" . $parsed_url['port']);
        if (isset($parsed_url['user'])) putenv("DB_USER=" . $parsed_url['user']);
        if (isset($parsed_url['pass'])) putenv("DB_PASS=" . $parsed_url['pass']);
        if (isset($parsed_url['path'])) putenv("DB_NAME=" . ltrim($parsed_url['path'], '/'));
    }
}

// 4. Resolve Database configuration (Supports standard and Railway native variables)
$resolved_host = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'localhost');
$resolved_port = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: 3306));
$resolved_user = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$resolved_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : (getenv('MYSQL_ROOT_PASSWORD') !== false ? getenv('MYSQL_ROOT_PASSWORD') : ''));
$resolved_name = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: (getenv('MYSQL_DATABASE') ?: 'learn-tracker'));

define('DB_HOST', $resolved_host);
define('DB_PORT', $resolved_port);
define('DB_USER', $resolved_user);
define('DB_PASS', $resolved_pass);
define('DB_NAME', $resolved_name);

// Auto-initialize schema & seed data if tables do not exist
function ensure_database_schema($conn) {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    $check = $conn->query("SHOW TABLES LIKE 'users'");
    if ($check && $check->num_rows === 0) {
        $schemaFile = __DIR__ . '/schema.sql';
        if (file_exists($schemaFile)) {
            $schemaSql = file_get_contents($schemaFile);
            if ($conn->multi_query($schemaSql)) {
                do {
                    if ($res = $conn->store_result()) {
                        $res->free();
                    }
                } while ($conn->more_results() && $conn->next_result());
            }
        }

        $seedFile = __DIR__ . '/database.sql';
        if (file_exists($seedFile)) {
            $seedSql = file_get_contents($seedFile);
            if (preg_match('/(INSERT INTO `quests`[\s\S]+?;)/i', $seedSql, $mQuests)) {
                $conn->query($mQuests[1]);
            }
            if (preg_match('/(INSERT INTO `resources`[\s\S]+?;)/i', $seedSql, $mRes)) {
                $conn->query($mRes[1]);
            }
        }
    }
}

// Connect database
function db_connect() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        die("Database connection failed: " . htmlspecialchars($conn->connect_error));
    }
    $conn->set_charset("utf8mb4");
    ensure_database_schema($conn);
    return $conn;
}

// Helper: redirect
function redirect($url) {
    header("Location: $url");
    exit();
}

// Helper: sanitize input
function clean($data) {
    if ($data === null) return '';
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
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
    $stmt->bind_param("isi", $streak, $today, $user_id);
    $stmt->execute();
    $stmt->close();

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
