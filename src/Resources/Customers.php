<?php

declare(strict_types=1);

namespace VentureDrake\Neto\Resources;

use VentureDrake\Neto\Exceptions\NotFoundException;

/**
 * Customers in Neto.
 *
 * @see https://developers.maropost.com/ (GetCustomer, AddCustomer, UpdateCustomer)
 */
class Customers extends Resource
{
    public const string KEY = 'Customer';

    /**
     * Output selectors returned when none are given.
     *
     * @var list<string>
     */
    public const array DEFAULT_SELECTORS = [
        'ID',
        'Username',
        'EmailAddress',
        'Type',
        'Active',
        'OnCreditHold',
        'BillingAddress',
        'ShippingAddress',
        'DateAdded',
        'DateUpdated',
    ];

    /**
     * List customers matching the given GetCustomer filter.
     *
     * @param  array<string, mixed>  $filter  Any GetCustomer filter keys, e.g. Username, Email, DateUpdatedFrom, Page, Limit
     * @param  list<string>  $outputSelector
     * @return list<array<string, mixed>>
     */
    public function get(array $filter = [], array $outputSelector = []): array
    {
        $response = $this->client->get('GetCustomer', $filter, $outputSelector ?: self::DEFAULT_SELECTORS);

        return $this->records($response, self::KEY);
    }

    /**
     * Retrieve a single customer by username.
     *
     * @param  list<string>  $outputSelector
     * @return array<string, mixed>
     *
     * @throws NotFoundException
     */
    public function find(string $username, array $outputSelector = []): array
    {
        $response = $this->client->get('GetCustomer', ['Username' => [$username]], $outputSelector ?: self::DEFAULT_SELECTORS);

        return $this->firstRecord($response, self::KEY, $username);
    }

    /**
     * Retrieve a single customer by email address.
     *
     * @param  list<string>  $outputSelector
     * @return array<string, mixed>
     *
     * @throws NotFoundException
     */
    public function findByEmail(string $email, array $outputSelector = []): array
    {
        $response = $this->client->get('GetCustomer', ['Email' => [$email]], $outputSelector ?: self::DEFAULT_SELECTORS);

        return $this->firstRecord($response, self::KEY, $email);
    }

    /**
     * Create a customer.
     *
     * @param  array<string, mixed>  $customer  The AddCustomer "Customer" record (Username, EmailAddress, BillingAddress, ...)
     * @return array<string, mixed>
     */
    public function create(array $customer): array
    {
        $response = $this->client->request('AddCustomer', $this->wrap(self::KEY, $customer));

        return $this->firstRecord($response, self::KEY, $this->username($customer));
    }

    /**
     * Update an existing customer.
     *
     * @param  array<string, mixed>  $attributes  The UpdateCustomer fields to change
     * @return array<string, mixed>
     */
    public function update(string $username, array $attributes): array
    {
        $response = $this->client->request('UpdateCustomer', $this->wrap(self::KEY, ['Username' => $username] + $attributes));

        return $this->firstRecord($response, self::KEY, $username);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function username(array $customer): string
    {
        $username = $customer['Username'] ?? $customer['EmailAddress'] ?? null;

        return is_scalar($username) ? (string) $username : 'new customer';
    }
}
