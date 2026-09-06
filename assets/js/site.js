
/* Site: theme, missions, subtasks, pill, prefetch */
document.addEventListener('DOMContentLoaded', function() {
const main = document.querySelector('main');
if (main && !main.id) main.id = 'main';
document.querySelectorAll('.lt-nav-link.active').forEach(a => a.setAttribute('aria-current', 'page'));

document.querySelectorAll('form:not(.quest-toggle-form)').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('button[type="submit"]');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
        }
    });
});
// Theme Toggle (dark / light, persisted; may appear in navbar and/or user menu)
const themeToggles = document.querySelectorAll('.ltThemeToggle');
function paintThemeIcon() {
    const dark = document.documentElement.dataset.theme === 'dark';
    themeToggles.forEach(function(btn) {
        const icon = btn.querySelector('i');
        if (icon) icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
        const label = btn.querySelector('.theme-toggle-label');
        if (label) label.textContent = dark ? 'Mode terang' : 'Mode gelap';
        btn.setAttribute('aria-label', dark ? 'Ganti ke tema terang' : 'Ganti ke tema gelap');
    });
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', dark ? '#121614' : '#2f6b5e');
}
paintThemeIcon();
themeToggles.forEach(function(btn) {
    btn.addEventListener('click', function() {
        const dark = document.documentElement.dataset.theme !== 'dark';
        if (dark) document.documentElement.dataset.theme = 'dark';
        else document.documentElement.removeAttribute('data-theme');
        try { localStorage.setItem('lt_theme', dark ? 'dark' : 'light'); } catch (e) {}
        paintThemeIcon();
    });
});
document.querySelectorAll('.mission-claim-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const card = this.closest('.mission-card');
        const btn = this.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner" aria-hidden="true"></span>'; }
        if (card) card.classList.add('claiming');
        fetch('claim_mission.php', { method: 'POST', body: new FormData(this), headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(r => r.json()).then(d => {
            showToast(d.message || 'Misi diproses.', d.status === 'success' ? 'success' : 'danger');
            if (d.status === 'success' && card) {
                card.classList.remove('claiming');
                card.classList.add('claimed');
                const f = card.querySelector('.mission-claim-form');
                if (f) f.outerHTML = '<span class="quest-done"><i class="fas fa-check" aria-hidden="true"></i>Diklaim</span>';
                if (typeof d.xp_reward === 'number') {
                    const hx = document.getElementById('hudXp');
                    if (hx) { const cur = parseInt(hx.textContent, 10); if (!isNaN(cur)) hx.textContent = (cur + d.xp_reward) + ' XP'; }
                    const sx = document.getElementById('statTotalXp');
                    if (sx) { const cur = parseInt(sx.textContent, 10); if (!isNaN(cur)) sx.textContent = cur + d.xp_reward; }
                    xpJuice(d.xp_reward, card || btn);
                }
                SoundEffects.questComplete();
            }
            else if (btn) { btn.disabled = false; btn.textContent = 'Klaim'; if (card) card.classList.remove('claiming'); }
        }).catch(() => {
            const fd = new FormData(form);
            if (!navigator.onLine && window.LTOutbox) {
                window.LTOutbox.enqueue({ type: 'mission_claim', mission_key: String(fd.get('mission_key') || ''), csrf: String(fd.get('csrf_token') || '') });
                showToast('Offline. Klaim misi masuk antrean.', 'warning');
                if (btn) { btn.disabled = false; btn.textContent = 'Klaim'; }
            } else {
                showToast('Gagal klaim misi.', 'danger');
                if (btn) { btn.disabled = false; btn.textContent = 'Klaim'; }
            }
        });
    });
});

['lt:mission-synced', 'lt:subtask-synced', 'lt:review-synced', 'lt:quiz-synced'].forEach(ev => {
    document.addEventListener(ev, () => setTimeout(() => location.reload(), 800));
});

try {
    if ('Notification' in window && location.pathname.endsWith('index.php')) {
        const done = document.querySelectorAll('.mission-card.claimed').length;
        const key = 'lt_remind_' + new Date().toISOString().slice(0, 10);
        if (done < 3 && new Date().getHours() >= 20 && !localStorage.getItem(key)) {
            if (Notification.permission === 'granted') {
                new Notification('Misi harian belum selesai', { body: 'Selesaikan 1 quest / fokus / catatan sebelum tidur.' });
                localStorage.setItem(key, '1');
            } else if (Notification.permission === 'default') Notification.requestPermission();
        }
    }
} catch (e) {}

