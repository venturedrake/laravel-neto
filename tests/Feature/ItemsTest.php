<?php

declare(strict_types=1);

use VentureDrake\Neto\Exceptions\NotFoundException;
use VentureDrake\Neto\Facades\Neto;
use VentureDrake\Neto\Resources\Items;

it('lists items with the default output selectors', function () {
    $this->fakeNeto(['Item' => [['SKU' => 'A'], ['SKU' => 'B']]]);

    expect(Neto::items()->get(['IsActive' => true]))->toBe([['SKU' => 'A'], ['SKU' => 'B']])
        ->and($this->sentPayload())->toBe([
            'Filter' => ['IsActive' => true, 'OutputSelector' => Items::DEFAULT_SELECTORS],
        ])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['GetItem']);
});

it('finds an item by sku', function () {
    $this->fakeNeto(['Item' => [['SKU' => 'A', 'Name' => 'Widget']]]);

    expect(Neto::items()->find('A', ['SKU', 'Name']))->toBe(['SKU' => 'A', 'Name' => 'Widget'])
        ->and($this->sentPayload())->toBe([
            'Filter' => ['SKU' => ['A'], 'OutputSelector' => ['SKU', 'Name']],
        ]);
});

it('throws when an item is not found', function () {
    $this->fakeNeto(['Item' => []]);

    expect(fn () => Neto::items()->find('MISSING'))
        ->toThrow(NotFoundException::class, 'Item "MISSING" was not found in Neto.');
});

it('creates an item', function () {
    $this->fakeNeto(['Item' => [['SKU' => 'NEW', 'InventoryID' => '42']]]);

    expect(Neto::items()->create(['SKU' => 'NEW', 'Name' => 'New Widget']))->toBe(['SKU' => 'NEW', 'InventoryID' => '42'])
        ->and($this->sentPayload())->toBe(['Item' => [['SKU' => 'NEW', 'Name' => 'New Widget']]])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['AddItem']);
});

it('updates an item', function () {
    $this->fakeNeto(['Item' => [['SKU' => 'A']]]);

    expect(Neto::items()->update('A', ['DefaultPrice' => 19.95]))->toBe(['SKU' => 'A'])
        ->and($this->sentPayload())->toBe(['Item' => [['SKU' => 'A', 'DefaultPrice' => 19.95]]])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['UpdateItem']);
});
