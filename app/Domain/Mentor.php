<?php
namespace App\Domain;
class Mentor {
    public static function recommend(array $s): array {
        $track = \App\Domain\Track\Tracks::normalize((string)($s['track'] ?? 'devops'));
        $out = [];
        $due = (int)($s['due_reviews'] ?? 0);
        $tries = (int)($s['incident_tries'] ?? 0);
        $avg = (int)($s['incident_avg'] ?? 0);
        $gap = (string)($s['skill_gap'] ?? '');
        $streakBroken = !empty($s['streak_broken']);
        $questPct = (float)($s['quest_pct'] ?? 100);
        if ($streakBroken) $out[] = ['icon' => 'fas fa-fire', 'title' => 'Misi comeback 10 menit', 'desc' => 'Streak putus. Selesaikan 1 quest kecil hari ini untuk mulai lagi.', 'href' => 'quests.php', 'cta' => 'Mulai lagi', 'reason' => 'recovery'];
        if ($due > 0) $out[] = ['icon' => 'fas fa-inbox', 'title' => "Sikat {$due} review", 'desc' => 'Review menumpuk bikin lupa. 2 menit per kartu, XP +2 per Tahu.', 'href' => 'review.php', 'cta' => 'Review', 'reason' => 'retention'];
        if ($tries === 0) $out[] = ['icon' => 'fas fa-fire-extinguisher', 'title' => 'Coba 1 lab incident gratis', 'desc' => 'Bukti skill tercepat: diagnosis + fix aman, masuk passport.', 'href' => 'incident.php', 'cta' => 'Coba lab', 'reason' => 'proof'];
        elseif ($avg > 0 && $avg < 70) $out[] = ['icon' => 'fas fa-target', 'title' => "Naikkan incident avg {$avg} → 70", 'desc' => 'Ulangi lab dengan skor terendah. Sertifikat butuh ≥70.', 'href' => 'incident.php', 'cta' => 'Perbaiki', 'reason' => 'assessment'];
        if ($gap !== '') $out[] = ['icon' => 'fas fa-layer-group', 'title' => "Tutup gap: {$gap}", 'desc' => 'Skill ini paling rendah di track ' . strtoupper($track) . '. Kerjakan quest + lab terkait.', 'href' => 'skills.php', 'cta' => 'Lihat skill', 'reason' => 'gap'];
        if ($questPct < 100) $out[] = ['icon' => 'fas fa-road', 'title' => 'Lanjutkan roadmap ' . strtoupper($track), 'desc' => 'Progress ' . (int)$questPct . '%. Satu quest selesai = +XP + evidence.', 'href' => 'quests.php', 'cta' => 'Lanjut quest', 'reason' => 'roadmap'];
        $out[] = ['icon' => 'fas fa-flask', 'title' => 'Latihan lab 5 menit', 'desc' => 'Playground RPL, simulator TKJ, atau challenge DKV sesuai track.', 'href' => 'lab.php', 'cta' => 'Buka lab', 'reason' => 'practice'];
        $seen = [];
        $final = [];
        foreach ($out as $r) {
            if (isset($seen[$r['reason']])) continue;
            $seen[$r['reason']] = true;
            $final[] = $r;
            if (count($final) >= 3) break;
        }
        return $final;
    }
}
