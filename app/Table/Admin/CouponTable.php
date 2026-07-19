<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class CouponTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            // Column::make('id')->label('Id'),
            Column::make('code')->label('Code')->sortable(),
            Column::make('name')->label('Name')->sortable(),
            Column::make('type')->label('Type')->sortable(),
            Column::make('value')->label('Value')->sortable(),
            Column::make('is_active')->component('StatusBadgeCell')->label('Status')->sortable(),
            Column::make('usage_count')->label('Usage')->sortable(),
            Column::make('expires_at')->label('Expires')->sortable(),
        ];
    }
}
