<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Exceptions;

class JuleicaRateLimitException extends JuleicaApiException
{
    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $responseBody = null,
        public readonly ?int $limit = null,
        public readonly ?int $remaining = null,
    ) {
        parent::__construct($message, $statusCode, $responseBody);
    }
}
