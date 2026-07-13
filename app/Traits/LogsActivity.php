<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

trait LogsActivity
{
    /**
     * Log a user activity.
     */
    public function logActivity(string $module, string $action, string $description, array $properties = [], $user = null)
    {
        $logUser = $user ?? auth()->user();

        ActivityLog::create([
            'user_id' => $logUser?->id,
            'user_name' => $logUser?->name ?? 'Guest',
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'ip_address' => Request::ip(),
            'properties' => $properties,
        ]);
    }
}
