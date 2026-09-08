<?php
namespace App\Domain;
use App\Domain\Quest\QuestPolicy;
class NextAction {
    public static function pick(array $s): array {
        $claimN = (int)($s['claimable_n'] ?? 0);
        if ($claimN > 0) {
            $xp = (int)($s['claimable_xp'] ?? 0);
            return ['type' => 'claim', 'title' => "Klaim +{$xp} XP", 'desc' => "{$claimN} misi selesai menunggumu", 'href' => null, 'cta' => 'Klaim'];
        }
        if (!empty($s['streak_broken'])) {
            return ['type' => 'recovery', 'title' => 'Misi comeback', 'desc' => 'streak putus? mulai lagi dari 1 quest kecil', 'href' => 'quests.php', 'cta' => 'Mulai lagi'];
        }
        if ((int)($s['due_reviews'] ?? 0) > 0) {
            $n = (int)$s['due_reviews'];
            return ['type' => 'review', 'title' => "Sikat {$n} review", 'desc' => 'cuma 2 menit, biar tak menumpuk', 'href' => 'review.php', 'cta' => 'Review'];
        }
        if (!empty($s['next_quest']) && is_array($s['next_quest'])) {
            $q = $s['next_quest'];
            $t = mb_strimwidth((string)($q['title'] ?? 'Quest'), 0, 60, '...');
            return ['type' => 'quest', 'title' => "Lanjutkan: {$t}", 'desc' => '+' . (int)($q['xp_reward'] ?? 0) . ' XP menanti', 'href' => 'quests.php', 'cta' => 'Kerjakan', 'quest_id' => (int)($q['id'] ?? 0)];
        }
        if ((int)($s['pomo_today'] ?? 0) === 0) {
            return ['type' => 'focus', 'title' => 'Mulai sesi fokus', 'desc' => '25 menit · +10 XP · lanjutkan quest minggu ini', 'href' => 'timer.php', 'cta' => 'Fokus'];
        }
        return ['type' => 'digest', 'title' => 'Minggu ini beres!', 'desc' => 'lihat ringkasan & rencanakan berikutnya', 'href' => 'digest.php', 'cta' => 'Ringkasan'];
    }
    public static function signals(\mysqli $conn, int $uid): array {
        $out = ['claimable_n' => 0, 'claimable_xp' => 0, 'due_reviews' => 0, 'pomo_today' => 0, 'next_quest' => null, 'streak_broken' => false];
        try {
            $m = \App\Domain\Gamification\Missions::status($conn, $uid);
            foreach ($m as $mm) {
                if (!empty($mm['done']) && empty($mm['claimed'])) { $out['claimable_n']++; $out['claimable_xp'] += (int)$mm['xp']; }
            }
            $q = $conn->prepare("SELECT (SELECT COUNT(*) FROM reviews WHERE user_id = ? AND next_due <= CURDATE()) dr, (SELECT COUNT(*) FROM pomodoro_sessions WHERE user_id = ? AND completed_at >= CURDATE() AND completed_at < CURDATE() + INTERVAL 1 DAY) pt");
            if ($q) {
                $q->bind_param("ii", $uid, $uid); $q->execute();
                $r = $q->get_result()->fetch_assoc() ?: []; $q->close();
                $out['due_reviews'] = (int)($r['dr'] ?? 0); $out['pomo_today'] = (int)($r['pt'] ?? 0);
            }
            $userTrack = user_track($conn, $uid);
            $g = $conn->prepare("SELECT id, week, user_id, title, xp_reward, depends_on FROM quests WHERE user_id IS NULL AND (track = ? OR track = 'all' OR track IS NULL OR track = '') ORDER BY week ASC, id ASC");
            if (!$g) {
                $g = $conn->prepare("SELECT id, week, user_id, title, xp_reward, depends_on FROM quests WHERE user_id IS NULL ORDER BY week ASC, id ASC");
            } else {
                $g->bind_param("s", $userTrack);
            }
            if ($g) {
                $g->execute();
                $globals = $g->get_result()->fetch_all(MYSQLI_ASSOC); $g->close();
                $done = [];
                $dq = $conn->prepare("SELECT quest_id, completed_at FROM user_quests WHERE user_id = ?");
                if ($dq) {
                    $dq->bind_param("i", $uid); $dq->execute();
                    foreach ($dq->get_result()->fetch_all(MYSQLI_ASSOC) as $dr) {
                        $done[(int)$dr['quest_id']] = true;
                        foreach ($globals as &$gg) {
                            if ((int)$gg['id'] === (int)$dr['quest_id']) $gg['completed_at'] = $dr['completed_at'];
                        }
                        unset($gg);
                    }
                    $dq->close();
                }
                $out['next_quest'] = QuestPolicy::nextUnlocked($globals, $done, QuestPolicy::prevMap($globals));
            }
            $u = $conn->prepare("SELECT streak, last_active_date FROM users WHERE id = ?");
            if ($u) {
                $u->bind_param("i", $uid); $u->execute();
                $ur = $u->get_result()->fetch_assoc() ?: []; $u->close();
                $last = (string)($ur['last_active_date'] ?? '');
                $out['streak_broken'] = $last !== '' && $last < date('Y-m-d', strtotime('-1 day')) && (int)($ur['streak'] ?? 0) <= 1;
            }
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function resolve(\mysqli $conn, int $uid): array {
        return self::pick(self::signals($conn, $uid));
    }
}
