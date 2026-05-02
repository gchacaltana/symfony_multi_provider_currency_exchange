<?php

declare(strict_types=1);

namespace App\Provider;

use App\DTO\ProviderRate;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * External FX vendors implementing deferred HTTP reads so orchestrators dispatch parallel GETs safely.
 */
interface ExchangeRateProviderInterface
{
    /**
     * @return string Identifier nested under JSON `providers` keys (e.g. `frankfurter`).
     */
    public function getName(): string;

    /**
     * Issues GET quote retrieval without consuming the response body synchronously yet.
     *
     * @param string[] $symbols Target ISO 4217 codes
     *
     * @return ResponseInterface Pending HTTP client response headed for {@see decodeRatesResponse()}
     */
    public function createRatesRequest(string $base, array $symbols, string $date): ResponseInterface;

    /**
     * Maps HTTP payloads into {@see ProviderRate}.
     *
     * @throws \App\Exception\ProviderUnavailableException
     *
     * @return ProviderRate Normalized multiplier snapshot once decoding succeeds.
     */
    public function decodeRatesResponse(ResponseInterface $response): ProviderRate;
}
