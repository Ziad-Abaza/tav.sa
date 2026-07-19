<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class CannedReplyTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [

            Column::make('sr_no')
                ->label('SR.NO')
                ->width('60px')
                ->class('text-center'),

            Column::make('title')
                ->label('Title')
                ->sortable(),

            Column::make('description')
                ->label('Description')
                ->sortable(),

            Column::make('public')
                ->label('Public')
                ->sortable()
                ->component('ToggleCell'),

            Column::make('created_at')
                ->label('Created At')
                ->sortable()
                ->component('DateCell'),
        ];
    }
}
