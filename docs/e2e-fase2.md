# E2E Fase 2 — DevOps–RPL + Pro (21/21 PASS)

Tanggal: 2026-09-08. DB uji terisolasi `learn_tracker_e2e` (MySQL lokal Laragon),
skema v34. Server: `php -S 127.0.0.1:8099 router.php`. Suite HTTP session:
21 skenario register → onboarding → track → quest/XP → voucher/Pro → incident →
regresi. Unit CLI: 227 pass.

## Hasil

R1 register, O1 onboarding, Q1/Q2 quests + isolasi custom per track,
T1 switch DevOps↔RPL tersimpan, X1/X2 complete quest + XP akumulasi +
mastery node sesuai track (rpl W1 = `sql`), Z1 quiz topics ikut track,
A1/A2 admin guard (non-admin 404), V1–V5 voucher (buat, redeem Pro,
invalid, expired, limit 1x), P1 grant/revoke Pro, I1 incident gratis,
I2 incident Pro terkunci (redirect pricing), G1/G2 leaderboard + pricing,
M1 viewport + CSS (statis).

## Bug aplikasi yang diperbaiki saat verifikasi

1. `onboarding.php` — `bind_param("sisisi")` 6 tipe untuk 5 var → exception,
   transaksi rollback, user gagal onboard. Jadi `"sissi"`.
2. `leaderboard.php:37` — `$_GET['scope']` undefined saat dipakai di ternary
   → `Keys::leaderboard(null)` → 500 untuk SEMUA user login. Defaultkan dulu.
3. Belum ada ganti track — `users.track` hanya ditulis saat onboarding.
   Baru: `switch_track.php` (POST + CSRF + rate-limit) + switcher di
   `quests.php`.
4. `quests.php` abaikan track (judul hardcode DevOps). Kini filter:
   global tampil semua track, custom ikut track aktif; judul dinamis.
5. `complete_quest.php` mastery pakai minggu DevOps untuk semua track.
   Kini `skill_for_week(week, user_track)`.
6. `quiz.php` topik global. Kini `quiz_topics(user_track)`.

## Batasan pengujian

- Browser MCP men-strip semua `<input>` non-hidden (sanitasi), jadi E2E
  lewat HTTP session (tetap full-stack: HTML + CSRF + cookie + MySQL),
  bukan DOM browser.
- M1 hanya statis (viewport + CSS). Cek visual HP fisik belum dilakukan.
- Voucher expired/limit diuji via aturan implementasi (set `expires_at` /
  `max_uses` langsung + redeem), bukan menunggu waktu nyata.
- Jangan tambah TKJ/DKV sebelum alur ini lolos di Railway juga.
