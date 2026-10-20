<?php

declare(strict_types=1);

use VentureDrake\Neto\Exceptions\NetoException;
use VentureDrake\Neto\Exceptions\ResponseException;

it('normalises a single error object', function () {
    expect(ResponseException::normaliseMessages([
        'Message' => 'Invalid API key',
        'SeverityCode' => 'Error',
        'Description' => 'The key was rejected.',
    ]))->toBe([
        ['Message' => 'Invalid API key', 'SeverityCode' => 'Error', 'Description' => 'The key was rejected.'],
    ]);
});

it('normalises a list of error objects', function () {
    expect(ResponseException::normaliseMessages([
        ['Message' => 'First'],
        ['Message' => 'Second', 'SeverityCode' => 'Error'],
    ]))->toBe([
        ['Message' => 'First', 'SeverityCode' => null, 'Description' => null],
        ['Message' => 'Second', 'SeverityCode' => 'Error', 'Description' => null],
    ]);
});

it('normalises bare strings and ignores junk', function () {
    expect(ResponseException::normaliseMessages(['Oops', 42, ['Description' => 'no message']]))->toBe([
        ['Message' => 'Oops', 'SeverityCode' => null, 'Description' => null],
        ['Message' => 'Unknown error', 'SeverityCode' => null, 'Description' => 'no message'],
    ]);
});

it('returns an empty list for missing or malformed messages', function () {
    expect(ResponseException::normaliseMessages(null))->toBe([])
        ->and(ResponseException::normaliseMessages('nope'))->toBe([])
        ->and(ResponseException::normaliseMessages([]))->toBe([]);
});

it('builds a readable message from the response', function () {
    $exception = ResponseException::fromResponse('AddOrder', [
        'Ack' => 'Error',
        'Messages' => ['Error' => [['Message' => 'One', 'Description' => 'first'], ['Message' => 'Two']]],
    ]);

    expect($exception)->toBeInstanceOf(NetoException::class)
        ->and($exception->getMessage())->toBe('Neto API request "AddOrder" returned an error: One (first); Two')
        ->and($exception->firstError())->toBe('One')
        ->and($exception->action)->toBe('AddOrder');
});

it('copes with an error ack that carries no message', function () {
    $exception = ResponseException::fromResponse('GetOrder', ['Ack' => 'Error']);

    expect($exception->getMessage())->toBe('Neto API request "GetOrder" returned an error: the API did not return an error message')
        ->and($exception->firstError())->toBeNull()
        ->and($exception->errors)->toBe([]);
});
