# 🎮 Learn Tracker - Gamified DevOps Learning Platform

Aplikasi pelacak progres belajar interaktif dengan sistem gamifikasi (*Experience Points*, *Character Levels*, *Daily Streaks*, *Quest Board*, *Pomodoro Focus Station*, dan *Troubleshooting Error Log*) yang dirancang khusus untuk persiapan PKL dan karir **DevOps & Backend Engineer**.

---

## ✨ Fitur Utama

- ⚡ **Sistem Gamifikasi & Rank**:
  - Peringkat otomatis berdasarkan Level (mulai dari *Terminal Cadet* hingga *DevOps Legend*).
  - Progress bar dinamis, persentase XP level aktif, dan HUD status bar terintegrasi.
- 📜 **Papan Quest 12 Minggu**:
  - 14 Quest kurikulum lengkap sesuai roadmap industri (Database Relasional, PHP OOP, Laravel, Docker, CI/CD, AWS EC2, Nginx, SSL HTTPS).
  - Checklist interaktif via AJAX tanpa reload halaman + efek suara kemenangan & semburan confetti.
  - Dukungan toggle & batalkan selesai jika terjadi kekeliruan.
- 🍅 **Pomodoro Focus Station**:
  - Timer visual dengan SVG circular progress animasi.
  - Mode Fokus (25m), Istirahat Pendek (5m), dan Istirahat Panjang (15m).
  - Reward **+10 XP** dan pencatatan sesi harian setiap menyelesaikan sesi fokus.
  - Alarm suara otomatis via Web Audio API tanpa file eksternal & notifikasi browser.
- 🐞 **Jurnal Error & Troubleshooting**:
  - Catat pesan error, tag kategori (*MySQL, PHP, Laravel, Docker, Linux, Git, AWS*), solusi, dan referensi (+5 XP).
  - Tombol instan *Salin Solusi* ke clipboard, modal edit catatan, dan pencarian instan.
- 📚 **Sumber Belajar Terkurasi (36 Materi)**:
  - 36 referensi pilihan terbagi dalam 3 kategori: **Video Tutorial**, **Dokumentasi Resmi**, dan **Praktek/Lab Interaktif**.
  - Filter interaktif per minggu (1–12) dan per tipe materi.
- 🎨 **Desain Cyberpunk-DevOps Modern**:
  - Glassmorphism UI berbasis dark theme dengan saturasi neon elektrik.
  - Animasi api streak dinamis (`flameBounce`), kartu 3D lift, dan audit skor aksesibilitas **100% (A11y Compliant)**.
- 🎯 **Misi Harian & Multiplier**:
  - 3 misi auto-detect (1 quest, 1 sesi fokus, 1 catatan), masing-masing **+5 XP**.
  - Multiplier **x1.5** aktif saat semua misi harian selesai.
- 🧩 **Quest Custom & Subtask**:
  - Buat quest sendiri (max 20 XP) + langkah kecil checklist per quest.
- 🔁 **Review Inbox (Spaced Repetition)**:
  - Quest, catatan, dan jawaban otomatis terjadwal ulang 1-3-7-14-30 hari. Mode satu kartu: Tahu / Lupa.
- 🏅 **12 Badge + Streak Freeze**:
  - Badge otomatis (Quest Hunter, Deep Worker, Bug Hunter, streak 7/30, dll) + token freeze penyelamat streak mingguan.
- 🏆 **Leaderboard & Profil Publik**:
  - Opt-in, tanpa email, tab Total XP / Minggu ini. Profil publik `/u/username` siap share + export portofolio (Print/PDF, Markdown, JSON).
- 📳 **PWA Mobile-First**:
  - Bottom tabbar 5 tab, tombol ≥44px, antrean offline otomatis (quest, pomodoro, misi, subtask, review), install ke layar utama.

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.x Native (Clean, modular, prepared statement MySQLi)
- **Database**: MySQL / MariaDB (InnoDB, Foreign Key Integrity, utf8mb4)
- **Frontend & Styling**: Bootstrap 5.3, Custom CSS Design System, FontAwesome 6, Plus Jakarta Sans & JetBrains Mono
- **Interaktivitas & Audio**: Vanilla JavaScript, Web Audio API Sound Synthesizer, Canvas Confetti

---

## 🚀 Panduan Instalasi & Menjalankan

