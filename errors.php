<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_error']) || (isset($_POST['action']) && $_POST['action'] === 'add_error'))) {
    verify_csrf();
    $allowed_cats = ['General','MySQL','PHP','Laravel','Docker','Linux','Git','AWS'];
    $category = clean($_POST['category'] ?? 'General');
    if (!in_array($category, $allowed_cats, true)) $category = 'General';
    $error_message = mb_substr(clean($_POST['error_message'] ?? ''), 0, 2000);
    $solution = mb_substr(clean($_POST['solution'] ?? ''), 0, 2000);
    $reference = valid_url($_POST['reference_link'] ?? '');

    if ($error_message === '') {
        set_flash('warning', "Pesan error tidak boleh kosong!");
    } else {
        $stmt = $conn->prepare("INSERT INTO errors (user_id, category, error_message, solution, reference_link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $category, $error_message, $solution, $reference);
        if ($stmt->execute()) {
            $new_error_id = $stmt->insert_id;
            $xp_gain = 0;
            $quota_left = NOTE_DAILY_XP_CAP - daily_reason_xp($conn, $user_id, 'note');
            if ($quota_left > 0) {
                $xp_gain = min(apply_xp_multiplier(5, mission_multiplier($conn, $user_id)), $quota_left);
                award_xp($conn, $user_id, $xp_gain, 'note', 'error', (int)$new_error_id);
            }

            update_user_streak($conn, $user_id);
            schedule_review($conn, $user_id, 'error', (int)$new_error_id, $error_message, $solution);
            $nb = check_and_unlock_badges($conn, $user_id);

            $msg = $xp_gain > 0 ? "Catatan berhasil disimpan. +{$xp_gain} XP." : "Catatan tersimpan. Kuota XP catatan harian (+" . NOTE_DAILY_XP_CAP . ") tercapai.";
            set_flash('success', $msg . (!empty($nb) ? ' Badge: ' . implode(', ', $nb) . '!' : ''));
        } else {
            set_flash('danger', "Gagal menyimpan error ke database.");
        }
        $stmt->close();
    }
    redirect('errors.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_error']) || (isset($_POST['action']) && $_POST['action'] === 'update_error'))) {
    verify_csrf();
    $error_id = (int)($_POST['error_id'] ?? 0);
    $allowed_cats = ['General','MySQL','PHP','Laravel','Docker','Linux','Git','AWS'];
    $category = clean($_POST['category'] ?? 'General');
    if (!in_array($category, $allowed_cats, true)) $category = 'General';
    $error_message = mb_substr(clean($_POST['error_message'] ?? ''), 0, 2000);
    $solution = mb_substr(clean($_POST['solution'] ?? ''), 0, 2000);
    $reference = valid_url($_POST['reference_link'] ?? '');

    if ($error_message !== '' && $error_id > 0) {
        $stmt = $conn->prepare("UPDATE errors SET category = ?, error_message = ?, solution = ?, reference_link = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ssssii", $category, $error_message, $solution, $reference, $error_id, $user_id);
        if ($stmt->execute()) {
            set_flash('success', "Catatan error berhasil diperbarui!");
        } else {
            set_flash('danger', "Gagal memperbarui error.");
        }
        $stmt->close();
    }
    redirect('errors.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_error') {
    verify_csrf();
    $id = (int)($_POST['error_id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM errors WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->close();
        delete_review($conn, $user_id, 'error', $id);
        set_flash('info', "Catatan dihapus.");
    }
    redirect('errors.php');
}


// Fetch errors (dibatasi 500 terbaru agar halaman tetap ringan)
$stmt = $conn->prepare("SELECT id, user_id, category, error_message, solution, reference_link, created_at FROM errors WHERE user_id = ? ORDER BY created_at DESC LIMIT 500");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$errors = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Compute stats
$total_errors = count($errors);
$solved_errors = 0;
$categories_count = [];

foreach ($errors as $e) {
    if (!empty($e['solution'])) {
        $solved_errors++;
    }
    $cat = $e['category'] ?? 'General';
    $categories_count[$cat] = ($categories_count[$cat] ?? 0) + 1;
}



$page_title = 'Error Log & Solusi Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker"><?= $total_errors ?> catatan · <?= $solved_errors ?> ada solusi</div>
        <h1 class="page-title">Notes & solusi</h1>
        <p class="page-desc">Simpan error dan solusi agar bisa dipakai lagi. Setiap catatan baru +5 XP.</p>
    </div>

    <div class="row g-4">
        <!-- Form Add Error -->
        <div class="col-lg-4">
            <details class="collapsible-card sticky-top" id="errorFormWrap" style="top: 80px;" open>
                <summary class="collapsible-summary">
                    <span class="collapsible-text"><strong>Tambah catatan</strong><small>+5 XP tiap catatan baru</small></span>
                    <i class="fas fa-chevron-down collapsible-chev" aria-hidden="true"></i>
                </summary>
                <div class="collapsible-body">
                <p class="text-secondary small mb-3">Dokumentasikan kendala teknis dan solusi yang berhasil kamu temukan.</p>

                <form method="POST" action="errors.php" data-outbox="error_add">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add_error">

                    <div class="mb-3">
                        <label for="errCategory" class="form-label">Kategori Masalah</label>
                        <select name="category" id="errCategory" class="form-select">
                            <option value="General">General / Lainnya</option>
                            <option value="MySQL">MySQL / Database</option>
                            <option value="PHP">PHP Native / Syntax</option>
                            <option value="Laravel">Laravel Framework</option>
                            <option value="Docker">Docker & Container</option>
                            <option value="Linux">Linux / Terminal / Bash</option>
                            <option value="Git">Git / GitHub</option>
                            <option value="AWS">AWS / Cloud / Nginx</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="errMessage" class="form-label">Pesan Error / Gejala Kendala</label>
                        <textarea name="error_message" id="errMessage" class="form-control" rows="3" placeholder="Contoh: SQLSTATE[HY000] [2002] Connection refused saat connect DB..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="errSolution" class="form-label">Solusi yang Diterapkan</label>
                        <textarea name="solution" id="errSolution" class="form-control" rows="3" placeholder="Contoh: Cek docker-compose port mapping 3306:3306 atau ganti DB_HOST jadi 127.0.0.1"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="errRef" class="form-label">Link Referensi (Opsional)</label>
                        <input type="url" name="reference_link" id="errRef" class="form-control" placeholder="https://stackoverflow.com/... atau docs">
                    </div>

                    <button type="submit" name="add_error" class="btn btn-cyber w-100 py-2">
                        <i class="fas fa-save me-2"></i> Simpan catatan
                    </button>
                </form>
                </div>
            </details>
            <script>(function(){var d=document.getElementById('errorFormWrap');if(d&&matchMedia('(max-width:991.98px)').matches){d.removeAttribute('open');}})();</script>
        </div>

        <!-- Error List & Filter -->
        <div class="col-lg-8">
            <!-- Search & Filter Bar -->
            <div class="mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" id="errorSearch" class="form-control" placeholder="Cari pesan error, solusi, atau tag..." oninput="filterErrors()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-md-end">
                            <div class="segmented" role="group" aria-label="Filter status solusi">
                                <button type="button" class="filter-pill active" onclick="filterByCat('all', this)">Semua</button>
                                <button type="button" class="filter-pill" onclick="filterBySolved('solved', this)">Ada solusi</button>
                                <button type="button" class="filter-pill" onclick="filterBySolved('unsolved', this)">Belum ada</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Categories Quick Pills -->
                <div class="mt-3 pt-3 border-top filter-pills">
                    <?php 
                    $cats = ['MySQL', 'PHP', 'Laravel', 'Docker', 'Linux', 'Git', 'AWS', 'General'];
                    foreach ($cats as $c): 
                        if (isset($categories_count[$c])):
                    ?>
                        <button type="button" class="filter-pill" onclick="filterByCat('<?= $c ?>', this)">
                            <?= $c ?> <span class="badge bg-secondary ms-1"><?= $categories_count[$c] ?></span>
                        </button>
                    <?php endif; endforeach; ?>
                </div>
            </div>

            <!-- Error Items Container -->
            <div id="errorContainer">
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $err): 
                        $has_solution = !empty($err['solution']);
                        $category_val = $err['category'] ?? 'General';
                    ?>
                        <div class="error-card" data-category="<?= htmlspecialchars($category_val) ?>" data-solved="<?= $has_solution ? 'solved' : 'unsolved' ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="small text-secondary">
                                    <?= htmlspecialchars($category_val) ?> · <?= date('d M Y', strtotime($err['created_at'])) ?> · <?= $has_solution ? 'Ada solusi' : 'Belum ada solusi' ?>
                                </div>

                                <div class="d-flex align-items-center gap-1">
                                    <button type="button" class="btn btn-cyber-outline btn-sm py-1 px-2 text-secondary" title="Jadikan kartu kuis" aria-label="Jadikan kartu kuis" onclick="openQuizModal(<?= (int)$err['id'] ?>, <?= htmlspecialchars(json_encode(mb_strimwidth($err['error_message'], 0, 255))) ?>, <?= htmlspecialchars(json_encode($err['solution'] ?? '')) ?>)">
                                        <i class="fas fa-brain"></i>
                                    </button>
                                    <!-- Edit Button -->
                                    <button type="button" class="btn btn-cyber-outline btn-sm py-1 px-2 text-secondary" title="Edit Catatan" onclick="openEditModal(<?= htmlspecialchars(json_encode($err)) ?>)">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>

                                    <form method="POST" action="errors.php" class="m-0" onsubmit="return confirm('Hapus catatan ini?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete_error">
                                        <input type="hidden" name="error_id" value="<?= (int)$err['id'] ?>">
                                        <button type="submit" class="btn btn-cyber-danger btn-sm py-1 px-2" title="Hapus catatan" aria-label="Hapus catatan">
                                            <i class="fas fa-trash-alt" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <div class="mb-2">
                                <div class="small text-secondary mb-1">Error</div>
                                <div class="code-snippet error-text"><?= htmlspecialchars($err['error_message']) ?></div>
                            </div>

                            <!-- Solution -->
                            <?php if ($has_solution): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small text-secondary">Solusi</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-secondary text-decoration-none small copy-btn" onclick="copyToClipboard(<?= htmlspecialchars(json_encode($err['solution'])) ?>, this)">
                                            <i class="far fa-copy me-1"></i> Salin Solusi
                                        </button>
                                    </div>
                                    <div class="code-solution solution-text"><?= htmlspecialchars($err['solution']) ?></div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($err['reference_link'])): ?>
                            <div class="mt-3 pt-2 border-top">
                                <a href="<?= htmlspecialchars($err['reference_link']) ?>" target="_blank" rel="noopener noreferrer" class="small text-secondary text-decoration-none">Referensi <i class="fas fa-arrow-up-right-from-square ms-1" aria-hidden="true" style="font-size:.7rem"></i></a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card p-4 p-md-5 text-center empty-state">
                        <div class="empty-state-icon"><i class="fas fa-shield-alt text-emerald"></i></div>
                        <h2 class="h5 fw-bold mb-2">Belum Ada Catatan Error</h2>
                        <p class="text-secondary small mb-3">Belum pernah stuck atau error? Hebat! Jika kamu menemui bug, catat di formulir sebelah kiri untuk mendapatkan reward +5 XP.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Empty Search Result State -->
            <div id="noErrorsSearch" class="card p-4 p-md-5 text-center empty-state d-none">
                <div class="empty-state-icon"><i class="fas fa-search-minus"></i></div>
                <h2 class="h5 fw-bold mb-2">Tidak ada error yang cocok</h2>
                <p class="text-secondary small mb-0">Coba ubah kata kunci pencarian atau ganti filter kategori.</p>
            </div>
        </div>
    </div>
