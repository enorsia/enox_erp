<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Structured logging for the Ecom Tracker module.
 *
 * Two flows share one daily log channel (storage/logs/ecom-tracker/):
 * - frontend(): storefront API hits and full ingest pipeline
 * - backend(): ERP admin analytics and dashboard reads
 */
final class EcomTrackerLogger
{
    public static function isEnabled(): bool
    {
        return (bool) config('tracker.logging_enabled', false);
    }

    public static function channelName(): string
    {
        return (string) config('tracker.log_channel', 'ecom_tracker');
    }

    public static function frontend(): EcomTrackerLogWriter
    {
        return new EcomTrackerLogWriter('[EcomTracker Frontend]', 'frontend');
    }

    public static function backend(): EcomTrackerLogWriter
    {
        return new EcomTrackerLogWriter('[EcomTracker Backend]', 'backend');
    }
}

final class EcomTrackerLogWriter
{
    public function __construct(
        private string $prefix,
        private string $flow,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function debug(string $step, string $message, array $context = []): void
    {
        $this->write('debug', $step, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function info(string $step, string $message, array $context = []): void
    {
        $this->write('info', $step, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function warning(string $step, string $message, array $context = []): void
    {
        $this->write('warning', $step, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function error(string $step, string $message, array $context = []): void
    {
        $this->write('error', $step, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $level, string $step, string $message, array $context = []): void
    {
        if (! EcomTrackerLogger::isEnabled()) {
            return;
        }

        $context = array_merge([
            'step' => $step,
            'flow' => $this->flow,
            'module' => 'ecom_tracker',
        ], $context);

        Log::channel(EcomTrackerLogger::channelName())->{$level}($this->prefix.' '.$message, $context);
    }
}
