<?php
require_once __DIR__ . '/app/bootstrap.php';
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

// 2. Session driver: file (default) | redis | db — Fase 3 stateless
$session_driver = strtolower((string)(getenv('SESSION_DRIVER') ?: 'file'));
if (PHP_SAPI !== 'cli' && $session_driver === 'redis' && extension_loaded('redis') && (getenv('REDIS_URL') || getenv('REDIS_HOST'))) {
    $h = new \App\Session\RedisHandler();
    if ($h->open('', '')) session_set_save_handler($h, true);
} elseif (PHP_SAPI !== 'cli' && $session_driver === 'db') {
    $h = new \App\Session\DbHandler();
    if ($h->open('', '')) session_set_save_handler($h, true);
} elseif (is_dir('/tmp/sessions') && is_writable('/tmp/sessions')) {
    ini_set('session.save_path', '/tmp/sessions');
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
}

function rate_limit_hit($key, $max, $window_sec) { return \App\Http\RateLimit::hit((string)$key, (int)$max, (int)$window_sec); }
function shop_reroll_win($roll, $draw) { return \App\Domain\Shop::rerollWin((int)$roll, (int)$draw); }
function shop_reroll_ev() { return \App\Domain\Shop::rerollEv(); }

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

define('SCHEMA_VERSION', 32);

function quiz_topics() { return \App\Domain\Quiz\QuizBank::topics(); }

function quiz_bank_cards() { return \App\Domain\Quiz\QuizBank::cards(); }

