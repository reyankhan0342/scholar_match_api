<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\StudentProfileController;
use App\Http\Controllers\Api\V1\StudentEducationController;
use App\Http\Controllers\Api\V1\StudentEducationDocumentController;
use App\Http\Controllers\Api\V1\StudentResearchProfileController;
use App\Http\Controllers\Api\V1\StudentResearchProposalController;
use App\Http\Controllers\Api\V1\StudentResearchProposalDocumentController;
use App\Http\Controllers\Api\V1\StudentCvController;
use App\Http\Controllers\Api\V1\StudentWorkExperienceController;
use App\Http\Controllers\Api\V1\StudentProjectController;
use App\Http\Controllers\Api\V1\StudentCertificationController;
use App\Http\Controllers\Api\V1\StudentPublicationController;
use App\Http\Controllers\Api\V1\MyProfileController;
use App\Http\Controllers\Api\V1\AccountSettingsController;
use App\Http\Controllers\Api\V1\GmailConnectionController;
use App\Http\Controllers\Api\V1\OutreachController;
use App\Http\Controllers\Api\V1\AiDraftController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'ScholarMatch API is running',
    ]);
});

/*
|--------------------------------------------------------------------------
| Default Authenticated User
|--------------------------------------------------------------------------
*/

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| ScholarMatch API Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    // The mobile client calls /api/v1/health, not the legacy /api/health.
    Route::get('/health', function () {
        return response()->json(['success' => true,
            'message' => 'ScholarMatch API is running',
            'data' => ['status' => 'ok']]);
    });

    // Public OAuth return URL; protected by one-time, short-lived state token.
    Route::get('/gmail/callback', [GmailConnectionController::class, 'callback'])
        ->middleware('throttle:20,1');


    /*
    |--------------------------------------------------------------------------
    | Authentication Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('auth')->group(function () {

        // Register and send email OTP
        Route::post('/register', [
            AuthController::class,
            'register',
        ])->middleware('throttle:5,1');

        // Check credentials and send email OTP
        Route::post('/login', [
            AuthController::class,
            'login',
        ])->middleware('throttle:10,1');

        // Verify OTP and issue Sanctum access token
        Route::post('/verify-otp', [
            AuthController::class,
            'verifyOtp',
        ])->middleware('throttle:15,1');

        // Resend verification OTP
        Route::post('/resend-otp', [
            AuthController::class,
            'resendOtp',
        ])->middleware('throttle:3,1');

        /*
        |--------------------------------------------------------------------------
        | Authenticated Authentication Routes
        |--------------------------------------------------------------------------
        */

        Route::middleware('auth:sanctum')->group(function () {

            // Current authenticated user
            Route::get('/me', [
                AuthController::class,
                'me',
            ]);

            // Logout current session
            Route::post('/logout', [
                AuthController::class,
                'logout',
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Protected Student Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function () {
        // AI draft generation is opt-in and never auto-sends.
        Route::post('/ai/email-draft', [AiDraftController::class, 'email'])
            ->middleware('throttle:5,1');

        // Student Gmail account authorization (no SMTP impersonation).
        Route::get('/gmail/status', [GmailConnectionController::class, 'status']);
        Route::post('/gmail/connect', [GmailConnectionController::class, 'connect'])
            ->middleware('throttle:5,1');
        Route::delete('/gmail/disconnect', [GmailConnectionController::class, 'disconnect']);

        // User-owned drafts, sending, and manual reply tracking.
        Route::get('/outreach', [OutreachController::class, 'index']);
        Route::post('/outreach', [OutreachController::class, 'store']);
        Route::put('/outreach/{id}', [OutreachController::class, 'update'])->whereNumber('id');
        Route::delete('/outreach/{id}', [OutreachController::class, 'destroy'])->whereNumber('id');
        Route::post('/outreach/{id}/send', [OutreachController::class, 'send'])
            ->whereNumber('id')->middleware('throttle:10,1');
        Route::post('/outreach/{id}/mark-replied', [OutreachController::class, 'markReply'])
            ->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Complete Student Profile
        |--------------------------------------------------------------------------
        */

        Route::get('/my-profile', [
            MyProfileController::class,
            'show',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Account Settings
        |--------------------------------------------------------------------------
        */

        Route::put('/account', [
            AccountSettingsController::class,
            'update',
        ]);

        Route::put('/account/password', [
            AccountSettingsController::class,
            'changePassword',
        ])->middleware('throttle:5,1');

        Route::delete('/account', [
            AccountSettingsController::class,
            'deleteAccount',
        ])->middleware('throttle:3,1');

        /*
        |--------------------------------------------------------------------------
        | Personal Profile
        |--------------------------------------------------------------------------
        */

        Route::get('/profile', [
            StudentProfileController::class,
            'show',
        ]);

        Route::put('/profile', [
            StudentProfileController::class,
            'update',
        ]);

        Route::delete('/profile', [
            AccountSettingsController::class,
            'deletePersonalProfile',
        ])->middleware('throttle:5,1');

        /*
        |--------------------------------------------------------------------------
        | Academic Education
        |--------------------------------------------------------------------------
        */

        Route::get('/educations', [
            StudentEducationController::class,
            'index',
        ]);

        Route::post('/educations', [
            StudentEducationController::class,
            'store',
        ]);

        Route::get('/educations/{id}', [
            StudentEducationController::class,
            'show',
        ])->whereNumber('id');

        Route::put('/educations/{id}', [
            StudentEducationController::class,
            'update',
        ])->whereNumber('id');

        Route::delete('/educations/{id}', [
            StudentEducationController::class,
            'destroy',
        ])->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Academic Documents
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/educations/{id}/documents/{type}',
            [
                StudentEducationDocumentController::class,
                'upload',
            ]
        )->whereNumber('id')
         ->whereIn('type', ['degree', 'transcript']);

        Route::get(
            '/educations/{id}/documents/{type}',
            [
                StudentEducationDocumentController::class,
                'view',
            ]
        )->whereNumber('id')
         ->whereIn('type', ['degree', 'transcript']);

        Route::get(
            '/educations/{id}/documents/{type}/download',
            [
                StudentEducationDocumentController::class,
                'download',
            ]
        )->whereNumber('id')
         ->whereIn('type', ['degree', 'transcript']);

        Route::delete(
            '/educations/{id}/documents/{type}',
            [
                StudentEducationDocumentController::class,
                'destroy',
            ]
        )->whereNumber('id')
         ->whereIn('type', ['degree', 'transcript']);

        /*
        |--------------------------------------------------------------------------
        | Student Research Profile
        |--------------------------------------------------------------------------
        */

        Route::get('/research-profile', [
            StudentResearchProfileController::class,
            'show',
        ]);

        Route::put('/research-profile', [
            StudentResearchProfileController::class,
            'update',
        ]);

        Route::delete('/research-profile', [
            AccountSettingsController::class,
            'deleteResearchProfile',
        ])->middleware('throttle:5,1');

        /*
        |--------------------------------------------------------------------------
        | Research Proposals
        |--------------------------------------------------------------------------
        */

        Route::get('/research-proposals', [
            StudentResearchProposalController::class,
            'index',
        ]);

        Route::post('/research-proposals', [
            StudentResearchProposalController::class,
            'store',
        ]);

        Route::get('/research-proposals/{id}', [
            StudentResearchProposalController::class,
            'show',
        ])->whereNumber('id');

        Route::put('/research-proposals/{id}', [
            StudentResearchProposalController::class,
            'update',
        ])->whereNumber('id');

        Route::delete('/research-proposals/{id}', [
            StudentResearchProposalController::class,
            'destroy',
        ])->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Research Proposal Documents
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/research-proposals/{id}/document',
            [
                StudentResearchProposalDocumentController::class,
                'upload',
            ]
        )->whereNumber('id');

        Route::get(
            '/research-proposals/{id}/document',
            [
                StudentResearchProposalDocumentController::class,
                'view',
            ]
        )->whereNumber('id');

        Route::get(
            '/research-proposals/{id}/document/download',
            [
                StudentResearchProposalDocumentController::class,
                'download',
            ]
        )->whereNumber('id');

        Route::delete(
            '/research-proposals/{id}/document',
            [
                StudentResearchProposalDocumentController::class,
                'destroy',
            ]
        )->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Student CV
        |--------------------------------------------------------------------------
        */

        Route::get('/cv', [
            StudentCvController::class,
            'show',
        ]);

        Route::post('/cv', [
            StudentCvController::class,
            'upload',
        ]);

        Route::get('/cv/download', [
            StudentCvController::class,
            'download',
        ]);

        Route::delete('/cv', [
            StudentCvController::class,
            'destroy',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Work Experience
        |--------------------------------------------------------------------------
        */

        Route::get('/work-experiences', [
            StudentWorkExperienceController::class,
            'index',
        ]);

        Route::post('/work-experiences', [
            StudentWorkExperienceController::class,
            'store',
        ]);

        Route::get('/work-experiences/{id}', [
            StudentWorkExperienceController::class,
            'show',
        ])->whereNumber('id');

        Route::put('/work-experiences/{id}', [
            StudentWorkExperienceController::class,
            'update',
        ])->whereNumber('id');

        Route::delete('/work-experiences/{id}', [
            StudentWorkExperienceController::class,
            'destroy',
        ])->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Academic Projects
        |--------------------------------------------------------------------------
        */

        Route::get('/projects', [
            StudentProjectController::class,
            'index',
        ]);

        Route::post('/projects', [
            StudentProjectController::class,
            'store',
        ]);

        Route::get('/projects/{id}', [
            StudentProjectController::class,
            'show',
        ])->whereNumber('id');

        Route::put('/projects/{id}', [
            StudentProjectController::class,
            'update',
        ])->whereNumber('id');

        Route::delete('/projects/{id}', [
            StudentProjectController::class,
            'destroy',
        ])->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Certifications
        |--------------------------------------------------------------------------
        */

        Route::get('/certifications', [
            StudentCertificationController::class,
            'index',
        ]);

        Route::post('/certifications', [
            StudentCertificationController::class,
            'store',
        ]);

        Route::get('/certifications/{id}', [
            StudentCertificationController::class,
            'show',
        ])->whereNumber('id');

        Route::put('/certifications/{id}', [
            StudentCertificationController::class,
            'update',
        ])->whereNumber('id');

        Route::delete('/certifications/{id}', [
            StudentCertificationController::class,
            'destroy',
        ])->whereNumber('id');

        /*
        |--------------------------------------------------------------------------
        | Research Publications
        |--------------------------------------------------------------------------
        */

        Route::get('/publications', [
            StudentPublicationController::class,
            'index',
        ]);

        Route::post('/publications', [
            StudentPublicationController::class,
            'store',
        ]);

        Route::get('/publications/{id}', [
            StudentPublicationController::class,
            'show',
        ])->whereNumber('id');

        Route::put('/publications/{id}', [
            StudentPublicationController::class,
            'update',
        ])->whereNumber('id');

        Route::delete('/publications/{id}', [
            StudentPublicationController::class,
            'destroy',
        ])->whereNumber('id');
    });
});
