<?php

declare(strict_types=1);

namespace Connect24\Samples\OrderNotifications;

/** What happened on one channel: "sent" with the message id, or "skipped" with the reason. */
final class Outcome
{
    public function __construct(
        public readonly string $status,
        public readonly string $detail,
    ) {
    }
}
