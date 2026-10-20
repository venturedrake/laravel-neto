<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Resources;

use VentureDrake\Neto\Exceptions\NotFoundException;

/**
 * Items (products) in Neto.
 *
 * @see https://developers.maropost.com/ (GetItem, AddItem, UpdateItem)
 */
class Items extends Resource
{
    public const string KEY = 'Item';

    /**
     * Output selectors returned when none are given.
     *
     * @var list<string>
     */
    public const array DEFAULT_SELECTORS = [
        'ID',
        'SKU',
        'Name',
        'Brand',
        'Model',
        'ParentSKU',
        'IsActive',
        'Approved',
        'Virtual',
        'DefaultPrice',
        'PromotionPrice',
        'CostPrice',
        'RRP',
        'TaxFreeItem',
        'AvailableSellQuantity',
        'WarehouseQuantity',
        'Images',
        'Categories',
        'DateAdded',
        'DateUpdated',
    ];

    /**
     * List items matching the given GetItem filter.
     *
     * @param  array<string, mixed>  $filter  Any GetItem filter keys, e.g. SKU, IsActive, DateUpdatedFrom, Page, Limit
     * @param  list<string>  $outputSelector
     * @return list<array<string, mixed>>
     */
    public function get(array $filter = [], array $outputSelector = []): array
    {
        $response = $this->client->get('GetItem', $filter, $outputSelector ?: self::DEFAULT_SELECTORS);

        return $this->records($response, self::KEY);
    }

    /**
     * Retrieve a single item by SKU.
     *
     * @param  list<string>  $outputSelector
     * @return array<string, mixed>
     *
     * @throws NotFoundException
     */
    public function find(string $sku, array $outputSelector = []): array
    {
        $response = $this->client->get('GetItem', ['SKU' => [$sku]], $outputSelector ?: self::DEFAULT_SELECTORS);

        return $this->firstRecord($response, self::KEY, $sku);
    }

    /**
     * Create an item.
     *
     * @param  array<string, mixed>  $item  The AddItem "Item" record (SKU, Name, DefaultPrice, ...)
     * @return array<string, mixed>
     */
    public function create(array $item): array
    {
        $response = $this->client->request('AddItem', $this->wrap(self::KEY, $item));

        return $this->firstRecord($response, self::KEY, $this->sku($item));
    }

    /**
     * Update an existing item.
     *
     * @param  array<string, mixed>  $attributes  The UpdateItem fields to change
     * @return array<string, mixed>
     */
    public function update(string $sku, array $attributes): array
    {
        $response = $this->client->request('UpdateItem', $this->wrap(self::KEY, ['SKU' => $sku] + $attributes));

        return $this->firstRecord($response, self::KEY, $sku);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function sku(array $item): string
    {
        $sku = $item['SKU'] ?? null;

        return is_scalar($sku) ? (string) $sku : 'new item';
    }
}
