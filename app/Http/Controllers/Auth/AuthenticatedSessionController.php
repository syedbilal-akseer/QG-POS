<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Traits\LogsActivity;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    use LogsActivity;
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Role-based post-login landing. Roles NOT listed here fall through
        // to the default dashboard redirect at the bottom. Keep this list in
        // sync with the `/` route's landing table in routes/web.php —
        // otherwise a user who hits `/` after login sees a different page
        // than the one login sent them to.
        $userRole = $user->role?->name ?? $user->role;

        // isSalesHead() must be checked before the 'user' role match below —
        // it covers 4 hardcoded email overrides (see User::isSalesHead())
        // whose stored role is 'user' but who must land on the dashboard,
        // same precedence the `/` route's landing table uses.
        if (method_exists($user, 'isSalesHead') && $user->isSalesHead()) {
            $landing = route('dashboard');
        } else {
            $landing = match ($userRole) {
                'supply-chain'      => route('orders.supply-chain.all'),
                'scm-lhr'           => route('orders.scm-lhr.all'),
                'inventory-manager' => route('wms.locations'),
                'invoice-manager'   => route('invoices.view'),
                // 'user' (salesperson) isn't allowed on /dashboard, so
                // without an explicit landing here it fell through to the
                // default dashboard redirect below and infinite-looped:
                // dashboard rejects the role -> redirects to /login -> guest
                // middleware bounces an already-authenticated user back to
                // dashboard.
                'user'              => route('monthlyTourPlans.all'),
                default             => null,
            };
        }

        // Log Activity
        $this->logActivity('Auth', 'login', "User {$user->name} logged in.", [], $user);

        if ($landing) {
            return redirect()->to($landing);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if ($user) {
            $this->logActivity('Auth', 'logout', "User {$user->name} logged out.");
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
