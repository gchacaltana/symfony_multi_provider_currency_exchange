<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\ProviderRate;
use App\Exception\ProviderUnavailableException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ExchangeRatesAPI.io backend wired via `EXCHANGERATES_BASE_URL` / optional `EXCHANGERATES_API_KEY`.
 */
#[AutoconfigureTag('app.exchange_rate_provider')]
final class ExchangeRatesApiProvider implements ExchangeRateProviderInterface
{
    /**
     * @param HttpClientInterface $httpClient Symfony outbound HTTP client
     * @param string              $baseUrl    Vendor-specific REST prefix rooted inside DI configuration
     * @param string              $apiKey     Blank skips attaching `access_key` automatically during outbound GET preparation.
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
    ) {
    }

    public function getName(): string
    {
        return 'exchangeratesapi';
    }

    public function createRatesRequest(string $base, array $symbols, string $date): ResponseInterface
    {
        $query = [
            'base' => $base,
            'symbols' => implode(',', $symbols),
        ];

        if ($this->apiKey !== '') {
            $query['access_key'] = $this->apiKey;
        }

        return $this->httpClient->request(
            'GET',
            sprintf('%s/%s', rtrim($this->baseUrl, '/'), $date),
            ['query' => $query],
        );
    }

    /** {@inheritdoc} */
    public function decodeRatesResponse(ResponseInterface $response): ProviderRate
    {
        try {
            $data = $response->toArray();

            if (($data['success'] ?? false) !== true) {
                $reason = 'Unknown API error';
                if (isset($data['error']['info'])) {
                    $reason = (string) $data['error']['info'];
                } elseif (isset($data['error']['code'])) {
                    $reason = (string) $data['error']['code'];
                }

                throw new ProviderUnavailableException($this->getName(), $reason);
            }

            return new ProviderRate(
                providerName: $this->getName(),
                base: $data['base'],
                date: $data['date'],
                rates: $data['rates'],
            );
        } catch (ProviderUnavailableException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ProviderUnavailableException($this->getName(), $e->getMessage(), $e);
        }
    }
}
