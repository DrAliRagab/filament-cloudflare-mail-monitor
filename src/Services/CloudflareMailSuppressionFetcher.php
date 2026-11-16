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
        $zones = array_map(ConfiguredZone::fromArray(...), Config::zones());

        if ($zones === []) {
            return 0;
        }

        $accountId = Config::string('account_id');

        if ($accountId === null || trim($accountId) === '') {
            throw MissingCloudflareConfiguration::accountId();
        }

        foreach ($zones as $zone) {
            if ($zone->name === null || trim($zone->name) === '') {
                throw MissingCloudflareConfiguration::zoneName($zone->id);
            }
        }

        $stored = 0;

        foreach ($this->suppressionsForAccount($accountId) as $payload) {
            foreach ($this->zonesForSuppression($zones, $payload) as $zone) {
                $suppression = EmailSuppressionData::fromCloudflare($zone, $payload);
                CloudflareMailSuppression::query()->updateOrCreate(
                    ['zone_id' => $zone->id, 'suppression_id' => $suppression->id],
                    $suppression->toDatabaseAttributes(),
                );
                ++$stored;
            }
        }

        return $stored;
    }

    /**
     * @param  list<ConfiguredZone>  $zones
     * @param  array<string, mixed>  $payload
     * @return list<ConfiguredZone>
     */
    private function zonesForSuppression(array $zones, array $payload): array
    {
        if (Arr::get($payload, 'scope.type') === 'account') {
            return $zones;
        }

        $domain = Arr::get($payload, 'scope.value');
        if (Arr::get($payload, 'scope.type') !== 'sending_domain' || ! is_string($domain)) {
            return [];
        }

        $domain = strtolower(rtrim($domain, '.'));
        $match = null;
        $matchLength = 0;
        foreach ($zones as $zone) {
            $name = strtolower(rtrim(trim((string) $zone->name), '.'));
            if ($name !== '' && ($domain === $name || str_ends_with($domain, '.'.$name)) && strlen($name) > $matchLength) {
                $match = $zone;
                $matchLength = strlen($name);
            }
        }

        return $match === null ? [] : [$match];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suppressionsForAccount(string $accountId): array
    {
        $perPage = min(max(Config::integer('suppressions.page_size', 100), 1), 1000);
        $cursor = null;
        $seen = [];
        $suppressions = [];

        do {
            $query = ['per_page' => $perPage];
            if ($cursor !== null) {
                $query['cursor'] = $cursor;
            }

            $payload = $this->get(sprintf('/accounts/%s/email/sending/suppressions', rawurlencode($accountId)), $query);
            $result = $payload['result'] ?? null;
            if (($payload['success'] ?? true) !== true || ! is_array($result) || ! array_is_list($result)) {
                throw CloudflareApiException::forMalformedRestResponse();
            }

            foreach ($result as $suppression) {
                if (is_array($suppression)) {
                    $suppressions[] = $this->stringKeyedArray($suppression);
                }
            }

            $cursor = Arr::get($payload, 'result_info.next_cursor');
            if ($cursor === '') {
                $cursor = null;
            }

            if ($cursor !== null) {
                if (! is_string($cursor) || in_array($cursor, $seen, true)) {
                    throw CloudflareApiException::forMalformedRestResponse();
                }

                $seen[] = $cursor;
            }
        } while ($cursor !== null);

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
}
