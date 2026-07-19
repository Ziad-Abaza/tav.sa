<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class SourceTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')
                ->label('SR.NO'),

            Column::make('name')
                ->label('Name')
                ->sortable(),
        ];
    }
}
