<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - Learn Tracker</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #fafbfa; color: #1c2420; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; }
        .box { background: #fff; border: 1px solid #e2e7e1; border-radius: 10px; padding: 28px; max-width: 400px; width: 100%; text-align: center; }
        h1 { font-size: 1.3rem; margin: 0 0 8px; }
        p { color: #64706a; font-size: 0.9rem; margin: 0 0 16px; }
        .queue { display: inline-block; background: #e7efec; color: #2f6b5e; font-weight: 700; font-size: 0.8rem; border-radius: 999px; padding: 4px 14px; margin-bottom: 16px; }
        .links { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 16px; }
        .links a { display: flex; align-items: center; justify-content: center; border: 1px solid #cbd5cc; border-radius: 8px; padding: 12px 8px; min-height: 48px; font-size: 0.85rem; font-weight: 600; color: #1c2420; text-decoration: none; }
        button { background: #2f6b5e; color: #fff; border: none; border-radius: 8px; padding: 12px 24px; font-size: 0.9rem; font-weight: 600; min-height: 44px; width: 100%; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Kamu sedang offline</h1>
        <p>Progres belajarmu aman. Antrean di bawah terkirim otomatis saat online, bahkan saat aplikasi tertutup.</p>
        <div class="queue" id="queueInfo">0 antrean</div>
        <div class="links">
            <a href="index.php">Overview</a>
            <a href="quests.php">Roadmap</a>
            <a href="timer.php">Fokus</a>
            <a href="errors.php">Catatan</a>
        </div>
        <button type="button" onclick="location.reload()">Muat ulang</button>
    </div>
    <script>
    (function() {
        try {
            var q = JSON.parse(localStorage.getItem('lt_outbox_v1') || '[]');
            var n = Array.isArray(q) ? q.length : 0;
            document.getElementById('queueInfo').textContent = n === 0 ? 'Tidak ada antrean' : n + ' antrean menunggu';
        } catch (e) {}
        window.addEventListener('online', function() { location.reload(); });
    })();
    </script>
</body>
</html>
