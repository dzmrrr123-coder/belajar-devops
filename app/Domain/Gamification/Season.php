<?php
namespace App\Domain\Gamification;
class Season {
    public const PREMIUM_PRICE = 150;
    public static function key(?int $ts = null): string { return date('Y-m', $ts ?? time()); }
    public static function label(string $key): string {
        $t = \DateTime::createFromFormat('Y-m', $key);
        return $t ? $t->format('M Y') : $key;
    }
    public static function tiers(): array {
        return [
            1 => ['xp' => 50, 'free' => 5, 'premium' => 10, 'pfreeze' => 0],
            2 => ['xp' => 120, 'free' => 10, 'premium' => 20, 'pfreeze' => 1],
            3 => ['xp' => 250, 'free' => 15, 'premium' => 30, 'pfreeze' => 0],
            4 => ['xp' => 400, 'free' => 20, 'premium' => 40, 'pfreeze' => 1],
            5 => ['xp' => 600, 'free' => 30, 'premium' => 60, 'pfreeze' => 1],
        ];
    }
    public static function seasonXp(\mysqli $conn, int $uid, string $key): int {
        try {
            $s = $conn->prepare("SELECT GREATEST(0, COALESCE(SUM(amount),0)) n FROM xp_events WHERE user_id = ? AND amount > 0 AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            if (!$s) return 0;
            $s->bind_param("is", $uid, $key); $s->execute();
            $n = (int)($s->get_result()->fetch_assoc()['n'] ?? 0); $s->close();
            return max(0, $n);
        } catch (\Throwable $e) { return 0; }
    }
    public static function hasPremium(\mysqli $conn, int $uid, string $key): bool {
        try {
            $s = $conn->prepare("SELECT 1 FROM season_premium WHERE user_id = ? AND season_key = ?");
            if (!$s) return false;
            $s->bind_param("is", $uid, $key); $s->execute();
            $ok = (bool)$s->get_result()->fetch_assoc(); $s->close();
            return $ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function claimed(\mysqli $conn, int $uid, string $key): array {
        $out = [];
        try {
            $s = $conn->prepare("SELECT tier, track FROM season_claims WHERE user_id = ? AND season_key = ?");
            if (!$s) return $out;
            $s->bind_param("is", $uid, $key); $s->execute();
            foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $out[(int)$r['tier'] . ':' . $r['track']] = true;
            $s->close();
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function buy(\mysqli $conn, int $uid, string $key): array {
        if (self::hasPremium($conn, $uid, $key)) return ['ok' => false, 'msg' => 'Pass premium sudah aktif musim ini.'];
        try {
            $s = $conn->prepare("SELECT xp FROM users WHERE id = ?");
            if (!$s) return ['ok' => false, 'msg' => 'Gagal cek saldo.'];
            $s->bind_param("i", $uid); $s->execute();
            $bal = (int)($s->get_result()->fetch_assoc()['xp'] ?? 0); $s->close();
            if ($bal < self::PREMIUM_PRICE) return ['ok' => false, 'msg' => 'XP kurang. Butuh ' . self::PREMIUM_PRICE . ' XP.'];
            $conn->begin_transaction();
            try {
                $ins = $conn->prepare("INSERT IGNORE INTO season_premium (user_id, season_key) VALUES (?, ?)");
                if (!$ins) throw new \Exception('db');
                $ins->bind_param("is", $uid, $key); $ins->execute();
                if ($ins->affected_rows < 1) { $ins->close(); $conn->rollback(); return ['ok' => false, 'msg' => 'Pass premium sudah aktif musim ini.']; }
                $ins->close();
                Ledger::award($conn, $uid, -self::PREMIUM_PRICE, 'season_premium');
                $conn->commit();
                return ['ok' => true, 'msg' => 'Pass premium aktif! Klaim hadiah emasmu.'];
            } catch (\Throwable $e) { $conn->rollback(); return ['ok' => false, 'msg' => 'Gagal membeli pass.']; }
        } catch (\Throwable $e) { return ['ok' => false, 'msg' => 'Gagal membeli pass.']; }
    }
    public static function claim(\mysqli $conn, int $uid, string $key, int $tier, string $track): array {
        $tiers = self::tiers();
        if (!isset($tiers[$tier])) return ['ok' => false, 'msg' => 'Tier tidak valid.'];
        if ($track !== 'free' && $track !== 'premium') return ['ok' => false, 'msg' => 'Track tidak valid.'];
        if ($track === 'premium' && !self::hasPremium($conn, $uid, $key)) return ['ok' => false, 'msg' => 'Butuh pass premium.'];
        if (self::seasonXp($conn, $uid, $key) < $tiers[$tier]['xp']) return ['ok' => false, 'msg' => 'XP musim belum cukup.'];
        $conn->begin_transaction();
        try {
            $ins = $conn->prepare("INSERT IGNORE INTO season_claims (user_id, season_key, tier, track) VALUES (?, ?, ?, ?)");
            if (!$ins) throw new \Exception('db');
            $ins->bind_param("siis", $uid, $key, $tier, $track); $ins->execute();
            if ($ins->affected_rows < 1) { $ins->close(); $conn->rollback(); return ['ok' => false, 'msg' => 'Sudah diklaim.']; }
            $ins->close();
            $gain = $track === 'premium' ? (int)$tiers[$tier]['premium'] : (int)$tiers[$tier]['free'];
            Ledger::award($conn, $uid, $gain, 'season_claim');
            if ($track === 'premium' && (int)$tiers[$tier]['pfreeze'] > 0) {
                $up = $conn->prepare("UPDATE users SET freeze_tokens = LEAST(3, freeze_tokens + ?) WHERE id = ?");
                if ($up) { $fz = (int)$tiers[$tier]['pfreeze']; $up->bind_param("ii", $fz, $uid); $up->execute(); $up->close(); }
            }
            $conn->commit();
        } catch (\Throwable $e) { $conn->rollback(); return ['ok' => false, 'msg' => 'Gagal klaim.']; }
        Badges::check($conn, $uid);
        $extra = ($track === 'premium' && (int)$tiers[$tier]['pfreeze'] > 0) ? ' + ' . (int)$tiers[$tier]['pfreeze'] . ' freeze' : '';
        return ['ok' => true, 'msg' => "Tier {$tier} diklaim! +{$gain} XP{$extra}."];
    }
    public static function tiersClaimed(\mysqli $conn, int $uid): int {
        try {
            $s = $conn->prepare("SELECT MAX(n) m FROM (SELECT COUNT(DISTINCT tier) n FROM season_claims WHERE user_id = ? GROUP BY season_key) t");
            if (!$s) return 0;
            $s->bind_param("i", $uid); $s->execute();
            $n = (int)($s->get_result()->fetch_assoc()['m'] ?? 0); $s->close();
            return $n;
        } catch (\Throwable $e) { return 0; }
    }
}
