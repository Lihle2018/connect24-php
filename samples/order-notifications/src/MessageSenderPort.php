<?php

declare(strict_types=1);

namespace Connect24\Samples\OrderNotifications;

/**
 * The slice of Connect24 this sample needs. Your app owns this interface, the adapter
 * below plugs the SDK into it, and your tests mock it — the pattern to copy, since the
 * SDK's own classes are final.
 *
 * Both methods return the API's accepted-message array (id, channel, status) and throw
 * \Connect24\ApiException when the API refuses.
 */
interface MessageSenderPort
{
    /** @return array<string, mixed> */
    public function sendEmail(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $idempotencyKey,
    ): array;

    /** @return array<string, mixed> */
    public function sendSms(string $to, string $text, string $idempotencyKey): array;
}
