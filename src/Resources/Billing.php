<?php

declare(strict_types=1);

namespace Connect24\Resources;

use Connect24\Transport;

/** `$client->billing` — prepaid credit, and where it went. */
final class Billing
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Credit remaining.
     *
     * Every message is charged before it is sent. When the balance cannot cover one the send is
     * refused with a 402 rather than silently dropped, so a low balance surfaces as an error you
     * can act on instead of as messages quietly not arriving.
     *
     * @return array<string, mixed>
     */
    public function balance(): array
    {
        return $this->transport->get('v1/balance') ?? [];
    }

    /**
     * Every credit and debit, with what caused it.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ledger(int $limit = 100): array
    {
        return $this->transport->get('v1/ledger?limit=' . $limit) ?? [];
    }
}
