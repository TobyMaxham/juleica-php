<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use JuleicaPhp\Juleica\Enums\JuleicaStatus;
use JuleicaPhp\Juleica\Exceptions\JuleicaApiException;
use JuleicaPhp\Juleica\Exceptions\JuleicaAuthenticationException;
use JuleicaPhp\Juleica\Exceptions\JuleicaRateLimitException;
use JuleicaPhp\Juleica\JuleicaClient;

final class JuleicaClientTest extends TestCase
{
    private function makeClient(MockHandler $mock): JuleicaClient
    {
        $handlerStack = HandlerStack::create($mock);
        $guzzle = new Client(['handler' => $handlerStack]);

        // We wire Guzzle explicitly here for deterministic, mockable tests.
        // In production code, JuleicaClient works with ANY PSR-18 client —
        // if none is passed, php-http/discovery finds whatever is installed.
        return new JuleicaClient(token: 'test-token', httpClient: $guzzle, requestFactory: new HttpFactory());
    }

    public function test_it_returns_a_valid_status(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'valid',
                'valid_till' => '31.12.2025',
            ])),
        ]);

        $result = $this->makeClient($mock)->checkCard('1234567890');

        $this->assertTrue($result->isValid());
        $this->assertFalse($result->isExpired());
        $this->assertSame(JuleicaStatus::Valid, $result->status);
        $this->assertSame('2025-12-31', $result->validTill?->format('Y-m-d'));
    }

    public function test_it_returns_an_invalid_status(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'invalid',
                'valid_till' => null,
            ])),
        ]);

        $result = $this->makeClient($mock)->checkCard('0000000000');

        $this->assertTrue($result->isInvalid());
        $this->assertNull($result->validTill);
    }

    public function test_it_returns_an_expired_status(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'expired',
                'valid_till' => null,
            ])),
        ]);

        $result = $this->makeClient($mock)->checkCard('1234567890');

        $this->assertTrue($result->isExpired());
    }

    public function test_it_parses_extension_fields(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'status' => 'valid',
                'valid_till' => '31.12.2025',
                'extension' => true,
                'extended_card_number' => '9876543210',
            ])),
        ]);

        $result = $this->makeClient($mock)->checkCard('1234567890', checkExtension: true);

        $this->assertTrue($result->hasExtension());
        $this->assertSame('9876543210', $result->extendedCardNumber);
    }

    public function test_it_throws_authentication_exception_on_401(): void
    {
        $mock = new MockHandler([
            new Response(401, [], json_encode(['message' => 'Unauthenticated.'])),
        ]);

        $this->expectException(JuleicaAuthenticationException::class);

        $this->makeClient($mock)->checkCard('1234567890');
    }

    public function test_it_throws_rate_limit_exception_on_429(): void
    {
        $mock = new MockHandler([
            new Response(429, [
                'X-RateLimit-Limit' => '120',
                'X-RateLimit-Remaining' => '0',
            ], json_encode(['message' => 'Too Many Attempts.'])),
        ]);

        try {
            $this->makeClient($mock)->checkCard('1234567890');
            $this->fail('Expected JuleicaRateLimitException was not thrown.');
        } catch (JuleicaRateLimitException $e) {
            $this->assertSame(120, $e->limit);
            $this->assertSame(0, $e->remaining);
        }
    }

    public function test_it_throws_generic_api_exception_on_unexpected_body(): void
    {
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], 'not-json'),
        ]);

        $this->expectException(JuleicaApiException::class);

        $this->makeClient($mock)->checkCard('1234567890');
    }

    public function test_it_sends_optional_parameters_and_bearer_token(): void
    {
        $capturedRequest = null;

        $mock = new MockHandler([
            function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;

                return new Response(200, ['Content-Type' => 'application/json'], json_encode([
                    'status' => 'valid',
                    'valid_till' => '31.12.2025',
                ]));
            },
        ]);

        $this->makeClient($mock)->checkCard(
            cardNumber: '1234567890',
            firstname: 'Tim',
            lastname: 'the Tester',
        );

        $this->assertNotNull($capturedRequest);
        $this->assertSame('Bearer test-token', $capturedRequest->getHeaderLine('Authorization'));
        $this->assertStringContainsString('card_number=1234567890', (string) $capturedRequest->getUri());
        $this->assertStringContainsString('firstname=Tim', (string) $capturedRequest->getUri());
        $this->assertStringContainsString('lastname=the+Tester', (string) $capturedRequest->getUri());
    }

    public function test_it_auto_discovers_a_psr18_client_when_none_is_given(): void
    {
        // No httpClient/requestFactory passed — php-http/discovery must find Guzzle
        // (installed as a require-dev dependency here, standing in for whatever
        // PSR-18 client the consuming project happens to have).
        $client = new JuleicaClient(token: 'test-token');

        $this->assertInstanceOf(JuleicaClient::class, $client);
    }
}
