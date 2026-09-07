<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once dirname(__DIR__) . '/config.php';
$cmd = $argv[1] ?? '--up';
try {
    $conn = \App\Db::connectWrite();
} catch (Throwable $e) {
    fwrite(STDERR, "connect failed: " . $e->getMessage() . PHP_EOL);
    exit(2);
}
if ($cmd === '--check') {
    $st = \App\Db\Migrator::check($conn);
    echo json_encode($st, JSON_PRETTY_PRINT) . PHP_EOL;
    exit($st['needsUpgrade'] ? 1 : 0);
}
$ver = \App\Db\Migrator::run($conn);
echo "migrated to version: {$ver}\n";
$st = \App\Db\Migrator::check($conn);
if (!empty($st['pendingFiles'])) {
    fwrite(STDERR, "pending files remain: " . implode(', ', $st['pendingFiles']) . PHP_EOL);
    exit(1);
}
