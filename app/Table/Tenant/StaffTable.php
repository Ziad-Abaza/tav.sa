<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class StaffTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')->label(t('id'))->sortable(),
            Column::make('name')->label(t('name'))->sortable(),
            Column::make('phone')->label('Phone')->sortable(),
            Column::make('email')->label('Email')->sortable(),
            Column::make('active')->label('Active')->sortable()->component('ToggleCell'),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
