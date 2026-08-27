<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Http\Psr7;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class Request implements RequestInterface
{
    use MessageTrait;

    private string $method;

    private UriInterface $uri;

    private ?string $requestTarget = null;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        string $method,
        UriInterface|string $uri,
        array $headers = [],
        string|StreamInterface $body = '',
    ) {
        $this->method = $method;
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri($uri);
        $this->setHeaders($headers);
        $this->body = $body instanceof StreamInterface ? $body : new Stream($body);

        if (! $this->hasHeader('Host') && $this->uri->getHost() !== '') {
            $this->setHeader('Host', $this->getHostHeaderValue());
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();

        if ($target === '') {
            $target = '/';
        }

        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $new = clone $this;
        $new->requestTarget = $requestTarget;

        return $new;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        $new = clone $this;
        $new->method = $method;

        return $new;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $new = clone $this;
        $new->uri = $uri;

        if ($preserveHost && $this->hasHeader('Host')) {
            return $new;
        }

        if ($uri->getHost() === '') {
            return $new;
        }

        $new->setHeader('Host', self::hostHeaderValueFor($uri));

        return $new;
    }

    private function getHostHeaderValue(): string
    {
        return self::hostHeaderValueFor($this->uri);
    }

    private static function hostHeaderValueFor(UriInterface $uri): string
    {
        $host = $uri->getHost();

        if ($uri->getPort() !== null) {
            $host .= ':' . $uri->getPort();
        }

        return $host;
    }
}
