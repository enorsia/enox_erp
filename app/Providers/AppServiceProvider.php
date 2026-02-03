<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('price', function ($expression) {
            return '<?php
                $__value = ' . $expression . ';
                $__price = is_array($__value) ? ($__value["amount"] ?? 0) : ($__value ?? 0);
                $__symbol = is_array($__value) ? ($__value["symbol"] ?? true) : true;
                echo $__symbol ? "£" . number_format($__price, 2) : number_format($__price, 2);
            ?>';
        });

        // price without symble
        Blade::directive('pricews', function ($expression) {
            return '<?php
                $__value = ' . $expression . ';
                $__price = is_array($__value) ? ($__value["amount"] ?? 0) : ($__value ?? 0);
                $__symbol = is_array($__value) ? ($__value["symbol"] ?? true) : true;
                echo $__symbol ? number_format($__price, 2) : number_format($__price, 2);
            ?>';
        });
    }
}
