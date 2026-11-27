<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentPublication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentPublicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $publications = StudentPublication::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('publication_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Publications retrieved successfully.',
            'data' => [
                'publications' => $publications,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->rules(true)
        );

        $publication = new StudentPublication($validated);

        $publication->user()->associate(
            $request->user()
        );

        $publication->save();

        return response()->json([
            'success' => true,
            'message' => 'Publication created successfully.',
            'data' => [
                'publication' => $publication->fresh(),
            ],
        ], 201);
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $publication = $this->findPublication(
            $request,
            $id
        );

        return response()->json([
            'success' => true,
            'message' => 'Publication retrieved successfully.',
            'data' => [
                'publication' => $publication,
            ],
        ]);
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $publication = $this->findPublication(
            $request,
            $id
        );

        $validated = $request->validate(
            $this->rules(false)
        );

        $publication->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Publication updated successfully.',
            'data' => [
                'publication' => $publication->fresh(),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $publication = $this->findPublication(
            $request,
            $id
        );

        $publication->delete();

        return response()->json([
            'success' => true,
            'message' => 'Publication deleted successfully.',
        ]);
    }

    private function findPublication(
        Request $request,
        int $id
    ): StudentPublication {
        return StudentPublication::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function rules(bool $creating): array
    {
        return [
            'title' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],

            'publication_type' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'journal_or_conference' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'publisher' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'authors' => [
                'sometimes',
                'nullable',
                'array',
                'max:30',
            ],

            'authors.*' => [
                'required',
                'string',
                'max:255',
            ],

            'abstract' => [
                'sometimes',
                'nullable',
                'string',
                'max:20000',
            ],

            'keywords' => [
                'sometimes',
                'nullable',
                'array',
                'max:30',
            ],

            'keywords.*' => [
                'required',
                'string',
                'max:100',
            ],

            'research_field' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'doi' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'publication_url' => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:500',
            ],

            'publication_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],

            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'draft',
                    'submitted',
                    'under_review',
                    'accepted',
                    'published',
                    'rejected',
                ]),
            ],

            'user_id' => [
                'prohibited',
            ],
        ];
    }
}
