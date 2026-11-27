# ScholarMatch Laravel 12 API — implementation and setup

## Source update

The original Laravel API endpoints and existing code are kept. Added:

- `GET /api/v1/health` for the app's versioned health endpoint (legacy `/api/health` remains).
- Google OAuth connection: `GET /api/v1/gmail/status`, `POST /api/v1/gmail/connect`, public `GET /api/v1/gmail/callback`, `DELETE /api/v1/gmail/disconnect`.
- Student-owned outreach: `GET /api/v1/outreach`, `POST /api/v1/outreach`, `PUT /api/v1/outreach/{id}`, `DELETE /api/v1/outreach/{id}`, `POST /api/v1/outreach/{id}/send`, `POST /api/v1/outreach/{id}/mark-replied`.
- Opt-in AI drafting: `POST /api/v1/ai/email-draft`.
- Migration for `gmail_oauth_states`, `gmail_connections`, `outreach_messages`. Models are user-scoped; Google refresh tokens are encrypted using Laravel's APP_KEY and hidden from JSON.

### Merge into your existing local project

Keep your existing `.env` and **take a backup**. The ZIP intentionally omits `.env` and `vendor/`.

On your Windows local source (PHP 8.4 CLI example):

```bash
composer install
/c/php84/php.exe artisan config:clear
/c/php84/php.exe artisan migrate
/c/php84/php.exe artisan route:list --path=api
/c/php84/php.exe artisan serve --host=0.0.0.0 --port=8000
```

Use `127.0.0.1` instead of `0.0.0.0` for local-only usage. Configure MySQL `127.0.0.1:3307` and database `scholar_match` in YOUR existing `.env`. Run new migrations on a database backup, not production first. `APP_KEY` must stay unchanged for encrypted existing data until a planned rotation.

### OTP email

Your existing Gmail SMTP setup can remain unchanged; it is for registration/login OTPs. Do not paste SMTP App Passwords into source files or the chat.

### Gmail OAuth (required for student-owned sending)

1. In Google Cloud Console create a project and enable **Gmail API**.
2. Configure OAuth consent screen. For testing, add the students' Gmail addresses to approved test users.
3. Create an **OAuth client ID: Web application**. The authorized redirect must match the Laravel public callback EXACTLY, for example `https://api.yourdomain.com/api/v1/gmail/callback`.
4. Add these entries to your private `.env`:

```dotenv
GOOGLE_OAUTH_CLIENT_ID=YOUR_WEB_CLIENT_ID
GOOGLE_OAUTH_CLIENT_SECRET=YOUR_CLIENT_SECRET
GOOGLE_OAUTH_REDIRECT_URI=https://api.yourdomain.com/api/v1/gmail/callback
```

5. Run `/c/php84/php.exe artisan config:clear`. On the phone open Outreach > Connect Gmail. The user explicitly consents to `gmail.send` and basic identity. Refresh Outreach after the browser confirms Gmail connected.
6. Gmail connection is per student, NOT the ScholarMatch SMTP account. Sending requires the user's own Google authorization; the API uses only the `gmail.send` scope and does not implement read receipts.

Google's sensitive Gmail scopes may require consent-screen verification before public deployment. A stable HTTPS hostname is necessary for normal web OAuth callbacks. A short-lived loopback URL or an expiring temporary tunnel is not a production solution.

### Optional AI email drafting

To enable opt-in drafts, configure:

```dotenv
OPENAI_API_KEY=YOUR_SERVER_SIDE_API_KEY
SCHOLARMATCH_AI_MODEL=gpt-4.1-mini
```

Requires a separate paid API account. The student explicitly chooses Generate, and the server shares only student name and user-entered professor, university, research topic and goal for a draft. No CV or password is sent. `store=false`; drafts are not emailed unless the user explicitly presses Send. Human review is essential.

### Security and behavior

- OTP must be verified before a Sanctum token is issued, and expires in five minutes.
- All protected routes use `auth:sanctum`. Profile, outreach and Gmail connection ownership comes from the authenticated user, not a client `user_id`.
- Gmail connection state is one-time, random, stored hashed in MySQL, and expires in 10 minutes.
- Google refresh tokens use Laravel's encrypted cast. Do not change APP_KEY casually.
- Outlook-style tracking is **manual reply status**, not actual read tracking.
- Gmail send claims a draft atomically. An uncertain network outcome is recorded as `unknown` and cannot automatically resend. Check Gmail Sent folder before drafting again.
- OpenAlex indexed authors do not automatically represent verifiable faculty contacts. Obtain professor email from official sites.
- Never expose `.env`, Google refresh tokens, Sanctum access tokens, or the OpenAI API key to Flutter.

### Verification commands

```bash
/c/php84/php.exe artisan route:list --path=api
/c/php84/php.exe artisan migrate:status
/c/php84/php.exe artisan test
```

`artisan test` requires your dependencies and test DB configured. Static PHP syntax checks passed in the authoring runtime, but full API tests were not run against your Windows MySQL/Gmail/Google accounts.
