<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'latest_version',
        'min_supported_version',
        'store_url',
        'force_update_message',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function updater() { return $this->belongsTo(User::class, 'updated_by'); }

    /**
     * Cached lookup for the middleware (per-platform).
     * Bumped whenever an admin saves a new version row.
     */
    public static function forPlatform(string $platform): ?self
    {
        $platform = strtolower(trim($platform));
        if ($platform === '') return null;

        return Cache::remember(
            'app_version_active_' . $platform,
            now()->addMinutes(10),
            fn () => static::query()
                ->where('platform', $platform)
                ->where('is_active', true)
                ->first()
        );
    }

    public static function flushCacheFor(string $platform): void
    {
        Cache::forget('app_version_active_' . strtolower(trim($platform)));
    }
}
