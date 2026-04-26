<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Services;

use DrAliRagab\FilamentCloudflareMailMonitor\Data\ConfiguredZone;
use DrAliRagab\FilamentCloudflareMailMonitor\Data\EmailSuppressionData;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\MissingCloudflareConfiguration;
use DrAliRagab\FilamentCloudflareMailMonitor\Models\CloudflareMailSuppression;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;

final readonly class CloudflareMailSuppressionFetcher
{
    public function __construct(
        private HttpFactory $httpFactory,
    ) {}

    public function fetch(): int
    {
        $stored = 0;

        foreach (Config::zones() as $zoneConfig) {
            $zone = ConfiguredZone::fromArray($zoneConfig);

            foreach ($this->suppressionsForZone($zone) as $suppression) {
                CloudflareMailSuppression::query()->updateOrCreate(
                    [
                        'zone_id' => $suppression->zone->id,
                        'suppression_id' => $suppression->id,
                    ],
                    $suppression->toDatabaseAttributes(),
                );

                ++$stored;
            }
        }

        return $stored;
    }

    /**
     * @return list<EmailSuppressionData>
     */
    private function suppressionsForZone(ConfiguredZone $configuredZone): array
    {
        $page = 1;
        $perPage = min(max(Config::integer('suppressions.page_size', 100), 1), 1000);
        $suppressions = [];

        do {
            $payload = $this->get(sprintf('/zones/%s/email/sending/suppression', rawurlencode($configuredZone->id)), [
                'page' => $page,
                'per_page' => $perPage,
                'order' => 'created_at',
                'direction' => 'desc',
            ]);

            $result = Arr::get($payload, 'result', []);

            if (! is_array($result)) {
                throw CloudflareApiException::forMalformedRestResponse();
            }

            foreach ($result as $suppression) {
                if (! is_array($suppression)) {
                    continue;
                }

                $suppressions[] = EmailSuppressionData::fromCloudflare($configuredZone, $this->stringKeyedArray($suppression));
            }

            $total = $this->integer(Arr::get($payload, 'total'), count($suppressions));
            $currentPageCount = count($result);
            ++$page;
        } while ($currentPageCount === $perPage && count($suppressions) < $total);

        return $suppressions;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $token = Config::string('api.token');

        if ($token === null || $token === '') {
            throw MissingCloudflareConfiguration::apiToken();
        }

        $baseUrl = rtrim((string) Config::string('api.base_url', 'https://api.cloudflare.com/client/v4'), '/');

        $response = $this->httpFactory
            ->withToken($token)
            ->acceptJson()
            ->timeout(Config::integer('api.timeout', 15))
            ->retry(
                Config::integer('api.retry_times', 2),
                Config::integer('api.retry_sleep_milliseconds', 250),
                throw: false,
            )
            ->get($baseUrl.$path, $query);

        if ($response->failed()) {
            throw CloudflareApiException::forHttpFailure($response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw CloudflareApiException::forMalformedRestResponse();
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $stringKeyed = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $stringKeyed[$key] = $value;
            }
        }

        return $stringKeyed;
    }

    private function integer(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
