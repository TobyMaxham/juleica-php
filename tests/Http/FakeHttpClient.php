<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Tests\Http;

use Closure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeHttpClient implements ClientInterface
{
    public ?RequestInterface $lastRequest = null;

    /**
     * @param ResponseInterface|Closure(RequestInterface): ResponseInterface $response
     */
    public function __construct(
        private readonly ResponseInterface|Closure $response,
    ) {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->lastRequest = $request;

        if ($this->response instanceof Closure) {
            return ($this->response)($request);
        }

        return $this->response;
    }
}
