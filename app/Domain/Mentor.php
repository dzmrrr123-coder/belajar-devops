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
        
        // Bukti skill / proof signature per jurusan
        if ($track === 'dkv') {
            $out[] = ['icon' => 'fas fa-pen-nib', 'title' => 'Kerjakan 1 brief kreatif', 'desc' => 'Bukti skill desain nyata: pilih brief, upload karya, masuk portofolio.', 'href' => 'brief.php', 'cta' => 'Pilih brief', 'reason' => 'proof'];
        } elseif ($track === 'rpl') {
            $out[] = ['icon' => 'fas fa-code', 'title' => 'Latihan di Playground', 'desc' => 'Uji coba logic PHP, JS, & SQL secara aman dengan evaluasi instan.', 'href' => 'playground.php', 'cta' => 'Buka Playground', 'reason' => 'proof'];
        } elseif ($track === 'tkj') {
            if ($tries === 0) {
                $out[] = ['icon' => 'fas fa-network-wired', 'title' => 'Rancang topologi & subnet', 'desc' => 'Desain arsitektur jaringan & hitung alokasi host usable.', 'href' => 'topologi.php', 'cta' => 'Buka Topologi', 'reason' => 'proof'];
            } else {
                $out[] = ['icon' => 'fas fa-terminal', 'title' => 'Terminal Linux virtual', 'desc' => 'Latihan perintah Linux server dan troubleshooting jaringan.', 'href' => 'terminal.php', 'cta' => 'Terminal', 'reason' => 'proof'];
            }
        } else {
            if ($tries === 0) $out[] = ['icon' => 'fas fa-fire-extinguisher', 'title' => 'Coba 1 lab incident gratis', 'desc' => 'Bukti skill tercepat: diagnosis + fix aman, masuk passport.', 'href' => 'incident.php', 'cta' => 'Coba lab', 'reason' => 'proof'];
            elseif ($avg > 0 && $avg < 70) $out[] = ['icon' => 'fas fa-target', 'title' => "Naikkan incident avg {$avg} → 70", 'desc' => 'Ulangi lab dengan skor terendah. Sertifikat butuh ≥70.', 'href' => 'incident.php', 'cta' => 'Perbaiki', 'reason' => 'assessment'];
        }

        if ($gap !== '') $out[] = ['icon' => 'fas fa-layer-group', 'title' => "Tutup gap: {$gap}", 'desc' => 'Skill ini paling rendah di track ' . strtoupper($track) . '. Kerjakan quest + lab terkait.', 'href' => 'skills.php', 'cta' => 'Lihat skill', 'reason' => 'gap'];
        if ($questPct < 100) $out[] = ['icon' => 'fas fa-road', 'title' => 'Lanjutkan roadmap ' . strtoupper($track), 'desc' => 'Progress ' . (int)$questPct . '%. Satu quest selesai = +XP + evidence.', 'href' => 'quests.php', 'cta' => 'Lanjut quest', 'reason' => 'roadmap'];
        $out[] = ['icon' => 'fas fa-flask', 'title' => 'Latihan lab 5 menit', 'desc' => 'Latihan praktikum interaktif cepat sesuai kurikulum ' . strtoupper($track) . '.', 'href' => 'lab.php', 'cta' => 'Buka lab', 'reason' => 'practice'];
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
