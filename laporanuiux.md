# Laporan Pembaruan UI/UX dan Optimasi Sistem

**Tanggal:** 8 September 2026  
**Status:** Diterapkan & Dalam Pengerjaan  
**Fokus Utama:** Isolasi Jurusan (Track Isolation), Pembersihan Dashboard (De-cluttering), Optimasi Performa, dan Gamifikasi.

---

## 1. Ringkasan Eksekutif (Executive Summary)
Pembaruan UI/UX terbaru bertujuan untuk menciptakan pengalaman belajar yang lebih terarah, fokus, dan responsif. Masalah utama yang diatasi adalah *cognitive overload* pada user akibat terlalu banyak menu dan materi yang tidak relevan dengan jurusannya, serta isu *layout* pada elemen navigasi global.

Pembaruan ini secara signifikan merombak alur navigasi dengan memperkenalkan konsep **Isolasi Jurusan**, mengoptimalkan kecepatan muat halaman (*load time*), serta menyempurnakan elemen-elemen visual pendukung motivasi (gamifikasi).

---

## 2. Refactoring UI/UX & Isolasi Jurusan (Track Isolation)

Demi meningkatkan efektivitas belajar pengguna, antarmuka kini disesuaikan secara ketat berdasarkan jurusan (*track*) yang dipilih. 

### 2.1. De-clutter Dashboard (`index.php`)
- **Penyederhanaan Tampilan:** Elemen-elemen yang sebelumnya menumpuk dan berpotensi memecah fokus telah dihapus atau dipindahkan ke submenu yang relevan.
- **Informasi Kontekstual:** Metrik dan kurasi materi di *dashboard* kini sepenuhnya berfokus pada progres aktif di jurusan yang sedang diambil oleh pengguna.

### 2.2. Implementasi Track Guard (`includes/track_guard.php`)
- **Fungsi:** Sebuah sistem pengamanan logika navigasi baru yang memastikan pengguna tidak secara tidak sengaja "tersesat" ke materi atau *quest* di luar kurikulum jurusannya.
- **UX Impact:** Mengurangi rasa bingung bagi pengguna baru dan mencegah akses data lintas jurusan yang tidak relevan, menciptakan lingkungan *sandbox* yang aman untuk setiap *track*.

### 2.3. Kurasi Materi Adaptif
- Halaman inti seperti `lab.php`, `onboarding.php`, `quests.php`, dan `profile.php` telah direfaktor sehingga *state* UI-nya secara dinamis membaca data jurusan saat ini. Pengguna hanya melihat apa yang perlu mereka lihat.

---

## 3. Perbaikan Navigasi & Tata Letak (Navigation Fixes)

Fokus perbaikan pada komponen navigasi global untuk meningkatkan kegunaan, khususnya pada perangkat dengan resolusi layar bervariasi.

### 3.1. Dropdown "Jelajah" (Explore Dropdown)
- **Bug Fix:** Memperbaiki insiden di mana menu dropdown sering menembus bagian bawah layar (*overflow*) dan *state*-nya tersangkut (tidak bisa ditutup).
- **Penyesuaian CSS (`assets/css/app.css`):** 
  - Menerapkan batasan tinggi maksimal (*max-height*) dipadukan dengan *scroll*.
  - Mengubah *layout* menjadi sistem *grid* 2 kolom yang rapi khusus saat ditampilkan di layar desktop (grid hanya diaktifkan saat class `.show` aktif).
- **Hasil:** Interaksi menu menjadi lebih *smooth*, dapat diprediksi, dan tidak mengganggu konten di bawahnya.

---

## 4. Gamifikasi & *Polish* Visual

Elemen motivasi visual telah disempurnakan untuk memberikan *reward feedback* yang lebih berkesan saat pengguna mencapai target.

- **Label Kombo & Tier Emas:** Sentuhan *polishing* pada komponen kelas (class CSS) untuk menonjolkan pencapaian *streak* (kombo) pengguna dan status Tier Emas. Efek visualnya kini dibuat lebih *striking* (menonjol) namun tetap harmonis dengan tema warna utama aplikasi.

---

## 5. Optimasi Performa Front-end & Back-end

Peningkatan UX tidak hanya dari sisi visual, melainkan juga seberapa instan aplikasi merespons interaksi.

- **Kondisional JavaScript & Caching:** 
  - Memastikan *script* (*JS kondisional*) hanya dimuat di halaman yang benar-benar membutuhkannya.
  - Implementasi teknik *cache* agresif dan optimalisasi di sisi `sw.js` (Service Worker) untuk aset statis.
- **Efisiensi Database (`db/migrations/059_perf_indexes.sql`):** 
  - Pengurangan *query* yang redundan saat memuat dashboard.
  - Penambahan indeks pada tabel kritis untuk mempercepat pengambilan data performa (*pulse/activity*).
- **Hasil:** Penurunan drastis pada waktu tunggu render *dashboard* dan perpindahan halaman.

---

## 6. Fitur Baru dalam Pengerjaan (Work In Progress): Hub Jurusan

Saat ini sedang dikembangkan halaman terdedikasi khusus untuk masing-masing jurusan, yaitu **Hub Jurusan**.
- **Komponen:** `hub.php`, `app/Domain/Track/Hub.php`, dan spesifikasi desain navigasi *sidebar* baru (`assets/css/sidebar.css`).
- **Tujuan UX:** Menyediakan satu portal sentral (*hub*) di mana pengguna dapat melihat peta jalan belajar (*roadmap*), proyek yang berjalan, dan pencapaian spesifik jurusannya secara komprehensif tanpa harus berpindah-pindah menu.

---

*Laporan ini disusun sebagai dokumentasi resmi perubahan UI/UX guna menjaga keselarasan desain dan arsitektur pengembangan di masa mendatang.*
