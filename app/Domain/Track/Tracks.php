<?php
namespace App\Domain\Track;
class Tracks {
    public static function all(): array {
        return [
            'devops' => ['slug' => 'devops', 'name' => 'DevOps', 'desc' => 'Linux, Docker, CI/CD & Cloud', 'icon' => 'fas fa-infinity'],
            'rpl' => ['slug' => 'rpl', 'name' => 'RPL', 'desc' => 'Programming, Database, Web & Testing', 'icon' => 'fas fa-code'],
            'tkj' => ['slug' => 'tkj', 'name' => 'TKJ', 'desc' => 'Networking, Linux, Server & Cloud', 'icon' => 'fas fa-network-wired'],
            'dkv' => ['slug' => 'dkv', 'name' => 'DKV', 'desc' => 'Desain, Branding, UI/UX & Motion', 'icon' => 'fas fa-palette'],
        ];
    }
    public static function isValid(string $t): bool {
        return isset(self::all()[$t]);
    }
    public static function normalize(string $t): string {
        $t = strtolower(trim($t));
        return self::isValid($t) ? $t : 'devops';
    }
    public static function weeks(string $track): array {
        $t = self::normalize($track);
        if ($t === 'rpl') {
            return [1 => 'MySQL', 2 => 'PHP', 3 => 'PHP', 4 => 'PHP', 5 => 'Laravel', 6 => 'Laravel', 7 => 'Git', 8 => 'Testing', 9 => 'Laravel', 10 => 'PHP', 11 => 'Git', 12 => 'General'];
        }
        if ($t === 'tkj') {
            return [1 => 'Networking', 2 => 'Linux', 3 => 'Linux', 4 => 'Networking', 5 => 'Docker', 6 => 'Linux', 7 => 'AWS', 8 => 'Networking', 9 => 'Docker', 10 => 'AWS', 11 => 'Git', 12 => 'General'];
        }
        if ($t === 'dkv') {
            return [1 => 'Desain', 2 => 'Tipografi', 3 => 'Desain', 4 => 'Branding', 5 => 'UI/UX', 6 => 'Tipografi', 7 => 'Branding', 8 => 'UI/UX', 9 => 'Ilustrasi', 10 => 'Motion', 11 => 'UI/UX', 12 => 'General'];
        }
        return [1 => 'MySQL', 2 => 'PHP', 3 => 'PHP', 4 => 'PHP', 5 => 'Laravel', 6 => 'Laravel', 7 => 'Docker', 8 => 'Docker', 9 => 'AWS', 10 => 'Linux', 11 => 'Git', 12 => 'General'];
    }
    public static function skills(string $track): array {
        $t = self::normalize($track);
        if ($t === 'rpl') {
            return ['MySQL', 'PHP', 'Laravel', 'Git', 'Testing', 'General'];
        }
        if ($t === 'tkj') {
            return ['Networking', 'Linux', 'Docker', 'AWS', 'Git', 'General'];
        }
        if ($t === 'dkv') {
            return ['Desain', 'Tipografi', 'Branding', 'UI/UX', 'Ilustrasi', 'Motion', 'General'];
        }
        return ['Linux', 'Git', 'MySQL', 'PHP', 'Laravel', 'Docker', 'AWS', 'General'];
    }
    public static function targets(string $track): array {
        $t = self::normalize($track);
        if ($t === 'rpl') {
            return ['Backend Engineer', 'Frontend Engineer', 'Fullstack Engineer', 'QA Engineer', 'Masih ragu'];
        }
        if ($t === 'tkj') {
            return ['Network Engineer', 'System Administrator', 'Cloud Engineer', 'Security Analyst', 'Masih ragu'];
        }
        if ($t === 'dkv') {
            return ['UI/UX Designer', 'Graphic Designer', 'Brand Designer', 'Motion Designer', 'Masih ragu'];
        }
        return ['DevOps Engineer', 'Backend Engineer', 'Cloud Engineer', 'Site Reliability Engineer', 'Masih ragu'];
    }
    public static function quizTopics(string $track): array {
        $t = self::normalize($track);
        if ($t === 'rpl') {
            return ['Git', 'MySQL', 'PHP', 'Laravel', 'Testing', 'General'];
        }
        if ($t === 'tkj') {
            return ['Networking', 'Linux', 'Docker', 'AWS', 'Git', 'General'];
        }
        if ($t === 'dkv') {
            return ['Desain', 'Tipografi', 'Branding', 'UI/UX', 'Ilustrasi', 'Motion', 'General'];
        }
        return ['Linux', 'Git', 'MySQL', 'PHP', 'Laravel', 'Docker', 'AWS', 'Networking', 'General'];
    }

