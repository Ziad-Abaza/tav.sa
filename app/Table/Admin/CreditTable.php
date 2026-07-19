<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class CreditTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')
                ->label(t('id'))
                ->sortable(),

            Column::make('customer')
                ->label('Customer')
                ->sortable(),

            Column::make('balance')
                ->label('Balance')
                ->sortable(),

            Column::make('updated_at')
                ->label('Updated at')
                ->sortable()
                ->component('DateCell'),

            Column::make('view_details')
                ->label('View Details')
                ->sortable(false),
        ];
    }
}
