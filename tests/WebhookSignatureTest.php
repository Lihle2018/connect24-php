<?php

declare(strict_types=1);

namespace Connect24\Tests;

use Connect24\WebhookSignature;
use PHPUnit\Framework\TestCase;

/**
 * Signature verification.
 *
 * Tested harder than anything else here, because it is the only part of the SDK that is a security
 * control. Everything else fails loudly when it is wrong; this one fails by quietly accepting a
 * forged request.
 */
final class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test';
    private const PAYLOAD = '{"id":"evt_1","type":"message.delivered","messageId":"msg_1"}';

    private function header(?string $payload = null, ?string $secret = null, ?int $at = null): string
    {
        $payload ??= self::PAYLOAD;
        $secret ??= self::SECRET;
        $at ??= time();

        return sprintf('t=%d,v1=%s', $at, hash_hmac('sha256', $at . '.' . $payload, $secret));
    }

    public function testAcceptsAGenuineRecentDelivery(): void
    {
        self::assertTrue(WebhookSignature::isValid(self::PAYLOAD, $this->header(), self::SECRET));
    }

    public function testRejectsAPayloadThatWasAltered(): void
    {
        $tampered = str_replace('delivered', 'bounced', self::PAYLOAD);

        self::assertFalse(WebhookSignature::isValid($tampered, $this->header(), self::SECRET));
    }

    public function testRejectsTheWrongSecret(): void
    {
        $header = $this->header(null, 'whsec_other');

        self::assertFalse(WebhookSignature::isValid(self::PAYLOAD, $header, self::SECRET));
    }

    public function testRejectsADeliveryThatIsTooOld(): void
    {
        // The signature is still valid; the point is that a captured request cannot be replayed
        // tomorrow.
        $header = $this->header(null, null, time() - 3600);

        self::assertFalse(WebhookSignature::isValid(self::PAYLOAD, $header, self::SECRET));
    }

    public function testRejectsADeliveryStampedInTheFuture(): void
    {
        // Not symmetry for its own sake: a future timestamp means a forged request or a badly
        // skewed clock, and neither should be trusted.
        $header = $this->header(null, null, time() + 3600);

        self::assertFalse(WebhookSignature::isValid(self::PAYLOAD, $header, self::SECRET));
    }

    public function testToleranceOfZeroSkipsTheAgeCheck(): void
    {
        $header = $this->header(null, null, 1600000000);

        self::assertTrue(WebhookSignature::isValid(self::PAYLOAD, $header, self::SECRET, 0));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedHeaders(): array
    {
        return [
            'empty' => [''],
            'garbage' => ['garbage'],
            'no timestamp value' => ['t=,v1=abc'],
            'timestamp not a number' => ['t=notanumber,v1=abc'],
            'no timestamp' => ['v1=abc'],
            'no signature' => ['t=1770000000'],
            'empty signature' => ['t=1770000000,v1='],
        ];
    }

    /**
     * @dataProvider malformedHeaders
     */
    public function testAMalformedHeaderReturnsFalseRatherThanThrowing(string $header): void
    {
        // An exception here would let an attacker turn a forged request into a 500, which is a
        // denial of service on an endpoint meant to be resilient.
        self::assertFalse(WebhookSignature::isValid(self::PAYLOAD, $header, self::SECRET));
    }

    public function testMissingInputsReturnFalse(): void
    {
        self::assertFalse(WebhookSignature::isValid('', $this->header(), self::SECRET));
        self::assertFalse(WebhookSignature::isValid(self::PAYLOAD, '', self::SECRET));
        self::assertFalse(WebhookSignature::isValid(self::PAYLOAD, $this->header(), ''));
    }

    public function testTimestampOfReadsTheHeader(): void
    {
        self::assertSame(1770000000, WebhookSignature::timestampOf($this->header(null, null, 1770000000)));
    }

    public function testTimestampOfReturnsNullWhenMalformed(): void
    {
        self::assertNull(WebhookSignature::timestampOf('nonsense'));
    }
}
