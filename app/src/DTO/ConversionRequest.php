<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Immutable conversion payload handed from HTTP/controller validation into domain services.
 */
final class ConversionRequest
{
    /**
     * @param float               $amount  Amount to convert
     * @param string              $from    Base currency ISO 4217 code (normalized uppercase)
     * @param array<int, string>  $symbols Target ISO codes (normalized uppercase)
     * @param string              $date    Effective quote date `Y-m-d`
     */
    public function __construct(
        public readonly float $amount,
        public readonly string $from,
        public readonly array $symbols,
        public readonly string $date,
    ) {
    }
}