</main>

<!-- Quiz Modal -->
<div class="modal fade" id="quizModal" tabindex="-1" aria-labelledby="quizModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-bottom">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h2 class="modal-title h6 fw-bold mb-0" id="quizModalLabel"><i class="fas fa-brain text-primary me-2"></i>Jadikan kartu kuis</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <form method="POST" action="quiz.php">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="source" value="error">
                    <input type="hidden" name="source_id" id="quizSourceId">
                    <input type="hidden" name="back" value="errors.php">
                    <div class="mb-3"><label class="form-label" for="quizQuestion">Pertanyaan</label><textarea name="question" id="quizQuestion" class="form-control" rows="2" required maxlength="255"></textarea></div>
                    <div class="mb-1"><label class="form-label" for="quizAnswer">Jawaban</label><textarea name="answer" id="quizAnswer" class="form-control" rows="3" required></textarea></div>
                </div>
                <div class="modal-footer border-top sticky-bottom-bar">
                    <button type="button" class="btn btn-cyber-outline" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-cyber">Simpan kartu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editErrorModal" tabindex="-1" aria-labelledby="editErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h2 class="modal-title h6 fw-bold" id="editErrorModalLabel"><i class="fas fa-pencil-alt text-primary me-2"></i> Edit Catatan Error</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="errors.php">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_error">
                    <input type="hidden" name="error_id" id="modalErrorId">

                    <div class="mb-3">
                        <label for="modalCategory" class="form-label">Kategori</label>
                        <select name="category" id="modalCategory" class="form-select">
                            <option value="General">General / Lainnya</option>
                            <option value="MySQL">MySQL / Database</option>
                            <option value="PHP">PHP Native / Syntax</option>
                            <option value="Laravel">Laravel Framework</option>
                            <option value="Docker">Docker & Container</option>
                            <option value="Linux">Linux / Terminal / Bash</option>
                            <option value="Git">Git / GitHub</option>
                            <option value="AWS">AWS / Cloud / Nginx</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modalMessage" class="form-label">Pesan Error</label>
                        <textarea name="error_message" id="modalMessage" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="modalSolution" class="form-label">Solusi</label>
                        <textarea name="solution" id="modalSolution" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="modalRef" class="form-label">Link Referensi</label>
                        <input type="url" name="reference_link" id="modalRef" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-top">
                    <button type="button" class="btn btn-cyber-outline btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="update_error" class="btn btn-cyber btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let activeCat = 'all';
