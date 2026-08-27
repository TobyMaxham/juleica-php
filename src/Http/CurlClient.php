<?php

declare(strict_types=1);

namespace JuleicaPhp\Juleica\Http;

use JuleicaPhp\Juleica\Http\Exceptions\CurlNetworkException;
use JuleicaPhp\Juleica\Http\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class CurlClient implements ClientInterface
{
    public function __construct(
        private readonly int $timeout = 10,
    ) {
        if (! extension_loaded('curl')) {
            throw new RuntimeException(
                'ext-curl ist nicht aktiviert. Bitte curl aktivieren oder einen eigenen PSR-18-Client via Konstruktor übergeben.',
            );
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $handle = curl_init();

        $headerLines = [];

        foreach ($request->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headerLines[] = "{$name}: {$value}";
            }
        }

        $responseHeaders = [];

        curl_setopt_array($handle, [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HEADERFUNCTION => function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $trimmed = trim($headerLine, "\r\n");

                if ($trimmed === '' || ! str_contains($trimmed, ':')) {
                    return strlen($headerLine);
                }

                [$name, $value] = explode(':', $trimmed, 2);
                $responseHeaders[trim($name)][] = trim($value);

                return strlen($headerLine);
            },
        ]);

        $body = $request->getBody()->__toString();

        if ($body !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $errorMessage = curl_error($handle);
            curl_close($handle);

            throw new CurlNetworkException(
                "Curl-Request fehlgeschlagen: {$errorMessage}",
                $request,
            );
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($statusCode, $responseHeaders, (string) $responseBody);
    }
}
