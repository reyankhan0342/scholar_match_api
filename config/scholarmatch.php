<?php

return [
    'openai_api_key' => env('OPENAI_API_KEY'),
    'openai_model' => env('SCHOLARMATCH_AI_MODEL', 'gpt-4.1-mini'),
    'google_client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
    'google_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
    'google_redirect_uri' => env('GOOGLE_OAUTH_REDIRECT_URI'),
];
