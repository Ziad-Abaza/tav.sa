<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class AdminInvoiceTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('invoice_number')->label('Invoice'),
            Column::make('date')->label('date')->sortable(),
            Column::make('amount')->label('Amount'),
            Column::make('status')->label('Status')->component('StatusBadgeCell'),
        ];
    }
}
