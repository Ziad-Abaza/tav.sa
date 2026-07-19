<?php

namespace App\Table;

abstract class BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    protected bool $searchable = true;

    protected bool $sticky = false;

    protected string $maxHeight = '600px';

    /**
     * Return an array of Column instances that define this table's structure.
     *
     * @return Column[]
     */
    abstract public function columns(): array;

    /**
     * Serialize table metadata for the /table/settings API endpoint.
     * The Vue ResourceDataTable component consumes this on mount.
     */
    public function settings(): array
    {
        return [
            'columns' => collect($this->columns())->map->toArray()->values()->all(),
            'per_page' => $this->perPage,
            'default_sort' => $this->defaultSort,
            'default_direction' => $this->defaultDirection,
            'searchable' => $this->searchable,
            'sticky' => $this->sticky,
            'max_height' => $this->maxHeight,
        ];
    }
}
