<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Hydratable projection of JSON POST body for Validator constraints before mapping to {@see ConversionRequest}.
 */
final class ConversionRequestInput
{
    #[Assert\NotNull(message: 'amount is required')]
    #[Assert\Type(['int', 'float'])]
    #[Assert\Positive(message: 'amount must be greater than zero')]
    public mixed $amount = null;

    #[Assert\NotBlank(message: 'from is required')]
    #[Assert\Length(exactly: 3)]
    #[Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'from must be a 3-letter ISO 4217 code')]
    public string $from = '';

    #[Assert\NotNull(message: 'symbols is required')]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1, max: 32)]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\NotBlank(),
        new Assert\Length(exactly: 3),
        new Assert\Regex(pattern: '/^[A-Z]{3}$/', message: 'Each symbol must be a 3-letter ISO 4217 code'),
    ])]
    public ?array $symbols = null;

    /** Optional `date`; when set, format validated in the controller (`Y-m-d`). */
    public ?string $date = null;
}
