<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Http;

use JuleicaPhp\Juleica\Http\Psr7\Request;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

final class CurlHttpFactory implements RequestFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        return new Request($method, $uri);
    }
}