### 1. Clone Repositori
```bash
git clone https://github.com/dzmrrr123-coder/learn-tracker.git
cd learn-tracker
```

### 2. Import Database
Pastikan layanan MySQL aktif (Laragon / XAMPP / MariaDB), lalu import:
```bash
mysql -u root -p < database.sql
```
*Atau import skema dasar melalui `schema.sql`.*

### 3. Konfigurasi Database
Via environment variable (`.env`, didukung Railway `MYSQL*`):
```
DB_HOST=localhost
DB_PORT=3306
DB_USER=root
DB_PASS=
DB_NAME=learn-tracker
```
Skema auto-migrate saat konek (`ensure_database_schema()`), jadi DB lama ikut ter-upgrade. Cek `health.php` untuk diagnostik tabel.

### 4. Buka di Browser
Akses melalui web server lokal:
```
http://localhost/kasir-minimarket/learn-tracker/
# atau
http://localhost/learn-tracker/
```

---

## 🗺️ Roadmap 12 Minggu

| Minggu | Quest | Fokus Materi | Reward |
| :---: | :--- | :--- | :---: |
| **1** | Bikin Database Tokoonline | Relational Schema & Table Design | +15 XP |
| **1** | Jauhkan Foreign Key & Relasi Kuat | Constraints, Indexing & Cascade | +10 XP |
| **2** | CRUD Produk PHP Native | Prepared Statements & Sanitasi SQL | +20 XP |
| **2** | Login & Auth System Terproteksi | BCRYPT Password Hashing & Sesi | +15 XP |
| **3** | Refactor Kode ke OOP PHP | Encapsulation, Class, & Object | +25 XP |
| **4** | Polimorfisme & Design Pattern | Inheritance, Interface & SOLID | +20 XP |
| **5** | Migrasi ke Laravel Framework | MVC, Migrations, & Eloquent ORM | +30 XP |
| **6** | Auth Laravel & Custom Middleware | Breeze Auth & Role Route Guard | +25 XP |
| **7** | Dockerize App (PHP, Nginx, MySQL) | Dockerfile & Docker Compose | +30 XP |
| **8** | Build & Push ke Docker Hub | Production Build & Semantic Tags | +20 XP |
| **9** | Deploy ke AWS EC2 Linux VPS | SSH Key, Ubuntu Server, & Security Group | +40 XP |
| **10** | Live URL, Domain & SSL Certbot | Nginx Reverse Proxy & Let's Encrypt | +30 XP |
| **11** | Rapikan Portfolio GitHub & Readme | Architecture Diagrams & Showcase | +35 XP |
| **12** | Review Akhir, Portofolio & CV DevOps | Mock Interview & Final Audit | +25 XP |

---

## 🎯 Sistem Gamifikasi

- **Perolehan XP** (anti-farm: ada cap harian):
  - Selesaikan Quest: **+10 s/d +40 XP** (custom max 20)
  - Sesi Pomodoro (25 menit fokus): **+10 XP**
  - Catat Error & Solusi: **+5 XP**, maks **+25 XP/hari**
  - Kuis: **+2 XP** per kartu Tahu, maks **+20 XP/hari** (kartu tuntas tak muncul lagi hari itu)
  - Klaim Misi Harian: **+5 XP** per misi (3/hari)
  - Review pertama per kartu: **+5 XP**
  - Question → Error Log: **+5 XP** (masuk kuota catatan harian)
  - Semua tercatat di ledger `xp_events` (basis leaderboard mingguan)
- **Kalkulasi Level**: `Level = floor(sqrt(XP / 100)) + 1`
- **Tingkatan Rank**:
  - Lv 1: *Terminal Cadet*
  - Lv 2: *Junior Scripter*
  - Lv 3: *Git Wrangler*
  - Lv 4: *Backend Craftsman*
  - Lv 5: *Docker Apprentice*
  - Lv 6: *Container Captain*
  - Lv 7: *Cloud Pioneer*
  - Lv 8: *DevOps Specialist*
  - Lv 9: *CI/CD Architect*
  - Lv 10: *Site Reliability Engineer*
  - Lv 11: *Cloud Guru*
  - Lv 12: *DevOps Legend*

---

## 👨‍💻 Kontributor

Dibuat dengan ❤️ untuk persiapan PKL SMK RPL menuju profesional **DevOps & Cloud Engineering**.
GitHub: [@dzmrrr123-coder](https://github.com/dzmrrr123-coder)
