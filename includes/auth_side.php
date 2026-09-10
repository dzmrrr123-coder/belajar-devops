<?php
// Panel samping halaman auth (desktop): 3 langkah mulai + jurusan. $auth_mode = 'login'|'register'.
$auth_mode = $auth_mode ?? 'register';
?>
<aside class="auth-side" aria-label="Cara mulai">
    <h2><?= $auth_mode === 'login' ? 'Lanjutkan streak-mu' : 'Mulai dalam 1 menit' ?></h2>
    <p><?= $auth_mode === 'login' ? 'Masuk dan kerjakan quest berikutnya. Streak dan XP menunggumu.' : 'Daftar gratis, quest minggu pertama langsung jadi.' ?></p>
    <ol class="auth-steps">
        <li><span class="n" aria-hidden="true">1</span><div><strong><?= $auth_mode === 'login' ? 'Masuk' : 'Buat akun' ?></strong><small>Username + email<?= $auth_mode === 'login' ? ', lanjut' : ' + kata sandi' ?></small></div></li>
        <li><span class="n" aria-hidden="true">2</span><div><strong>Atur start 1 menit</strong><small>Pilih jurusan + target PKL</small></div></li>
        <li><span class="n" aria-hidden="true">3</span><div><strong>Kerjakan quest pertama</strong><small>+XP + streak hari ini</small></div></li>
    </ol>
    <div class="auth-tracks" aria-label="Jurusan tersedia">
        <?php foreach (\App\Domain\Track\Tracks::all() as $slug => $tr): ?>
        <span class="stat-chip"><i class="<?= htmlspecialchars($tr['icon'] ?? 'fas fa-graduation-cap') ?>" aria-hidden="true"></i><?= htmlspecialchars($tr['name']) ?></span>
        <?php endforeach; ?>
    </div>
    <p class="fine">Gratis · tanpa kartu · bisa ganti jurusan kapan saja.</p>
</aside>
