<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ENORSIA shop database (read replica)
    |--------------------------------------------------------------------------
    |
    | Points at the enorsia_v2 MySQL replica on Enox Suite. Use for reporting
    | modules that query shop data directly (orders, products, departments).
    | Do not run migrations or writes on this connection.
    */
    'database_connection' => env('ENORSIA_DB_CONNECTION', 'enorsia'),

];