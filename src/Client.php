<?php

declare(strict_types=1);

namespace Connect24;

use Connect24\Resources\Account;
use Connect24\Resources\Billing;
use Connect24\Resources\Messages;
use Connect24\Resources\SendingDomains;
use Connect24\Resources\Suppressions;
use Connect24\Resources\Templates;
use Connect24\Resources\Webhooks;
use InvalidArgumentException;
use RuntimeException;

/**
 * Entry point to the Connect24 communications API. Create one and reuse it.
 *
 * Both credentials come from the portal, under **Settings → API keys**. The account id (`acc_…`)
 * is safe to commit; the key (`ck_live_…`) is a secret and belongs in an environment variable or a
 * secret store, never in source control — anyone holding it can send messages billed to you and
 * attributed to you.
 *
 * ```php
 * use Connect24\Client;
 *
 * $client = new Client('acc_3f9c1a7b4e2d', getenv('CONNECT24_API_KEY'));
 * $client->messages->sendSms('+27821234567', 'Your order has shipped.');
 * ```
 *
 * Or read both from the environment, which is what most deployments want:
 *
 * ```php
 * $client = Client::fromEnv();
 * ```
 */
final class Client
{
    /** Where the API lives. Override to point at a sandbox. */
    public const DEFAULT_BASE_URL = 'https://api.connect24.co.za';

    public const DEFAULT_TIMEOUT_SECONDS = 30;

    /**
     * Retries apply to rate limits, 5xx and connection failures — never to a 4xx, which would fail
     * identically however many times it is repeated.
     */
    public const DEFAULT_MAX_RETRIES = 2;

    public string $accountId;

    /** Send messages, and read back what happened to them. */
    public Messages $messages;
    /** Stored bodies with placeholders. */
    public Templates $templates;
    /** Addresses that will not be sent to. */
    public Suppressions $suppressions;
    /** Delivery events pushed to you. */
    public Webhooks $webhooks;
    /** Domains you have proved you control. */
    public SendingDomains $sendingDomains;
    /** Prepaid credit and the statement. */
    public Billing $billing;
    /** Who you are, and which channels can send right now. */
    public Account $account;

    public function __construct(
        string $accountId,
        string $apiKey,
        string $baseUrl = self::DEFAULT_BASE_URL,
        int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        int $maxRetries = self::DEFAULT_MAX_RETRIES
    ) {
        if ($accountId === '') {
            throw new InvalidArgumentException(
                'An account id is required — find it in the portal under Settings, API keys.'
            );
        }
        if ($apiKey === '') {
            throw new InvalidArgumentException(
                'An API key is required — find it in the portal under Settings, API keys.'
            );
        }

        $transport = new Transport($baseUrl, $accountId, $apiKey, $timeoutSeconds, $maxRetries);

        $this->accountId = $accountId;
        $this->messages = new Messages($transport);
        $this->templates = new Templates($transport);
        $this->suppressions = new Suppressions($transport);
        $this->webhooks = new Webhooks($transport);
        $this->sendingDomains = new SendingDomains($transport);
        $this->billing = new Billing($transport);
        $this->account = new Account($transport);
    }

    /**
     * Builds a client from CONNECT24_ACCOUNT_ID and CONNECT24_API_KEY.
     *
     * CONNECT24_BASE_URL is honoured too, which is how a staging deployment points elsewhere
     * without a code change.
     */
    public static function fromEnv(): self
    {
        $accountId = (string) (getenv('CONNECT24_ACCOUNT_ID') ?: '');
        $apiKey = (string) (getenv('CONNECT24_API_KEY') ?: '');

        $missing = [];
        if ($accountId === '') {
            $missing[] = 'CONNECT24_ACCOUNT_ID';
        }
        if ($apiKey === '') {
            $missing[] = 'CONNECT24_API_KEY';
        }

        if ($missing !== []) {
            throw new RuntimeException('Missing environment variable(s): ' . implode(', ', $missing) . '.');
        }

        $baseUrl = (string) (getenv('CONNECT24_BASE_URL') ?: self::DEFAULT_BASE_URL);

        return new self($accountId, $apiKey, $baseUrl);
    }

    /**
     * The key is deliberately absent: an object that dumps its own credential ends up in a log,
     * and var_dump on a client is exactly what somebody does while debugging a failing send.
     *
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['accountId' => $this->accountId];
    }
}
