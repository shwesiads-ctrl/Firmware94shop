<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when an idempotency key is reused for an order that is already being processed. */
class DuplicateOrderException extends RuntimeException
{
    //
}
