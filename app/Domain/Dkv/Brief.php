<?php
namespace App\Domain\Dkv;
class Brief {
    public static function all(): array {
        return [
            ['slug' => 'poster-acara', 'week' => 1, 'title' => 'Poster acara sekolah', 'goal' => 'Buat poster 1 pesan jelas terbaca dari 3 meter.', 'constraints' => ['1 fokus pesan', 'Maks 2 font', 'Kontras AA'], 'deliverable' => 'PNG + link Figma/Canva', 'tip' => 'Uji kontras + hierarki sebelum upload.'],
            ['slug' => 'sistem-tipo', 'week' => 2, 'title' => 'Sistem tipografi mini', 'goal' => 'Susun skala H1/body/caption yang konsisten.', 'constraints' => ['Skala 1.25x', 'Line-height 1.5 body', '1 keluarga font'], 'deliverable' => 'Screenshot skala tipe', 'tip' => 'Rubrik tipografi bobot 20%.'],
            ['slug' => 'logo-umkm', 'week' => 4, 'title' => 'Logo UMKM + varian', 'goal' => 'Identitas sederhana yang jalan di stempel & avatar.', 'constraints' => ['Versi 1 warna', 'Clearspace', 'Tanpa gradien berlebihan'], 'deliverable' => 'Logo primer + monochrome', 'tip' => 'Minta critique guru di Kelas.'],
            ['slug' => 'landing-ppdb', 'week' => 5, 'title' => 'Landing PPDB 1 layar', 'goal' => 'Wireframe → UI 1 layar dengan CTA jelas.', 'constraints' => ['Grid 8px', 'CTA 1 saja', 'Mobile 360px'], 'deliverable' => 'Link prototype + PNG', 'tip' => 'Uji 5 detik: user paham CTA?'],
            ['slug' => 'ilustrasi-maskot', 'week' => 9, 'title' => 'Maskot kelas', 'goal' => 'Maskot vektor 2 pose untuk badge/event.', 'constraints' => ['Palet 3 warna', 'Siluet terbaca kecil', 'Ekspresi konsisten'], 'deliverable' => 'PNG 2 pose', 'tip' => 'Cek siluet di 32px.'],
            ['slug' => 'bumper-motion', 'week' => 10, 'title' => 'Bumper motion 5 detik', 'goal' => 'Intro 5 detik untuk video karya.', 'constraints' => ['Maks 5 detik', 'Safe area teks', 'Tanpa audio pecah'], 'deliverable' => 'GIF/MP4 + storyboard', 'tip' => 'Easing halus, teks < 6 kata.'],
        ];
    }
    public static function forWeek(int $week): ?array {
        foreach (self::all() as $b) if ((int)$b['week'] === $week) return $b;
        return null;
    }
}
