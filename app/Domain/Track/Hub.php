<?php
namespace App\Domain\Track;

class Hub {
    /**
     * Get track-specific widget data for the hub.
     * @param \mysqli $conn
     * @param int $userId
     * @param string $track
     * @return array
     */
    public static function getWidgetData(\mysqli $conn, int $userId, string $track): array {
        $t = Tracks::normalize($track);
        return \App\Cache\Store::remember(\App\Cache\Keys::hub($userId, $t), 60, function () use ($conn, $userId, $t) {
            return self::computeWidgetData($conn, $userId, $t);
        });
    }

    public static function forget(\mysqli $conn, int $userId): void {
        \App\Cache\Store::forgetPrefix(\App\Cache\Keys::hubPrefix($userId));
    }

    private static function computeWidgetData(\mysqli $conn, int $userId, string $track): array {
        $data = [];
        $t = Tracks::normalize($track);

        if ($t === 'tkj') {
            // Widget: Recent Topologies
            $saved = [];
            try {
                $q = $conn->prepare("SELECT id, name, created_at FROM topo_saves WHERE user_id = ? ORDER BY id DESC LIMIT 5");
                if ($q) { 
                    $q->bind_param("i", $userId); 
                    $q->execute(); 
                    $saved = $q->get_result()->fetch_all(MYSQLI_ASSOC); 
                    $q->close(); 
                }
            } catch (\Throwable $e) {}
            $data['recent_topologies'] = $saved;
        }

        if ($t === 'dkv') {
            // Widget: Recent Uploaded Karya (evidence with link or note)
            $karya = [];
            try {
                $q = $conn->prepare("
                    SELECT q.title, e.url, e.created_at
                    FROM evidence_submissions e
                    JOIN quests q ON e.quest_id = q.id
                    WHERE e.user_id = ? AND ((e.url IS NOT NULL AND e.url != '') OR (e.note IS NOT NULL AND e.note != ''))
                    ORDER BY e.created_at DESC LIMIT 6
                ");
                if ($q) {
                    $q->bind_param("i", $userId);
                    $q->execute();
                    $karya = $q->get_result()->fetch_all(MYSQLI_ASSOC);
                    $q->close();
                }
            } catch (\Throwable $e) {}
            $data['recent_karya'] = $karya;
        }

        if ($t === 'rpl' || $t === 'devops') {
            // Widget: Recent Errors / Bugs
            $errors = [];
            try {
                $q = $conn->prepare("SELECT category, error_message, created_at FROM errors WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
                if ($q) {
                    $q->bind_param("i", $userId);
                    $q->execute();
                    $errors = $q->get_result()->fetch_all(MYSQLI_ASSOC);
                    $q->close();
                }
            } catch (\Throwable $e) {}
            $data['recent_bugs'] = $errors;
        }
        
        // Widget: Track progress (from quest system)
        $data['track_progress'] = self::getTrackProgress($conn, $userId, $t);

        return $data;
    }

    private static function getTrackProgress(\mysqli $conn, int $userId, string $track): array {
        $stats = ['done' => 0, 'total' => 1];
        try {
            $stmt = $conn->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM user_quests uq JOIN quests q ON q.id = uq.quest_id WHERE uq.user_id = ? AND ((q.user_id IS NULL AND (q.track = ? OR q.track = 'all' OR q.track IS NULL OR q.track = '')) OR (q.user_id = ? AND (q.track = ? OR q.track IS NULL OR q.track = '')))) AS done,
                    (SELECT COUNT(*) FROM quests WHERE (user_id IS NULL AND (track = ? OR track = 'all' OR track IS NULL OR track = '')) OR (user_id = ? AND (track = ? OR track IS NULL OR track = ''))) AS total
            ");
            if ($stmt) {
                $stmt->bind_param("isissis", $userId, $track, $userId, $track, $track, $userId, $track);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                if ($row) {
                    $stats['done'] = (int)$row['done'];
                    $stats['total'] = max(1, (int)$row['total']);
                }
                $stmt->close();
            }
        } catch (\Throwable $e) {}
        
        $stats['percent'] = round(($stats['done'] / $stats['total']) * 100);
        return $stats;
    }
}