document.querySelectorAll('.subtask-toggle-form, .subtask-add-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(form);
        const isAdd = form.classList.contains('subtask-add-form');
        fetch('subtask.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
        .then(r => r.json()).then(d => {
            if (d.status !== 'success') showToast(d.message || 'Gagal.', 'danger');
            else setTimeout(() => location.reload(), 400);
        }).catch(() => {
            if (!navigator.onLine && window.LTOutbox) {
                const csrf = String(fd.get('csrf_token') || '');
                const questId = String(fd.get('quest_id') || '');
                if (isAdd) {
                    window.LTOutbox.enqueue({ type: 'subtask_create', quest_id: questId, title: String(fd.get('title') || ''), csrf: csrf });
                } else {
                    const btn = form.querySelector('.subtask-check');
                    const wantDone = btn ? !btn.classList.contains('done') : true;
                    window.LTOutbox.enqueue({ type: 'subtask_set', quest_id: questId, subtask_id: String(fd.get('subtask_id') || ''), done: wantDone, csrf: csrf });
                    if (btn) btn.classList.toggle('done', wantDone);
                }
                showToast('Offline. Subtask masuk antrean.', 'warning');
            } else {
                showToast('Jaringan bermasalah.', 'danger');
            }
        });
    });
});

// Persistent timer pill (reads lt_timer_v1 written by timer.php)
(function ltTimerPill() {
    const KEY = 'lt_timer_v1';
    const POS_KEY = 'lt_pill_pos';
    const DONE_TTL = 30 * 60 * 1000;
    const RECORDED_TTL = 2 * 60 * 1000;
    const STALE_TTL = 24 * 3600 * 1000;
    if (location.pathname.endsWith('timer.php')) return;
    if (!document.body.classList.contains('has-tabbar')) return;
    let el = null;
    function read() { try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { return null; } }
    function clear() { try { localStorage.removeItem(KEY); } catch (e) {} }
    function readPos() { try { return JSON.parse(localStorage.getItem(POS_KEY) || 'null'); } catch (e) { return null; } }
    function fmt(sec) {
        sec = Math.max(0, sec);
        return String(Math.floor(sec / 60)).padStart(2, '0') + ':' + String(sec % 60).padStart(2, '0');
    }
    function ensure() {
        if (el) return el;
        el = document.createElement('a');
        el.id = 'ltTimerPill';
        el.className = 'timer-pill';
        el.href = 'timer.php';
        el.draggable = false;
        el.ondragstart = function(e) { e.preventDefault(); };
        el.innerHTML = '<i class="fas fa-grip-vertical timer-pill-handle" aria-hidden="true" title="Geser untuk memindah"></i><i class="fas fa-clock" aria-hidden="true"></i><span class="timer-pill-time"></span><span class="timer-pill-label"></span><button type="button" class="timer-pill-close" aria-label="Tutup bubble timer"><i class="fas fa-xmark" aria-hidden="true"></i></button>';
        el.querySelector('.timer-pill-close').addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            clear();
            drop();
        });
        const pos = readPos();
        if (pos && typeof pos.left === 'number' && typeof pos.top === 'number') {
            el.style.left = Math.max(0, Math.min(window.innerWidth - 60, pos.left)) + 'px';
            el.style.top = Math.max(0, Math.min(window.innerHeight - 60, pos.top)) + 'px';
            el.style.right = 'auto';
            el.style.bottom = 'auto';
        }
        enableDrag(el);
        document.body.appendChild(el);
        return el;
    }
    function drop() { if (el) { el.remove(); el = null; } }
    function enableDrag(node) {
        let sx = 0, sy = 0, ox = 0, oy = 0, dragging = false, pid = null;
        function clampPos(x, y) {
            const w = node.offsetWidth || 140, h = node.offsetHeight || 40;
            return {
                x: Math.max(8, Math.min(window.innerWidth - w - 8, x)),
                y: Math.max(8, Math.min(window.innerHeight - h - 8, y))
            };
        }
        function place(x, y) {
            const p = clampPos(x, y);
            node.style.left = p.x + 'px';
            node.style.top = p.y + 'px';
            node.style.right = 'auto';
            node.style.bottom = 'auto';
            return p;
        }
        function save(x, y) {
            try { localStorage.setItem(POS_KEY, JSON.stringify({ left: x, top: y })); } catch (err) {}
        }
        node.addEventListener('pointerdown', function(e) {
            if (e.target.closest('.timer-pill-close')) return;
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            pid = e.pointerId;
            sx = e.clientX; sy = e.clientY;
            const r = node.getBoundingClientRect();
            ox = sx - r.left; oy = sy - r.top;
            dragging = false;
            node.classList.remove('snap');
        });
        window.addEventListener('pointermove', function(e) {
            if (pid === null || e.pointerId !== pid) return;
            if (!dragging && Math.hypot(e.clientX - sx, e.clientY - sy) < 6) return;
            if (!dragging) { dragging = true; node.dataset.moved = '1'; node.classList.add('dragging'); }
            if (e.cancelable) e.preventDefault();
            place(e.clientX - ox, e.clientY - oy);
        }, { passive: false });
        function end(e) {
            if (pid === null || e.pointerId !== pid) return;
            pid = null;
            node.classList.remove('dragging');
            if (!dragging) return;
            dragging = false;
            const r = node.getBoundingClientRect();
            const snapX = (r.left + r.width / 2 < window.innerWidth / 2) ? 12 : window.innerWidth - r.width - 12;
            node.classList.add('snap');
            const p = place(snapX, r.top);
            save(p.x, p.y);
        }
        window.addEventListener('pointerup', end);
        window.addEventListener('pointercancel', end);
        node.addEventListener('click', function(e) {
            if (node.dataset.moved === '1') { e.preventDefault(); e.stopPropagation(); node.dataset.moved = '0'; }
        }, true);
        node.addEventListener('keydown', function(e) {
            const step = e.shiftKey ? 2 : 16;
            let dx = 0, dy = 0;
            if (e.key === 'ArrowLeft') dx = -step;
            else if (e.key === 'ArrowRight') dx = step;
            else if (e.key === 'ArrowUp') dy = -step;
            else if (e.key === 'ArrowDown') dy = step;
            else return;
            e.preventDefault();
            const r = node.getBoundingClientRect();
            node.classList.add('snap');
            const p = place(r.left + dx, r.top + dy);
            save(p.x, p.y);
            setTimeout(function() { node.classList.remove('snap'); }, 220);
        });
    }
    function finishRemote(s) {
        const mode = s.mode || 'focus';
        try {
            localStorage.setItem(KEY, JSON.stringify({ finished: true, recorded: false, sessionId: s.sessionId || null, mode: mode, totalSec: s.totalSec || 1500, ts: Date.now() }));
        } catch (e) {}
        render();
        if (mode === 'focus') {
            showToast('Sesi fokus selesai! Istirahat sejenak, XP +10 tercatat saat buka Focus.', 'success');
            try { if (window.SoundEffects) SoundEffects.pomodoroAlarm(); } catch (e) {}
        } else {
            showToast('Istirahat selesai. Waktunya kembali fokus.', 'info');
        }
        try {
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification(mode === 'focus' ? 'Sesi fokus selesai' : 'Istirahat selesai', { body: 'Ketuk untuk kembali ke Focus.', tag: 'lt-timer-done' });
            }
        } catch (e) {}
    }
    function render() {
        const s = read();
        if (!s) { drop(); return; }
        const age = Date.now() - (s.ts || Date.now());
        if (s.finished) {
            if (s.recorded && age > RECORDED_TTL) { clear(); drop(); return; }
            if (!s.recorded && age > DONE_TTL) { clear(); drop(); return; }
            const b = ensure();
            b.classList.add('done');
            b.querySelector('.timer-pill-time').textContent = 'Selesai';
            b.querySelector('.timer-pill-label').textContent = s.recorded ? '' : 'ketuk · istirahat dulu';
            return;
        }
        if (!s.running && age > STALE_TTL) { clear(); drop(); return; }
        let rem = (typeof s.remainingSec === 'number') ? s.remainingSec : 0;
        if (s.running && s.endsAt) {
            rem = Math.max(0, Math.ceil((s.endsAt - Date.now()) / 1000));
            if (rem <= 0) { finishRemote(s); return; }
        }
        if (!s.running && rem >= (s.totalSec || 0)) { drop(); return; }
        const b = ensure();
        b.classList.remove('done');
        b.querySelector('.timer-pill-time').textContent = fmt(rem);
        b.querySelector('.timer-pill-label').textContent = (s.mode === 'focus' ? 'Fokus' : 'Istirahat') + (s.running ? '' : ' · jeda');
    }
    setInterval(render, 1000);
    window.addEventListener('storage', function(e) { if (e.key === KEY) render(); });
    render();
})();

