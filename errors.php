<?php
require_once 'config.php';
require_login();

$conn = db_connect();
$user_id = (int)$_SESSION['user_id'];

// Add new error
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_error']) || (isset($_POST['action']) && $_POST['action'] === 'add_error'))) {
    verify_csrf();
    $category = clean($_POST['category'] ?? 'General');
    $error_message = clean($_POST['error_message'] ?? '');
    $solution = clean($_POST['solution'] ?? '');
    $reference = clean($_POST['reference_link'] ?? '');

    if (!empty($error_message)) {
        $stmt = $conn->prepare("INSERT INTO errors (user_id, category, error_message, solution, reference_link) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $category, $error_message, $solution, $reference);
        if ($stmt->execute()) {
            // Reward +5 XP
            $stmt_xp = $conn->prepare("UPDATE users SET xp = xp + 5 WHERE id = ?");
            $stmt_xp->bind_param("i", $user_id);
            $stmt_xp->execute();
            $stmt_xp->close();

            update_user_streak($conn, $user_id);

            set_flash('success', "Catatan berhasil disimpan. Kamu mendapatkan +5 XP.");
        } else {
            set_flash('danger', "Gagal menyimpan error ke database.");
        }
        $stmt->close();
    } else {
        set_flash('warning', "Pesan error tidak boleh kosong!");
    }
    redirect('errors.php');
}

