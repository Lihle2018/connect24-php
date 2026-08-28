<?php

declare(strict_types=1);

namespace Connect24\Resources;

use Connect24\Transport;

/**
 * `$client->suppressions` — addresses that will not be sent to.
 *
 * Two kinds live here and they behave differently. One the **recipient** created — an unsubscribe,
 * a STOP reply, a spam complaint, the National Opt-Out Registry — cannot be removed by you, through
 * this API or any other route. Only they can undo it, by opting in again. Suppressions created for
 * other reasons, such as a mailbox that permanently rejected mail or an address you added by hand,
 * you may remove.
 */
final class Suppressions
{
    /** Reasons the recipient created, which the sender may never remove. */
    private const CHOSEN_BY_RECIPIENT = ['unsubscribed', 'complained', 'stopreply', 'optoutregistry'];

    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /** @return array<int, array<string, mixed>> */
    public function list(int $limit = 100): array
    {
        return $this->transport->get('v1/suppressions?limit=' . $limit) ?? [];
    }

    /** Suppresses an address yourself, for somebody who asked you directly. */
    public function add(string $address, ?string $reason = null): void
    {
        $this->transport->post('v1/suppressions', ['address' => $address, 'reason' => $reason]);
    }

    /**
     * Removes a suppression you are allowed to remove.
     *
     * Refused with a 403 when the recipient created it. That is not a bug to work around: acting
     * on it would mean messaging somebody who said no.
     */
    public function remove(string $address): void
    {
        $this->transport->delete('v1/suppressions/' . rawurlencode($address));
    }

    /** Whether the recipient created this suppression, and it is therefore yours to leave alone. */
    public static function chosenByRecipient(string $reason): bool
    {
        return in_array(strtolower($reason), self::CHOSEN_BY_RECIPIENT, true);
    }
}
