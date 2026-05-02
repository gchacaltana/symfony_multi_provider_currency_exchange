<?php

declare(strict_types=1);

namespace App\Security;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class JwtAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly string $jwtSecret)
    {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod(Request::METHOD_POST) && $request->getPathInfo() === '/convert';
    }

    public function authenticate(Request $request): Passport
    {
        if ('' === trim($this->jwtSecret)) {
            throw new CustomUserMessageAuthenticationException('JWT secret is not configured.');
        }

        $authHeader = $request->headers->get('Authorization');
        if (!$authHeader || !preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            throw new CustomUserMessageAuthenticationException('Missing or invalid Authorization Bearer token.');
        }

        $jwt = trim($matches[1]);

        try {
            JWT::decode($jwt, new Key($this->jwtSecret, 'HS256'));
        } catch (ExpiredException $e) {
            throw new CustomUserMessageAuthenticationException('JWT has expired.');
        } catch (\Throwable $e) {
            throw new CustomUserMessageAuthenticationException('Invalid JWT.');
        }

        return new SelfValidatingPassport(new UserBadge('jwt_client'));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            'error' => 'unauthorized',
            'message' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error' => 'unauthorized',
            'message' => 'Authentication required.',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
