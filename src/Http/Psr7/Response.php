<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Http\Psr7;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Response implements ResponseInterface
{
    use MessageTrait;

    private const REASON_PHRASES = [
        200 => 'OK',
        201 => 'Created',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    private int $statusCode;

    private string $reasonPhrase;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        int $statusCode = 200,
        array $headers = [],
        string|StreamInterface $body = '',
        string $reasonPhrase = '',
    ) {
        $this->statusCode = $statusCode;
        $this->setHeaders($headers);
        $this->body = $body instanceof StreamInterface ? $body : new Stream($body);
        $this->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::REASON_PHRASES[$statusCode] ?? '');
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $new = clone $this;
        $new->statusCode = $code;
        $new->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::REASON_PHRASES[$code] ?? '');

        return $new;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
