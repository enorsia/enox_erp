<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickHouseService
{
    protected string $baseUrl;
    protected string $database;
    protected string $username;
    protected string $password;
    protected int $timeout;

    public function __construct()
    {
        $host = config('clickhouse.host', '127.0.0.1');
        $port = config('clickhouse.port', 8123);
        $this->baseUrl = "http://{$host}:{$port}";
        $this->database = config('clickhouse.database', 'enorsia_analytics');
        $this->username = config('clickhouse.username', 'default');
        $this->password = config('clickhouse.password', '');
        $this->timeout = config('clickhouse.timeout', 5);
    }

    /**
     * Execute a ClickHouse query via HTTP interface.
     */
    public function query(string $sql, string $format = 'JSON'): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withQueryParameters([
                    'database' => $this->database,
                    'user' => $this->username,
                    'password' => $this->password,
                ])
                ->withBody($sql . " FORMAT {$format}", 'text/plain')
                ->post($this->baseUrl);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('ClickHouse query error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'sql' => $sql,
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('ClickHouse connection error', [
                'message' => $e->getMessage(),
                'sql' => $sql,
            ]);
            return null;
        }
    }

    /**
     * Execute a statement (INSERT, CREATE, etc.) that doesn't return data.
     */
    public function statement(string $sql): bool
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withQueryParameters([
                    'database' => $this->database,
                    'user' => $this->username,
                    'password' => $this->password,
                ])
                ->withBody($sql, 'text/plain')
                ->post($this->baseUrl);

            if ($response->successful()) {
                return true;
            }

            Log::error('ClickHouse statement error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'sql' => $sql,
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('ClickHouse statement error', [
                'message' => $e->getMessage(),
                'sql' => $sql,
            ]);
            return false;
        }
    }


    /**
     * Escape a value for ClickHouse SQL.
     */
    protected function escape(string $value): string
    {
        return addslashes($value);
    }
}

