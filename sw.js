const CACHE_NAME = 'slofinance-v1-cache';
const urlsToCache = [
  '/index.php'
];

// Install Event
self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Activate Event
self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});

// Fetch Event (Network first, fallback to cache)
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(() => {
      return caches.match(event.request);
    })
  );
});