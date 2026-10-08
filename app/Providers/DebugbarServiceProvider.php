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

            // Debugbar is always off in the console, but these listeners would still keep every
            // transaction event, so a long-running queue worker grows until it hits its memory limit.
            if (! LaravelDebugbar::canBeEnabled() || $this->app->runningInConsole()) {
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
