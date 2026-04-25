<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Data;

use Carbon\CarbonImmutable;

final readonly class EmailEventData
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public ConfiguredZone $zone,
        public CarbonImmutable $occurredAt,
        public ?string $messageId,
        public ?string $sessionId,
        public ?string $from,
        public ?string $to,
        public ?string $subject,
        public ?string $status,
        public ?string $eventType,
        public ?string $sendingDomain,
        public ?string $errorCause,
        public ?string $errorDetail,
        public ?string $arc,
        public ?string $dkim,
        public ?string $dmarc,
        public ?string $spf,
        public bool $isSpam,
        public bool $isNdr,
        public array $raw,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromCloudflare(ConfiguredZone $zone, array $payload): self
    {
        return new self(
            zone: $zone,
            occurredAt: CarbonImmutable::parse((string) ($payload['datetime'] ?? 'now'), 'UTC')->utc(),
            messageId: self::nullableString($payload['messageId'] ?? null),
            sessionId: self::nullableString($payload['sessionId'] ?? null),
            from: self::nullableString($payload['from'] ?? null),
            to: self::nullableString($payload['to'] ?? null),
            subject: self::nullableString($payload['subject'] ?? null),
            status: self::nullableString($payload['status'] ?? null),
            eventType: self::nullableString($payload['eventType'] ?? null),
            sendingDomain: self::nullableString($payload['sendingDomain'] ?? null),
            errorCause: self::nullableString($payload['errorCause'] ?? null),
            errorDetail: self::nullableString($payload['errorDetail'] ?? null),
            arc: self::nullableString($payload['arc'] ?? null),
            dkim: self::nullableString($payload['dkim'] ?? null),
            dmarc: self::nullableString($payload['dmarc'] ?? null),
            spf: self::nullableString($payload['spf'] ?? null),
            isSpam: (bool) ($payload['isSpam'] ?? false),
            isNdr: (bool) ($payload['isNDR'] ?? $payload['isNdr'] ?? false),
            raw: $payload,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabaseAttributes(): array
    {
        return [
            'event_hash' => $this->eventHash(),
            'zone_id' => $this->zone->id,
            'zone_name' => $this->zone->name,
            'occurred_at' => $this->occurredAt,
            'message_id' => $this->messageId,
            'session_id' => $this->sessionId,
            'from' => $this->from,
            'to' => $this->to,
            'subject' => $this->subject,
            'status' => $this->status,
            'event_type' => $this->eventType,
            'sending_domain' => $this->sendingDomain,
            'error_cause' => $this->errorCause,
            'error_detail' => $this->errorDetail,
            'arc' => $this->arc,
            'dkim' => $this->dkim,
            'dmarc' => $this->dmarc,
            'spf' => $this->spf,
            'is_spam' => $this->isSpam,
            'is_ndr' => $this->isNdr,
            'raw' => $this->raw,
        ];
    }

    public function eventHash(): string
    {
        return hash('sha256', implode('|', [
            $this->zone->id,
            $this->occurredAt->toIso8601String(),
            $this->messageId ?? '',
            $this->sessionId ?? '',
            $this->to ?? '',
            $this->status ?? '',
            $this->eventType ?? '',
            $this->errorCause ?? '',
        ]));
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }
}
