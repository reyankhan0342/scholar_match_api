<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentCv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StudentCvController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $cv = StudentCv::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Student CV retrieved successfully.',
            'data' => [
                'cv' => $cv,
                'has_cv' => $cv !== null,
            ],
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:pdf',
                'max:2048',
            ],
        ]);

        $user = $request->user();
        $file = $request->file('file');

        $cv = StudentCv::query()
            ->where('user_id', $user->id)
            ->first();

        $isNew = $cv === null;
        $oldPath = $cv?->file_path;

        $directory = 'student-cvs/' . $user->id;

        $newPath = $file->store(
            $directory,
            'local'
        );

        if ($newPath === false) {
            return response()->json([
                'success' => false,
                'message' => 'CV upload failed.',
            ], 500);
        }

        $originalName = basename(
            str_replace(
                '\\',
                '/',
                $file->getClientOriginalName()
            )
        );

        if (!$cv) {
            $cv = new StudentCv();
            $cv->user()->associate($user);
        }

        try {
            $cv->fill([
                'original_file_name' => mb_substr(
                    $originalName,
                    0,
                    255
                ),
                'file_path' => $newPath,
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);

            $cv->save();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json([
            'success' => true,
            'message' => $isNew
                ? 'Student CV uploaded successfully.'
                : 'Student CV replaced successfully.',
            'data' => [
                'cv' => $cv->fresh(),
                'has_cv' => true,
            ],
        ], $isNew ? 201 : 200);
    }

    public function download(
        Request $request
    ): StreamedResponse {
        $cv = $this->findCv($request);

        abort_unless(
            Storage::disk('local')->exists($cv->file_path),
            404,
            'CV document not found.'
        );

        return Storage::disk('local')->download(
            $cv->file_path,
            'student-cv-' . $request->user()->id . '.pdf',
            [
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        $cv = $this->findCv($request);

        $path = $cv->file_path;

        $cv->delete();

        Storage::disk('local')->delete($path);

        return response()->json([
            'success' => true,
            'message' => 'Student CV deleted successfully.',
        ]);
    }

    private function findCv(Request $request): StudentCv
    {
        return StudentCv::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }
}
