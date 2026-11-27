<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentResearchProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentResearchProposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $proposals = StudentResearchProposal::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Research proposals retrieved successfully.',
            'data' => [
                'proposals' => $proposals,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            $this->rules(true)
        );

        $proposal = new StudentResearchProposal($validated);

        $proposal->user()->associate($request->user());
        $proposal->save();

        return response()->json([
            'success' => true,
            'message' => 'Research proposal created successfully.',
            'data' => [
                'proposal' => $proposal->withoutRelations(),
            ],
        ], 201);
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $proposal = $this->findProposal($request, $id);

        return response()->json([
            'success' => true,
            'message' => 'Research proposal retrieved successfully.',
            'data' => [
                'proposal' => $proposal,
            ],
        ]);
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $proposal = $this->findProposal($request, $id);

        $validated = $request->validate(
            $this->rules(false)
        );

        $proposal->fill($validated);
        $proposal->save();

        return response()->json([
            'success' => true,
            'message' => 'Research proposal updated successfully.',
            'data' => [
                'proposal' => $proposal->fresh(),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $proposal = $this->findProposal($request, $id);

        $documentPath = $proposal->document_file_path;

        $proposal->delete();

        if ($documentPath) {
            Storage::disk('local')->delete($documentPath);
        }

        return response()->json([
            'success' => true,
            'message' => 'Research proposal deleted successfully.',
        ]);
    }

    private function findProposal(
        Request $request,
        int $id
    ): StudentResearchProposal {
        return StudentResearchProposal::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function rules(bool $creating): array
    {
        return [
            'title' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],

            'abstract' => [
                'sometimes', 'nullable', 'string', 'max:10000',
            ],

            'research_field' => [
                'sometimes', 'nullable', 'string', 'max:255',
            ],

            'keywords' => [
                'sometimes', 'nullable', 'array', 'max:30',
            ],

            'keywords.*' => [
                'required', 'string', 'max:100',
            ],

            'research_objectives' => [
                'sometimes', 'nullable', 'string', 'max:10000',
            ],

            'research_methodology' => [
                'sometimes', 'nullable', 'string', 'max:10000',
            ],

            'expected_outcomes' => [
                'sometimes', 'nullable', 'string', 'max:10000',
            ],

            'target_degree' => [
                'sometimes', 'nullable', 'string', 'max:100',
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in(['draft', 'ready', 'archived']),
            ],

            'user_id' => ['prohibited'],
            'document_file_path' => ['prohibited'],
            'original_file_name' => ['prohibited'],
            'mime_type' => ['prohibited'],
            'file_size' => ['prohibited'],
        ];
    }
}
