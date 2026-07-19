<?php

namespace Modules\ApiWebhookManager\Services\V2;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class QueryBuilderService
{
    protected Builder $query;

    protected Request $request;

    protected array $allowedFilters = [];

    protected array $allowedSorts = [];

    protected array $allowedIncludes = [];

    protected array $searchableFields = [];

    public function __construct(Builder $query, Request $request)
    {
        $this->query = $query;
        $this->request = $request;
    }

    public static function for(Builder $query, Request $request): self
    {
        return new self($query, $request);
    }

    public function allowedFilters(array $filters): self
    {
        $this->allowedFilters = $filters;

        return $this;
    }

    public function allowedSorts(array $sorts): self
    {
        $this->allowedSorts = $sorts;

        return $this;
    }

    public function allowedIncludes(array $includes): self
    {
        $this->allowedIncludes = $includes;

        return $this;
    }

    public function searchable(array $fields): self
    {
        $this->searchableFields = $fields;

        return $this;
    }

    public function apply(): Builder
    {
        $this->applyFilters();
        $this->applySearch();
        $this->applySorts();
        $this->applyIncludes();
        $this->applyFields();

        return $this->query;
    }

    protected function applyFilters(): void
    {
        $filters = $this->request->input('filter', []);

        if (empty($filters) || empty($this->allowedFilters)) {
            return;
        }

        foreach ($filters as $field => $value) {
            if (! in_array($field, $this->allowedFilters)) {
                continue;
            }

            if (is_array($value)) {
                $this->applyComplexFilter($field, $value);
            } else {
                $this->query->where($field, $value);
            }
        }
    }

    protected function applyComplexFilter(string $field, array $conditions): void
    {
        foreach ($conditions as $operator => $value) {
            match ($operator) {
                'gte', '>=' => $this->query->where($field, '>=', $value),
                'lte', '<=' => $this->query->where($field, '<=', $value),
                'gt', '>' => $this->query->where($field, '>', $value),
                'lt', '<' => $this->query->where($field, '<', $value),
                'ne', '!=' => $this->query->where($field, '!=', $value),
                'like' => $this->query->where($field, 'like', "%{$value}%"),
                'in' => $this->query->whereIn($field, is_array($value) ? $value : explode(',', $value)),
                'not_in' => $this->query->whereNotIn($field, is_array($value) ? $value : explode(',', $value)),
                'between' => $this->query->whereBetween($field, is_array($value) ? $value : explode(',', $value)),
                'null' => $value ? $this->query->whereNull($field) : $this->query->whereNotNull($field),
                default => $this->query->where($field, $value),
            };
        }
    }

    protected function applySearch(): void
    {
        $search = $this->request->input('search') ?? $this->request->input('q');

        if (! $search || empty($this->searchableFields)) {
            return;
        }

        $this->query->where(function ($query) use ($search) {
            foreach ($this->searchableFields as $field) {
                $query->orWhere($field, 'like', "%{$search}%");
            }
        });
    }

    protected function applySorts(): void
    {
        $sort = $this->request->input('sort');

        if (! $sort || empty($this->allowedSorts)) {
            return;
        }

        $sorts = is_array($sort) ? $sort : explode(',', $sort);

        foreach ($sorts as $sortField) {
            $direction = 'asc';

            if (str_starts_with($sortField, '-')) {
                $direction = 'desc';
                $sortField = substr($sortField, 1);
            }

            if (in_array($sortField, $this->allowedSorts)) {
                $this->query->orderBy($sortField, $direction);
            }
        }
    }

    protected function applyIncludes(): void
    {
        $includes = $this->request->input('include');

        if (! $includes || empty($this->allowedIncludes)) {
            return;
        }

        $includes = is_array($includes) ? $includes : explode(',', $includes);

        foreach ($includes as $include) {
            if (in_array($include, $this->allowedIncludes)) {
                $this->query->with($include);
            }
        }
    }

    protected function applyFields(): void
    {
        $fields = $this->request->input('fields');

        if (! $fields) {
            return;
        }

        $fields = is_array($fields) ? $fields : explode(',', $fields);

        if (! empty($fields)) {
            // Always include critical fields for proper functioning
            // These fields are required by API resources and models
            $requiredFields = [
                'id',
                'tenant_id',
                'created_at',
                'updated_at',
                'type',
                'status_id',
                'source_id',
                'email',
                'phone',
                'description',
                'city',
                'state',
                'zip',
                'address',
                'country_id',
                'assigned_id',
                'group_id',
                'is_opted_out',
                'firstname',
                'lastname',
                'company',
            ];

            foreach ($requiredFields as $required) {
                if (! in_array($required, $fields)) {
                    $fields[] = $required;
                }
            }

            $this->query->select($fields);
        }
    }

    public function paginate(?int $perPage = null): mixed
    {
        $perPage = $perPage ?? $this->request->input('per_page', 15);
        $perPage = min($perPage, 100);

        return $this->query->paginate($perPage);
    }

    public function get(): mixed
    {
        return $this->query->get();
    }
}
