<?php
namespace App\Domain\Social;
use App\Domain\Challenge;
use App\Domain\Gamification\Ledger;
class Duels {
    public const WIN_XP = 15;
    public static function yearWeek(string $weekKey): int {
        if (preg_match('/^(\d{4})-W(\d{1,2})$/', $weekKey, $m)) return ((int)$m[1]) * 100 + (int)$m[2];
        return (int)date('oW');
    }
    public static function weekXp(\mysqli $conn, int $uid, int $yearWeek): int {
        try {
            $q = $conn->prepare("SELECT GREATEST(0, COALESCE(SUM(amount),0)) n FROM xp_events WHERE user_id = ? AND YEARWEEK(created_at, 1) = ?");
            if (!$q) return 0;
            $q->bind_param("ii", $uid, $yearWeek); $q->execute();
            $n = (int)($q->get_result()->fetch_assoc()['n'] ?? 0); $q->close();
            return max(0, $n);
        } catch (\Throwable $e) { return 0; }
    }
    public static function challenge(\mysqli $conn, int $from, string $username): array {
        $username = trim($username);
        if ($username === '') return ['ok' => false, 'msg' => 'Isi username lawan.'];
        try {
            $s = $conn->prepare("SELECT id, username FROM users WHERE username = ?");
            if (!$s) return ['ok' => false, 'msg' => 'Gagal mencari user.'];
            $s->bind_param("s", $username); $s->execute();
            $row = $s->get_result()->fetch_assoc(); $s->close();
            if (!$row) return ['ok' => false, 'msg' => 'Username tidak ditemukan.'];
            $opp = (int)$row['id'];
            if ($opp === $from) return ['ok' => false, 'msg' => 'Tidak bisa duel diri sendiri.'];
            $wk = Challenge::weekKey();
            $d = $conn->prepare("SELECT id FROM duels WHERE ((challenger_id = ? AND opponent_id = ?) OR (challenger_id = ? AND opponent_id = ?)) AND week_key = ? AND status IN ('pending','active')");
            $d->bind_param("iiiis", $from, $opp, $opp, $from, $wk); $d->execute();
            $dup = $d->get_result()->fetch_assoc(); $d->close();
            if ($dup) return ['ok' => false, 'msg' => 'Duel minggu ini dengan ' . $row['username'] . ' sudah ada.'];
            $ins = $conn->prepare("INSERT INTO duels (challenger_id, opponent_id, week_key, status) VALUES (?, ?, ?, 'pending')");
            if (!$ins) return ['ok' => false, 'msg' => 'Gagal membuat duel.'];
            $ins->bind_param("iis", $from, $opp, $wk); $ins->execute(); $ins->close();
            return ['ok' => true, 'msg' => 'Tantangan dikirim ke ' . $row['username'] . '!'];
        } catch (\Throwable $e) { return ['ok' => false, 'msg' => 'Gagal membuat duel.']; }
    }
    public static function accept(\mysqli $conn, int $uid, int $duelId): bool {
        try {
            $s = $conn->prepare("UPDATE duels SET status = 'active' WHERE id = ? AND opponent_id = ? AND status = 'pending'");
            if (!$s) return false;
            $s->bind_param("ii", $duelId, $uid); $s->execute();
            $ok = $s->affected_rows > 0; $s->close();
            return $ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function decline(\mysqli $conn, int $uid, int $duelId): bool {
        try {
            $s = $conn->prepare("DELETE FROM duels WHERE id = ? AND opponent_id = ? AND status = 'pending'");
            if (!$s) return false;
            $s->bind_param("ii", $duelId, $uid); $s->execute();
            $ok = $s->affected_rows > 0; $s->close();
            return $ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function finishable(array $duel): bool {
        if (($duel['status'] ?? '') !== 'active') return false;
        if (($duel['week_key'] ?? '') !== Challenge::weekKey()) return true;
        return (time() - strtotime((string)($duel['created_at'] ?? 'now'))) >= 7 * 86400;
    }
    public static function finish(\mysqli $conn, int $uid, int $duelId): array {
        try {
            $s = $conn->prepare("SELECT d.*, c.username AS cname, o.username AS oname FROM duels d JOIN users c ON c.id = d.challenger_id JOIN users o ON o.id = d.opponent_id WHERE d.id = ? AND d.status = 'active' AND (d.challenger_id = ? OR d.opponent_id = ?)");
            if (!$s) return ['ok' => false, 'msg' => 'Duel tidak ditemukan.'];
            $s->bind_param("iii", $duelId, $uid, $uid); $s->execute();
            $d = $s->get_result()->fetch_assoc(); $s->close();
            if (!$d) return ['ok' => false, 'msg' => 'Duel tidak aktif / bukan milikmu.'];
            if (!self::finishable($d)) return ['ok' => false, 'msg' => 'Duel baru bisa diselesaikan setelah minggunya berakhir.'];
            $yw = self::yearWeek((string)$d['week_key']);
            $cx = self::weekXp($conn, (int)$d['challenger_id'], $yw);
            $ox = self::weekXp($conn, (int)$d['opponent_id'], $yw);
            if ($cx === $ox) {
                $u = $conn->prepare("UPDATE duels SET status = 'finished', decided_at = NOW() WHERE id = ? AND status = 'active'");
                $u->bind_param("i", $duelId); $u->execute(); $u->close();
                return ['ok' => true, 'msg' => "Seri! {$cx} vs {$ox} XP. Rematch minggu depan?", 'winner' => null];
            }
            $winner = $cx > $ox ? (int)$d['challenger_id'] : (int)$d['opponent_id'];
            $wname = $cx > $ox ? $d['cname'] : $d['oname'];
            $conn->begin_transaction();
            try {
                $u = $conn->prepare("UPDATE duels SET status = 'finished', winner_id = ?, decided_at = NOW() WHERE id = ? AND status = 'active'");
                $u->bind_param("ii", $winner, $duelId); $u->execute();
                if ($u->affected_rows < 1) { $u->close(); $conn->rollback(); return ['ok' => false, 'msg' => 'Duel sudah diselesaikan.']; }
                $u->close();
                Ledger::award($conn, $winner, self::WIN_XP, 'duel_win', 'duel', $duelId);
                $conn->commit();
            } catch (\Throwable $e) { $conn->rollback(); return ['ok' => false, 'msg' => 'Gagal menyelesaikan duel.']; }
            \App\Domain\Gamification\Badges::check($conn, $winner);
            $you = $winner === $uid ? 'Kamu menang!' : $wname . ' menang.';
            return ['ok' => true, 'msg' => "{$you} {$cx} vs {$ox} XP. +15 XP untuk pemenang.", 'winner' => $winner];
        } catch (\Throwable $e) { return ['ok' => false, 'msg' => 'Gagal menyelesaikan duel.']; }
    }
    public static function myDuels(\mysqli $conn, int $uid): array {
        try {
            $s = $conn->prepare("SELECT d.*, c.username AS cname, o.username AS oname FROM duels d JOIN users c ON c.id = d.challenger_id JOIN users o ON o.id = d.opponent_id WHERE d.challenger_id = ? OR d.opponent_id = ? ORDER BY d.id DESC LIMIT 20");
            if (!$s) return [];
            $s->bind_param("ii", $uid, $uid); $s->execute();
            $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
            return $rows;
        } catch (\Throwable $e) { return []; }
    }
}
