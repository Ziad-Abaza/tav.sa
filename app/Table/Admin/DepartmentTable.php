<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class DepartmentTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')->label('SR.NO')->width('60px')->class('text-center')->sortable(),
            Column::make('name')
                ->label('Name')->sortable(),
            Column::make('description')->label('Description')->sortable(),
            Column::make('tickets_count')->label('Tickets')->sortable(),
            Column::make('assignees')->label('Assignees'),
            Column::make('status')->label('Active')->sortable()->component('ToggleCell'),
        ];
    }
}
