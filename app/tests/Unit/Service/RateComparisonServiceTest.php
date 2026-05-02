<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RateComparisonService;
use PHPUnit\Framework\TestCase;

final class RateComparisonServiceTest extends TestCase
{
    public function testEnrichAddsDifferenceAndBestProviderWhenTwoRatesExist(): void
    {
        $service = new RateComparisonService();

        $results = [
            'EUR' => [
                'providers' => [
                    'frankfurter' => ['rate' => 0.92, 'converted' => 92.0],
                    'exchangeratesapi' => ['rate' => 0.921, 'converted' => 92.1],
                ],
            ],
        ];

        $enriched = $service->enrich($results);

        $this->assertSame('exchangeratesapi', $enriched['EUR']['best_provider']);
        $this->assertEqualsWithDelta(0.001, $enriched['EUR']['difference']['absolute'], 0.00001);
        $this->assertEqualsWithDelta(0.11, $enriched['EUR']['difference']['percentage'], 0.01);
    }

    public function testEnrichOmitsDifferenceWhenOnlyOneProvider(): void
    {
        $service = new RateComparisonService();

        $results = [
            'EUR' => [
                'providers' => [
                    'frankfurter' => ['rate' => 0.92, 'converted' => 92.0],
                ],
            ],
        ];

        $enriched = $service->enrich($results);

        $this->assertSame('frankfurter', $enriched['EUR']['best_provider']);
        $this->assertArrayNotHasKey('difference', $enriched['EUR']);
    }
}
