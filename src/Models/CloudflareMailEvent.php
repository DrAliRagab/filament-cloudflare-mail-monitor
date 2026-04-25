<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_hash
 * @property string $zone_id
 * @property string|null $zone_name
 * @property CarbonImmutable $occurred_at
 * @property string|null $message_id
 * @property string|null $session_id
 * @property string|null $from
 * @property string|null $to
 * @property string|null $subject
 * @property string|null $status
 * @property string|null $event_type
 * @property string|null $sending_domain
 * @property string|null $error_cause
 * @property string|null $error_detail
 * @property string|null $arc
 * @property string|null $dkim
 * @property string|null $dmarc
 * @property string|null $spf
 * @property bool $is_spam
 * @property bool $is_ndr
 * @property array<string, mixed>|null $raw
 *
 * @method static Builder<self> query()
 */
final class CloudflareMailEvent extends Model
{
    protected $table = 'cloudflare_mail_monitor_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_hash',
        'zone_id',
        'zone_name',
        'occurred_at',
        'message_id',
        'session_id',
        'from',
        'to',
        'subject',
        'status',
        'event_type',
        'sending_domain',
        'error_cause',
        'error_detail',
        'arc',
        'dkim',
        'dmarc',
        'spf',
        'is_spam',
        'is_ndr',
        'raw',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'is_spam' => 'boolean',
            'is_ndr' => 'boolean',
            'raw' => 'array',
        ];
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeForZone(Builder $query, string $zoneId): Builder
    {
        return $query->where('zone_id', $zoneId);
    }

    /**
     * @param Builder<self> $query
     *
     * @return Builder<self>
     */
    public function scopeBetween(Builder $query, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return $query->whereBetween('occurred_at', [$start, $end]);
    }
}
