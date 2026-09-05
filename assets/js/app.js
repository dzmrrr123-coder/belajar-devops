/**
 * Learn Tracker - Interactive Audio, Confetti, and AJAX Gamification Engine
 */

// Audio Synthesizer via Web Audio API
const SoundEffects = (function() {
    let audioCtx = null;
    let isMuted = localStorage.getItem('lt_sound_muted') === 'true';

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
        } catch (e) {
            console.warn('Audio play error:', e);
        }
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

// Confetti Cannon
function triggerConfetti(levelUp = false) {
    if (typeof confetti === 'function') {
        if (levelUp) {
            // Big celebration
            const duration = 2.5 * 1000;
            const end = Date.now() + duration;

            (function frame() {
                confetti({
                    particleCount: 5,
                    angle: 60,
                    spread: 55,
                    origin: { x: 0 },
                    colors: ['#6366f1', '#a855f7', '#ec4899', '#fbbf24', '#34d399']
                });
                confetti({
                    particleCount: 5,
                    angle: 120,
                    spread: 55,
                    origin: { x: 1 },
                    colors: ['#6366f1', '#a855f7', '#ec4899', '#fbbf24', '#34d399']
                });
                if (Date.now() < end) {
                    requestAnimationFrame(frame);
                }
            })();
        } else {
            // Single burst
            confetti({
                particleCount: 60,
                spread: 70,
                origin: { y: 0.75 },
                colors: ['#6366f1', '#10b981', '#fbbf24']
            });
        }
    }
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

document.addEventListener('DOMContentLoaded', function() {
    const main = document.querySelector('main');
    if (main && !main.id) main.id = 'main';
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

    // Quest Check Form Interceptor
    document.querySelectorAll('.quest-toggle-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button');
            const questItem = this.closest('.quest-item');
            const formData = new FormData(this);

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
                if (data.status === 'success') {
                    if (data.action === 'completed') {
                        if (questItem) {
                            questItem.classList.add('completed');
                            const badge = questItem.querySelector('.quest-status-badge');
                            if (badge) {
                                badge.innerHTML = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Selesai Hari ini</span>';
                            }
                        }
                        if (submitBtn) {
                            submitBtn.innerHTML = '<i class="fas fa-check"></i>';
                            submitBtn.title = 'Batalkan selesai';
                        }
                        SoundEffects.questComplete();
                        triggerConfetti(data.leveled_up);
                        if (data.leveled_up) {
                            SoundEffects.levelUp();
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
                            submitBtn.innerHTML = '<i class="far fa-circle"></i>';
                            submitBtn.title = 'Tandai selesai';
                        }
                    }

                    // Update HUD & Stats live
                    const hudXp = document.getElementById('hudXp');
                    if (hudXp) hudXp.textContent = data.xp + ' XP';

                    const hudLevel = document.getElementById('hudLevel');
                    if (hudLevel) hudLevel.textContent = 'Lv. ' + data.level;

                    const hudStreak = document.getElementById('hudStreak');
                    if (hudStreak) hudStreak.textContent = data.streak + ' Hari';

                    const statTotalXp = document.getElementById('statTotalXp');
                    if (statTotalXp) statTotalXp.textContent = data.xp;

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

                    showToast(data.message, data.action === 'completed' ? 'success' : 'info');
                } else {
                    showToast(data.message || 'Gagal memproses quest', 'danger');
                }
            })
            .catch(() => {
                showToast('Jaringan bermasalah. Mengirim ulang...', 'warning');
                form.submit();
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
});
