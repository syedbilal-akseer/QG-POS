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
        // Check if the user is authenticated and has the required role or is an admin
        if (Auth::check() && (Auth::user()->role->name === $role || Auth::user()->role->name === 'admin' || Auth::user()->role->name === 'hod' || Auth::user()->role->name === 'line-manager')) {
            return $next($request); // Proceed to the next middleware/route
        }
    
        // If the user does not have access, return a response
        // Option 1: Redirect to the home page or login page
        return redirect('/login'); // Or any other page, e.g., '/'
    
        // Option 2: Return a JSON response with an error message
        // return response()->json([
        //     'error' => 'Unauthorized',
        //     'message' => 'You do not have the required role to access this resource.',
        //     'status' => 403,
        // ], 403);
    }
    
}
