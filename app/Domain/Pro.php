<?php
namespace App\Domain;
class Pro {
    public static function plans(): array {
        return [
            'monthly' => ['slug' => 'monthly', 'name' => 'Pro Bulanan', 'price' => 29000, 'days' => 30],
            'yearly' => ['slug' => 'yearly', 'name' => 'Pro Tahunan', 'price' => 199000, 'days' => 365],
            'team' => ['slug' => 'team', 'name' => 'Tim / Kampus', 'price' => 499000, 'days' => 365],
        ];
    }
    public static function plan(string $slug): ?array {
        $p = self::plans();
        return $p[$slug] ?? null;
    }
    public static function isPro(array $user): bool {
        if (!empty($user['is_pro'])) {
            $until = (string)($user['pro_until'] ?? '');
            if ($until === '' || $until === '0000-00-00 00:00:00') return true;
            return strtotime($until) > time();
        }
        return false;
    }
    public static function hasActiveSub(\mysqli $conn, int $uid): bool {
        try {
            $s = $conn->prepare("SELECT id FROM pro_subscriptions WHERE user_id = ? AND status = 'active' AND (ends_at IS NULL OR ends_at > NOW()) LIMIT 1");
            if (!$s) return false;
            $s->bind_param("i", $uid); $s->execute();
            $ok = (bool)$s->get_result()->fetch_assoc(); $s->close();
            return $ok;
        } catch (\Throwable $e) { return false; }
    }
    public static function canAccess(\mysqli $conn, array $user, string $feature): bool {
        if (self::isPro($user)) return true;
        try {
            if (!empty($user['id']) && self::hasActiveSub($conn, (int)$user['id'])) return true;
        } catch (\Throwable $e) {}
        return false;
    }
    public static function hintAllowed(int $hintCount, bool $hasAttempted): bool {
        if ($hintCount <= 0) return true;
        if ($hintCount === 1) return $hasAttempted;
        return false;
    }
    public static function rupiah(int $n): string {
        return 'Rp' . number_format($n, 0, ',', '.');
    }
    public static function features(string $tier): array {
        if ($tier === 'pro') return ['Incident simulator + nilai', 'Sertifikat verifikasi', 'Analytics 90 hari', 'Skill passport']; 
        if ($tier === 'team') return ['Semua Pro', 'Dashboard tim', 'Assignment & laporan', 'Onboarding bootcamp'];
        return ['Quest 12 minggu', 'Misi harian', 'Leaderboard'];
    }
    public static function grantDays(string $plan): int {
        return (int)(self::plan($plan)['days'] ?? 0);
    }
    public static function cleanContact(string $s): string {
        $s = trim(preg_replace('/\s+/', ' ', (string)$s));
        return mb_substr($s, 0, 140);
    }
    public static function validPlan(string $s): bool {
        return self::plan($s) !== null || $s === 'waitlist';
    }
}
