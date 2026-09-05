<?php
require_once 'config.php';
require_login();

$conn = db_connect();

$week_filter = isset($_GET['week']) ? (int)$_GET['week'] : 0;

// Query resources
if ($week_filter > 0) {
    $stmt = $conn->prepare("SELECT * FROM resources WHERE week = ? ORDER BY type ASC, id ASC");
    $stmt->bind_param("i", $week_filter);
    $stmt->execute();
    $resources = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM resources ORDER BY week ASC, type ASC, id ASC");
    $resources = $result->fetch_all(MYSQLI_ASSOC);
}

// Compute counts
$total_count = count($resources);
$video_count = 0;
$docs_count = 0;
$practice_count = 0;

$resources_by_week = [];
foreach ($resources as $r) {
    if ($r['type'] === 'video') $video_count++;
    elseif ($r['type'] === 'dokumentasi') $docs_count++;
    elseif ($r['type'] === 'praktek') $practice_count++;

    $resources_by_week[$r['week']][] = $r;
}

$conn->close();

$page_title = 'Sumber Belajar Terkurasi - Roadmap DevOps';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <!-- Header Banner -->
    <div class="resources-hero card p-4 mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge" style="background: rgba(6, 182, 212, 0.2); color: #67e8f9; border: 1px solid rgba(6, 182, 212, 0.4);">
                        <i class="fas fa-gem me-1"></i> Terkurasi & Berkualitas Tinggi
                    </span>
                    <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc;">
                        Roadmap 12 Minggu
                    </span>
                </div>
                <h1 class="h3 fw-bold mb-2">Resources <span class="text-gradient">DevOps & Backend</span></h1>
                <p class="text-secondary small mb-0">
                    Referensi resmi, tutorial video terbaik, dan latihan interaktif yang dirancang khusus untuk memandu kamu menyelesaikan setiap quest mingguan tanpa tersesat.
                </p>
            </div>

            <div class="col-lg-5">
                <div class="row g-2 text-center">
                    <div class="col-4">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-danger fw-bold fs-4"><?= $video_count ?></div>
                            <div class="text-secondary small">Video</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-cyan fw-bold fs-4"><?= $docs_count ?></div>
                            <div class="text-secondary small">Dokumentasi</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid var(--border-subtle);">
                            <div class="text-emerald fw-bold fs-4"><?= $practice_count ?></div>
                            <div class="text-secondary small">Praktek / Lab</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="card p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text border-0" style="background: rgba(15, 23, 42, 0.9); color: var(--text-muted);"><i class="fas fa-search"></i></span>
                    <input type="text" id="resourceSearch" class="form-control" placeholder="Cari materi (misal: Docker, Laravel, SQL, Nginx)..." oninput="filterResources()">
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                    <button type="button" class="filter-pill active" onclick="filterByType('all', this)">Semua Tipe</button>
                    <button type="button" class="filter-pill" onclick="filterByType('video', this)"><i class="fab fa-youtube text-danger me-1"></i> Video</button>
                    <button type="button" class="filter-pill" onclick="filterByType('dokumentasi', this)"><i class="fas fa-book text-cyan me-1"></i> Dokumen</button>
                    <button type="button" class="filter-pill" onclick="filterByType('praktek', this)"><i class="fas fa-laptop-code text-emerald me-1"></i> Praktek</button>
                </div>
            </div>
        </div>

        <!-- Week pills filter -->
        <div class="mt-3 pt-3 border-top filter-pills" style="border-color: var(--border-subtle) !important;">
            <a href="resources.php" class="filter-pill <?= $week_filter === 0 ? 'active' : '' ?>">Semua Minggu</a>
            <?php for ($w = 1; $w <= 12; $w++): ?>
                <a href="resources.php?week=<?= $w ?>" class="filter-pill <?= $week_filter === $w ? 'active' : '' ?>">Minggu <?= $w ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Resource Items Grouped By Week -->
    <div id="resourceContainer">
        <?php if (!empty($resources_by_week)): ?>
            <?php foreach ($resources_by_week as $w_num => $w_items): ?>
                <div class="resource-week-group mb-5" data-week="<?= $w_num ?>">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="badge" style="background: var(--primary-gradient); font-size: 0.85rem; padding: 6px 14px; border-radius: 8px;">
                            Minggu <?= $w_num ?>
                        </span>
                        <div class="flex-grow-1 border-bottom" style="border-color: var(--border-subtle) !important;"></div>
                        <span class="text-secondary small fw-semibold"><?= count($w_items) ?> Materi</span>
                    </div>

                    <div class="row g-3">
                        <?php foreach ($w_items as $res): 
                            $badge_class = 'badge-dokumentasi';
                            $type_icon = 'fas fa-book';
                            if ($res['type'] === 'video') {
                                $badge_class = 'badge-video';
                                $type_icon = 'fab fa-youtube';
                            } elseif ($res['type'] === 'praktek') {
                                $badge_class = 'badge-praktek';
                                $type_icon = 'fas fa-laptop-code';
                            }
                        ?>
                            <div class="col-md-6 col-lg-4 resource-item" data-type="<?= htmlspecialchars($res['type']) ?>">
                                <div class="resource-card">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="resource-type-badge <?= $badge_class ?>">
                                                <i class="<?= $type_icon ?>"></i> <?= ucfirst($res['type']) ?>
                                            </span>
                                            <span class="badge bg-dark border border-secondary text-secondary" style="font-size: 0.7rem;">
                                                M-<?= $res['week'] ?>
                                            </span>
                                        </div>

                                        <h2 class="h6 fw-bold mb-2 text-white resource-title">
                                            <?= htmlspecialchars($res['title']) ?>
                                        </h2>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mt-3 pt-2 border-top" style="border-color: var(--border-subtle) !important;">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-secondary text-decoration-none small" onclick="copyToClipboard(<?= htmlspecialchars(json_encode($res['url'])) ?>, this)">
                                            <i class="far fa-copy me-1"></i> Salin URL
                                        </button>
                                        <a href="<?= htmlspecialchars($res['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-cyber-outline btn-sm py-1 px-2" style="font-size: 0.78rem;">
                                            <span>Buka Sumber</span> <i class="fas fa-arrow-up-right-from-square ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card p-5 text-center empty-state">
                <div class="empty-state-icon"><i class="fas fa-book-reader"></i></div>
                <h2 class="h5 fw-bold mb-2">Tidak Ada Sumber untuk Filter Ini</h2>
                <p class="text-secondary small mb-3">Materi untuk minggu ini sedang dipersiapkan atau belum tersedia.</p>
                <div>
                    <a href="resources.php" class="btn btn-cyber-outline btn-sm">Lihat Semua Minggu</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Search No Result Message -->
    <div id="noResourcesSearch" class="card p-5 text-center empty-state d-none">
        <div class="empty-state-icon"><i class="fas fa-search-minus"></i></div>
        <h2 class="h5 fw-bold mb-2">Tidak ada sumber belajar yang cocok</h2>
        <p class="text-secondary small mb-0">Coba gunakan kata kunci pencarian yang lebih umum.</p>
    </div>
