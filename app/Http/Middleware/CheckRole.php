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

        $userRole = Auth::user()->role;

        // Check if user has one of the required roles
        if (in_array($userRole, $roles)) {
            return $next($request);
        }

        // Always allow these privileged roles access
        $privilegedRoles = ['admin', 'hod', 'line-manager', 'sales-head', 'cmd-khi', 'price-uploads', 'scm-lhr', 'cmd-lhr', 'supply-chain'];
        if (in_array($userRole, $privilegedRoles)) {
            return $next($request);
        }

        // Redirect unauthorized users
        return redirect('/login');
    }
}