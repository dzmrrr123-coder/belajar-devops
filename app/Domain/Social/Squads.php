<?php
namespace App\Domain\Social;
class Squads {
    public const MAX_MEMBERS = 5;
    public static function mySquadId(\mysqli $conn, int $uid): ?int {
        try {
            $s = $conn->prepare("SELECT squad_id FROM squad_members WHERE user_id = ?");
            if (!$s) return null;
            $s->bind_param("i", $uid); $s->execute();
            $row = $s->get_result()->fetch_assoc(); $s->close();
            return $row ? (int)$row['squad_id'] : null;
        } catch (\Throwable $e) { return null; }
    }
    public static function makeCode(): string {
        $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $c = '';
        try { for ($i = 0; $i < 6; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)]; }
        catch (\Throwable $e) { $c = substr(str_shuffle($abc . time()), 0, 6); }
        return $c;
    }
    public static function create(\mysqli $conn, int $uid, string $name): array {
        $name = mb_substr(trim(preg_replace('/\s+/', ' ', $name)), 0, 40);
        if ($name === '') return ['ok' => false, 'msg' => 'Nama squad wajib diisi.'];
        if (self::mySquadId($conn, $uid) !== null) return ['ok' => false, 'msg' => 'Kamu sudah di squad. Keluar dulu untuk buat baru.'];
        try {
            for ($t = 0; $t < 5; $t++) {
                $code = self::makeCode();
                $s = $conn->prepare("INSERT INTO squads (name, code, created_by) VALUES (?, ?, ?)");
                if (!$s) return ['ok' => false, 'msg' => 'Gagal membuat squad.'];
                $s->bind_param("ssi", $name, $code, $uid);
                if ($s->execute()) {
                    $sid = (int)$s->insert_id; $s->close();
                    $j = $conn->prepare("INSERT INTO squad_members (squad_id, user_id) VALUES (?, ?)");
                    if ($j) { $j->bind_param("ii", $sid, $uid); $j->execute(); $j->close(); }
                    return ['ok' => true, 'id' => $sid, 'code' => $code];
                }
                $s->close();
            }
            return ['ok' => false, 'msg' => 'Gagal membuat kode unik. Coba lagi.'];
        } catch (\Throwable $e) { return ['ok' => false, 'msg' => 'Gagal membuat squad.']; }
    }
    public static function joinByCode(\mysqli $conn, int $uid, string $code): array {
        $code = strtoupper(trim($code));
        if (self::mySquadId($conn, $uid) !== null) return ['ok' => false, 'msg' => 'Kamu sudah di squad.'];
        try {
            $s = $conn->prepare("SELECT id FROM squads WHERE code = ?");
            if (!$s) return ['ok' => false, 'msg' => 'Kode tidak valid.'];
            $s->bind_param("s", $code); $s->execute();
            $row = $s->get_result()->fetch_assoc(); $s->close();
            if (!$row) return ['ok' => false, 'msg' => 'Kode squad tidak ditemukan.'];
            $sid = (int)$row['id'];
            $c = $conn->prepare("SELECT COUNT(*) n FROM squad_members WHERE squad_id = ?");
            $c->bind_param("i", $sid); $c->execute();
            $n = (int)($c->get_result()->fetch_assoc()['n'] ?? 0); $c->close();
            if ($n >= self::MAX_MEMBERS) return ['ok' => false, 'msg' => 'Squad penuh (maks ' . self::MAX_MEMBERS . ').'];
            $j = $conn->prepare("INSERT IGNORE INTO squad_members (squad_id, user_id) VALUES (?, ?)");
            if (!$j) return ['ok' => false, 'msg' => 'Gagal gabung.'];
            $j->bind_param("ii", $sid, $uid); $j->execute(); $j->close();
            return ['ok' => true, 'id' => $sid];
        } catch (\Throwable $e) { return ['ok' => false, 'msg' => 'Gagal gabung squad.']; }
    }
    public static function leave(\mysqli $conn, int $uid): bool {
        try {
            $s = $conn->prepare("DELETE FROM squad_members WHERE user_id = ?");
            if (!$s) return false;
            $s->bind_param("i", $uid); $ok = $s->execute(); $s->close();
            return (bool)$ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function detail(\mysqli $conn, int $sid): ?array {
        try {
            $s = $conn->prepare("SELECT s.id, s.name, s.code, s.created_by, u.username AS creator FROM squads s JOIN users u ON u.id = s.created_by WHERE s.id = ?");
            if (!$s) return null;
            $s->bind_param("i", $sid); $s->execute();
            $row = $s->get_result()->fetch_assoc(); $s->close();
            if (!$row) return null;
            $m = $conn->prepare("SELECT u.id, u.username, u.xp, u.streak, GREATEST(0, COALESCE((SELECT SUM(amount) FROM xp_events WHERE user_id = u.id AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)), 0)) wxp FROM squad_members m JOIN users u ON u.id = m.user_id WHERE m.squad_id = ? ORDER BY wxp DESC, u.xp DESC");
            $m->bind_param("i", $sid); $m->execute();
            $row['members'] = $m->get_result()->fetch_all(MYSQLI_ASSOC); $m->close();
            $row['total_wxp'] = array_sum(array_map(fn($x) => (int)$x['wxp'], $row['members']));
            $row['goal'] = 300;
            $row['goal_pct'] = min(100, (int)round($row['total_wxp'] / 300 * 100));
            return $row;
        } catch (\Throwable $e) { return null; }
    }
}
