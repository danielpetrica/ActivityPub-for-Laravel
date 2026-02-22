<?php

return [
    // You'll add these values in your .env later
    'api_token' => env('MAILCOACH_API_TOKEN', ''),
    'endpoint' => env('MAILCOACH_API_ENDPOINT', ''), // e.g. https://mailcoach.app/api or your self-hosted API endpoint

    // Default email list UUID to subscribe users to
    'email_list_uuid' => env('MAILCOACH_EMAIL_LIST_UUID', ''),
];
