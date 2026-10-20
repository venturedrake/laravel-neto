<?php

declare(strict_types=1);

namespace VentureDrake\Neto;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException as HttpRequestException;
use Illuminate\Http\Client\Response;
use JsonSerializable;
use Psr\Log\LoggerInterface;
use stdClass;
use Throwable;
use VentureDrake\Neto\Exceptions\ConfigurationException;
use VentureDrake\Neto\Exceptions\RequestException;
use VentureDrake\Neto\Exceptions\ResponseException;
use VentureDrake\Neto\Resources\Customers;
use VentureDrake\Neto\Resources\Items;
use VentureDrake\Neto\Resources\Orders;

/**
 * A thin client for the Maropost Commerce Cloud (Neto) API.
 *
 * Every Neto API call is a POST to a single endpoint with the action named in
 * the NETOAPI_ACTION header. This class handles authentication, transport,
 * logging and error detection; the resource classes build the payloads.
 *
 * @see https://developers.maropost.com/
 */
class Neto
{
    /**
     * @param  array<string, mixed>  $orderDefaults
     */
    public function __construct(
        protected readonly HttpFactory $http,
        protected readonly string $apiUrl,
        protected readonly string $apiKey,
        protected readonly string $username,
        protected readonly int $timeout = 30,
        protected readonly int $retryTimes = 1,
        protected readonly int $retrySleep = 250,
        protected readonly ?LoggerInterface $logger = null,
        protected readonly array $orderDefaults = [],
    ) {}

    /**
     * Build a client from the package configuration array (config/neto.php).
     *
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(HttpFactory $http, array $config, ?LoggerInterface $logger = null): self
    {
        $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];
        $orders = is_array($config['orders'] ?? null) ? $config['orders'] : [];

        return new self(
            http: $http,
            apiUrl: self::requireString($config, 'api_url'),
            apiKey: self::requireString($config, 'api_key'),
            username: self::requireString($config, 'username'),
            timeout: (int) ($config['timeout'] ?? 30),
            retryTimes: max(1, (int) ($retry['times'] ?? 1)),
            retrySleep: max(0, (int) ($retry['sleep'] ?? 250)),
            logger: $logger,
            orderDefaults: $orders,
        );
    }

    /**
     * Send a request for the given Neto API action and return the decoded response.
     *
     * The payload is sent as the JSON request body exactly as given, so callers
     * can use any action and structure described in the Neto API documentation.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws RequestException when the request fails at the transport or HTTP level
     * @throws ResponseException when the API reports an error in its response
     */
    public function request(string $action, array $payload = []): array
    {
        $this->log('Neto API request', [
            'action' => $action,
            'url' => $this->apiUrl,
            'payload' => $payload,
        ]);

        $pending = $this->http
            ->withHeaders([
                'NETOAPI_ACTION' => $action,
                'NETOAPI_KEY' => $this->apiKey,
                'NETOAPI_USERNAME' => $this->username,
            ])
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                $this->retrySleep,
                fn (Throwable $exception): bool => $this->shouldRetry($exception),
                throw: false,
            );

        try {
            $response = $pending->post($this->apiUrl, $this->body($payload));
        } catch (ConnectionException $exception) {
            $this->log('Neto API connection failed', ['action' => $action, 'error' => $exception->getMessage()]);

            throw RequestException::connection($action, $exception);
        }

        $this->log('Neto API response', [
            'action' => $action,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return $this->decode($action, $response);
    }

    /**
     * Send a "Get*" style request with a Filter block built from the given
     * criteria and output selectors.
     *
     * @param  array<string, mixed>  $filter
     * @param  list<string>  $outputSelector
     * @return array<string, mixed>
     */
    public function get(string $action, array $filter = [], array $outputSelector = []): array
    {
        if ($outputSelector !== []) {
            $filter['OutputSelector'] = $outputSelector;
        }

        return $this->request($action, ['Filter' => $filter]);
    }

    /**
     * Work with orders.
     */
    public function orders(): Orders
    {
        return new Orders($this, $this->orderDefaults);
    }

    /**
     * Work with items (products).
     */
    public function items(): Items
    {
        return new Items($this);
    }

    /**
     * Work with customers.
     */
    public function customers(): Customers
    {
        return new Customers($this);
    }

    /**
     * The API endpoint this client sends requests to.
     */
    public function apiUrl(): string
    {
        return $this->apiUrl;
    }

    /**
     * The API username this client authenticates with.
     */
    public function username(): string
    {
        return $this->username;
    }

    /**
     * Decode a raw HTTP response and surface any error the API reported.
     *
     * @return array<string, mixed>
     */
    protected function decode(string $action, Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            throw $response->successful()
                ? RequestException::invalidJson($action, $response)
                : RequestException::fromResponse($action, $response);
        }

        /** @var array<string, mixed> $data */
        if (($data['Ack'] ?? null) === 'Error' || isset($data['Messages']['Error'])) {
            throw ResponseException::fromResponse($action, $data);
        }

        if ($response->failed()) {
            throw RequestException::fromResponse($action, $response);
        }

        return $data;
    }

    /**
     * The request body for a payload. Neto expects a JSON object, so an empty
     * payload is sent as "{}" rather than the "[]" an empty PHP array becomes.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|JsonSerializable
     */
    protected function body(array $payload): array|JsonSerializable
    {
        if ($payload !== []) {
            return $payload;
        }

        return new class implements JsonSerializable
        {
            public function jsonSerialize(): stdClass
            {
                return new stdClass;
            }
        };
    }

    /**
     * Retry only when the failure is transient: a connection problem or a
     * server-side (5xx) error. Client errors and API-level errors are final.
     */
    protected function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof HttpRequestException && $exception->response->serverError();
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function log(string $message, array $context = []): void
    {
        $this->logger?->debug($message, $context + ['api_key' => $this->maskedApiKey()]);
    }

    protected function maskedApiKey(): string
    {
        if (strlen($this->apiKey) <= 8) {
            return str_repeat('*', strlen($this->apiKey));
        }

        return substr($this->apiKey, 0, 4).str_repeat('*', strlen($this->apiKey) - 8).substr($this->apiKey, -4);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function requireString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw ConfigurationException::missing($key);
        }

        return trim($value);
    }
}
