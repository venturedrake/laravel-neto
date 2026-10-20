---
name: laravel-neto-development
description: >
  Configure and apply the Laravel Neto package (venturedrake/laravel-neto) to talk to the Maropost Commerce Cloud (Neto) API from a Laravel application.
license: MIT
metadata:
  author: Andrew Drake
---

# Laravel Neto

Use this skill when a Laravel application needs to read or write Neto (Maropost Commerce Cloud) data: orders, items, customers, or any other Neto API action.

## Primary Goal

- apply the `venturedrake/laravel-neto` package's public API in the smallest correct way

## Workflow

### 1. Inspect the Laravel app context

- confirm the app is a Laravel project with the package installed (`composer require venturedrake/laravel-neto`)
- confirm `NETO_API_URL`, `NETO_API_KEY` and `NETO_API_USERNAME` are set in `.env`; publish `config/neto.php` only when defaults need changing

### 2. Apply the package's public API

- resolve the client with the `VentureDrake\Neto\Facades\Neto` facade or inject `VentureDrake\Neto\Neto`
- use `Neto::orders()`, `Neto::items()` and `Neto::customers()` for the common resources:
  - `get(array $filter = [], array $outputSelector = [])` lists records (orders apply the configured default statuses when no `OrderStatus` filter is given)
  - `find(string $id, array $outputSelector = [])` returns one record or throws `NotFoundException`
  - `create(array $record)` / `update(string $id, array $attributes)` send `Add*` / `Update*` actions and return the record Neto echoes back
  - orders also have `cancel()`, `status()` and `tracking()`
- use `Neto::request(string $action, array $payload)` for any other action; `Neto::get($action, $filter, $outputSelector)` builds the `Filter` block for `Get*` actions
- pass field names exactly as Neto documents them (PascalCase, e.g. `ShipFirstName`, `OrderLine`, `OutputSelector`)
- catch `VentureDrake\Neto\Exceptions\NetoException` (or its subclasses `ConfigurationException`, `RequestException`, `ResponseException`, `NotFoundException`); methods never return success flags
- in tests, fake the endpoint with `Http::fake([config('neto.api_url') => Http::response([...])])`

## Rules, References, and Templates

Read before executing:

- the package README for the full resource and exception reference

## Examples

- "Create a Neto order from a checkout": build the AddOrder record (billing, shipping, `OrderLine` with `SKU` and `Quantity`), call `Neto::orders()->create($order)`, store the returned `OrderID`, and handle `ResponseException` for validation errors from Neto.
- "Show tracking for an order": call `Neto::orders()->tracking($orderId)` and use `TrackingNumber` and `Dispatched` from the result.

## Anti-patterns

- do not call the Neto endpoint with `Http::` directly in app code; go through the package so authentication, retries, logging and error handling stay in one place
- do not document package internals here; keep the skill focused on adoption in Laravel apps
