<?php

namespace App\Http\Controllers\Api\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Traits\LogsActivity;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use LogsActivity;
    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'       => 'required|email',
            'password'    => 'required',
            'fcm_token'   => 'nullable|string|min:10|max:4096',
            'app_version' => 'nullable|string|max:50',
        ]);

        // Check if the email is the bot user's email
        if ($request->input('email') === 'bot@ts.koreintl.com') {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Persist FCM token at login so push notifications work for this device
        // immediately, without requiring a separate /profile/fcm-token call.
        if ($fcmToken = $request->input('fcm_token')) {
            $user->update([
                'fcm_token'            => $fcmToken,
                'fcm_token_updated_at' => now(),
            ]);
        }

        $token      = $user->createToken('QG_APP')->plainTextToken;
        $appVersion = $request->input('app_version');

        // Include app_version in the description so it's visible at a glance in
        // the activity log table, and in properties for structured filtering.
        $description = "Mobile App: User {$user->name} logged in via API"
            . ($appVersion ? " (v{$appVersion})" : '')
            . '.';

        $this->logActivity('Auth', 'login', $description, [
            'id'            => $user->id,
            'app_version'   => $appVersion,
            'fcm_registered'=> (bool) $fcmToken,
        ], $user);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Login successful',
            'data' => [
                'access_token' => $token,
                'token_type'   => 'Bearer',
                'app_version'  => $appVersion,
            ],
        ], 200);
    }

    /**
     * Log the user out of the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $this->logActivity('Auth', 'logout', "Mobile App: User {$user->name} logged out via API.", ['id' => $user->id]);
        }
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Logged out successfully',
        ], 200);
    }
}
