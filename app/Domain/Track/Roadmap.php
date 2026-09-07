<?php
namespace App\Domain\Track;
class Roadmap {
    public static function quests(string $track): array {
        $t = Tracks::normalize($track);
        if ($t === 'rpl') return [
            [1, 'Database Toko Online', 'Rancang skema MySQL relasional: users, products, orders + foreign key.', 15],
            [2, 'CRUD Produk PHP', 'CRUD PHP native dengan prepared statement anti SQL injection.', 20],
            [3, 'Refactor OOP PHP', 'Ubah prosedural ke class, encapsulation, constructor.', 25],
            [4, 'Polimorfisme & SOLID', 'Inheritance, interface, dan 1 design pattern sederhana.', 20],
            [5, 'Migrasi Laravel', 'Setup Laravel, migration, seeder, model Eloquent.', 30],
            [6, 'Auth Laravel + Middleware', 'Auth + middleware role untuk route admin.', 25],
            [7, 'Git Workflow Tim', 'Branch, PR, resolve conflict tanpa force ke main.', 15],
            [8, 'Testing Pertama', 'Tulis unit test untuk 1 fungsi kritis (kasus batas wajib).', 20],
            [9, 'REST API Mini', 'Endpoint CRUD JSON + validasi server-side.', 25],
            [10, 'Keamanan Web Dasar', 'Hash password, CSRF, XSS escape di semua form.', 20],
            [11, 'CI Sederhana', 'Jalankan php -l + test otomatis tiap push.', 15],
            [12, 'Capstone + CV', 'Selesaikan 1 app penuh + README + CV ATS.', 30],
        ];
        if ($t === 'tkj') return [
            [1, 'Dasar Networking', 'IP, subnet mask, gateway + hitung /24 di kalkulator.', 15],
            [2, 'Linux Dasar', 'Navigasi file, permission 755 vs 777, user/group.', 15],
            [3, 'Server Web Nginx', 'Install Nginx, serve 1 halaman, cek log akses.', 20],
            [4, 'Subnetting Lanjut', 'Bagi /24 jadi 4 subnet + tabel alokasi host.', 20],
            [5, 'Docker Dasar', 'Jalankan container Nginx + volume mount.', 25],
            [6, 'Hardening SSH', 'Key-only, no root, fail2ban, batasi sumber IP.', 20],
            [7, 'Cloud EC2', 'Launch instance, security group seperlunya, SSH.', 30],
            [8, 'Troubleshoot Jaringan', 'Diagnosis dengan ping, ip, log + 1 lab incident.', 20],
            [9, 'Compose Multi-Service', 'Nginx + app + DB dalam 1 compose file.', 25],
            [10, 'Hemat Tagihan Cloud', 'Rightsize, schedule mati malam, tag wajib.', 20],
            [11, 'Git untuk Ops', 'Versioning config + rollback aman.', 15],
            [12, 'Proyek Akhir + Sertifikat', 'Deploy 1 layanan live + kumpulkan 1 sertifikat lab.', 30],
        ];
        if ($t === 'dkv') return [
            [1, 'Poster Acara Sekolah', '1 pesan jelas, kontras AA, maks 2 font.', 15],
            [2, 'Sistem Tipografi Mini', 'Skala H1/body/caption konsisten 1 keluarga font.', 15],
            [3, 'Komposisi & Grid', 'Layout grid 8px responsif 360px.', 20],
            [4, 'Logo UMKM', 'Logo + varian 1 warna + clearspace.', 25],
            [5, 'Landing PPDB', 'Wireframe ke UI 1 layar dengan 1 CTA jelas.', 30],
            [6, 'Warna & Aksesibilitas', 'Palet 3 warna, semua teks lolos kontras 4.5:1.', 20],
            [7, 'Brand Kit Mini', 'Logo, warna, tipe, contoh aplikasi dalam 1 sheet.', 25],
            [8, 'Prototipe Figma', 'Alur 3 layar bisa diklik + uji 5 detik.', 25],
            [9, 'Maskot Kelas', 'Maskot vektor 2 pose, siluet terbaca 32px.', 20],
            [10, 'Bumper Motion 5 Detik', 'Intro video + storyboard, teks < 6 kata.', 20],
            [11, 'Portofolio Passport', 'Upload 3 karya + minta 1 critique guru.', 20],
            [12, 'Pameran + CV Kreatif', 'Galeri 6 karya terbaik + CV portofolio.', 30],
        ];
        return [];
    }
    public static function ensureSeed(\mysqli $conn): void {
        try { @$conn->query("ALTER TABLE `quests` ADD COLUMN `track` VARCHAR(16) NOT NULL DEFAULT 'devops'"); } catch (\Throwable $e) {}
        foreach (['rpl', 'tkj', 'dkv'] as $t) {
            $rows = self::quests($t);
            if (!$rows) continue;
            try {
                $have = [];
                $q = $conn->prepare("SELECT title FROM quests WHERE user_id IS NULL AND track = ?");
                if ($q) { $q->bind_param("s", $t); $q->execute(); foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $have[mb_strtolower(trim((string)$r['title']))] = true; $q->close(); }
                $ins = $conn->prepare("INSERT INTO quests (user_id, is_custom, week, title, description, xp_reward, track) VALUES (NULL, 0, ?, ?, ?, ?, ?)");
                if (!$ins) continue;
                foreach ($rows as [$w, $title, $desc, $xp]) {
                    if (isset($have[mb_strtolower(trim($title))])) continue;
                    $ins->bind_param("issis", $w, $title, $desc, $xp, $t);
                    try { $ins->execute(); } catch (\Throwable $e) {}
                }
                $ins->close();
            } catch (\Throwable $e) {}
        }
    }
}
