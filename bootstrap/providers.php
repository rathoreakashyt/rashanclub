<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\PaymentGatewayServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
    Modules\Stock\Providers\StockServiceProvider::class,
];
