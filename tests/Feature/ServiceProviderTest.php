<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use VentureDrake\Neto\Exceptions\ConfigurationException;
use VentureDrake\Neto\Facades\Neto as NetoFacade;
use VentureDrake\Neto\Neto;
use VentureDrake\Neto\Resources\Orders;
use VentureDrake\Neto\Tests\TestCase;

it('resolves the client from the container', function () {
    expect(app(Neto::class))->toBeInstanceOf(Neto::class);
});

it('returns the same instance from the container', function () {
    expect(app(Neto::class))->toBe(app(Neto::class));
});

it('aliases the client as "neto"', function () {
    expect(app('neto'))->toBe(app(Neto::class));
});

it('resolves the facade to the client', function () {
    expect(NetoFacade::getFacadeRoot())->toBe(app(Neto::class));
    expect(NetoFacade::orders())->toBeInstanceOf(Orders::class);
});

it('merges the package config', function () {
    expect(config('neto.timeout'))->toBe(30)
        ->and(config('neto.retry.times'))->toBe(1)
        ->and(config('neto.logging.enabled'))->toBeFalse()
        ->and(config('neto.orders.default_statuses'))->toBe(['New', 'Pick', 'Pack', 'Quote'])
        ->and(config('neto.orders.output_selectors'))->toContain('OrderID', 'OrderStatus', 'OrderLine');
});

it('builds the client from the merged config', function () {
    $client = app(Neto::class);

    expect($client->apiUrl())->toBe(TestCase::API_URL)
        ->and($client->username())->toBe(TestCase::API_USERNAME);
});

it('fails clearly when the api key is not configured', function () {
    config()->set('neto.api_key', null);

    expect(fn () => app(Neto::class))
        ->toThrow(ConfigurationException::class, '"api_key" has not been configured');
});

it('fails clearly when the api url is blank', function () {
    config()->set('neto.api_url', '   ');

    expect(fn () => app(Neto::class))
        ->toThrow(ConfigurationException::class, '"api_url" has not been configured');
});

it('publishes the config file', function () {
    $target = config_path('neto.php');

    File::delete($target);

    $this->artisan('vendor:publish', ['--tag' => 'laravel-neto-config'])->assertSuccessful();

    expect(File::exists($target))->toBeTrue();

    File::delete($target);
});
