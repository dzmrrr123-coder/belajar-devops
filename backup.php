<?php
// Unduh backup SQL penuh (PHP-native, tanpa mysqldump). Admin saja.
require_once 'config.php';
require_login();
$conn = db_connect();
require_admin($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Gunakan tombol backup dari panel admin.');
}
verify_csrf();
$user_id = (int)$_SESSION['user_id'];

try {
    if (function_exists('set_time_limit')) @set_time_limit(0);
    while (ob_get_level() > 0) @ob_end_clean();
    $fname = 'learn-tracker-backup-' . date('Ymd-His') . '.sql';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    header('X-Content-Type-Options: nosniff');
    echo "-- Learn Tracker backup\n-- Tanggal: " . date('Y-m-d H:i:s') . "\n-- Oleh admin #{$user_id}\n\n";
    echo "SET FOREIGN_KEY_CHECKS=0;\n\n";
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_array()) $tables[] = $row[0];
        $res->free();
    }
    foreach ($tables as $t) {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $t)) continue;
        $cr = $conn->query("SHOW CREATE TABLE `{$t}`");
        if ($cr && ($crow = $cr->fetch_array())) {
            echo "DROP TABLE IF EXISTS `{$t}`;\n" . $crow[1] . ";\n\n";
            $cr->free();
        }
        $off = 0; $batch = 500;
        do {
            $rows = $conn->query("SELECT * FROM `{$t}` LIMIT {$off}, {$batch}");
            if (!$rows) break;
            $vals = [];
            while ($r = $rows->fetch_assoc()) {
                $cells = [];
                foreach ($r as $v) {
                    $cells[] = $v === null ? 'NULL' : ("'" . $conn->real_escape_string((string)$v) . "'");
                }
                $vals[] = '(' . implode(',', $cells) . ')';
            }
            $n = count($vals);
            $rows->free();
            if ($n > 0) echo "INSERT INTO `{$t}` VALUES\n" . implode(",\n", $vals) . ";\n";
            $off += $batch;
            if ($n > 0) { echo "\n"; @flush(); }
        } while ($n === $batch);
        echo "\n";
    }
    echo "SET FOREIGN_KEY_CHECKS=1;\n-- Backup selesai: " . count($tables) . " tabel.\n";
    $conn->close();
} catch (Throwable $e) {
    error_log("backup: " . $e->getMessage());
    if (!headers_sent()) http_response_code(500);
    echo "-- Backup gagal: " . $e->getMessage() . "\n";
}
exit();
