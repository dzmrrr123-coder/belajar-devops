<?php
namespace App\Domain\Quest;
use App\Support\Sanitize;
class Evidence {
    public static function save(\mysqli $conn, int $uid, int $qid, string $url, string $note): bool {
        $url = Sanitize::validUrl($url);
        $note = mb_substr(trim($note), 0, 500);
        if ($url === '' && $note === '') return false;
        try {
            $s = $conn->prepare("INSERT INTO evidence_submissions (user_id, quest_id, url, note) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE url = VALUES(url), note = VALUES(note)");
            if (!$s) return false;
            $un = $url === '' ? null : $url;
            $nn = $note === '' ? null : $note;
            $s->bind_param("iiss", $uid, $qid, $un, $nn);
            $ok = $s->execute(); $s->close();
            return (bool)$ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function clear(\mysqli $conn, int $uid, int $qid): void {
        try {
            $s = $conn->prepare("DELETE FROM evidence_submissions WHERE user_id = ? AND quest_id = ?");
            if ($s) { $s->bind_param("ii", $uid, $qid); $s->execute(); $s->close(); }
        } catch (\Throwable $e) {}
    }
    public static function forQuests(\mysqli $conn, int $uid, array $qids): array {
        $out = [];
        $qids = array_values(array_unique(array_map('intval', $qids)));
        if (empty($qids)) return $out;
        try {
            $in = implode(',', $qids);
            $r = $conn->query("SELECT quest_id, url, note FROM evidence_submissions WHERE user_id = {$uid} AND quest_id IN ({$in})");
            if ($r) { foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $out[(int)$row['quest_id']] = $row; $r->free(); }
        } catch (\Throwable $e) {}
        return $out;
    }
}
