<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class TenantsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')->label('#')->sortable()->width('60px')->class('text-center'),
            Column::make('name')->label('Name')->sortable()->primary(),
            Column::make('email')->label('Email')->sortable(),
            Column::make('company_name')->label('Company')->sortable(),
            Column::make('status')->label('Status')->sortable()->component('BadgeCell'),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
