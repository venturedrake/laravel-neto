<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Psr\Log\AbstractLogger;
use VentureDrake\Neto\Exceptions\RequestException;
use VentureDrake\Neto\Exceptions\ResponseException;
use VentureDrake\Neto\Neto;
use VentureDrake\Neto\Resources\Customers;
use VentureDrake\Neto\Resources\Items;
use VentureDrake\Neto\Resources\Orders;
use VentureDrake\Neto\Tests\TestCase;

it('posts the action and credentials as headers with a json body', function () {
    $this->fakeNeto(['Order' => []]);

    app(Neto::class)->request('GetOrder', ['Filter' => ['OrderID' => ['N10001']]]);

    Http::assertSentCount(1);

    Http::assertSent(fn (Request $request): bool => $request->url() === TestCase::API_URL
        && $request->method() === 'POST'
        && $request->hasHeader('NETOAPI_ACTION', 'GetOrder')
        && $request->hasHeader('NETOAPI_KEY', TestCase::API_KEY)
        && $request->hasHeader('NETOAPI_USERNAME', TestCase::API_USERNAME)
        && $request->hasHeader('Accept', 'application/json')
        && $request->isJson()
        && $request->data() === ['Filter' => ['OrderID' => ['N10001']]]);
});

it('sends an empty json object when there is no payload', function () {
    $this->fakeNeto();

    app(Neto::class)->request('GetContent');

    Http::assertSent(fn (Request $request): bool => $request->body() === '{}');
});

it('returns the decoded response', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10001']]]);

    $response = app(Neto::class)->request('GetOrder', ['Filter' => ['OrderID' => ['N10001']]]);

    expect($response)
        ->toHaveKey('Ack', 'Success')
        ->toHaveKey('Order', [['OrderID' => 'N10001']]);
});

it('builds a filter block for get requests', function () {
    $this->fakeNeto(['Item' => []]);

    app(Neto::class)->get('GetItem', ['SKU' => ['ABC']], ['SKU', 'Name']);

    expect($this->sentPayload())->toBe([
        'Filter' => [
            'SKU' => ['ABC'],
            'OutputSelector' => ['SKU', 'Name'],
        ],
    ]);
});

it('does not add an empty output selector to get requests', function () {
    $this->fakeNeto(['Item' => []]);

    app(Neto::class)->get('GetItem', ['SKU' => ['ABC']]);

    expect($this->sentPayload())->toBe(['Filter' => ['SKU' => ['ABC']]]);
});

it('throws a response exception when the api reports an error', function () {
    Http::fake([
        TestCase::API_URL => Http::response([
            'Ack' => 'Error',
            'Messages' => [
                'Error' => [
                    'Message' => 'Invalid product SKU',
                    'SeverityCode' => 'Error',
                    'Description' => 'The SKU INVALID does not exist.',
                ],
            ],
        ]),
    ]);

    try {
        app(Neto::class)->request('AddOrder', ['Order' => [['OrderLine' => [['SKU' => 'INVALID']]]]]);
    } catch (ResponseException $exception) {
        expect($exception->getMessage())
            ->toBe('Neto API request "AddOrder" returned an error: Invalid product SKU (The SKU INVALID does not exist.)')
            ->and($exception->action)->toBe('AddOrder')
            ->and($exception->firstError())->toBe('Invalid product SKU')
            ->and($exception->errors)->toHaveCount(1)
            ->and($exception->response['Ack'])->toBe('Error');

        return;
    }

    $this->fail('ResponseException was not thrown.');
});

it('throws a response exception when error messages are present without an error ack', function () {
    Http::fake([
        TestCase::API_URL => Http::response([
            'Ack' => 'Warning',
            'Messages' => ['Error' => [['Message' => 'First'], ['Message' => 'Second']]],
        ]),
    ]);

    expect(fn () => app(Neto::class)->request('UpdateOrder'))
        ->toThrow(ResponseException::class, 'First; Second');
});

it('throws a request exception for a failed http status', function () {
    Http::fake([
        TestCase::API_URL => Http::response('Forbidden', 403),
    ]);

    try {
        app(Neto::class)->request('GetOrder');
    } catch (RequestException $exception) {
        expect($exception->getMessage())->toBe('Neto API request "GetOrder" failed with HTTP status 403.')
            ->and($exception->getCode())->toBe(403)
            ->and($exception->action)->toBe('GetOrder')
            ->and($exception->response?->status())->toBe(403);

        return;
    }

    $this->fail('RequestException was not thrown.');
});

it('prefers the api error over the http status when both are present', function () {
    Http::fake([
        TestCase::API_URL => Http::response([
            'Ack' => 'Error',
            'Messages' => ['Error' => ['Message' => 'Invalid API key']],
        ], 401),
    ]);

    expect(fn () => app(Neto::class)->request('GetOrder'))
        ->toThrow(ResponseException::class, 'Invalid API key');
});

