<?php

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown by WalletService when a caller supplies a zero or negative amount
 * to debit(), credit(), or hold(). Direction must be conveyed only by which
 * method/type is used; the amount itself is always a positive magnitude.
 * See AUDIT FIX (BUG-009, P1, Phase 2.2) in WalletService.
 */
class InvalidWalletAmountException extends InvalidArgumentException
{
    //
}
