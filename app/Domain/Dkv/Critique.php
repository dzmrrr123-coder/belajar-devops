<?php
namespace App\Domain\Dkv;
class Critique {
    public static function ensureTables(\mysqli $conn): void {
        try { @$conn->query("CREATE TABLE IF NOT EXISTS `rubric_comments` (`id` INT AUTO_INCREMENT PRIMARY KEY, `quest_id` INT NOT NULL, `owner_id` INT NOT NULL, `author_id` INT NOT NULL, `note` VARCHAR(500) NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (\Throwable $e) {}
    }
    public static function clean(string $s): string {
        return mb_substr(trim(preg_replace('/\s+/', ' ', $s)), 0, 500);
    }
    public static function valid(string $note): bool {
        return mb_strlen(trim($note)) >= 3;
    }
    public static function thread(\mysqli $conn, int $ownerId, int $questId, int $limit = 20): array {
        self::ensureTables($conn);
        try {
            $s = $conn->prepare("SELECT c.note, c.created_at, u.username FROM rubric_comments c JOIN users u ON u.id = c.author_id WHERE c.owner_id = ? AND c.quest_id = ? ORDER BY c.id DESC LIMIT ?");
            if (!$s) return [];
            $s->bind_param("iii", $ownerId, $questId, $limit); $s->execute();
            $out = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
            return $out;
        } catch (\Throwable $e) { return []; }
    }
}
