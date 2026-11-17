<?php

namespace Voharaa\PrivatbankAutoclient\Exceptions;

use Exception;

/**
 * Thrown when the API responds with an error (HTTP status >= 400, or a "status" field other than "SUCCESS").
 *
 * getCode() returns the HTTP status code. The API's own error codes are exposed separately,
 * because they are strings (e.g. "VE005", "PMTSRV0112") and cannot be stored as Exception codes.
 */
class ApiException extends Exception
{
    /**
     * @param  array<mixed>  $response  Decoded response body (empty if the body was not JSON)
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $errorCode = null,
        public readonly ?string $serviceCode = null,
        public readonly ?string $requestId = null,
        public readonly array $response = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromResponse(int $statusCode, array $data): self
    {
        $string = static fn (mixed $value): ?string => is_scalar($value) ? (string) $value : null;

        return new self(
            message: $string($data['message'] ?? null) ?? "API request failed with HTTP status {$statusCode}",
            statusCode: $statusCode,
            errorCode: $string($data['code'] ?? null),
            serviceCode: $string($data['serviceCode'] ?? null),
            requestId: $string($data['requestId'] ?? null),
            response: $data,
        );
    }
}
