<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The email address someone is registering with already belongs to an account.
 *
 * Carries the message shown against the email field, because what the person needs
 * to do next differs: a complete account means sign in, while an address held by
 * someone else means the address is not theirs to register.
 */
class EmailAlreadyRegistered extends RuntimeException
{
}
