<?php
namespace App\Domain;
class ProVoucher {
    public static function ensureTables(\mysqli $conn): void {
        try { @$conn->query("CREATE TABLE IF NOT EXISTS `pro_vouchers` (`id` INT AUTO_INCREMENT PRIMARY KEY, `code` VARCHAR(16) NOT NULL UNIQUE, `plan` VARCHAR(16) NOT NULL DEFAULT 'monthly', `days` INT NOT NULL DEFAULT 30, `max_uses` INT NOT NULL DEFAULT 1, `used_count` INT NOT NULL DEFAULT 0, `expires_at` DATETIME NULL, `created_by` INT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (\Throwable $e) {}
        try { @$conn->query("CREATE TABLE IF NOT EXISTS `pro_redemptions` (`id` INT AUTO_INCREMENT PRIMARY KEY, `voucher_id` INT NOT NULL, `user_id` INT NOT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `uq_redemption` UNIQUE (`voucher_id`, `user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"); } catch (\Throwable $e) {}
    }
    public static function makeCode(): string {
        $abc = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $c = '';
        try { for ($i = 0; $i < 8; $i++) $c .= $abc[random_int(0, strlen($abc) - 1)]; }
        catch (\Throwable $e) { $c = substr(str_shuffle($abc . time()), 0, 8); }
        return 'VQ-' . $c;
    }
    public static function create(\mysqli $conn, int $adminId, string $plan, int $days, int $maxUses, ?string $expiresAt): array {
        self::ensureTables($conn);
        $plan = Pro::plan($plan) ? $plan : 'monthly';
        $days = max(1, min(3650, $days));
        $maxUses = max(1, min(10000, $maxUses));
        try {
            for ($t = 0; $t < 5; $t++) {
                $code = self::makeCode();
                $ins = $conn->prepare("INSERT INTO pro_vouchers (code, plan, days, max_uses, expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$ins) return ['ok' => false, 'msg' => 'Gagal membuat voucher.'];
                $ins->bind_param("ssiisi", $code, $plan, $days, $maxUses, $expiresAt, $adminId);
                if ($ins->execute()) { $ins->close(); return ['ok' => true, 'code' => $code]; }
                $ins->close();
            }
        } catch (\Throwable $e) {}
        return ['ok' => false, 'msg' => 'Gagal membuat kode unik. Coba lagi.'];
    }
    public static function createBulk(\mysqli $conn, int $adminId, string $plan, int $days, int $maxUses, ?string $expiresAt, int $qty): array {
        $qty = max(1, min(100, $qty));
        $codes = [];
        for ($i = 0; $i < $qty; $i++) {
            $r = self::create($conn, $adminId, $plan, $days, $maxUses, $expiresAt);
            if (!$r['ok']) break;
            $codes[] = $r['code'];
        }
        return $codes;
    }
    public static function grantPro(\mysqli $conn, int $uid, string $plan, int $days): bool {
        $days = max(1, min(3650, $days));
        try {
            $conn->begin_transaction();
            $up = $conn->prepare("UPDATE users SET is_pro = 1, pro_until = GREATEST(COALESCE(pro_until, NOW()), NOW()) + INTERVAL ? DAY, pro_plan = ? WHERE id = ?");
            if (!$up) { $conn->rollback(); return false; }
            $up->bind_param("isi", $days, $plan, $uid);
            $up->execute(); $up->close();
            $ends = date('Y-m-d H:i:s', strtotime("+$days days"));
            $sub = $conn->prepare("INSERT INTO pro_subscriptions (user_id, plan, ends_at, status) VALUES (?, ?, ?, 'active')");
            if ($sub) { $sub->bind_param("iss", $uid, $plan, $ends); $sub->execute(); $sub->close(); }
            $conn->commit();
            \App\Cache\Invalidator::userChanged($uid);
            return true;
        } catch (\Throwable $e) {
            try { $conn->rollback(); } catch (\Throwable $e2) {}
            return false;
        }
    }
    public static function revokePro(\mysqli $conn, int $uid): bool {
        try {
            $up = $conn->prepare("UPDATE users SET is_pro = 0, pro_until = NULL, pro_plan = NULL WHERE id = ?");
            if (!$up) return false;
            $up->bind_param("i", $uid); $up->execute(); $up->close();
            $ex = $conn->prepare("UPDATE pro_subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'");
            if ($ex) { $ex->bind_param("i", $uid); $ex->execute(); $ex->close(); }
            \App\Cache\Invalidator::userChanged($uid);
            return true;
        } catch (\Throwable $e) { return false; }
    }
    public static function redeem(\mysqli $conn, int $uid, string $code): array {
        self::ensureTables($conn);
        $code = strtoupper(trim($code));
        try {
            $s = $conn->prepare("SELECT id, plan, days, max_uses, used_count, expires_at FROM pro_vouchers WHERE code = ?");
            if (!$s) return ['ok' => false, 'msg' => 'Voucher tidak valid.'];
            $s->bind_param("s", $code); $s->execute();
            $v = $s->get_result()->fetch_assoc(); $s->close();
            if (!$v) return ['ok' => false, 'msg' => 'Kode voucher tidak ditemukan.'];
            if (!empty($v['expires_at']) && strtotime($v['expires_at']) < time()) return ['ok' => false, 'msg' => 'Voucher sudah kedaluwarsa.'];
            if ((int)$v['used_count'] >= (int)$v['max_uses']) return ['ok' => false, 'msg' => 'Voucher sudah mencapai batas penggunaan.'];
            $chk = $conn->prepare("SELECT id FROM pro_redemptions WHERE voucher_id = ? AND user_id = ?");
            if ($chk) { $chk->bind_param("ii", $v['id'], $uid); $chk->execute(); $dup = (bool)$chk->get_result()->fetch_assoc(); $chk->close(); if ($dup) return ['ok' => false, 'msg' => 'Kamu sudah memakai voucher ini.']; }
            $conn->begin_transaction();
            $bump = $conn->prepare("UPDATE pro_vouchers SET used_count = used_count + 1 WHERE id = ? AND used_count < max_uses");
            if (!$bump) { $conn->rollback(); return ['ok' => false, 'msg' => 'Gagal memakai voucher.']; }
            $bump->bind_param("i", $v['id']); $bump->execute();
            if ($bump->affected_rows !== 1) { $bump->close(); $conn->rollback(); return ['ok' => false, 'msg' => 'Voucher sudah mencapai batas penggunaan.']; }
            $bump->close();
            $rec = $conn->prepare("INSERT INTO pro_redemptions (voucher_id, user_id) VALUES (?, ?)");
            if ($rec) { $rec->bind_param("ii", $v['id'], $uid); $rec->execute(); $rec->close(); }
            $conn->commit();
        } catch (\Throwable $e) {
            try { $conn->rollback(); } catch (\Throwable $e2) {}
            return ['ok' => false, 'msg' => 'Gagal memakai voucher. Coba lagi.'];
        }
        if (!self::grantPro($conn, $uid, (string)$v['plan'], (int)$v['days'])) return ['ok' => false, 'msg' => 'Voucher tercatat, tetapi aktivasi Pro gagal. Hubungi admin.'];
        return ['ok' => true, 'msg' => 'Pro aktif ' . (int)$v['days'] . ' hari via voucher!'];
    }
    public static function list(\mysqli $conn, int $limit = 50): array {
        self::ensureTables($conn);
        try {
            $s = $conn->prepare("SELECT v.code, v.plan, v.days, v.max_uses, v.used_count, v.expires_at, v.created_at, u.username AS by_name FROM pro_vouchers v LEFT JOIN users u ON u.id = v.created_by ORDER BY v.id DESC LIMIT ?");
            if (!$s) return [];
            $s->bind_param("i", $limit); $s->execute();
            $out = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
            return $out;
        } catch (\Throwable $e) { return []; }
    }
}
