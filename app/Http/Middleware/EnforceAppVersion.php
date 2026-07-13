<?php

namespace App\Http\Middleware;

use App\Models\AppVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reject authenticated API calls coming from outdated mobile clients.
 *
 * Mobile sends a single header on every authenticated call:
 *   X-App-Version — semver-ish string like '1.0.3' or '1.2.0.4'
 *
 * Behaviour:
 *   • Missing X-App-Version → request is allowed through (lenient mode so
 *     legacy builds keep working). Drop the early return to enforce
 *     strictly.
 *   • No active rows in app_versions → allowed.
 *   • Client version < the LOWEST active min_supported_version (taken
 *     across all rows in app_versions, regardless of platform) → 426
 *     Upgrade Required with a structured payload the mobile can use to
 *     drive a forced-update screen.
 */
class EnforceAppVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = trim((string) $request->header('X-App-Version', ''));

        // Legacy clients that don't send the header yet still work.
        if ($version === '') {
            return $next($request);
        }

        $rows = AppVersion::query()->where('is_active', true)->get();
        if ($rows->isEmpty()) {
            return $next($request);
        }

        // Pick the row with the LOWEST min_supported_version. Comparing the
        // client against the most-lenient gate means we never over-block
        // when the system has multiple per-platform rows but the mobile
        // doesn't tell us which platform it is.
        $row = $rows->sortBy(function ($r) {
            // version_compare-aware sort: pad each segment for natural ordering.
            return implode('.', array_map(
                fn ($s) => str_pad($s, 6, '0', STR_PAD_LEFT),
                explode('.', $r->min_supported_version)
            ));
        })->first();

        // version_compare understands "1.0.3" vs "1.0.10" correctly and
        // tolerates 1–4 segment numerics like "1.2", "1.2.3", "1.2.3.4".
        if (version_compare($version, $row->min_supported_version, '<')) {
            return response()->json([
                'success' => false,
                'status'  => 426, // Upgrade Required
                'code'    => 'APP_UPDATE_REQUIRED',
                'message' => $row->force_update_message
                    ?: 'Please install the latest version of the app to continue.',
                'data'    => [
                    'current_version'       => $version,
                    'latest_version'        => $row->latest_version,
                    'min_supported_version' => $row->min_supported_version,
                    'store_url'             => $row->store_url,
                    'force_update'          => true,
                ],
            ], 426);
        }

        return $next($request);
    }
}
