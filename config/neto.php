<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Neto API Endpoint
    |--------------------------------------------------------------------------
    |
    | The full URL of your store's Neto API endpoint. This is typically your
    | store's domain followed by "/do/WS/NetoAPI", for example:
    | https://www.yourstore.com.au/do/WS/NetoAPI
    |
    */

    'api_url' => env('NETO_API_URL'),

    /*
    |--------------------------------------------------------------------------
    | Neto API Credentials
    |--------------------------------------------------------------------------
    |
    | The API key and API username generated from your Neto control panel
    | (Settings & Tools > All Settings & Tools > API Settings). The username
    | is the "API Username" shown against the key, not your staff login.
    |
    */

    'api_key' => env('NETO_API_KEY'),

    'username' => env('NETO_API_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The maximum number of seconds to wait for a response from the Neto API.
    |
    */

    'timeout' => (int) env('NETO_API_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retries
    |--------------------------------------------------------------------------
    |
    | The total number of times a request will be attempted when it fails
    | with a connection error or a server (5xx) error, and how long to wait
    | between attempts in milliseconds. Set "times" to 1 to disable retries.
    |
    */

    'retry' => [
        'times' => (int) env('NETO_API_RETRY_TIMES', 1),
        'sleep' => (int) env('NETO_API_RETRY_SLEEP', 250),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, every request and response is written to the configured
    | log channel at the "debug" level. The API key is always masked.
    |
    */

    'logging' => [
        'enabled' => (bool) env('NETO_API_LOGGING', false),
        'channel' => env('NETO_API_LOG_CHANNEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    |
    | Defaults used by the Orders resource. The output selectors control the
    | fields returned by GetOrder when none are given explicitly. The default
    | statuses are applied by Orders::get() when no OrderStatus filter is set.
    |
    */

    'orders' => [

        'default_statuses' => ['New', 'Pick', 'Pack', 'Quote'],

        'output_selectors' => [
            'OrderID',
            'OrderStatus',
            'OrderType',
            'Username',
            'Email',
            'CustomerFirstName',
            'CustomerLastName',
            'BillFirstName',
            'BillLastName',
            'BillAddress',
            'ShipAddress',
            'ShippingOption',
            'ShippingTrackingNumber',
            'ShippingSignature',
            'DeliveryInstruction',
            'CustomerRef1',
            'CustomerRef2',
            'SalesChannel',
            'GrandTotal',
            'ShippingTotal',
            'ShippingDiscount',
            'DatePlaced',
            'DateRequired',
            'DateInvoiced',
            'DatePaid',
            'OrderPayment',
            'OrderPayment.PaymentType',
            'OrderPayment.DatePaid',
            'OrderLine',
            'OrderLine.ProductName',
            'OrderLine.Quantity',
            'OrderLine.PickQuantity',
            'OrderLine.BackorderQuantity',
            'OrderLine.UnitPrice',
            'OrderLine.CostPrice',
            'OrderLine.PercentDiscount',
            'OrderLine.ProductDiscount',
            'OrderLine.WarehouseID',
            'OrderLine.ShippingMethod',
            'OrderLine.ShippingTracking',
        ],

    ],

];
