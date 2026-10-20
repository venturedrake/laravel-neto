<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Facades;

use Illuminate\Support\Facades\Facade;
use VentureDrake\Neto\Resources\Customers;
use VentureDrake\Neto\Resources\Items;
use VentureDrake\Neto\Resources\Orders;

/**
 * @method static array<string, mixed> request(string $action, array<string, mixed> $payload = [])
 * @method static array<string, mixed> get(string $action, array<string, mixed> $filter = [], list<string> $outputSelector = [])
 * @method static Orders orders()
 * @method static Items items()
 * @method static Customers customers()
 * @method static string apiUrl()
 * @method static string username()
 *
 * @see \VentureDrake\Neto\Neto
 */
class Neto extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \VentureDrake\Neto\Neto::class;
    }
}
