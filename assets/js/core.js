/**
 * Learn Tracker - Interactive Audio, Confetti, and AJAX Gamification Engine
 */

// Audio Synthesizer via Web Audio API
const SoundEffects = (function() {
    let audioCtx = null;
    const _storedMute = localStorage.getItem('lt_sound_muted');
    let isMuted = _storedMute === null ? true : _storedMute === 'true';

    function getContext() {
        if (!audioCtx) {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            if (AudioContext) {
                audioCtx = new AudioContext();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
        return audioCtx;
    }

    function playTone(freq, type, duration, delay = 0, gainLevel = 0.15) {
        if (isMuted) return;
        try {
            const ctx = getContext();
            if (!ctx) return;

            setTimeout(() => {
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = type;
                osc.frequency.setValueAtTime(freq, ctx.currentTime);

                gain.gain.setValueAtTime(gainLevel, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + duration);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start();
                osc.stop(ctx.currentTime + duration);
            }, delay * 1000);
        } catch (e) {}
    }

    return {
        isMuted: () => isMuted,
        toggleMute: function() {
            isMuted = !isMuted;
            localStorage.setItem('lt_sound_muted', isMuted);
            return isMuted;
        },
        questComplete: function() {
            // Uplifting chord (C5, E5, G5, C6)
            playTone(523.25, 'sine', 0.2, 0, 0.12);
            playTone(659.25, 'sine', 0.25, 0.08, 0.12);
            playTone(783.99, 'sine', 0.3, 0.16, 0.12);
            playTone(1046.50, 'triangle', 0.45, 0.24, 0.15);
        },
        levelUp: function() {
            // Victory Fanfare
            playTone(440.00, 'triangle', 0.2, 0, 0.15);
            playTone(554.37, 'triangle', 0.2, 0.1, 0.15);
            playTone(659.25, 'triangle', 0.25, 0.2, 0.15);
            playTone(880.00, 'sine', 0.6, 0.35, 0.2);
            playTone(1108.73, 'sine', 0.7, 0.45, 0.18);
        },
        pomodoroAlarm: function() {
            // Double Chime
            playTone(880, 'sine', 0.4, 0, 0.2);
            playTone(1046.5, 'sine', 0.5, 0.25, 0.2);
            playTone(880, 'sine', 0.4, 0.7, 0.2);
            playTone(1046.5, 'sine', 0.6, 0.95, 0.25);
        },
        click: function() {
            playTone(600, 'sine', 0.05, 0, 0.05);
        },
        chestCommon: function() {
            playTone(523.25, 'sine', 0.15, 0, 0.1);
            playTone(659.25, 'sine', 0.2, 0.1, 0.1);
        },
        chestRare: function() {
            playTone(523.25, 'sine', 0.15, 0, 0.12);
            playTone(659.25, 'sine', 0.15, 0.1, 0.12);
            playTone(783.99, 'triangle', 0.3, 0.2, 0.14);
        },
        chestEpic: function() {
            playTone(392.00, 'triangle', 0.15, 0, 0.14);
            playTone(523.25, 'triangle', 0.15, 0.1, 0.14);
            playTone(659.25, 'triangle', 0.15, 0.2, 0.14);
            playTone(783.99, 'sine', 0.4, 0.3, 0.16);
            playTone(1046.50, 'sine', 0.5, 0.4, 0.16);
        },
        chestLegendary: function() {
            playTone(523.25, 'sawtooth', 0.12, 0, 0.08);
            playTone(659.25, 'sawtooth', 0.12, 0.1, 0.08);
            playTone(783.99, 'triangle', 0.15, 0.2, 0.14);
            playTone(1046.50, 'triangle', 0.15, 0.3, 0.14);
            playTone(1318.51, 'sine', 0.5, 0.4, 0.18);
            playTone(1567.98, 'sine', 0.7, 0.55, 0.18);
        },
        comboUp: function() {
            playTone(740.00, 'square', 0.07, 0, 0.06);
            playTone(987.77, 'square', 0.1, 0.07, 0.06);
        },
        splashBoot: function() {
            playTone(392.00, 'square', 0.06, 0, 0.05);
            playTone(523.25, 'square', 0.06, 0.07, 0.05);
            playTone(783.99, 'sine', 0.15, 0.14, 0.08);
        },
        rankUp: function() {
            playTone(523.25, 'triangle', 0.12, 0, 0.14);
            playTone(659.25, 'triangle', 0.12, 0.1, 0.14);
            playTone(783.99, 'triangle', 0.12, 0.2, 0.14);
            playTone(1046.50, 'triangle', 0.2, 0.3, 0.16);
            playTone(1318.51, 'sine', 0.5, 0.42, 0.18);
            playTone(1567.98, 'sine', 0.6, 0.55, 0.16);
        },
        victory: function() {
            playTone(659.25, 'triangle', 0.15, 0, 0.14);
            playTone(659.25, 'triangle', 0.15, 0.18, 0.14);
            playTone(783.99, 'triangle', 0.15, 0.36, 0.14);
            playTone(1046.50, 'sine', 0.5, 0.5, 0.18);
        },
        defeat: function() {
            playTone(392.00, 'sine', 0.2, 0, 0.1);
            playTone(329.63, 'sine', 0.3, 0.2, 0.1);
        }
    };
})();
const LTMotion = (function() {
    function prefersReduced() { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
    function stored() { try { return localStorage.getItem('lt_motion') || 'full'; } catch (e) { return 'full'; } }
    function current() { return prefersReduced() || stored() === 'reduced' ? 'reduced' : 'full'; }
    function apply() { try { document.documentElement.dataset.motion = current(); } catch (e) {} syncBtn(); }
    function syncBtn() { try { document.querySelectorAll('[data-motion-toggle]').forEach(function(b) { b.setAttribute('aria-pressed', current() === 'reduced' ? 'true' : 'false'); b.querySelector('span').textContent = current() === 'reduced' ? 'Gerak: hemat' : 'Gerak: penuh'; }); } catch (e) {} }
    try { apply(); } catch (e) {}
    try { if (window.matchMedia) window.matchMedia('(prefers-reduced-motion: reduce)').addEventListener('change', apply); } catch (e) {}
    document.addEventListener('DOMContentLoaded', function() {
        syncBtn();
        document.querySelectorAll('[data-motion-toggle]').forEach(function(b) {
            b.addEventListener('click', function() { try { localStorage.setItem('lt_motion', current() === 'reduced' ? 'full' : 'reduced'); } catch (e) {} apply(); });
        });
    });
    return { current, apply, reduced: () => current() === 'reduced', toggle: function() { try { localStorage.setItem('lt_motion', current() === 'reduced' ? 'full' : 'reduced'); } catch (e) {} apply(); return current(); } };
})();
window.LTMotion = LTMotion;
function tierHaptic(tier) {
    if (window.LTMotion && window.LTMotion.reduced()) return;
    const map = { common: 15, rare: [20, 40, 20], epic: [30, 50, 30, 50, 30], legendary: [50, 60, 50, 60, 80] };
    buzz(map[tier] || 12);
}

let confettiPromise = null;
function ensureConfetti() {
    if (typeof confetti === 'function') return Promise.resolve(true);
    if (!confettiPromise) {
        confettiPromise = new Promise((resolve) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js';
            s.async = true;
            s.onload = () => resolve(true);
            s.onerror = () => resolve(false);
            document.head.appendChild(s);
        });
    }
    return confettiPromise;
}

function triggerConfetti(levelUp = false) {
    if (!levelUp) return;
    if (window.LTMotion && window.LTMotion.reduced()) return;
    ensureConfetti().then((ok) => {
        if (!ok || typeof confetti !== 'function') return;
        const duration = 2.5 * 1000;
        const end = Date.now() + duration;
        (function frame() {
            confetti({
                particleCount: 5,
                angle: 60,
                spread: 55,
                origin: { x: 0 },
                colors: ['#2f6b5e', '#8a6d2b', '#a44a3f']
            });
            confetti({
                particleCount: 5,
                angle: 120,
                spread: 55,
                origin: { x: 1 },
                colors: ['#2f6b5e', '#8a6d2b', '#a44a3f']
            });
            if (Date.now() < end) {
                requestAnimationFrame(frame);
            }
        })();
    });
}

function showToast(message, type = 'success') {
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        container.setAttribute('role', 'status');
        container.setAttribute('aria-live', 'polite');
        document.body.appendChild(container);
    }
    while (container.children.length >= 3) container.firstChild.remove();
    const toast = document.createElement('div');
    toast.className = 'lt-toast p-3 d-flex align-items-center justify-content-between gap-2';
    toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');
    let icon = 'fas fa-check-circle';
    if (type === 'danger') icon = 'fas fa-exclamation-circle';
    if (type === 'warning') icon = 'fas fa-exclamation-triangle';
    if (type === 'info') icon = 'fas fa-info-circle';
    const wrap = document.createElement('div');
    wrap.className = 'd-flex align-items-center gap-2';
    const ic = document.createElement('i');
    ic.className = icon;
    ic.setAttribute('aria-hidden', 'true');
    const txt = document.createElement('span');
    txt.className = 'small';
    txt.textContent = String(message ?? '');
    wrap.appendChild(ic);
    wrap.appendChild(txt);
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'btn-close btn-close-white ms-2 small';
    close.setAttribute('aria-label', 'Tutup notifikasi');
    close.addEventListener('click', () => toast.remove());
    toast.appendChild(wrap);
    toast.appendChild(close);
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity .4s ease';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 4200);
}

function copyToClipboard(text, btnElement) {
    const done = () => {
        if (btnElement) {
            const orig = btnElement.innerHTML;
            btnElement.disabled = true;
            btnElement.innerHTML = '<i class="fas fa-check me-1" aria-hidden="true"></i> Tersalin';
            setTimeout(() => { btnElement.innerHTML = orig; btnElement.disabled = false; }, 1600);
        }
        showToast('Disalin ke clipboard.', 'info');
    };
    const fallback = () => {
        try {
            const ta = document.createElement('textarea');
            ta.value = String(text ?? '');
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
            done();
        } catch (e) {
            showToast('Gagal menyalin.', 'danger');
        }
    };
    if (navigator.clipboard && window.isSecureContext !== false) {
        navigator.clipboard.writeText(String(text ?? '')).then(done).catch(fallback);
    } else {
        fallback();
    }
}

function togglePasswordVisibility(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;
    const b = btn || document.getElementById('togglePasswordBtn');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    if (b) {
        const iconOnly = !b.classList.contains('btn-link');
        b.innerHTML = show
            ? '<i class="far fa-eye-slash' + (iconOnly ? '' : ' me-1') + '" aria-hidden="true"></i>' + (iconOnly ? '' : 'Sembunyikan')
            : '<i class="far fa-eye' + (iconOnly ? '' : ' me-1') + '" aria-hidden="true"></i>' + (iconOnly ? '' : 'Lihat');
        b.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    }
}
document.addEventListener('lt:quest-synced', function(e) {
    const qid = String((e.detail && e.detail.questId) || '');
    if (!qid || !e.detail || !e.detail.data) return;
    const form = [...document.querySelectorAll('.quest-toggle-form')].find((f) => {
        const el = f.querySelector('input[name="quest_id"]');
        return el && el.value === qid;
    });
    if (form) applyQuestResponse(e.detail.data, form);
});
window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    ltInstallEvent = e;
    document.querySelectorAll('.pwa-install-btn').forEach(b => { b.hidden = false; });
});
function installPWA() {
    if (ltInstallEvent) ltInstallEvent.prompt();
    else showToast('Buka menu browser > Install / Tambah ke Layar Utama.', 'info');
}
window.installPWA = installPWA;

