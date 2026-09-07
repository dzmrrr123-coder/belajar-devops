<?php
namespace App\Domain\Incident;
class Certificate {
    public static function code(int $uid, int $cid): string {
        $h = strtoupper(substr(str_replace(['+', '/', '='], '', base64_encode(random_bytes(6))), 0, 6));
        return 'DQ-' . $h;
    }
    public static function ensureTables(\mysqli $conn): void {
        @$conn->query("CREATE TABLE IF NOT EXISTS `certificates` (`id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(16) NOT NULL UNIQUE, `user_id` INT NOT NULL, `challenge_id` INT NOT NULL, `score` INT NOT NULL DEFAULT 0, `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `fk_cert_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_cert_ch` FOREIGN KEY (`challenge_id`) REFERENCES `incident_challenges` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    public static function issue(\mysqli $conn, int $uid, int $cid, int $score): ?string {
        if ($score < 70) return null;
        self::ensureTables($conn);
        try {
            $q = $conn->prepare("SELECT code FROM certificates WHERE user_id = ? AND challenge_id = ? ORDER BY score DESC LIMIT 1");
            if ($q) { $q->bind_param("ii", $uid, $cid); $q->execute(); $row = $q->get_result()->fetch_assoc(); $q->close(); if ($row) return $row['code']; }
            for ($i = 0; $i < 3; $i++) {
                $code = self::code($uid, $cid);
                $ins = $conn->prepare("INSERT INTO certificates (code, user_id, challenge_id, score) VALUES (?, ?, ?, ?)");
                if (!$ins) return null;
                $ins->bind_param("siii", $code, $uid, $cid, $score);
                if ($ins->execute()) { $ins->close(); return $code; }
                $ins->close();
            }
        } catch (\Throwable $e) {}
        return null;
    }
    public static function forUser(\mysqli $conn, int $uid): array {
        self::ensureTables($conn);
        try {
            $s = $conn->prepare("SELECT t.code, t.score, t.issued_at, c.title, c.skill, c.difficulty FROM certificates t JOIN incident_challenges c ON c.id = t.challenge_id WHERE t.user_id = ? ORDER BY t.issued_at DESC");
            if (!$s) return [];
            $s->bind_param("i", $uid); $s->execute();
            $out = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
            return $out;
        } catch (\Throwable $e) { return []; }
    }
}
