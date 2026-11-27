<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use App\Models\StudentEducation;
use App\Models\StudentResearchProfile;
use App\Models\StudentResearchProposal;
use App\Models\StudentCv;
use App\Models\StudentWorkExperience;
use App\Models\StudentProject;
use App\Models\StudentCertification;
use App\Models\StudentPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $personalProfile = StudentProfile::query()
            ->where('user_id', $userId)
            ->first();

        $educations = StudentEducation::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        $researchProfile = StudentResearchProfile::query()
            ->where('user_id', $userId)
            ->first();

        $researchProposals = StudentResearchProposal::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        $cv = StudentCv::query()
            ->where('user_id', $userId)
            ->first();

        $workExperiences = StudentWorkExperience::query()
            ->where('user_id', $userId)
            ->orderByDesc('start_date')
            ->get();

        $projects = StudentProject::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        $certifications = StudentCertification::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        $publications = StudentPublication::query()
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Complete student profile retrieved successfully.',
            'data' => [
                'account' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],

                'personal_profile' => $this->safeRecord(
                    $personalProfile
                ),

                'educations' => $this->safeCollection(
                    $educations
                ),

                'research_profile' => $this->safeRecord(
                    $researchProfile
                ),

                'research_proposals' => $this->safeCollection(
                    $researchProposals
                ),

                'cv' => $this->safeRecord($cv),

                'work_experiences' => $this->safeCollection(
                    $workExperiences
                ),

                'projects' => $this->safeCollection(
                    $projects
                ),

                'certifications' => $this->safeCollection(
                    $certifications
                ),

                'publications' => $this->safeCollection(
                    $publications
                ),

                'summary' => [
                    'has_personal_profile' => $personalProfile !== null,
                    'has_research_profile' => $researchProfile !== null,
                    'has_cv' => $cv !== null,
                    'educations_count' => $educations->count(),
                    'research_proposals_count' => $researchProposals->count(),
                    'work_experiences_count' => $workExperiences->count(),
                    'projects_count' => $projects->count(),
                    'certifications_count' => $certifications->count(),
                    'publications_count' => $publications->count(),
                ],
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function safeRecord(?Model $record): ?array
    {
        if ($record === null) {
            return null;
        }

        $data = $record->toArray();

        foreach (array_keys($data) as $key) {
            if (preg_match(
                '/(^|_)(password|token|secret|path)(_|$)/i',
                (string) $key
            )) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    private function safeCollection(
        EloquentCollection $records
    ): array {
        return $records
            ->map(fn (Model $record) => $this->safeRecord($record))
            ->values()
            ->all();
    }
}
