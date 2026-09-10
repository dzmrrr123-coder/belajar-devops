const CACHE = 'lt-static-v7';
const PRECACHE = ['assets/css/app.css', 'assets/js/core.js', 'assets/js/site.js', 'assets/js/sync.js', 'offline.php', 'assets/icons/icon-192.png', 'assets/icons/icon-512.png'];
const PAGE_CACHE_LIMIT = 10;
const STATIC_CACHE_LIMIT = 40;
// Abaikan ?v=filemtime agar 1 file = 1 entri cache, bukan duplikat tiap deploy
function cacheKey(request) {
  const url = new URL(request.url);
  if (url.origin === self.location.origin && /\.(css|js|png|webp|ico|woff2?)$/i.test(url.pathname)) url.search = '';
  return new Request(url.toString(), { headers: request.headers });
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()).catch(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

function trimPages(cache) {
  return cache.keys().then((keys) => {
    const pages = keys.filter((r) => r.url.includes('.php') && !r.url.endsWith('offline.php'));
    if (pages.length <= PAGE_CACHE_LIMIT) return;
    const extra = pages.slice(0, pages.length - PAGE_CACHE_LIMIT);
    return Promise.all(extra.map((r) => cache.delete(r)));
  });
}
function trimStatic(cache) {
  return cache.keys().then((keys) => {
    const statics = keys.filter((r) => !r.url.includes('.php'));
    if (statics.length <= STATIC_CACHE_LIMIT) return;
    const extra = statics.slice(0, statics.length - STATIC_CACHE_LIMIT);
    return Promise.all(extra.map((r) => cache.delete(r)));
  });
}

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET' || url.origin !== self.location.origin) return;
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).then((res) => {
        if (res.ok) {
          const copy = res.clone();
          caches.open(CACHE).then((cache) => cache.put(event.request, copy).then(() => trimPages(cache)).catch(() => {}));
        }
        return res;
      }).catch(() => caches.match(event.request).then((hit) => hit || caches.match('offline.php')))
    );
    return;
  }
  const key = cacheKey(event.request);
  event.respondWith(
    caches.match(key).then((hit) => hit || fetch(event.request).then((res) => {
      if (res.ok) {
        const copy = res.clone();
        caches.open(CACHE).then((cache) => cache.put(key, copy).then(() => trimStatic(cache)).catch(() => {}));
      }
      return res;
    }).catch(() => caches.match(key)))
  );
});

self.addEventListener('sync', (event) => {
  if (event.tag !== 'lt-outbox') return;
  event.waitUntil(
    self.clients.matchAll({ includeUncontrolled: true, type: 'window' }).then((clients) => {
      clients.forEach((c) => { try { c.postMessage({ type: 'lt-flush-outbox' }); } catch (e) {} });
      return clients.length ? true : Promise.reject(new Error('no-client'));
    }).catch(() => Promise.reject(new Error('retry-later')))
  );
});
