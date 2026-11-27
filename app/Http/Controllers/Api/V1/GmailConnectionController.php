<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GmailConnection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class GmailConnectionController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $connection = GmailConnection::where('user_id', $request->user()->id)->first();
        return response()->json(['success' => true, 'data' => [
            'connected' => $connection !== null,
            'email' => $connection?->email,
        ]]);
    }

    public function connect(Request $request): JsonResponse
    {
        $id = config('scholarmatch.google_client_id');
        $secret = config('scholarmatch.google_client_secret');
        $redirect = config('scholarmatch.google_redirect_uri');
        if (!$id || !$secret || !$redirect || !str_starts_with($redirect, 'https://') &&
            !str_starts_with($redirect, 'http://127.0.0.1:')) {
            return response()->json(['success' => false,
                'message' => 'Google OAuth is not configured with a secure redirect URI.'], 503);
        }

        $state = Str::random(64);
        DB::table('gmail_oauth_states')->where('user_id', $request->user()->id)->delete();
        DB::table('gmail_oauth_states')->insert([
            'user_id' => $request->user()->id,
            'state_hash' => hash('sha256', $state),
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $query = http_build_query([
            'client_id' => $id,
            'redirect_uri' => $redirect,
            'response_type' => 'code',
            'scope' => 'openid email https://www.googleapis.com/auth/gmail.send',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return response()->json(['success' => true, 'data' => [
            'authorization_url' => 'https://accounts.google.com/o/oauth2/v2/auth?'.$query,
        ]]);
    }

    /// The OAuth redirect is public, but the one-time random state binds it to a user.
    public function callback(Request $request)
    {
        $state = $request->query('state');
        $code = $request->query('code');
        if (!is_string($state) || strlen($state) !== 64 || !is_string($code) ||
            strlen($code) > 4096 || !$code) {
            return response('Google connection cancelled or invalid.', 400);
        }

        $record = DB::table('gmail_oauth_states')
            ->where('state_hash', hash('sha256', $state))
            ->where('expires_at', '>', now())
            ->first();
        if (!$record) return response('The Google connection session has expired.', 400);
        DB::table('gmail_oauth_states')->where('id', $record->id)->delete();

        if (!User::find($record->user_id)) return response('Student account not found.', 404);
        try {
            $tokens = Http::asForm()->timeout(15)->post(
                'https://oauth2.googleapis.com/token', [
                    'client_id' => config('scholarmatch.google_client_id'),
                    'client_secret' => config('scholarmatch.google_client_secret'),
                    'redirect_uri' => config('scholarmatch.google_redirect_uri'),
                    'grant_type' => 'authorization_code', 'code' => $code,
                ]
            )->throw()->json();

            $refresh = $tokens['refresh_token'] ?? null;
            $access = $tokens['access_token'] ?? null;
            if (!is_string($refresh) || !$refresh || !is_string($access)) {
                return response('Google did not issue offline access. Disconnect and try again.', 422);
            }

            $googleUser = Http::withToken($access)->timeout(15)
                ->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
            $email = $googleUser['email'] ?? null;
            if (empty($googleUser['email_verified']) || !is_string($email) ||
                !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return response('Google email could not be verified.', 422);
            }

            GmailConnection::updateOrCreate(['user_id' => $record->user_id], [
                'email' => $email, 'refresh_token' => $refresh,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            return response('Could not connect Google. Please try again.', 502);
        }

        return response('<!doctype html><meta name="viewport" content="width=device-width"><title>ScholarMatch</title><h2>Gmail connected</h2><p>You may return to the ScholarMatch app.</p>',
            200, ['Content-Type' => 'text/html; charset=UTF-8',
                'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function disconnect(Request $request): JsonResponse
    {
        GmailConnection::where('user_id', $request->user()->id)->delete();
        return response()->json(['success' => true, 'message' => 'Gmail connection removed.']);
    }
}
