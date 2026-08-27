# juleica-php/client

[![Tests](https://github.com/TobyMaxham/juleica-php/actions/workflows/tests.yml/badge.svg)](https://github.com/TobyMaxham/juleica-php/actions/workflows/tests.yml)
[![Latest Stable Version](http://poser.pugx.org/juleica-php/client/v)](https://packagist.org/packages/juleica-php/client)
[![PHP Version Require](http://poser.pugx.org/juleica-php/client/require/php)](https://packagist.org/packages/juleica-php/client)
[![License](http://poser.pugx.org/juleica-php/client/license)](https://packagist.org/packages/juleica-php/client)

Ein schlanker, framework-agnostischer PHP-Client für die [Juleica](https://juleica.de/entwickler)-API zur Prüfung der Gültigkeit einer Jugendleiter/in-Card (Juleica).

> **Hinweis:** Dieses Package ist **kein offizielles Angebot von juleica.de** oder den dahinterstehenden Trägerorganisationen. Es bindet lediglich die öffentlich dokumentierte Juleica-API an und wird unabhängig davon gepflegt.
>
> Die Juleica (Jugendleiter/in-Card) ist ein bundesweit einheitlicher Ausweis für ehrenamtliche Jugendleiter*innen in Deutschland. Sie dient als Qualifikationsnachweis und zur Legitimation gegenüber Erziehungsberechtigten, Behörden und anderen Stellen, verbunden mit diversen Vergünstigungen für ehrenamtlich Engagierte.
>
> Da API-Endpunkt und -Dokumentation von juleica.de betrieben und gepflegt werden (nicht von diesem Package), können sie sich unabhängig von diesem Package ändern. Für aktuelle, offizielle Informationen:
> - Entwicklerseite: <https://juleica.de/entwickler>
> - API-Dokumentation: <https://documenter.gw.postman.com/api/collections/18297363/UVC6h67q>

## Installation

```bash
composer require juleica-php/client
```

Benötigt PHP `>=8.1`. Du brauchst außerdem ein Bearer-Token, das dir das Juleica-Team ausstellt (Kontakt: [siehe Juleica Website](https://juleica.de/entwickler)).

**Zero-Dependency-Prinzip:** Dieses Package benötigt **keinen zusätzlichen HTTP-Client als Composer-Abhängigkeit**.
Ist die PHP-`curl`-Extension aktiviert (bei den allermeisten PHP-Installationen und Hosting-Umgebungen standardmäßig der Fall),
funktioniert das Package sofort nach `composer require` — ganz ohne Guzzle, Symfony HttpClient oder sonstige zusätzliche Pakete.

Nutzt dein Projekt bereits einen PSR-18-HTTP-Client (Guzzle, Symfony HttpClient, o. ä.), wird dieser automatisch erkannt und
bevorzugt verwendet — praktisch, wenn du z. B. schon eine zentrale HTTP-Client-Konfiguration (Timeouts, Middleware, Logging)
im Projekt hast. Nötig ist das aber nicht: ohne einen erkannten Client greift das Package auf einen intern mitgelieferten,
schlanken curl-basierten Client zurück.

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

| Exception                        | Bedeutung                                            |
|----------------------------------|------------------------------------------------------|
| `JuleicaAuthenticationException` | Bearer-Token fehlt oder ist ungültig (401/403)       |
| `JuleicaRateLimitException`      | Rate-Limit erreicht (429), inkl. `limit`/`remaining` |
| `JuleicaApiException`            | Sonstiger API- oder Netzwerkfehler                   |

```php
use JuleicaPhp\Juleica\Exceptions\JuleicaRateLimitException;

try {
    $client->checkCard(cardNumber: '1234567890');
} catch (JuleicaRateLimitException $e) {
    // $e->limit, $e->remaining verfügbar
}
```

### Eigenen HTTP-Client verwenden

Standardmäßig wird automatisch der beste verfügbare Client verwendet — ein bereits im Projekt vorhandener PSR-18-Client
(falls vorhanden) oder sonst der eingebaute curl-Fallback (siehe oben). Für Tests, eigene Konfiguration (Proxy,
Logging-Middleware, Timeouts) oder um dieses Verhalten zu übersteuern, können ein eigener PSR-18-Client und/oder
eine PSR-17-Request-Factory explizit übergeben werden:

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
