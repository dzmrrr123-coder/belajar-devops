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
        }
    };
})();

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
    const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) return;
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
        document.body.appendChild(container);
    }
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

// Global generative lofi: mellow 7th-chord pads + vinyl crackle, synthesized live.
// Audio suatu halaman mati saat navigasi (aturan browser), jadi preferensi on/off
// disimpan dan musik lanjut otomatis pada interaksi pertama di halaman berikutnya.
const Lofi = (function() {
    const BAR = 3.6;
    const CHORDS = [
        [130.81, 164.81, 196.00, 246.94],
        [110.00, 130.81, 164.81, 196.00],
        [87.31, 110.00, 130.81, 164.81],
        [98.00, 123.47, 146.83, 174.61]
    ];
    const PENTA = [261.63, 293.66, 329.63, 392.00, 440.00, 523.25];
    let ctx = null, master = null, schedId = null, nextBar = 0, step = 0, playing = false;

    function ensure() {
        if (ctx) return true;
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return false;
        ctx = new AC();
        master = ctx.createGain();
        master.gain.value = volume();
        master.connect(ctx.destination);
        const len = 2 * ctx.sampleRate;
        const buf = ctx.createBuffer(1, len, ctx.sampleRate);
        const data = buf.getChannelData(0);
        for (let i = 0; i < len; i++) data[i] = (Math.random() * 2 - 1) * 0.5;
        const crackle = ctx.createBufferSource();
        crackle.buffer = buf;
        crackle.loop = true;
        const hp = ctx.createBiquadFilter();
        hp.type = 'highpass';
        hp.frequency.value = 1800;
        const cg = ctx.createGain();
        cg.gain.value = 0.012;
        crackle.connect(hp);
        hp.connect(cg);
        cg.connect(master);
        crackle.start();
        return true;
    }
    function storedVol() {
        try {
            const v = parseInt(localStorage.getItem('lt_lofi_vol') || '60', 10);
            if (!isNaN(v)) return Math.max(0, Math.min(100, v));
        } catch (e) {}
        return 60;
    }
    function volume() {
        const el = document.getElementById('lofiVol');
        return el ? (parseInt(el.value, 10) || 0) / 100 * 0.9 : storedVol() / 100 * 0.9;
    }
    function pad(freqs, t) {
        const lp = ctx.createBiquadFilter();
        lp.type = 'lowpass';
        lp.frequency.value = 850;
        const g = ctx.createGain();
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(0.055, t + 1.2);
        g.gain.setValueAtTime(0.055, t + BAR - 1.0);
        g.gain.exponentialRampToValueAtTime(0.0001, t + BAR + 0.4);
        lp.connect(g);
        g.connect(master);
        freqs.forEach(function(f) {
            const o = ctx.createOscillator();
            o.type = 'triangle';
            o.frequency.value = f * (1 + (Math.random() - 0.5) * 0.0012);
            o.connect(lp);
            o.start(t);
            o.stop(t + BAR + 0.5);
        });
        const bass = ctx.createOscillator();
        bass.type = 'sine';
        bass.frequency.value = freqs[0] / 2;
        const bg = ctx.createGain();
        bg.gain.setValueAtTime(0.0001, t);
        bg.gain.exponentialRampToValueAtTime(0.07, t + 0.4);
        bg.gain.exponentialRampToValueAtTime(0.0001, t + BAR);
        bass.connect(bg);
        bg.connect(master);
        bass.start(t);
        bass.stop(t + BAR + 0.1);
    }
    function pluck(freq, t) {
        const o = ctx.createOscillator();
        o.type = 'sine';
        o.frequency.value = freq;
        const g = ctx.createGain();
        g.gain.setValueAtTime(0.0001, t);
        g.gain.exponentialRampToValueAtTime(0.045, t + 0.03);
        g.gain.exponentialRampToValueAtTime(0.0001, t + 1.6);
        o.connect(g);
        g.connect(master);
        o.start(t);
        o.stop(t + 1.8);
    }
    function schedule() {
        if (!ctx || !playing) return;
        if (nextBar < ctx.currentTime - BAR * 2) nextBar = ctx.currentTime + 0.1;
        while (nextBar < ctx.currentTime + 2.0) {
            pad(CHORDS[step % CHORDS.length], nextBar);
            if (step % 2 === 1) pluck(PENTA[Math.floor(Math.random() * PENTA.length)], nextBar + BAR / 2);
            step++;
            nextBar += BAR;
        }
    }
    function wantOn() {
        try { return localStorage.getItem('lt_lofi_on') === '1'; } catch (e) { return false; }
    }
    function paint() {
        document.querySelectorAll('.js-lofi-toggle').forEach(function(btn) {
            btn.classList.toggle('active', playing);
            btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
            const lbl = btn.querySelector('.js-lofi-label');
            if (lbl) lbl.textContent = playing ? 'Stop' : (wantOn() ? 'Lanjut' : 'Lofi');
        });
    }
    return {
        toggle: function() {
            if (!ensure()) { showToast('Browser tidak mendukung audio.', 'warning'); return; }
            if (ctx.state === 'suspended') ctx.resume();
            playing = !playing;
            try { localStorage.setItem('lt_lofi_on', playing ? '1' : '0'); } catch (e) {}
            if (playing) {
                master.gain.cancelScheduledValues(ctx.currentTime);
                master.gain.setValueAtTime(master.gain.value, ctx.currentTime);
                master.gain.linearRampToValueAtTime(volume(), ctx.currentTime + 0.4);
                step = 0;
                nextBar = ctx.currentTime + 0.1;
                schedule();
                schedId = setInterval(schedule, 500);
            } else {
                clearInterval(schedId);
                schedId = null;
                master.gain.cancelScheduledValues(ctx.currentTime);
                master.gain.setValueAtTime(master.gain.value, ctx.currentTime);
                master.gain.linearRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
            }
            paint();
        },
        setVolume: function() {
            const el = document.getElementById('lofiVol');
            try { if (el) localStorage.setItem('lt_lofi_vol', el.value); } catch (e) {}
            if (ctx && playing && master) {
                master.gain.cancelScheduledValues(ctx.currentTime);
                master.gain.setValueAtTime(master.gain.value, ctx.currentTime);
                master.gain.linearRampToValueAtTime(volume(), ctx.currentTime + 0.1);
            }
        },
        resync: function() {
            if (playing) {
                if (ctx && ctx.state === 'suspended') ctx.resume();
                schedule();
            }
        },
        wantsResume: function() { return wantOn(); },
        refresh: function() { paint(); }
    };
})();

