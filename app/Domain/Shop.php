<?php
namespace App\Domain;
class Shop {
    public static function rerollWin(int $roll, int $draw): int {
        $roll=max(1,min(100,$roll)); $draw=(int)$draw;
        if ($roll<=60) return max(5,min(10,$draw));
        if ($roll<=90) return max(11,min(20,$draw));
        return max(21,min(30,$draw));
    }
    public static function rerollEv(): float { return 0.6*7.5+0.3*15.5+0.1*25.5; }
    public static function lootFrames(): array {
        return [
            ['frame' => 'aurora', 'name' => 'Aurora', 'price' => 400, 'months' => [9], 'month_label' => 'September', 'hint' => 'Edisi September · ungu-hijau langit malam'],
            ['frame' => 'specter', 'name' => 'Specter', 'price' => 400, 'months' => [10], 'month_label' => 'Oktober', 'hint' => 'Edisi Oktober · bayangan ungu'],
            ['frame' => 'solstice', 'name' => 'Solstice', 'price' => 500, 'months' => [12], 'month_label' => 'Desember', 'hint' => 'Edisi Desember · es berkilau'],
        ];
    }
    public static function lootByFrame(string $frame): ?array {
        foreach (self::lootFrames() as $item) if ($item['frame'] === $frame) return $item;
        return null;
    }
    public static function lootAvailable(array $item, ?int $month = null): bool {
        $month = $month ?? (int)date('n');
        return in_array($month, $item['months'] ?? [], true);
    }
    public static function ownedFrames(\mysqli $conn, int $uid): array {
        $out = [];
        try {
            $s = $conn->prepare("SELECT frame FROM user_frames WHERE user_id = ?");
            if (!$s) return $out;
            $s->bind_param("i", $uid); $s->execute();
            foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $out[] = (string)$r['frame'];
            $s->close();
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function grantFrame(\mysqli $conn, int $uid, string $frame): bool {
        try {
            $s = $conn->prepare("INSERT IGNORE INTO user_frames (user_id, frame) VALUES (?, ?)");
            if (!$s) return false;
            $s->bind_param("is", $uid, $frame);
            $ok = $s->execute() && $s->affected_rows > 0; $s->close();
            return (bool)$ok;
        } catch (\Throwable $e) { return false; }
    }
}
