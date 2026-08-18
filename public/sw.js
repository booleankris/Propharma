const CACHE_NAME = 'propharma-cache-v1';
const urlsToCache = [
  '/',
  '/manifest.json',
  '/img/sahabat-mascot.png',
  '/css/dashboard.css'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  // If it's an API call or non-GET request, always go to network
  if (event.request.method !== 'GET' || event.request.url.includes('/api/')) {
    return;
  }

  // Network First, falling back to cache
  event.respondWith(
    fetch(event.request).catch(function() {
      return caches.match(event.request);
    })
  );
});
