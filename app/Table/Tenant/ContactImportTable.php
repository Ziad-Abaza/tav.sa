<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class ContactImportTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('status')->label('Status')->component('BadgeCell'),
            Column::make('file')->label('File'),
            Column::make('progress')->label('Progress'),
            Column::make('total_records')->label('Records')->component('BadgeCell'),
            Column::make('created_at')->label('Created at')->component('DateCell'),
        ];
    }
}
