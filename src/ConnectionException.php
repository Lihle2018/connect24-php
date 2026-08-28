<?php

declare(strict_types=1);

namespace Connect24;

/**
 * The request never got an answer — DNS, TLS, a timeout, a dropped connection.
 *
 * Deliberately a different class from {@see ApiException}, because the outcome is genuinely
 * unknown: the message may well have been sent. Retry with the same idempotency key and you get
 * the original message back rather than a second copy.
 */
class ConnectionException extends Connect24Exception
{
}
