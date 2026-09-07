<?php
namespace App\Domain\Dkv;
class Rubric {
    public static function criteria(): array {
        return [
            ['slug' => 'konsep', 'name' => 'Konsep & brief', 'desc' => 'Karya menjawab brief dan punya ide jelas', 'weight' => 25],
            ['slug' => 'tipografi', 'name' => 'Tipografi', 'desc' => 'Pilihan dan susunan huruf rapi terbaca', 'weight' => 20],
            ['slug' => 'warna', 'name' => 'Warna', 'desc' => 'Palet konsisten dan mendukung pesan', 'weight' => 20],
            ['slug' => 'layout', 'name' => 'Layout', 'desc' => 'Komposisi, hierarki, dan ruang seimbang', 'weight' => 20],
            ['slug' => 'presentasi', 'name' => 'Presentasi', 'desc' => 'Kerapian file dan cara mempresentasikan', 'weight' => 15],
        ];
    }
    public static function clampScore(int $s): int { return max(1, min(5, $s)); }
    public static function canRate(\mysqli $conn, int $raterId, int $ownerId): bool {
        if ($raterId <= 0 || $ownerId <= 0) return false;
        if ($raterId === $ownerId) return true;
        try {
            if (\App\Domain\Auth\Roles::isAdmin($conn, $raterId)) return true;
            if (!\App\Domain\Auth\Roles::isGuru($conn, $raterId)) return false;
            $s = $conn->prepare("SELECT 1 FROM squad_members m JOIN squads s ON s.id = m.squad_id WHERE m.user_id = ? AND s.created_by = ? LIMIT 1");
            if (!$s) return false;
            $s->bind_param("ii", $ownerId, $raterId); $s->execute();
            $ok = (bool)$s->get_result()->fetch_assoc(); $s->close();
            return $ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function weightedAvg(array $scores): float {
        $num = 0; $den = 0;
        foreach (self::criteria() as $c) {
            if (!isset($scores[$c['slug']])) continue;
            $num += self::clampScore((int)$scores[$c['slug']]) * $c['weight'];
            $den += $c['weight'];
        }
        return $den > 0 ? round($num / $den, 1) : 0.0;
    }
    public static function ensureTables(\mysqli $conn): void {
        try { @$conn->query("CREATE TABLE IF NOT EXISTS `rubric_criteria` (`id` INT AUTO_INCREMENT PRIMARY KEY, `slug` VARCHAR(24) NOT NULL UNIQUE, `name` VARCHAR(64) NOT NULL, `description` VARCHAR(255) NOT NULL DEFAULT '', `weight` INT NOT NULL DEFAULT 20) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (\Throwable $e) {}
        try { @$conn->query("CREATE TABLE IF NOT EXISTS `submission_scores` (`id` INT AUTO_INCREMENT PRIMARY KEY, `owner_id` INT NOT NULL, `quest_id` INT NOT NULL, `criterion_id` INT NOT NULL, `score` TINYINT NOT NULL DEFAULT 3, `note` VARCHAR(300) NULL, `rater_id` INT NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, CONSTRAINT `uq_sub_score` UNIQUE (`owner_id`, `quest_id`, `criterion_id`, `rater_id`), CONSTRAINT `fk_sub_score_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_sub_score_quest` FOREIGN KEY (`quest_id`) REFERENCES `quests` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_sub_score_rater` FOREIGN KEY (`rater_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (\Throwable $e) {}
        try {
            $ins = $conn->prepare("INSERT IGNORE INTO rubric_criteria (slug, name, description, weight) VALUES (?, ?, ?, ?)");
            if ($ins) { foreach (self::criteria() as $c) { $ins->bind_param("sssi", $c['slug'], $c['name'], $c['desc'], $c['weight']); $ins->execute(); } $ins->close(); }
        } catch (\Throwable $e) {}
    }
    public static function save(\mysqli $conn, int $ownerId, int $questId, int $raterId, array $scores, string $note = ''): bool {
        self::ensureTables($conn);
        $note = mb_substr(trim($note), 0, 300);
        try {
            $ids = [];
            $r = $conn->query("SELECT id, slug FROM rubric_criteria");
            if ($r) { foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $ids[$row['slug']] = (int)$row['id']; $r->free(); }
            if (!$ids) return false;
            $ins = $conn->prepare("INSERT INTO submission_scores (owner_id, quest_id, criterion_id, score, note, rater_id) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score), note = VALUES(note)");
            if (!$ins) return false;
            foreach (self::criteria() as $c) {
                if (!isset($scores[$c['slug']]) || !isset($ids[$c['slug']])) continue;
                $sc = self::clampScore((int)$scores[$c['slug']]);
                $cid = $ids[$c['slug']];
                $ins->bind_param("iiiisi", $ownerId, $questId, $cid, $sc, $note, $raterId);
                $ins->execute();
            }
            $ins->close();
            return true;
        } catch (\Throwable $e) { return false; }
    }
    public static function summary(\mysqli $conn, int $ownerId, int $questId): array {
        self::ensureTables($conn);
        $out = ['avg' => 0.0, 'count' => 0, 'by_criterion' => []];
        try {
            $s = $conn->prepare("SELECT rc.slug, AVG(ss.score) avg_sc, COUNT(*) n FROM submission_scores ss JOIN rubric_criteria rc ON rc.id = ss.criterion_id WHERE ss.owner_id = ? AND ss.quest_id = ? GROUP BY rc.slug");
            if (!$s) return $out;
            $s->bind_param("ii", $ownerId, $questId); $s->execute();
            $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
            $map = [];
            foreach ($rows as $r) $map[$r['slug']] = (float)$r['avg_sc'];
            if (!$map) return $out;
            $out['by_criterion'] = $map;
            $out['count'] = count($map);
            $num = 0; $den = 0;
            foreach (self::criteria() as $c) {
                if (!isset($map[$c['slug']])) continue;
                $num += $map[$c['slug']] * $c['weight']; $den += $c['weight'];
            }
            $out['avg'] = $den > 0 ? round($num / $den, 1) : 0.0;
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function summaries(\mysqli $conn, int $ownerId, array $questIds): array {
        self::ensureTables($conn);
        $out = [];
        $questIds = array_values(array_unique(array_map('intval', $questIds)));
        if (!$questIds) return $out;
        try {
            $in = implode(',', $questIds);
            $r = $conn->query("SELECT ss.quest_id, rc.slug, AVG(ss.score) avg_sc FROM submission_scores ss JOIN rubric_criteria rc ON rc.id = ss.criterion_id WHERE ss.owner_id = " . (int)$ownerId . " AND ss.quest_id IN ($in) GROUP BY ss.quest_id, rc.slug");
            if (!$r) return $out;
            $perQ = [];
            foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $perQ[(int)$row['quest_id']][$row['slug']] = (float)$row['avg_sc'];
            foreach ($perQ as $qid => $m) {
                $num = 0; $den = 0;
                foreach (self::criteria() as $c) {
                    if (!isset($m[$c['slug']])) continue;
                    $num += $m[$c['slug']] * $c['weight']; $den += $c['weight'];
                }
                if ($den > 0) $out[$qid] = round($num / $den, 1);
            }
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function portfolioAvg(\mysqli $conn, int $ownerId): array {
        self::ensureTables($conn);
        $out = ['avg' => 0.0, 'quests' => 0];
        try {
            $s = $conn->prepare("SELECT ss.quest_id, rc.slug, AVG(ss.score) avg_sc FROM submission_scores ss JOIN rubric_criteria rc ON rc.id = ss.criterion_id WHERE ss.owner_id = ? GROUP BY ss.quest_id, rc.slug");
            if (!$s) return $out;
            $s->bind_param("i", $ownerId); $s->execute();
            $perQ = [];
            foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $perQ[(int)$r['quest_id']][$r['slug']] = (float)$r['avg_sc'];
            $s->close();
            $avgs = [];
            foreach ($perQ as $m) {
                $num = 0; $den = 0;
                foreach (self::criteria() as $c) {
                    if (!isset($m[$c['slug']])) continue;
                    $num += $m[$c['slug']] * $c['weight']; $den += $c['weight'];
                }
                if ($den > 0) $avgs[] = $num / $den;
            }
            if ($avgs) { $out['avg'] = round(array_sum($avgs) / count($avgs), 1); $out['quests'] = count($avgs); }
        } catch (\Throwable $e) {}
        return $out;
    }
}
