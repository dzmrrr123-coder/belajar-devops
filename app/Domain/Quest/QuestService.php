<?php
namespace App\Domain\Quest;
use App\Repositories\QuestRepository;
use App\Domain\Gamification\Level;
class QuestService {
    public function __construct(private \mysqli $db) {}
    public function dashboard(int $uid, int $week): array {
        $repo = new QuestRepository($this->db);
        $s = $this->db->prepare("SELECT q.*,uq.completed_at FROM quests q LEFT JOIN user_quests uq ON q.id=uq.quest_id AND uq.user_id=? WHERE q.week=? AND (q.user_id IS NULL OR q.user_id=?) ORDER BY q.id ASC");
        $s->bind_param("iii",$uid,$week,$uid); $s->execute();
        $quests = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
        $done = $repo->doneIds($uid);
        $globals = $repo->globals();
        $prev = QuestPolicy::prevMap($globals);
        $next = QuestPolicy::nextUnlocked($quests, $done, $prev);
        $counts = $repo->counts($uid);
        return ['quests'=>$quests,'doneIds'=>$done,'prevMap'=>$prev,'next'=>$next,'counts'=>$counts,'level'=>Level::calculate((int)($this->xp($uid)))];
    }
    private function xp(int $uid): int {
        $s = $this->db->prepare("SELECT xp FROM users WHERE id=?");
        $s->bind_param("i",$uid); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return (int)($r['xp'] ?? 0);
    }
}
