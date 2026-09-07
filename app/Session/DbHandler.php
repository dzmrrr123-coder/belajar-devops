<?php
namespace App\Session;
class DbHandler implements \SessionHandlerInterface {
    private ?\mysqli $conn = null;
    private string $table = 'sessions';
    public function open($path, $name): bool {
        try {
            $cfg = is_file(dirname(__DIR__, 2) . '/config/session.php') ? require dirname(__DIR__, 2) . '/config/session.php' : [];
            $this->table = preg_replace('/[^a-z_]/', '', (string)($cfg['table'] ?? 'sessions')) ?: 'sessions';
            $this->conn = \App\Db::connectWrite();
            return true;
        } catch (\Throwable $e) { return false; }
    }
    public function close(): bool { return true; }
    public function read($id): string|false {
        try {
            $s = $this->conn->prepare("SELECT payload FROM `{$this->table}` WHERE id = ? AND last_activity >= DATE_SUB(NOW(), INTERVAL 2 HOUR)");
            if (!$s) return '';
            $s->bind_param("s", $id); $s->execute();
            $row = $s->get_result()->fetch_assoc(); $s->close();
            return (string)($row['payload'] ?? '');
        } catch (\Throwable $e) { return ''; }
    }
    public function write($id, $data): bool {
        try {
            $s = $this->conn->prepare("INSERT INTO `{$this->table}` (id, payload, last_activity) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE payload = VALUES(payload), last_activity = NOW()");
            if (!$s) return false;
            $s->bind_param("ss", $id, $data); $ok = $s->execute(); $s->close();
            return (bool)$ok;
        } catch (\Throwable $e) { return false; }
    }
    public function destroy($id): bool {
        try {
            $s = $this->conn->prepare("DELETE FROM `{$this->table}` WHERE id = ?");
            if ($s) { $s->bind_param("s", $id); $s->execute(); $s->close(); }
        } catch (\Throwable $e) {}
        return true;
    }
    public function gc($max_lifetime): int|false {
        try {
            $mins = max(1, (int)($max_lifetime / 60));
            $this->conn->query("DELETE FROM `{$this->table}` WHERE last_activity < DATE_SUB(NOW(), INTERVAL {$mins} MINUTE)");
            return $this->conn->affected_rows;
        } catch (\Throwable $e) { return 0; }
    }
}
