<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = StudentProfile::where(
            'user_id',
            $user->id
        )->first();

        return response()->json([
            'success' => true,
            'message' => 'Student profile retrieved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'profile' => $profile,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'gender' => ['nullable', 'string', 'max:30'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'permanent_address' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'bio' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();

        $profile = StudentProfile::where(
            'user_id',
            $user->id
        )->first();

        if (!$profile) {
            $profile = new StudentProfile();
            $profile->user()->associate($user);
        }

        $profile->fill($validated);
        $profile->save();

        return response()->json([
            'success' => true,
            'message' => 'Student profile saved successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'profile' => $profile->fresh(),
            ],
        ]);
    }
}
