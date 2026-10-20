<?php

declare(strict_types=1);

use VentureDrake\Neto\Exceptions\NotFoundException;
use VentureDrake\Neto\Facades\Neto;
use VentureDrake\Neto\Resources\Customers;

it('lists customers with the default output selectors', function () {
    $this->fakeNeto(['Customer' => [['Username' => 'jane']]]);

    expect(Neto::customers()->get(['DateUpdatedFrom' => '2026-01-01 00:00:00']))->toBe([['Username' => 'jane']])
        ->and($this->sentPayload())->toBe([
            'Filter' => ['DateUpdatedFrom' => '2026-01-01 00:00:00', 'OutputSelector' => Customers::DEFAULT_SELECTORS],
        ])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['GetCustomer']);
});

it('finds a customer by username', function () {
    $this->fakeNeto(['Customer' => [['Username' => 'jane', 'EmailAddress' => 'jane@example.com']]]);

    expect(Neto::customers()->find('jane'))->toBe(['Username' => 'jane', 'EmailAddress' => 'jane@example.com'])
        ->and($this->sentPayload()['Filter']['Username'])->toBe(['jane']);
});

it('finds a customer by email', function () {
    $this->fakeNeto(['Customer' => [['Username' => 'jane']]]);

    expect(Neto::customers()->findByEmail('jane@example.com'))->toBe(['Username' => 'jane'])
        ->and($this->sentPayload()['Filter']['Email'])->toBe(['jane@example.com']);
});

it('throws when a customer is not found', function () {
    $this->fakeNeto(['Customer' => []]);

    expect(fn () => Neto::customers()->find('nobody'))
        ->toThrow(NotFoundException::class, 'Customer "nobody" was not found in Neto.');
});

it('creates a customer', function () {
    $this->fakeNeto(['Customer' => [['Username' => 'jane']]]);

    expect(Neto::customers()->create(['Username' => 'jane', 'EmailAddress' => 'jane@example.com']))->toBe(['Username' => 'jane'])
        ->and($this->sentPayload())->toBe(['Customer' => [['Username' => 'jane', 'EmailAddress' => 'jane@example.com']]])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['AddCustomer']);
});

it('updates a customer', function () {
    $this->fakeNeto(['Customer' => [['Username' => 'jane']]]);

    expect(Neto::customers()->update('jane', ['OnCreditHold' => true]))->toBe(['Username' => 'jane'])
        ->and($this->sentPayload())->toBe(['Customer' => [['Username' => 'jane', 'OnCreditHold' => true]]])
        ->and($this->sentHeaders()['NETOAPI_ACTION'])->toBe(['UpdateCustomer']);
});
