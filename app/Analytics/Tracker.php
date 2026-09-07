<?php
namespace App\Analytics;
class Tracker {
    public static function track(\mysqli $conn, int $uid, string $event, array $meta = []): void {
        if ($uid <= 0 || !in_array($event, Events::all(), true)) return;
        try {
            $sid = mb_substr((string)(session_id() ?: ''), 0, 128);
            $surface = mb_substr(basename((string)($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '')), 0, 64);
            $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) $json = '{}';
            $json = mb_substr($json, 0, 2000);
            $s = $conn->prepare("INSERT INTO analytics_events (user_id, session_id, event, surface, meta) VALUES (?, ?, ?, ?, ?)");
            if (!$s) return;
            $s->bind_param("issss", $uid, $sid, $event, $surface, $json);
            $s->execute();
            $s->close();
        } catch (\Throwable $e) {}
    }
}