</main>

<script>
let activeType = 'all';

function filterByType(type, btn) {
    activeType = type;
    btn.parentElement.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    applyResourceFilters();
}

function filterResources() {
    applyResourceFilters();
}

function applyResourceFilters() {
    const query = (document.getElementById('resourceSearch').value || '').toLowerCase().trim();
    const groups = document.querySelectorAll('.resource-week-group');
    let visibleGroupCount = 0;

    groups.forEach(group => {
        const items = group.querySelectorAll('.resource-item');
        let visibleItemsInGroup = 0;

        items.forEach(item => {
            const itemType = item.getAttribute('data-type');
            const title = (item.querySelector('.resource-title')?.textContent || '').toLowerCase();

            const matchType = (activeType === 'all' || activeType === itemType);
            const matchQuery = (!query || title.includes(query));

            if (matchType && matchQuery) {
                item.style.display = '';
                visibleItemsInGroup++;
            } else {
                item.style.display = 'none';
            }
        });

        if (visibleItemsInGroup > 0) {
            group.style.display = '';
            visibleGroupCount++;
        } else {
            group.style.display = 'none';
        }
    });

    const noResult = document.getElementById('noResourcesSearch');
    if (groups.length > 0) {
        if (visibleGroupCount === 0) {
            noResult.classList.remove('d-none');
        } else {
            noResult.classList.add('d-none');
        }
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