function applyQuestResponse(data, form) {
    const submitBtn = form ? form.querySelector('button') : null;
    const questItem = form ? form.closest('.quest-item') : null;
    if (data.status !== 'success') {
        showToast(data.message || 'Gagal memproses quest', 'danger');
        return;
    }
    if (data.action === 'completed') {
        if (questItem) {
            questItem.classList.add('completed');
            const badge = questItem.querySelector('.quest-status-badge');
            if (badge) {
                badge.innerHTML = '<span class="quest-done"><i class="fas fa-check" aria-hidden="true"></i>Selesai hari ini</span>';
            }
        }
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
            submitBtn.title = 'Batalkan selesai';
            const lbl = submitBtn.getAttribute('aria-label') || '';
            submitBtn.setAttribute('aria-label', 'Batalkan quest selesai: ' + lbl.replace(/^(Batalkan quest selesai: |Tandai quest selesai: )/, ''));
        }
        if (data.leveled_up) {
            SoundEffects.levelUp();
            triggerConfetti(true);
        } else {
            SoundEffects.questComplete();
        }
    } else {
        if (questItem) {
            questItem.classList.remove('completed');
            const badge = questItem.querySelector('.quest-status-badge');
            if (badge) {
                badge.innerHTML = '';
            }
        }
        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-circle" aria-hidden="true"></i>';
            submitBtn.title = 'Tandai selesai';
            const lbl = submitBtn.getAttribute('aria-label') || '';
            submitBtn.setAttribute('aria-label', 'Tandai quest selesai: ' + lbl.replace(/^(Batalkan quest selesai: |Tandai quest selesai: )/, ''));
        }
    }

    const hudXp = document.getElementById('hudXp');
    if (hudXp) hudXp.textContent = data.xp + ' XP';

    const hudLevel = document.getElementById('hudLevel');
    if (hudLevel) hudLevel.textContent = 'Lv. ' + data.level;

    const hudStreak = document.getElementById('hudStreak');
    if (hudStreak) hudStreak.textContent = data.streak;

    const statTotalXp = document.getElementById('statTotalXp');
    if (statTotalXp) statTotalXp.textContent = data.xp;

    if (typeof data.xp_delta === 'number') {
        const dw = document.getElementById('dashWeekXp');
        if (dw) dw.textContent = Math.max(0, (parseInt(dw.textContent, 10) || 0) + data.xp_delta);
    }

    const statLevel = document.getElementById('statLevel');
    if (statLevel) statLevel.textContent = data.level;

    const statRank = document.getElementById('statRank');
    if (statRank) statRank.textContent = data.level_title;

    const progressBar = document.getElementById('levelProgressBar');
    if (progressBar) progressBar.style.width = data.level_progress + '%';

    const nextLevelXpText = document.getElementById('nextLevelXpText');
    if (nextLevelXpText) {
        const need = Math.max(0, (data.next_level_xp ?? data.xp) - data.xp);
        nextLevelXpText.textContent = need + ' XP lagi';
    }

    if (typeof data.quests_done === 'number') {
        const qd = document.getElementById('roadmapDone');
        if (qd) qd.textContent = data.quests_done;
        const dd = document.getElementById('dashQuestDone');
        if (dd) dd.textContent = data.quests_done;
        const total = data.quests_total > 0 ? data.quests_total : 0;
        const pct = total > 0 ? Math.round((data.quests_done / total) * 100) : 0;
        const rp = document.getElementById('roadmapPct');
        if (rp) rp.textContent = pct;
        const dp = document.getElementById('dashQuestPct');
        if (dp) dp.textContent = pct;
        const rb = document.getElementById('roadmapBar');
        if (rb) rb.style.width = pct + '%';
        const rw = document.getElementById('roadmapBarWrap');
        if (rw) rw.setAttribute('aria-valuenow', pct);
    }

    showToast(data.message, data.action === 'completed' ? 'success' : 'info');
    if (data.action === 'completed' && Array.isArray(data.new_badges) && data.new_badges.length) {
        triggerConfetti(true);
        SoundEffects.levelUp();
        setTimeout(() => showToast('Badge baru: ' + data.new_badges.join(', ') + '!', 'success'), 600);
    }
    if (data.new_badges && data.new_badges.length && data.action !== 'completed') {
        setTimeout(() => showToast('Badge baru: ' + data.new_badges.join(', ') + '!', 'success'), 600);
    }
}

