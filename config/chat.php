<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Message retention (RA 10173 — storage limitation)
    |--------------------------------------------------------------------------
    | Private messages are kept only as long as they are useful. `chat:prune`
    | runs daily and permanently deletes messages older than this many days.
    | A floor of 30 days stops a misconfigured env from wiping live threads.
    */
    'retention_days' => max(30, (int) env('CHAT_RETENTION_DAYS', 365)),

    // Longest message a user may send, in characters.
    'max_length' => 2000,

    /*
    |--------------------------------------------------------------------------
    | People search (data minimisation)
    |--------------------------------------------------------------------------
    | The directory is searched, never browsed: a query must be at least this
    | long, and only a handful of matches come back — name, role and photo only.
    */
    'search_min_length' => 2,
    'search_limit'      => 10,
];
