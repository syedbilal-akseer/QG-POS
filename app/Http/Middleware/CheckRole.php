<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Multiple roles can be passed
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        $userRole = $user->role_name;

        // 1. Direct role-string match against the required list (primary role)
        if ($userRole && in_array($userRole, $roles)) {
            return $next($request);
        }

        // 2. Additional roles (JSON) — secondary roles assigned to the user
        //    that grant the SAME access as if they were the primary role.
        //    Used for view-only roles like 'view-khi', 'view-all', etc., and
        //    for combining work-roles (e.g. salesperson with admin view).
        if (method_exists($user, 'getAdditionalRoles')) {
            foreach ($user->getAdditionalRoles() as $extra) {
                if (in_array($extra, $roles, true)) {
                    return $next($request);
                }
            }
        }

        // 3. Method-based check — lets per-user email overrides on
        //    is{Role}() helpers (e.g. isSalesHead) grant access even when
        //    the user's role attribute doesn't match the required string.
        //    'sales-head' → isSalesHead, 'cmd-khi' → isCmdKhi, etc.
        foreach ($roles as $role) {
            $method = 'is' . str_replace(' ', '', ucwords(str_replace('-', ' ', $role)));
            if (method_exists($user, $method) && $user->{$method}()) {
                return $next($request);
            }
        }

        // 3. Always allow these privileged roles access
        $privilegedRoles = ['admin', 'hod', 'line-manager', 'sales-head', 'cmd-khi', 'price-uploads', 'scm-lhr', 'cmd-lhr', 'supply-chain'];
        if ($userRole && in_array($userRole, $privilegedRoles)) {
            return $next($request);
        }

        // Redirect unauthorized users
        return redirect('/login');
    }
}