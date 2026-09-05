<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - Learn Tracker</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #fafbfa; color: #1c2420; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 16px; }
        .box { background: #fff; border: 1px solid #e2e7e1; border-radius: 10px; padding: 28px; max-width: 380px; text-align: center; }
        h1 { font-size: 1.3rem; margin: 0 0 8px; }
        p { color: #64706a; font-size: 0.9rem; margin: 0 0 16px; }
        button { background: #2f6b5e; color: #fff; border: none; border-radius: 8px; padding: 12px 24px; font-size: 0.9rem; font-weight: 600; min-height: 44px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Kamu sedang offline</h1>
        <p>Progres belajarmu aman di server. Sambungkan internet lalu muat ulang.</p>
        <button type="button" onclick="location.reload()">Muat ulang</button>
    </div>
</body>
</html>
