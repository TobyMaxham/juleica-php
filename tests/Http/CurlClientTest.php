<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Tests\Http;

use JuleicaPhp\Juleica\Http\CurlClient;
use JuleicaPhp\Juleica\Http\Exceptions\CurlNetworkException;
use JuleicaPhp\Juleica\Http\Psr7\Request;
use PHPUnit\Framework\TestCase;

final class CurlClientTest extends TestCase
{
    private static int $port;

    /** @var resource */
    private static $serverProcess;

    public static function setUpBeforeClass(): void
    {
        self::$port = random_int(20000, 60000);

        $router = __DIR__ . '/fixtures/router.php';
        $command = sprintf('exec php -S 127.0.0.1:%d %s', self::$port, escapeshellarg($router));

        self::$serverProcess = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        // Give the built-in webserver a moment to start accepting connections.
        $deadline = microtime(true) + 5;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', self::$port, $errCode, $errMsg, 0.2);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        self::fail('PHP built-in webserver did not start in time.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    private function baseUri(): string
    {
        return 'http://127.0.0.1:' . self::$port;
    }

    public function test_it_sends_a_get_request_and_parses_a_json_response(): void
    {
        $client = new CurlClient();
        $request = new Request('GET', $this->baseUri() . '/ok');

        $response = $client->sendRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('valid', $data['status']);
    }

    public function test_it_extracts_status_code_and_headers_for_error_responses(): void
    {
        $client = new CurlClient();
        $request = new Request('GET', $this->baseUri() . '/rate-limited');

        $response = $client->sendRequest($request);

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('120', $response->getHeaderLine('X-RateLimit-Limit'));
        $this->assertSame('0', $response->getHeaderLine('X-RateLimit-Remaining'));
    }

    public function test_it_returns_404_for_unknown_routes(): void
    {
        $client = new CurlClient();
        $request = new Request('GET', $this->baseUri() . '/not-found');

        $response = $client->sendRequest($request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_it_sends_request_headers(): void
    {
        $client = new CurlClient();
        $request = (new Request('GET', $this->baseUri() . '/echo-headers'))
            ->withHeader('Authorization', 'Bearer test-token')
            ->withHeader('Accept', 'application/json');

        $response = $client->sendRequest($request);
        $data = json_decode((string) $response->getBody(), true);

        $this->assertSame('Bearer test-token', $data['authorization']);
        $this->assertSame('application/json', $data['accept']);
    }

    public function test_it_throws_a_network_exception_when_the_connection_is_refused(): void
    {
        $client = new CurlClient();
        // Port 1 is a reserved/unroutable port for local userland servers -> connection refused.
        $request = new Request('GET', 'http://127.0.0.1:1/ok');

        $this->expectException(CurlNetworkException::class);

        $client->sendRequest($request);
    }

    public function test_it_throws_a_network_exception_on_timeout(): void
    {
        $client = new CurlClient(timeout: 1);
        $request = new Request('GET', $this->baseUri() . '/slow');

        $this->expectException(CurlNetworkException::class);

        $client->sendRequest($request);
    }
}
