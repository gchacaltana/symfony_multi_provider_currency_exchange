<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Derives `best_provider` and optional spread metrics between providers per currency symbol.
 */
final class RateComparisonService
{
    /**
     * @param array<string, array{providers: array<string, array{rate: float, converted: float}>}> $results Per-symbol rows with only `providers` populated so far
     *
     * @return array<string, mixed> Same keys plus `best_provider` and optional `difference` when ≥ two rates exist
     */
    public function enrich(array $results): array
    {
        foreach ($results as $symbol => $row) {
            $providers = $row['providers'];
            $bestProvider = null;
            $bestRate = null;

            foreach ($providers as $name => $payload) {
                $rate = $payload['rate'];
                if ($bestRate === null || $rate > $bestRate) {
                    $bestRate = $rate;
                    $bestProvider = $name;
                }
            }

            $row['best_provider'] = $bestProvider;

            $rateValues = array_map(static fn (array $p): float => $p['rate'], $providers);

            if (\count($rateValues) >= 2) {
                $min = min($rateValues);
                $max = max($rateValues);
                $absolute = round($max - $min, 6);
                $percentage = $min > 0.0 ? round(($absolute / $min) * 100, 2) : 0.0;

                $row['difference'] = [
                    'absolute' => $absolute,
                    'percentage' => $percentage,
                ];
            }

            $results[$symbol] = $row;
        }

        return $results;
    }
}
