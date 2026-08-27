<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

trait MessageTrait
{
    private string $protocolVersion = '1.1';

    /** @var array<string, string[]> Header name (original case) => values */
    private array $headers = [];

    /** @var array<string, string> Lowercase header name => original-case header name */
    private array $headerNames = [];

    private StreamInterface $body;

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $new = clone $this;
        $new->protocolVersion = $version;

        return $new;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $lower = strtolower($name);

        if (! isset($this->headerNames[$lower])) {
            return [];
        }

        return $this->headers[$this->headerNames[$lower]];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    /**
     * @param string|string[] $value
     */
    public function withHeader(string $name, $value): static
    {
        $new = clone $this;
        $new->setHeader($name, $value);

        return $new;
    }

    /**
     * @param string|string[] $value
     */
    public function withAddedHeader(string $name, $value): static
    {
        $new = clone $this;
        $lower = strtolower($name);
        $values = is_array($value) ? array_values($value) : [$value];

        if (isset($new->headerNames[$lower])) {
            $existingName = $new->headerNames[$lower];
            $new->headers[$existingName] = array_merge($new->headers[$existingName], $values);
        } else {
            $new->setHeader($name, $value);
        }

        return $new;
    }

    public function withoutHeader(string $name): static
    {
        $lower = strtolower($name);

        if (! isset($this->headerNames[$lower])) {
            return clone $this;
        }

        $new = clone $this;
        unset($new->headers[$new->headerNames[$lower]], $new->headerNames[$lower]);

        return $new;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $new = clone $this;
        $new->body = $body;

        return $new;
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    private function setHeaders(array $headers): void
    {
        $this->headers = [];
        $this->headerNames = [];

        foreach ($headers as $name => $value) {
            $this->setHeader((string) $name, $value);
        }
    }

    /**
     * @param string|string[] $value
     */
    private function setHeader(string $name, $value): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Header name cannot be empty.');
        }

        $lower = strtolower($name);
        $values = is_array($value) ? array_values(array_map('strval', $value)) : [(string) $value];

        if (isset($this->headerNames[$lower])) {
            unset($this->headers[$this->headerNames[$lower]]);
        }

        $this->headerNames[$lower] = $name;
        $this->headers[$name] = $values;
    }
}
