<?php

namespace App\Support;

use Illuminate\Http\Request;

final class TrackerQueryParams
{
    /**
     * Convert flat bracket keys from JSON/export payloads into Laravel-style arrays.
     *
     * Example: ['funnel[0]' => 'payment_success'] -> ['funnel' => ['payment_success']]
     *
     * @param  array<string, mixed>  $queryParams
     * @return array<string, mixed>
     */
    public static function normalize(array $queryParams): array
    {
        $normalized = [];

        foreach ($queryParams as $key => $value) {
            if (! is_string($key)) {
                $normalized[$key] = $value;

                continue;
            }

            if (preg_match('/^([^\[]+)\[(\d*)\]$/', $key, $matches) === 1) {
                $baseKey = $matches[1];

                if (! isset($normalized[$baseKey]) || ! is_array($normalized[$baseKey])) {
                    $normalized[$baseKey] = [];
                }

                if ($matches[2] === '') {
                    $normalized[$baseKey][] = $value;
                } else {
                    $normalized[$baseKey][(int) $matches[2]] = $value;
                }

                continue;
            }

            if (str_ends_with($key, '[]')) {
                $baseKey = substr($key, 0, -2);

                if (! isset($normalized[$baseKey]) || ! is_array($normalized[$baseKey])) {
                    $normalized[$baseKey] = [];
                }

                if (is_array($value)) {
                    foreach ($value as $item) {
                        $normalized[$baseKey][] = $item;
                    }
                } else {
                    $normalized[$baseKey][] = $value;
                }

                continue;
            }

            if (! array_key_exists($key, $normalized)) {
                $normalized[$key] = $value;
            }
        }

        foreach ($normalized as $key => $value) {
            if (is_array($value)) {
                $normalized[$key] = array_values($value);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $queryParams
     */
    public static function request(array $queryParams): Request
    {
        return Request::create('/', 'GET', self::normalize($queryParams));
    }
}
