<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Data;

use InvalidArgumentException;

final readonly class ConfiguredZone
{
    public function __construct(
        public string $id,
        public ?string $name = null,
    ) {
        if ($this->id === '') {
            throw new InvalidArgumentException('Cloudflare zone ID cannot be empty.');
        }
    }

    /**
     * @param  array{id?: string|null, name?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $id = $data['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new InvalidArgumentException('Cloudflare zone ID must be configured.');
        }

        return new self(
            id: $id,
            name: isset($data['name']) && $data['name'] !== '' ? $data['name'] : null,
        );
    }
}
