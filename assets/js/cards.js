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

function applyReviewResponse(data, form) {
    const ok = data.result === 'good' || data.result === 'easy' || data.result === 'know' || data.result === 'hard';
    showToast(data.message + (data.next_interval ? ' Berikutnya ' + data.next_interval + ' hari.' : ''), ok ? 'success' : 'info');
    if (data.xp_gain > 0) {
        xpJuice(data.xp_gain, form);
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
    const sk = document.getElementById('reviewSkill');
    if (sk) sk.textContent = n.skill || 'General';
    const due = document.getElementById('reviewDue');
    if (due) due.textContent = reviewDueLabel(n.next_due) + ' · interval ' + n.interval_day + ' hari · reps ' + (n.reps || 0);
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
function applyQuizResponse(data, form) {
    const run = data.run || {};
    if (data.result === 'know' && data.gain > 0) {
        xpJuice(data.gain, form);
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

/* Review/quiz interceptors */
document.addEventListener('DOMContentLoaded', function() {
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
            fetchJSON('review.php', submitterFormData(this, clicked))
            .then(data => {
                if (data.status !== 'success') {
                    showToast(data.message || 'Gagal menyimpan review.', 'danger');
                    return;
                }
                applyReviewResponse(data, form);
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
            fetchJSON('quiz.php', submitterFormData(this, clicked))
            .then(data => {
                if (data.status !== 'success') {
                    showToast(data.message || 'Gagal menyimpan jawaban.', 'danger');
                    return;
                }
                applyQuizResponse(data, form);
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

});
