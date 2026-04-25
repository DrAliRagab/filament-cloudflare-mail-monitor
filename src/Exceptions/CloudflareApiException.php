<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Exceptions;

use Stringable;

final class CloudflareApiException extends CloudflareMailMonitorException
{
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
        return new self(sprintf('Cloudflare API request failed with HTTP status %d.', $status));
    }

    public static function forMalformedResponse(): self
    {
        return new self('Cloudflare API returned a malformed GraphQL response.');
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