it('throws a request exception when the body is not json', function () {
    Http::fake([
        TestCase::API_URL => Http::response('<html>Service unavailable</html>', 200),
    ]);

    expect(fn () => app(Neto::class)->request('GetOrder'))
        ->toThrow(RequestException::class, 'could not be decoded as JSON');
});

it('throws a request exception when the connection fails', function () {
    Http::fake([
        TestCase::API_URL => Http::failedConnection('cURL error 28: Operation timed out'),
    ]);

    try {
        app(Neto::class)->request('GetOrder');
    } catch (RequestException $exception) {
        expect($exception->getMessage())->toContain('could not be completed: cURL error 28')
            ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class)
            ->and($exception->response)->toBeNull();

        return;
    }

    $this->fail('RequestException was not thrown.');
});

it('retries server errors when configured', function () {
    config()->set('neto.retry', ['times' => 3, 'sleep' => 0]);

    Http::fakeSequence(TestCase::API_URL)
        ->pushStatus(503)
        ->pushStatus(502)
        ->push(['Ack' => 'Success', 'Order' => []]);

    $response = app(Neto::class)->request('GetOrder');

    expect($response['Ack'])->toBe('Success');

    Http::assertSentCount(3);
});

it('retries connection failures when configured', function () {
    config()->set('neto.retry', ['times' => 2, 'sleep' => 0]);

    $attempts = 0;

    Http::fake(function () use (&$attempts) {
        return ++$attempts === 1
            ? Http::failedConnection('cURL error 28: Operation timed out')
            : Http::response(['Ack' => 'Success', 'Order' => []]);
    });

    $response = app(Neto::class)->request('GetOrder');

    expect($response['Ack'])->toBe('Success')
        ->and($attempts)->toBe(2);
});

it('gives up after the configured number of attempts', function () {
    config()->set('neto.retry', ['times' => 2, 'sleep' => 0]);

    Http::fakeSequence(TestCase::API_URL)
        ->pushStatus(503)
        ->pushStatus(503)
        ->push(['Ack' => 'Success']);

    expect(fn () => app(Neto::class)->request('GetOrder'))
        ->toThrow(RequestException::class, 'HTTP status 503');

    Http::assertSentCount(2);
});

it('does not retry client errors', function () {
    config()->set('neto.retry', ['times' => 3, 'sleep' => 0]);

    Http::fakeSequence(TestCase::API_URL)
        ->pushStatus(404)
        ->push(['Ack' => 'Success']);

    expect(fn () => app(Neto::class)->request('GetOrder'))
        ->toThrow(RequestException::class, 'HTTP status 404');

    Http::assertSentCount(1);
});

it('does not retry by default', function () {
    Http::fakeSequence(TestCase::API_URL)
        ->pushStatus(500)
        ->push(['Ack' => 'Success']);

    expect(fn () => app(Neto::class)->request('GetOrder'))
        ->toThrow(RequestException::class, 'HTTP status 500');

    Http::assertSentCount(1);
});

it('logs requests and responses with a masked api key when a logger is given', function () {
    $this->fakeNeto(['Order' => []]);

    $logger = new class extends AbstractLogger
    {
        /** @var list<array{level: mixed, message: string, context: array<string, mixed>}> */
        public array $records = [];

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
        }
    };

    $client = new Neto(
        http: app(HttpFactory::class),
        apiUrl: TestCase::API_URL,
        apiKey: TestCase::API_KEY,
        username: TestCase::API_USERNAME,
        logger: $logger,
    );

    $client->request('GetOrder', ['Filter' => ['OrderID' => ['N10001']]]);

    expect($logger->records)->toHaveCount(2)
        ->and($logger->records[0]['message'])->toBe('Neto API request')
        ->and($logger->records[0]['context']['action'])->toBe('GetOrder')
        ->and($logger->records[0]['context']['payload'])->toBe(['Filter' => ['OrderID' => ['N10001']]])
        ->and($logger->records[0]['context']['api_key'])->toBe('test***************6789')
        ->and($logger->records[0]['context']['api_key'])->not->toContain(TestCase::API_KEY)
        ->and($logger->records[1]['message'])->toBe('Neto API response')
        ->and($logger->records[1]['context']['status'])->toBe(200)
        ->and($logger->records[1]['context']['body'])->toContain('"Ack":"Success"');
});

it('does not log when logging is disabled', function () {
    $this->fakeNeto(['Order' => []]);

    $client = new Neto(
        http: app(HttpFactory::class),
        apiUrl: TestCase::API_URL,
        apiKey: TestCase::API_KEY,
        username: TestCase::API_USERNAME,
    );

    expect($client->request('GetOrder'))->toHaveKey('Ack', 'Success');
});

it('exposes the resources', function () {
    $client = app(Neto::class);

    expect($client->orders())->toBeInstanceOf(Orders::class)
        ->and($client->items())->toBeInstanceOf(Items::class)
        ->and($client->customers())->toBeInstanceOf(Customers::class)
        ->and($client->orders()->client())->toBe($client);
});
