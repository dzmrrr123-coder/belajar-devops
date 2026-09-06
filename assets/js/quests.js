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
    if (typeof data.xp_delta === 'number' && data.xp_delta > 0) xpJuice(data.xp_delta, questItem || form);
    if (data.leveled_up) {
        buzz([25, 50, 25]);
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

/* Quest toggle */
document.addEventListener('DOMContentLoaded', function() {
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

});