// Update existing error
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['update_error']) || (isset($_POST['action']) && $_POST['action'] === 'update_error'))) {
    verify_csrf();
    $error_id = (int)$_POST['error_id'];
    $category = clean($_POST['category'] ?? 'General');
    $error_message = clean($_POST['error_message'] ?? '');
    $solution = clean($_POST['solution'] ?? '');
    $reference = clean($_POST['reference_link'] ?? '');

    if (!empty($error_message)) {
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

// Delete error
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $token = $_GET['token'] ?? '';
    if (hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        $stmt = $conn->prepare("DELETE FROM errors WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $user_id);
        $stmt->execute();
        $stmt->close();
        set_flash('info', "Catatan error telah dihapus.");
    } else {
        set_flash('danger', "Token keamanan tidak valid untuk penghapusan.");
    }
    redirect('errors.php');
}

// Fetch all errors
$stmt = $conn->prepare("SELECT * FROM errors WHERE user_id = ? ORDER BY created_at DESC");
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

$conn->close();

$page_title = 'Error Log & Solusi Belajar';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <!-- Header Banner -->
    <div class="notes-hero card p-4 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge" style="background: rgba(244, 63, 94, 0.2); color: #fda4af; border: 1px solid rgba(244, 63, 94, 0.4);">
                        <i class="fas fa-note-sticky me-1"></i> Notes & Errors
                    </span>
                    <span class="badge" style="background: rgba(245, 158, 11, 0.2); color: #fde68a;">
                        Dokumentasi pembelajaran
                    </span>
                </div>
                <h1 class="h3 fw-bold mb-2">Notes & solusi belajar</h1>
                <p class="text-secondary small mb-0">
                    Simpan error, insight, dan solusi agar pengalaman belajar bisa kamu gunakan kembali saat dibutuhkan.
                </p>
            </div>

            <div class="col-lg-5">
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-white fw-bold fs-4"><?= $total_errors ?></div>
                            <div class="text-secondary small">Total Error</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-emerald fw-bold fs-4"><?= $solved_errors ?></div>
                            <div class="text-secondary small">Terselesaikan</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-gold fw-bold fs-4">+<?= $total_errors * 5 ?></div>
                            <div class="text-secondary small">XP Didapat</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Form Add Error -->
        <div class="col-lg-4">
            <div class="card p-4 sticky-top" style="top: 80px;">
                <h2 class="h5 fw-bold mb-1 d-flex align-items-center gap-2">
                    <i class="fas fa-plus-circle text-primary"></i> Tambah catatan
                </h2>
                <p class="text-secondary small mb-3">Dokumentasikan kendala teknis dan solusi yang berhasil kamu temukan.</p>

                <form method="POST" action="errors.php">
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
        </div>

        <!-- Error List & Filter -->
        <div class="col-lg-8">
            <!-- Search & Filter Bar -->
            <div class="card p-3 mb-4">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text border-0" style="background: rgba(15, 23, 42, 0.9); color: var(--text-muted);"><i class="fas fa-search"></i></span>
                            <input type="text" id="errorSearch" class="form-control" placeholder="Cari pesan error, solusi, atau tag..." oninput="filterErrors()">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                            <button type="button" class="filter-pill active" onclick="filterByCat('all', this)">Semua Tag</button>
                            <button type="button" class="filter-pill" onclick="filterBySolved('solved', this)"><i class="fas fa-check text-emerald me-1"></i> Solved</button>
                            <button type="button" class="filter-pill" onclick="filterBySolved('unsolved', this)"><i class="fas fa-clock text-warning me-1"></i> Pending</button>
                        </div>
                    </div>
                </div>

                <!-- Categories Quick Pills -->
                <div class="mt-3 pt-3 border-top filter-pills" style="border-color: var(--border-subtle) !important;">
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
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge bg-dark border border-secondary text-light px-2 py-1" style="font-size: 0.75rem;">
                                        <i class="fas fa-tag me-1 text-cyan"></i> <?= htmlspecialchars($category_val) ?>
                                    </span>
                                    <?php if ($has_solution): ?>
                                        <span class="badge bg-success bg-opacity-25 text-emerald border border-success border-opacity-25 px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="fas fa-check-circle me-1"></i> Solved
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning bg-opacity-25 text-gold border border-warning border-opacity-25 px-2 py-1" style="font-size: 0.72rem;">
                                            <i class="fas fa-hourglass-half me-1"></i> Belum ada solusi
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex align-items-center gap-1">
                                    <!-- Edit Button -->
                                    <button type="button" class="btn btn-cyber-outline btn-sm py-1 px-2 text-secondary" title="Edit Catatan" onclick="openEditModal(<?= htmlspecialchars(json_encode($err)) ?>)">
                                        <i class="fas fa-pencil-alt"></i>
                                    </button>

                                    <!-- Delete Link -->
                                    <a href="errors.php?delete=<?= (int)$err['id'] ?>&token=<?= urlencode(csrf_token()) ?>" class="btn btn-cyber-danger btn-sm py-1 px-2" title="Hapus Error" onclick="return confirm('Hapus catatan error ini?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <div class="mb-2">
                                <div class="text-secondary small fw-bold mb-1"><i class="fas fa-exclamation-triangle text-danger me-1"></i> Error:</div>
                                <div class="code-snippet error-text"><?= htmlspecialchars($err['error_message']) ?></div>
                            </div>

                            <!-- Solution -->
                            <?php if ($has_solution): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="text-secondary small fw-bold"><i class="fas fa-check-circle text-emerald me-1"></i> Solusi:</span>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-secondary text-decoration-none small copy-btn" onclick="copyToClipboard(<?= htmlspecialchars(json_encode($err['solution'])) ?>, this)">
                                            <i class="far fa-copy me-1"></i> Salin Solusi
                                        </button>
                                    </div>
                                    <div class="code-solution solution-text"><?= htmlspecialchars($err['solution']) ?></div>
                                </div>
                            <?php endif; ?>

                            <!-- Reference & Footer -->
                            <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-2 border-top" style="border-color: var(--border-subtle) !important;">
                                <div>
                                    <?php if (!empty($err['reference_link'])): ?>
                                        <a href="<?= htmlspecialchars($err['reference_link']) ?>" target="_blank" rel="noopener noreferrer" class="small text-cyan text-decoration-none">
                                            <i class="fas fa-external-link-alt me-1"></i> Referensi / Dokumentasi
                                        </a>
                                    <?php else: ?>
                                        <span class="small text-muted fst-italic">Tidak ada link referensi</span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="far fa-clock me-1"></i> <?= date('d M Y, H:i', strtotime($err['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="card p-5 text-center empty-state">
                        <div class="empty-state-icon"><i class="fas fa-shield-alt text-emerald"></i></div>
                        <h2 class="h5 fw-bold mb-2">Belum Ada Catatan Error</h2>
                        <p class="text-secondary small mb-3">Belum pernah stuck atau error? Hebat! Jika kamu menemui bug, catat di formulir sebelah kiri untuk mendapatkan reward +5 XP.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Empty Search Result State -->
            <div id="noErrorsSearch" class="card p-5 text-center empty-state d-none">
                <div class="empty-state-icon"><i class="fas fa-search-minus"></i></div>
                <h2 class="h5 fw-bold mb-2">Tidak ada error yang cocok</h2>
                <p class="text-secondary small mb-0">Coba ubah kata kunci pencarian atau ganti filter kategori.</p>
            </div>
        </div>
    </div>
</main>

<!-- Edit Modal -->
<div class="modal fade" id="editErrorModal" tabindex="-1" aria-labelledby="editErrorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: var(--bg-surface-elevated); border: 1px solid var(--border-subtle); color: #fff; border-radius: var(--radius-md);">
            <div class="modal-header border-bottom" style="border-color: var(--border-subtle) !important;">
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
                <div class="modal-footer border-top" style="border-color: var(--border-subtle) !important;">
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

function openEditModal(err) {
    document.getElementById('modalErrorId').value = err.id;
    document.getElementById('modalCategory').value = err.category || 'General';
    document.getElementById('modalMessage').value = err.error_message || '';
    document.getElementById('modalSolution').value = err.solution || '';
    document.getElementById('modalRef').value = err.reference_link || '';

    const modal = new bootstrap.Modal(document.getElementById('editErrorModal'));
    modal.show();
}

function filterByCat(cat, btn) {
    activeCat = cat;
    document.querySelectorAll('.filter-pills button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyErrorFilters();
}

function filterBySolved(solved, btn) {
    activeSolved = solved;
    btn.parentElement.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
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
