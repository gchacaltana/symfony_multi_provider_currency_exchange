<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\ConversionRequest;
use App\DTO\ProviderRate;
use App\Exception\ProviderUnavailableException;
use App\Provider\ExchangeRateProviderInterface;
use App\Service\CurrencyConversionService;
use App\Service\RateComparisonService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CurrencyConversionServiceTest extends TestCase
{
    public function testConvertWithSingleProviderReturnsCorrectRatesAndConvertedAmounts(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $provider = $this->createMock(ExchangeRateProviderInterface::class);
        $provider->method('getName')->willReturn('frankfurter');
        $provider->expects($this->once())->method('createRatesRequest')->willReturn($response);
        $provider->method('decodeRatesResponse')->with($response)->willReturn(new ProviderRate(
            providerName: 'frankfurter',
            base: 'USD',
            date: '2026-05-01',
            rates: ['EUR' => 0.92, 'PEN' => 3.72],
        ));

        $service = new CurrencyConversionService([$provider], new RateComparisonService());
        $request = new ConversionRequest(100.0, 'USD', ['EUR', 'PEN'], '2026-05-01');

        $result = $service->convert($request);

        $this->assertSame('USD', $result->base);
        $this->assertSame('2026-05-01', $result->date);
        $this->assertSame(['frankfurter'], $result->meta['providers_used']);
        $this->assertArrayNotHasKey('errors', $result->meta);

        $this->assertEqualsWithDelta(0.92, $result->results['EUR']['providers']['frankfurter']['rate'], 0.001);
        $this->assertEqualsWithDelta(92.0, $result->results['EUR']['providers']['frankfurter']['converted'], 0.001);
        $this->assertSame('frankfurter', $result->results['EUR']['best_provider']);
        $this->assertArrayNotHasKey('difference', $result->results['EUR']);

        $this->assertEqualsWithDelta(3.72, $result->results['PEN']['providers']['frankfurter']['rate'], 0.001);
        $this->assertEqualsWithDelta(372.0, $result->results['PEN']['providers']['frankfurter']['converted'], 0.001);
    }

    public function testConvertWithTwoProvidersAddsComparisonMetadata(): void
    {
        $r1 = $this->createMock(ResponseInterface::class);
        $r2 = $this->createMock(ResponseInterface::class);

        $frankfurter = $this->createMock(ExchangeRateProviderInterface::class);
        $frankfurter->method('getName')->willReturn('frankfurter');
        $frankfurter->expects($this->once())->method('createRatesRequest')->willReturn($r1);
        $frankfurter->method('decodeRatesResponse')->with($r1)->willReturn(new ProviderRate(
            providerName: 'frankfurter',
            base: 'USD',
            date: '2026-05-01',
            rates: ['EUR' => 0.92],
        ));

        $exchangeRatesApi = $this->createMock(ExchangeRateProviderInterface::class);
        $exchangeRatesApi->method('getName')->willReturn('exchangeratesapi');
        $exchangeRatesApi->expects($this->once())->method('createRatesRequest')->willReturn($r2);
        $exchangeRatesApi->method('decodeRatesResponse')->with($r2)->willReturn(new ProviderRate(
            providerName: 'exchangeratesapi',
            base: 'USD',
            date: '2026-05-01',
            rates: ['EUR' => 0.921],
        ));

        $service = new CurrencyConversionService([$frankfurter, $exchangeRatesApi], new RateComparisonService());
        $request = new ConversionRequest(100.0, 'USD', ['EUR'], '2026-05-01');

        $result = $service->convert($request);

        $this->assertSame(['frankfurter', 'exchangeratesapi'], $result->meta['providers_used']);
        $this->assertSame('exchangeratesapi', $result->results['EUR']['best_provider']);
        $this->assertArrayHasKey('difference', $result->results['EUR']);
        $this->assertEqualsWithDelta(0.001, $result->results['EUR']['difference']['absolute'], 0.00001);
    }

    public function testConvertWithFailingProviderReturnsEmptyResultsWithErrors(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $provider = $this->createMock(ExchangeRateProviderInterface::class);
        $provider->method('getName')->willReturn('frankfurter');
        $provider->method('createRatesRequest')->willReturn($response);
        $provider->method('decodeRatesResponse')->willThrowException(
            new ProviderUnavailableException('frankfurter', 'connection timeout'),
        );

        $service = new CurrencyConversionService([$provider], new RateComparisonService());
        $request = new ConversionRequest(100.0, 'USD', ['EUR'], '2026-05-01');

        $result = $service->convert($request);

        $this->assertEmpty($result->meta['providers_used']);
        $this->assertArrayHasKey('errors', $result->meta);
        $this->assertArrayHasKey('frankfurter', $result->meta['errors']);
        $this->assertEmpty($result->results);
    }

    public function testConvertSkipsSymbolMissingFromProviderResponse(): void
    {
        $response = $this->createMock(ResponseInterface::class);

        $provider = $this->createMock(ExchangeRateProviderInterface::class);
        $provider->method('getName')->willReturn('frankfurter');
        $provider->method('createRatesRequest')->willReturn($response);
        $provider->method('decodeRatesResponse')->with($response)->willReturn(new ProviderRate(
            providerName: 'frankfurter',
            base: 'USD',
            date: '2026-05-01',
            rates: ['EUR' => 0.92],
        ));

        $service = new CurrencyConversionService([$provider], new RateComparisonService());
        $request = new ConversionRequest(100.0, 'USD', ['EUR', 'XYZ'], '2026-05-01');

        $result = $service->convert($request);

        $this->assertArrayHasKey('EUR', $result->results);
        $this->assertArrayNotHasKey('XYZ', $result->results);
    }
}
