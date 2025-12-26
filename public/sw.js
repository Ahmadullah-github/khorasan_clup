/**
 * Service Worker for کمپ خراسان PWA
 * Handles offline caching, background sync, and push notifications
 */

const CACHE_NAME = 'khorasan-camp-v1';
const API_CACHE_NAME = 'khorasan-api-v1';

// Files to cache immediately (App Shell)
const STATIC_CACHE_FILES = [
  './',
  './index.html',
  './login.html',
  './students.html',
  './student-detail.html',
  './coaches.html',
  './coach-detail.html',
  './coach-form.html',
  './expenses.html',
  './expense-detail.html',
  './rent.html',
  './accounting.html',
  './reports.html',
  './breakdown.html',
  './admin.html',
  './invoice.html',
  './css/design-system.css',
  './css/custom.css',
  './css/pwa-styles.css',
  './js/app.js',
  './js/offline-manager.js',
  './js/pwa-manager.js',
  './js/jalali.js',
  './manifest.json'
];

// API endpoints to cache
const API_CACHE_PATTERNS = [
  /\/api\/students\.php/,
  /\/api\/coaches\.php/,
  /\/api\/expenses\.php/,
  /\/api\/accounting\.php/,
  /\/api\/reports\.php/
];

// Install event - cache static files
self.addEventListener('install', event => {
  console.log('Service Worker: Installing...');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Caching static files');
        return cache.addAll(STATIC_CACHE_FILES);
      })
      .then(() => {
        console.log('Service Worker: Static files cached');
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Cache failed', error);
      })
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('Service Worker: Activating...');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== CACHE_NAME && cacheName !== API_CACHE_NAME) {
              console.log('Service Worker: Deleting old cache', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Activated');
        return self.clients.claim();
      })
  );
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Handle API requests
  if (url.pathname.startsWith('/api/')) {
    event.respondWith(handleAPIRequest(request));
    return;
  }
  
  // Handle static files
  event.respondWith(
    caches.match(request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }
        
        return fetch(request)
          .then(response => {
            // Don't cache non-successful responses
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }
            
            // Clone response for caching
            const responseToCache = response.clone();
            
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(request, responseToCache);
              });
            
            return response;
          })
          .catch(() => {
            // Offline fallback for HTML pages
            if (request.headers.get('accept').includes('text/html')) {
              return caches.match('/index.html');
            }
          });
      })
  );
});

// Handle API requests with caching strategy
async function handleAPIRequest(request) {
  const url = new URL(request.url);
  
  // Check if this API should be cached
  const shouldCache = API_CACHE_PATTERNS.some(pattern => pattern.test(url.pathname));
  
  if (!shouldCache) {
    // For non-cacheable APIs (like auth, uploads), just try network
    try {
      return await fetch(request);
    } catch (error) {
      return new Response(JSON.stringify({
        success: false,
        error: 'شبکه در دسترس نیست. لطفاً اتصال اینترنت خود را بررسی کنید.'
      }), {
        status: 503,
        headers: { 'Content-Type': 'application/json' }
      });
    }
  }
  
  // For cacheable APIs: Network First strategy
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful responses
      const cache = await caches.open(API_CACHE_NAME);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // Network failed, try cache
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
      // Add offline indicator to cached response
      const data = await cachedResponse.json();
      data._offline = true;
      data._cached_at = new Date().toISOString();
      
      return new Response(JSON.stringify(data), {
        status: 200,
        headers: { 'Content-Type': 'application/json' }
      });
    }
    
    // No cache available
    return new Response(JSON.stringify({
      success: false,
      error: 'داده‌ها در حالت آفلاین در دسترس نیست',
      _offline: true
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

// Background Sync for offline actions
self.addEventListener('sync', event => {
  console.log('Service Worker: Background sync triggered', event.tag);
  
  if (event.tag === 'sync-offline-data') {
    event.waitUntil(syncOfflineData());
  }
});

// Sync offline data when connection is restored
async function syncOfflineData() {
  console.log('Service Worker: Syncing offline data...');
  
  try {
    // Get offline data from IndexedDB
    const offlineData = await getOfflineData();
    
    if (offlineData.length === 0) {
      console.log('Service Worker: No offline data to sync');
      return;
    }
    
    // Send each offline action to server
    for (const item of offlineData) {
      try {
        const response = await fetch(item.url, {
          method: item.method,
          headers: item.headers,
          body: item.body
        });
        
        if (response.ok) {
          // Remove from offline storage
          await removeOfflineData(item.id);
          console.log('Service Worker: Synced offline item', item.id);
        }
      } catch (error) {
        console.error('Service Worker: Failed to sync item', item.id, error);
      }
    }
    
    // Notify main app about sync completion
    const clients = await self.clients.matchAll();
    clients.forEach(client => {
      client.postMessage({
        type: 'SYNC_COMPLETE',
        synced: offlineData.length
      });
    });
    
  } catch (error) {
    console.error('Service Worker: Sync failed', error);
  }
}

// IndexedDB helpers (simplified - full implementation needed)
async function getOfflineData() {
  // TODO: Implement IndexedDB read
  return [];
}

async function removeOfflineData(id) {
  // TODO: Implement IndexedDB delete
}

// Push notification handler
self.addEventListener('push', event => {
  if (!event.data) return;
  
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/assets/icon-192x192.png',
    badge: '/assets/icon-72x72.png',
    vibrate: [200, 100, 200],
    data: data.data || {},
    actions: data.actions || []
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  const urlToOpen = event.notification.data.url || '/';
  
  event.waitUntil(
    self.clients.matchAll({ type: 'window' })
      .then(clients => {
        // Check if app is already open
        for (const client of clients) {
          if (client.url === urlToOpen && 'focus' in client) {
            return client.focus();
          }
        }
        
        // Open new window
        if (self.clients.openWindow) {
          return self.clients.openWindow(urlToOpen);
        }
      })
  );
});

console.log('Service Worker: Loaded');