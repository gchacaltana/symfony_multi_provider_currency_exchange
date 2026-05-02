<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\ProviderRate;
use App\Exception\ProviderUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Frankfurter REST integration configured via `FRANKFURTER_BASE_URL`.
 */
#[AutoconfigureTag('app.exchange_rate_provider')]
final class FrankfurterProvider implements ExchangeRateProviderInterface
{
    /**
     * @param HttpClientInterface $httpClient Symfony outbound HTTP client
     * @param string              $baseUrl    Frankfurter API origin/path configured via env/services parameters
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
    ) {
    }

    public function getName(): string
    {
        return 'frankfurter';
    }

    public function createRatesRequest(string $base, array $symbols, string $date): ResponseInterface
    {
        return $this->httpClient->request('GET', sprintf('%s/%s', rtrim($this->baseUrl, '/'), $date), [
            'query' => [
                'base' => $base,
                'symbols' => implode(',', $symbols),
            ],
        ]);
    }

    /** {@inheritdoc} */
    public function decodeRatesResponse(ResponseInterface $response): ProviderRate
    {
        try {
            $data = $response->toArray();

            return new ProviderRate(
                providerName: $this->getName(),
                base: $data['base'],
                date: $data['date'],
                rates: $data['rates'],
            );
        } catch (\Throwable $e) {
            throw new ProviderUnavailableException($this->getName(), $e->getMessage(), $e);
        }
    }
}
