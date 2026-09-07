<?php

namespace App\Support;

use Illuminate\Http\Request;

final class TrackerMultiSelectFilter
{
    /**
     * @return list<string>
     */
    public static function values(mixed $input): array
    {
        if ($input === null || $input === '') {
            return [];
        }

        $values = is_array($input) ? $input : [$input];

        return array_values(array_unique(array_filter(array_map(
            static fn ($value) => trim((string) $value),
            $values,
        ), static fn (string $value) => $value !== '')));
    }

    /**
     * @return list<string>
     */
    public static function requestValues(Request $request, string $key): array
    {
        return self::values($request->input($key));
    }

    public static function requestFilled(Request $request, string $key): bool
    {
        return self::requestValues($request, $key) !== [];
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public static function allowedValues(mixed $input, array $allowed): array
    {
        if ($allowed === []) {
            return self::values($input);
        }

        $allowedLookup = array_fill_keys($allowed, true);

        return array_values(array_filter(
            self::values($input),
            static fn (string $value) => isset($allowedLookup[$value]),
        ));
    }

    /**
     * @param  list<string>  $current
     * @return array<string, list<string>|null>
     */
    public static function queryWithoutValue(string $key, array $current, string $remove): array
    {
        $remaining = array_values(array_filter(
            $current,
            static fn (string $value) => $value !== $remove,
        ));

        return [$key => $remaining === [] ? null : $remaining];
    }
}
