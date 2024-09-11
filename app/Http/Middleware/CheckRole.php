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
     * @param  string  $role
     */
    public function handle(Request $request, Closure $next, $role): Response
    {
        if (Auth::check() && (Auth::user()->role->value === $role || Auth::user()->role->value === 'admin')) {
            return $next($request);
        }

        return redirect('/');

        return response()->json([
            'error' => 'Unauthorized',
           'message' => 'You do not have the required role to access this resource.',
           'status' => 403,
        ], 403);
    }
}
