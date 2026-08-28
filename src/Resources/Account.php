<?php

declare(strict_types=1);

namespace Connect24\Resources;

use Connect24\Transport;

/** `$client->account` — who you are, and what can send right now. */
final class Account
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Your account, including the sending address assigned to it.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->transport->get('v1/account') ?? [];
    }

    /**
     * Which channels can send, and for those that cannot, what is missing.
     *
     * Worth calling at start-up in a deployment you did not configure yourself: it answers "why is
     * nothing sending" without waiting for a failed message to tell you.
     *
     * @return array<int, array<string, mixed>>
     */
    public function channels(): array
    {
        return $this->transport->get('v1/channels') ?? [];
    }
}
