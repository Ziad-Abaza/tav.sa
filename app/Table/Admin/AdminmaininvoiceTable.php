<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class AdminmaininvoiceTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [

            Column::make('invoice_number')
                ->label('Invoice #')
                ->sortable(),

            Column::make('tenant')
                ->label('Tenant')
                ->sortable(),

            Column::make('status')
                ->label('Status')
                ->sortable(),

            Column::make('amount')
                ->label('Amount')
                ->sortable(),

            Column::make('total_with_tax')
                ->label('Total (With Tax)')
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
