<?php

declare(strict_types=1);

namespace Connect24\Tests;

use Connect24\ApiException;
use Connect24\Client;
use Connect24\Resources\Suppressions;
use Connect24\Transport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** The client and the parts of the transport that do not need a network. */
final class ClientTest extends TestCase
{
    public function testRequiresAnAccountId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/account id/');

        new Client('', 'ck_live_x');
    }

    public function testRequiresAnApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/API key/');

        new Client('acc_1', '');
    }

    public function testExposesEveryResource(): void
    {
        $client = new Client('acc_1', 'ck_live_x');

        self::assertNotNull($client->messages);
        self::assertNotNull($client->templates);
        self::assertNotNull($client->suppressions);
        self::assertNotNull($client->webhooks);
        self::assertNotNull($client->sendingDomains);
        self::assertNotNull($client->billing);
        self::assertNotNull($client->account);
    }

    public function testDebugOutputDoesNotLeakTheKey(): void
    {
        // var_dump on a client is exactly what somebody does while debugging a failing send, and
        // the output ends up pasted into a ticket.
        $client = new Client('acc_1', 'ck_live_supersecret');

        $dumped = print_r($client->__debugInfo(), true);

        self::assertStringNotContainsString('ck_live_supersecret', $dumped);
        self::assertStringContainsString('acc_1', $dumped);
    }
}

/** The body shaping that happens before anything is sent. */
final class TransportBodyTest extends TestCase
{
    public function testDropsNullsBeforeSending(): void
    {
        // An absent `from` means "use my account's assigned address". An explicit null would be an
        // attempt to send from nothing, which the API rejects.
        $body = Transport::withoutNulls([
            'channel' => 'Sms',
            'to' => '+27821234567',
            'from' => null,
            'cc' => null,
        ]);

        self::assertSame(['channel' => 'Sms', 'to' => '+27821234567'], $body);
    }

    public function testDropsNullsInsideNestedArrays(): void
    {
        $body = Transport::withoutNulls([
            'content' => ['type' => 'text', 'text' => 'hi', 'subject' => null],
        ]);

        self::assertSame(['content' => ['type' => 'text', 'text' => 'hi']], $body);
    }
}

/** Turning an error response into something a caller can act on. */
final class ApiExceptionTest extends TestCase
{
    public function testReadsTheApiErrorMessage(): void
    {
        $error = ApiException::fromResponse(402, '{"error":"Insufficient credit."}');

        self::assertSame(402, $error->getStatusCode());
        self::assertStringContainsString('Insufficient credit.', $error->getMessage());
    }

    public function testReadsFieldLevelValidationErrors(): void
    {
        $body = '{"title":"Validation failed","errors":{"to":["Not a valid number."]}}';

        $error = ApiException::fromResponse(400, $body);

        self::assertSame(['Not a valid number.'], $error->getErrors()['to']);
        self::assertStringContainsString('to: Not a valid number.', $error->getMessage());
    }

    public function testSurvivesABodyThatIsNotJson(): void
    {
        // A proxy returning an HTML 502 must not become a JSON parse error that hides the 502.
        $error = ApiException::fromResponse(502, '<html>Bad Gateway</html>');

        self::assertSame(502, $error->getStatusCode());
        self::assertStringContainsString('502', $error->getMessage());
    }

    public function testSurvivesAnEmptyBody(): void
    {
        self::assertSame(500, ApiException::fromResponse(500, '')->getStatusCode());
        self::assertSame(500, ApiException::fromResponse(500, null)->getStatusCode());
    }
}

/** Which suppressions the sender is allowed to remove. */
final class SuppressionsTest extends TestCase
{
    public function testKnowsWhichSuppressionsTheSenderMayNotRemove(): void
    {
        self::assertTrue(Suppressions::chosenByRecipient('Unsubscribed'));
        self::assertTrue(Suppressions::chosenByRecipient('StopReply'));
        self::assertTrue(Suppressions::chosenByRecipient('OptOutRegistry'));
        self::assertFalse(Suppressions::chosenByRecipient('HardBounce'));
    }
}
