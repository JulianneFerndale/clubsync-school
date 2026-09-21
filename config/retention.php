<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database storage cap
    |--------------------------------------------------------------------------
    | Supabase free tier allows ~500 MB. The retention guard works against this
    | figure; bump it if you upgrade the plan.
    */
    'database_cap_mb' => (int) env('DB_CAP_MB', 500),

    // Usage thresholds (percent of the cap) that drive admin alerts.
    'warn_percent'     => (int) env('RETENTION_WARN_PERCENT', 75),
    'critical_percent' => (int) env('RETENTION_CRITICAL_PERCENT', 90),

    /*
    |--------------------------------------------------------------------------
    | Auto-prune (transient data only — safe, no confirmation)
    |--------------------------------------------------------------------------
    */
    'session_days'           => (int) env('RETENTION_SESSION_DAYS', 7),   // expired sessions
    'read_notification_days' => (int) env('RETENTION_READ_NOTIF_DAYS', 90), // read notifications

    /*
    |--------------------------------------------------------------------------
    | Audit log retention (POLICY: minimum one academic year)
    |--------------------------------------------------------------------------
    | Never set below 365. Audit rows older than this MAY be pruned (oldest-first).
    */
    'audit_retention_days' => max(365, (int) env('RETENTION_AUDIT_DAYS', 365)),
];
