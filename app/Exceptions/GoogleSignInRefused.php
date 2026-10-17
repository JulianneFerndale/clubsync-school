<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Google answered, but not with an account ClubSync accepts — one outside the SCC
 * Google Workspace, an unverified address, or a reply that does not belong to the
 * sign-in this browser started.
 *
 * Carries the message shown to the person, because what they should do next differs:
 * choose their @sccpag.edu.ph account, or simply try again.
 */
class GoogleSignInRefused extends RuntimeException
{
}
