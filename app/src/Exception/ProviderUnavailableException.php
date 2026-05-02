<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Thrown when a single upstream FX quote vendor fails decode/handshake semantics while orchestrators optionally retain sibling successes.
 */
final class ProviderUnavailableException extends \RuntimeException
{
    /**
     * @param string          $providerName Same identifier returned by the failing provider's `getName()` implementation
     * @param string          $reason       Technical/context excerpt appended to the exception message
     * @param \Throwable|null $previous     Wrapped HTTP/decoding fault where applicable
     */
    public function __construct(
        public readonly string $providerName,
        string $reason = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf('Provider "%s" is unavailable: %s', $providerName, $reason),
            0,
            $previous,
        );
    }
}
