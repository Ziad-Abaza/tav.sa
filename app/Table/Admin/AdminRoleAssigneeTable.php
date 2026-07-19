<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class AdminRoleAssigneeTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    // protected bool $columns = false;

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),

            Column::make('name')
                ->label('name'),

        ];
    }
}
