window.LTOutbox = (function() {
    const KEY = 'lt_outbox_v1';
    const MAX_ATTEMPTS = 8;

    function load() {
        try {
            const v = JSON.parse(localStorage.getItem(KEY) || '[]');
            return Array.isArray(v) ? v.filter((e) => e && e.type && e.id) : [];
        } catch (e) {
            return [];
        }
    }

    function save(queue) {
        try {
            localStorage.setItem(KEY, JSON.stringify(queue));
        } catch (e) {}
    }

    function freshCsrf(fallback) {
        const el = document.querySelector('input[name="csrf_token"]');
        return (el && el.value) || fallback || '';
    }

    function findQuestForm(questId) {
        const id = String(questId ?? '');
        return [...document.querySelectorAll('.quest-toggle-form')].find((f) => {
            const el = f.querySelector('input[name="quest_id"]');
            return el && el.value === id;
        }) || null;
    }

    function ensureBar() {
        let el = document.getElementById('syncBar');
        if (el) return el;
        el = document.createElement('div');
        el.id = 'syncBar';
        el.className = 'sync-bar';
        el.setAttribute('role', 'status');
        el.hidden = true;
        el.innerHTML = '<span class="sync-dot" aria-hidden="true"></span><span class="sync-text"></span><button type="button" class="sync-retry">Kirim ulang</button>';
        el.querySelector('.sync-retry').addEventListener('click', () => flush());
        document.body.appendChild(el);
        return el;
    }

    function render() {
        const queue = load();
        const el = ensureBar();
        if (!queue.length && navigator.onLine) {
            el.hidden = true;
        } else {
            el.hidden = false;
            el.querySelector('.sync-text').textContent = !navigator.onLine
                ? 'Offline · ' + queue.length + ' antrean'
                : queue.length + ' antrean menunggu dikirim';
        }
        document.querySelectorAll('.quest-pending').forEach((n) => n.remove());
        queue.forEach((entry) => {
            if (entry.type !== 'quest_toggle') return;
            const form = findQuestForm(entry.quest_id);
            const badge = form && form.closest('.quest-item') ? form.closest('.quest-item').querySelector('.quest-status-badge') : null;
            if (badge) {
                const s = document.createElement('span');
                s.className = 'quest-pending';
                s.textContent = 'Antre';
                badge.appendChild(s);
            }
        });
    }

    function enqueue(entry) {
        entry.id = 'q' + Date.now().toString(36) + Math.floor(Math.random() * 1e6).toString(36);
        entry.ts = Date.now();
        entry.attempts = 0;
        const queue = load();
        if (entry.type === 'quest_toggle' && queue.some((e) => e.type === 'quest_toggle' && String(e.quest_id) === String(entry.quest_id))) return;
        queue.push(entry);
        save(queue);
        render();
        if (navigator.onLine) flush();
    }

    let flushing = false;
    function flush() {
        if (flushing) return;
        if (!navigator.onLine) {
            render();
            return;
        }
        const queue = load();
        if (!queue.length) {
            render();
            return;
        }
        flushing = true;
        const head = queue[0];
        send(head).then((result) => {
            flushing = false;
            if (result === 'done') {
                save(load().filter((e) => e.id !== head.id));
                render();
                flush();
            } else if (result === 'auth') {
                save([]);
                render();
                showToast('Sesi berakhir. Masuk lagi untuk mengirim antrean.', 'warning');
            } else {
                const rest = load();
                const cur = rest.find((e) => e.id === head.id);
                if (cur) {
                    cur.attempts = (cur.attempts || 0) + 1;
                    if (cur.attempts >= MAX_ATTEMPTS) {
                        save(rest.filter((e) => e.id !== cur.id));
                        showToast('Satu antrean dibatalkan setelah gagal berulang.', 'danger');
                    } else {
                        save(rest);
                    }
                }
                render();
            }
        }).catch(() => {
            flushing = false;
            render();
        });
    }

    function postForm(url, fields, ajax) {
        const fd = new FormData();
        Object.keys(fields).forEach((k) => fd.append(k, fields[k] ?? ''));
        return fetch(url, {
            method: 'POST',
            body: fd,
            headers: ajax ? { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } : {}
        });
    }

    function authFailed(text) {
        return /token|csrf|keamanan|security|login|masuk/i.test(text || '');
    }

    function send(entry) {
        if (entry.type === 'quest_toggle') {
            return postForm('complete_quest.php', { csrf_token: freshCsrf(entry.csrf), quest_id: entry.quest_id }, true)
                .then((res) => res.json().catch(() => ({ status: 'error', message: 'Respon tak valid.' })))
                .then((data) => {
                    if (data.status === 'success') {
                        document.dispatchEvent(new CustomEvent('lt:quest-synced', { detail: { data, questId: String(entry.quest_id) } }));
                        return 'done';
                    }
                    return authFailed(data.message) ? 'auth' : 'retry';
                }).catch(() => 'retry');
        }
        if (entry.type === 'pomodoro') {
            return postForm('record_pomodoro.php', { csrf_token: freshCsrf(entry.csrf), duration: entry.minutes || 25 }, true)
                .then((res) => res.json().catch(() => ({ status: 'error', message: '' })))
                .then((data) => {
                    if (data.status === 'success') {
                        document.dispatchEvent(new CustomEvent('lt:pomodoro-synced', { detail: { data, minutes: entry.minutes || 25 } }));
                        return 'done';
                    }
                    return data.message && authFailed(data.message) ? 'auth' : 'retry';
                }).catch(() => 'retry');
        }
        if (entry.type === 'error_add') {
            const f = entry.fields || {};
            if (!String(f.error_message || '').trim()) return Promise.resolve('done');
            return postForm('errors.php', {
                csrf_token: freshCsrf(entry.csrf),
                action: 'add_error',
                category: f.category || 'General',
                error_message: f.error_message || '',
                solution: f.solution || '',
                reference_link: f.reference_link || ''
            }, false).then((res) => {
                if (res.status === 403) return 'auth';
                if (res.ok) {
                    showToast('Catatan offline terkirim (+5 XP).', 'success');
                    return 'done';
                }
                return 'retry';
            }).catch(() => 'retry');
        }
        return Promise.resolve('done');
    }

    function interceptForms() {
        document.querySelectorAll('form[data-outbox="error_add"]').forEach((form) => {
            form.addEventListener('submit', function(e) {
                if (navigator.onLine) return;
                e.preventDefault();
                const fd = new FormData(form);
                const msg = String(fd.get('error_message') || '').trim();
                if (!msg) {
                    showToast('Pesan error tidak boleh kosong.', 'warning');
                    return;
                }
                enqueue({
                    type: 'error_add',
                    csrf: String(fd.get('csrf_token') || ''),
                    fields: {
                        category: String(fd.get('category') || 'General'),
                        error_message: msg,
                        solution: String(fd.get('solution') || ''),
                        reference_link: String(fd.get('reference_link') || '')
                    }
                });
                form.reset();
                showToast('Offline. Catatan masuk antrean.', 'warning');
            });
        });
    }

    window.addEventListener('online', flush);
    window.addEventListener('offline', render);
    document.addEventListener('DOMContentLoaded', () => {
        interceptForms();
        render();
        flush();
    });

    return { enqueue, flush, count: () => load().length };
})();