    public static function pageAllowedTracks(string $page): ?array {
        $map = [
            'playground.php' => ['rpl', 'devops'],
            'topologi.php'   => ['tkj'],
            'terminal.php'   => ['tkj', 'devops'],
            'brief.php'      => ['dkv'],
            'karya.php'      => ['dkv'],
            'karya_upload.php' => ['dkv'],
            'rubric.php'     => ['dkv'],
            'critique.php'   => ['dkv'],
            'incident.php'   => ['devops', 'tkj'],
        ];
        return $map[$page] ?? null;
    }

    public static function isPageAllowed(string $page, string $track): bool {
        $allowed = self::pageAllowedTracks($page);
        if ($allowed === null) return true;
        return in_array(self::normalize($track), $allowed, true);
    }

    public static function primaryFeature(string $track): array {
        $t = self::normalize($track);
        return match($t) {
            'rpl' => [
                'title' => 'Coding Playground',
                'desc'  => 'Tulis dan uji coba kode PHP, JS, dan SQL langsung',
                'icon'  => 'fas fa-code',
                'href'  => 'playground.php',
                'badge' => 'RPL',
                'cta'   => 'Buka Playground'
            ],
            'tkj' => [
                'title' => 'Topologi & Subnet',
                'desc'  => 'Kalkulator CIDR dan kanvas rancang jaringan interaktif',
                'icon'  => 'fas fa-network-wired',
                'href'  => 'topologi.php',
                'badge' => 'TKJ',
                'cta'   => 'Buka Topologi'
            ],
            'dkv' => [
                'title' => 'Brief Kreatif & Karya',
                'desc'  => 'Kerjakan brief desain, kumpulkan karya & minta critique',
                'icon'  => 'fas fa-palette',
                'href'  => 'brief.php',
                'badge' => 'DKV',
                'cta'   => 'Lihat Brief'
            ],
            'devops' => [
                'title' => 'Incident Simulator',
                'desc'  => 'Simulasi tangani deploy crash, drift migrasi & server 500',
                'icon'  => 'fas fa-fire-extinguisher',
                'href'  => 'incident.php',
                'badge' => 'DevOps',
                'cta'   => 'Coba Simulator'
            ],
            default => [
                'title' => 'Lab Praktik 5 Menit',
                'desc'  => 'Latihan cepat sesuai materi minggumu',
                'icon'  => 'fas fa-flask',
                'href'  => 'lab.php',
                'badge' => 'Lab',
                'cta'   => 'Buka Lab'
            ]
        };
    }

    public static function trackFeatures(string $track): array {
        $t = self::normalize($track);
        if ($t === 'rpl') {
            return [
                ['href' => 'playground.php', 'icon' => 'fas fa-code', 'title' => 'Playground', 'desc' => 'eksekusi PHP/JS'],
                ['href' => 'lab.php', 'icon' => 'fas fa-flask', 'title' => 'Lab RPL', 'desc' => 'tantangan kuis kode'],
            ];
        }
        if ($t === 'tkj') {
            return [
                ['href' => 'topologi.php', 'icon' => 'fas fa-network-wired', 'title' => 'Topologi', 'desc' => 'subnet & kanvas'],
                ['href' => 'terminal.php', 'icon' => 'fas fa-terminal', 'title' => 'Terminal Linux', 'desc' => 'lab virtual'],
                ['href' => 'incident.php', 'icon' => 'fas fa-fire-extinguisher', 'title' => 'Incident', 'desc' => 'simulator server'],
                ['href' => 'lab.php', 'icon' => 'fas fa-flask', 'title' => 'Lab TKJ', 'desc' => 'tantangan jaringan'],
            ];
        }
        if ($t === 'dkv') {
            return [
                ['href' => 'brief.php', 'icon' => 'fas fa-pen-nib', 'title' => 'Brief Kreatif', 'desc' => 'proyek desain'],
                ['href' => 'quests.php', 'icon' => 'fas fa-cloud-arrow-up', 'title' => 'Upload Karya', 'desc' => 'di roadmap'],
                ['href' => 'lab.php', 'icon' => 'fas fa-flask', 'title' => 'Lab DKV', 'desc' => 'tantangan desain'],
            ];
        }
        return [
            ['href' => 'incident.php', 'icon' => 'fas fa-fire-extinguisher', 'title' => 'Incident', 'desc' => 'simulator deploy'],
            ['href' => 'terminal.php', 'icon' => 'fas fa-terminal', 'title' => 'Terminal Linux', 'desc' => 'lab CLI'],
            ['href' => 'playground.php', 'icon' => 'fas fa-code', 'title' => 'Playground', 'desc' => 'scripting'],
            ['href' => 'lab.php', 'icon' => 'fas fa-flask', 'title' => 'Lab DevOps', 'desc' => 'tantangan praktik'],
        ];
    }
}

