<?php

declare(strict_types=1);

namespace App\Tests\HttpClient;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TestMockExchangeHttpClient
{
    public static function create(): MockHttpClient
    {
        return new MockHttpClient(static function (string $method, string $url): MockResponse {
            if (str_contains($url, 'frankfurter')) {
                return new MockResponse(json_encode([
                    'base' => 'USD',
                    'date' => '2026-05-01',
                    'rates' => ['EUR' => 0.92, 'PEN' => 3.72],
                ], JSON_THROW_ON_ERROR));
            }

            if (str_contains($url, 'exchangeratesapi')) {
                return new MockResponse(json_encode([
                    'success' => true,
                    'base' => 'USD',
                    'date' => '2026-05-01',
                    'rates' => ['EUR' => 0.921, 'PEN' => 3.71],
                ], JSON_THROW_ON_ERROR));
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });
    }
}
