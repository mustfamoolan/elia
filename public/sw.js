/* ==========================================================================
   معهد آيلا التعليمي — Unified PWA & Firebase Background Push Service Worker
   ========================================================================== */
const SW_CACHE_NAME = 'eyal-static-v1';

self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== SW_CACHE_NAME).map((k) => caches.delete(k)))
        ).then(() => self.clients.claim())
    );
});

// تخزين مؤقت خفيف للملفات الثابتة فقط حتى لا تتأثر صفحات Laravel أو جلسات الدخول
self.addEventListener('fetch', (event) => {
    const req = event.request;
    if (req.method !== 'GET') return;

    const url = new URL(req.url);
    if (url.origin !== self.location.origin) return;

    const isStaticAsset =
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/css/') ||
        url.pathname.startsWith('/js/') ||
        url.pathname === '/favicon.ico' ||
        url.pathname === '/manifest.webmanifest';

    if (!isStaticAsset) return;

    event.respondWith(
        fetch(req)
            .then((res) => {
                if (res && res.status === 200) {
                    const clone = res.clone();
                    caches.open(SW_CACHE_NAME).then((cache) => cache.put(req, clone));
                }
                return res;
            })
            .catch(() => caches.match(req))
    );
});

// استقبال إشعارات Firebase Cloud Messaging في الخلفية
self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { notification: { title: 'معهد آيلا التعليمي', body: event.data ? event.data.text() : '' } };
    }

    const notif = payload.notification || {};
    const data = payload.data || {};
    const title = notif.title || data.title || 'معهد آيلا التعليمي';
    const body = notif.body || data.body || 'لديك إشعار جديد';
    const targetUrl = data.url || (payload.fcmOptions && payload.fcmOptions.link) || '/';
    const tag = data.tag || 'eyal-notif';

    event.waitUntil(
        self.registration.showNotification(title, {
            body,
            icon: notif.icon || '/assets/logo.png',
            badge: notif.badge || '/assets/logo.png',
            dir: 'rtl',
            lang: 'ar',
            tag,
            renotify: true,
            data: { url: targetUrl },
        })
    );
});

// فتح الصفحة المطلوبة عند النقر على الإشعار
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const targetUrl = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client) {
                    client.navigate(targetUrl).catch(() => {});
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(targetUrl);
            }
        })
    );
});