// Navigation progress + hover prefetch (same-origin .php)
(function() {
    const bar = document.getElementById('pageProgress');
    let timer = null;
    function start() {
        if (!bar || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        bar.classList.add('on');
        bar.style.width = '12%';
        clearTimeout(timer);
        timer = setTimeout(() => { bar.style.width = '72%'; }, 300);
    }
    function stop() { if (bar) { bar.style.width = '100%'; setTimeout(() => { bar.classList.remove('on'); bar.style.width = '0'; }, 250); } }
    document.addEventListener('click', function(e) {
        const a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const href = a.getAttribute('href') || '';
        if (href.startsWith('#') || href.startsWith('http') || a.target === '_blank') return;
        if (/\.php(\?|$)/.test(href)) start();
    });
    window.addEventListener('pageshow', stop);
    window.addEventListener('pagehide', stop);
    const seen = new Set();
    function prefetch(url) {
        try {
            const u = new URL(url, location.href);
            if (u.origin !== location.origin || seen.has(u.href)) return;
            seen.add(u.href);
            const l = document.createElement('link');
            l.rel = 'prefetch';
            l.href = u.href;
            document.head.appendChild(l);
        } catch (e) {}
    }
    document.addEventListener('mouseover', function(e) {
        const a = e.target.closest ? e.target.closest('a[href]') : null;
        if (!a) return;
        const href = a.getAttribute('href') || '';
        if (/\.php(\?|$)/.test(href) && !href.startsWith('http')) {
            if ('requestIdleCallback' in window) requestIdleCallback(() => prefetch(href));
            else setTimeout(() => prefetch(href), 150);
        }
    }, { passive: true });
})();
});