function reviewDueLabel(dd) {
    const today = new Date();
    const t = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
    const p = String(dd || '').split('-');
    const d = Date.UTC(+p[0], (+p[1] || 1) - 1, +p[2] || 1);
    const diff = Math.round((d - t) / 86400000);
    if (diff <= 0 && d <= t) {
        if (d < t) return 'terlambat ' + Math.max(1, Math.round((t - d) / 86400000)) + ' hari';
        return 'hari ini';
    }
    try {
        return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' });
    } catch (err) { return dd; }
}

function applyReviewResponse(data) {
    showToast(data.message, data.result === 'know' ? 'success' : 'info');
    if (data.xp_gain > 0) {
        try { triggerConfetti(true); } catch (err) {}
        try { if (window.SoundEffects) SoundEffects.questComplete(); } catch (err) {}
    }
    if (Array.isArray(data.new_badges) && data.new_badges.length) {
        setTimeout(() => showToast('Badge baru: ' + data.new_badges.join(', ') + '!', 'success'), 600);
    }
    const kicker = document.getElementById('reviewKicker');
    if (kicker) kicker.textContent = data.remaining + ' perlu direview hari ini';
    const card = document.querySelector('.review-card');
    if (!data.next || !card) {
        if (card) {
            card.outerHTML = '<div class="empty-state card p-4 p-md-5"><div class="empty-state-icon"><i class="fas fa-check-double"></i></div><h2 class="h5 fw-bold">Bersih! Tidak ada review jatuh tempo.</h2><p class="text-secondary small mb-0">Selesaikan quest atau tulis catatan — otomatis masuk antrean review besok.</p></div>';
        }
        return;
    }
    const n = data.next;
    const idInput = document.getElementById('reviewIdInput');
    if (idInput) idInput.value = n.id;
    const src = document.getElementById('reviewSource');
    if (src) src.textContent = n.source;
    const due = document.getElementById('reviewDue');
    if (due) due.textContent = reviewDueLabel(n.next_due) + ' · interval ' + n.interval_day + ' hari';
    const title = document.getElementById('reviewTitle');
    if (title) title.textContent = n.title;
    const det = document.getElementById('reviewDetail');
    if (det) {
        det.innerHTML = '';
        let text = (n.detail || '').trim();
        if (text.length > 400) text = text.slice(0, 400) + '...';
        if (text) {
            if (n.source === 'quiz') {
                const d = document.createElement('details');
                d.className = 'quiz-answer';
                const s = document.createElement('summary');
                s.className = 'quiz-answer-toggle';
                s.innerHTML = '<i class="fas fa-eye me-1" aria-hidden="true"></i>Lihat jawaban';
                const body = document.createElement('div');
                body.className = 'code-solution';
                body.textContent = text;
                d.appendChild(s); d.appendChild(body); det.appendChild(d);
            } else {
                const p = document.createElement('p');
                p.className = 'text-secondary';
                p.textContent = text;
                det.appendChild(p);
            }
        }
    }
    const pos = document.getElementById('reviewPos');
    if (pos) pos.textContent = 'Kartu 1 dari ' + data.remaining;
    const bar = document.getElementById('reviewBar');
    if (bar) {
        bar.style.width = Math.round(100 / Math.max(1, data.remaining)) + '%';
        const wrap = bar.closest('[role="progressbar"]');
        if (wrap) { wrap.setAttribute('aria-valuemax', data.remaining); wrap.setAttribute('aria-valuenow', 1); }
    }
    const left = document.getElementById('reviewLeft');
    if (left) left.textContent = 'Sisa ' + Math.max(0, data.remaining - 1) + ' kartu lagi setelah ini.';
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

let ltInstallEvent = null;
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
    // Global lofi wiring (all pages; audio resumes on first tap after navigation)
    Lofi.refresh();
    document.querySelectorAll('.js-lofi-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() { Lofi.toggle(); });
    });
    const lofiVol = document.getElementById('lofiVol');
    if (lofiVol) {
        try {
            const sv = parseInt(localStorage.getItem('lt_lofi_vol') || '60', 10);
            if (!isNaN(sv)) lofiVol.value = Math.max(0, Math.min(100, sv));
        } catch (e) {}
        lofiVol.addEventListener('input', function() { Lofi.setVolume(); });
    }
    document.addEventListener('visibilitychange', function() { if (!document.hidden) Lofi.resync(); });
    if (document.body.classList.contains('has-tabbar') && Lofi.wantsResume()) {
        const resumeOnce = function(e) {
            document.removeEventListener('pointerdown', resumeOnce);
            document.removeEventListener('keydown', resumeOnce);
            if (e && e.target && e.target.closest && e.target.closest('.js-lofi-toggle')) return;
            Lofi.toggle();
        };
        document.addEventListener('pointerdown', resumeOnce);
        document.addEventListener('keydown', resumeOnce);
    }
    document.querySelectorAll('a[href="logout.php"]').forEach(function(a) {
        a.addEventListener('click', function() {
            try { localStorage.setItem('lt_lofi_on', '0'); } catch (e) {}
        });
    });
    // Sound Toggle Button listener
    const soundToggle = document.getElementById('ltSoundToggle');
    if (soundToggle) {
        const updateSoundIcon = () => {
            const muted = SoundEffects.isMuted();
            soundToggle.innerHTML = muted 
                ? '<i class="fas fa-volume-mute text-muted"></i>' 
                : '<i class="fas fa-volume-up text-cyan"></i>';
            soundToggle.title = muted ? 'Aktifkan Suara' : 'Bisukan Suara';
        };
        updateSoundIcon();
        soundToggle.addEventListener('click', function(e) {
            e.preventDefault();
            SoundEffects.toggleMute();
            updateSoundIcon();
            showToast(SoundEffects.isMuted() ? 'Suara dinonaktifkan' : 'Suara diaktifkan', 'info');
        });
    }

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

    document.querySelectorAll('.review-actions').forEach(form => {
        if (!form.querySelector('input[name="review_id"]')) return;
        form.addEventListener('submit', function(e) {
            if (navigator.onLine) return;
            e.preventDefault();
            const fd = new FormData(form);
            const btn = e.submitter && e.submitter.value ? e.submitter : null;
            const result = btn ? btn.value : 'know';
            if (window.LTOutbox) {
                window.LTOutbox.enqueue({ type: 'review_answer', review_id: String(fd.get('review_id') || ''), result: result, csrf: String(fd.get('csrf_token') || '') });
                showToast('Offline. Jawaban review masuk antrean.', 'warning');
            }
        }, true);
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

    // Quest Check Form Interceptor (optimistic UI)
    document.querySelectorAll('.quest-toggle-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button');
            const formData = new FormData(this);
            const questItem = form.closest('.quest-item');
            const wasDone = questItem ? questItem.classList.contains('completed') : false;
            if (questItem) {
                questItem.classList.add('pending');
                questItem.classList.toggle('completed', !wasDone);
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.setAttribute('aria-busy', 'true');
                submitBtn.dataset.orig = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="spinner" aria-hidden="true"></span>';
            }

            fetch('complete_quest.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (questItem) questItem.classList.remove('pending');
                if (data.status !== 'success' && questItem) questItem.classList.toggle('completed', wasDone);
                applyQuestResponse(data, form);
            })
            .catch(() => {
                if (questItem) { questItem.classList.remove('pending'); questItem.classList.toggle('completed', wasDone); }
                const questId = form.querySelector('input[name="quest_id"]')?.value || '';
                const tokenEl = form.querySelector('input[name="csrf_token"]');
                if (!navigator.onLine && window.LTOutbox && questId) {
                    window.LTOutbox.enqueue({ type: 'quest_toggle', quest_id: questId, csrf: tokenEl ? tokenEl.value : '' });
                    showToast('Offline. Quest masuk antrean, terkirim saat online.', 'warning');
                } else {
                    showToast('Jaringan bermasalah. Mengirim ulang...', 'warning');
                    form.submit();
                }
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.removeAttribute('aria-busy');
                    if (submitBtn.dataset.orig && submitBtn.innerHTML.includes('spinner')) {
                        submitBtn.innerHTML = submitBtn.dataset.orig;
                    }
                }
            });
        });
    });

    // Review Card Interceptor (tanpa reload per kartu)
    document.querySelectorAll('form.review-actions').forEach(form => {
        if (!form.querySelector('input[name="review_id"]')) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btns = [...this.querySelectorAll('button[type="submit"]')];
            const clicked = e.submitter && e.submitter.type === 'submit' ? e.submitter : btns[0];
            btns.forEach(b => { b.disabled = true; });
            let orig = null;
            if (clicked) {
                orig = clicked.innerHTML;
                clicked.innerHTML = '<span class="spinner" aria-hidden="true"></span>';
            }
            const fd = new FormData(this);
            if (clicked && clicked.name) fd.append(clicked.name, clicked.value);
            fetch('review.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.status !== 'success') {
                    showToast(data.message || 'Gagal menyimpan review.', 'danger');
                    return;
                }
                applyReviewResponse(data);
            })
            .catch(() => {
                if (!navigator.onLine) return;
                form.submit();
            })
            .finally(() => {
                btns.forEach(b => { b.disabled = false; });
                if (clicked && orig && clicked.innerHTML.includes('spinner')) clicked.innerHTML = orig;
            });
        });
    });

    // Quiz Card Interceptor (tanpa reload per kartu)
    document.querySelectorAll('form.review-actions').forEach(form => {
        const cardInput = form.querySelector('input[name="card_id"]');
        if (!cardInput) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btns = [...this.querySelectorAll('button[type="submit"]')];
            const clicked = e.submitter && e.submitter.type === 'submit' ? e.submitter : btns[0];
            btns.forEach(b => { b.disabled = true; });
            let orig = null;
            if (clicked) {
                orig = clicked.innerHTML;
                clicked.innerHTML = '<span class="spinner" aria-hidden="true"></span>';
            }
            const fd = new FormData(this);
            if (clicked && clicked.name) fd.append(clicked.name, clicked.value);
            fetch('quiz.php', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(res => {
                if (!res.ok) throw new Error('HTTP ' + res.status);
                return res.json();
            })
            .then(data => {
                if (data.status !== 'success') {
                    showToast(data.message || 'Gagal menyimpan jawaban.', 'danger');
                    return;
                }
                applyQuizResponse(data);
            })
            .catch(() => {
                if (!navigator.onLine && window.LTOutbox) {
                    const fd2 = new FormData(form);
                    window.LTOutbox.enqueue({ type: 'quiz_answer', card_id: String(fd2.get('card_id') || ''), mode: String(fd2.get('mode') || 'latihan'), ids: String(fd2.get('ids') || ''), i: String(fd2.get('i') || '0'), result: (clicked && clicked.value) || 'know', csrf: String(fd2.get('csrf_token') || '') });
                    showToast('Offline. Jawaban kuis masuk antrean.', 'warning');
                    return;
                }
                form.submit();
            })
            .finally(() => {
                btns.forEach(b => { b.disabled = false; });
                if (clicked && orig && clicked.innerHTML.includes('spinner')) clicked.innerHTML = orig;
            });
        });
    });

