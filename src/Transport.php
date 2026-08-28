<?php

declare(strict_types=1);

namespace Connect24;

/**
 * Shared request plumbing.
 *
 * Internal to the package: the shape of a Connect24 call is not part of the public surface, so it
 * can change without breaking anyone.
 *
 * Built on cURL, which ships with essentially every PHP install, rather than on Guzzle. An SDK is
 * a dependency of somebody else's application, and pulling in an HTTP client is the single most
 * common way a package causes a version conflict that becomes theirs to resolve.
 *
 * @internal
 */
final class Transport
{
    /**
     * Statuses worth trying again. A 429 says the limit is temporary; 5xx says the fault was ours.
     * A 4xx means the request itself is wrong, and repeating it changes nothing.
     */
    private const RETRYABLE = [429, 500, 502, 503, 504];

    private string $baseUrl;
    private string $accountId;
    private string $apiKey;
    private int $timeoutSeconds;
    private int $maxRetries;

    public function __construct(
        string $baseUrl,
        string $accountId,
        string $apiKey,
        int $timeoutSeconds,
        int $maxRetries
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->accountId = $accountId;
        $this->apiKey = $apiKey;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->maxRetries = $maxRetries;
    }

    /** @return array<mixed>|null */
    public function get(string $path): ?array
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<mixed>|null $body
     * @return array<mixed>|null
     */
    public function post(string $path, ?array $body = null, ?string $idempotencyKey = null): ?array
    {
        return $this->request('POST', $path, $body, $idempotencyKey);
    }

    /**
     * @param array<mixed>|null $body
     * @return array<mixed>|null
     */
    public function put(string $path, ?array $body = null): ?array
    {
        return $this->request('PUT', $path, $body);
    }

    public function delete(string $path): void
    {
        $this->request('DELETE', $path);
    }

    /**
     * @param array<mixed>|null $body
     * @return array<mixed>|null
     */
    private function request(
        string $method,
        string $path,
        ?array $body = null,
        ?string $idempotencyKey = null
    ): ?array {
        $url = $this->baseUrl . '/' . ltrim($path, '/');

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'X-Account-Id: ' . $this->accountId,
            'Accept: application/json',
            'User-Agent: connect24-php',
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode(self::withoutNulls($body), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }

        for ($attempt = 0; ; $attempt++) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new ConnectionException('Could not initialise a cURL handle.');
            }

            curl_setopt_array($handle, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
                // Never turn these off. Without them the "https" in the URL is decoration, and a
                // key travels to whoever answered the DNS query.
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            if ($payload !== null) {
                curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
            }

            $raw = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);

            if ($raw === false) {
                // The request never got an answer, so whether it was applied is unknown. Retrying
                // is safe only because callers can pass an idempotency key.
                if ($attempt < $this->maxRetries) {
                    usleep(self::backoffMicroseconds($attempt + 1));
                    continue;
                }
                throw new ConnectionException($error !== '' ? $error : 'The request could not be sent.');
            }

            $text = (string) $raw;

            if ($status >= 400) {
                if (in_array($status, self::RETRYABLE, true) && $attempt < $this->maxRetries) {
                    usleep(self::backoffMicroseconds($attempt + 1));
                    continue;
                }
                throw ApiException::fromResponse($status, $text);
            }

            if (trim($text) === '') {
                return null;
            }

            $decoded = json_decode($text, true);

            return is_array($decoded) ? $decoded : null;
        }
    }

    /** Doubling, capped. Long enough to let a rate limit clear without stalling a request. */
    private static function backoffMicroseconds(int $attempt): int
    {
        return (int) min(500_000 * (2 ** ($attempt - 1)), 4_000_000);
    }

    /**
     * Drops null members before sending.
     *
     * The API treats an absent field and an explicit null differently in places — an absent `from`
     * means "use my account's assigned address", where a null would be an attempt to send from
     * nothing.
     *
     * @param array<mixed> $value
     * @return array<mixed>
     */
    public static function withoutNulls(array $value): array
    {
        $result = [];

        foreach ($value as $key => $member) {
            if ($member === null) {
                continue;
            }
            $result[$key] = is_array($member) ? self::withoutNulls($member) : $member;
        }

        return $result;
    }
}
