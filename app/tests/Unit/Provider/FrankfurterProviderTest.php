<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Exception\ProviderUnavailableException;
use App\Provider\FrankfurterProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class FrankfurterProviderTest extends TestCase
{
    public function testDecodeReturnsNormalizedProviderRate(): void
    {
        $payload = json_encode([
            'amount' => 1.0,
            'base' => 'USD',
            'date' => '2026-05-01',
            'rates' => ['EUR' => 0.9218, 'PEN' => 3.672],
        ]);

        $client = new MockHttpClient(new MockResponse($payload));
        $provider = new FrankfurterProvider($client, 'https://api.frankfurter.app');

        $response = $provider->createRatesRequest('USD', ['EUR', 'PEN'], '2026-05-01');
        $rate = $provider->decodeRatesResponse($response);

        $this->assertSame('frankfurter', $rate->providerName);
        $this->assertSame('USD', $rate->base);
        $this->assertSame('2026-05-01', $rate->date);
        $this->assertArrayHasKey('EUR', $rate->rates);
        $this->assertArrayHasKey('PEN', $rate->rates);
        $this->assertEqualsWithDelta(0.9218, $rate->rates['EUR'], 0.0001);
        $this->assertEqualsWithDelta(3.672, $rate->rates['PEN'], 0.0001);
    }

    public function testDecodeThrowsProviderUnavailableExceptionOnHttpError(): void
    {
        $client = new MockHttpClient(new MockResponse('Service Unavailable', ['http_code' => 503]));
        $provider = new FrankfurterProvider($client, 'https://api.frankfurter.app');

        $this->expectException(ProviderUnavailableException::class);
        $this->expectExceptionMessageMatches('/frankfurter/');

        $response = $provider->createRatesRequest('USD', ['EUR'], '2026-05-01');
        $provider->decodeRatesResponse($response);
    }

    public function testDecodeThrowsProviderUnavailableExceptionOnNetworkError(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'Network error']));
        $provider = new FrankfurterProvider($client, 'https://api.frankfurter.app');

        $this->expectException(ProviderUnavailableException::class);

        $response = $provider->createRatesRequest('USD', ['EUR'], '2026-05-01');
        $provider->decodeRatesResponse($response);
    }
}
