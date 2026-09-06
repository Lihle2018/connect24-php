<?php

/**
 * These tests mock the sample's own MessageSenderPort — the pattern to copy in your app,
 * since the SDK's classes are final. They prove the behaviour that matters: idempotency
 * keys tied to the order, suppression handled as a skip, out-of-credit surfaced loudly.
 * Run with `composer install && composer test` in this folder.
 */

declare(strict_types=1);

namespace Connect24\Samples\OrderNotifications\Tests;

use Connect24\ApiException;
use Connect24\Samples\OrderNotifications\MessageSenderPort;
use Connect24\Samples\OrderNotifications\OrderNotifier;
use Connect24\Samples\OrderNotifications\OutOfCreditException;
use PHPUnit\Framework\TestCase;

final class OrderNotifierTest extends TestCase
{
    /** @var array{id: string, customerName: string, email: string, trackingUrl: string, phone: string} */
    private const ORDER = [
        'id' => '1042',
        'customerName' => 'Thandi',
        'email' => 'thandi@example.co.za',
        'phone' => '+27821234567',
        'trackingUrl' => 'https://store.example/track/1042',
    ];

    public function testSendsEmailAndSmsWithIdempotencyKeysTiedToTheOrder(): void
    {
        $messages = $this->createMock(MessageSenderPort::class);

        $messages->expects(self::once())
            ->method('sendEmail')
            ->with(
                'thandi@example.co.za',
                self::stringContains('1042'),
                self::stringContains('Track it here'),
                self::stringContains('1042'),
                'order-1042-shipped-email',
            )
            ->willReturn(['id' => 'msg_1', 'status' => 'queued']);

        $messages->expects(self::once())
            ->method('sendSms')
            ->with('+27821234567', self::stringContains('1042'), 'order-1042-shipped-sms')
            ->willReturn(['id' => 'msg_2', 'status' => 'queued']);

        $result = (new OrderNotifier($messages))->notifyShipped(self::ORDER);

        self::assertSame('sent', $result['email']->status);
        self::assertSame('msg_1', $result['email']->detail);
        self::assertSame('sent', $result['sms']->status);
    }

    public function testSkipsTheSmsWhenNoPhoneIsOnFile(): void
    {
        $messages = $this->createMock(MessageSenderPort::class);
        $messages->method('sendEmail')->willReturn(['id' => 'msg_1']);
        $messages->expects(self::never())->method('sendSms');

        $order = self::ORDER;
        unset($order['phone']);

        $result = (new OrderNotifier($messages))->notifyShipped($order);

        self::assertSame('skipped', $result['sms']->status);
    }

    public function testTreatsASuppressedRecipientAsASkipNotAFailure(): void
    {
        $messages = $this->createMock(MessageSenderPort::class);
        $messages->method('sendEmail')->willThrowException(new ApiException(
            403,
            'That recipient is on your suppression list and cannot be contacted.',
        ));
        $messages->method('sendSms')->willReturn(['id' => 'msg_2', 'status' => 'queued']);

        $result = (new OrderNotifier($messages))->notifyShipped(self::ORDER);

        self::assertSame('skipped', $result['email']->status);
        self::assertStringContainsString('suppression list', $result['email']->detail);
        // One refused channel must not block the other.
        self::assertSame('sent', $result['sms']->status);
    }

    public function testSurfacesAnEmptyBalanceAsOutOfCredit(): void
    {
        $messages = $this->createMock(MessageSenderPort::class);
        $messages->method('sendEmail')->willThrowException(new ApiException(402, 'Insufficient credit.'));

        $this->expectException(OutOfCreditException::class);

        (new OrderNotifier($messages))->notifyShipped(self::ORDER);
    }

    public function testLetsUnexpectedErrorsEscapeForTheCallerToRetry(): void
    {
        $messages = $this->createMock(MessageSenderPort::class);
        $messages->method('sendEmail')->willThrowException(new ApiException(500, 'boom'));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('boom');

        (new OrderNotifier($messages))->notifyShipped(self::ORDER);
    }
}
