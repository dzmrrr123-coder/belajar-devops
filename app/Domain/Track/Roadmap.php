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

    public static function resources(string $track): array {
        $t = Tracks::normalize($track);
        if ($t === 'rpl') return [
            [1, 'Tutorial Desain Relasional Database MySQL', 'video', 'https://www.youtube.com/results?search_query=desain+database+relasional+mysql'],
            [1, 'MySQL Official Documentation - Foreign Keys & Constraints', 'dokumentasi', 'https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html'],
            [1, 'SQLBolt - Latihan Interaktif Perintah SQL', 'praktek', 'https://sqlbolt.com/'],
            [2, 'PHP Native CRUD & Prepared Statements Security Guide', 'video', 'https://www.youtube.com/results?search_query=php+pdo+mysqli+prepared+statement'],
            [2, 'PHP Manual - Hashing Password yang Benar', 'dokumentasi', 'https://www.php.net/manual/en/function.password-hash.php'],
            [2, 'OWASP PHP Security Cheat Sheet', 'praktek', 'https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html'],
            [3, 'Dasar-Dasar Object Oriented Programming (OOP) PHP', 'video', 'https://www.youtube.com/results?search_query=php+oop+dasar'],
            [3, 'PHP: The Right Way - Object-Oriented Programming', 'dokumentasi', 'https://phptherightway.com/#object-oriented_programming'],
            [3, 'Latihan Refactoring Prosedural ke OOP', 'praktek', 'https://refactoring.guru/design-patterns/php'],
            [4, 'Interface, Abstract Class & Polimorfisme di PHP', 'video', 'https://www.youtube.com/results?search_query=php+interface+abstract+class'],
            [4, 'Prinsip SOLID dalam PHP Secara Sederhana', 'dokumentasi', 'https://www.freecodecamp.org/news/solid-principles-explained-in-plain-english/'],
            [4, 'Studi Kasus: Membuat Payment Gateway Interface Dummy', 'praktek', 'https://github.com/kamranahmedse/design-patterns-for-humans'],
            [5, 'Laravel 11 Crash Course untuk Pemula', 'video', 'https://www.youtube.com/results?search_query=laravel+11+tutorial+indonesia'],
            [5, 'Dokumentasi Resmi Laravel - Migrations & Eloquent', 'dokumentasi', 'https://laravel.com/docs/11.x/migrations'],
            [5, 'Latihan REST API Sederhana dengan Laravel', 'praktek', 'https://laravel.com/docs/11.x/eloquent-resources'],
            [6, 'Sistem Autentikasi Laravel Breeze & Middleware Guard', 'video', 'https://www.youtube.com/results?search_query=laravel+breeze+middleware+authentication'],
            [6, 'Dokumentasi Laravel - Routing & Middleware Guide', 'dokumentasi', 'https://laravel.com/docs/11.x/middleware'],
            [6, 'Implementasi Multi-Role & Permissions', 'praktek', 'https://spatie.be/docs/laravel-permission/v6/introduction'],
            [7, 'Panduan Git Branching, Pull Request & Resolusi Konflik', 'video', 'https://www.youtube.com/results?search_query=git+branching+pull+request+tutorial'],
            [7, 'GitHub Documentation - Collaborative Git Workflow', 'dokumentasi', 'https://docs.github.com/en/get-started/using-git/about-git'],
            [7, 'Learn Git Branching - Simulasi Interaktif di Browser', 'praktek', 'https://learngitbranching.js.org/?locale=id_ID'],
            [8, 'Dasar Unit Testing PHPUnit untuk Web Developer', 'video', 'https://www.youtube.com/results?search_query=phpunit+testing+dasar+tutorial'],
            [8, 'PHPUnit Official Manual - Getting Started', 'dokumentasi', 'https://docs.phpunit.de/en/11.0/'],
            [8, 'Latihan Menulis Test Case & Boundary Values', 'praktek', 'https://laracasts.com/series/phpunit-testing-in-laravel'],
            [9, 'Membangun REST API CRUD dengan Validasi & JSON di PHP', 'video', 'https://www.youtube.com/results?search_query=rest+api+php+json+tutorial'],
            [9, 'RESTful API Best Practices & Design Guidelines', 'dokumentasi', 'https://restfulapi.net/'],
            [9, 'Postman API Testing - Latihan Menguji Endpoint', 'praktek', 'https://learning.postman.com/docs/getting-started/overview/'],
            [10, 'Keamanan Web: Mencegah SQLi, XSS, dan CSRF', 'video', 'https://www.youtube.com/results?search_query=owasp+top+10+web+security+php'],
            [10, 'OWASP Top 10 Web Application Security Risks', 'dokumentasi', 'https://owasp.org/www-project-top-ten/'],
            [10, 'OWASP Juice Shop - Latihan Penetrasi Web Ramah Pemula', 'praktek', 'https://owasp.org/www-project-juice-shop/'],
            [11, 'Automasi Build & Test dengan GitHub Actions', 'video', 'https://www.youtube.com/results?search_query=github+actions+php+tutorial'],
            [11, 'GitHub Actions CI Documentation for PHP', 'dokumentasi', 'https://docs.github.com/en/actions/automating-builds-and-tests/building-and-testing-php'],
            [11, 'Setup Workflow CI Sederhana (php -l + PHPUnit)', 'praktek', 'https://github.com/features/actions'],
            [12, 'Menyusun CV ATS & Portofolio Software Engineer', 'video', 'https://www.youtube.com/results?search_query=cv+ats+software+engineer+indonesia'],
            [12, 'Roadmap Software Engineer & Backend 2026', 'dokumentasi', 'https://roadmap.sh/backend'],
            [12, 'Make a README - Template Portofolio GitHub Profesional', 'praktek', 'https://www.makeareadme.com/'],
        ];
        if ($t === 'tkj') return [
            [1, 'Dasar Jaringan Komputer: IP Address, Subnet & Gateway', 'video', 'https://www.youtube.com/results?search_query=dasar+jaringan+komputer+ip+address'],
            [1, 'Cisco Networking Academy - IP Addressing Essentials', 'dokumentasi', 'https://www.cisco.com/c/en/us/support/docs/ip/routing-information-protocol-rip/13788-3.html'],
            [1, 'SubnettingPractice - Kalkulator & Latihan Subnetting CIDR', 'praktek', 'https://www.subnettingpractice.com/'],
            [2, 'Perintah Dasar Linux & Manajemen Hak Akses File', 'video', 'https://www.youtube.com/results?search_query=belajar+linux+dasar+cli+permissions'],
            [2, 'Linux Journey - Grasshopper Level (CLI & Permissions)', 'dokumentasi', 'https://linuxjourney.com/'],
            [2, 'OverTheWire Bandit - Game Interaktif Perintah Linux', 'praktek', 'https://overthewire.org/wargames/bandit/'],
            [3, 'Install & Konfigurasi Web Server Nginx di Ubuntu', 'video', 'https://www.youtube.com/results?search_query=install+nginx+ubuntu+server+tutorial'],
            [3, 'Nginx Beginner\'s Guide - Dokumentasi Resmi', 'dokumentasi', 'https://nginx.org/en/docs/beginners_guide.html'],
            [3, 'Latihan Setup Server Block Nginx & Log Troubleshooting', 'praktek', 'https://www.digitalocean.com/community/tutorials/how-to-install-nginx-on-ubuntu-22-04'],
            [4, 'Teknik Subnetting VLSM (Variable Length Subnet Masking)', 'video', 'https://www.youtube.com/results?search_query=vlsm+subnetting+tutorial+indonesia'],
            [4, 'IPv4 CIDR Reference Sheet & Host Calculation', 'dokumentasi', 'https://www.ionos.com/digitalguide/server/know-how/cidr-classless-inter-domain-routing/'],
            [4, 'Simulasi Desain Subnet Kantor dengan Cisco Packet Tracer', 'praktek', 'https://www.netacad.com/courses/packet-tracer'],
            [5, 'Docker Dasar untuk Teknisi Jaringan & Sysadmin', 'video', 'https://www.youtube.com/results?search_query=docker+dasar+untuk+pemula+indonesia'],
            [5, 'Dokumentasi Resmi Docker - Orientasi & Container', 'dokumentasi', 'https://docs.docker.com/get-started/'],
            [5, 'Latihan Menjalankan Web Container & Volume Mount', 'praktek', 'https://labs.play-with-docker.com/'],
            [6, 'Hardening Server Linux: SSH Key, Disable Root & Port', 'video', 'https://www.youtube.com/results?search_query=ssh+hardening+fail2ban+ubuntu'],
            [6, 'SSH Security Best Practices & Fail2ban Documentation', 'dokumentasi', 'https://infosec.mozilla.org/guidelines/openssh'],
            [6, 'Konfigurasi Firewall UFW & Pemblokiran Brute Force', 'praktek', 'https://www.digitalocean.com/community/tutorials/initial-server-setup-with-ubuntu-22-04'],
            [7, 'Panduan Deploy VPS Cloud di AWS EC2 dari Nol', 'video', 'https://www.youtube.com/results?search_query=deploy+aws+ec2+tutorial+pemula'],
            [7, 'AWS Documentation - Getting Started with Amazon EC2', 'dokumentasi', 'https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/EC2_GetStarted.html'],
            [7, 'Konfigurasi Security Group Inbound/Outbound Rules', 'praktek', 'https://aws.amazon.com/free/'],
            [8, 'Troubleshooting Jaringan: Ping, Traceroute, MTR & Netstat', 'video', 'https://www.youtube.com/results?search_query=troubleshooting+jaringan+linux+ping+traceroute'],
            [8, 'Linux Network Troubleshooting Guide', 'dokumentasi', 'https://www.redhat.com/sysadmin/troubleshooting-network-issues'],
            [8, 'SadServers - Selesaikan Masalah Jaringan Linux Nyata', 'praktek', 'https://sadservers.com/'],
            [9, 'Docker Compose Multi-Container (Nginx + App + DB)', 'video', 'https://www.youtube.com/results?search_query=docker+compose+multi+container+tutorial'],
            [9, 'Docker Compose File Specification & Networking', 'dokumentasi', 'https://docs.docker.com/compose/compose-file/'],
            [9, 'Latihan Konfigurasi Jaringan Internal Bridge Docker', 'praktek', 'https://docs.docker.com/network/'],
            [10, 'Strategi Manajemen Resource & Hemat Biaya Server Cloud', 'video', 'https://www.youtube.com/results?search_query=aws+cost+optimization+tips'],
            [10, 'AWS CloudWatch Alarms & Resource Management', 'dokumentasi', 'https://docs.aws.amazon.com/cost-management/latest/userguide/what-is-costmanagement.html'],
            [10, 'Simulasi Right-Sizing & Scheduling Instance Otomatis', 'praktek', 'https://calculator.aws/'],
            [11, 'Version Control Konfigurasi Server dengan Git', 'video', 'https://www.youtube.com/results?search_query=git+for+sysadmin+infrastructure'],
            [11, 'Infrastructure as Code & GitOps Overview', 'dokumentasi', 'https://about.gitlab.com/topics/gitops/'],
            [11, 'Latihan Backup & Rollback Config Nginx via Git', 'praktek', 'https://github.com/'],
            [12, 'Persiapan Sertifikasi Jaringan (MTCNA/CCNA/Network+)', 'video', 'https://www.youtube.com/results?search_query=persiapan+sertifikasi+jaringan+ccna+mtcna'],
            [12, 'CompTIA Network+ & Cisco Certification Roadmap', 'dokumentasi', 'https://www.comptia.org/certifications/network'],
            [12, 'Showcase Portofolio Proyek Jaringan & Server Publik', 'praktek', 'https://roadmap.sh/cyber-security'],
        ];
        if ($t === 'dkv') return [
            [1, 'Prinsip Dasar Desain Grafis: Hirarki, Kontras & Alignment', 'video', 'https://www.youtube.com/results?search_query=prinsip+dasar+desain+grafis+indonesia'],
            [1, 'Canva Design School - Graphic Design Basics', 'dokumentasi', 'https://www.canva.com/learn/graphic-design-basics/'],
            [1, 'Latihan Layout Poster Acara dengan Visual Hierarchy Kuat', 'praktek', 'https://www.canva.com/create/posters/'],
            [2, 'Panduan Memilih & Memadukan Font untuk Desainer', 'video', 'https://www.youtube.com/results?search_query=tips+memilih+kombinasi+font+desain'],
            [2, 'Typewolf - Panduan Tipografi & Font Pairing Populer', 'dokumentasi', 'https://www.typewolf.com/'],
            [2, 'Google Fonts - Eksplorasi Skala & Karakter Tipografi', 'praktek', 'https://fonts.google.com/'],
            [3, 'Penerapan 8-Point Grid System dalam Layout Digital', 'video', 'https://www.youtube.com/results?search_query=8pt+grid+system+tutorial+desain'],
            [3, 'Material Design - Layout & Responsive Grid Foundation', 'dokumentasi', 'https://m2.material.io/design/layout/understanding-layout.html'],
            [3, 'Latihan Menyusun Layout Responsive 360px di Figma', 'praktek', 'https://www.figma.com/community'],
            [4, 'Proses Desain Logo: Dari Mindmap, Sketsa ke Vektor', 'video', 'https://www.youtube.com/results?search_query=proses+desain+logo+sketsa+ke+vektor'],
            [4, 'Logo Design Love - Panduan Esensial Desain Logo', 'dokumentasi', 'https://www.logodesignlove.com/'],
            [4, 'Latihan Desain Logo + Varian 1 Warna + Clearspace', 'praktek', 'https://brandstyleguide.io/'],
            [5, 'Tutorial Figma Pemula: Wireframe ke High-Fidelity UI', 'video', 'https://www.youtube.com/results?search_query=figma+tutorial+pemula+ui+ux+indonesia'],
            [5, 'Figma Official Manual - UI Design Fundamentals', 'dokumentasi', 'https://help.figma.com/hc/en-us/articles/360041003114-Getting-started-with-Figma'],
            [5, 'Mobbin - Referensi Pola Desain Aplikasi Mobile & Web', 'praktek', 'https://mobbin.com/'],
            [6, 'Teori Warna Digital & Standar Kontras Aksesibilitas WCAG', 'video', 'https://www.youtube.com/results?search_query=teori+warna+desain+aksesibilitas+wcag'],
            [6, 'WebAIM Color Contrast Checker & Guidelines', 'dokumentasi', 'https://webaim.org/resources/contrastchecker/'],
            [6, 'Adobe Color Wheel - Eksplorasi Palet Warna & Aksesibilitas', 'praktek', 'https://color.adobe.com/create/color-wheel'],
            [7, 'Cara Membuat Brand Kit & Brand Style Guide Profesional', 'video', 'https://www.youtube.com/results?search_query=cara+membuat+brand+guidelines+figma'],
            [7, 'Brand Identity Guide Essentials - Elements of Brand Kit', 'dokumentasi', 'https://www.designhill.com/design-blog/brand-identity-elements-guide/'],
            [7, 'Latihan Menyusun One-Page Brand Kit di Figma', 'praktek', 'https://www.figma.com/templates/brand-guidelines/'],
            [8, 'Membuat Prototipe Interaktif & Micro-Interactions di Figma', 'video', 'https://www.youtube.com/results?search_query=figma+prototyping+tutorial+indonesia'],
            [8, 'Figma Interactive Components Documentation', 'dokumentasi', 'https://help.figma.com/hc/en-us/articles/360040314193-Guide-to-prototyping-in-Figma'],
            [8, 'Latihan Uji Navigasi 3 Layar & Usability Test 5 Detik', 'praktek', 'https://www.interaction-design.org/literature/topics/usability-testing'],
            [9, 'Menggambar Maskot & Karakter Vektor dengan Pen Tool', 'video', 'https://www.youtube.com/results?search_query=vektor+karakter+maskot+illustrator+tutorial'],
            [9, 'Character Design Basics: Silhouette, Proportion & Poses', 'dokumentasi', 'https://www.clipstudio.net/how-to-draw/archives/157297'],
            [9, 'Latihan Desain Maskot 2 Pose dengan Siluet Terbaca', 'praktek', 'https://dribbble.com/tags/character-design'],
            [10, '12 Prinsip Animasi dalam Motion Graphics & Logo Bumper', 'video', 'https://www.youtube.com/results?search_query=12+prinsip+animasi+motion+graphics+indonesia'],
            [10, 'The Illusion of Life - Disney Animation Principles', 'dokumentasi', 'https://www.animatorisland.com/51-great-animation-exercises-to-master/'],
            [10, 'Latihan Bumper Motion 5 Detik & Storyboard Sederhana', 'praktek', 'https://www.youtube.com/results?search_query=after+effects+bumper+logo+tutorial'],
            [11, 'Tips Kurasi Portofolio Desain di Behance & Dribbble', 'video', 'https://www.youtube.com/results?search_query=cara+membuat+portofolio+behance+menarik'],
            [11, 'Behance Portfolio Best Practices Guide', 'dokumentasi', 'https://help.behance.net/hc/en-us/articles/204483894-Guide-To-Creating-A-Project'],
            [11, 'Upload Karya ke Portofolio & Minta Critique Ulasan', 'praktek', 'https://www.behance.net/'],
            [12, 'Menyusun CV Desainer Grafis / UI & Persiapan Pameran Karya', 'video', 'https://www.youtube.com/results?search_query=cv+portofolio+desainer+grafis+tips+interview'],
            [12, 'AIGA Design Career Guide - Preparing Your Creative Portfolio', 'dokumentasi', 'https://www.aiga.org/design/design-careers'],
            [12, 'Showcase Galeri Portofolio DKV & Evaluasi Akhir', 'praktek', 'https://dribbble.com/'],
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
        self::ensureSeedResources($conn);
    }

    public static function ensureSeedResources(\mysqli $conn): void {
        try { @$conn->query("ALTER TABLE `resources` ADD COLUMN `track` VARCHAR(16) NOT NULL DEFAULT 'devops'"); } catch (\Throwable $e) {}
        try { @$conn->query("UPDATE `resources` SET `track` = 'devops' WHERE `track` IS NULL OR `track` = '' OR `track` = 'all'"); } catch (\Throwable $e) {}
        foreach (['rpl', 'tkj', 'dkv'] as $t) {
            $rows = self::resources($t);
            if (!$rows) continue;
            try {
                $have = [];
                $q = $conn->prepare("SELECT title FROM resources WHERE track = ?");
                if ($q) {
                    $q->bind_param("s", $t);
                    $q->execute();
                    foreach ($q->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                        $have[mb_strtolower(trim((string)$r['title']))] = true;
                    }
                    $q->close();
                }
                $ins = $conn->prepare("INSERT INTO resources (week, title, type, url, track) VALUES (?, ?, ?, ?, ?)");
                if (!$ins) continue;
                foreach ($rows as [$w, $title, $type, $url]) {
                    if (isset($have[mb_strtolower(trim($title))])) continue;
                    $ins->bind_param("issss", $w, $title, $type, $url, $t);
                    try { $ins->execute(); } catch (\Throwable $e) {}
                }
                $ins->close();
            } catch (\Throwable $e) {}
        }
    }
}
