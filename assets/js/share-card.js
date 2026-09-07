function drawProgressCard(d) {
    const W = 1080, H = 1350;
    const c = document.createElement('canvas');
    c.width = W; c.height = H;
    const x = c.getContext('2d');
    x.fillStyle = '#101514';
    x.fillRect(0, 0, W, H);
    x.globalAlpha = 0.16;
    x.fillStyle = '#63b39b';
    x.beginPath(); x.arc(W - 60, 200, 380, 0, Math.PI * 2); x.fill();
    x.globalAlpha = 0.1;
    x.beginPath(); x.arc(80, H - 160, 320, 0, Math.PI * 2); x.fill();
    x.globalAlpha = 1;
    const F = "'DM Sans', system-ui, sans-serif";
    x.textAlign = 'center';
    x.fillStyle = '#63b39b';
    x.font = '700 36px ' + F;
    try { x.letterSpacing = '8px'; } catch (e) {}
    x.fillText('LEARN TRACKER · DEVOPS', W / 2, 150);
    try { x.letterSpacing = '0px'; } catch (e) {}
    let nameSize = 100;
    x.font = '700 ' + nameSize + 'px ' + F;
    while (x.measureText(d.username).width > W - 160 && nameSize > 44) {
        nameSize -= 6;
        x.font = '700 ' + nameSize + 'px ' + F;
    }
    x.fillStyle = '#f2f5f3';
    x.fillText(d.username, W / 2, 280);
    x.fillStyle = '#a9b5ad';
    x.font = '500 42px ' + F;
    x.fillText('Level ' + d.level + ' · ' + d.rank, W / 2, 350);
    const rows = [
        [String(d.streak), 'HARI STREAK'],
        [String(d.xp), 'TOTAL XP'],
        [d.quests, 'QUEST TUNTAS']
    ];
    let y = 580;
    rows.forEach(function(r, idx) {
        x.fillStyle = idx === 0 ? '#63b39b' : '#f2f5f3';
        x.font = '800 120px ' + F;
        x.fillText(r[0], W / 2, y);
        x.fillStyle = '#8b958d';
        x.font = '600 34px ' + F;
        try { x.letterSpacing = '6px'; } catch (e) {}
        x.fillText(r[1], W / 2, y + 58);
        try { x.letterSpacing = '0px'; } catch (e) {}
        y += 250;
    });
    x.fillStyle = '#8b958d';
    x.font = '500 32px ' + F;
    x.fillText('Level up setiap hari.', W / 2, H - 100);
    return c;
}
function shareCanvasImage(canvas, filename, title, textFallback) {
    if (!canvas) return;
    const download = function(blob) {
        const a = document.createElement('a');
        a.download = filename;
        a.href = URL.createObjectURL(blob);
        a.click();
        setTimeout(function() { URL.revokeObjectURL(a.href); }, 4000);
    };
    canvas.toBlob(async function(blob) {
        if (!blob) return;
        const file = new File([blob], filename, { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            try { await navigator.share({ files: [file], title: title }); return; }
            catch (e) { if (e && e.name === 'AbortError') return; }
        }
        if (navigator.share && textFallback) {
            try { await navigator.share({ title: title, text: textFallback }); return; }
            catch (e) { if (e && e.name === 'AbortError') return; }
        }
        download(blob);
        try { showToast('Gambar tersimpan. Bagikan ke story favoritmu!', 'info'); } catch (e) {}
    }, 'image/png');
}
