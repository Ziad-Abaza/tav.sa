<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class AdminLanguageTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'asc';

    public function columns(): array
    {
        return [
            Column::make('id')->label('#')->sortable()->width('60px')->class('text-center'),
            Column::make('name')->label('Name')->sortable()->primary(),
            Column::make('code')->label('Code')->sortable(),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
