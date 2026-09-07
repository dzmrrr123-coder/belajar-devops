document.querySelectorAll('.react-bar').forEach(function(bar) {
    if (bar.dataset.reactBound) return;
    bar.dataset.reactBound = '1';
    bar.addEventListener('click', async function(e) {
        const btn = e.target.closest('.react-btn');
        if (!btn || btn.disabled) return;
        btn.disabled = true;
        const fd = new FormData();
        fd.append('target_type', 'profile');
        fd.append('target_id', bar.dataset.target);
        fd.append('emoji', btn.dataset.emoji);
        const csrf = document.querySelector('#reactCsrf input[name="csrf_token"], input[name="csrf_token"]');
        if (csrf) fd.append('csrf_token', csrf.value);
        try {
            const res = await fetch('react.php', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const data = await res.json();
            if (data && data.status === 'success') {
                bar.querySelectorAll('.react-btn').forEach(function(b) {
                    const k = b.dataset.emoji;
                    const n = (data.counts && data.counts[k]) || 0;
                    const on = (data.mine || []).includes(k);
                    b.querySelector('span').textContent = n;
                    b.classList.toggle('on', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
            } else if (data && data.message) {
                try { showToast(data.message, 'warning'); } catch (err) {}
            }
        } catch (err) {}
        btn.disabled = false;
    });
});
