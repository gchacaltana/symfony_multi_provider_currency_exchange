<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\ConversionRequest;
use App\DTO\ConversionResult;
use App\DTO\ProviderRate;
use App\Exception\ProviderUnavailableException;
use App\Provider\ExchangeRateProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Orchestrates exchange-rate providers, merges decoded quotes, and enriches comparison metadata.
 */
final class CurrencyConversionService
{
    /**
     * @param iterable<ExchangeRateProviderInterface> $providers Tagged implementations fetched by HTTP client
     */
    public function __construct(
        #[TaggedIterator('app.exchange_rate_provider')]
        private readonly iterable $providers,
        private readonly RateComparisonService $rateComparison,
    ) {
    }

    /**
     * @param ConversionRequest $request Validated conversion criteria (`amount`, `from`, `symbols`, `date`)
     *
     * @return ConversionResult `results` grouped per symbol with providers plus comparison fields after enrichment
     */
    public function convert(ConversionRequest $request): ConversionResult
    {
        $pending = [];
        foreach ($this->providers as $provider) {
            $pending[] = [
                'provider' => $provider,
                'response' => $provider->createRatesRequest($request->from, $request->symbols, $request->date),
            ];
        }

        $providerRates = [];
        $providersUsed = [];
        $errors = [];

        foreach ($pending as $item) {
            $provider = $item['provider'];
            try {
                $providerRates[] = $provider->decodeRatesResponse($item['response']);
                $providersUsed[] = $provider->getName();
            } catch (ProviderUnavailableException $e) {
                $errors[$e->providerName] = $e->getMessage();
            }
        }

        $results = $this->buildResults($request->amount, $request->symbols, $providerRates);
        $results = $this->rateComparison->enrich($results);

        $meta = [
            'providers_used' => $providersUsed,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        if (!empty($errors)) {
            $meta['errors'] = $errors;
        }

        return new ConversionResult(
            base: $request->from,
            date: $request->date,
            results: $results,
            meta: $meta,
        );
    }

    /**
     * Builds `providers` map per symbol with converted amounts before comparison enrichment runs.
     *
     * @param ProviderRate[] $providerRates Normalized quotes returned by providers that decoded successfully
     *
     * @return array<string, array{providers: array<string, array{rate: float, converted: float}>}>
     */
    private function buildResults(float $amount, array $symbols, array $providerRates): array
    {
        $results = [];

        foreach ($symbols as $symbol) {
            $providers = [];

            foreach ($providerRates as $providerRate) {
                if (!isset($providerRate->rates[$symbol])) {
                    continue;
                }

                $rate = $providerRate->rates[$symbol];
                $providers[$providerRate->providerName] = [
                    'rate' => $rate,
                    'converted' => round($amount * $rate, 4),
                ];
            }

            if (empty($providers)) {
                continue;
            }

            $results[$symbol] = ['providers' => $providers];
        }

        return $results;
    }
}
