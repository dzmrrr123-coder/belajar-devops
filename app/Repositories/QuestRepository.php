<?php
namespace App\Repositories;
class QuestRepository {
    public function __construct(private \mysqli $db) {}
    public function findForUser(int $id, int $uid): ?array {
        $s = $this->db->prepare("SELECT id,user_id,week,title,xp_reward,depends_on FROM quests WHERE id=? AND (user_id IS NULL OR user_id=?)");
        $s->bind_param("ii",$id,$uid); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r ?: null;
    }
    public function doneIds(int $uid): array {
        $out = []; $s = $this->db->prepare("SELECT quest_id FROM user_quests WHERE user_id=?");
        $s->bind_param("i",$uid); $s->execute();
        foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $out[(int)$r['quest_id']]=true;
        $s->close(); return $out;
    }
    public function prevGlobal(int $qid, int $week): ?int {
        $s = $this->db->prepare("SELECT id FROM quests WHERE user_id IS NULL AND (week < ? OR (week=? AND id<?)) ORDER BY week DESC,id DESC LIMIT 1");
        $s->bind_param("iii",$week,$week,$qid); $s->execute();
        $r = $s->get_result()->fetch_assoc(); $s->close();
        return $r ? (int)$r['id'] : null;
    }
    public function globals(): array {
        $r = $this->db->query("SELECT id,user_id,week,title,depends_on FROM quests WHERE user_id IS NULL ORDER BY week ASC,id ASC");
        return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
    }
    public function counts(int $uid): array {
        $s = $this->db->prepare("SELECT (SELECT COUNT(*) FROM user_quests WHERE user_id=?) done,(SELECT COUNT(*) FROM quests WHERE user_id IS NULL OR user_id=?) total");
        $s->bind_param("ii",$uid,$uid); $s->execute();
        $r = $s->get_result()->fetch_assoc() ?: ['done'=>0,'total'=>0]; $s->close(); return $r;
    }
}
