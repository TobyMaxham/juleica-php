# juleica-php/client

Ein schlanker, framework-agnostischer PHP-Client für die [Juleica](https://juleica.de/entwickler)-API zur Prüfung der Gültigkeit einer Jugendleiter/in-Card (Juleica).

## Installation

```bash
composer require juleica-php/client
```

Benötigt PHP `>=8.1`. Du brauchst außerdem ein Bearer-Token, das dir das Juleica-Team ausstellt (Kontakt: juleica@farbcode.net).

**Wichtig:** Dieses Package schreibt bewusst keine konkrete HTTP-Client-Implementierung vor (kein hartes `require` auf Guzzle o. ä.), um Versionskonflikte in deinem Projekt zu vermeiden. Es verlangt nur PSR-18/PSR-17-Interfaces und nutzt [`php-http/discovery`](https://github.com/php-http/discovery), um zur Laufzeit automatisch zu erkennen, welcher HTTP-Client in deinem Projekt bereits installiert ist (Guzzle, Symfony HttpClient, curl-basierte Clients, …). Hast du noch keinen PSR-18-Client im Projekt, reicht es, einen zu installieren, z. B.:

```bash
composer require guzzlehttp/guzzle
```

## Verwendung

```php
use JuleicaPhp\Juleica\JuleicaClient;
use JuleicaPhp\Juleica\Exceptions\JuleicaApiException;

$client = new JuleicaClient(token: 'dein-bearer-token');

try {
    $result = $client->checkCard(cardNumber: '1234567890');

    if ($result->isValid()) {
        echo 'Gültig bis: ' . $result->validTill->format('d.m.Y');
    } elseif ($result->isExpired()) {
        echo 'Karte ist abgelaufen.';
    } else {
        echo 'Karte ist ungültig.';
    }
} catch (JuleicaApiException $e) {
    // Netzwerk- oder API-Fehler
}
```

### Optionale Parameter

`checkCard()` unterstützt alle Parameter der API:

```php
use DateTimeImmutable;

$result = $client->checkCard(
    cardNumber: '1234567890',
    validTill: new DateTimeImmutable('2025-11-30'),   // prüft Gültigkeit bis zu diesem Datum
    birthday: new DateTimeImmutable('2000-01-01'),     // muss mit Geburtsdatum übereinstimmen
    firstname: 'Tim',                                   // muss mit Vornamen übereinstimmen
    lastname: 'the Tester',                              // muss mit Nachnamen übereinstimmen
    validAt: new DateTimeImmutable('2025-01-02'),        // Stichtag der Prüfung (Default: heute)
    checkExtension: true,                                // prüft zusätzlich auf verlängerte Karten
);

if ($result->hasExtension()) {
    echo 'Verlängerte Kartennummer: ' . $result->extendedCardNumber;
}
```

### Fehlerbehandlung

| Exception                        | Bedeutung                                      |
|-----------------------------------|-------------------------------------------------|
| `JuleicaAuthenticationException`  | Bearer-Token fehlt oder ist ungültig (401/403)  |
| `JuleicaRateLimitException`       | Rate-Limit erreicht (429), inkl. `limit`/`remaining` |
| `JuleicaApiException`             | Sonstiger API- oder Netzwerkfehler              |

```php
use JuleicaPhp\Juleica\Exceptions\JuleicaRateLimitException;

try {
    $client->checkCard(cardNumber: '1234567890');
} catch (JuleicaRateLimitException $e) {
    // $e->limit, $e->remaining verfügbar
}
```

### Eigenen HTTP-Client verwenden

Standardmäßig wird der HTTP-Client automatisch erkannt (siehe oben). Für Tests, eigene Konfiguration (Proxy, Logging-Middleware, Timeouts) oder um die Auto-Discovery zu umgehen, können ein eigener PSR-18-Client und/oder eine PSR-17-Request-Factory übergeben werden:

```php
$client = new JuleicaClient(
    token: 'dein-bearer-token',
    httpClient: $meinPsr18Client,
    requestFactory: $meinePsr17RequestFactory,
);
```

## Für Laravel

Eine Laravel-Bridge (Service Provider, Facade, Config-Publishing) folgt als eigenständiges Package `juleica-php/laravel`.

## Tests

```bash
composer install
composer test
```

## Lizenz

MIT. Siehe [LICENSE.md](LICENSE.md).

Dies ist ein inoffizielles Community-Package und steht in keiner Verbindung zum Juleica-Team oder Trägerorganisationen.
