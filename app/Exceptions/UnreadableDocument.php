<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An uploaded proof-of-enrollment file that cannot be opened as an image or a PDF.
 *
 * Carries the message shown against the upload field. It is not a failed check —
 * nothing was read — so it neither counts as an attempt nor can be sent for review.
 */
class UnreadableDocument extends RuntimeException
{
}
