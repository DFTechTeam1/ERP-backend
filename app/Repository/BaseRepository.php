<?php

namespace App\Repository;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic data-access layer. Per the backend convention, ALL database
 * communication lives in repositories; services pass the selection columns,
 * where conditions, eager-loads and ordering in as parameters and the
 * repository just runs the query.
 *
 * Supported `$params` keys:
 *   - with:       array<int,string>            relations to eager-load
 *   - where:      array<string,mixed>          equality constraints (column => value)
 *   - orderBy:    array<string,string>         column => 'asc'|'desc'
 *   - orderByRaw: string                        raw ORDER BY expression
 *   - scope:      Closure(Builder): void        arbitrary extra constraints
 */
abstract class BaseRepository
{
    public function __construct(protected Model $model) {}

    /**
     * Fresh query builder for the underlying model.
     */
    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Translate caller-supplied parameters into query constraints.
     *
     * @param  array<string,mixed>  $params
     */
    protected function applyParams(Builder $query, array $params): Builder
    {
        foreach ($params['with'] ?? [] as $relation) {
            $query->with($relation);
        }

        foreach ($params['where'] ?? [] as $column => $value) {
            $query->where($column, $value);
        }

        foreach ($params['orderBy'] ?? [] as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        if (isset($params['orderByRaw'])) {
            $query->orderByRaw($params['orderByRaw']);
        }

        if (($params['scope'] ?? null) instanceof Closure) {
            ($params['scope'])($query);
        }

        return $query;
    }

    /**
     * Fetch all matching records.
     *
     * @param  array<string,mixed>  $params
     */
    public function get(array $params = []): Collection
    {
        return $this->applyParams($this->query(), $params)->get();
    }

    /**
     * Fetch a paginated slice of matching records.
     *
     * @param  array<string,mixed>  $params
     */
    public function paginate(array $params = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyParams($this->query(), $params)->paginate($perPage);
    }

    /**
     * Fetch the first matching record, or null.
     *
     * @param  array<string,mixed>  $params
     */
    public function show(array $params = []): ?Model
    {
        return $this->applyParams($this->query(), $params)->first();
    }

    /**
     * Persist a new record.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function store(array $attributes): Model
    {
        return $this->query()->create($attributes);
    }

    /**
     * Fill and persist an existing model.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes)->save();

        return $model;
    }

    /**
     * Persist pending changes on a model instance.
     */
    public function save(Model $model): Model
    {
        $model->save();

        return $model;
    }

    /**
     * Delete a model instance.
     */
    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Find a matching record or return a fresh (unsaved) instance.
     *
     * @param  array<string,mixed>  $attributes
     */
    public function firstOrNew(array $attributes): Model
    {
        return $this->query()->firstOrNew($attributes);
    }
}
