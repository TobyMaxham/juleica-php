<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Http\Psr7;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

final class Stream implements StreamInterface
{
    private ?string $content;

    private int $position = 0;

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public function __toString(): string
    {
        if ($this->content === null) {
            return '';
        }

        return $this->content;
    }

    public function close(): void
    {
        $this->content = null;
    }

    public function detach()
    {
        $this->content = null;

        return null;
    }

    public function getSize(): ?int
    {
        return $this->content === null ? null : strlen($this->content);
    }

    public function tell(): int
    {
        if ($this->content === null) {
            throw new RuntimeException('Stream is detached.');
        }

        return $this->position;
    }

    public function eof(): bool
    {
        if ($this->content === null) {
            return true;
        }

        return $this->position >= strlen($this->content);
    }

    public function isSeekable(): bool
    {
        return $this->content !== null;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->content === null) {
            throw new RuntimeException('Stream is detached.');
        }

        $length = strlen($this->content);

        $newPosition = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => $length + $offset,
            default => throw new RuntimeException('Invalid whence value.'),
        };

        if ($newPosition < 0) {
            throw new RuntimeException('Cannot seek to a negative stream position.');
        }

        $this->position = $newPosition;
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->content !== null;
    }

    public function write(string $string): int
    {
        if ($this->content === null) {
            throw new RuntimeException('Stream is detached.');
        }

        $this->content = substr($this->content, 0, $this->position)
            . $string
            . substr($this->content, $this->position + strlen($string));
        $this->position += strlen($string);

        return strlen($string);
    }

    public function isReadable(): bool
    {
        return $this->content !== null;
    }

    public function read(int $length): string
    {
        if ($this->content === null) {
            throw new RuntimeException('Stream is detached.');
        }

        $data = substr($this->content, $this->position, $length);
        $this->position += strlen($data);

        return $data;
    }

    public function getContents(): string
    {
        if ($this->content === null) {
            throw new RuntimeException('Stream is detached.');
        }

        $remaining = substr($this->content, $this->position);
        $this->position = strlen($this->content);

        return $remaining;
    }

    public function getMetadata(?string $key = null)
    {
        if ($key === null) {
            return [];
        }

        return null;
    }
}
