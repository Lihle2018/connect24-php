<?php

declare(strict_types=1);

namespace Connect24\Resources;

use Connect24\Transport;
use InvalidArgumentException;

/**
 * `$client->messages` — every channel through one shape.
 *
 * Responses come back as associative arrays mirroring the API exactly. There is no mapping layer,
 * deliberately: a mapping layer is a second place for the contract to live, and the two drift the
 * moment the API adds a field.
 */
final class Messages
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Sends one message.
     *
     * Prefer {@see sendSms}, {@see sendEmail} or {@see sendWhatsApp}; this is the full shape
     * underneath them, for the cases they do not cover.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function send(array $request, ?string $idempotencyKey = null): array
    {
        return $this->transport->post('v1/messages', $request, $idempotencyKey) ?? [];
    }

    /**
     * Sends an SMS.
     *
     * There is no sender argument, deliberately. South African traffic leaves from a shared
     * originator pool, and naming an identity you do not own is rejected by the network rather
     * than by us.
     *
     * Watch what goes in $text. An SMS holds 160 characters using the GSM-7 alphabet; one emoji,
     * curly quote or em dash switches the whole message to UCS-2, which holds 70 per part. A
     * 150-character message with one emoji costs three SMS, not one.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendSms(string $to, string $text, array $options = [], ?string $idempotencyKey = null): array
    {
        return $this->send(array_merge([
            'channel' => 'Sms',
            'to' => $to,
            'content' => ['type' => 'text', 'text' => $text],
        ], $options), $idempotencyKey);
    }

    /**
     * Sends an email.
     *
     * `from` is not used until you verify the domain it belongs to. Until then mail leaves from
     * your account's assigned address on connect24.co.za and the address you give becomes the
     * Reply-To — so replies still reach you, but the envelope is ours.
     *
     * @param array{address: string, name?: string}|null $from
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendEmail(
        string $to,
        string $subject,
        ?string $html = null,
        ?string $text = null,
        ?array $from = null,
        array $options = [],
        ?string $idempotencyKey = null
    ): array {
        return $this->send(array_merge([
            'channel' => 'Email',
            'to' => $to,
            'from' => $from,
            'content' => [
                'type' => $html !== null ? 'html' : 'text',
                'subject' => $subject,
                'html' => $html,
                'text' => $text,
            ],
        ], $options), $idempotencyKey);
    }

    /**
     * Sends a WhatsApp message.
     *
     * Free-form text only works inside the 24-hour window that opens when the customer last
     * messaged you. Outside it WhatsApp requires an approved template — pass $templateName.
     * Sending free-form outside the window is refused by WhatsApp, not by us.
     *
     * @param array<string, string>|null $variables
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendWhatsApp(
        string $to,
        ?string $text = null,
        ?string $templateName = null,
        ?array $variables = null,
        array $options = [],
        ?string $idempotencyKey = null
    ): array {
        if ($text === null && $templateName === null) {
            throw new InvalidArgumentException(
                'Give either $text, or $templateName for a message outside the 24-hour window.'
            );
        }

        return $this->send(array_merge([
            'channel' => 'WhatsApp',
            'to' => $to,
            'content' => ['type' => 'text', 'text' => $text, 'templateName' => $templateName],
            'variables' => $variables,
        ], $options), $idempotencyKey);
    }

    /**
     * Sends a stored template, with values substituted into its placeholders.
     *
     * @param array<string, string>|null $variables
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function sendTemplate(
        string $channel,
        string $to,
        string $template,
        ?array $variables = null,
        array $options = [],
        ?string $idempotencyKey = null
    ): array {
        return $this->send(array_merge([
            'channel' => $channel,
            'to' => $to,
            'content' => ['type' => 'template'],
            'template' => $template,
            'variables' => $variables,
        ], $options), $idempotencyKey);
    }

    /**
     * One message, with its current status and why it failed if it did.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->transport->get('v1/messages/' . rawurlencode($id)) ?? [];
    }

    /**
     * The most recent messages, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(int $limit = 100): array
    {
        return $this->transport->get('v1/messages?limit=' . $limit) ?? [];
    }
}
