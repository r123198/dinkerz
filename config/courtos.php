<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Booking Payment Provider
    |--------------------------------------------------------------------------
    |
    | The payment provider used for court booking checkouts. Booking logic
    | only ever talks to the payment abstraction layer, never to a provider
    | directly. Supported: "fake", "paymongo".
    |
    */

    'payment_provider' => env('COURTOS_PAYMENT_PROVIDER', 'fake'),

];
