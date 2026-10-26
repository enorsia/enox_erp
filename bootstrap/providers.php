<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

if (class_exists(\Fruitcake\LaravelDebugbar\LaravelDebugbar::class)) {
    $providers[] = App\Providers\DebugbarServiceProvider::class;
}

return $providers;
