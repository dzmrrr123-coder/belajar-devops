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

define('SCHEMA_VERSION', 25);

// Auto-initialize schema & seed data safely without multi_query
function ensure_database_schema($conn) {
    static $initialized = false;
    if ($initialized) return;
    $initialized = true;

    try {
        mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $ver = $conn->query("SELECT `v` FROM `schema_meta` WHERE `k` = 'version' LIMIT 1");
            if ($ver && ($row = $ver->fetch_assoc()) && (int)$row['v'] >= SCHEMA_VERSION) {
                $ver->free();
                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                return;
            }
            if ($ver) $ver->free();
        } catch (Throwable $e) {}
        // Additive migrations run on every connect so existing DBs also upgrade.
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

        $conn->query("CREATE TABLE IF NOT EXISTS `remember_tokens` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `selector` VARCHAR(24) NOT NULL UNIQUE,
            `validator_hash` VARCHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `role` VARCHAR(16) NOT NULL DEFAULT 'user'");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `last_login_at` DATETIME NULL");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `freeze_tokens` INT NOT NULL DEFAULT 1");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `best_streak` INT NOT NULL DEFAULT 0");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `show_on_board` TINYINT NOT NULL DEFAULT 0");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `public_profile` TINYINT NOT NULL DEFAULT 0");
        @$conn->query("CREATE TABLE IF NOT EXISTS `user_badges` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `slug` VARCHAR(64) NOT NULL,
            `unlocked_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `uq_user_badge` UNIQUE (`user_id`, `slug`),
            CONSTRAINT `fk_user_badges_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE INDEX idx_user_badges_user ON `user_badges` (`user_id`)");
        @$conn->query("CREATE TABLE IF NOT EXISTS `xp_events` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `amount` INT NOT NULL,
            `reason` VARCHAR(64) NOT NULL DEFAULT 'other',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_xp_events_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE INDEX idx_xp_events_user ON `xp_events` (`user_id`, `created_at`)");
        @$conn->query("ALTER TABLE `xp_events` ADD COLUMN `ref_type` VARCHAR(32) NULL");
        @$conn->query("ALTER TABLE `xp_events` ADD COLUMN `ref_id` INT NULL");
        @$conn->query("CREATE INDEX idx_xp_events_ref ON `xp_events` (`user_id`, `ref_type`, `ref_id`)");
        @$conn->query("ALTER TABLE `quests` ADD COLUMN `user_id` INT NULL");
        @$conn->query("ALTER TABLE `quests` ADD COLUMN `is_custom` TINYINT NOT NULL DEFAULT 0");
        @$conn->query("CREATE TABLE IF NOT EXISTS `daily_missions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `mission_date` DATE NOT NULL,
            `mission_key` VARCHAR(32) NOT NULL,
            `claimed_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `uq_daily_mission` UNIQUE (`user_id`, `mission_date`, `mission_key`),
            CONSTRAINT `fk_daily_missions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE TABLE IF NOT EXISTS `quest_subtasks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `quest_id` INT NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `done_at` DATETIME NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_subtasks_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_subtasks_quest` FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("ALTER TABLE `pomodoro_sessions` ADD COLUMN `mode` VARCHAR(16) NOT NULL DEFAULT 'focus'");
        @$conn->query("ALTER TABLE `pomodoro_sessions` ADD COLUMN `focus_note` VARCHAR(255) NULL");
        @$conn->query("ALTER TABLE `questions` ADD COLUMN `linked_error_id` INT NULL");
        @$conn->query("ALTER TABLE `questions` ADD CONSTRAINT `fk_questions_error` FOREIGN KEY (`linked_error_id`) REFERENCES `errors` (`id`) ON DELETE SET NULL");
        @$conn->query("CREATE TABLE IF NOT EXISTS `reviews` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `source` VARCHAR(16) NOT NULL DEFAULT 'quest',
            `source_id` INT NOT NULL DEFAULT 0,
            `title` VARCHAR(255) NOT NULL,
            `detail` TEXT NULL,
            `next_due` DATE NOT NULL,
            `interval_day` INT NOT NULL DEFAULT 1,
            `done_count` INT NOT NULL DEFAULT 0,
            `lapses` INT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT `uq_review` UNIQUE (`user_id`, `source`, `source_id`),
            CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE INDEX idx_reviews_due ON `reviews` (`user_id`, `next_due`)");
        @$conn->query("CREATE INDEX idx_quests_week ON `quests` (`week`)");
        @$conn->query("CREATE INDEX idx_quests_user ON `quests` (`user_id`)");
        @$conn->query("CREATE INDEX idx_daily_missions_user ON `daily_missions` (`user_id`, `mission_date`)");
        @$conn->query("CREATE INDEX idx_subtasks_quest ON `quest_subtasks` (`user_id`, `quest_id`)");
        @$conn->query("CREATE INDEX idx_resources_week ON `resources` (`week`)");
        @$conn->query("CREATE INDEX idx_quests_week_user ON `quests` (`week`, `user_id`)");
        @$conn->query("CREATE INDEX idx_users_board ON `users` (`show_on_board`, `xp`)");
        @$conn->query("CREATE INDEX idx_errors_user ON `errors` (`user_id`, `created_at`)");
        @$conn->query("CREATE INDEX idx_pomodoro_user ON `pomodoro_sessions` (`user_id`, `completed_at`)");
        @$conn->query("CREATE INDEX idx_questions_user ON `questions` (`user_id`, `status`, `created_at`)");
        @$conn->query("CREATE TABLE IF NOT EXISTS `quiz_cards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `source` VARCHAR(16) NOT NULL DEFAULT 'error',
            `source_id` INT NOT NULL DEFAULT 0,
            `question` VARCHAR(255) NOT NULL,
            `answer` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `uq_quiz_card` UNIQUE (`user_id`, `source`, `source_id`),
            CONSTRAINT `fk_quiz_cards_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE INDEX idx_quiz_cards_user ON `quiz_cards` (`user_id`)");
        @$conn->query("CREATE TABLE IF NOT EXISTS `daily_chests` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `chest_date` DATE NOT NULL,
            `xp` INT NOT NULL DEFAULT 0,
            `freeze` TINYINT NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `uq_daily_chest` UNIQUE (`user_id`, `chest_date`),
            CONSTRAINT `fk_daily_chests_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `flair` VARCHAR(24) NULL DEFAULT NULL");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `avatar_frame` VARCHAR(16) NOT NULL DEFAULT 'default'");

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
        @$conn->query("CREATE TABLE IF NOT EXISTS `schema_meta` (`k` VARCHAR(64) PRIMARY KEY, `v` VARCHAR(64) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("INSERT INTO `schema_meta` (`k`, `v`) VALUES ('version', '" . SCHEMA_VERSION . "') ON DUPLICATE KEY UPDATE `v` = '" . SCHEMA_VERSION . "'");
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    } catch (Throwable $e) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        error_log("Schema auto-init: " . $e->getMessage() . " | DB error: " . ($conn->error ?? ''));
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

// Admin dikunci ke satu akun pemilik. Perbandingan live dari DB agar
// perubahan email/role langsung berlaku tanpa perlu logout.
define('OWNER_ADMIN_EMAIL', 'dzmrrr123@gmail.com');
function is_admin($conn, $user_id) {
    try {
        $s = $conn->prepare("SELECT email FROM users WHERE id = ?");
        if (!$s) return false;
        $s->bind_param("i", $user_id);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        return strtolower(trim((string)($r['email'] ?? ''))) === OWNER_ADMIN_EMAIL;
    } catch (Throwable $e) { return false; }
}

// Gate for private admin pages: 404 (not 403) so the URL stays undiscoverable
function require_admin($conn) {
    if (!is_logged_in() || !is_admin($conn, (int)($_SESSION['user_id'] ?? 0))) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit();
    }
}

// Require login
function require_login() {
    if (!is_logged_in()) {
        set_flash('warning', 'Silakan login terlebih dahulu untuk melanjutkan.');
        redirect('login.php');
    }
}

// Daily streak updater (dengan freeze token: absen 1 hari tidak reset jika token tersedia)
function update_user_streak($conn, $user_id) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $two_ago = date('Y-m-d', strtotime('-2 days'));

    $stmt = $conn->prepare("SELECT streak, last_active_date, freeze_tokens, best_streak FROM users WHERE id = ?");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$res) return 0;

    $last_active = $res['last_active_date'];
    $streak = (int)$res['streak'];
    $tokens = (int)($res['freeze_tokens'] ?? 1);
    $best = (int)($res['best_streak'] ?? 0);
    if ($last_active === $today) {
        if ($streak > $best) {
            $up = $conn->prepare("UPDATE users SET best_streak = ? WHERE id = ?");
            if ($up) { $up->bind_param("ii", $streak, $user_id); $up->execute(); $up->close(); }
        }
        return $streak;
    }

    $used_freeze = false;
    if ($last_active === $yesterday) {
        $streak++;
    } elseif ($last_active === $two_ago && $tokens > 0) {
        $streak++;
        $tokens--;
        $used_freeze = true;
    } else {
        $streak = 1;
    }
    if (date('W') !== date('W', strtotime($last_active ?: $today))) $tokens = min(2, $tokens + 1);

    $best = max($best, $streak);
    $stmt = $conn->prepare("UPDATE users SET streak = ?, last_active_date = ?, freeze_tokens = ?, best_streak = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("isiii", $streak, $today, $tokens, $best, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    if ($used_freeze && session_status() === PHP_SESSION_ACTIVE) set_flash('info', 'Streak Freeze dipakai! Streak-mu terselamatkan.');
    return $streak;
}

function badge_defs() {
    return [
        'first-quest' => ['name' => 'Langkah Pertama', 'desc' => 'Selesaikan 1 quest', 'icon' => 'fa-flag'],
        'quest-5' => ['name' => 'Quest Hunter 5', 'desc' => 'Selesaikan 5 quest', 'icon' => 'fa-map'],
        'quest-10' => ['name' => 'Quest Hunter 10', 'desc' => 'Selesaikan 10 quest', 'icon' => 'fa-map-location-dot'],
        'quest-all' => ['name' => 'Roadmap Tuntas', 'desc' => 'Selesaikan 14 quest', 'icon' => 'fa-crown'],
        'focus-1' => ['name' => 'Fokus Perdana', 'desc' => '1 sesi fokus', 'icon' => 'fa-clock'],
        'focus-25' => ['name' => 'Deep Worker', 'desc' => '25 sesi fokus', 'icon' => 'fa-brain'],
        'note-1' => ['name' => 'Bug Reporter', 'desc' => 'Tulis 1 catatan', 'icon' => 'fa-note-sticky'],
        'note-25' => ['name' => 'Bug Hunter', 'desc' => '25 catatan error/tanya', 'icon' => 'fa-bug'],
        'streak-7' => ['name' => 'Konsisten 7 Hari', 'desc' => 'Streak 7 hari', 'icon' => 'fa-fire'],
        'streak-30' => ['name' => 'Unstoppable 30', 'desc' => 'Streak 30 hari', 'icon' => 'fa-volcano'],
        'review-10' => ['name' => 'Reviewer', 'desc' => '10 review Tahu', 'icon' => 'fa-rotate-right'],
        'custom-1' => ['name' => 'Inisiatif', 'desc' => 'Buat 1 quest custom', 'icon' => 'fa-plus'],
    ];
}

function user_badges($conn, $user_id) {
    $out = [];
    try {
        $q = $conn->prepare("SELECT slug, unlocked_at FROM user_badges WHERE user_id = ? ORDER BY unlocked_at ASC");
        $q->bind_param("i", $user_id); $q->execute();
        foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $out[$r['slug']] = $r;
        $q->close();
    } catch (Throwable $e) {}
    return $out;
}

function check_and_unlock_badges($conn, $user_id) {
    $defs = badge_defs();
    $owned = user_badges($conn, $user_id);
    $cat_of = [
        'first-quest' => 'quest', 'quest-5' => 'quest', 'quest-10' => 'quest', 'quest-all' => 'quest',
        'focus-1' => 'focus', 'focus-25' => 'focus',
        'note-1' => 'note', 'note-25' => 'note',
        'streak-7' => 'streak', 'streak-30' => 'streak',
        'review-10' => 'review', 'custom-1' => 'custom',
    ];
    $need = [];
    foreach ($cat_of as $slug => $cat) {
        if (!isset($owned[$slug]) && isset($defs[$slug])) $need[$cat] = true;
    }
    if (empty($need)) return [];
    $c = ['quest' => 0, 'focus' => 0, 'note' => 0, 'streak' => 0, 'review' => 0, 'custom' => 0];
    try {
        if (isset($need['quest'])) {
            $q = $conn->prepare("SELECT COUNT(*) n FROM user_quests WHERE user_id = ?");
            $q->bind_param("i", $user_id); $q->execute(); $c['quest'] = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        }
        if (isset($need['focus'])) {
            $q = $conn->prepare("SELECT COUNT(*) n FROM pomodoro_sessions WHERE user_id = ? AND mode = 'focus'");
            $q->bind_param("i", $user_id); $q->execute(); $c['focus'] = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        }
        if (isset($need['note'])) {
            $q = $conn->prepare("SELECT (SELECT COUNT(*) FROM errors WHERE user_id = ?) + (SELECT COUNT(*) FROM questions WHERE user_id = ?) n");
            $q->bind_param("ii", $user_id, $user_id); $q->execute(); $c['note'] = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        }
        if (isset($need['streak'])) {
            $q = $conn->prepare("SELECT streak FROM users WHERE id = ?");
            $q->bind_param("i", $user_id); $q->execute();
            $u = $q->get_result()->fetch_assoc(); $q->close();
            $c['streak'] = (int)($u['streak'] ?? 0);
        }
        if (isset($need['review'])) {
            try {
                $q = $conn->prepare("SELECT COALESCE(SUM(done_count),0) n FROM reviews WHERE user_id = ?");
                $q->bind_param("i", $user_id); $q->execute(); $c['review'] = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
            } catch (Throwable $e) {}
        }
        if (isset($need['custom'])) {
            $q = $conn->prepare("SELECT COUNT(*) n FROM quests WHERE user_id = ? AND is_custom = 1");
            $q->bind_param("i", $user_id); $q->execute(); $c['custom'] = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        }
    } catch (Throwable $e) { return []; }
    $rules = [
        'first-quest' => $c['quest'] >= 1, 'quest-5' => $c['quest'] >= 5, 'quest-10' => $c['quest'] >= 10, 'quest-all' => $c['quest'] >= 14,
        'focus-1' => $c['focus'] >= 1, 'focus-25' => $c['focus'] >= 25,
        'note-1' => $c['note'] >= 1, 'note-25' => $c['note'] >= 25,
        'streak-7' => $c['streak'] >= 7, 'streak-30' => $c['streak'] >= 30,
        'review-10' => $c['review'] >= 10, 'custom-1' => $c['custom'] >= 1,
    ];
    $new = [];
    foreach ($rules as $slug => $ok) {
        if ($ok && !isset($owned[$slug]) && isset($defs[$slug])) {
            try {
                $ins = $conn->prepare("INSERT IGNORE INTO user_badges (user_id, slug) VALUES (?, ?)");
                $ins->bind_param("is", $user_id, $slug);
                if ($ins->execute() && $ins->affected_rows > 0) $new[] = $slug;
                $ins->close();
            } catch (Throwable $e) {}
        }
    }
    return $new;
}

function mission_multiplier($conn, $user_id) {
    try {
        $s = get_daily_mission_status($conn, $user_id);
        foreach ($s as $m) if (empty($m['done'])) return 1.0;
        return 1.5;
    } catch (Throwable $e) { return 1.0; }
}

function apply_xp_multiplier($base, $mult) {
    return (int)ceil($base * $mult);
}

define('NOTE_DAILY_XP_CAP', 25);

function capped_xp_gain($wanted, $today_sum, $cap) {
    $left = max(0, (int)$cap - (int)$today_sum);
    if ($left <= 0) return 0;
    return min(max(0, (int)$wanted), $left);
}

function daily_reason_xp($conn, $user_id, $reason) {
    try {
        $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND reason = ? AND amount > 0 AND created_at >= CURDATE() AND created_at < CURDATE() + INTERVAL 1 DAY");
        $q->bind_param("is", $user_id, $reason); $q->execute();
        $n = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        return max(0, $n);
    } catch (Throwable $e) { return 0; }
}

function xp_events_has_ref($conn) {
    static $cached = null;
    if ($cached !== null) return $cached;
    try {
        $chk = $conn->query("SHOW COLUMNS FROM `xp_events` LIKE 'ref_type'");
        $cached = ($chk && $chk->num_rows > 0);
        if ($chk) $chk->free();
    } catch (Throwable $e) { $cached = false; }
    return $cached;
}

function award_xp($conn, $user_id, $amount, $reason = 'other', $ref_type = null, $ref_id = null) {
    $amount = (int)$amount;
    if ($amount === 0) return;
    $stmt = $conn->prepare("UPDATE users SET xp = GREATEST(0, xp + ?) WHERE id = ?");
    $stmt->bind_param("ii", $amount, $user_id);
    $stmt->execute();
    $stmt->close();
    try {
        if (xp_events_has_ref($conn)) {
            $log = $conn->prepare("INSERT INTO xp_events (user_id, amount, reason, ref_type, ref_id) VALUES (?, ?, ?, ?, ?)");
            $log->bind_param("iissi", $user_id, $amount, $reason, $ref_type, $ref_id);
            $log->execute();
            $log->close();
        } else {
            $log = $conn->prepare("INSERT INTO xp_events (user_id, amount, reason) VALUES (?, ?, ?)");
            $log->bind_param("iis", $user_id, $amount, $reason);
            $log->execute();
            $log->close();
        }
    } catch (Throwable $e) {}
}

function xp_ledger_sum($conn, $user_id) {
    try {
        $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ?");
        $q->bind_param("i", $user_id); $q->execute();
        $n = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        return max(0, $n);
    } catch (Throwable $e) { return 0; }
}

function sync_user_xp($conn, $user_id) {
    try {
        $sum = xp_ledger_sum($conn, $user_id);
        $cur = 0;
        $q = $conn->prepare("SELECT xp FROM users WHERE id = ?");
        $q->bind_param("i", $user_id); $q->execute();
        $cur = (int)($q->get_result()->fetch_assoc()['xp'] ?? 0); $q->close();
        if ($sum < $cur) {
            $diff = $cur - $sum;
            try {
                $b = $conn->prepare("INSERT INTO xp_events (user_id, amount, reason) VALUES (?, ?, 'backfill')");
                $b->bind_param("ii", $user_id, $diff);
                $b->execute(); $b->close();
            } catch (Throwable $e) {}
            return $cur;
        }
        $up = $conn->prepare("UPDATE users SET xp = ? WHERE id = ?");
        $up->bind_param("ii", $sum, $user_id);
        $up->execute(); $up->close();
        return $sum;
    } catch (Throwable $e) { return 0; }
}

function awarded_for_ref($conn, $user_id, $ref_type, $ref_id) {
    try {
        $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND ref_type = ? AND ref_id = ? AND amount > 0");
        $ref_id = (int)$ref_id;
        $q->bind_param("isi", $user_id, $ref_type, $ref_id);
        $q->execute();
        $n = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        return $n;
    } catch (Throwable $e) { return 0; }
}

function weekly_xp($conn, $user_id) {
    try {
        $q = $conn->prepare("SELECT COALESCE(SUM(amount),0) n FROM xp_events WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $q->bind_param("i", $user_id); $q->execute();
        $n = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
        return max(0, $n);
    } catch (Throwable $e) { return 0; }
}

function daily_mission_defs() {
    return [
        'quest1' => ['label' => 'Selesaikan 1 quest', 'xp' => 5, 'icon' => 'fa-map'],
        'focus1' => ['label' => '1 sesi fokus', 'xp' => 5, 'icon' => 'fa-clock'],
        'note1' => ['label' => 'Tulis 1 catatan', 'xp' => 5, 'icon' => 'fa-note-sticky'],
    ];
}

function get_daily_mission_status($conn, $user_id) {
    $defs = daily_mission_defs();
    $out = [];
    foreach ($defs as $k => $d) $out[$k] = ['done' => false, 'claimed' => false] + $d;
    try {
        $q = $conn->prepare("SELECT (SELECT COUNT(*) FROM user_quests WHERE user_id=? AND completed_at=CURDATE()) AS qc, (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id=? AND completed_at>=CURDATE() AND completed_at<CURDATE() + INTERVAL 1 DAY) AS fc, (SELECT COUNT(*) FROM errors WHERE user_id=? AND created_at>=CURDATE() AND created_at<CURDATE() + INTERVAL 1 DAY) + (SELECT COUNT(*) FROM questions WHERE user_id=? AND created_at>=CURDATE() AND created_at<CURDATE() + INTERVAL 1 DAY) AS nc, (SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='quest1' AND claimed_at IS NOT NULL) AS c_quest1, (SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='focus1' AND claimed_at IS NOT NULL) AS c_focus1, (SELECT COUNT(*) FROM daily_missions WHERE user_id=? AND mission_date=CURDATE() AND mission_key='note1' AND claimed_at IS NOT NULL) AS c_note1");
        $q->bind_param("iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id); $q->execute();
        $mc = $q->get_result()->fetch_assoc() ?: [];
        $q->close();
        $out['quest1']['done'] = ((int)($mc['qc'] ?? 0)) > 0;
        $out['focus1']['done'] = ((int)($mc['fc'] ?? 0)) > 0;
        $out['note1']['done'] = ((int)($mc['nc'] ?? 0)) > 0;
        foreach (['quest1' => 'c_quest1', 'focus1' => 'c_focus1', 'note1' => 'c_note1'] as $k => $ck) {
            if (((int)($mc[$ck] ?? 0)) > 0) $out[$k]['claimed'] = true;
        }
    } catch (Throwable $e) {}
    return $out;
}

function quest_visible_where() {
    return "(q.user_id IS NULL OR q.user_id = ?)";
}

function delete_review($conn, $user_id, $source, $source_id) {
    try {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE user_id = ? AND source = ? AND source_id = ?");
        $stmt->bind_param("isi", $user_id, $source, $source_id);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {}
}

function review_next_interval($current) {
    foreach ([1, 3, 7, 14, 30] as $step) if ($current < $step) return $step;
    return 30;
}

function schedule_review($conn, $user_id, $source, $source_id, $title, $detail = '') {
    try {
        $title = mb_substr(trim((string)$title) ?: 'Review', 0, 255);
        $detail = mb_substr((string)$detail, 0, 2000);
        $stmt = $conn->prepare("INSERT INTO reviews (user_id, source, source_id, title, detail, next_due, interval_day) VALUES (?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 1 DAY), 1) ON DUPLICATE KEY UPDATE title=VALUES(title), detail=VALUES(detail)");
        $stmt->bind_param("isiss", $user_id, $source, $source_id, $title, $detail);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {}
}

// Skill tree: 8 skill sejajar kategori catatan, minggu roadmap dipetakan ke skill
function skill_defs() {
    return [
        'Linux' => ['icon' => 'fas fa-terminal', 'desc' => 'VPS, terminal & jaringan'],
        'Git' => ['icon' => 'fas fa-code-branch', 'desc' => 'Version control & GitHub'],
        'MySQL' => ['icon' => 'fas fa-database', 'desc' => 'Database & relasi'],
        'PHP' => ['icon' => 'fas fa-file-code', 'desc' => 'Native, OOP & security'],
        'Laravel' => ['icon' => 'fa-brands fa-laravel', 'desc' => 'Framework & middleware'],
        'Docker' => ['icon' => 'fa-brands fa-docker', 'desc' => 'Container & registry'],
        'AWS' => ['icon' => 'fa-brands fa-aws', 'desc' => 'Cloud & deploy'],
        'General' => ['icon' => 'fas fa-layer-group', 'desc' => 'Lainnya'],
    ];
}

function skill_for_week($week) {
    $map = [1 => 'MySQL', 2 => 'PHP', 3 => 'PHP', 4 => 'PHP', 5 => 'Laravel', 6 => 'Laravel', 7 => 'Docker', 8 => 'Docker', 9 => 'AWS', 10 => 'Linux', 11 => 'Git', 12 => 'General'];
    return $map[max(1, min(12, (int)$week))] ?? 'General';
}

function normalize_skill($topic) {
    $t = strtolower(trim((string)$topic));
    if ($t === '') return '';
    $aliases = [
        'MySQL' => ['mysql', 'database', 'db', 'sql', 'mariadb', 'relasi'],
        'PHP' => ['php', 'oop', 'solid', 'composer'],
        'Laravel' => ['laravel', 'eloquent', 'blade', 'middleware'],
        'Docker' => ['docker', 'container', 'dockerfile', 'image'],
        'Linux' => ['linux', 'ubuntu', 'bash', 'terminal', 'vps', 'nginx', 'ssl', 'domain', 'server'],
        'Git' => ['git', 'github', 'version'],
        'AWS' => ['aws', 'cloud', 'ec2', 'deploy'],
    ];
    foreach ($aliases as $skill => $keys) {
        foreach ($keys as $k) {
            if (strpos($t, $k) !== false) return $skill;
        }
    }
    foreach (array_keys(skill_defs()) as $skill) {
        if (strtolower($skill) === $t) return $skill;
    }
    return 'General';
}

// Bingkai avatar unlockable (kunci berbasis progres, awet karena pakai best_streak)
function avatar_frames() {
    return [
        'default' => ['name' => 'Polos', 'hint' => 'Untuk semua orang'],
        'ring' => ['name' => 'Cincin', 'hint' => 'Capai Level 3'],
        'ember' => ['name' => 'Bara', 'hint' => 'Streak terbaik 7 hari'],
        'gold' => ['name' => 'Emas', 'hint' => 'Capai Level 5'],
        'legend' => ['name' => 'Legenda', 'hint' => 'Badge Roadmap Tuntas / Level 8'],
    ];
}

function avatar_unlocked($frame, $level, $best_streak, $badges, $is_owner = false) {
    if ($is_owner) return true;
    if ($frame === 'default') return true;
    if ($frame === 'ring') return $level >= 3;
    if ($frame === 'ember') return $best_streak >= 7;
    if ($frame === 'gold') return $level >= 5;
    if ($frame === 'legend') return $level >= 8 || isset($badges['quest-all']);
    return false;
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

define('REMEMBER_COOKIE', 'lt_remember');
define('REMEMBER_DAYS', 30);

function remember_cookie_opts($expire) {
    return [
        'expires' => $expire,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function create_remember_token($conn, $user_id) {
    try {
        @$conn->query("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $hash = hash('sha256', $validator);
        $expires = date('Y-m-d H:i:s', time() + REMEMBER_DAYS * 86400);
        $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)");
        if (!$stmt) return;
        $stmt->bind_param("isss", $user_id, $selector, $hash, $expires);
        if ($stmt->execute()) {
            setcookie(REMEMBER_COOKIE, $selector . ':' . $validator, remember_cookie_opts(time() + REMEMBER_DAYS * 86400));
        }
        $stmt->close();
    } catch (Throwable $e) {
        error_log("remember create: " . $e->getMessage());
    }
}

function clear_remember_token($conn = null) {
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    $parts = explode(':', $cookie, 2);
    if (count($parts) === 2 && $conn) {
        try {
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            if ($stmt) {
                $stmt->bind_param("s", $parts[0]);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            error_log("remember clear: " . $e->getMessage());
        }
    }
    setcookie(REMEMBER_COOKIE, '', remember_cookie_opts(time() - 3600));
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function touch_login_time($conn, $user_id) {
    try {
        $stmt = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Throwable $e) {
        error_log("last_login touch: " . $e->getMessage());
    }
}

function try_remember_login() {
    if (!empty($_SESSION['user_id'])) return;
    $cookie = $_COOKIE[REMEMBER_COOKIE] ?? '';
    $parts = explode(':', $cookie, 2);
    if (count($parts) !== 2 || !ctype_xdigit($parts[0]) || !ctype_xdigit($parts[1])) return;
    list($selector, $validator) = $parts;
    $conn = db_connect();
    try {
        $stmt = $conn->prepare("SELECT rt.user_id, rt.validator_hash, rt.expires_at, u.username FROM remember_tokens rt JOIN users u ON u.id = rt.user_id WHERE rt.selector = ?");
        if (!$stmt) return;
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row || strtotime($row['expires_at']) < time() || !hash_equals($row['validator_hash'], hash('sha256', $validator))) {
            $del = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
            if ($del) {
                $del->bind_param("s", $selector);
                $del->execute();
                $del->close();
            }
            clear_remember_token();
            return;
        }
        $user_id = (int)$row['user_id'];
        $del = $conn->prepare("DELETE FROM remember_tokens WHERE selector = ?");
        if ($del) {
            $del->bind_param("s", $selector);
            $del->execute();
            $del->close();
        }
        create_remember_token($conn, $user_id);
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user_id;
        $_SESSION['username'] = $row['username'];
        update_user_streak($conn, $user_id);
        touch_login_time($conn, $user_id);
        set_flash('success', "Selamat datang kembali, {$row['username']}!");
    } catch (Throwable $e) {
        error_log("remember login: " . $e->getMessage());
    }
}

if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['user_id']) && PHP_SAPI !== 'cli') {
    try_remember_login();
}
