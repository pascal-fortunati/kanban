const VERSION = 'v1';
const STATIC_CACHE = 'static-' + VERSION;
const RUNTIME_CACHE = 'runtime-' + VERSION;
const CORE = [
  './',
  './assets/css/kanban.css',
  './assets/js/utils.js',
  './assets/js/kanban.js',
  './assets/js/notifications.js',
  'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css',
  'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js',
  'https://cdn.jsdelivr.net/npm/sweetalert2@11'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(STATIC_CACHE).then((cache) => cache.addAll(CORE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.map((k) => {
      if (![STATIC_CACHE, RUNTIME_CACHE].includes(k)) { return caches.delete(k); }
    }))).then(() => self.clients.claim())
  );
});

function isHtml(request) {
  return request.mode === 'navigate' || (request.headers.get('accept') || '').includes('text/html');
}

function isAsset(request) {
  const d = request.destination;
  return d === 'script' || d === 'style' || d === 'font' || d === 'image';
}

const OFFLINE = new Response('<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hors ligne</title><style>body{background:#1f2937;color:#e5e7eb;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0} .box{background:#111827;border:1px solid #374151;border-radius:.75rem;padding:1.5rem;max-width:520px;box-shadow:0 10px 25px rgba(0,0,0,.35)} .title{font-weight:600;font-size:1.25rem;margin-bottom:.5rem;color:#61afef} .text{font-size:.95rem;color:#cbd5e1}</style></head><body><div class="box"><div class="title">Vous êtes hors ligne</div><div class="text">Certaines fonctionnalités ne sont pas disponibles sans connexion. Réessayez lorsque vous serez en ligne.</div></div></body></html>', { headers: { 'Content-Type': 'text/html; charset=utf-8' } });

self.addEventListener('fetch', (event) => {
  const { request } = event;
  if (isHtml(request)) {
    event.respondWith(
      fetch(request).then((resp) => {
        const copy = resp.clone();
        caches.open(RUNTIME_CACHE).then((c) => c.put(request, copy));
        return resp;
      }).catch(() => caches.match(request).then((c) => c || OFFLINE))
    );
    return;
  }
  if (isAsset(request)) {
    event.respondWith(
      caches.match(request).then((cached) => {
        const fetchPromise = fetch(request).then((resp) => {
          const copy = resp.clone();
          caches.open(STATIC_CACHE).then((c) => c.put(request, copy));
          return resp;
        }).catch(() => cached);
        return cached || fetchPromise;
      })
    );
    return;
  }
  event.respondWith(
    caches.match(request).then((c) => c || fetch(request).then((resp) => {
      const copy = resp.clone();
      caches.open(RUNTIME_CACHE).then((cache) => cache.put(request, copy));
      return resp;
    }).catch(() => new Response('', { status: 408 })))
  );
});