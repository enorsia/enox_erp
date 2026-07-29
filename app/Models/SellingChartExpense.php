<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SellingChartExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'year',
        'conversion_rate',
        'commercial_expense',
        'enorsia_expense_bd',
        'enorsia_expense_uk',
        'shipping_cost',
        'status',
    ];

    public static function activeOrdered(): Collection
    {
        return static::query()
            ->where('status', 1)
            ->orderByDesc('year')
            ->get();
    }

    public static function seasonYearLastDigit(?string $seasonName): ?int
    {
        if (!$seasonName || strlen($seasonName) < 2) {
            return null;
        }

        return ((int) substr($seasonName, -2)) % 10;
    }

    public static function expenseYearLastDigit(self $expense): int
    {
        $year = $expense->year;

        if ($year instanceof \DateTimeInterface) {
            return ((int) $year->format('Y')) % 10;
        }

        return ((int) $year) % 10;
    }

    public static function matchForSeason(?string $seasonName, ?Collection $expenses = null): ?self
    {
        $expenses = $expenses ?? static::activeOrdered();
        $lastDigit = static::seasonYearLastDigit($seasonName);

        if ($lastDigit === null) {
            return $expenses->first();
        }

        return $expenses->first(
            fn (self $expense) => static::expenseYearLastDigit($expense) === $lastDigit
        ) ?? $expenses->first();
    }

    public static function configForSeason(?string $seasonName, ?Collection $expenses = null): array
    {
        $expense = static::matchForSeason($seasonName, $expenses);

        return [
            'conversion_rate' => (float) ($expense?->conversion_rate ?? 0),
            'shipping_cost' => (float) ($expense?->shipping_cost ?? 0),
            'year' => $expense?->year,
        ];
    }

    /** @param  array<int, string|null>  $seasonNames */
    public static function configMapForSeasons(array $seasonNames): array
    {
        $expenses = static::activeOrdered();
        $map = [];

        foreach (array_unique(array_filter($seasonNames)) as $seasonName) {
            $map[$seasonName] = static::configForSeason($seasonName, $expenses);
        }

        return $map;
    }
}
