<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Rules\StrongPassword;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Update the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'new_password' => ['required', 'string', 'min:8', 'confirmed', new StrongPassword],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        // Check current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Password updated successfully.',
        ], 200);
    }

    /**
     * Update the user's profile photo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateProfilePhoto(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'profile_photo' => 'required|image|mimes:jpeg,png,jpg,webp,tiff|max:6144', // max size in KB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the authenticated user
        $user = Auth::user();

        // Handle the profile image upload
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            $filePath = 'profile_photos/';
            $fileName = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();

            // Store the file
            $file->storeAs($filePath, $fileName, 'public');

            // Optionally, delete the old profile image if it exists
            if ($user->profile_photo) {
                Storage::disk('public')->delete($user->profile_photo);
            }

            // Update the user's profile image path
            $user->update(['profile_photo' => $filePath . $fileName]);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Profile photo updated successfully.',
            'data' => [
                'profile_photo_url' => asset('storage/' . $user->profile_photo),
            ],
        ], 200);
    }

    /**
     * Update the user's current location (lat, lng, address).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user    = Auth::user();
        $newLat  = (float) $request->lat;
        $newLng  = (float) $request->lng;
        $address = $request->address;

        // Compare against the most recent history entry; fall back to the
        // current cached lat/lng on the users table if no history exists yet.
        $last = $user->locationHistories()->first();
        $oldLat = $last ? (float) $last->lat : ($user->lat !== null ? (float) $user->lat : null);
        $oldLng = $last ? (float) $last->lng : ($user->lng !== null ? (float) $user->lng : null);

        // Threshold: ~0.0001 degree ≈ 11 meters at the equator. Anything below
        // that is GPS jitter, not an actual move — don't log a new row.
        $threshold = 0.0001;
        $changed   = $oldLat === null || $oldLng === null
            || abs($oldLat - $newLat) > $threshold
            || abs($oldLng - $newLng) > $threshold;

        $created = false;
        if ($changed) {
            \App\Models\UserLocationHistory::create([
                'user_id' => $user->id,
                'lat'     => $newLat,
                'lng'     => $newLng,
                'address' => $address,
            ]);
            $created = true;
        }

        // Always keep users.lat/lng/address as the "current location" cache so
        // existing readers (ListUserLocations, dashboards) keep working.
        $user->update([
            'lat'     => $newLat,
            'lng'     => $newLng,
            'address' => $address,
        ]);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => $created
                ? 'New location logged.'
                : 'Location unchanged — history not duplicated.',
            'data' => [
                'lat'              => $user->lat,
                'lng'              => $user->lng,
                'address'          => $user->address,
                'history_inserted' => $created,
            ],
        ], 200);
    }

    /**
     * Register / update the authenticated user's FCM (Firebase Cloud Messaging) token.
     * Mobile and web clients call this whenever the device token changes
     * (first launch, reinstall, browser permission grant, token refresh).
     *
     * POST /api/profile/fcm-token
     * Body: { "fcm_token": "<token string>" }
     */
    public function updateFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|min:10|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Invalid token.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $user->update([
            'fcm_token'            => $request->input('fcm_token'),
            'fcm_token_updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'FCM token saved.',
        ], 200);
    }
}
