<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UriInterface;

final class Uri implements UriInterface
{
    private const SCHEME_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    private string $scheme = '';

    private string $userInfo = '';

    private string $host = '';

    private ?int $port = null;

    private string $path = '';

    private string $query = '';

    private string $fragment = '';

    public function __construct(string $uri = '')
    {
        if ($uri === '') {
            return;
        }

        $parts = parse_url($uri);

        if ($parts === false) {
            throw new InvalidArgumentException("Unable to parse URI: {$uri}");
        }

        $this->scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        $this->host = isset($parts['host']) ? strtolower($parts['host']) : '';
        $this->port = $this->filterPort($parts['port'] ?? null);
        $this->path = $parts['path'] ?? '';
        $this->query = $parts['query'] ?? '';
        $this->fragment = $parts['fragment'] ?? '';

        if (isset($parts['user'])) {
            $this->userInfo = $parts['user'];

            if (isset($parts['pass'])) {
                $this->userInfo .= ':' . $parts['pass'];
            }
        }
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getAuthority(): string
    {
        if ($this->host === '') {
            return '';
        }

        $authority = $this->host;

        if ($this->userInfo !== '') {
            $authority = $this->userInfo . '@' . $authority;
        }

        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }

        return $authority;
    }

    public function getUserInfo(): string
    {
        return $this->userInfo;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getFragment(): string
    {
        return $this->fragment;
    }

    public function withScheme(string $scheme): UriInterface
    {
        $new = clone $this;
        $new->scheme = strtolower($scheme);
        $new->port = $new->filterPort($new->port);

        return $new;
    }

    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        $userInfo = $user;

        if ($password !== null && $password !== '') {
            $userInfo .= ':' . $password;
        }

        $new = clone $this;
        $new->userInfo = $userInfo;

        return $new;
    }

    public function withHost(string $host): UriInterface
    {
        $new = clone $this;
        $new->host = strtolower($host);

        return $new;
    }

    public function withPort(?int $port): UriInterface
    {
        $new = clone $this;
        $new->port = $new->filterPort($port);

        return $new;
    }

    public function withPath(string $path): UriInterface
    {
        $new = clone $this;
        $new->path = $path;

        return $new;
    }

    public function withQuery(string $query): UriInterface
    {
        $new = clone $this;
        $new->query = $query;

        return $new;
    }

    public function withFragment(string $fragment): UriInterface
    {
        $new = clone $this;
        $new->fragment = $fragment;

        return $new;
    }

    public function __toString(): string
    {
        $uri = '';

        if ($this->scheme !== '') {
            $uri .= $this->scheme . ':';
        }

        $authority = $this->getAuthority();

        if ($authority !== '' || $this->scheme === 'file') {
            $uri .= '//' . $authority;
        }

        $path = $this->path;

        if ($authority !== '' && $path !== '' && $path[0] !== '/') {
            $path = '/' . $path;
        }

        $uri .= $path;

        if ($this->query !== '') {
            $uri .= '?' . $this->query;
        }

        if ($this->fragment !== '') {
            $uri .= '#' . $this->fragment;
        }

        return $uri;
    }

    private function filterPort(?int $port): ?int
    {
        if ($port === null) {
            return null;
        }

        if (isset(self::SCHEME_PORTS[$this->scheme]) && self::SCHEME_PORTS[$this->scheme] === $port) {
            return null;
        }

        return $port;
    }
}
