<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class TransactionTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [

            Column::make('id')
                ->label('ID')
                ->sortable(),

            Column::make('customer_name')
                ->label('Customer')
                ->sortable(),

            Column::make('type')
                ->label('Payment Gateway')
                ->sortable(),

            Column::make('status')
                ->label('Status')
                ->sortable()
                ->component('StatusBadgeCell'),

            Column::make('amount')
                ->label('Amount')
                ->sortable(),

            Column::make('amount_with_tax')
                ->label('Amount (With Tax)')
                ->sortable(false),

            Column::make('created_at')
                ->label('Created At')
                ->sortable()
                ->component('DateCell'),

            Column::make('view_details')
                ->label('View Details')
                ->sortable(false),
        ];
    }
}
