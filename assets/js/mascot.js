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
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    document.querySelectorAll('[data-bolt]').forEach(function(el) {
        boltSay(el, el.dataset.mood || 'idle', el.dataset.msg || 'Gas!');
    });
    /* Splash 1x per sesi */
    try {
        if (!reduced && !sessionStorage.getItem('lt_splash')) {
            sessionStorage.setItem('lt_splash', '1');
            const ov = document.createElement('div');
            ov.className = 'bolt-overlay';
            ov.setAttribute('role', 'status');
            ov.innerHTML = '<div class="bolt-card">' + boltSVG('happy') + '<p class="bolt-term" id="boltTerm"></p></div>';
            document.body.appendChild(ov);
            const term = ov.querySelector('#boltTerm');
            const lines = ['$ lt --start', '> memuat quest…', '> siap, gas!'];
            let li = 0, ci = 0, out = '';
            try { if (window.SoundEffects && SoundEffects.splashBoot) SoundEffects.splashBoot(); } catch (e) {}
            const close = function() { ov.remove(); };
            ov.addEventListener('click', close);
            const tick = function() {
                if (!document.body.contains(ov)) return;
                if (li >= lines.length) { setTimeout(close, 350); return; }
                const line = lines[li];
                if (ci <= line.length) {
                    out = lines.slice(0, li).join('\n') + (li ? '\n' : '') + line.slice(0, ci);
                    term.textContent = out;
                    ci++;
                    setTimeout(tick, line.startsWith('$') ? 28 : 14);
                } else { li++; ci = 0; setTimeout(tick, 120); }
            };
            tick();
            setTimeout(close, 2600);
        }
    } catch (e) {}
})();
function showRankUp(level, title) {
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
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
    document.body.appendChild(ov);
    const close = function() { ov.remove(); };
    ov.querySelector('button').addEventListener('click', close);
    ov.addEventListener('click', function(e) { if (e.target === ov) close(); });
    try { if (window.SoundEffects && SoundEffects.rankUp) SoundEffects.rankUp(); } catch (e) {}
    try { triggerConfetti(true); } catch (e) {}
}
