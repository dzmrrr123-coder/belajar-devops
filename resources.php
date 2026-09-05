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



$page_title = 'Sumber Belajar Terkurasi - Roadmap DevOps';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<main class="container py-4" role="main">
    <div class="page-head">
        <div class="page-kicker">Referensi terkurasi · <?= $total_count ?> materi</div>
        <h1 class="page-title">Resources DevOps & backend</h1>
        <p class="page-desc">Video, dokumentasi resmi, dan latihan pendamping setiap quest. <?= $video_count ?> video · <?= $docs_count ?> dokumen · <?= $practice_count ?> praktek.</p>
    </div>

    <div class="mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-6">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <input type="search" id="resourceSearch" class="form-control" placeholder="Cari materi…" aria-label="Cari materi" oninput="filterResources()">
                </div>
            </div>
            <div class="col-md-6">
                <div class="d-flex justify-content-md-end">
                    <div class="segmented" role="group" aria-label="Filter tipe materi">
                        <button type="button" class="filter-pill active" onclick="filterByType('all', this)">Semua</button>
                        <button type="button" class="filter-pill" onclick="filterByType('video', this)">Video</button>
                        <button type="button" class="filter-pill" onclick="filterByType('dokumentasi', this)">Dokumen</button>
                        <button type="button" class="filter-pill" onclick="filterByType('praktek', this)">Praktek</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-3 filter-pills" role="group" aria-label="Filter minggu">
            <a href="resources.php" class="filter-pill <?= $week_filter === 0 ? 'active' : '' ?>">Semua minggu</a>
            <?php for ($w = 1; $w <= 12; $w++): ?>
                <a href="resources.php?week=<?= $w ?>" class="filter-pill <?= $week_filter === $w ? 'active' : '' ?>">Minggu <?= $w ?></a>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Resource Items Grouped By Week -->
    <div id="resourceContainer">
        <?php if (!empty($resources_by_week)): ?>
            <?php foreach ($resources_by_week as $w_num => $w_items): ?>
                <section class="week-block resource-week-group" data-week="<?= $w_num ?>" aria-label="Minggu <?= $w_num ?>">
                    <div class="week-block-head">
                        <span class="week-tag">Minggu <?= $w_num ?></span>
                        <span class="week-count"><?= count($w_items) ?> materi</span>
                        <span class="rule" aria-hidden="true"></span>
                    </div>

                    <div>
                        <?php foreach ($w_items as $res): ?>
                            <div class="resource-item" data-type="<?= htmlspecialchars($res['type']) ?>">
                                <a class="list-row" href="<?= htmlspecialchars($res['url']) ?>" target="_blank" rel="noopener noreferrer">
                                    <div class="list-main">
                                        <p class="list-title"><?= htmlspecialchars($res['title']) ?></p>
                                        <p class="list-meta"><?= htmlspecialchars(ucfirst($res['type'])) ?> · Minggu <?= (int)$res['week'] ?></p>
                                    </div>
                                    <i class="fas fa-arrow-up-right-from-square list-chev" aria-hidden="true"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
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
            const title = (item.querySelector('.list-title')?.textContent || '').toLowerCase();

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
