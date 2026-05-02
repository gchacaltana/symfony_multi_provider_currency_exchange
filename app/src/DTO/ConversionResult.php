<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Domain aggregate serialized back into HTTP JSON (`base`, `date`, `results`, `meta`).
 */
final class ConversionResult
{
    /**
     * @param string               $base    Same ISO currency clients supplied as `from`
     * @param string               $date    Effective dated quotes bundled inside payload
     * @param array<string, mixed> $results Compared/converting figures keyed by target ISO currency code
     * @param array<string, mixed> $meta    Contains `providers_used`, ISO timestamp; adds `errors` when providers fail
     */
    public function __construct(
        public readonly string $base,
        public readonly string $date,
        public readonly array $results,
        public readonly array $meta,
    ) {
    }
}
