<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentWorkExperience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentWorkExperienceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $experiences = StudentWorkExperience::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('start_date')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Work experiences retrieved successfully.',
            'data' => [
                'experiences' => $experiences,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateExperience($request);

        $experience = new StudentWorkExperience($validated);
        $experience->user()->associate($request->user());
        $experience->save();

        return response()->json([
            'success' => true,
            'message' => 'Work experience created successfully.',
            'data' => [
                'experience' => $experience->fresh(),
            ],
        ], 201);
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $experience = $this->findExperience($request, $id);

        return response()->json([
            'success' => true,
            'message' => 'Work experience retrieved successfully.',
            'data' => [
                'experience' => $experience,
            ],
        ]);
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $experience = $this->findExperience($request, $id);

        $validated = $this->validateExperience(
            $request,
            $experience
        );

        $experience->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Work experience updated successfully.',
            'data' => [
                'experience' => $experience->fresh(),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $experience = $this->findExperience($request, $id);

        $experience->delete();

        return response()->json([
            'success' => true,
            'message' => 'Work experience deleted successfully.',
        ]);
    }

    private function findExperience(
        Request $request,
        int $id
    ): StudentWorkExperience {
        return StudentWorkExperience::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function validateExperience(
        Request $request,
        ?StudentWorkExperience $experience = null
    ): array {
        $creating = $experience === null;

        $validator = Validator::make($request->all(), [
            'job_title' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],

            'company_name' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],

            'employment_type' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'location' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'start_date' => $creating
                ? ['required', 'date_format:Y-m-d']
                : ['sometimes', 'required', 'date_format:Y-m-d'],

            'end_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],

            'is_current' => [
                'sometimes',
                'boolean',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],

            'user_id' => ['prohibited'],
        ]);

        $validator->after(function ($validator) use (
            $request,
            $experience
        ) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $isCurrent = $request->exists('is_current')
                ? $request->boolean('is_current')
                : (bool) ($experience?->is_current ?? false);

            if ($isCurrent) {
                return;
            }

            $startDate = $request->input(
                'start_date',
                $experience?->start_date?->format('Y-m-d')
            );

            $endDate = $request->exists('end_date')
                ? $request->input('end_date')
                : $experience?->end_date?->format('Y-m-d');

            if (
                $startDate !== null &&
                $endDate !== null &&
                $endDate < $startDate
            ) {
                $validator->errors()->add(
                    'end_date',
                    'End date cannot be before start date.'
                );
            }
        });

        $validated = $validator->validate();

        $isCurrent = $validated['is_current']
            ?? $experience?->is_current
            ?? false;

        if ($isCurrent) {
            $validated['end_date'] = null;
        }

        return $validated;
    }
}
