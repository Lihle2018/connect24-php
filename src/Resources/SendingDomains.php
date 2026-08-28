<?php

declare(strict_types=1);

namespace Connect24\Resources;

use Connect24\Transport;

/**
 * `$client->sendingDomains` — proving you control a domain, so mail goes out as you.
 *
 * Until a domain is verified, email leaves from your account's assigned address on
 * connect24.co.za and your address becomes the Reply-To. That address is random and not
 * chooseable: if customers could pick it, one could send as security@connect24.co.za and phish
 * under the platform's brand. Sending reputation is shared, so the identity stays ours until you
 * have proved a domain of your own.
 */
final class SendingDomains
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /** @return array<int, array<string, mixed>> */
    public function list(): array
    {
        return $this->transport->get('v1/sending-domains') ?? [];
    }

    /**
     * Registers a domain and returns the DNS records to publish.
     *
     * @return array<string, mixed>
     */
    public function add(string $domain): array
    {
        return $this->transport->post('v1/sending-domains', ['domain' => $domain]) ?? [];
    }

    /**
     * Checks the DNS records you published.
     *
     * DNS propagation is not instant, so a first call that comes back unverified usually means
     * "not yet" rather than "wrong" — wait and call again.
     *
     * @return array<string, mixed>
     */
    public function verify(string $domain): array
    {
        return $this->transport->post('v1/sending-domains/' . rawurlencode($domain) . '/verify') ?? [];
    }

    public function remove(string $domain): void
    {
        $this->transport->delete('v1/sending-domains/' . rawurlencode($domain));
    }
}
