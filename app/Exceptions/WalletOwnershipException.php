<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an idempotency key already used on one wallet is replayed
 * against a different wallet. See AUDIT FIX (BUG-002, Phase 2.1) in
 * WalletService — this should never happen in normal operation (every
 * caller prefixes keys with an order/deposit id), so surfacing it loudly as
 * an exception rather than silently returning the other wallet's
 * transaction is the deliberately "fail safe" behavior here.
 */
class WalletOwnershipException extends RuntimeException
{
    //
}
