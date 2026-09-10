<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
enforce_track_access($conn, $uid, ['tkj'], 'Topologi & Subnet Network');
\App\Domain\Tkj\Topo::ensureTables($conn);
$calc = null;
$cidr = trim($_GET['cidr'] ?? ($_POST['cidr'] ?? '192.168.1.0/24'));
if ($cidr !== '') $calc = \App\Domain\Tkj\Topo::parse($cidr);
$saved = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verify_csrf();
    $name = mb_substr(trim(clean($_POST['name'] ?? 'topologi')), 0, 80);
    $payload = substr((string)($_POST['payload'] ?? ''), 0, 8000);
    if ($name === '' || $payload === '') set_flash('warning', 'Nama + payload wajib.');
    else {
        $s = $conn->prepare("INSERT INTO topo_saves (user_id, name, payload) VALUES (?, ?, ?)");
        if ($s) { $s->bind_param("iss", $uid, $name, $payload); $s->execute(); $s->close(); set_flash('success', 'Topologi tersimpan.'); }
    }
    redirect('topologi.php?cidr=' . urlencode($cidr));
}
try {
    $q = $conn->prepare("SELECT id, name, created_at FROM topo_saves WHERE user_id = ? ORDER BY id DESC LIMIT 10");
    if ($q) { $q->bind_param("i", $uid); $q->execute(); $saved = $q->get_result()->fetch_all(MYSQLI_ASSOC); $q->close(); }
} catch (Throwable $e) {}
$conn->close();
$page_title = 'Topologi & Subnet';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>
<main class="container py-4" role="main">
<div class="page-head"><div class="page-kicker eyebrow">TKJ · kalkulator subnet</div>
<h1 class="page-title">Topologi & subnet</h1><p class="page-desc">Hitung network/broadcast/host dulu. Kanvas drag opsional di bawah.</p></div>
<div class="row g-4 align-items-start">
    <div class="col-lg-3">
        <section class="card p-3 mb-3 border-0 shadow-sm">
            <h2 class="h6 fw-bold mb-3"><i class="fas fa-calculator me-2"></i>CIDR Calculator</h2>
            <form method="GET" class="d-flex flex-column gap-2 m-0">
                <input name="cidr" class="form-control form-control-sm font-monospace" value="<?= htmlspecialchars($cidr) ?>" placeholder="192.168.1.0/24">
                <button class="btn btn-cyber-outline btn-sm" type="submit">Hitung Subnet</button>
            </form>
            <?php if ($calc && ($calc['ok'] ?? false)): ?>
            <div class="mt-3 pt-3 border-top small">
                <div class="mb-1"><span class="text-muted d-block" style="font-size:0.7rem">Network</span><code class="text-info"><?= htmlspecialchars($calc['network']) ?></code></div>
                <div class="mb-1"><span class="text-muted d-block" style="font-size:0.7rem">Broadcast</span><code class="text-info"><?= htmlspecialchars($calc['broadcast']) ?></code></div>
                <div class="mb-1"><span class="text-muted d-block" style="font-size:0.7rem">Subnet Mask</span><code><?= htmlspecialchars($calc['mask']) ?></code></div>
                <div class="mb-1"><span class="text-muted d-block" style="font-size:0.7rem">Total Host (Usable)</span><strong><?= (int)$calc['hosts'] ?></strong></div>
                <div class="mt-2 text-muted" style="font-size:0.75rem">Kelas <?= htmlspecialchars($calc['class']) ?> · <?= !empty($calc['private']) ? 'Privat' : 'Publik' ?></div>
            </div>
            <?php elseif ($calc): ?>
            <p class="small text-danger mt-2 mb-0"><?= htmlspecialchars($calc['msg']) ?></p>
            <?php endif; ?>
        </section>

        <?php if ($saved): ?>
        <section class="card p-3 border-0 shadow-sm">
            <h2 class="h6 fw-bold mb-2"><i class="fas fa-folder-open me-2"></i>Tersimpan (<?= count($saved) ?>)</h2>
            <div class="d-flex flex-column gap-1">
                <?php foreach ($saved as $s): ?>
                <div class="p-2 rounded bg-body-tertiary border border-secondary-subtle small d-flex flex-column">
                    <strong><?= htmlspecialchars($s['name']) ?></strong>
                    <span class="text-muted" style="font-size: 0.75rem"><?= htmlspecialchars($s['created_at']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>

    <div class="col-lg-9">
        <details class="card border-0 shadow-sm overflow-hidden p-3">
            <summary class="h6 fw-bold mb-0" style="cursor:pointer"><i class="fas fa-network-wired me-2"></i>Kanvas topologi (opsional) — klik untuk buka</summary>
            <div class="mt-3">
        <section class="card border shadow-sm overflow-hidden p-0">
            <!-- Toolbar -->
            <div class="bg-body-tertiary p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <h2 class="h6 fw-bold mb-0 m-0 d-none d-md-block"><i class="fas fa-network-wired me-2"></i>Kanvas</h2>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Toolbar Topologi">
                        <button class="btn btn-cyber-outline" id="tpAddRouter" type="button" title="Tambah Router"><i class="fas fa-server"></i> Router</button>
                        <button class="btn btn-cyber-outline" id="tpAddSwitch" type="button" title="Tambah Switch"><i class="fas fa-network-wired"></i> Switch</button>
                        <button class="btn btn-cyber-outline" id="tpAddPC" type="button" title="Tambah PC"><i class="fas fa-desktop"></i> PC</button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-danger btn-sm" id="tpClear" type="button" title="Bersihkan Kanvas"><i class="fas fa-trash-can"></i> Reset</button>
                </div>
            </div>
            
            <!-- Sandbox Canvas -->
            <div class="position-relative topo-canvas-wrapper w-100" style="height: 400px; border-radius: 12px; background: var(--surface-2); border: 1px solid var(--line);">
                <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg" style="position: absolute; top:0; left:0; pointer-events: none;">
                    <defs>
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
                <svg id="tpSvg" width="100%" height="100%" style="position:relative; z-index: 10; touch-action:none" role="img" aria-label="Kanvas topologi"></svg>
            </div>
            
            <!-- Save Form -->
            <div class="bg-body-tertiary p-3 border-top">
                <form method="POST" class="d-flex align-items-center gap-3 m-0 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="payload" id="tpPayload">
                    <div class="flex-grow-1 min-w-0" style="min-width: 200px;">
                        <input name="name" class="form-control form-control-sm" maxlength="80" placeholder="Nama desain: lab-warnet-1" required>
                    </div>
                    <button class="btn btn-cyber btn-sm px-4" type="submit"><i class="fas fa-save me-2"></i>Simpan Desain</button>
                </form>
            </div>
        </section>
            </div>
        </details>
    </div>
</div>
</main>

<script>
const svg = document.getElementById('tpSvg');
let nodes = [{x: 200, y: 80, label: 'Router 1', type: 'router'}];

function getColor(type) {
    if (type === 'router') return '#f59e0b'; // warning
    if (type === 'switch') return '#10b981'; // success
    return '#0ea5e9'; // info (PC)
}

function draw() {
    svg.innerHTML = '';
    // Draw links (all non-routers connect to the first node for now, as a simple star topology)
    nodes.forEach((n, i) => {
        if (i > 0) {
            const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            l.setAttribute('x1', nodes[0].x); l.setAttribute('y1', nodes[0].y);
            l.setAttribute('x2', n.x); l.setAttribute('y2', n.y);
            l.setAttribute('stroke', '#475569'); 
            l.setAttribute('stroke-width', '2');
            svg.appendChild(l);
        }
    });
    // Draw nodes
    nodes.forEach((n, i) => {
        const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        c.setAttribute('cx', n.x); c.setAttribute('cy', n.y); c.setAttribute('r', 18);
        c.setAttribute('fill', getColor(n.type)); 
        c.setAttribute('stroke', '#0f172a');
        c.setAttribute('stroke-width', '2');
        c.style.cursor = 'grab';
        c.dataset.i = i;
        svg.appendChild(c);
        
        const t = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        t.setAttribute('x', n.x); t.setAttribute('y', n.y + 34); 
        t.setAttribute('text-anchor', 'middle');
        t.setAttribute('font-size', '12'); 
        t.setAttribute('fill', '#f8fafc');
        t.style.pointerEvents = 'none';
        t.textContent = n.label; 
        svg.appendChild(t);
    });
    document.getElementById('tpPayload').value = JSON.stringify(nodes);
}

let drag = null;
svg.addEventListener('pointerdown', e => { 
    const t = e.target; 
    if (t.dataset.i !== undefined) {
        drag = +t.dataset.i; 
        t.style.cursor = 'grabbing';
        svg.setPointerCapture(e.pointerId);
    }
});
svg.addEventListener('pointermove', e => { 
    if (drag === null) return; 
    const r = svg.getBoundingClientRect(); 
    nodes[drag].x = Math.max(20, Math.min(r.width - 20, e.clientX - r.left)); 
    nodes[drag].y = Math.max(20, Math.min(r.height - 30, e.clientY - r.top)); 
    draw(); 
});
svg.addEventListener('pointerup', e => {
    if (drag !== null) {
        drag = null;
        svg.releasePointerCapture(e.pointerId);
        draw();
    }
});

function addNode(type, prefix) {
    const count = nodes.filter(n => n.type === type).length + 1;
    nodes.push({
        x: 50 + (Math.random() * 200), 
        y: 150 + (Math.random() * 100), 
        label: prefix + ' ' + count,
        type: type
    });
    draw();
}

document.getElementById('tpAddRouter').onclick = () => addNode('router', 'Router');
document.getElementById('tpAddSwitch').onclick = () => addNode('switch', 'Switch');
document.getElementById('tpAddPC').onclick = () => addNode('pc', 'PC');
document.getElementById('tpClear').onclick = () => { nodes = [{x: 200, y: 80, label: 'Router 1', type: 'router'}]; draw(); };

draw();
</script>
<?php require_once 'includes/footer.php'; ?>
