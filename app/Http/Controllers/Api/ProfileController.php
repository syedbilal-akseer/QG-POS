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
}
