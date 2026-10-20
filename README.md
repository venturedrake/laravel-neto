<div align="center">
    <h1>Laravel Neto</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/venturedrake/laravel-neto"><img src="https://img.shields.io/packagist/v/venturedrake/laravel-neto.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/venturedrake/laravel-neto"><img src="https://img.shields.io/packagist/php-v/venturedrake/laravel-neto.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/venturedrake/laravel-neto"><img src="https://badge.laravel.cloud/badge/venturedrake/laravel-neto?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/venturedrake/laravel-neto/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/venturedrake/laravel-neto/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/venturedrake/laravel-neto"><img src="https://img.shields.io/packagist/dt/venturedrake/laravel-neto.svg?style=flat-square" alt="Total Downloads"></a>
</p>

A Laravel package for interacting with the Maropost Commerce Cloud (Neto) API.

It wraps the single-endpoint, header-authenticated Neto API in a small client with
first-class helpers for orders, items and customers, and throws typed exceptions
instead of returning `['success' => false]` arrays.

## Requirements

- PHP 8.3 or newer
- Laravel 12 or 13

## Installation

You can install the package via Composer:

```bash
composer require venturedrake/laravel-neto
```

Add your store's API details to `.env`. The key and username come from your Neto
control panel under **Settings & Tools → All Settings & Tools → API Settings**:

```dotenv
NETO_API_URL=https://www.yourstore.com.au/do/WS/NetoAPI
NETO_API_KEY=your-api-key
NETO_API_USERNAME=your-api-username
```

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="laravel-neto-config"
```

The published `config/neto.php` lets you tune the request timeout, retries,
request logging, and the default statuses and output selectors used for orders:

| Key | Env | Default |
| --- | --- | --- |
| `api_url` | `NETO_API_URL` | — |
| `api_key` | `NETO_API_KEY` | — |
| `username` | `NETO_API_USERNAME` | — |
| `timeout` | `NETO_API_TIMEOUT` | `30` seconds |
| `retry.times` | `NETO_API_RETRY_TIMES` | `1` (no retries) |
| `retry.sleep` | `NETO_API_RETRY_SLEEP` | `250` ms |
| `logging.enabled` | `NETO_API_LOGGING` | `false` |
| `logging.channel` | `NETO_API_LOG_CHANNEL` | default channel |
| `orders.default_statuses` | — | `New`, `Pick`, `Pack`, `Quote` |
| `orders.output_selectors` | — | a broad set of order fields |

Retries only apply to connection failures and `5xx` responses; API-level errors
are never retried. When logging is enabled every request and response is written
at the `debug` level with the API key masked.

## Usage

Resolve the client through the `Neto` facade, dependency injection
(`VentureDrake\Neto\Neto`), or `app('neto')`.

### Orders

```php
use VentureDrake\Neto\Facades\Neto;

// Open orders (uses the configured default statuses and output selectors)
$orders = Neto::orders()->get();

// Any GetOrder filter, with your own output selectors
$orders = Neto::orders()->get(
    ['OrderStatus' => ['Dispatched'], 'DatePlacedFrom' => '2026-01-01 00:00:00'],
    ['OrderID', 'OrderStatus', 'GrandTotal'],
);

// A single order (throws NotFoundException when it does not exist)
$order = Neto::orders()->find('N10001');

// Create an order — the array is the AddOrder "Order" record
$created = Neto::orders()->create([
    'Username' => 'jane',
    'Email' => 'jane@example.com',
    'BillFirstName' => 'Jane',
    'BillLastName' => 'Doe',
    'ShipFirstName' => 'Jane',
    'ShipLastName' => 'Doe',
    'ShipStreet1' => '123 Main St',
    'ShipCity' => 'Sydney',
    'ShipState' => 'NSW',
    'ShipPostCode' => '2000',
    'ShipCountry' => 'AU',
    'ShippingMethod' => 'Standard Shipping',
    'OrderLine' => [
        ['SKU' => 'PROD-001', 'Quantity' => 2],
    ],
]);

$orderId = $created['OrderID'];

// Update, cancel, status and tracking
Neto::orders()->update('N10001', ['OrderStatus' => 'Pick']);
Neto::orders()->cancel('N10001');

$status = Neto::orders()->status('N10001');
// ['OrderID' => 'N10001', 'OrderStatus' => 'Pack', 'DatePlaced' => '...', 'DateUpdated' => '...']

$tracking = Neto::orders()->tracking('N10001');
// [
//     'OrderID' => 'N10001',
//     'OrderStatus' => 'Dispatched',
//     'DatePlaced' => '...',
//     'ShippingOption' => 'Express Post',
//     'TrackingNumber' => 'TRACK123',   // falls back to the first order line's tracking number
//     'Dispatched' => true,
//     'OrderLine' => [...],
// ]
```

### Items and customers

```php
$items = Neto::items()->get(['IsActive' => true, 'Limit' => 100]);
$item = Neto::items()->find('PROD-001');
Neto::items()->create(['SKU' => 'PROD-002', 'Name' => 'Widget', 'DefaultPrice' => 19.95]);
Neto::items()->update('PROD-001', ['DefaultPrice' => 24.95]);

$customers = Neto::customers()->get(['DateUpdatedFrom' => '2026-01-01 00:00:00']);
$customer = Neto::customers()->find('jane');
$customer = Neto::customers()->findByEmail('jane@example.com');
Neto::customers()->create(['Username' => 'jane', 'EmailAddress' => 'jane@example.com']);
Neto::customers()->update('jane', ['OnCreditHold' => true]);
```

### Any other API action

Every Neto API action is a POST to the same endpoint with the action in a header,
so anything not covered by a resource can be called directly. The payload is sent
as the JSON body exactly as given and the decoded response is returned:

```php
$response = Neto::request('GetContent', [
    'Filter' => [
        'ContentType' => ['Category'],
        'OutputSelector' => ['ContentName', 'ContentID'],
    ],
]);

// Shorthand for "Get*" actions that take a Filter block
$response = Neto::get('GetWarehouse', ['WarehouseID' => ['1']], ['WarehouseName']);
```

### Error handling

All exceptions extend `VentureDrake\Neto\Exceptions\NetoException`:

| Exception | When |
| --- | --- |
| `ConfigurationException` | `api_url`, `api_key` or `username` is missing |
| `RequestException` | the request failed at the transport or HTTP level (connection error, non-2xx status, non-JSON body); `->response` holds the HTTP response when there is one |
| `ResponseException` | Neto answered with `Ack: Error` or error messages; `->errors` holds the normalised messages and `->firstError()` the first one |
| `NotFoundException` | a `find()` style call matched nothing |

```php
use VentureDrake\Neto\Exceptions\NotFoundException;
use VentureDrake\Neto\Exceptions\ResponseException;

try {
    $order = Neto::orders()->find($id);
} catch (NotFoundException) {
    abort(404);
} catch (ResponseException $e) {
    report($e);

    return back()->with('error', $e->firstError());
}
```

### Testing your application

The client sends requests through Laravel's HTTP client, so `Http::fake()` works as usual:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    config('neto.api_url') => Http::response([
        'Ack' => 'Success',
        'Order' => [['OrderID' => 'N10001', 'OrderStatus' => 'New']],
    ]),
]);
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Neto! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Andrew Drake](https://github.com/venturedrake)
- [All Contributors](../../contributors)

## License

Laravel Neto is open-sourced software licensed under the [MIT license](LICENSE.md).
