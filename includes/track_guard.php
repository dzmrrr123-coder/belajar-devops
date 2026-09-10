<?php
/**
 * Track Guard: Memberikan pembatas yang jelas antar jurusan
 * Jika siswa membuka halaman jurusan lain, tampilkan halaman panduan ramah,
 * arahkan ke fitur jurusannya sendiri, atau beri opsi ganti jurusan.
 */
function enforce_track_access(\mysqli $conn, int $user_id, array $allowed_tracks, string $feature_title): void {
    $myTrack = user_track($conn, $user_id);
    $isAdmin = is_admin($conn, $user_id) || \App\Domain\Auth\Roles::isGuru($conn, $user_id);
    if ($isAdmin || in_array($myTrack, $allowed_tracks, true)) {
        return; // Akses diizinkan
    }

    $tracks = \App\Domain\Track\Tracks::all();
    $myTrackInfo = $tracks[$myTrack] ?? ['name' => strtoupper($myTrack), 'desc' => ''];
    $allowedNames = array_map(fn($t) => $tracks[$t]['name'] ?? strtoupper($t), $allowed_tracks);
    $allowedNamesStr = implode(' / ', $allowedNames);
    $primaryMyTrack = \App\Domain\Track\Tracks::primaryFeature($myTrack);

    $page_title = 'Akses Khusus Jurusan ' . $allowedNamesStr;
    require_once __DIR__ . '/header.php';
    require_once __DIR__ . '/navbar.php';
    ?>
    <main class="container py-5" role="main">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                <div class="card p-4 p-md-5 text-center shadow-sm">
                    <div class="d-inline-flex align-items-center justify-content-center mx-auto mb-3 rounded-circle bg-warning bg-opacity-10 text-warning" style="width: 64px; height: 64px; font-size: 1.75rem;">
                        <i class="fas fa-compass" aria-hidden="true"></i>
                    </div>

                    <div class="page-kicker eyebrow mb-1">Eksklusif Jurusan <?= htmlspecialchars($allowedNamesStr) ?></div>
                    <h1 class="h3 fw-bold mb-2"><?= htmlspecialchars($feature_title) ?></h1>
                    
                    <p class="text-secondary mb-4">
                        Halaman ini merupakan laboratorium khusus untuk siswa jurusan <strong><?= htmlspecialchars($allowedNamesStr) ?></strong>.
                        Saat ini jurusan aktif akunmu adalah <span class="badge bg-primary bg-opacity-10 text-primary px-2 py-1"><i class="<?= htmlspecialchars($myTrackInfo['icon'] ?? 'fas fa-graduation-cap') ?> me-1"></i><?= htmlspecialchars($myTrackInfo['name']) ?></span>.
                    </p>

                    <div class="card bg-body-tertiary p-3 mb-4 text-start border-0">
                        <div class="d-flex align-items-start gap-3">
                            <div class="fs-4 text-primary mt-1"><i class="<?= htmlspecialchars($primaryMyTrack['icon']) ?>"></i></div>
                            <div>
                                <strong class="d-block mb-1">Rekomendasi untuk jurusanmu:</strong>
                                <p class="small text-muted mb-2"><?= htmlspecialchars($primaryMyTrack['title']) ?> — <?= htmlspecialchars($primaryMyTrack['desc']) ?></p>
                                <a href="<?= htmlspecialchars($primaryMyTrack['href']) ?>" class="btn btn-cyber btn-sm">
                                    <i class="<?= htmlspecialchars($primaryMyTrack['icon']) ?> me-1" aria-hidden="true"></i> <?= htmlspecialchars($primaryMyTrack['cta']) ?>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-2 mb-4">
                        <a href="quests.php" class="btn btn-cyber-outline">
                            <i class="fas fa-map me-1" aria-hidden="true"></i> Buka Roadmap <?= htmlspecialchars($myTrackInfo['name']) ?>
                        </a>
                        <a href="quests.php" class="btn btn-cyber-outline">
                            <i class="fas fa-map me-1" aria-hidden="true"></i> Roadmap
                        </a>
                    </div>

                    <hr class="my-4">

                    <div class="small text-muted">
                        <p class="mb-2">Salah memilih jurusan saat pendaftaran atau ingin berpindah track?</p>
                        <form method="POST" action="switch_track.php" class="d-inline-flex flex-wrap align-items-center justify-content-center gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="back" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'quests.php') ?>">
                            <select name="track" class="form-select form-select-sm w-auto" aria-label="Pilih jurusan tujuan">
                                <?php foreach ($tracks as $slug => $tr): ?>
                                    <option value="<?= htmlspecialchars($slug) ?>" <?= in_array($slug, $allowed_tracks, true) ? 'selected' : '' ?>>
                                        Pindah ke <?= htmlspecialchars($tr['name']) ?> (<?= htmlspecialchars($tr['desc']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-cyber-outline btn-sm">
                                <i class="fas fa-arrows-rotate me-1" aria-hidden="true"></i> Ganti Jurusan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php
    require_once __DIR__ . '/footer.php';
    if (isset($conn) && $conn instanceof \mysqli && $conn->ping()) {
        $conn->close();
    }
    exit();
}
