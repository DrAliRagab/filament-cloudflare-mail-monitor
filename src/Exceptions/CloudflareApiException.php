<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Exceptions;

use Stringable;

final class CloudflareApiException extends CloudflareMailMonitorException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<int, array<string, mixed>>  $errors
     */
    public static function forGraphqlErrors(array $errors): self
    {
        $messages = array_map(
            static fn (array $error): string => self::errorMessage($error['message'] ?? null),
            $errors,
        );

        return new self('Cloudflare GraphQL request failed: '.implode('; ', $messages));
    }

    public static function forHttpFailure(int $status): self
    {
        return new self(sprintf('Cloudflare API request failed with HTTP status %d.', $status), $status);
    }

    public static function forMalformedResponse(): self
    {
        return new self('Cloudflare API returned a malformed GraphQL response.');
    }

    public function isAuthorizationFailure(): bool
    {
        return in_array($this->statusCode, [401, 403], true)
            || str_contains(strtolower($this->getMessage()), 'authorization')
            || str_contains(strtolower($this->getMessage()), 'permission');
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    private static function errorMessage(mixed $message): string
    {
        if (is_string($message)) {
            return $message;
        }

        if (is_scalar($message) || $message instanceof Stringable) {
            return (string) $message;
        }

        return 'Unknown GraphQL error';
    }
}
