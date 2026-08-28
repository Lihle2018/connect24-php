<?php

declare(strict_types=1);

namespace Connect24;

/**
 * The API answered, and said no.
 *
 * `getStatusCode()` says what kind of problem it was:
 *
 * - 400 — malformed request; repeating it unchanged fails again
 * - 401 — key is wrong, revoked, or belongs to another account
 * - 402 — out of credit; retrying will not help
 * - 409 — a conflict, usually a name already taken
 * - 429 — rate limited (already retried a few times before you see this)
 * - 502 — the upstream provider refused the message or could not be reached
 */
class ApiException extends Connect24Exception
{
    private int $statusCode;

    /** @var array<string, list<string>> */
    private array $errors;

    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(int $statusCode, string $message, array $errors = [])
    {
        $detail = [];
        foreach ($errors as $field => $messages) {
            $detail[] = $field . ': ' . implode(', ', $messages);
        }

        parent::__construct($detail === [] ? $message : $message . ' (' . implode('; ', $detail) . ')');

        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Field-level validation messages, when the API returned any.
     *
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Unwraps whichever error shape the API used, so the message says what actually happened.
     *
     * Falling back to the status code is deliberate rather than lazy: an error body that is HTML
     * from a proxy, or empty, should still produce something actionable instead of a JSON parse
     * failure hiding the real problem.
     */
    public static function fromResponse(int $statusCode, ?string $body): self
    {
        if ($body === null || trim($body) === '') {
            return new self($statusCode, sprintf('The request failed (%d).', $statusCode));
        }

        $parsed = json_decode($body, true);

        if (!is_array($parsed)) {
            return new self(
                $statusCode,
                sprintf('The request failed (%d): %s', $statusCode, substr($body, 0, 200))
            );
        }

        $errors = [];
        if (isset($parsed['errors']) && is_array($parsed['errors'])) {
            foreach ($parsed['errors'] as $field => $messages) {
                $errors[(string) $field] = is_array($messages)
                    ? array_values(array_map('strval', $messages))
                    : [(string) $messages];
            }
        }

        $message = $parsed['error']
            ?? $parsed['detail']
            ?? $parsed['title']
            ?? $parsed['message']
            ?? sprintf('The request failed (%d).', $statusCode);

        return new self($statusCode, (string) $message, $errors);
    }
}