function applyQuizResponse(data) {
    const run = data.run || {};
    if (data.result === 'know' && data.gain > 0) {
        showToast('Tahu! +' + data.gain + ' XP.', 'success');
        try { triggerConfetti(true); } catch (err) {}
        try { if (window.SoundEffects) SoundEffects.questComplete(); } catch (err) {}
    } else if (data.result === 'know') {
        showToast('Tersimpan. Kuota XP kuis harian habis.', 'info');
    } else {
        showToast('Dicatat. Kita ulang besok.', 'info');
    }
    const r = document.getElementById('quizRun');
    if (r) r.textContent = 'Sesi ini: Tahu ' + (run.tahu || 0) + ' · Lupa ' + (run.lupa || 0) + ' · +' + (run.xp || 0) + ' XP';
    const tx = document.getElementById('quizTahuXp');
    if (tx) tx.textContent = data.quota_left > 0 ? ' (+' + Math.min(2, data.quota_left) + ' XP)' : '';
    const qq = document.getElementById('quizQuota');
    if (qq) qq.textContent = '+' + data.quota_left + ' XP';
    const card = document.querySelector('.quiz-card');
    if (!data.next || !card) {
        if (card) {
            card.outerHTML = '<div class="empty-state card p-4 p-md-5"><div class="empty-state-icon"><i class="fas fa-flag-checkered" aria-hidden="true"></i></div><h2 class="h5 fw-bold">Sesi selesai!</h2><p class="text-secondary small mb-3">Tahu ' + (run.tahu || 0) + ' · Lupa ' + (run.lupa || 0) + ' · +' + (run.xp || 0) + ' XP sesi ini.</p><div class="d-flex gap-2 justify-content-center flex-wrap"><a href="quiz.php?mode=latihan" class="btn btn-cyber btn-sm">Main lagi</a><a href="review.php" class="btn btn-cyber-outline btn-sm">Ke Review</a></div></div>';
        }
        return;
    }
    const n = data.next;
    const cid = document.getElementById('quizCardId');
    if (cid) cid.value = n.id;
    const ii = document.getElementById('quizI');
    if (ii) ii.value = Math.max(0, (parseInt(ii.value, 10) || 0) + 1);
    const q = document.getElementById('quizQ');
    if (q) q.textContent = n.question;
    const a = document.getElementById('quizA');
    if (a) a.textContent = n.answer;
    const det = document.getElementById('quizDetails');
    if (det) det.open = false;
    const pos = document.getElementById('quizPos');
    if (pos) pos.textContent = 'Kartu ' + data.pos + ' dari ' + data.total;
    const bar = document.getElementById('quizBar');
    if (bar) {
        bar.style.width = Math.round((data.pos / Math.max(1, data.total)) * 100) + '%';
        const wrap = bar.closest('[role="progressbar"]');
        if (wrap) { wrap.setAttribute('aria-valuemax', data.total); wrap.setAttribute('aria-valuenow', data.pos); }
    }
}

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
