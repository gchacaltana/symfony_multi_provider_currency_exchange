<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Firebase\JWT\JWT;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ConversionControllerTest extends WebTestCase
{
    private function createJwt(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? getenv('JWT_SECRET');
        self::assertNotEmpty($secret, 'JWT_SECRET must be set for integration tests (see .env.test).');

        return JWT::encode([
            'iat' => time(),
            'exp' => time() + 3600,
            'sub' => 'integration-test',
        ], $secret, 'HS256');
    }

    public function testConvertWithoutBearerReturns401(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/convert',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"amount":100,"from":"USD","symbols":["EUR"]}',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('unauthorized', $data['error']);
    }

    public function testConvertWithInvalidJwtReturns401(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/convert',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer not-a-valid-jwt',
            ],
            '{"amount":100,"from":"USD","symbols":["EUR"]}',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('unauthorized', $data['error']);
    }

    public function testConvertWithValidJwtInvalidJsonReturns400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/convert',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->createJwt(),
            ],
            '{"broken',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('invalid_json', $data['error']);
    }

    public function testConvertValidationErrorReturns422(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/convert',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->createJwt(),
            ],
            '{"amount":-1,"from":"USD","symbols":["EUR"]}',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $data['error']);
        self::assertArrayHasKey('violations', $data);
        self::assertNotEmpty($data['violations']);
    }

    public function testConvertSuccessReturns200WithResults(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/convert',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->createJwt(),
            ],
            json_encode([
                'amount' => 100,
                'from' => 'USD',
                'symbols' => ['EUR', 'PEN'],
                'date' => '2026-05-01',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('USD', $data['base']);
        self::assertSame('2026-05-01', $data['date']);
        self::assertArrayHasKey('EUR', $data['results']);
        self::assertArrayHasKey('providers', $data['results']['EUR']);
        self::assertArrayHasKey('best_provider', $data['results']['EUR']);
        self::assertContains('frankfurter', $data['meta']['providers_used']);
        self::assertContains('exchangeratesapi', $data['meta']['providers_used']);
    }
}
