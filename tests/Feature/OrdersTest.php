<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use VentureDrake\Neto\Exceptions\NotFoundException;
use VentureDrake\Neto\Exceptions\ResponseException;
use VentureDrake\Neto\Facades\Neto;
use VentureDrake\Neto\Resources\Orders;
use VentureDrake\Neto\Tests\TestCase;

it('lists orders using the default statuses and output selectors', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10001'], ['OrderID' => 'N10002']]]);

    $orders = Neto::orders()->get();

    expect($orders)->toBe([['OrderID' => 'N10001'], ['OrderID' => 'N10002']]);

    $payload = $this->sentPayload();

    expect($payload['Filter']['OrderStatus'])->toBe(['New', 'Pick', 'Pack', 'Quote'])
        ->and($payload['Filter']['OutputSelector'])->toBe(config('neto.orders.output_selectors'));

    expect($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['GetOrder']);
});

it('lists orders with explicit filters and output selectors', function () {
    $this->fakeNeto(['Order' => []]);

    $orders = Neto::orders()->get(
        ['OrderStatus' => ['Dispatched'], 'DatePlacedFrom' => '2026-01-01 00:00:00'],
        ['OrderID', 'OrderStatus'],
    );

    expect($orders)->toBe([])
        ->and($this->sentPayload())->toBe([
            'Filter' => [
                'OrderStatus' => ['Dispatched'],
                'DatePlacedFrom' => '2026-01-01 00:00:00',
                'OutputSelector' => ['OrderID', 'OrderStatus'],
            ],
        ]);
});

it('does not force default statuses when the config has none', function () {
    config()->set('neto.orders.default_statuses', []);

    $this->fakeNeto(['Order' => []]);

    Neto::orders()->get(['Username' => ['jane']]);

    expect($this->sentPayload()['Filter'])->not->toHaveKey('OrderStatus');
});

it('returns an empty list when the response has no orders', function () {
    $this->fakeNeto();

    expect(Neto::orders()->get())->toBe([]);
});

it('finds an order by id', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10001', 'OrderStatus' => 'New']]]);

    $order = Neto::orders()->find('N10001');

    expect($order)->toBe(['OrderID' => 'N10001', 'OrderStatus' => 'New'])
        ->and($this->sentPayload()['Filter']['OrderID'])->toBe(['N10001'])
        ->and($this->sentPayload()['Filter']['OutputSelector'])->toBe(config('neto.orders.output_selectors'));
});

it('finds an order with custom output selectors', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10001']]]);

    Neto::orders()->find('N10001', ['OrderID', 'GrandTotal']);

    expect($this->sentPayload()['Filter']['OutputSelector'])->toBe(['OrderID', 'GrandTotal']);
});

it('handles a single order returned as an object rather than a list', function () {
    $this->fakeNeto(['Order' => ['OrderID' => 'N10001', 'OrderStatus' => 'New']]);

    expect(Neto::orders()->find('N10001'))->toBe(['OrderID' => 'N10001', 'OrderStatus' => 'New']);
});

it('throws when an order is not found', function () {
    $this->fakeNeto(['Order' => []]);

    expect(fn () => Neto::orders()->find('MISSING'))
        ->toThrow(NotFoundException::class, 'Order "MISSING" was not found in Neto.');
});

it('creates an order', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10003', 'OrderStatus' => 'New']]]);

    $order = [
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
        'OrderLine' => [
            ['SKU' => 'PROD-001', 'Quantity' => 2],
        ],
    ];

    $created = Neto::orders()->create($order);

    expect($created)->toBe(['OrderID' => 'N10003', 'OrderStatus' => 'New'])
        ->and($this->sentPayload())->toBe(['Order' => [$order]])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['AddOrder']);
});

it('surfaces api errors when creating an order', function () {
    Http::fake([
        TestCase::API_URL => Http::response([
            'Ack' => 'Error',
            'Messages' => ['Error' => ['Message' => 'Invalid product SKU']],
        ]),
    ]);

    expect(fn () => Neto::orders()->create(['OrderLine' => [['SKU' => 'NOPE', 'Quantity' => 1]]]))
        ->toThrow(ResponseException::class, 'Invalid product SKU');
});

