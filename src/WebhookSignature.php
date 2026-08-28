<?php

declare(strict_types=1);

namespace Connect24;

/**
 * Proving a webhook request really came from Connect24.
 *
 * Your webhook URL is public, so anyone can POST to it. Without verification, anyone could tell
 * your system that a message bounced, that a customer unsubscribed, or that an invoice was paid.
 * **Verify every request before acting on it.**
 *
 * Two things are easy to get wrong and both fail silently:
 *
 * 1. Verify against the **raw request body**, byte for byte as received. Decoding JSON and
 *    re-encoding it changes whitespace and key order, and the signature will no longer match.
 *    In PHP that means `file_get_contents('php://input')`, not `$_POST`.
 * 2. Read the body before anything else consumes the stream. `php://input` can only be read once
 *    in some server configurations, and frameworks often read it for you.
 *
 * A plain PHP receiver, in full:
 *
 * ```php
 * $payload = file_get_contents('php://input');
 * $signature = $_SERVER['HTTP_X_CONNECT24_SIGNATURE'] ?? '';
 *
 * if (!WebhookSignature::isValid($payload, $signature, $secret)) {
 *     http_response_code(401);
 *     exit;
 * }
 *
 * $event = json_decode($payload, true);
 * // Acknowledge fast — anything that is not 2xx is retried.
 * http_response_code(200);
 * ```
 *
 * In Laravel, `$request->getContent()` gives the raw body. Delivery is at-least-once, so
 * deduplicate on the event id.
 */
final class WebhookSignature
{
    /**
     * How old a delivery may be and still be accepted. Five minutes leaves room for clock drift
     * and a slow network, while stopping a captured request from being replayed hours later.
     */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Whether a webhook request is authentic and recent.
     *
     * @param string $payload          The raw request body, exactly as received.
     * @param string $signatureHeader  The X-Connect24-Signature header, `t=1770000000,v1=abc123…`.
     * @param string $secret           The endpoint's signing secret (`whsec_…`).
     * @param int    $toleranceSeconds How old the delivery may be. Pass 0 to skip the age check —
     *                                 only sensible when replaying a captured request in a test.
     *
     * Never throws. A malformed header from an attacker returns false rather than becoming a 500,
     * because an exception here would turn a forged request into an outage.
     */
    public static function isValid(
        string $payload,
        string $signatureHeader,
        string $secret,
        int $toleranceSeconds = self::DEFAULT_TOLERANCE_SECONDS
    ): bool {
        if ($payload === '' || $signatureHeader === '' || $secret === '') {
            return false;
        }

        $parsed = self::parseHeader($signatureHeader);
        if ($parsed === null) {
            return false;
        }

        [$timestamp, $provided] = $parsed;

        if ($toleranceSeconds > 0) {
            // Absolute, so a delivery stamped in the future — a forged request, or badly skewed
            // clocks — is refused as well.
            if (abs(time() - $timestamp) > $toleranceSeconds) {
                return false;
            }
        }

        $expected = self::compute($secret, $timestamp, $payload);

        // hash_equals, not ===: a plain comparison stops at the first differing character, and the
        // time that takes leaks how much of the signature an attacker has guessed correctly.
        return hash_equals($expected, $provided);
    }

    /** When Connect24 signed the delivery, or null if the header is malformed. */
    public static function timestampOf(string $signatureHeader): ?int
    {
        $parsed = self::parseHeader($signatureHeader);

        return $parsed === null ? null : $parsed[0];
    }

    /**
     * @return array{0: int, 1: string}|null
     */
    private static function parseHeader(string $header): ?array
    {
        $timestamp = 0;
        $signature = '';

        foreach (explode(',', $header) as $part) {
            $index = strpos($part, '=');
            if ($index === false || $index === 0) {
                continue;
            }

            $key = trim(substr($part, 0, $index));
            $value = trim(substr($part, $index + 1));

            if ($key === 't') {
                if (preg_match('/^\d+$/', $value) !== 1) {
                    return null;
                }
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signature = $value;
            }
        }

        return ($timestamp > 0 && $signature !== '') ? [$timestamp, $signature] : null;
    }

    /**
     * The HMAC covers `"{timestamp}.{payload}"`, not the payload alone.
     *
     * That is what stops a captured request being replayed indefinitely: the timestamp is inside
     * the signature, so an attacker cannot rewrite it to look recent without invalidating the
     * whole thing.
     */
    private static function compute(string $secret, int $timestamp, string $payload): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }
}
