<?php

declare(strict_types=1);

namespace Connect24\Resources;

use Connect24\Transport;

/**
 * `$client->webhooks` — delivery events pushed to you.
 *
 * Registering an endpoint is only half of it. Verify every request that arrives with
 * {@see \Connect24\WebhookSignature::isValid()} before acting on it: the URL is public, so
 * without that check anyone can tell your system a message bounced.
 */
final class Webhooks
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return $this->transport->get('v1/webhooks') ?? [];
    }

    /**
     * Registers an endpoint.
     *
     * The signing secret is on the returned array and is shown **once**. Store it now; it cannot
     * be read back later, only replaced.
     *
     * @param list<string>|null $events
     * @return array<string, mixed>
     */
    public function create(string $url, ?array $events = null): array
    {
        return $this->transport->post('v1/webhooks', ['url' => $url, 'events' => $events]) ?? [];
    }

    public function delete(string $endpointId): void
    {
        $this->transport->delete('v1/webhooks/' . rawurlencode($endpointId));
    }

    /**
     * Recent attempts to reach your endpoints — the first place to look when events stop.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listDeliveries(int $limit = 100): array
    {
        return $this->transport->get('v1/webhooks/deliveries?limit=' . $limit) ?? [];
    }
}
