const CACHE_NAME = 'oyalo-cache-v1';
const ASSETS_TO_CACHE = [
  '/',
  '/css/app.css',
  '/js/pwa.js',
  '/images/logo-192.png',
  '/images/logo-512.png',
];

// Install Event - Caching basic files
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Pre-caching offline assets');
      return cache.addAll(ASSETS_TO_CACHE);
    }).then(() => self.skipWaiting())
  );
});

// Activate Event - Clean up old caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[Service Worker] Clearing old cache:', cache);
            return caches.delete(cache);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

// Fetch Event - Network first, fall back to Cache
self.addEventListener('fetch', (event) => {
  // Only handle GET requests and exclude administrative panels or API polling
  if (event.request.method !== 'GET' || event.request.url.includes('/api/') || event.request.url.includes('/admin') || event.request.url.includes('/superadmin')) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        // If successful, clone response and add to cache
        if (networkResponse.status === 200) {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseToCache);
          });
        }
        return networkResponse;
      })
      .catch(() => {
        // Network failed, try to serve from Cache
        console.log('[Service Worker] Network failed, loading cache for:', event.request.url);
        return caches.match(event.request).then((cachedResponse) => {
          if (cachedResponse) {
            return cachedResponse;
          }
          
          // If a page request fails entirely, return a simple offline page response
          if (event.request.headers.get('accept').includes('text/html')) {
            return new Response(`
              <!DOCTYPE html>
              <html lang="en">
              <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Offline - Oyalo WiFi</title>
                <style>
                  body { font-family: sans-serif; text-align: center; padding: 50px; background-color: #0f172a; color: #f8fafc; }
                  h1 { color: #f43f5e; }
                  p { color: #94a3b8; font-size: 1.1em; }
                  .btn { display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #4f46e5; color: white; text-decoration: none; border-radius: 8px; }
                </style>
              </head>
              <body>
                <h1>You are currently offline</h1>
                <p>Please make sure you are connected to the Oyalo Hotspot network and try again.</p>
                <a href="javascript:window.location.reload();" class="btn">Retry Connection</a>
              </body>
              </html>
            `, {
              headers: { 'Content-Type': 'text/html' }
            });
          }
        });
      })
  );
});
