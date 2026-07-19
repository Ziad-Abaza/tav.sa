<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class RoleTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')->label(t('id'))->sortable(),
            Column::make('name')->label(t('name'))->sortable(),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
