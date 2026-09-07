function boltSVG(mood) {
    return '<svg viewBox="0 0 120 120" class="bolt" data-mood="' + (mood || 'idle') + '" aria-hidden="true">'
        + '<line x1="60" y1="8" x2="60" y2="24" stroke="var(--primary)" stroke-width="4" stroke-linecap="round"/>'
        + '<circle cx="60" cy="8" r="5" class="bolt-tip"/>'
        + '<rect x="18" y="24" width="84" height="72" rx="20" class="bolt-head"/>'
        + '<g class="eyes-open"><circle cx="44" cy="54" r="7" class="bolt-eye"/><circle cx="76" cy="54" r="7" class="bolt-eye"/></g>'
        + '<g class="eyes-happy" stroke="var(--ink)" stroke-width="5" fill="none" stroke-linecap="round"><path d="M36 56 l8 -8 l8 8"/><path d="M68 56 l8 -8 l8 8"/></g>'
        + '<g class="eyes-sleep" stroke="var(--ink)" stroke-width="5" stroke-linecap="round"><line x1="37" y1="54" x2="51" y2="54"/><line x1="69" y1="54" x2="83" y2="54"/></g>'
        + '<path class="bolt-mouth" d="M48 78 q12 8 24 0"/>'
        + '<text x="60" y="106" text-anchor="middle" class="bolt-prompt">&gt;_</text>'
        + '</svg>';
}
function boltSay(el, mood, html) {
    if (!el) return;
    el.innerHTML = boltSVG(mood) + '<p>' + html + '</p>';
}
(function() {
    const reduced = window.LTMotion ? window.LTMotion.reduced() : (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    document.querySelectorAll('[data-bolt]').forEach(function(el) {
        const ctx = el.dataset.context || '';
        let mood = el.dataset.mood || 'idle';
        if (!el.dataset.mood) {
            if (ctx === 'combo') mood = 'hype';
            else if (ctx === 'recovery') mood = 'sleep';
            else if (ctx === 'claim') mood = 'happy';
        }
        boltSay(el, mood, el.dataset.msg || 'Gas!');
    });
    try {
        if (!reduced && !sessionStorage.getItem('lt_splash')) {
            sessionStorage.setItem('lt_splash', '1');
            const prevFocus = document.activeElement;
            const ov = document.createElement('div');
            ov.className = 'bolt-overlay';
            ov.setAttribute('role', 'dialog');
            ov.setAttribute('aria-modal', 'true');
            ov.setAttribute('aria-label', 'Selamat datang');
            ov.innerHTML = '<div class="bolt-card">' + boltSVG('happy') + '<p class="bolt-term" id="boltTerm"></p><button type="button" class="btn btn-cyber btn-sm" data-close>Mulai gas</button></div>';
            document.body.appendChild(ov);
            const term = ov.querySelector('#boltTerm');
            const btn = ov.querySelector('[data-close]');
            const lines = ['$ lt --start', '> memuat quest…', '> siap, gas!'];
            let li = 0, ci = 0, closed = false;
            try { if (window.SoundEffects && SoundEffects.splashBoot) SoundEffects.splashBoot(); } catch (e) {}
            const close = function() { if (closed) return; closed = true; ov.remove(); if (prevFocus && prevFocus.focus) try { prevFocus.focus(); } catch (e) {} };
            btn.addEventListener('click', close);
            ov.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
            const tick = function() {
                if (closed || !document.body.contains(ov)) return;
                if (li >= lines.length) { btn.focus(); setTimeout(close, 4000); return; }
                const line = lines[li];
                if (ci <= line.length) {
                    term.textContent = lines.slice(0, li).join('\n') + (li ? '\n' : '') + line.slice(0, ci);
                    ci++;
                    setTimeout(tick, line.startsWith('$') ? 28 : 14);
                } else { li++; ci = 0; setTimeout(tick, 120); }
            };
            tick();
            setTimeout(close, 6000);
            btn.focus();
        }
    } catch (e) {}
})();
function showBoltMoment(kind, title, sub) {
    const reduced = window.LTMotion ? window.LTMotion.reduced() : (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const prevFocus = document.activeElement;
    const ov = document.createElement('div');
    ov.className = 'bolt-overlay';
    ov.setAttribute('role', 'alertdialog');
    ov.setAttribute('aria-modal', 'true');
    ov.setAttribute('aria-label', String(title || kind));
    const mood = kind === 'recovery' ? 'sleep' : (reduced ? 'happy' : 'hype');
    const head = kind === 'combo' ? '$ combo --check → x2' : kind === 'recovery' ? '$ streak --recovery' : '$ xp --check → LEVEL UP';
    ov.innerHTML = '<div class="bolt-card">' + boltSVG(mood) + '<p class="bolt-term"></p><div class="bolt-win"></div><p class="bolt-sub"></p><button type="button" class="btn btn-cyber">Lanjut gas</button></div>';
    ov.querySelector('.bolt-term').textContent = head;
    ov.querySelector('.bolt-win').textContent = String(title || '');
    ov.querySelector('.bolt-sub').textContent = String(sub || (kind === 'recovery' ? 'Streak putus? Mulai lagi dari 1 quest kecil.' : 'Rank baru diraih. Gas ke berikutnya!'));
    document.body.appendChild(ov);
    const btn = ov.querySelector('button');
    const close = function() { ov.remove(); if (prevFocus && prevFocus.focus) try { prevFocus.focus(); } catch (e) {} };
    btn.addEventListener('click', close);
    ov.addEventListener('click', function(e) { if (e.target === ov) close(); });
    ov.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
    btn.focus();
    try {
        if (window.SoundEffects) {
            if (kind === 'combo' && window.SoundEffects.comboUp) window.SoundEffects.comboUp();
            else if (window.SoundEffects.rankUp) window.SoundEffects.rankUp();
        }
    } catch (e) {}
    try { if (kind !== 'recovery') triggerConfetti(true); } catch (e) {}
}
function showComboUp(mult) { showBoltMoment('combo', 'Combo ' + mult, 'Pertahankan. 1 aksi lagi bikin streak XP.'); }
function showRecovery() { showBoltMoment('recovery', 'Misi comeback', 'Streak putus? Mulai lagi dari 1 quest kecil.'); }
window.showBoltMoment = showBoltMoment; window.showComboUp = showComboUp; window.showRecovery = showRecovery; window.showRankUp = showRankUp;
function showRankUp(level, title) {
    const reduced = window.LTMotion ? window.LTMotion.reduced() : (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const ov = document.createElement('div');
    ov.className = 'bolt-overlay';
    ov.setAttribute('role', 'alertdialog');
    ov.setAttribute('aria-label', 'Naik level');
    const tmp = document.createElement('div');
    tmp.textContent = title;
    ov.innerHTML = '<div class="bolt-card">' + boltSVG(reduced ? 'happy' : 'hype')
        + '<p class="bolt-term">$ xp --check → LEVEL UP</p>'
        + '<div class="bolt-win">Level ' + parseInt(level, 10) + '<small></small></div>'
        + '<p>Rank baru diraih. Gas ke berikutnya!</p>'
        + '<button type="button" class="btn btn-cyber">Lanjut gas</button></div>';
    ov.querySelector('.bolt-win small').textContent = tmp.textContent;
    ov.setAttribute('aria-modal', 'true');
    const prevFocus = document.activeElement;
    document.body.appendChild(ov);
    const btn = ov.querySelector('button');
    const close = function() { ov.remove(); if (prevFocus && prevFocus.focus) try { prevFocus.focus(); } catch (e) {} };
    btn.addEventListener('click', close);
    ov.addEventListener('click', function(e) { if (e.target === ov) close(); });
    ov.addEventListener('keydown', function(e) { if (e.key === 'Escape') close(); });
    btn.focus();
    try { if (window.SoundEffects && SoundEffects.rankUp) SoundEffects.rankUp(); } catch (e) {}
    try { triggerConfetti(true); } catch (e) {}
}
