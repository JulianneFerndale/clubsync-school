<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Load-test rehearsal
    |--------------------------------------------------------------------------
    |
    | Turns on the fake-student seeder and the session minting command used by
    | the load tests (loadtest/README.md). Both write to the database and the
    | command signs users in without a password, so this must stay off anywhere
    | real people use the app. Only the loadtest/docker-compose.yml rehearsal sets it.
    |
    */

    'enabled' => (bool) env('LOADTEST', false),

    // Every fake student's email ends here. `.invalid` is reserved and can never
    // be a real address, and the session command refuses anyone else.
    'email_domain' => 'loadtest.invalid',

];
