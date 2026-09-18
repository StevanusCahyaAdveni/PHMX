// sw.js - PHMX Service Worker with Web Push Handler
const CACHE_NAME = 'phmx-pwa-v1';
const ASSETS_TO_CACHE = [
  './',
  'assets/css/bootstrap.min.css',
  'assets/images/logo/phmx-mark.svg',
  'assets/images/logo/phmx-logo.png'
];

// 1. Install & Pre-cache
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE).catch((err) => {
        console.warn('[PHMX SW] Pre-cache warning:', err);
      });
    }).then(() => self.skipWaiting())
  );
});

// 2. Activate & Clean Old Caches
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => {
      return Promise.all(
        keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))
      );
    }).then(() => self.clients.claim())
  );
});

// 3. Fetch Event (Network-first with Cache fallback)
self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return;
  
  event.respondWith(
    fetch(event.request)
      .then((networkResponse) => {
        return networkResponse;
      })
      .catch(() => {
        return caches.match(event.request);
      })
  );
});

// 4. Push Event (VAPID Notification Receiver)
self.addEventListener('push', (event) => {
  let data = {
    title: 'PHMX Notification',
    body: 'Anda mendapatkan pemberitahuan baru.',
    icon: 'assets/images/logo/phmx-mark.svg',
    badge: 'assets/images/logo/phmx-mark.svg',
    data: { url: './' }
  };

  if (event.data) {
    try {
      const parsed = event.data.json();
      data = Object.assign(data, parsed);
    } catch (e) {
      data.body = event.data.text();
    }
  }

  const options = {
    body: data.body,
    icon: data.icon || 'assets/images/logo/phmx-mark.svg',
    badge: data.badge || 'assets/images/logo/phmx-mark.svg',
    vibrate: [100, 50, 100],
    data: data.data || { url: './' },
    actions: data.actions || []
  };

  // 1. Tampilkan notifikasi sistem OS (Pop-up OS)
  const showNotificationPromise = self.registration.showNotification(data.title, options);

  // 2. Kirim sinyal ke semua tab web yang sedang aktif agar memunculkan Toast di dalam aplikasi
  const broadcastPromise = self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
    for (const client of clientList) {
      client.postMessage({
        type: 'PHMX_PUSH_NOTIFICATION',
        payload: data
      });
    }
  });

  event.waitUntil(
    Promise.all([showNotificationPromise, broadcastPromise])
  );
});

// 5. Notification Click Event
self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = event.notification.data && event.notification.data.url ? event.notification.data.url : './';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
      // Jika tab sudah terbuka, fokuskan
      for (const client of clientList) {
        if (client.url.includes(targetUrl) && 'focus' in client) {
          return client.focus();
        }
      }
      // Jika belum terbuka, buka tab baru
      if (clients.openWindow) {
        return clients.openWindow(targetUrl);
      }
    })
  );
});
