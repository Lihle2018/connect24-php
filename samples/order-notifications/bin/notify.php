<?php

/**
 * Runs the notifier for one demo order. Safe with a TEST key: the default recipient uses a
 * simulation local part (queued@) that never leaves the platform and costs nothing.
 *
 *     set CONNECT24_ACCOUNT_ID=acc_...
 *     set CONNECT24_API_KEY=ck_test_...
 *     php bin/notify.php
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Connect24\Client;
use Connect24\Samples\OrderNotifications\OrderNotifier;
use Connect24\Samples\OrderNotifications\OutOfCreditException;
use Connect24\Samples\OrderNotifications\SdkMessageSender;

$client = new Client(
    getenv('CONNECT24_ACCOUNT_ID') ?: exit("Set CONNECT24_ACCOUNT_ID.\n"),
    getenv('CONNECT24_API_KEY') ?: exit("Set CONNECT24_API_KEY.\n"),
);

$notifier = new OrderNotifier(new SdkMessageSender($client));

try {
    $result = $notifier->notifyShipped([
        'id' => '1042',
        'customerName' => 'Thandi',
        'email' => getenv('CONNECT24_EMAIL_TO') ?: 'queued@example.com',
        'phone' => getenv('CONNECT24_SMS_TO') ?: null,
        'trackingUrl' => 'https://store.example/track/1042',
    ]);
} catch (OutOfCreditException $exception) {
    fwrite(STDERR, 'Top up before retrying: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

echo "email: {$result['email']->status}  {$result['email']->detail}" . PHP_EOL;
echo "sms:   {$result['sms']->status}  {$result['sms']->detail}" . PHP_EOL;
