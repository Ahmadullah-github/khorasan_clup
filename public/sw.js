/**
 * Service Worker for کمپ خراسان PWA
 * DISABLED CACHING - All requests go directly to network
 */

const CACHE_NAME = 'khorasan-camp-v4';

// Install - skip waiting immediately
self.addEventListener('install', event => {
  console.log('Service Worker: Installing (no-cache mode)');
  self.skipWaiting();
});

// Activate - delete ALL caches and claim clients
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating (clearing all caches)');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            console.log('Service Worker: Deleting cache', cacheName);
            return caches.delete(cacheName);
          })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch - ALWAYS go to network, no caching
self.addEventListener('fetch', event => {
  // Just pass through to network - no caching at all
  event.respondWith(fetch(event.request));
});

console.log('Service Worker: Loaded (no-cache mode)');