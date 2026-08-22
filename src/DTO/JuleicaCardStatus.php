<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\DTO;

use DateTimeImmutable;
use JuleicaPhp\Juleica\Enums\JuleicaStatus;

final class JuleicaCardStatus
{
    public function __construct(
        public readonly JuleicaStatus $status,
        public readonly ?DateTimeImmutable $validTill = null,
        public readonly ?bool $extension = null,
        public readonly ?string $extendedCardNumber = null,
    ) {
    }

    public function isValid(): bool
    {
        return $this->status === JuleicaStatus::Valid;
    }

    public function isInvalid(): bool
    {
        return $this->status === JuleicaStatus::Invalid;
    }

    public function isExpired(): bool
    {
        return $this->status === JuleicaStatus::Expired;
    }

    public function hasExtension(): bool
    {
        return $this->extension === true;
    }

    /**
     * @param array<string, mixed> $data Decoded JSON body from the Juleica API.
     */
    public static function fromArray(array $data): self
    {
        $validTill = null;

        if (! empty($data['valid_till'])) {
            $validTill = DateTimeImmutable::createFromFormat('d.m.Y', (string) $data['valid_till']) ?: null;
        }

        return new self(
            status: JuleicaStatus::from((string) $data['status']),
            validTill: $validTill,
            extension: array_key_exists('extension', $data) ? (bool) $data['extension'] : null,
            extendedCardNumber: $data['extended_card_number'] ?? null,
        );
    }
}
