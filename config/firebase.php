<?php

return [
    'api_key'      => env('FIREBASE_API_KEY'),
    'project_id'   => env('FIREBASE_PROJECT_ID'),
    'database_url' => env('FIREBASE_DATABASE_URL'),

    'sign_in_url'   => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
    'sign_up_url'   => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
    'update_url'    => 'https://identitytoolkit.googleapis.com/v1/accounts:update',
    'lookup_url'    => 'https://identitytoolkit.googleapis.com/v1/accounts:lookup',
    // Answers "is this email already registered?" with only the web API key.
    // accounts:lookup cannot: it needs an idToken or an admin credential.
    'create_auth_uri_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:createAuthUri',
    // Signs in (or links) with a Google ID token — "Continue with Google".
    'sign_in_with_idp_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithIdp',
    'refresh_url'        => 'https://securetoken.googleapis.com/v1/token',
    'send_oob_code_url'  => 'https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode',
    // Verifies a password-reset oobCode and applies the new password. ClubSync hosts the
    // reset page itself (ResetPasswordController); Firebase only mints and emails the code.
    'reset_password_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:resetPassword',
];
