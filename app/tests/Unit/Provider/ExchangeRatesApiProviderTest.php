<?php

declare(strict_types=1);

namespace App\Tests\Unit\Provider;

use App\Exception\ProviderUnavailableException;
use App\Provider\ExchangeRatesApiProvider;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ExchangeRatesApiProviderTest extends TestCase
{
    public function testDecodeReturnsNormalizedProviderRateOnSuccess(): void
    {
        $payload = json_encode([
            'success' => true,
            'historical' => true,
            'base' => 'USD',
            'date' => '2026-05-01',
            'rates' => ['EUR' => 0.921, 'PEN' => 3.71],
        ]);

        $client = new MockHttpClient(new MockResponse($payload));
        $provider = new ExchangeRatesApiProvider($client, 'https://api.exchangeratesapi.io/v1', 'test-key');

        $response = $provider->createRatesRequest('USD', ['EUR', 'PEN'], '2026-05-01');
        $rate = $provider->decodeRatesResponse($response);

        $this->assertSame('exchangeratesapi', $rate->providerName);
        $this->assertSame('USD', $rate->base);
        $this->assertSame('2026-05-01', $rate->date);
        $this->assertEqualsWithDelta(0.921, $rate->rates['EUR'], 0.0001);
        $this->assertEqualsWithDelta(3.71, $rate->rates['PEN'], 0.0001);
    }

    public function testDecodeThrowsWhenSuccessIsFalse(): void
    {
        $payload = json_encode([
            'success' => false,
            'error' => ['code' => 101, 'info' => 'Invalid API key'],
        ]);

        $client = new MockHttpClient(new MockResponse($payload));
        $provider = new ExchangeRatesApiProvider($client, 'https://api.exchangeratesapi.io/v1', 'bad-key');

        $this->expectException(ProviderUnavailableException::class);
        $this->expectExceptionMessage('Invalid API key');

        $response = $provider->createRatesRequest('USD', ['EUR'], '2026-05-01');
        $provider->decodeRatesResponse($response);
    }

    public function testCreateRequestOmitsAccessKeyWhenApiKeyIsEmpty(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            Assert::assertArrayNotHasKey('access_key', $options['query'] ?? []);

            return new MockResponse(json_encode([
                'success' => true,
                'base' => 'USD',
                'date' => '2026-05-01',
                'rates' => ['EUR' => 1.0],
            ]));
        });

        $provider = new ExchangeRatesApiProvider($client, 'https://api.exchangeratesapi.io/v1', '');
        $provider->decodeRatesResponse($provider->createRatesRequest('USD', ['EUR'], '2026-05-01'));
    }
}
