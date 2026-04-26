<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Models;

use Carbon\CarbonImmutable;
use DrAliRagab\FilamentCloudflareMailMonitor\Events\CloudflareMailSuppressionCreated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * @property int $id
 * @property string $suppression_id
 * @property string $zone_id
 * @property string|null $zone_name
 * @property string $email
 * @property string $reason
 * @property CarbonImmutable $suppressed_at
 * @property CarbonImmutable|null $expires_at
 * @property list<string>|null $cloudflare_zones
 * @property array<string, mixed>|null $raw
 *
 * @method static Builder<self> query()
 */
final class CloudflareMailSuppression extends Model
{
    use Prunable;

    protected $table = 'cloudflare_mail_monitor_suppressions';

    /**
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => CloudflareMailSuppressionCreated::class,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'suppression_id',
        'zone_id',
        'zone_name',
        'email',
        'reason',
        'suppressed_at',
        'expires_at',
        'cloudflare_zones',
        'raw',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'suppressed_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'cloudflare_zones' => 'array',
            'raw' => 'array',
        ];
    }

    /**
     * @param  Builder<self>  $builder
     */
    public function scopeForZone(Builder $builder, string $zoneId): void
    {
        $builder->where('zone_id', $zoneId);
    }

    /**
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', CarbonImmutable::now('UTC'));
    }
}
