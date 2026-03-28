const CACHE_NAME = 'pgconnect-v2';
const APP_ROOT = new URL('./', self.location.href);
const OFFLINE_URLS = [
  'index.php',
  'backend/login.php',
  'backend/signup.php',
  'user/pg-listings.php'
].map((path) => new URL(path, APP_ROOT).pathname);
const FALLBACK_URL = new URL('index.php', APP_ROOT).pathname;

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(OFFLINE_URLS)).catch(() => {})
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : Promise.resolve()))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    fetch(event.request)
      .then((resp) => {
        const copy = resp.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy)).catch(() => {});
        return resp;
      })
      .catch(() => caches.match(event.request).then((cached) => cached || caches.match(FALLBACK_URL)))
  );
});
