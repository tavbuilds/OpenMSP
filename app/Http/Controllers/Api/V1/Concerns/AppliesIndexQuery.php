<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait AppliesIndexQuery
{
    /**
     * Case-insensitive LIKE search across local columns and optional relation columns.
     *
     * @param  list<string>  $columns
     * @param  array<string, list<string>>  $relations  relation => [columns]
     */
    protected function applySearch(Builder $query, Request $request, array $columns, array $relations = []): void
    {
        $search = $request->string('search')->trim()->toString();
        if ($search === '') {
            return;
        }

        $term = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $q) use ($term, $columns, $relations) {
            foreach ($columns as $i => $column) {
                $safe = $this->assertSafeColumn($column);
                $method = $i === 0 ? 'whereRaw' : 'orWhereRaw';
                $q->{$method}("LOWER(COALESCE({$safe}, '')) LIKE ?", [$term]);
            }

            foreach ($relations as $relation => $relColumns) {
                $q->orWhereHas($relation, function (Builder $rq) use ($term, $relColumns) {
                    $rq->where(function (Builder $inner) use ($term, $relColumns) {
                        foreach ($relColumns as $i => $column) {
                            $safe = $this->assertSafeColumn($column);
                            $method = $i === 0 ? 'whereRaw' : 'orWhereRaw';
                            $inner->{$method}("LOWER(COALESCE({$safe}, '')) LIKE ?", [$term]);
                        }
                    });
                });
            }
        });
    }

    protected function applyIntegerEquals(Builder $query, Request $request, string $param, ?string $column = null): void
    {
        $column ??= $param;
        if ($request->filled($param)) {
            $query->where($column, $request->integer($param));
        }
    }

    protected function applyStringEquals(Builder $query, Request $request, string $param, ?string $column = null): void
    {
        $column ??= $param;
        if ($request->filled($param)) {
            $query->where($column, $request->string($param)->toString());
        }
    }

    protected function applyBooleanFilter(Builder $query, Request $request, string $param, ?string $column = null): void
    {
        $column ??= $param;
        if (! $request->exists($param)) {
            return;
        }

        $raw = $request->input($param);
        if ($raw === null || $raw === '') {
            return;
        }

        $query->where($column, filter_var($raw, FILTER_VALIDATE_BOOLEAN));
    }

    protected function applyNumericRange(
        Builder $query,
        Request $request,
        string $column,
        string $minParam,
        string $maxParam,
    ): void {
        if ($request->filled($minParam)) {
            $query->where($column, '>=', $request->input($minParam));
        }

        if ($request->filled($maxParam)) {
            $query->where($column, '<=', $request->input($maxParam));
        }
    }

    protected function applyDateRange(
        Builder $query,
        Request $request,
        string $column,
        string $afterParam,
        string $beforeParam,
    ): void {
        if ($request->filled($afterParam)) {
            $query->whereDate($column, '>=', $request->input($afterParam));
        }

        if ($request->filled($beforeParam)) {
            $query->whereDate($column, '<=', $request->input($beforeParam));
        }
    }

    /**
     * @param  list<string>  $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed): void
    {
        $sort = $request->string('sort')->trim()->toString();
        if ($sort === '' || ! in_array($sort, $allowed, true)) {
            return;
        }

        $order = strtolower($request->string('order')->trim()->toString() ?: 'asc');
        if (! in_array($order, ['asc', 'desc'], true)) {
            $order = 'asc';
        }

        $query->reorder($sort, $order);
    }

    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        $perPage = $request->integer('per_page', $default);

        return max(1, min($perPage > 0 ? $perPage : $default, $max));
    }

    protected function assertSafeColumn(string $column): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException("Unsafe column name: {$column}");
        }

        return $column;
    }
}
