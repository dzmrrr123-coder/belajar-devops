<?php
require_once 'config.php';
header('Content-Type: text/plain');
echo "=== Learn Tracker Health Diagnostic ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "mysqli extension loaded: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "\n";
echo "session extension loaded: " . (extension_loaded('session') ? 'YES' : 'NO') . "\n";
echo "PORT env: " . (getenv('PORT') ?: 'NOT SET') . "\n";
echo "Resolved DB_HOST: " . DB_HOST . "\n";
echo "Resolved DB_PORT: " . DB_PORT . "\n";
echo "Resolved DB_USER: " . DB_USER . "\n";
echo "Resolved DB_NAME: " . DB_NAME . "\n";

try {
    $conn = db_connect();
    echo "DB Connection: SUCCESS\n";
    $res = $conn->query("SHOW TABLES");
    $tables = [];
    if ($res) {
        while ($row = $res->fetch_array()) {
            $tables[] = $row[0];
        }
    }
    echo "Tables Found: " . (empty($tables) ? 'NONE' : implode(', ', $tables)) . "\n";
    $expected = ['users','quests','user_quests','errors','resources','pomodoro_sessions','questions','remember_tokens','daily_missions','quest_subtasks','reviews','user_badges','xp_events'];
    $missing = array_diff($expected, $tables);
    echo "Missing Tables: " . (empty($missing) ? 'NONE' : implode(', ', $missing)) . "\n";
    $conn->close();
} catch (Throwable $e) {
    echo "DB Connection: FAILED (" . $e->getMessage() . ")\n";
}