let activeSolved = 'all';

function openQuizModal(id, question, answer) {
    document.getElementById('quizSourceId').value = id;
    document.getElementById('quizQuestion').value = question || '';
    document.getElementById('quizAnswer').value = answer || '';
    new bootstrap.Modal(document.getElementById('quizModal')).show();
}

function openEditModal(err) {
    document.getElementById('modalErrorId').value = err.id;
    document.getElementById('modalCategory').value = err.category || 'General';
    document.getElementById('modalMessage').value = err.error_message || '';
    document.getElementById('modalSolution').value = err.solution || '';
    document.getElementById('modalRef').value = err.reference_link || '';

    const modal = new bootstrap.Modal(document.getElementById('editErrorModal'));
    modal.show();
}

function clearSegmented(except) {
    document.querySelectorAll('.segmented .filter-pill').forEach(b => {
        if (b !== except) b.classList.remove('active');
    });
}

function filterByCat(cat, btn) {
    if (btn.closest('.segmented') && cat === 'all') {
        activeCat = 'all';
        activeSolved = 'all';
        document.querySelectorAll('.filter-pills button').forEach(b => b.classList.remove('active'));
        clearSegmented(btn);
        btn.classList.add('active');
    } else {
        activeCat = cat;
        document.querySelectorAll('.filter-pills button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }
    applyErrorFilters();
}

function filterBySolved(solved, btn) {
    activeSolved = solved;
    clearSegmented(btn);
    btn.classList.add('active');
    applyErrorFilters();
}

function filterErrors() {
    applyErrorFilters();
}

function applyErrorFilters() {
    const query = (document.getElementById('errorSearch').value || '').toLowerCase().trim();
    const cards = document.querySelectorAll('.error-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const cat = card.getAttribute('data-category');
        const solved = card.getAttribute('data-solved');
        const errText = (card.querySelector('.error-text')?.textContent || '').toLowerCase();
        const solText = (card.querySelector('.solution-text')?.textContent || '').toLowerCase();

        const matchCat = (activeCat === 'all' || activeCat === cat);
        const matchSolved = (activeSolved === 'all' || activeSolved === solved);
        const matchQuery = (!query || errText.includes(query) || solText.includes(query) || cat.toLowerCase().includes(query));

        if (matchCat && matchSolved && matchQuery) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    const noResult = document.getElementById('noErrorsSearch');
    if (cards.length > 0) {
        if (visibleCount === 0) {
            noResult.classList.remove('d-none');
        } else {
            noResult.classList.add('d-none');
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
