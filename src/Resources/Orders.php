<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Resources;

use VentureDrake\Neto\Exceptions\NotFoundException;
use VentureDrake\Neto\Neto;

/**
 * Orders in Neto: list, find, create, update, cancel, status and tracking.
 *
 * @see https://developers.maropost.com/ (GetOrder, AddOrder, UpdateOrder)
 */
class Orders extends Resource
{
    public const KEY = 'Order';

    /**
     * Output selectors used when a status check is all that is needed.
     *
     * @var list<string>
     */
    public const STATUS_SELECTORS = ['OrderID', 'OrderStatus', 'DatePlaced', 'DateUpdated'];

    /**
     * Output selectors used when retrieving tracking details.
     *
     * @var list<string>
     */
    public const TRACKING_SELECTORS = [
        'OrderID',
        'OrderStatus',
        'DatePlaced',
        'ShippingOption',
        'ShippingTrackingNumber',
        'OrderLine',
        'OrderLine.ShippingMethod',
        'OrderLine.ShippingTracking',
    ];

    /**
     * @param  array<string, mixed>  $defaults  The "orders" section of config/neto.php
     */
    public function __construct(
        Neto $client,
        protected readonly array $defaults = [],
    ) {
        parent::__construct($client);
    }

    /**
     * List orders matching the given GetOrder filter.
     *
     * When no "OrderStatus" filter is given, the default statuses from the
     * package configuration are applied. When no output selectors are given,
     * the configured default selectors are used.
     *
     * @param  array<string, mixed>  $filter  Any GetOrder filter keys, e.g. OrderStatus, DatePlacedFrom, Username
     * @param  list<string>  $outputSelector
     * @return list<array<string, mixed>>
     */
    public function get(array $filter = [], array $outputSelector = []): array
    {
        if (! array_key_exists('OrderStatus', $filter) && $this->defaultStatuses() !== []) {
            $filter['OrderStatus'] = $this->defaultStatuses();
        }

        $response = $this->client->get('GetOrder', $filter, $outputSelector ?: $this->defaultOutputSelectors());

        return $this->records($response, self::KEY);
    }

    /**
     * Retrieve a single order by its Neto order ID.
     *
     * @param  list<string>  $outputSelector
     * @return array<string, mixed>
     *
     * @throws NotFoundException
     */
    public function find(string $orderId, array $outputSelector = []): array
    {
        $response = $this->client->get('GetOrder', [
            'OrderID' => [$orderId],
        ], $outputSelector ?: $this->defaultOutputSelectors());

        return $this->firstRecord($response, self::KEY, $orderId);
    }

    /**
     * Create an order.
     *
     * The array is sent as the AddOrder "Order" record, so it accepts every
     * field documented for that action (BillFirstName, ShipStreet1,
     * OrderLine, ShippingMethod, OrderStatus, ...).
     *
     * @param  array<string, mixed>  $order
     * @return array<string, mixed>  The record Neto returns for the new order, including its OrderID
     */
    public function create(array $order): array
    {
        $response = $this->client->request('AddOrder', $this->wrap(self::KEY, $order));

        return $this->firstRecord($response, self::KEY, $this->describe($order));
    }

    /**
     * Update an existing order.
     *
     * @param  array<string, mixed>  $attributes  The UpdateOrder fields to change, e.g. OrderStatus, ShippingMethod
     * @return array<string, mixed>
     */
    public function update(string $orderId, array $attributes): array
    {
        $response = $this->client->request('UpdateOrder', $this->wrap(self::KEY, ['OrderID' => $orderId] + $attributes));

        return $this->firstRecord($response, self::KEY, $orderId);
    }

    /**
     * Cancel an order by setting its status to "Cancelled".
     *
     * @return array<string, mixed>
     */
    public function cancel(string $orderId): array
    {
        return $this->update($orderId, ['OrderStatus' => 'Cancelled']);
    }

    /**
     * Retrieve just the status details of an order.
     *
     * @return array{OrderID: string, OrderStatus: string|null, DatePlaced: string|null, DateUpdated: string|null}
     *
     * @throws NotFoundException
     */
    public function status(string $orderId): array
    {
        $order = $this->find($orderId, self::STATUS_SELECTORS);

        return [
            'OrderID' => $orderId,
            'OrderStatus' => $this->string($order, 'OrderStatus'),
            'DatePlaced' => $this->string($order, 'DatePlaced'),
            'DateUpdated' => $this->string($order, 'DateUpdated'),
        ];
    }

    /**
     * Retrieve the shipping and tracking details of an order.
     *
     * The order-level ShippingTrackingNumber is returned as TrackingNumber.
     * When it is empty, the first tracking number found on an order line is
     * used instead, as Neto records per-line tracking for split shipments.
     *
     * @return array{OrderID: string, OrderStatus: string|null, DatePlaced: string|null, ShippingOption: string|null, TrackingNumber: string|null, Dispatched: bool, OrderLine: list<array<string, mixed>>}
     *
     * @throws NotFoundException
     */
    public function tracking(string $orderId): array
    {
        $order = $this->find($orderId, self::TRACKING_SELECTORS);

        $lines = $this->records($order, 'OrderLine');
        $trackingNumber = $this->string($order, 'ShippingTrackingNumber');

        if ($trackingNumber === null) {
            foreach ($lines as $line) {
                if (($lineTracking = $this->string($line, 'ShippingTracking')) !== null) {
                    $trackingNumber = $lineTracking;

                    break;
                }
            }
        }

        return [
            'OrderID' => $orderId,
            'OrderStatus' => $this->string($order, 'OrderStatus'),
            'DatePlaced' => $this->string($order, 'DatePlaced'),
            'ShippingOption' => $this->string($order, 'ShippingOption'),
            'TrackingNumber' => $trackingNumber,
            'Dispatched' => $this->string($order, 'OrderStatus') === 'Dispatched',
            'OrderLine' => $lines,
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultStatuses(): array
    {
        return $this->stringList($this->defaults['default_statuses'] ?? []);
    }

    /**
     * @return list<string>
     */
    public function defaultOutputSelectors(): array
    {
        return $this->stringList($this->defaults['output_selectors'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function string(array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        if (! is_scalar($value) || (string) $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $value): string => is_scalar($value) ? (string) $value : '', $values),
            fn (string $value): bool => $value !== '',
        ));
    }

    /**
     * A short label for an order payload, used in error messages.
     *
     * @param  array<string, mixed>  $order
     */
    private function describe(array $order): string
    {
        foreach (['OrderID', 'Email', 'Username'] as $key) {
            if (($value = $this->string($order, $key)) !== null) {
                return $value;
            }
        }

        return 'new order';
    }
}
