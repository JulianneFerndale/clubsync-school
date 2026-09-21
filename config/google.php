<?php

return [

    'calendar' => [
        /*
        |----------------------------------------------------------------------
        | Google Calendar sync (institutional Google Workspace)
        |----------------------------------------------------------------------
        | Background sync of confirmed club activities, fee due dates and
        | semester dates into the personal Google Calendar of every user they
        | concern, via a service account with domain-wide delegation. See the
        | setup notes in .env.example / workflows/POLICY.md.
        */

        // Master switch. Sync is a no-op unless this is true AND the key file exists.
        'enabled' => (bool) env('GOOGLE_CALENDAR_ENABLED', false),

        // Absolute path to the service-account JSON key file. `?:` rather than an env()
        // default, because a blank GOOGLE_SERVICE_ACCOUNT_JSON= line reads as '' and
        // would otherwise disable sync even with the key in its default place.
        'credentials' => env('GOOGLE_SERVICE_ACCOUNT_JSON') ?: storage_path('app/google/service-account.json'),

        // Only users with an email on this Workspace domain are synced — the
        // service account can act as nobody else.
        'domain' => env('GOOGLE_WORKSPACE_DOMAIN', 'sccpag.edu.ph'),

        // IANA timezone used for event start/end.
        'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', 'Asia/Manila'),
    ],

];
