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
                'slug' => 'slow-query-100x',
                'title' => 'Slow Query: halaman produk 12 detik',
                'skill' => 'MySQL',
                'difficulty' => 'pemula',
                'objective' => 'Turunkan load halaman di bawah 1 detik tanpa ubah fitur.',
                'est_minutes' => 10,
                'story' => 'Halaman katalog makin laris makin lemot. User kabur. Owner minta cepat tanpa ganti fitur.',
                'log_text' => "[slowlog] Query_time: 11.8s Rows_examined: 980k\n[slowlog] SELECT * FROM produk WHERE kategori LIKE '%promo%' ORDER BY dibuat DESC;\n[app] EXPLAIN: type=ALL, key=NULL, Extra=Using filesort",
                'diagnosis_q' => 'Dari EXPLAIN, masalah utamanya?',
                'diagnosis_opts' => ['Server kurang RAM', 'Full table scan + filesort: tanpa index, SELECT *, LIKE %...% di depan', 'Koneksi pool kekecilan', 'PHP version terlalu lama'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Fix paling aman pertama?',
                'fix_opts' => ['Naikkan RAM 4x lipat', 'Tambah index kategori+dibuat, pilih kolom seperlunya, hindari LIKE %...% di depan', 'Cache semua halaman 24 jam', 'Pindah ke NoSQL minggu ini'],
                'fix_correct' => 1,
                'hint_lvl1' => 'type=ALL + key=NULL = tidak ada index yang dipakai. Kolom apa yang difilter?',
                'hint_lvl2' => 'Coret naik RAM/cache. Cari index + SELECT kolom + LIKE.',
                'explanation' => 'EXPLAIN type=ALL = scan 980k baris + filesort. Fix: index (kategori, dibuat), SELECT kolom perlu saja, hindari wildcard depan.',
                'feedback_ok' => 'Tepat. type=ALL + filesort = index. Tambah index, rampingkan SELECT, dan uji EXPLAIN ulang sampai type bukan ALL.',
                'feedback_fail' => 'Kunci: type=ALL, key=NULL, filesort. Bukan RAM. Tambah index pada kolom filter + rampingkan SELECT.',
                'time_limit' => 300,
                'xp_reward' => 50,
                'is_pro' => 0,
            ],
            [
                'slug' => 'git-force-push-bencana',
                'title' => 'Git Disaster: force push menimpa main',
                'skill' => 'Git',
                'difficulty' => 'menengah',
                'objective' => 'Kembalikan main tanpa kehilangan commit tim.',
                'est_minutes' => 10,
                'story' => 'Seorang dev force-push branch lama ke main. 5 commit tim hilang dari remote. Rilis 1 jam lagi.',
                'log_text' => "[git] To origin\n[git] + abc1234...def5678 main -> main (forced update)\n[git] main@{1}: commit: fitur pembayaran (2 jam lalu)\n[ci] build main: FAIL - file tidak lengkap",
                'diagnosis_q' => 'Apa yang terjadi + data penyelamatnya?',
                'diagnosis_opts' => ['Remote corrupt total, tidak bisa pulih', 'Riwayat main tertimpa; reflog lokal (main@{1}) masih pegang commit hilang', 'CI salah config', 'Branch terhapus permanen dari semua clone'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Recovery paling aman?',
                'fix_opts' => ['Push ulang branch lama lagi', 'Buat repo baru dari clone terakhir', 'Checkout main@{1} yang benar, verifikasi, push dengan --force-with-lease + proteksi branch', 'Revert tiap commit satu-satu dari HP'],
                'fix_correct' => 2,
                'hint_lvl1' => 'forced update + main@{1} masih ada = riwayat lama bisa diambil. Dari mana?',
                'hint_lvl2' => 'Coret push ulang / repo baru. Cari reflog + force-with-lease + proteksi.',
                'explanation' => 'Reflog menyimpan main@{1}. Pulihkan dari sana, verifikasi, push --force-with-lease, lalu kunci proteksi branch + larang force push.',
                'feedback_ok' => 'Benar. Reflog = jaring pengaman. Pulihkan main@{1}, verifikasi, force-with-lease, kunci proteksi branch.',
                'feedback_fail' => 'Kunci: forced update menimpa riwayat, tapi reflog main@{1} menyimpan yang hilang. Jangan push ulang branch lama.',
                'time_limit' => 300,
                'xp_reward' => 60,
                'is_pro' => 1,
            ],
            [
                'slug' => 'n-plus-one-lemot',
                'title' => 'N+1 Query: API order makin lambat',
                'skill' => 'Laravel',
                'difficulty' => 'menengah',
                'objective' => 'Hilangkan N+1 tanpa mengubah response API.',
                'est_minutes' => 12,
                'story' => 'API /orders 50ms saat 10 order, 4 detik saat 500 order. Traffic naik, timeout mulai muncul.',
                'log_text' => "[debugbar] queries: 501 (500 duplikat)\n[debugbar] SELECT * FROM users WHERE id = ? (x500)\n[code] foreach ($orders as $o) { echo $o->user->name; }",
                'diagnosis_q' => 'Pola masalahnya?',
                'diagnosis_opts' => ['DB perlu di-sharding', 'N+1 query: relasi user di-load satu-satu di loop', 'Response JSON terlalu besar', 'PHP-FPM worker kurang'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Fix paling tepat?',
                'fix_opts' => ['Naikkan memory_limit ke 2GB', 'Eager load relasi (with user) + index FK, verifikasi query turun drastis', 'Matikan debugbar saja', 'Pagination 5 per halaman tanpa fix'],
                'fix_correct' => 1,
                'hint_lvl1' => '501 query untuk 500 order = 1 + N. Baris code mana yang memicu query di loop?',
                'hint_lvl2' => 'Coret memory/pagination. Cari eager load (with) + index.',
                'explanation' => 'Akses $o->user di loop = 500 query. Fix: Order::with(user), index FK user_id, cek debugbar turun ke ~2 query.',
                'feedback_ok' => 'Tepat. Eager load with(user) + index FK: 501 query jadi segelintir, response sama.',
                'feedback_fail' => 'Kunci: 1 + N query dari relasi di loop. Solusi: eager load + index, bukan memory/pagination.',
                'time_limit' => 360,
                'xp_reward' => 60,
                'is_pro' => 1,
            ],
            [
                'slug' => 'subnet-tumpang-tindih',
                'title' => 'Subnet Overlap: dua divisi saling senggol',
                'skill' => 'Networking',
                'difficulty' => 'pemula',
                'objective' => 'Alokasi ulang subnet tanpa downtime divisi lain.',
                'est_minutes' => 10,
                'story' => 'Kantor tambah divisi baru. Setelah colok kabel, separuh PC lama putus-putus. Semua menyalahkan switch.',
                'log_text' => "[net] divisi-lama: 192.168.1.0/24 (gateway .1)\n[net] divisi-baru: 192.168.1.128/25 (gateway .129)\n[ping] 192.168.1.50: intermittent, duplicate replies\n[arp] flapping MAC untuk 192.168.1.130",
                'diagnosis_q' => 'Penyebab putus-putus?',
                'diagnosis_opts' => ['Switch rusak', 'Subnet tumpang tindih: 192.168.1.128/25 masuk dalam 192.168.1.0/24', 'Kabel UTP kategori salah', 'DHCP lease habis'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Fix paling aman?',
                'fix_opts' => ['Ganti semua switch baru', 'Pindahkan divisi baru ke blok bebas (misal 192.168.2.0/24), rapikan DHCP + dokumentasi IP plan', 'Matikan divisi lama sehari', 'Set semua IP jadi static acak'],
                'fix_correct' => 1,
                'hint_lvl1' => '.128/25 itu separuh dari .0/24. Dua gateway klaim range sama = tabrakan.',
                'hint_lvl2' => 'Coret ganti switch. Cari blok baru + IP plan.',
                'explanation' => '/25 kedua hidup di dalam /24 pertama: routing ambigu + ARP flap. Fix: blok baru yang bebas, DHCP scope rapi, catat IP plan.',
                'feedback_ok' => 'Tepat. Overlap subnet = tabrakan routing. Blok baru + DHCP rapi + dokumentasi mencegah ulang.',
                'feedback_fail' => 'Kunci: 192.168.1.128/25 adalah bagian dari 192.168.1.0/24. Bukan switch. Pindah ke blok bebas + IP plan.',
                'time_limit' => 300,
                'xp_reward' => 50,
                'is_pro' => 1,
            ],
            [
                'slug' => 'dns-tidak-resolve',
                'title' => 'DNS Fail: intranet tidak bisa dibuka',
                'skill' => 'Networking',
                'difficulty' => 'menengah',
                'objective' => 'Kembalikan resolve intranet <5 menit.',
                'est_minutes' => 10,
                'story' => 'Semua PC gagal buka portal intranet, tapi IP langsung bisa. Ujian online 30 menit lagi.',
                'log_text' => "[client] nslookup portal.sekolah: server failed\n[client] ping 8.8.8.8 OK, ping portal.sekolah FAIL\n[dns] named: zone portal.sekolah expired, serial mismatch\n[dhcp] DNS server: 192.168.1.10 (lokal)",
                'diagnosis_q' => 'Diagnosis paling tepat?',
                'diagnosis_opts' => ['Kabel backbone putus', 'Zone DNS lokal expired/serial mismatch, client hanya pakai DNS lokal', 'PC kena virus', 'Web server down'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Fix paling cepat + aman?',
                'fix_opts' => ['Install ulang semua PC', 'Perbaiki zone (sinkron serial, reload), tambah forwarder cadangan, flush DNS client', 'Ganti semua ke DNS publik permanen', 'Restart switch berulang'],
                'fix_correct' => 1,
                'hint_lvl1' => 'IP OK + nama FAIL = DNS. Serial mismatch = zone basi.',
                'hint_lvl2' => 'Coret install ulang. Cari reload zone + forwarder cadangan.',
                'explanation' => 'Nama gagal tapi IP jalan = DNS. Fix: sinkron zone, reload, forwarder cadangan, flush client. Monitoring expiry zone.',
                'feedback_ok' => 'Benar. Bedakan IP vs nama: ini DNS. Reload zone + forwarder + flush memulihkan cepat.',
                'feedback_fail' => 'Kunci: ping IP OK, nama FAIL = DNS, bukan kabel/server. Perbaiki zone + forwarder cadangan.',
                'time_limit' => 300,
                'xp_reward' => 60,
                'is_pro' => 1,
            ],
            [
                'slug' => 'ssh-connection-refused',
                'title' => 'SSH Refused: tidak bisa remote server',
                'skill' => 'Linux',
                'difficulty' => 'pemula',
                'objective' => 'Buka akses SSH aman tanpa mematikan firewall.',
                'est_minutes' => 10,
                'story' => 'Server web hidup, tapi SSH connection refused dari lab. Tugas deploy tertahan.',
                'log_text' => "[ssh] ssh: connect to 192.168.1.20 port 22: Connection refused\n[server] systemctl status sshd: inactive (dead)\n[server] ufw status: 22/tcp DENY IN\n[web] curl localhost:80 OK",
                'diagnosis_q' => 'Dua lapis masalahnya?',
                'diagnosis_opts' => ['Internet sekolah down', 'sshd mati + firewall menutup port 22', 'Password salah', 'IP server berubah'],
                'diagnosis_correct' => 1,
                'fix_q' => 'Urutan fix aman?',
                'fix_opts' => ['Matikan firewall total (ufw disable)', 'Start + enable sshd, buka 22/tcp hanya untuk subnet lab, test login key', 'Reinstall OS server', 'Ganti port SSH ke 80'],
                'fix_correct' => 1,
                'hint_lvl1' => 'refused + inactive + DENY = service mati DAN port ditutup. Web OK = mesin hidup.',
                'hint_lvl2' => 'Coret matikan firewall / reinstall. Cari enable service + buka port selektif.',
                'explanation' => 'Dua lapis: sshd dead + UFW DENY 22. Fix: systemctl enable --now sshd, allow 22 dari subnet lab saja, uji login key.',
                'feedback_ok' => 'Tepat. Service + firewall = dua lapis. Nyalakan sshd, buka port selektif, jangan disable firewall.',
                'feedback_fail' => 'Kunci: inactive + DENY 22. Web OK berarti mesin hidup. Enable sshd + allow port lab, bukan matikan firewall.',
                'time_limit' => 300,
                'xp_reward' => 50,
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
            foreach ([
                "CREATE TABLE IF NOT EXISTS `incident_challenges` (`id` INT AUTO_INCREMENT PRIMARY KEY, `slug` VARCHAR(64) NOT NULL UNIQUE, `title` VARCHAR(120) NOT NULL, `skill` VARCHAR(32) NOT NULL DEFAULT 'Docker', `difficulty` ENUM('pemula','menengah','mahir') NOT NULL DEFAULT 'pemula', `story` TEXT NOT NULL, `log_text` TEXT NOT NULL, `diagnosis_q` VARCHAR(255) NOT NULL, `diagnosis_opts` TEXT NOT NULL, `diagnosis_correct` TINYINT NOT NULL DEFAULT 0, `fix_q` VARCHAR(255) NOT NULL, `fix_opts` TEXT NOT NULL, `fix_correct` TINYINT NOT NULL DEFAULT 0, `feedback_ok` TEXT NOT NULL, `feedback_fail` TEXT NOT NULL, `time_limit` INT NOT NULL DEFAULT 300, `xp_reward` INT NOT NULL DEFAULT 50, `is_pro` TINYINT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "ALTER TABLE `incident_challenges` ADD COLUMN `objective` VARCHAR(255) NOT NULL DEFAULT ''",
                "ALTER TABLE `incident_challenges` ADD COLUMN `est_minutes` INT NOT NULL DEFAULT 10",
                "ALTER TABLE `incident_challenges` ADD COLUMN `hint_lvl1` TEXT NOT NULL",
                "ALTER TABLE `incident_challenges` ADD COLUMN `hint_lvl2` TEXT NOT NULL",
                "ALTER TABLE `incident_challenges` ADD COLUMN `explanation` TEXT NOT NULL",
                "CREATE TABLE IF NOT EXISTS `incident_attempts` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `challenge_id` INT NOT NULL, `score` INT NOT NULL DEFAULT 0, `duration_sec` INT NOT NULL DEFAULT 0, `mistakes` INT NOT NULL DEFAULT 0, `diagnosis_ok` TINYINT NOT NULL DEFAULT 0, `fix_ok` TINYINT NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `fk_incident_attempt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_incident_attempt_ch` FOREIGN KEY (`challenge_id`) REFERENCES `incident_challenges` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
                "CREATE TABLE IF NOT EXISTS `pro_subscriptions` (`id` INT AUTO_INCREMENT PRIMARY KEY, `user_id` INT NOT NULL, `plan` VARCHAR(16) NOT NULL DEFAULT 'monthly', `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, `ends_at` DATETIME NULL, `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active', `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `fk_pro_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            ] as $ddl) {
                try { @$conn->query($ddl); } catch (\Throwable $e) {}
            }
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
