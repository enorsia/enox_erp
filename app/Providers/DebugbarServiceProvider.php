<?php

namespace App\Providers;

use App\Debugbar\FilteredDatabaseCollectorProvider;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Support\ServiceProvider;

class DebugbarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            if (! config('debugbar.hide_infra_queries', true)) {
                return;
            }

            if (! LaravelDebugbar::canBeEnabled()) {
                return;
            }

            $debugbar = $this->app->make(LaravelDebugbar::class);

            if ($debugbar->hasCollector('queries')) {
                return;
            }

            $this->app->call(FilteredDatabaseCollectorProvider::class, [
                'options' => config('debugbar.options.db', []),
            ]);
        });
    }
}
