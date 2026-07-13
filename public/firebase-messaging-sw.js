// Firebase Cloud Messaging service worker for the QG POS portal.
// Receives background pushes (when the tab is closed/backgrounded) and renders
// the system notification. Foreground messages are handled inside the page.
//
// IMPORTANT: this file must be served from the SITE ROOT (e.g. https://host/firebase-messaging-sw.js)
// — Firebase requires it. Do not move into /js or /assets.

importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

// These values are populated server-side by the firebase-init Blade partial,
// then this SW reads them from the URL query string at registration time:
//   /firebase-messaging-sw.js?apiKey=...&projectId=...&messagingSenderId=...&appId=...&authDomain=...
const params = new URL(self.location).searchParams;
const firebaseConfig = {
    apiKey:            params.get('apiKey'),
    authDomain:        params.get('authDomain'),
    projectId:         params.get('projectId'),
    storageBucket:     params.get('storageBucket'),
    messagingSenderId: params.get('messagingSenderId'),
    appId:             params.get('appId'),
};

firebase.initializeApp(firebaseConfig);
const messaging = firebase.messaging();

// Background message handler — fires when the page is NOT focused.
// Foreground messages are delivered to the page's onMessage listener instead.
messaging.onBackgroundMessage((payload) => {
    const title = (payload.notification && payload.notification.title) || 'QG POS';
    const body  = (payload.notification && payload.notification.body)  || '';
    const data  = payload.data || {};

    self.registration.showNotification(title, {
        body,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data,
        tag: data.type || 'qg-pos',  // collapse multiple of same type
        requireInteraction: data.type && data.type.endsWith('_reminder'), // sticky for buzzer
    });
});

// Click handler — focus an existing tab if one is open, otherwise open a new one.
self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const data   = event.notification.data || {};
    const target = routeFor(data) || '/';

    event.waitUntil((async () => {
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const c of clients) {
            if ('focus' in c) { await c.focus(); c.postMessage({ source: 'fcm', data }); return; }
        }
        if (self.clients.openWindow) await self.clients.openWindow(target);
    })());
});

function routeFor(data) {
    switch (data.type) {
        case 'order_created':
        case 'order_pending_reminder':
            return '/app/orders';
        case 'order_pushed':
            return data.order_id ? `/app/orders` : '/app/orders';
        case 'receipt_created':
        case 'receipt_pending_reminder':
            return '/app/reciepts';
        case 'receipt_pushed':
            return data.receipt_id ? `/app/reciepts/${data.receipt_id}` : '/app/reciepts';
        default:
            return '/';
    }
}
