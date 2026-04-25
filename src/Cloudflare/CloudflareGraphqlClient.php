<?php

declare(strict_types=1);

namespace DrAliRagab\FilamentCloudflareMailMonitor\Cloudflare;

use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\CloudflareApiException;
use DrAliRagab\FilamentCloudflareMailMonitor\Exceptions\MissingCloudflareConfiguration;
use DrAliRagab\FilamentCloudflareMailMonitor\Support\Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Arr;

final readonly class CloudflareGraphqlClient
{
    public function __construct(
        private HttpFactory $httpFactory,
    ) {}

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    public function query(string $query, array $variables = []): array
    {
        $token = Config::string('api.token');

        if ($token === null || $token === '') {
            throw MissingCloudflareConfiguration::apiToken();
        }

        $baseUrl = rtrim((string) Config::string('api.base_url', 'https://api.cloudflare.com/client/v4'), '/');

        $response = $this->httpFactory
            ->withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(Config::integer('api.timeout', 15))
            ->retry(
                Config::integer('api.retry_times', 2),
                Config::integer('api.retry_sleep_milliseconds', 250),
                throw: false,
            )
            ->post($baseUrl.'/graphql', [
                'query' => $query,
                'variables' => $variables,
            ]);

        if ($response->failed()) {
            throw CloudflareApiException::forHttpFailure($response->status());
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw CloudflareApiException::forMalformedResponse();
        }

        $errors = Arr::get($payload, 'errors');

        if (is_array($errors) && $errors !== []) {
            /** @var array<int, array<string, mixed>> $errors */
            throw CloudflareApiException::forGraphqlErrors($errors);
        }

        $data = Arr::get($payload, 'data');

        if (! is_array($data)) {
            throw CloudflareApiException::forMalformedResponse();
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
