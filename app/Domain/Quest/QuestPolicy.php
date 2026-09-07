<?php
namespace App\Domain\Quest;
class QuestPolicy {
    public static function prevMap(array $quests): array {
        $sorted = array_values($quests);
        usort($sorted, fn($a,$b) => ((int)($a['week']??0) <=> (int)($b['week']??0)) ?: ((int)($a['id']??0) <=> (int)($b['id']??0)));
        $prev = null; $map = [];
        foreach ($sorted as $q) {
            $id = (int)($q['id'] ?? 0);
            if ($id <= 0) continue;
            if (!empty($q['user_id'])) { $map[$id] = null; continue; }
            $map[$id] = $prev; $prev = $id;
        }
        return $map;
    }
    public static function blocker(array $quest, array $doneIds, $prevId = null) {
        if (!empty($quest['completed_at']) || !empty($quest['user_id'])) return null;
        $dep = isset($quest['depends_on']) && (int)$quest['depends_on'] > 0 ? (int)$quest['depends_on'] : $prevId;
        if ($dep === null || $dep <= 0 || (int)$dep === (int)($quest['id'] ?? 0)) return null;
        return isset($doneIds[$dep]) ? null : (int)$dep;
    }
    public static function weekStats(array $qs): array {
        $total = count($qs); $done = 0;
        foreach ($qs as $q) if (!empty($q['completed_at'])) $done++;
        return ['done'=>$done,'total'=>$total,'pct'=>$total>0?(int)round($done/$total*100):0];
    }
    public static function nextUnlocked(array $sorted, array $doneIds, array $prevMap) {
        foreach ($sorted as $q) {
            if (!empty($q['completed_at'])) continue;
            if (self::blocker($q, $doneIds, $prevMap[(int)($q['id']??0)] ?? null) === null) return $q;
        }
        return null;
    }
    public static function visibleWhere(): string { return "(q.user_id IS NULL OR q.user_id = ?)"; }
}
