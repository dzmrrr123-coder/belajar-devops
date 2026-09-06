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

/* Lofi wiring + sound toggle */
document.addEventListener('DOMContentLoaded', function() {
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

});
