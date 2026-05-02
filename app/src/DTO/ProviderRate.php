<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Normalized quote snapshot produced by any provider implementation after decoding HTTP payloads.
 */
final class ProviderRate
{
    /**
     * @param string               $providerName Matches JSON-facing vendor slug (`frankfurter`, …)
     * @param string               $base         Quote basis reused downstream responses
     * @param string               $date         Provider-declared quote date `Y-m-d`
     * @param array<string, float> $rates        Target ISO currency → multiplier versus `base`
     */
    public function __construct(
        public readonly string $providerName,
        public readonly string $base,
        public readonly string $date,
        public readonly array $rates,
    ) {
    }
}
