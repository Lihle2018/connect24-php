<?php

/**
 * Order-shipped notifications — the shape most Connect24 integrations take.
 *
 * A store's backend calls notifyShipped($order) when a parcel leaves the warehouse. The
 * service emails the customer, texts them when a phone number is on file, and answers the
 * three questions every real integration has to answer:
 *
 *  - What if this runs twice for the same order (a retry, a replayed queue message)?
 *    Idempotency keys derived from the order id: the API returns the original message
 *    instead of sending a second copy, so calling this twice is always safe.
 *  - What if the customer opted out? The API refuses with a 403. That is not an error in
 *    your system — it is the suppression list doing its job — so it comes back as a
 *    "skipped" outcome, not a throw.
 *  - What if the account is out of credit? A 402 means every further send will also fail,
 *    so that one throws OutOfCreditException for the caller to alert on.
 *
 * The notifier depends on the small MessageSenderPort interface rather than the SDK client
 * directly. That is not ceremony: the SDK's classes are final (so PHPUnit cannot mock
 * them), and this is the pattern to use in your own app — your code owns a port, the
 * SdkMessageSender adapter plugs the SDK into it, and your tests mock the port.
 */

declare(strict_types=1);

namespace Connect24\Samples\OrderNotifications;

use Connect24\ApiException;

final class OrderNotifier
{
    public function __construct(private readonly MessageSenderPort $messages)
    {
    }

    /**
     * @param array{id: string, customerName: string, email: string, trackingUrl: string,
     *               phone?: string|null} $order
     * @return array{email: Outcome, sms: Outcome}
     */
    public function notifyShipped(array $order): array
    {
        $email = $this->deliver(fn (): array => $this->messages->sendEmail(
            to: $order['email'],
            subject: "Order {$order['id']} is on its way",
            html: "<p>Hi {$order['customerName']},</p><p>Your order <strong>{$order['id']}</strong> "
                . "has shipped. <a href=\"{$order['trackingUrl']}\">Track it here</a>.</p>",
            text: "Hi {$order['customerName']}, your order {$order['id']} has shipped. "
                . "Track it: {$order['trackingUrl']}",
            // Stable and tied to the event — NOT a uniqid() minted at call time, which
            // would differ on a retry and defeat the point.
            idempotencyKey: "order-{$order['id']}-shipped-email",
        ));

        $phone = $order['phone'] ?? null;
        $sms = $phone === null
            ? new Outcome('skipped', 'no phone number on file')
            : $this->deliver(fn (): array => $this->messages->sendSms(
                to: $phone,
                // Plain GSM-7 characters only: one emoji or curly quote turns 1 SMS into 3.
                text: "Your order {$order['id']} has shipped. Track it: {$order['trackingUrl']}",
                idempotencyKey: "order-{$order['id']}-shipped-sms",
            ));

        return ['email' => $email, 'sms' => $sms];
    }

    /** @param callable(): array<string, mixed> $send */
    private function deliver(callable $send): Outcome
    {
        try {
            $accepted = $send();

            return new Outcome('sent', (string) ($accepted['id'] ?? ''));
        } catch (ApiException $exception) {
            if ($exception->getStatusCode() === 403) {
                // Most often the suppression list: the person opted out or hard-bounced
                // before. Retrying will never succeed, and contacting them anyway is the
                // one thing a messaging integration must never do.
                return new Outcome('skipped', $exception->getMessage());
            }
            if ($exception->getStatusCode() === 402) {
                throw new OutOfCreditException($exception->getMessage());
            }

            // Everything else (bad request, 5xx, connection loss) surfaces to the caller.
            // A connection error is safe to retry with the SAME order — the idempotency
            // keys make the retry return the original message.
            throw $exception;
        }
    }
}
