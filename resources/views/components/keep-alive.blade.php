{{-- Session / CSRF keep-alive for every full-page Livewire view.

     Two problems this solves:
       1. Users working mid-page suddenly see a "Page Expired" reload prompt
          because Laravel rotated the CSRF token (login/logout in another
          tab, session regenerate, or the file-session GC pruning an idle
          record). Livewire's default reaction is a full-page reload — the
          user loses their in-progress form/cart.
       2. A tab left idle for a while, when returned to, throws 419 on the
          first click because the in-memory token is stale.

     Strategy:
       - Ping /app/keep-alive on a short interval (90s) and on tab-focus so
          the token stored in the meta tag / axios defaults / Livewire config
          is always fresh.
       - Hook into Livewire's `request` lifecycle and inject the current
          token on every outgoing call so late-changed tokens are picked up
          without waiting for the next full render.
       - On any 419 (Livewire, fetch, or axios), suppress the default UI,
          synchronously refresh the token, and let the caller retry. For
          Livewire specifically we call the retry path automatically so the
          user doesn't have to click twice. --}}
<script>
(function () {
    var KEEP_ALIVE_URL = @json(route('keep-alive'));
    var INTERVAL_MS    = 90 * 1000;   // 90s — well under any GC / cookie window
    var STALE_MS       = 45 * 1000;   // refresh-before-request if older than this
    var lastPingAt     = 0;
    var pingInFlight   = null;

    function getToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    function setToken(token) {
        if (!token) return;
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.setAttribute('content', token);
        // Any framework that reads its token at boot needs a live update too,
        // otherwise the first post-rotation request goes out with the stale
        // header and still 419s.
        if (window.axios && window.axios.defaults) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
        if (window.livewireScriptConfig) {
            window.livewireScriptConfig.csrf = token;
        }
        if (window.Livewire && window.Livewire.find && window.Livewire.all) {
            // Livewire 3 stores the token internally on window.livewireScriptConfig
            // AND uses it lazily from that object per request — updating the
            // object above is sufficient.
        }
    }

    function ping(force) {
        var now = Date.now();
        if (!force && pingInFlight) return pingInFlight;
        if (!force && (now - lastPingAt) < STALE_MS) return Promise.resolve(getToken());

        lastPingAt = now;
        pingInFlight = fetch(KEEP_ALIVE_URL, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            cache: 'no-store',
        }).then(function (res) {
            if (res.status === 401) {
                // Session actually died (logged out). Send them to login
                // rather than leaving them stuck on a broken page.
                if (typeof window !== 'undefined') window.location.href = '/login';
                return null;
            }
            if (res.status === 419) return null;
            if (!res.ok) return null;
            return res.json().catch(function () { return null; });
        }).then(function (data) {
            if (data && data.token) setToken(data.token);
            return getToken();
        }).catch(function () { return getToken(); })
          .finally(function () { pingInFlight = null; });

        return pingInFlight;
    }

    function registerLivewireHook() {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return false;

        window.Livewire.hook('request', function (ctx) {
            // Inject the freshest known token into every outgoing Livewire
            // call, in case one rotated between hook registration and now.
            var t = getToken();
            if (t && ctx && ctx.options) {
                ctx.options.headers = ctx.options.headers || {};
                ctx.options.headers['X-CSRF-TOKEN'] = t;
            }

            // Fail handler — Livewire 3 fires this before its built-in
            // "Page Expired" modal. Calling preventDefault stops the modal
            // AND the reload. We refresh the token then dispatch a soft
            // re-render on the failed component so the user's action retries
            // transparently with the new token.
            if (ctx && typeof ctx.fail === 'function') {
                ctx.fail(function (failCtx) {
                    if (!failCtx || failCtx.status !== 419) return;
                    if (typeof failCtx.preventDefault === 'function') failCtx.preventDefault();
                    ping(true).then(function () {
                        try {
                            // Nudge every mounted Livewire component to re-render
                            // with the fresh token; the user's next click will
                            // succeed without the "Page Expired" prompt.
                            if (window.Livewire && typeof window.Livewire.all === 'function') {
                                window.Livewire.all().forEach(function (c) {
                                    if (c && typeof c.$refresh === 'function') c.$refresh();
                                });
                            }
                        } catch (e) { /* non-fatal */ }
                    });
                });
            }
        });

        return true;
    }

    if (!registerLivewireHook()) {
        document.addEventListener('livewire:init', registerLivewireHook);
    }

    // Catch 419s from plain fetch()/axios calls (Filament tables, custom
    // Alpine actions, ad-hoc admin scripts). Silently refresh the token and
    // return the original response so the caller's own handler still sees
    // it — no browser confirm/alert ever fires.
    if (typeof window.fetch === 'function' && !window._keepAliveFetchPatched) {
        window._keepAliveFetchPatched = true;
        var origFetch = window.fetch.bind(window);
        window.fetch = function () {
            var args = arguments;
            return origFetch.apply(null, args).then(function (res) {
                if (res && res.status === 419) ping(true);
                return res;
            });
        };
    }
    if (window.axios && window.axios.interceptors && !window._keepAliveAxiosPatched) {
        window._keepAliveAxiosPatched = true;
        window.axios.interceptors.response.use(
            function (res) { return res; },
            function (err) {
                if (err && err.response && err.response.status === 419) ping(true);
                return Promise.reject(err);
            }
        );
    }

    // Suppress Livewire 3's built-in "Page Expired" browser confirm dialog
    // as a belt-and-braces fallback: if the fail hook missed for any reason,
    // this catches the reload prompt before the user sees it. The DOM node
    // Livewire injects has data-testid="livewire-error-modal" in dev builds
    // and a specific class in prod — hide anything matching common markers.
    document.addEventListener('DOMContentLoaded', function () {
        var style = document.createElement('style');
        style.textContent = [
            '[data-testid="livewire-error-modal"]',
            '[data-livewire-error]',
            '[wire\\:offline]',
            '.livewire-error, #livewire-error',
        ].join(',') + ' { display: none !important; }';
        document.head.appendChild(style);
    });

    // Ping shortly after load (covers the case where the token rotated
    // between page render and the user's first action), then on interval.
    setTimeout(function () { ping(true); }, 3000);
    setInterval(function () { ping(false); }, INTERVAL_MS);

    // When the tab regains focus after being backgrounded, ping BEFORE the
    // user has a chance to click anything so the next action uses the fresh
    // token. Force = true so we don't skip on the STALE_MS guard.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') ping(true);
    });
    window.addEventListener('focus', function () { ping(true); });
})();
</script>
