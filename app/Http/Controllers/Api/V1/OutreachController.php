<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GmailConnection;
use App\Models\OutreachMessage;
use App\Models\StudentCv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mime\Email;
use Throwable;

class OutreachController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $messages = OutreachMessage::where('user_id', $request->user()->id)
            ->latest()->paginate(20);
        return response()->json(['success' => true, 'data' => ['messages' => $messages]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->fields($request);
        $message = OutreachMessage::create([...$data,
            'user_id' => $request->user()->id, 'status' => 'draft',
        ]);
        return response()->json(['success' => true, 'message' => 'Draft saved.',
            'data' => ['message' => $message]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $message = $this->own($request, $id);
        abort_unless($message->status === 'draft', 409, 'Only drafts can be edited.');
        $message->update($this->fields($request, true));
        return response()->json(['success' => true, 'data' => ['message' => $message->fresh()]]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->own($request, $id)->delete();
        return response()->json(['success' => true, 'message' => 'Outreach record removed.']);
    }

    public function markReply(Request $request, int $id): JsonResponse
    {
        $message = $this->own($request, $id);
        abort_unless(in_array($message->status, ['sent', 'replied'], true), 409);
        // Manually recorded by the student. No unreliable Gmail read tracking.
        $message->update(['status' => 'replied']);
        return response()->json(['success' => true, 'data' => ['message' => $message->fresh()]]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $message = $this->own($request, $id);
        abort_unless($message->status === 'draft', 409, 'Message already sent.');
        $connection = GmailConnection::where('user_id', $request->user()->id)->first();
        if (!$connection) throw ValidationException::withMessages([
            'gmail' => ['Connect your Gmail account before sending.'],
        ]);

        $refresh = $connection->refresh_token;
        if (!$refresh || !config('scholarmatch.google_client_secret')) {
            return response()->json(['success' => false,
                'message' => 'Google OAuth configuration is incomplete.'], 503);
        }

        $cv = null;
        if ($message->attach_cv) {
            $cv = StudentCv::where('user_id', $request->user()->id)->first();
            if (!$cv || !Storage::disk('local')->exists($cv->file_path)) {
                throw ValidationException::withMessages(['cv' => ['Upload your PDF CV before sending.']]);
            }
        }

        // Claim the draft atomically to prevent duplicate sends across devices.
        $claimed = OutreachMessage::where('id', $message->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'draft')->update(['status' => 'sending']);
        abort_unless($claimed === 1, 409, 'This draft is already being sent.');

        $attemptedGmailSend = false;
        try {
            $tokens = Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('scholarmatch.google_client_id'),
                'client_secret' => config('scholarmatch.google_client_secret'),
                'refresh_token' => $refresh, 'grant_type' => 'refresh_token',
            ])->throw()->json();
            $access = $tokens['access_token'] ?? null;
            if (!$access) throw new \RuntimeException('Google access token missing.');

            $email = (new Email())->from($connection->email)
                ->to($message->professor_email)->subject($message->subject)
                ->text($message->body);
            if ($cv) {
                $email->attachFromPath(Storage::disk('local')->path($cv->file_path),
                    'student-cv.pdf', 'application/pdf');
            }
            $raw = rtrim(strtr(base64_encode($email->toString()), '+/', '-_'), '=');
            $attemptedGmailSend = true;
            $sent = Http::withToken($access)->acceptJson()->timeout(30)->post(
                'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
                ['raw' => $raw]
            )->throw()->json();
            if (empty($sent['id'])) throw new \RuntimeException('Gmail message ID missing.');
            $message->update([
                'status' => 'sent', 'gmail_message_id' => $sent['id'], 'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            // A network timeout after POST may mean Gmail DID send the email.
            // Stop automatic retries until the student checks Gmail Sent folder.
            $message->update(['status' => $attemptedGmailSend ? 'unknown' : 'draft']);
            return response()->json(['success' => false,
                'message' => $attemptedGmailSend
                    ? 'Sending status uncertain. Check Gmail Sent before drafting another email.'
                    : 'Could not authorize Gmail. Please reconnect and try again.'], 502);
        }
        return response()->json(['success' => true,
            'message' => 'Email sent from your connected Gmail account.',
            'data' => ['message' => $message->fresh()]]);
    }

    private function own(Request $request, int $id): OutreachMessage
    {
        return OutreachMessage::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function fields(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';
        return $request->validate([
            'professor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'professor_email' => [$required, 'email', 'max:255'],
            'university_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'subject' => [$required, 'string', 'max:255'],
            'body' => [$required, 'string', 'max:20000'],
            'attach_cv' => ['sometimes', 'boolean'],
            'status' => ['prohibited'], 'user_id' => ['prohibited'],
        ]);
    }
}
