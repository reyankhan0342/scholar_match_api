<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentCv;
use App\Models\StudentEducation;
use App\Models\StudentProfile;
use App\Models\StudentResearchProfile;
use App\Models\StudentResearchProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AccountSettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => [
                'sometimes', 'required', 'string',
                'min:2', 'max:255',
            ],
            'email' => [
                'sometimes', 'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'current_password' => [
                'sometimes', 'string',
            ],
        ]);

        if (
            !array_key_exists('name', $data) &&
            !array_key_exists('email', $data)
        ) {
            throw ValidationException::withMessages([
                'name' => ['Provide a name or email to update.'],
            ]);
        }

        $emailChanged = isset($data['email'])
            && $data['email'] !== $user->email;

        if ($emailChanged) {
            $this->verifyPassword(
                $user,
                $data['current_password'] ?? null
            );

            $user->email = $data['email'];
            $user->email_verified_at = null;
        }

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }

        $user->save();

        if ($emailChanged) {
            $user->tokens()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => $emailChanged
                ? 'Account updated. Please log in again and verify the new email when email verification is configured.'
                : 'Account information updated successfully.',
            'data' => [
                'account' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'login_required' => $emailChanged,
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => [
                'required', 'string',
            ],
            'password' => [
                'required', 'string',
                'min:8', 'max:255',
                'confirmed',
                'different:current_password',
            ],
        ]);

        $user = $request->user();

        $this->verifyPassword(
            $user,
            $data['current_password']
        );

        $user->password = Hash::make($data['password']);
        $user->save();

        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password changed. All API sessions revoked. Please log in again.',
        ]);
    }

    public function deletePersonalProfile(
        Request $request
    ): JsonResponse {
        $this->confirmReset($request);

        $deleted = StudentProfile::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Personal profile cleared. Account name and email were preserved.',
            'data' => [
                'removed' => $deleted > 0,
            ],
        ]);
    }

    public function deleteResearchProfile(
        Request $request
    ): JsonResponse {
        $this->confirmReset($request);

        $deleted = StudentResearchProfile::query()
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Research profile cleared. Research proposals were preserved.',
            'data' => [
                'removed' => $deleted > 0,
            ],
        ]);
    }

    public function deleteAccount(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'current_password' => [
                'required', 'string',
            ],
            'confirmation' => [
                'required',
                Rule::in(['DELETE MY ACCOUNT']),
            ],
        ]);

        $user = $request->user();

        $this->verifyPassword(
            $user,
            $data['current_password']
        );

        $userId = (int) $user->id;

        // Gather private file references before removing records.
        $paths = $this->collectDocumentPaths($userId);

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')
                    ->where('email', $user->email)
                    ->delete();
            }

            if (!$user->delete()) {
                throw new \RuntimeException(
                    'Account deletion failed.'
                );
            }
        });

        // Delete private files only after the database transaction succeeds.
        $cleanupComplete = true;
        $disk = Storage::disk('local');

        try {
            if (
                $paths !== [] &&
                !$disk->delete($paths)
            ) {
                $cleanupComplete = false;
            }

            $directories = [
                "student-cvs/{$userId}",
                "student-proposals/{$userId}",
            ];

            foreach ($directories as $directory) {
                if (
                    $disk->directoryExists($directory) &&
                    !$disk->deleteDirectory($directory)
                ) {
                    $cleanupComplete = false;
                }
            }
        } catch (Throwable $exception) {
            $cleanupComplete = false;

            Log::error(
                'Account deleted, but file cleanup failed.',
                [
                    'deleted_user_id' => $userId,
                    'exception' => $exception->getMessage(),
                ]
            );
        }

        if (!$cleanupComplete) {
            Log::warning(
                'Some deleted account files may need manual cleanup.',
                ['deleted_user_id' => $userId]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Account deleted. API tokens revoked.',
            'data' => [
                'local_file_cleanup_complete' => $cleanupComplete,
            ],
        ]);
    }

    private function confirmReset(Request $request): void
    {
        $data = $request->validate([
            'current_password' => [
                'required', 'string',
            ],
            'confirmation' => [
                'required',
                Rule::in(['RESET']),
            ],
        ]);

        $this->verifyPassword(
            $request->user(),
            $data['current_password']
        );
    }

    private function verifyPassword(
        $user,
        ?string $password
    ): void {
        if (
            !$password ||
            !Hash::check($password, $user->password)
        ) {
            throw ValidationException::withMessages([
                'current_password' => [
                    'Current password is incorrect or missing.',
                ],
            ]);
        }
    }

    private function collectDocumentPaths(
        int $userId
    ): array {
        $paths = [];

        $cv = StudentCv::query()
            ->where('user_id', $userId)
            ->first();

        if ($cv) {
            $this->addPaths(
                $paths,
                $cv->getAttributes()
            );
        }

        $proposals = StudentResearchProposal::query()
            ->where('user_id', $userId)
            ->get();

        foreach ($proposals as $proposal) {
            $this->addPaths(
                $paths,
                $proposal->getAttributes()
            );
        }

        $educations = StudentEducation::query()
            ->where('user_id', $userId)
            ->get();

        foreach ($educations as $education) {
            $this->addPaths(
                $paths,
                $education->getAttributes()
            );
        }

        // Check the known academic-document metadata table.
        if (
            Schema::hasTable('student_education_documents') &&
            $educations->isNotEmpty()
        ) {
            $foreignKey = null;

            foreach (
                ['student_education_id', 'education_id']
                as $candidate
            ) {
                if (
                    Schema::hasColumn(
                        'student_education_documents',
                        $candidate
                    )
                ) {
                    $foreignKey = $candidate;
                    break;
                }
            }

            if ($foreignKey === null) {
                throw new \RuntimeException(
                    'Check education document foreign key before account deletion.'
                );
            }

            $documents = DB::table(
                'student_education_documents'
            )
                ->whereIn(
                    $foreignKey,
                    $educations->pluck('id')->all()
                )
                ->get();

            foreach ($documents as $document) {
                $this->addPaths(
                    $paths,
                    (array) $document
                );
            }
        }

        return array_values(array_unique($paths));
    }

    private function addPaths(
        array &$paths,
        array $fields
    ): void {
        foreach ($fields as $key => $value) {
            if (
                !preg_match('/(^|_)path$/i', (string) $key) ||
                !is_string($value)
            ) {
                continue;
            }

            if (
                $value === '' ||
                str_starts_with($value, '/') ||
                str_contains($value, '..') ||
                str_contains($value, '\\') ||
                str_contains($value, '://')
            ) {
                continue;
            }

            $paths[] = $value;
        }
    }
}
