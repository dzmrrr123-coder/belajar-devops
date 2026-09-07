(function() {
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;

    /* Sapaan waktu dinamis */
    const hour = new Date().getHours();
    const greet = hour < 4 ? 'Begadang produktif' : hour < 11 ? 'Pagi, pejuang streak' : hour < 15 ? 'Siang, lanjut gas' : hour < 19 ? 'Sore, kunci hari ini' : 'Malam, tutup dengan XP';
    document.querySelectorAll('[data-greet]').forEach(function(el) {
        el.textContent = greet;
    });

    /* Partikel ember: ringan, pause saat tab sembunyi */
    const MAX = 36;
    let canvas = null, ctx = null, parts = [], running = false, raf = 0;
    function sizeCanvas() {
        if (!canvas) return;
        canvas.width = Math.floor(window.innerWidth / 2);
        canvas.height = Math.floor(window.innerHeight / 2);
    }
    function spawn(init) {
        return {
            x: Math.random(),
            y: init ? Math.random() : 1.02,
            r: 0.0016 + Math.random() * 0.0032,
            vy: 0.00012 + Math.random() * 0.00028,
            drift: (Math.random() - 0.5) * 0.0004,
            a: 0.12 + Math.random() * 0.25,
            hue: Math.random() < 0.7 ? '99,179,155' : '224,154,60'
        };
    }
    function frame() {
        if (!running) return;
        const W = canvas.width, H = canvas.height;
        ctx.clearRect(0, 0, W, H);
        for (const p of parts) {
            p.y -= p.vy;
            p.x += p.drift + Math.sin((p.y * 40)) * 0.00008;
            if (p.y < -0.02) Object.assign(p, spawn(false));
            ctx.beginPath();
            ctx.arc(p.x * W, p.y * H, Math.max(1, p.r * W), 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(' + p.hue + ',' + p.a.toFixed(2) + ')';
            ctx.fill();
        }
        raf = requestAnimationFrame(frame);
    }
    function start() {
        if (running || document.hidden) return;
        running = true;
        raf = requestAnimationFrame(frame);
    }
    function stop() {
        running = false;
        if (raf) cancelAnimationFrame(raf);
        raf = 0;
    }
    function boot() {
        canvas = document.createElement('canvas');
        canvas.className = 'ambience-canvas';
        canvas.setAttribute('aria-hidden', 'true');
        document.body.prepend(canvas);
        ctx = canvas.getContext('2d');
        sizeCanvas();
        parts = Array.from({ length: MAX }, function() { return spawn(true); });
        window.addEventListener('resize', sizeCanvas);
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) stop(); else start();
        });
        const lowPower = window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches && (navigator.hardwareConcurrency || 8) <= 4;
        if (lowPower) { parts = parts.slice(0, 16); }
        if (navigator.getBattery) {
            navigator.getBattery().then(function(b) {
                const apply = function() { if (b.charging === false && b.level < 0.2) stop(); else start(); };
                b.addEventListener('levelchange', apply);
                b.addEventListener('chargingchange', apply);
                apply();
            }).catch(start);
        } else {
            start();
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();

    /* Tilt 3D halus: desktop berpointer presisi saja */
    if (window.matchMedia && window.matchMedia('(pointer: fine)').matches) {
        const MAX = 6;
        document.querySelectorAll('.quest-item, .badge-item').forEach(function(el) {
            el.classList.add('tilt');
            el.addEventListener('mousemove', function(e) {
                const r = el.getBoundingClientRect();
                const dx = (e.clientX - r.left) / r.width - 0.5;
                const dy = (e.clientY - r.top) / r.height - 0.5;
                el.style.transform = 'perspective(700px) rotateY(' + (dx * MAX).toFixed(2) + 'deg) rotateX(' + (-dy * MAX).toFixed(2) + 'deg)';
            });
            el.addEventListener('mouseleave', function() { el.style.transform = ''; });
        });
    }

    /* Transisi antar halaman: fade kilat, fallback = navigasi biasa */
    if (document.startViewTransition) {
        document.addEventListener('click', function(e) {
            const a = e.target.closest('a[href]');
            if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (a.target === '_blank' || a.hasAttribute('download')) return;
            let url = null;
            try { url = new URL(a.getAttribute('href'), location.href); } catch (err) { return; }
            if (url.origin !== location.origin) return;
            if (url.pathname.endsWith('.sql') || url.pathname.endsWith('.png')) return;
            e.preventDefault();
            document.startViewTransition(function() { location.href = a.href; });
        });
    }
})();
