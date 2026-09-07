<?php
namespace App\Domain\Incident;
class IncidentBank {
    public static function all(): array {
        return [
            [
                'slug' => 'deploy-crash-loop',
                'title' => 'Deploy CrashLoop: container restart terus',
                'skill' => 'Docker',
                'difficulty' => 'pemula',
                'objective' => 'Pulihkan deploy dalam 5 menit tanpa downtime tambahan.',
                'est_minutes' => 10,
                'story' => 'Kemarin deploy aman. Pagi ini pod restart 17x. User 500. CTO tanya: kapan pulih?',
                'log_text' => "[deploy] pulling image app:v42 OK\n[deploy] container started\n[app] FATAL: DATABASE_URL is not set\n[app] exit code 1\n[kube] BackOff: RestartCount=17",
                'diagnosis_q' => 'Dari log, apa penyebab paling mungkin?',
                'diagnosis_opts' => ['Image corrupt / registry down', 'ENV DATABASE_URL belum di-set di deployment', 'CPU limit terlalu kecil', 'Port 443 diblokir firewall'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Tindakan recovery paling aman pertama?',
                'fix_opts' => ['Scale replica ke 10 biar ada yang hidup', 'Rollback ke v41 tanpa cek ENV', 'Set ENV DATABASE_URL yang benar, rollout restart, verifikasi /health', 'Hapus volume database biar fresh'],
                'fix_correct' => 2,
                'hint_lvl1' => 'Fokus ke baris FATAL + exit code 1. Itu config atau code?',
                'hint_lvl2' => 'Bandingkan ENV v41 vs v42. Eliminasi opsi scale/hapus volume — itu menambah risiko.',
                'explanation' => 'FATAL DATABASE_URL = config missing, bukan image rusak. Urutan aman: diff ENV, set ENV, rollout restart, cek /health + restart count 0.',
                'feedback_ok' => 'Tepat. FATAL DATABASE_URL is not set = config, bukan code. Fix aman: set ENV -> rollout -> cek /health -> monitor restart count 0.',
                'feedback_fail' => 'Kunci: exit 1 + FATAL DATABASE_URL. Jangan scale/rollback buta. Urutan aman: cek diff ENV v41 vs v42, set ENV, rollout, verifikasi health & log.',
                'time_limit' => 300,
                'xp_reward' => 50,
                'is_pro' => 0,
            ],
            [
                'slug' => 'pipeline-merah-env',
                'title' => 'Pipeline Merah: test lolos lokal, gagal di CI',
                'skill' => 'Git',
                'difficulty' => 'menengah',
                'objective' => 'Hijaukan pipeline tanpa skip migrate.',
                'est_minutes' => 10,
                'story' => 'PR mentok. CI merah di step migrate. Lokal hijau. Rilis Jumat tertahan.',
                'log_text' => "[ci] npm test OK (42 passed)\n[ci] running migrations...\n[ci] ERROR: relation \"users\" already exists\n[ci] exit 1",
                'diagnosis_q' => 'Diagnosis paling tepat?',
                'diagnosis_opts' => ['Runner CI kehabisan RAM', 'Migrasi tidak idempoten / urutan migrasi beda dengan lokal', 'Node version beda minor', 'Cache npm basi'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Fix paling aman?',
                'fix_opts' => ['Rerun pipeline sampai hijau', 'Hapus DB CI manual tiap run', 'Buat migrasi idempoten (IF NOT EXISTS), kunci versi, test fresh + rerun', 'Skip migrate di CI'],
                'fix_correct' => 2,
                'hint_lvl1' => 'already exists = migrasi jalan 2x atau urutan beda. Di mana beda lokal vs CI?',
                'hint_lvl2' => 'Coret rerun/skip. Cari kata idempoten + lock version.',
                'explanation' => 'Drift lokal incremental vs CI fresh. Fix: IF NOT EXISTS, kunci versi migrasi, uji dari DB kosong.',
                'feedback_ok' => 'Benar. Classic drift: lokal incremental, CI fresh. Solusi: migrasi idempoten + lock version + uji dari nol.',
                'feedback_fail' => 'Fokus ke already exists: migrasi jalan 2x / urutan beda. Jangan rerun buta atau skip migrate. Buat idempoten dan reproducible.',
                'time_limit' => 300,
                'xp_reward' => 60,
                'is_pro' => 1,
            ],
            [
                'slug' => 'yaml-indent-500',
                'title' => 'YAML Repair: deploy 500 karena indentasi',
                'skill' => 'Docker',
                'difficulty' => 'pemula',
                'objective' => 'Perbaiki manifest agar deploy valid dalam 10 menit.',
                'est_minutes' => 10,
                'story' => 'Apply manifest selalu error validasi. Service 500. Tim bingung: code tidak diubah.',
                'log_text' => "[kubectl] error: mapping values are not allowed here\n[app] env:\n[app] - name: PORT\n[app] value: 8080\n[app]  - name: DATABASE_URL\n[ci] deploy failed: exit 1",
                'diagnosis_q' => 'Penyebab paling mungkin?',
                'diagnosis_opts' => ['Image tag salah', 'Indentasi YAML / struktur env list rusak', 'Secret belum base64', 'Ingress host typo'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Fix paling aman?',
                'fix_opts' => ['Edit manual di server lalu apply ulang', 'Validasi dengan yamllint + kubeval, perbaiki indent, apply dry-run lalu rollout', 'Hapus namespace dan buat ulang', 'Scale down ke 0 lalu up'],
                'fix_correct' => 1,
                'hint_lvl1' => 'Lihat spasi ekstra sebelum "- name: DATABASE_URL". YAML sensitif indent.',
                'hint_lvl2' => 'Jangan edit di server. Validasi lokal + dry-run dulu.',
                'explanation' => 'List YAML harus sejajar. Fix: samakan indent, lint, dry-run, baru apply. Simpan manifest di Git.',
                'feedback_ok' => 'Tepat. Indentasi list env merusak parsing. Lint + dry-run mencegah 500 berulang.',
                'feedback_fail' => 'Kunci: mapping values are not allowed = indentasi. Jangan hapus namespace. Lint, perbaiki, dry-run.',
                'time_limit' => 300,
                'xp_reward' => 50,
                'is_pro' => 1,
            ],
            [
                'slug' => 'ci-lama-cache',
                'title' => 'Pipeline Debugging: build 25 menit',
                'skill' => 'Git',
                'difficulty' => 'menengah',
                'objective' => 'Turunkan build <8 menit tanpa merusak cache.',
                'est_minutes' => 12,
                'story' => 'Pipeline makin lambat. Tiap PR nunggu 25 menit. Developer mulai skip CI.',
                'log_text' => "[ci] restore cache: miss\n[ci] npm ci: 14m02s\n[ci] docker build: no layer cache\n[ci] total: 25m11s",
                'diagnosis_q' => 'Bottleneck utama?',
                'diagnosis_opts' => ['Test terlalu banyak', 'Cache miss: lockfile tidak dipakai + layer Docker tidak di-cache', 'Runner terlalu kecil', 'Slack notif lambat'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Optimasi paling aman?',
                'fix_opts' => ['Hapus test biar cepat', 'Kunci dependency (lockfile), cache npm + Docker layer, paralel job test/build', 'Beli runner 10x tanpa ukur', 'Jalankan CI hanya di main'],
                'fix_correct' => 1,
                'hint_lvl1' => 'miss + no layer cache = tidak ada yang dipakai ulang. Apa yang harus dikunci?',
                'hint_lvl2' => 'Coret hapus test / skip branch. Cari cache + paralel yang aman.',
                'explanation' => 'Kunci lockfile, cache npm + BuildKit layer, split job. Ukur sebelum/ sesudah.',
                'feedback_ok' => 'Benar. Cache + layer + paralel memangkas 25→8 menit tanpa kurangi kualitas.',
                'feedback_fail' => 'Fokus ke cache miss. Jangan hapus test. Kunci deps, cache layer, paralelkan.',
                'time_limit' => 360,
                'xp_reward' => 60,
                'is_pro' => 1,
            ],
            [
                'slug' => 'arsitektur-antrean',
                'title' => 'Architecture Decision: antrean membludak',
                'skill' => 'Networking',
                'difficulty' => 'mahir',
                'objective' => 'Pilih desain yang tahan lonjakan 10x tanpa data loss.',
                'est_minutes' => 15,
                'story' => 'Flash sale: worker timeout, order ganda, DB 100% CPU. Pilih arsitektur sebelum incident berulang.',
                'log_text' => "[api] p99 8.2s, timeout 40%\n[worker] retry storm x5\n[db] CPU 98%, deadlocks 120\n[queue] depth 45k, age 22m",
                'diagnosis_q' => 'Root cause arsitektur?',
                'diagnosis_opts' => ['Kurang replica API saja', 'Sync request + retry tanpa idempotency + tanpa backpressure', 'Log terlalu verbose', 'CDN belum dipakai'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Desain paling tahan?',
                'fix_opts' => ['Naikkan timeout ke 120s', 'Antrekan + idempotency key + rate limit + autoscale worker + circuit breaker', 'Matikan retry', 'Pindah semua ke cron malam'],
                'fix_correct' => 1,
                'hint_lvl1' => 'retry storm + deadlock = sync + tanpa idempotency. Apa yang menahan beban?',
                'hint_lvl2' => 'Coret naikkan timeout. Cari queue + idempotency + backpressure.',
                'explanation' => 'Tahan 10x: async queue, idempotency key, rate limit, autoscale, breaker. Ukur p99 + queue age.',
                'feedback_ok' => 'Tepat. Async + idempotency + backpressure menghentikan retry storm dan deadlocks.',
                'feedback_fail' => 'Kunci: retry tanpa idempotency + sync DB. Solusi: queue, idempotency, limit, autoscale.',
                'time_limit' => 450,
                'xp_reward' => 80,
                'is_pro' => 1,
            ],
        ];
    }
    public static function score(bool $diagOk, bool $fixOk, int $durationSec, int $timeLimit): array {
        $mistakes = (!$diagOk ? 1 : 0) + (!$fixOk ? 1 : 0);
        $base = 100 - $mistakes * 35;
        if ($durationSec > $timeLimit) $base -= 15;
        elseif ($durationSec > (int)($timeLimit * 0.6)) $base -= 5;
        $score = max(0, min(100, $base));
        $grade = $score >= 90 ? 'S' : ($score >= 70 ? 'A' : ($score >= 50 ? 'B' : 'C'));
        return ['score' => $score, 'mistakes' => $mistakes, 'grade' => $grade];
    }
    public static function xpFor(int $score, int $maxXp): int {
        if ($score >= 90) return $maxXp;
        if ($score >= 70) return (int)round($maxXp * 0.7);
        if ($score >= 50) return (int)round($maxXp * 0.4);
        return 5;
    }
    public static function ensureSeed(\mysqli $conn): void {
        try {
            @$conn->query("CREATE TABLE IF NOT EXISTS `incident_challenges` (`id` INT AUTO_INCREMENT PRIMARY KEY, `slug` VARCHAR(64) NOT NULL UNIQUE, `title` VARCHAR(120) NOT NULL, `skill` VARCHAR(32) NOT NULL DEFAULT 'Docker', `difficulty` ENUM('pemula','menengah','mahir') NOT NULL DEFAULT 'pemula', `story` TEXT NOT NULL, `log_text` TEXT NOT NULL, `diagnosis_q` VARCHAR(255) NOT NULL, `diagnosis_opts` TEXT NOT NULL, `diagnosis_correct` TINYINT NOT NULL DEFAULT 0, `fix_q` VARCHAR(255) NOT NULL, `fix_opts` TEXT NOT NULL, `fix_correct` TINYINT NOT NULL DEFAULT 0, `feedback_ok` TEXT NOT NULL, `feedback_fail` TEXT NOT NULL, `time_limit` INT NOT NULL DEFAULT 300, `xp_reward` INT NOT NULL DEFAULT 50, `is_pro` TINYINT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            @$conn->query("ALTER TABLE `incident_challenges` ADD COLUMN `objective` VARCHAR(255) NOT NULL DEFAULT ''");
            @$conn->query("ALTER TABLE `incident_challenges` ADD COLUMN `est_minutes` INT NOT NULL DEFAULT 10");
            @$conn->query("ALTER TABLE `incident_challenges` ADD COLUMN `hint_lvl1` TEXT NOT NULL");
            @$conn->query("ALTER TABLE `incident_challenges` ADD COLUMN `hint_lvl2` TEXT NOT NULL");
            @$conn->query("ALTER TABLE `incident_challenges` ADD COLUMN `explanation` TEXT NOT NULL");
            @$conn->query("CREATE TABLE IF NOT EXISTS `incident_attempts` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `challenge_id` INT NOT NULL, `score` INT NOT NULL DEFAULT 0, `duration_sec` INT NOT NULL DEFAULT 0, `mistakes` INT NOT NULL DEFAULT 0, `diagnosis_ok` TINYINT NOT NULL DEFAULT 0, `fix_ok` TINYINT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `fk_incident_attempt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_incident_attempt_ch` FOREIGN KEY (`challenge_id`) REFERENCES `incident_challenges` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            @$conn->query("CREATE TABLE IF NOT EXISTS `pro_subscriptions` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `plan` VARCHAR(16) NOT NULL DEFAULT 'monthly', `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `ends_at` DATETIME NULL, `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active', `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `fk_pro_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $cols = [];
            try {
                $cr = $conn->query("SHOW COLUMNS FROM incident_challenges");
                if ($cr) { while ($row = $cr->fetch_assoc()) $cols[$row['Field']] = true; $cr->free(); }
            } catch (\Throwable $e) {}
            $hasNew = isset($cols['hint_lvl1']);
            $ins = $conn->prepare("INSERT IGNORE INTO incident_challenges (slug, title, skill, difficulty, objective, est_minutes, story, log_text, diagnosis_q, diagnosis_opts, diagnosis_correct, fix_q, fix_opts, fix_correct, hint_lvl1, hint_lvl2, explanation, feedback_ok, feedback_fail, time_limit, xp_reward, is_pro) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$ins) return;
            foreach (self::all() as $c) {
                $dopts = json_encode($c['diagnosis_opts'], JSON_UNESCAPED_UNICODE);
                $fopts = json_encode($c['fix_opts'], JSON_UNESCAPED_UNICODE);
                if ($hasNew) {
                    $ins->bind_param("ssssissssisssisssssiii", $c['slug'], $c['title'], $c['skill'], $c['difficulty'], $c['objective'], $c['est_minutes'], $c['story'], $c['log_text'], $c['diagnosis_q'], $dopts, $c['diagnosis_correct'], $c['fix_q'], $fopts, $c['fix_correct'], $c['hint_lvl1'], $c['hint_lvl2'], $c['explanation'], $c['feedback_ok'], $c['feedback_fail'], $c['time_limit'], $c['xp_reward'], $c['is_pro']);
                } else {
                    continue;
                }
                $ins->execute();
            }
            $ins->close();
            try {
                $up = $conn->prepare("UPDATE incident_challenges SET objective = ?, est_minutes = ?, hint_lvl1 = ?, hint_lvl2 = ?, explanation = ? WHERE slug = ? AND (objective = '' OR hint_lvl1 = '')");
                if ($up) {
                    foreach (self::all() as $c) {
                        $up->bind_param("sissss", $c['objective'], $c['est_minutes'], $c['hint_lvl1'], $c['hint_lvl2'], $c['explanation'], $c['slug']);
                        $up->execute();
                    }
                    $up->close();
                }
            } catch (\Throwable $e) {}
        } catch (\Throwable $e) {}
    }
}
