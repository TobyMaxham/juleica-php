<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Tests;

use Closure;
use PHPUnit\Framework\TestCase;
use JuleicaPhp\Juleica\Enums\JuleicaStatus;
use JuleicaPhp\Juleica\Exceptions\JuleicaApiException;
use JuleicaPhp\Juleica\Exceptions\JuleicaAuthenticationException;
use JuleicaPhp\Juleica\Exceptions\JuleicaRateLimitException;
use JuleicaPhp\Juleica\Http\CurlHttpFactory;
use JuleicaPhp\Juleica\Http\Psr7\Response;
use JuleicaPhp\Juleica\JuleicaClient;
use JuleicaPhp\Juleica\Tests\Http\FakeHttpClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class JuleicaClientTest extends TestCase
{
    /**
     * @param ResponseInterface|Closure(RequestInterface): ResponseInterface $response
     */
    private function makeClient(ResponseInterface|Closure $response, ?FakeHttpClient $fakeClient = null): JuleicaClient
    {
        $fake = $fakeClient ?? new FakeHttpClient($response);

        return new JuleicaClient(token: 'test-token', httpClient: $fake, requestFactory: new CurlHttpFactory());
    }

    public function test_it_returns_a_valid_status(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'valid',
            'valid_till' => '31.12.2025',
        ]));

        $result = $this->makeClient($response)->checkCard('1234567890');

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isExpired());
        $this->assertSame(JuleicaStatus::Valid, $result->status);
        $this->assertSame('2025-12-31', $result->validTill?->format('Y-m-d'));
    }

    public function test_it_returns_an_invalid_status(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'invalid',
            'valid_till' => null,
        ]));

        $result = $this->makeClient($response)->checkCard('0000000000');

        $this->assertTrue($result->isInvalid());
        $this->assertNull($result->validTill);
    }

    public function test_it_returns_an_expired_status(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'expired',
            'valid_till' => null,
        ]));

        $result = $this->makeClient($response)->checkCard('1234567890');

        $this->assertTrue($result->isExpired());
    }

    public function test_it_parses_extension_fields(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'valid',
            'valid_till' => '31.12.2025',
            'extension' => true,
            'extended_card_number' => '9876543210',
        ]));

        $result = $this->makeClient($response)->checkCard('1234567890', checkExtension: true);

        $this->assertTrue($result->hasExtension());
        $this->assertSame('9876543210', $result->extendedCardNumber);
    }

    public function test_it_throws_authentication_exception_on_401(): void
    {
        $response = new Response(401, [], json_encode(['message' => 'Unauthenticated.']));

        $this->expectException(JuleicaAuthenticationException::class);

        $this->makeClient($response)->checkCard('1234567890');
    }

    public function test_it_throws_rate_limit_exception_on_429(): void
    {
        $response = new Response(429, [
            'X-RateLimit-Limit' => '120',
            'X-RateLimit-Remaining' => '0',
        ], json_encode(['message' => 'Too Many Attempts.']));

        try {
            $this->makeClient($response)->checkCard('1234567890');
            $this->fail('Expected JuleicaRateLimitException was not thrown.');
        } catch (JuleicaRateLimitException $e) {
            $this->assertSame(120, $e->limit);
            $this->assertSame(0, $e->remaining);
        }
    }

    public function test_it_throws_generic_api_exception_on_unexpected_body(): void
    {
        $response = new Response(200, ['Content-Type' => 'application/json'], 'not-json');

        $this->expectException(JuleicaApiException::class);

        $this->makeClient($response)->checkCard('1234567890');
    }

    public function test_it_sends_optional_parameters_and_bearer_token(): void
    {
        $fake = new FakeHttpClient(new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'status' => 'valid',
            'valid_till' => '31.12.2025',
        ])));

        $this->makeClient(new Response(200), $fake)->checkCard(
            cardNumber: '1234567890',
            firstname: 'Tim',
            lastname: 'the Tester',
        );

        $capturedRequest = $fake->lastRequest;

        $this->assertNotNull($capturedRequest);
        $this->assertSame('Bearer test-token', $capturedRequest->getHeaderLine('Authorization'));
        $this->assertStringContainsString('card_number=1234567890', (string) $capturedRequest->getUri());
        $this->assertStringContainsString('firstname=Tim', (string) $capturedRequest->getUri());
        $this->assertStringContainsString('lastname=the+Tester', (string) $capturedRequest->getUri());
    }

    public function test_it_uses_the_builtin_curl_client_when_none_is_given(): void
    {
        // No httpClient/requestFactory passed — the package must fall back to its
        // built-in CurlClient/CurlHttpFactory without requiring any other package.
        $client = new JuleicaClient(token: 'test-token');

        $this->assertInstanceOf(JuleicaClient::class, $client);
    }
}
