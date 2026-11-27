<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentEducation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentEducationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $educations = StudentEducation::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('completion_year')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Academic records retrieved successfully.',
            'data' => [
                'educations' => $educations,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules(true));

        $education = new StudentEducation($validated);
        $education->user()->associate($request->user());
        $education->save();

        return response()->json([
            'success' => true,
            'message' => 'Academic record created successfully.',
            'data' => [
                'education' => $education,
            ],
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $education = $this->findEducation($request, $id);

        return response()->json([
            'success' => true,
            'message' => 'Academic record retrieved successfully.',
            'data' => [
                'education' => $education,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $education = $this->findEducation($request, $id);

        $validated = $request->validate($this->rules(false));

        $education->fill($validated);
        $education->save();

        return response()->json([
            'success' => true,
            'message' => 'Academic record updated successfully.',
            'data' => [
                'education' => $education->fresh(),
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $education = $this->findEducation($request, $id);

        $education->delete();

        return response()->json([
            'success' => true,
            'message' => 'Academic record deleted successfully.',
        ]);
    }

    private function findEducation(
        Request $request,
        int $id
    ): StudentEducation {
        return StudentEducation::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'qualification_level' => [
                $required, 'string', 'max:100',
            ],
            'qualification_full_name' => [
                'sometimes', 'nullable', 'string', 'max:255',
            ],
            'degree_title' => [
                'sometimes', 'nullable', 'string', 'max:255',
            ],
            'field_of_study' => [
                'sometimes', 'nullable', 'string', 'max:200',
            ],
            'specialization' => [
                'sometimes', 'nullable', 'string', 'max:200',
            ],
            'institution_name' => [
                $required, 'string', 'max:255',
            ],
            'awarding_body' => [
                'sometimes', 'nullable', 'string', 'max:255',
            ],
            'country' => [
                'sometimes', 'nullable', 'string', 'max:100',
            ],
            'city' => [
                'sometimes', 'nullable', 'string', 'max:100',
            ],
            'start_year' => [
                'sometimes', 'nullable', 'integer', 'between:1900,2100',
            ],
            'completion_year' => [
                'sometimes', 'nullable', 'integer', 'between:1900,2100',
            ],
            'is_current' => [
                'sometimes', 'boolean',
            ],
            'degree_status' => [
                'sometimes', 'nullable', 'string', 'max:50',
            ],
            'grading_system' => [
                'sometimes', 'nullable', 'string', 'max:50',
            ],
            'cgpa' => [
                'sometimes', 'nullable', 'numeric', 'between:0,100',
            ],
            'cgpa_scale' => [
                'sometimes', 'nullable', 'numeric', 'gt:0', 'lte:100',
            ],
            'percentage' => [
                'sometimes', 'nullable', 'numeric', 'between:0,100',
            ],
            'obtained_marks' => [
                'sometimes', 'nullable', 'integer', 'min:0',
            ],
            'total_marks' => [
                'sometimes', 'nullable', 'integer', 'min:0',
            ],
            'grade' => [
                'sometimes', 'nullable', 'string', 'max:50',
            ],
            'description' => [
                'sometimes', 'nullable', 'string', 'max:5000',
            ],
            'user_id' => ['prohibited'],
            'degree_file_path' => ['prohibited'],
            'transcript_file_path' => ['prohibited'],
        ];
    }
}
