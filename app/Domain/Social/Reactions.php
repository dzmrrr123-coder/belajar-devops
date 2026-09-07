<?php
namespace App\Domain\Social;
class Reactions {
    public static function emojis(): array {
        return ['fire' => '🔥', 'muscle' => '💪', 'rocket' => '🚀', 'clap' => '👏'];
    }
    public static function valid(string $e): bool { return isset(self::emojis()[$e]); }
    public static function toggle(\mysqli $conn, int $uid, string $type, int $target, string $emoji): bool {
        if (!self::valid($emoji) || !in_array($type, ['profile'], true) || $target <= 0) return false;
        try {
            $s = $conn->prepare("SELECT id FROM reactions WHERE user_id = ? AND target_type = ? AND target_id = ? AND emoji = ?");
            if (!$s) return false;
            $s->bind_param("isis", $uid, $type, $target, $emoji);
            $s->execute();
            $ex = $s->get_result()->fetch_assoc(); $s->close();
            if ($ex) {
                $d = $conn->prepare("DELETE FROM reactions WHERE id = ?");
                if ($d) { $id = (int)$ex['id']; $d->bind_param("i", $id); $d->execute(); $d->close(); }
                return false;
            }
            $ins = $conn->prepare("INSERT IGNORE INTO reactions (target_type, target_id, user_id, emoji) VALUES (?, ?, ?, ?)");
            if (!$ins) return false;
            $ins->bind_param("siis", $type, $target, $uid, $emoji);
            $ok = $ins->execute() && $ins->affected_rows > 0; $ins->close();
            return (bool)$ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function counts(\mysqli $conn, string $type, array $targets): array {
        $out = [];
        foreach ($targets as $t) $out[(int)$t] = [];
        if (empty($targets)) return $out;
        try {
            $ids = implode(',', array_map('intval', $targets));
            $t = $conn->real_escape_string($type);
            $r = $conn->query("SELECT target_id, emoji, COUNT(*) n FROM reactions WHERE target_type = '{$t}' AND target_id IN ({$ids}) GROUP BY target_id, emoji");
            if ($r) { while ($row = $r->fetch_assoc()) $out[(int)$row['target_id']][$row['emoji']] = (int)$row['n']; $r->free(); }
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function mine(\mysqli $conn, int $uid, string $type, array $targets): array {
        $out = [];
        foreach ($targets as $t) $out[(int)$t] = [];
        if (empty($targets)) return $out;
        try {
            $ids = implode(',', array_map('intval', $targets));
            $t = $conn->real_escape_string($type);
            $s = $conn->prepare("SELECT target_id, emoji FROM reactions WHERE user_id = ? AND target_type = '{$t}' AND target_id IN ({$ids})");
            if (!$s) return $out;
            $s->bind_param("i", $uid); $s->execute();
            foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $out[(int)$row['target_id']][] = $row['emoji'];
            $s->close();
        } catch (\Throwable $e) {}
        return $out;
    }
}
