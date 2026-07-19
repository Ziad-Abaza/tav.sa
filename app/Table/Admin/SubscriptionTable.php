<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class SubscriptionTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')->label('#')->sortable()->width('60px')->class('text-center'),
            Column::make('company_name')->label('Name')->sortable()->primary(),
            Column::make('plan')->label('Plan')->sortable(),
            Column::make('status')->label('Status')->component('StatusBadgeCell'),
            Column::make('current_period_ends_at')->label('Current Period Ends At')->component('DateCell'),
        ];
    }
}
