<?php

declare(strict_types=1);

namespace Connect24\Samples\OrderNotifications;

use RuntimeException;

/** The Connect24 account cannot cover the send. Top up; retrying will not help. */
final class OutOfCreditException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct("Connect24 account is out of credit: {$message}");
    }
}