function seed_quiz_bank($conn, $user_id) { return \App\Domain\Quiz\QuizSeeder::seed($conn, (int)$user_id); }

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
        @$conn->query("ALTER TABLE `quiz_cards` ADD COLUMN `topic` VARCHAR(32) NOT NULL DEFAULT 'General'");
        @$conn->query("CREATE INDEX idx_quiz_cards_topic ON `quiz_cards` (`user_id`, `topic`)");
        try {
            $bank = quiz_bank_cards();
            $up = $conn->prepare("UPDATE quiz_cards SET topic = ? WHERE source = 'bank' AND source_id = ? AND (topic = '' OR topic = 'General')");
            if ($up) {
                foreach ($bank as $i => $c) {
                    $t = mb_substr(trim((string)($c[0] ?? 'General')) ?: 'General', 0, 32);
                    $sid = $i + 1;
                    $up->bind_param("si", $t, $sid);
                    $up->execute();
                }
                $up->close();
            }
        } catch (Throwable $e) {}
        try {
            $ur = $conn->query("SELECT id FROM `users`");
            if ($ur) {
                while ($u = $ur->fetch_assoc()) {
                    seed_quiz_bank($conn, (int)$u['id']);
                }
                $ur->free();
            }
        } catch (Throwable $e) {}
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
        @$conn->query("ALTER TABLE `reviews` ADD COLUMN `ease_factor` FLOAT NOT NULL DEFAULT 2.5");
        @$conn->query("ALTER TABLE `reviews` ADD COLUMN `reps` INT NOT NULL DEFAULT 0");
        @$conn->query("ALTER TABLE `reviews` ADD COLUMN `skill` VARCHAR(32) NOT NULL DEFAULT 'General'");
        @$conn->query("CREATE INDEX idx_reviews_skill_due ON `reviews` (`user_id`, `skill`, `next_due`)");
        @$conn->query("ALTER TABLE `quests` ADD COLUMN `depends_on` INT NULL");
        @$conn->query("CREATE INDEX idx_quests_depends ON `quests` (`depends_on`)");
        @$conn->query("CREATE TABLE IF NOT EXISTS `challenges` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `week_key` VARCHAR(16) NOT NULL UNIQUE,
            `title` VARCHAR(120) NOT NULL,
            `target_xp` INT NOT NULL DEFAULT 100,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE TABLE IF NOT EXISTS `challenge_joins` (
            `challenge_id` INT NOT NULL,
            `user_id` INT NOT NULL,
            `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `uq_challenge_join` UNIQUE (`challenge_id`, `user_id`),
            CONSTRAINT `fk_challenge_join_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE TABLE IF NOT EXISTS `cheers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `profile_id` INT NOT NULL,
            `from_id` INT NOT NULL,
            `body` VARCHAR(140) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT `fk_cheers_profile` FOREIGN KEY (`profile_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_cheers_from` FOREIGN KEY (`from_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        @$conn->query("CREATE INDEX idx_cheers_profile ON `cheers` (`profile_id`, `created_at`)");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `onboarded` TINYINT NOT NULL DEFAULT 0");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `pkl_target` VARCHAR(32) NOT NULL DEFAULT ''");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `daily_minutes` INT NOT NULL DEFAULT 25");
        @$conn->query("ALTER TABLE `users` ADD COLUMN `focus_skills` VARCHAR(255) NOT NULL DEFAULT ''");
        @$conn->query("UPDATE `users` SET `onboarded` = 1 WHERE `onboarded` = 0");

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

// Connect database (one shared connection per request, Fase 2: fast version gate)
function db_connect() {
    static $shared = null;
    if ($shared instanceof mysqli) {
        try {
            if (@$shared->ping()) return $shared;
        } catch (Throwable $e) {}
        $shared = null;
    }
    try {
        $conn = \App\Db::connectWrite();
        if (\App\Db\Schema::needsUpgrade($conn) && \App\Db\Schema::autoMigrateEnabled()) {
            \App\Db\Migrator::run($conn);
        }
        $shared = $conn;
        return $conn;
    } catch (Throwable $e) {
        http_response_code(500);
        render_db_error_page($e->getMessage(), DB_HOST, DB_PORT, DB_USER, DB_NAME);
        exit();
    }
}
function db_read() { return \App\Db::connectRead(); }

function redirect($url) { \App\Http\Auth::redirect((string)$url); }

function clean($data) { return \App\Support\Sanitize::clean($data); }
function esc($data) { return \App\Support\Sanitize::esc($data); }
function valid_url($url) { return \App\Support\Sanitize::validUrl($url); }

// Level calculation delegates to App\Domain\Gamification\Level (Fase 1)
function calculate_level($xp) { return \App\Domain\Gamification\Level::calculate((int)$xp); }
function level_base_xp($level) { return \App\Domain\Gamification\Level::baseXp((int)$level); }
function xp_to_next_level($xp) { return \App\Domain\Gamification\Level::nextXp((int)$xp); }
function level_progress_percent($xp) { return \App\Domain\Gamification\Level::progress((int)$xp); }
function get_user_rank($level) { return \App\Domain\Gamification\Level::rank((int)$level); }

define('OWNER_ADMIN_EMAIL', 'dzmrrr123@gmail.com');
function is_logged_in() { return \App\Http\Auth::loggedIn(); }
function is_admin($conn, $user_id) { return \App\Http\Auth::isAdmin($conn, (int)$user_id); }
function require_admin($conn) { \App\Http\Auth::requireAdmin($conn); }
function require_login() { \App\Http\Auth::requireLogin(); }

function update_user_streak($conn, $user_id) { return \App\Domain\Gamification\Streak::update($conn, (int)$user_id); }

function badge_defs() { return \App\Domain\Social::badgeDefs(); }

function user_badges($conn, $user_id) { return \App\Domain\Gamification\Badges::owned($conn, (int)$user_id); }

function check_and_unlock_badges($conn, $user_id) { return \App\Domain\Gamification\Badges::check($conn, (int)$user_id); }
function mission_multiplier($conn, $user_id) { return \App\Domain\Gamification\Combo::multiplier($conn, (int)$user_id); }
function combo_tier($done) { return \App\Domain\Gamification\Combo::tier((int)$done); }
function combo_count_done($missions) { return \App\Domain\Gamification\Combo::countDone((array)$missions); }

function apply_xp_multiplier($base, $mult) { return \App\Domain\Gamification\Xp::apply((int)$base, (float)$mult); }

define('NOTE_DAILY_XP_CAP', 25);

function capped_xp_gain($wanted, $today_sum, $cap) { return \App\Domain\Gamification\Xp::capped((int)$wanted, (int)$today_sum, (int)$cap); }

function daily_reason_xp($c, $u, $r) { return \App\Domain\Gamification\Ledger::dailyReason($c, (int)$u, (string)$r); }
function xp_events_has_ref($c) { return \App\Domain\Gamification\Ledger::hasRef($c); }
function award_xp($c, $u, $a, $r = 'other', $t = null, $i = null) { \App\Domain\Gamification\Ledger::award($c, (int)$u, (int)$a, (string)$r, $t, $i); }
function xp_ledger_sum($c, $u) { return \App\Domain\Gamification\Ledger::sum($c, (int)$u); }
function sync_user_xp($c, $u) { return \App\Domain\Gamification\Ledger::sync($c, (int)$u); }
function awarded_for_ref($c, $u, $t, $i) { return \App\Domain\Gamification\Ledger::awardedFor($c, (int)$u, (string)$t, (int)$i); }

function weekly_xp($conn, $user_id) { return \App\Domain\Gamification\Ledger::weekly($conn, (int)$user_id); }

function daily_mission_defs() { return \App\Domain\Gamification\Mission::defs(); }

function get_daily_mission_status($conn, $user_id) { return \App\Domain\Gamification\Missions::status($conn, (int)$user_id); }

function quest_visible_where() { return \App\Domain\Quest\QuestPolicy::visibleWhere(); }

function delete_review($conn, $user_id, $source, $source_id) { \App\Domain\Review\ReviewStore::delete($conn, (int)$user_id, (string)$source, (int)$source_id); }

function review_next_interval($c) { return \App\Domain\Review\Sm2::nextInterval((int)$c); }

function schedule_review($conn, $user_id, $source, $source_id, $title, $detail = '', $skill = '') { \App\Domain\Review\ReviewStore::schedule($conn, (int)$user_id, (string)$source, (int)$source_id, (string)$title, (string)$detail, (string)$skill); }

function skill_defs() { return \App\Domain\Skill\Skill::defs(); }
function skill_for_week($week) { return \App\Domain\Skill\Skill::forWeek((int)$week); }
function normalize_skill($topic) { return \App\Domain\Skill\Skill::normalize((string)$topic); }
function avatar_frames() { return \App\Domain\Social::avatarFrames(); }
function avatar_unlocked($frame, $level, $best_streak, $badges, $is_owner = false) { return \App\Domain\Social::avatarUnlocked((string)$frame, (int)$level, (int)$best_streak, (array)$badges, (bool)$is_owner); }

function analytics_trend_percent($now, $prev) { return \App\Domain\Analytics::trend((int)$now, (int)$prev); }
function analytics_consistency_score($a, $t) { return \App\Domain\Analytics::consistency((int)$a, (int)$t); }
function analytics_heat_level($xp) { return \App\Domain\Analytics::heat((int)$xp); }
function analytics_streak_verdict($s) { return \App\Domain\Analytics::verdict((int)$s); }
function analytics_project_days_left($d, $t, $a) { return \App\Domain\Analytics::daysLeft((int)$d, (int)$t, (float)$a); }
function analytics_predict_label($d) { return \App\Domain\Analytics::predictLabel((int)$d); }
function analytics_week_label($o) { return \App\Domain\Analytics::weekLabel((int)$o); }
function sm2_grade_to_int($g) { return \App\Domain\Review\Sm2::gradeToInt($g); }
function sm2_next($e, $r, $i, $g) { return \App\Domain\Review\Sm2::next((float)$e, (int)$r, (int)$i, $g); }
function sm2_labels() { return \App\Domain\Review\Sm2::labels(); }

function quest_prev_map($quests) { return \App\Domain\Quest\QuestPolicy::prevMap((array)$quests); }
function quest_blocker($quest, $done_ids, $prev_id = null) { return \App\Domain\Quest\QuestPolicy::blocker((array)$quest, (array)$done_ids, $prev_id); }
function quest_week_stats($week_quests) { return \App\Domain\Quest\QuestPolicy::weekStats((array)$week_quests); }

function quest_next_unlocked($a, $b, $c) { return \App\Domain\Quest\QuestPolicy::nextUnlocked((array)$a, (array)$b, (array)$c); }
function challenge_week_key($ts = null) { return \App\Domain\Challenge::weekKey($ts); }
function challenge_for_week($k) { return \App\Domain\Challenge::forWeek((string)$k); }
function challenge_pct($xp, $t) { return \App\Domain\Challenge::pct((int)$xp, (int)$t); }
function cheer_clean($b) { return \App\Domain\Social::cheerClean((string)$b); }
function badge_share_text($u, $b) { return \App\Domain\Social::badgeShare((string)$u, (string)$b); }

function ensure_weekly_challenge($conn) { return \App\Domain\ChallengeStore::ensureWeekly($conn); }

function onboarding_targets() { return \App\Domain\Onboarding::targets(); }
function onboarding_minutes() { return \App\Domain\Onboarding::minutes(); }
function onboarding_plan($t, $m, $s) { return \App\Domain\Onboarding::plan((string)$t, (int)$m, (array)$s); }
function review_skill_for($s, $t, $d) { return \App\Domain\Skill\Skill::reviewSkillFor((string)$s, (string)$t, (string)$d); }
function set_flash($t, $m) { \App\Http\Flash::set((string)$t, (string)$m); }
function get_flash() { return \App\Http\Flash::get(); }

function csrf_token() { return \App\Http\Csrf::token(); }
function csrf_field() { return \App\Http\Csrf::field(); }
function verify_csrf() { \App\Http\Csrf::verify(); }

define('REMEMBER_COOKIE', 'lt_remember');
define('REMEMBER_DAYS', 30);

function remember_cookie_opts($e) { return \App\Domain\Auth\Remember::opts((int)$e); }
function create_remember_token($conn, $user_id) { \App\Domain\Auth\Remember::create($conn, (int)$user_id); }

function clear_remember_token($conn = null) { \App\Domain\Auth\Remember::clear($conn); }
function touch_login_time($conn, $user_id) { \App\Domain\Auth\Remember::touch($conn, (int)$user_id); }

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
