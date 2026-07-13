@auth
@php
    $cfg     = config('firebase.web');
    $vapid   = $cfg['vapid_key']       ?? null;
    $apiKey  = $cfg['api_key']         ?? null;
    $project = $cfg['project_id']      ?? null;
    $sender  = $cfg['messaging_sender_id'] ?? null;
    $appId   = $cfg['app_id']          ?? null;
    $authDom = $cfg['auth_domain']     ?? null;
    $bucket  = $cfg['storage_bucket']  ?? '';

    $swParams = http_build_query(array_filter([
        'apiKey'            => $apiKey,
        'authDomain'        => $authDom,
        'projectId'         => $project,
        'storageBucket'     => $bucket,
        'messagingSenderId' => $sender,
        'appId'             => $appId,
    ]));
@endphp

@if($apiKey && $project && $vapid && $appId)
<script type="module">
    // Foreground push handling + token registration for the QG POS portal.
    import { initializeApp }              from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-app.js';
    import { getMessaging, getToken, onMessage } from 'https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging.js';

    const firebaseConfig = {
        apiKey:            @json($apiKey),
        authDomain:        @json($authDom),
        projectId:         @json($project),
        storageBucket:     @json($bucket),
        messagingSenderId: @json($sender),
        appId:             @json($appId),
    };

    const app       = initializeApp(firebaseConfig);
    const messaging = getMessaging(app);

    async function registerFcm() {
        if (!('serviceWorker' in navigator) || !('Notification' in window)) return;

        // Ask permission only if not already granted/denied.
        if (Notification.permission === 'default') {
            const perm = await Notification.requestPermission();
            if (perm !== 'granted') return;
        }
        if (Notification.permission !== 'granted') return;

        // Service worker MUST be served from the site root with the same query
        // string that firebase-messaging-sw.js reads at registration time.
        const swReg = await navigator.serviceWorker.register(
            '/firebase-messaging-sw.js?{{ $swParams }}',
            { scope: '/' }
        );

        try {
            const token = await getToken(messaging, {
                vapidKey: @json($vapid),
                serviceWorkerRegistration: swReg,
            });
            if (!token) return;

            // Send token to the portal's web FCM endpoint (session-authenticated).
            await fetch('/fcm-token', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                credentials: 'same-origin',
                body: JSON.stringify({ fcm_token: token }),
            });
        } catch (e) {
            console.warn('FCM token registration failed', e);
        }

        // Foreground message handler — page is focused, browser does NOT show
        // a system notification automatically, so we render one ourselves.
        onMessage(messaging, (payload) => {
            const title = payload.notification?.title || 'QG POS';
            const body  = payload.notification?.body  || '';
            try {
                new Notification(title, {
                    body,
                    icon: '/favicon.ico',
                    tag:  payload.data?.type || 'qg-pos',
                });
            } catch (e) { /* some browsers require SW for Notifications */ }
        });
    }

    // Defer until after first paint
    if (document.readyState === 'complete') registerFcm();
    else window.addEventListener('load', registerFcm);
</script>
@endif
@endauth
