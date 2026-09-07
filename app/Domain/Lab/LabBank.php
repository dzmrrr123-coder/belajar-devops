<?php
namespace App\Domain\Lab;
class LabBank {
    public static function all(): array {
        return [
            [
                'slug' => 'rpl-output-php',
                'track' => 'rpl',
                'title' => 'Tebak output PHP',
                'skill' => 'PHP',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Apa output kode ini? $diskon = 10; $harga = 100000; echo $harga - ($harga * $diskon / 100);',
                'code' => '$diskon = 10; $harga = 100000;',
                'options' => ['10000', '90000', '9000', 'Error'],
                'answer' => 1,
                'explanation' => 'Diskon 10% dari 100rb = 10rb. 100rb - 10rb = 90000.',
                'sponsor' => null,
            ],
            [
                'slug' => 'rpl-fix-bug',
                'track' => 'rpl',
                'title' => 'Perbaiki bug checkout',
                'skill' => 'PHP',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Kupon kadaluarsa tetap memberi diskon 100%. Baris mana yang salah? if ($coupon) { $discount = 100; }',
                'code' => 'if ($coupon) { $discount = 100; }',
                'options' => ['Kurang validasi tanggal kadaluarsa di server', 'Kurang CSS', 'Server kurang cepat', 'Nama variabel terlalu pendek'],
                'answer' => 0,
                'explanation' => 'Wajib cek tanggal kadaluarsa di server + unit test kasus batas.',
                'sponsor' => null,
            ],
            [
                'slug' => 'rpl-git-urutan',
                'track' => 'rpl',
                'title' => 'Urutan Git aman',
                'skill' => 'Git',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Urutan aman sebelum push ke main?',
                'code' => 'pull --rebase → test lokal → commit → push',
                'options' => ['push --force langsung', 'pull --rebase, test lokal, commit, push', 'hapus .git lalu init ulang', 'commit kosong biar hijau'],
                'answer' => 1,
                'explanation' => 'Sinkron dulu, test lokal, baru push. Jangan force ke main.',
                'sponsor' => null,
            ],
            [
                'slug' => 'tkj-subnet-dasar',
                'track' => 'tkj',
                'title' => 'Hitung subnet /24',
                'skill' => 'Networking',
                'type' => 'calc',
                'xp' => 15,
                'prompt' => 'Network 192.168.1.0/24. Berapa jumlah host usable?',
                'code' => '192.168.1.0/24',
                'options' => [],
                'answer' => 1,
                'calc_answer' => '254',
                'explanation' => '2^(32-24) - 2 = 254 host usable. Kurangi network + broadcast.',
                'sponsor' => null,
            ],
            [
                'slug' => 'tkj-linux-permission',
                'track' => 'tkj',
                'title' => 'Fix permission deploy',
                'skill' => 'Linux',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Upload gagal: Permission denied ke /var/www. Perintah paling aman?',
                'code' => 'drwxr-xr-x root root /var/www',
                'options' => ['chmod 777 -R /var/www', 'chown -R www-data:www-data + chmod 755 secukupnya', 'rm -rf /var/www', 'jalankan semua sebagai root'],
                'answer' => 1,
                'explanation' => 'Jangan 777. Serahkan ke user app + izin minimal.',
                'sponsor' => null,
            ],
            [
                'slug' => 'tkj-port-aman',
                'track' => 'tkj',
                'title' => 'Port mana yang dibuka?',
                'skill' => 'Networking',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Web app Nginx + SSH admin saja. Aturan firewall tepat?',
                'code' => 'ufw default deny incoming',
                'options' => ['allow 22,80,443 dari mana saja + fail2ban', 'allow semua port biar gampang', 'deny 80,443 buka 3306 ke publik', 'matikan firewall'],
                'answer' => 0,
                'explanation' => 'Buka seperlunya + fail2ban + batasi SSH ke IP lab/VPN bila bisa.',
                'sponsor' => 'Lab Networking SMK',
            ],
            [
                'slug' => 'dkv-kontras-warna',
                'track' => 'dkv',
                'title' => 'Cek kontras teks',
                'skill' => 'Desain',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Teks abu muda di atas putih gagal dibaca. Prinsip apa yang dilanggar?',
                'code' => '#CCCCCC on #FFFFFF',
                'options' => ['Kontras & aksesibilitas', 'Harga font', 'Ukuran server', 'Nama file'],
                'answer' => 0,
                'explanation' => 'Rasio kontras minimal 4.5:1 untuk teks body (WCAG AA).',
                'sponsor' => null,
            ],
            [
                'slug' => 'dkv-tipografi',
                'track' => 'dkv',
                'title' => 'Pilih hierarki tipe',
                'skill' => 'Tipografi',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Poster event: judul, tanggal, lokasi semua sama besar. Perbaikan utama?',
                'code' => 'H1 = body = caption (16px semua)',
                'options' => ['Besarkan semua jadi 32px', 'Bedakan skala: judul besar, detail kecil, satu fokus', 'Ganti jadi Comic Sans', 'Tambah 5 font berbeda'],
                'answer' => 1,
                'explanation' => 'Satu fokus + skala jelas + maksimal 2 keluarga font.',
                'sponsor' => null,
            ],
            [
                'slug' => 'dkv-layout-grid',
                'track' => 'dkv',
                'title' => 'Rapikan layout zdjęć',
                'skill' => 'UI/UX',
                'type' => 'mcq',
                'xp' => 10,
                'prompt' => 'Galeri karya terlihat berantakan di HP. Solusi tercepat?',
                'code' => 'grid 4 kolom fix di 360px',
                'options' => ['Perkecil semua sampai tak terbaca', 'Grid responsif + spacing konsisten + 1 kolom di HP', 'Sembunyikan separuh karya', 'Hilangkan margin semua'],
                'answer' => 1,
                'explanation' => 'Grid responsif + ritme spacing 8px + uji di 360px.',
                'sponsor' => 'Studio Portofolio',
            ],
        ];
    }
    public static function find(string $slug): ?array {
        foreach (self::all() as $l) if ($l['slug'] === $slug) return $l;
        return null;
    }
    public static function forTrack(string $track): array {
        $t = \App\Domain\Track\Tracks::normalize($track);
        return array_values(array_filter(self::all(), fn($l) => $l['track'] === $t));
    }
    public static function grade(array $lab, string $input): bool {
        if (($lab['type'] ?? 'mcq') === 'calc') {
            return trim(strtolower($input)) === trim(strtolower((string)($lab['calc_answer'] ?? '')));
        }
        return (int)$input === (int)($lab['answer'] ?? -1);
    }
}
