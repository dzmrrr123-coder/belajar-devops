<?php
namespace App\Domain\Skill;
class Mastery {
    public const PER_LEVEL = 50;
    public static function level(int $xp): int { return 1 + (int)floor(max(0, $xp) / self::PER_LEVEL); }
    public static function progress(int $xp): int {
        $xp = max(0, $xp);
        return (int)round(($xp % self::PER_LEVEL) / self::PER_LEVEL * 100);
    }
    public static function need(int $xp): int {
        $xp = max(0, $xp);
        $r = $xp % self::PER_LEVEL;
        return $r === 0 ? self::PER_LEVEL : self::PER_LEVEL - $r;
    }
    public static function nodeForSkill(string $skill): ?string {
        $map = ['Linux' => 'linux', 'Git' => 'git', 'MySQL' => 'sql', 'PHP' => 'php', 'Laravel' => 'laravel', 'Docker' => 'docker', 'AWS' => 'cloud', 'Networking' => 'networking', 'Testing' => 'testing', 'Desain' => 'desain', 'Tipografi' => 'desain', 'Branding' => 'desain', 'UI/UX' => 'uiux', 'Ilustrasi' => 'desain', 'Motion' => 'motion', 'General' => null];
        return $map[$skill] ?? null;
    }
    public static function award(\mysqli $conn, int $uid, ?string $node, int $xp, string $source, ?string $refType = null, ?int $refId = null): void {
        if ($node === null || $node === '' || $xp <= 0) return;
        try {
            $e = $conn->prepare("INSERT INTO mastery_events (user_id, node, xp, source, ref_type, ref_id) VALUES (?, ?, ?, ?, ?, ?)");
            if ($e) { $e->bind_param("isissi", $uid, $node, $xp, $source, $refType, $refId); $e->execute(); $e->close(); }
            $u = $conn->prepare("INSERT INTO user_skill_mastery (user_id, node, xp) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE xp = xp + VALUES(xp)");
            if ($u) { $u->bind_param("isi", $uid, $node, $xp); $u->execute(); $u->close(); }
        } catch (\Throwable $e2) {}
    }
    public static function map(\mysqli $conn, int $uid): array {
        $out = [];
        try {
            $r = $conn->query("SELECT slug, name, icon FROM skill_nodes ORDER BY sort ASC, slug ASC");
            if ($r) { foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $out[$row['slug']] = $row + ['xp' => 0]; $r->free(); }
            if (empty($out)) return $out;
            $s = $conn->prepare("SELECT node, xp FROM user_skill_mastery WHERE user_id = ?");
            if ($s) {
                $s->bind_param("i", $uid); $s->execute();
                foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                    if (isset($out[$row['node']])) $out[$row['node']]['xp'] = (int)$row['xp'];
                }
                $s->close();
            }
            foreach ($out as $k => $v) {
                $out[$k]['level'] = self::level((int)$v['xp']);
                $out[$k]['pct'] = self::progress((int)$v['xp']);
                $out[$k]['need'] = self::need((int)$v['xp']);
            }
        } catch (\Throwable $e) {}
        return $out;
    }
}
