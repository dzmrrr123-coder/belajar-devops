<?php
require_once 'config.php';
$mode = $_GET['mode'] ?? '';
$format = $_GET['format'] ?? '';
if ($mode === 'live' || $format === 'json') {
    header('Content-Type: application/json');
    $out = ['status' => 'ok', 'php' => PHP_VERSION, 'time' => date('c')];
    if ($mode === 'ready' || ($mode === '' && $format === 'json')) {
        $out['session_driver'] = strtolower((string)(getenv('SESSION_DRIVER') ?: 'file'));
        $out['cache'] = extension_loaded('redis') && (getenv('REDIS_URL') || getenv('REDIS_HOST')) ? 'redis' : 'file';
        $out['storage_writable'] = is_writable(__DIR__ . '/storage') || @mkdir(__DIR__ . '/storage', 0775, true);
        try {
            $conn = \App\Db::connectWrite();
            $out['db'] = 'up';
            $out['schema_current'] = \App\Db\Schema::current($conn);
            $out['schema_expected'] = \App\Db\Schema::expected();
            $out['schema_ready'] = !\App\Db\Schema::needsUpgrade($conn);
        } catch (Throwable $e) {
            http_response_code(503);
            $out['status'] = 'degraded';
            $out['db'] = 'down';
        }
        if (($out['db'] ?? '') !== 'up' || empty($out['schema_ready'])) {
            http_response_code(503);
            $out['status'] = ($out['db'] ?? '') !== 'up' ? 'degraded' : 'migrating';
        }
    }
    echo json_encode($out);
    exit;
}
header('Content-Type: text/plain');
echo "=== Learn Tracker Health Diagnostic ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "mysqli extension loaded: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "\n";
echo "session extension loaded: " . (extension_loaded('session') ? 'YES' : 'NO') . "\n";
echo "Session driver: " . strtolower((string)(getenv('SESSION_DRIVER') ?: 'file')) . "\n";
echo "Cache: " . (extension_loaded('redis') && (getenv('REDIS_URL') || getenv('REDIS_HOST')) ? 'redis' : 'file') . "\n";
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
    $expected = ['users','quests','user_quests','errors','resources','pomodoro_sessions','questions','remember_tokens','daily_missions','quest_subtasks','reviews','user_badges','xp_events','quiz_cards','daily_chests','schema_meta'];
    $missing = array_diff($expected, $tables);
    echo "Missing Tables: " . (empty($missing) ? 'NONE' : implode(', ', $missing)) . "\n";
    $conn->close();
} catch (Throwable $e) {
    echo "DB Connection: FAILED (" . $e->getMessage() . ")\n";
}
