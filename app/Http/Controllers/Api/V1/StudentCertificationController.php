<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentCertification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StudentCertificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $certifications = StudentCertification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Certifications retrieved successfully.',
            'data' => [
                'certifications' => $certifications,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateCertification($request);

        $certification = new StudentCertification($validated);

        $certification->user()->associate($request->user());

        $certification->save();

        return response()->json([
            'success' => true,
            'message' => 'Certification created successfully.',
            'data' => [
                'certification' => $certification->fresh(),
            ],
        ], 201);
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $certification = $this->findCertification($request, $id);

        return response()->json([
            'success' => true,
            'message' => 'Certification retrieved successfully.',
            'data' => [
                'certification' => $certification,
            ],
        ]);
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $certification = $this->findCertification($request, $id);

        $validated = $this->validateCertification(
            $request,
            $certification
        );

        $certification->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Certification updated successfully.',
            'data' => [
                'certification' => $certification->fresh(),
            ],
        ]);
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $certification = $this->findCertification($request, $id);

        $certification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Certification deleted successfully.',
        ]);
    }

    private function findCertification(
        Request $request,
        int $id
    ): StudentCertification {
        return StudentCertification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);
    }

    private function validateCertification(
        Request $request,
        ?StudentCertification $certification = null
    ): array {
        $creating = $certification === null;

        $validator = Validator::make($request->all(), [
            'title' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],

            'issuing_organization' => $creating
                ? ['required', 'string', 'max:255']
                : ['sometimes', 'required', 'string', 'max:255'],

            'credential_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'credential_url' => [
                'sometimes',
                'nullable',
                'url:http,https',
                'max:500',
            ],

            'issue_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],

            'expiry_date' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d',
            ],

            'does_not_expire' => [
                'sometimes',
                'boolean',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:10000',
            ],

            'user_id' => [
                'prohibited',
            ],
        ]);

        $validator->after(function ($validator) use (
            $request,
            $certification
        ) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $doesNotExpire = $request->exists('does_not_expire')
                ? $request->boolean('does_not_expire')
                : (bool) ($certification?->does_not_expire ?? false);

            if ($doesNotExpire) {
                return;
            }

            $issueDate = $request->exists('issue_date')
                ? $request->input('issue_date')
                : $certification?->issue_date?->format('Y-m-d');

            $expiryDate = $request->exists('expiry_date')
                ? $request->input('expiry_date')
                : $certification?->expiry_date?->format('Y-m-d');

            if (
                $issueDate !== null &&
                $expiryDate !== null &&
                $expiryDate < $issueDate
            ) {
                $validator->errors()->add(
                    'expiry_date',
                    'Expiry date cannot be before issue date.'
                );
            }
        });

        $validated = $validator->validate();

        $doesNotExpire = $validated['does_not_expire']
            ?? $certification?->does_not_expire
            ?? false;

        if ($doesNotExpire) {
            $validated['expiry_date'] = null;
        }

        return $validated;
    }
}
