<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ConversionRequest;
use App\DTO\ConversionRequestInput;
use App\Service\CurrencyConversionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * REST controller for `POST /convert` — multi-provider currency conversion and rate comparison.
 */
final class ConversionController extends AbstractController
{
    /**
     * @param CurrencyConversionService $conversionService Provider calls and comparison logic
     * @param ValidatorInterface        $validator         Validates `ConversionRequestInput` after decoding JSON
     */
    public function __construct(
        private readonly CurrencyConversionService $conversionService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Runs the conversion pipeline for one POST body (JWT already enforced upstream).
     *
     * @param Request $request HTTP request whose raw body is JSON (`amount`, `from`, `symbols`, optional `date`)
     *
     * @return JsonResponse Comparison payload or `invalid_json` / `validation_failed` error JSON; status reflects outcome (200, 206, 400, 422, 502)
     */
    #[Route('/convert', name: 'app_convert', methods: ['POST'])]
    public function convert(Request $request): JsonResponse
    {
        $rawJson = $request->getContent();
        $data = json_decode($rawJson, true);

        if (JSON_ERROR_NONE !== json_last_error()) {
            return $this->json([
                'error' => 'invalid_json',
                'message' => json_last_error_msg(),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_array($data)) {
            return $this->json([
                'error' => 'invalid_json',
                'message' => 'JSON body must be an object.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $input = $this->hydrateInput($data);
        $violations = $this->validator->validate($input);

        if (\count($violations) > 0) {
            $payload = [];
            foreach ($violations as $violation) {
                $payload[] = [
                    'property' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
            }

            return $this->json([
                'error' => 'validation_failed',
                'violations' => $payload,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        \assert(null !== $input->amount);
        \assert(\is_array($input->symbols));

        $effectiveDate = (new \DateTimeImmutable())->format('Y-m-d');
        if ($input->date !== null && '' !== trim($input->date)) {
            $trimmed = trim($input->date);
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $trimmed);
            if (!$dt instanceof \DateTimeImmutable || $dt->format('Y-m-d') !== $trimmed) {
                return $this->json([
                    'error' => 'validation_failed',
                    'violations' => [
                        [
                            'property' => 'date',
                            'message' => 'date must be a valid date in YYYY-MM-DD format',
                        ],
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $effectiveDate = $trimmed;
        }

        $conversionRequest = new ConversionRequest(
            amount: (float) $input->amount,
            from: $input->from,
            symbols: $input->symbols,
            date: $effectiveDate,
        );

        $result = $this->conversionService->convert($conversionRequest);

        $statusCode = empty($result->meta['providers_used'])
            ? Response::HTTP_BAD_GATEWAY
            : (isset($result->meta['errors']) ? Response::HTTP_PARTIAL_CONTENT : Response::HTTP_OK);

        return $this->json([
            'base' => $result->base,
            'date' => $result->date,
            'results' => $result->results,
            'meta' => $result->meta,
        ], $statusCode);
    }

    /**
     * Fills `ConversionRequestInput` from decoded JSON (normalization only; validator runs next).
     *
     * @param array<string, mixed> $data Root JSON object as associative array
     *
     * @return ConversionRequestInput Hydrated input ready for `ValidatorInterface::validate()`
     */
    private function hydrateInput(array $data): ConversionRequestInput
    {
        $input = new ConversionRequestInput();

        if (\array_key_exists('amount', $data)) {
            $raw = $data['amount'];
            if (\is_string($raw) && is_numeric($raw)) {
                $input->amount = (float) $raw;
            } elseif (\is_int($raw) || \is_float($raw)) {
                $input->amount = (float) $raw;
            } else {
                $input->amount = $raw;
            }
        }

        if (isset($data['from'])) {
            $input->from = strtoupper(trim((string) $data['from']));
        }

        if (\array_key_exists('symbols', $data)) {
            $symbols = \is_array($data['symbols']) ? $data['symbols'] : [];
            $input->symbols = array_values(array_map(
                static fn (mixed $s): string => strtoupper(trim((string) $s)),
                $symbols,
            ));
        }

        if (isset($data['date'])) {
            $input->date = trim((string) $data['date']);
        }

        return $input;
    }
}