it('updates an order', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10001']]]);

    $updated = Neto::orders()->update('N10001', ['OrderStatus' => 'Pick', 'ShippingMethod' => 'Express']);

    expect($updated)->toBe(['OrderID' => 'N10001'])
        ->and($this->sentPayload())->toBe([
            'Order' => [['OrderID' => 'N10001', 'OrderStatus' => 'Pick', 'ShippingMethod' => 'Express']],
        ])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['UpdateOrder']);
});

it('does not let the attributes override the order id when updating', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10001']]]);

    Neto::orders()->update('N10001', ['OrderID' => 'OTHER', 'OrderStatus' => 'Pick']);

    expect($this->sentPayload()['Order'][0]['OrderID'])->toBe('N10001');
});

it('cancels an order', function () {
    $this->fakeNeto(['Order' => [['OrderID' => 'N10001']]]);

    Neto::orders()->cancel('N10001');

    expect($this->sentPayload())->toBe(['Order' => [['OrderID' => 'N10001', 'OrderStatus' => 'Cancelled']]])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['UpdateOrder']);
});

it('retrieves the status of an order', function () {
    $this->fakeNeto(['Order' => [[
        'OrderID' => 'N10001',
        'OrderStatus' => 'Pack',
        'DatePlaced' => '2026-01-02 10:00:00',
        'DateUpdated' => '2026-01-03 11:00:00',
    ]]]);

    expect(Neto::orders()->status('N10001'))->toBe([
        'OrderID' => 'N10001',
        'OrderStatus' => 'Pack',
        'DatePlaced' => '2026-01-02 10:00:00',
        'DateUpdated' => '2026-01-03 11:00:00',
    ])->and($this->sentPayload()['Filter']['OutputSelector'])->toBe(Orders::STATUS_SELECTORS);
});

it('retrieves tracking details for a dispatched order', function () {
    $this->fakeNeto(['Order' => [[
        'OrderID' => 'N10001',
        'OrderStatus' => 'Dispatched',
        'DatePlaced' => '2026-01-02 10:00:00',
        'ShippingOption' => 'Express Post',
        'ShippingTrackingNumber' => 'TRACK123',
        'OrderLine' => [
            ['SKU' => 'PROD-001', 'ShippingTracking' => 'TRACK123'],
        ],
    ]]]);

    $tracking = Neto::orders()->tracking('N10001');

    expect($tracking)->toBe([
        'OrderID' => 'N10001',
        'OrderStatus' => 'Dispatched',
        'DatePlaced' => '2026-01-02 10:00:00',
        'ShippingOption' => 'Express Post',
        'TrackingNumber' => 'TRACK123',
        'Dispatched' => true,
        'OrderLine' => [['SKU' => 'PROD-001', 'ShippingTracking' => 'TRACK123']],
    ])->and($this->sentPayload()['Filter']['OutputSelector'])->toBe(Orders::TRACKING_SELECTORS);
});

it('falls back to order line tracking numbers', function () {
    $this->fakeNeto(['Order' => [[
        'OrderID' => 'N10001',
        'OrderStatus' => 'Dispatched',
        'ShippingTrackingNumber' => '',
        'OrderLine' => [
            ['SKU' => 'PROD-001', 'ShippingTracking' => ''],
            ['SKU' => 'PROD-002', 'ShippingTracking' => 'LINE456'],
        ],
    ]]]);

    expect(Neto::orders()->tracking('N10001')['TrackingNumber'])->toBe('LINE456');
});

it('reports orders that are not yet dispatched', function () {
    $this->fakeNeto(['Order' => [[
        'OrderID' => 'N10001',
        'OrderStatus' => 'Pick',
    ]]]);

    $tracking = Neto::orders()->tracking('N10001');

    expect($tracking['Dispatched'])->toBeFalse()
        ->and($tracking['TrackingNumber'])->toBeNull()
        ->and($tracking['ShippingOption'])->toBeNull()
        ->and($tracking['OrderLine'])->toBe([]);
});

it('reads its defaults from the config', function () {
    config()->set('neto.orders.default_statuses', ['New']);
    config()->set('neto.orders.output_selectors', ['OrderID']);

    $orders = Neto::orders();

    expect($orders->defaultStatuses())->toBe(['New'])
        ->and($orders->defaultOutputSelectors())->toBe(['OrderID']);
});
