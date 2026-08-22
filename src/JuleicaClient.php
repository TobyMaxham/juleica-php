<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica;

use DateTimeInterface;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use JuleicaPhp\Juleica\DTO\JuleicaCardStatus;
use JuleicaPhp\Juleica\Exceptions\JuleicaApiException;
use JuleicaPhp\Juleica\Exceptions\JuleicaAuthenticationException;
use JuleicaPhp\Juleica\Exceptions\JuleicaRateLimitException;

final class JuleicaClient
{
    private const DEFAULT_BASE_URI = 'https://app.juleica.de';

    private readonly ClientInterface $httpClient;

    private readonly RequestFactoryInterface $requestFactory;

    /**
     * @param string $token Bearer token issued by the Juleica team (juleica@farbcode.net).
     * @param string $baseUri Override for testing against a different host.
     * @param ClientInterface|null $httpClient A PSR-18 HTTP client. If omitted, one is auto-discovered
     *        via php-http/discovery from whatever is already installed in the consuming project
     *        (Guzzle, Symfony HttpClient, curl, etc.) — this package does not force a specific client.
     * @param RequestFactoryInterface|null $requestFactory A PSR-17 request factory. Auto-discovered if omitted.
     */
    public function __construct(
        private readonly string $token,
        private readonly string $baseUri = self::DEFAULT_BASE_URI,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
    }

    /**
     * Check the validity of a Juleica card.
     *
     * @param string $cardNumber The card number to check (required).
     * @param DateTimeInterface|null $validTill Only valid if the card is valid until this date.
     * @param DateTimeInterface|null $birthday Must match the cardholder's birthday.
     * @param string|null $firstname Must match the cardholder's first name.
     * @param string|null $lastname Must match the cardholder's last name.
     * @param DateTimeInterface|null $validAt The date at which validity should be checked (defaults to today).
     * @param bool $checkExtension Whether to also check for an extended/renewed card.
     *
     * @throws JuleicaAuthenticationException If the bearer token is missing or invalid.
     * @throws JuleicaRateLimitException If the rate limit (120 req, per the API) was exceeded.
     * @throws JuleicaApiException On any other API or network error.
     */
    public function checkCard(
        string $cardNumber,
        ?DateTimeInterface $validTill = null,
        ?DateTimeInterface $birthday = null,
        ?string $firstname = null,
        ?string $lastname = null,
        ?DateTimeInterface $validAt = null,
        bool $checkExtension = false,
    ): JuleicaCardStatus {
        $query = array_filter([
            'card_number' => $cardNumber,
            'valid_till' => $validTill?->format('Y-m-d'),
            'birthday' => $birthday?->format('Y-m-d'),
            'firstname' => $firstname,
            'lastname' => $lastname,
            'valid_at' => $validAt?->format('Y-m-d'),
            'check_extension' => $checkExtension ? 'true' : null,
        ], static fn ($value) => $value !== null);

        $url = rtrim($this->baseUri, '/') . '/api/card-is-valid?' . http_build_query($query);

        $request = $this->requestFactory
            ->createRequest('GET', $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('Authorization', 'Bearer ' . $this->token);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            // Network-level failure (DNS, connection refused, timeout, ...).
            // PSR-18 clients do NOT throw for HTTP error status codes (401, 429, 500, ...) —
            // those are handled below via the response status code instead.
            throw new JuleicaApiException('Request to the Juleica API failed: ' . $e->getMessage(), previous: $e);
        }

        if ($response->getStatusCode() >= 400) {
            throw $this->transformErrorResponse($response);
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (! is_array($data) || ! isset($data['status'])) {
            throw new JuleicaApiException(
                'Unexpected response from the Juleica API.',
                $response->getStatusCode(),
                $body,
            );
        }

        return JuleicaCardStatus::fromArray($data);
    }

    private function transformErrorResponse(ResponseInterface $response): JuleicaApiException
    {
        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if (in_array($statusCode, [401, 403], true)) {
            return new JuleicaAuthenticationException(
                'Authentication with the Juleica API failed. Check your bearer token.',
                $statusCode,
                $body,
            );
        }

        if ($statusCode === 429) {
            $limit = $response->hasHeader('X-RateLimit-Limit')
                ? (int) $response->getHeaderLine('X-RateLimit-Limit')
                : null;
            $remaining = $response->hasHeader('X-RateLimit-Remaining')
                ? (int) $response->getHeaderLine('X-RateLimit-Remaining')
                : null;

            return new JuleicaRateLimitException(
                'Juleica API rate limit exceeded.',
                $statusCode,
                $body,
                $limit,
                $remaining,
            );
        }

        return new JuleicaApiException(
            "Juleica API returned an error (HTTP {$statusCode}).",
            $statusCode,
            $body,
        );
    }
}
