<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Tests;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use VentureDrake\Neto\NetoServiceProvider;

abstract class TestCase extends Orchestra
{
    public const API_URL = 'https://example.neto.com.au/do/WS/NetoAPI';

    public const API_KEY = 'test-api-key-0123456789';

    public const API_USERNAME = 'APIUser';

    protected function getPackageProviders($app): array
    {
        return [
            NetoServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('neto.api_url', self::API_URL);
        $app['config']->set('neto.api_key', self::API_KEY);
        $app['config']->set('neto.username', self::API_USERNAME);
    }

    /**
     * Fake a successful Neto API response for the configured endpoint.
     *
     * @param  array<string, mixed>  $body
     */
    protected function fakeNeto(array $body = [], int $status = 200): void
    {
        Http::fake([
            self::API_URL => Http::response($body + ['Ack' => 'Success', 'CurrentTime' => '2026-01-01 00:00:00'], $status),
        ]);
    }

    /**
     * The decoded JSON body of the single request sent to the Neto API.
     *
     * @return array<string, mixed>
     */
    protected function sentPayload(): array
    {
        $payload = [];

        Http::assertSent(function (Request $request) use (&$payload): bool {
            $payload = $request->data();

            return true;
        });

        return $payload;
    }

    /**
     * The headers of the single request sent to the Neto API.
     *
     * @return array<string, list<string>>
     */
    protected function sentHeaders(): array
    {
        $headers = [];

        Http::assertSent(function (Request $request) use (&$headers): bool {
            $headers = $request->headers();

            return true;
        });

        return $headers;
    }
}
