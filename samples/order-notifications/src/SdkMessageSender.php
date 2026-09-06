<?php

declare(strict_types=1);

namespace Connect24\Samples\OrderNotifications;

use Connect24\Client;

/** The production adapter: the port, implemented by the real SDK client. */
final class SdkMessageSender implements MessageSenderPort
{
    public function __construct(private readonly Client $client)
    {
    }

    public function sendEmail(
        string $to,
        string $subject,
        string $html,
        string $text,
        string $idempotencyKey,
    ): array {
        return $this->client->messages->sendEmail(
            to: $to,
            subject: $subject,
            html: $html,
            text: $text,
            idempotencyKey: $idempotencyKey,
        );
    }

    public function sendSms(string $to, string $text, string $idempotencyKey): array
    {
        return $this->client->messages->sendSms(
            to: $to,
            text: $text,
            idempotencyKey: $idempotencyKey,
        );
    }
}
