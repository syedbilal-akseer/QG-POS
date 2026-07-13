<?php

namespace App\Http\Controllers;

use App\Models\AppVersion;
use Illuminate\Http\Request;

/**
 * Admin page to manage the minimum app version mobile clients are allowed to
 * use. The /api/* tree's EnforceAppVersion middleware consults the row this
 * page edits — bumping `min_supported_version` immediately starts rejecting
 * older clients with a 426 + the configured force-update message.
 */
class AppVersionController extends Controller
{
    public function index()
    {
        // Single shared version row — same app on Android & iOS, not on
        // any store. We seed the legacy 'android' row as the canonical one
        // and keep the table column structure so the EnforceAppVersion
        // middleware (and a possible future per-platform split) continues
        // to work unchanged.
        $version = $this->canonicalRow();

        return view('admin.app-versions.index', [
            'version' => $version,
        ]);
    }

    /**
     * Update the single shared version. Whatever the admin enters is used
     * for BOTH latest_version and min_supported_version (so old clients are
     * blocked the moment a new version is published) and is mirrored to
     * every row in the table so the middleware picks it up regardless of
     * which platform header (or none) the client sends.
     */
    public function update(Request $request, AppVersion $appVersion)
    {
        $data = $request->validate([
            'latest_version' => 'required|string|max:32|regex:/^\d+(\.\d+){0,3}$/',
        ]);

        $v = $data['latest_version'];

        // Mirror to every row so the "lowest active min" the middleware
        // picks always lands on this value, regardless of whether the
        // client sends X-App-Platform or not.
        AppVersion::query()->update([
            'latest_version'        => $v,
            'min_supported_version' => $v,
            'is_active'             => true,
            'updated_by'            => auth()->id(),
            'updated_at'            => now(),
        ]);

        // Flush every cached row.
        foreach (AppVersion::pluck('platform') as $p) {
            AppVersion::flushCacheFor($p);
        }

        notify("App version set to {$v}. Older clients will be required to update on their next API call.", 'success');
        return redirect()->route('app-versions.index');
    }

    /**
     * Returns the one row the admin form binds to. Creates / promotes one
     * if the table is empty so the form always has something to render.
     */
    protected function canonicalRow(): AppVersion
    {
        $row = AppVersion::orderBy('id')->first();
        if (!$row) {
            $row = AppVersion::create([
                'platform'              => 'all',
                'latest_version'        => '1.0.0',
                'min_supported_version' => '1.0.0',
                'is_active'             => true,
            ]);
        }
        return $row->fresh(['updater']);
    }
}