/* Juice: angka +XP melayang + getar */
function buzz(pattern) {
    try { if (navigator.vibrate) navigator.vibrate(pattern); } catch (e) {}
}
function xpTierForAmount(amount) {
    if (amount >= 30) return 'legendary';
    if (amount >= 15) return 'epic';
    if (amount >= 10) return 'rare';
    return '';
}
function xpJuice(amount, anchor, opts) {
    amount = parseInt(amount, 10) || 0;
    if (amount <= 0) return;
    opts = opts || {};
    if (!opts.tier) opts.tier = xpTierForAmount(amount);
    if (window.LTMotion && window.LTMotion.reduced()) { buzz(0); return; }
    buzz(opts.buzz || 12);
    const host = anchor && anchor.getBoundingClientRect ? anchor : null;
    const r = host ? host.getBoundingClientRect() : { left: window.innerWidth / 2, top: window.innerHeight / 3, width: 0 };
    const s = document.createElement('span');
    s.className = 'xp-float' + (opts.tier ? ' tier-' + opts.tier : '');
    s.setAttribute('aria-hidden', 'true');
    s.textContent = '+' + amount + ' XP';
    s.style.left = (r.left + r.width / 2 + window.scrollX) + 'px';
    s.style.top = (r.top + window.scrollY) + 'px';
    document.body.appendChild(s);
    setTimeout(() => s.remove(), 1300);
}

/* Shared AJAX helpers */
function submitterFormData(form, submitter) {
    const fd = new FormData(form);
    if (submitter && submitter.name) fd.append(submitter.name, submitter.value);
    return fd;
}
function fetchJSON(url, fd) {
    return fetch(url, {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then((res) => {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    });
}
