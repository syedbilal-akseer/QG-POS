<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (FCM) v1 sender.
 *
 * Self-contained: signs the OAuth JWT with `openssl_sign` and posts to Google's
 * token endpoint, then sends notifications via the FCM v1 REST API.
 * No composer dependency required.
 *
 * Both Android/iOS device tokens and Web Push (FCM JS) tokens are accepted —
 * the same /messages:send endpoint handles both.
 */
class FcmService
{
    protected ?array $serviceAccount = null;

    /**
     * Send a notification to a specific FCM token.
     *
     * @param  string $token  The device / browser FCM token
     * @param  string $title  Notification title
     * @param  string $body   Notification body
     * @param  array  $data   Custom data payload (string keys + string values only)
     * @return bool           True on success, false on failure
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): bool
    {
        if (!$token) {
            return false;
        }

        try {
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return false;
            }

            $endpoint = sprintf(config('firebase.fcm_endpoint'), config('firebase.project_id'));

            // FCM v1 requires all data values to be strings
            $stringData = array_map(fn($v) => (string) $v, $data);

            $payload = [
                'message' => [
                    'token'        => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data'    => $stringData,
                    'android' => [
                        'priority'     => 'high',
                        'notification' => ['sound' => 'default', 'click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
                    ],
                    'apns' => [
                        'payload' => ['aps' => ['sound' => 'default']],
                    ],
                    'webpush' => [
                        'headers' => ['Urgency' => 'high'],
                        'notification' => ['icon' => '/favicon.ico'],
                    ],
                ],
            ];

            // We run after the response is flushed (dispatch->afterResponse),
            // so a generous timeout is fine — it never delays the user.
            // Cold SSL handshakes from XAMPP / corporate networks can take
            // several seconds, hence 20s rather than the previous 5s.
            $response = Http::withToken($accessToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->timeout(20)
                ->connectTimeout(10)
                ->post($endpoint, $payload);

            if ($response->successful()) {
                return true;
            }

            $errBody = $response->json();
            $errCode = $errBody['error']['details'][0]['errorCode']
                    ?? $errBody['error']['status']
                    ?? null;

            // Token is dead — strip it from any user record using it
            if (in_array($errCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true)) {
                User::where('fcm_token', $token)->update([
                    'fcm_token' => null,
                    'fcm_token_updated_at' => now(),
                ]);
            }

            Log::warning('FCM send failed', [
                'token_suffix' => substr($token, -8),
                'status'       => $response->status(),
                'error'        => $errBody,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM send exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Send to a single user (no-op if user has no fcm_token).
     *
     * The actual HTTP call to Google is deferred via dispatchAfterResponse(),
     * so the caller's HTTP request returns to the client immediately and the
     * 2-5s FCM round-trip happens in a background PHP process. Returns true
     * to indicate "scheduled" — actual success is logged inside sendToToken().
     */
    public function sendToUser(?User $user, string $title, string $body, array $data = []): bool
    {
        if (!$user || !$user->fcm_token) {
            return false;
        }
        $token = $user->fcm_token;
        dispatch(function () use ($token, $title, $body, $data) {
            app(self::class)->sendToToken($token, $title, $body, $data);
        })->afterResponse();
        return true;
    }

    /**
     * Send to multiple users — same deferred pattern as sendToUser(). Returns
     * the count SCHEDULED (not the count successfully delivered).
     */
    public function sendToUsers($users, string $title, string $body, array $data = []): int
    {
        // Snapshot the tokens NOW so the deferred closure doesn't have to
        // re-query — and we don't capture full Eloquent models in the queue.
        $tokens = collect($users)
            ->pluck('fcm_token')
            ->filter()
            ->values()
            ->all();

        if (empty($tokens)) {
            return 0;
        }

        dispatch(function () use ($tokens, $title, $body, $data) {
            $svc = app(self::class);
            foreach ($tokens as $token) {
                $svc->sendToToken($token, $title, $body, $data);
            }
        })->afterResponse();

        return count($tokens);
    }

    // --------------------------------------------------------------------
    // OAuth (service-account JWT → Google access token)
    // --------------------------------------------------------------------

    /**
     * Get a cached OAuth access token, generating a new one if needed.
     */
    protected function getAccessToken(): ?string
    {
        $cacheKey = config('firebase.oauth_cache_key');
        $ttl      = config('firebase.oauth_cache_ttl');

        $cached = Cache::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $token = $this->fetchNewAccessToken();
        if ($token) {
            Cache::put($cacheKey, $token, $ttl);
        }
        return $token;
    }

    /**
     * Exchange a service-account-signed JWT for an OAuth access token.
     */
    protected function fetchNewAccessToken(): ?string
    {
        $sa = $this->loadServiceAccount();
        if (!$sa) {
            return null;
        }

        $now    = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claim  = [
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => $sa['token_uri'] ?? config('firebase.oauth_token_url'),
            'iat'   => $now,
            'exp'   => $now + 3600,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header));
        $encodedClaim  = $this->base64UrlEncode(json_encode($claim));
        $signingInput  = "{$encodedHeader}.{$encodedClaim}";

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $sa['private_key'], 'sha256WithRSAEncryption');

        if (!$ok) {
            Log::error('FCM JWT signing failed (openssl_sign returned false)');
            return null;
        }

        $jwt = "{$signingInput}." . $this->base64UrlEncode($signature);

        $resp = Http::asForm()
            ->timeout(15)
            ->connectTimeout(10)
            ->post(config('firebase.oauth_token_url'), [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

        if (!$resp->successful()) {
            Log::error('FCM OAuth token fetch failed', [
                'status' => $resp->status(),
                'body'   => $resp->body(),
            ]);
            return null;
        }

        return $resp->json('access_token');
    }

    /**
     * Load and parse the service account JSON file.
     */
    protected function loadServiceAccount(): ?array
    {
        if ($this->serviceAccount !== null) {
            return $this->serviceAccount;
        }

        $path = config('firebase.credentials_path');
        if (!is_file($path) || !is_readable($path)) {
            Log::error('Firebase credentials file missing', ['path' => $path]);
            return null;
        }

        $json = json_decode(file_get_contents($path), true);
        if (!is_array($json) || empty($json['private_key']) || empty($json['client_email'])) {
            Log::error('Firebase credentials file is malformed', ['path' => $path]);
            return null;
        }

        return $this->serviceAccount = $json;
    }

    protected function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
