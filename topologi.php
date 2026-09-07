<?php
require_once 'config.php';
require_login();
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
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
<div class="page-head"><div class="page-kicker eyebrow">TKJ · kalkulator + kanvas</div>
<h1 class="page-title">Topologi & subnet</h1><p class="page-desc">Hitung network/broadcast/host, rancang topologi drag, simpan ke akun.</p></div>
<section class="card p-4 mb-3"><h2 class="h5">Kalkulator CIDR</h2>
<form method="GET" class="d-flex gap-2"><input name="cidr" class="form-control" value="<?= htmlspecialchars($cidr) ?>" placeholder="192.168.1.0/24"><button class="btn btn-cyber btn-sm" type="submit">Hitung</button></form>
<?php if ($calc && ($calc['ok'] ?? false)): ?><ul class="small mt-2 mb-0"><li>Network: <?= htmlspecialchars($calc['network']) ?></li><li>Broadcast: <?= htmlspecialchars($calc['broadcast']) ?></li><li>Mask: <?= htmlspecialchars($calc['mask']) ?> · Kelas <?= htmlspecialchars($calc['class']) ?> · <?= !empty($calc['private']) ? 'privat' : 'publik' ?></li><li>Host usable: <?= (int)$calc['hosts'] ?></li></ul>
<?php elseif ($calc): ?><p class="small text-danger mt-2 mb-0"><?= htmlspecialchars($calc['msg']) ?></p><?php endif; ?></section>
<section class="card p-4 mb-3"><h2 class="h5">Kanvas topologi</h2><p class="small text-muted">Klik tambah node, drag untuk pindah, hubungkan otomatis ke router.</p>
<div class="d-flex gap-2 mb-2"><button class="btn btn-cyber-outline btn-sm" id="tpAdd" type="button">+ PC</button><button class="btn btn-cyber-outline btn-sm" id="tpClear" type="button">Reset</button></div>
<svg id="tpSvg" width="100%" height="260" style="border:1px dashed #888;border-radius:8px;touch-action:none" role="img" aria-label="Kanvas topologi"></svg>
<form method="POST" class="d-flex gap-2 mt-2"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="payload" id="tpPayload"><input name="name" class="form-control form-control-sm" maxlength="80" placeholder="Nama: lab-warnet-1" required><button class="btn btn-cyber btn-sm" type="submit">Simpan</button></form></section>
<?php if ($saved): ?><section class="card p-4"><h2 class="h5">Tersimpan (<?= count($saved) ?>)</h2><?php foreach ($saved as $s): ?><p class="small mb-1"><?= htmlspecialchars($s['name']) ?> · <?= htmlspecialchars($s['created_at']) ?></p><?php endforeach; ?></section><?php endif; ?>
</main>
<script>
const svg = document.getElementById('tpSvg');
let nodes = [{x: 200, y: 60, label: 'router'}];
function draw() {
  svg.innerHTML = '';
  nodes.forEach((n, i) => {
    if (i > 0) {
      const l = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      l.setAttribute('x1', nodes[0].x); l.setAttribute('y1', nodes[0].y);
      l.setAttribute('x2', n.x); l.setAttribute('y2', n.y);
      l.setAttribute('stroke', '#888'); svg.appendChild(l);
    }
  });
  nodes.forEach((n, i) => {
    const c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    c.setAttribute('cx', n.x); c.setAttribute('cy', n.y); c.setAttribute('r', 16);
    c.setAttribute('fill', i === 0 ? '#f59e0b' : '#0ea5e9'); c.dataset.i = i;
    svg.appendChild(c);
    const t = document.createElementNS('http://www.w3.org/2000/svg', 'text');
    t.setAttribute('x', n.x); t.setAttribute('y', n.y + 32); t.setAttribute('text-anchor', 'middle');
    t.setAttribute('font-size', '11'); t.textContent = n.label; svg.appendChild(t);
  });
  document.getElementById('tpPayload').value = JSON.stringify(nodes);
}
let drag = null;
svg.addEventListener('pointerdown', e => { const t = e.target; if (t.dataset.i !== undefined) drag = +t.dataset.i; });
svg.addEventListener('pointermove', e => { if (drag === null) return; const r = svg.getBoundingClientRect(); nodes[drag].x = e.clientX - r.left; nodes[drag].y = e.clientY - r.top; draw(); });
svg.addEventListener('pointerup', () => drag = null);
document.getElementById('tpAdd').onclick = () => { nodes.push({x: 60 + nodes.length * 50, y: 170, label: 'pc' + nodes.length}); draw(); };
document.getElementById('tpClear').onclick = () => { nodes = [{x: 200, y: 60, label: 'router'}]; draw(); };
draw();
</script>
<?php require_once 'includes/footer.php'; ?>
