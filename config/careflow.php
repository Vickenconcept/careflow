<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Case references
    |--------------------------------------------------------------------------
    |
    | Case references are generated per organization and calendar year using an
    | atomic sequence table. The prefix is stable for human operators.
    |
    */

    'case_reference' => [
        'prefix' => env('CAREFLOW_CASE_PREFIX', 'CF'),
        'pad_length' => 6,
    ],

    'proposal_reference' => [
        'prefix' => env('CAREFLOW_PROPOSAL_PREFIX', 'TP'),
        'pad_length' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Document storage
    |--------------------------------------------------------------------------
    |
    | Patient documents are always stored on a private disk. Paths must never
    | be exposed as permanent public URLs.
    |
    */

    'documents' => [
        'disk' => env('CAREFLOW_DOCUMENTS_DISK', 'local'),
        'max_upload_kilobytes' => (int) env('CAREFLOW_DOCUMENTS_MAX_KB', 10240),
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Consent versions
    |--------------------------------------------------------------------------
    |
    | Bump these versions when legal copy changes. Historical consents retain
    | the version accepted at submission time.
    |
    */

    'consent' => [
        'privacy_notice_version' => '2026-11-01',
        'enquiry_consent_version' => '2026-11-01',
        'health_data_consent_version' => '2026-11-01',
        'ai_processing_consent_version' => '2026-11-01',
    ],

    /*
    |--------------------------------------------------------------------------
    | Patient portal access
    |--------------------------------------------------------------------------
    */

    'portal' => [
        'access_token_ttl_days' => (int) env('CAREFLOW_PORTAL_TOKEN_TTL_DAYS', 90),
        'supported_languages' => ['en', 'tr', 'ar', 'ru', 'az'],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI document processing
    |--------------------------------------------------------------------------
    |
    | Supported providers:
    | - openai: Laravel AI SDK agents via OpenAI (requires OPENAI_API_KEY)
    | - mock: deterministic adapters for automated tests only (phpunit.xml)
    |
    | Do not send real patient documents to OpenAI without approved safeguards.
    |
    */

    'ai' => [
        'provider' => env('CAREFLOW_AI_PROVIDER', 'openai'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound communications
    |--------------------------------------------------------------------------
    |
    | Supported providers:
    | - log: Laravel Mail for email + structured log for WhatsApp/portal
    |
    */

    'communications' => [
        'provider' => env('CAREFLOW_COMMUNICATIONS_PROVIDER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hospital information system adapters
    |--------------------------------------------------------------------------
    */

    'his' => [
        'provider' => env('CAREFLOW_HIS_PROVIDER', 'mock'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Platform administrators
    |--------------------------------------------------------------------------
    |
    | Comma-separated emails granted platform-admin access without needing the
    | users.is_platform_admin database flag. Impersonation is the supported way
    | to enter an organization user's interface for support/testing.
    |
    */

    'admin_emails' => array_values(array_filter(array_map(
        static fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', '')),
    ))),

];
