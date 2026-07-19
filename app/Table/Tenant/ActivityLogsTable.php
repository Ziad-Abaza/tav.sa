<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class ActivityLogsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('category')->label('Category')->sortable(),
            Column::make('name')->label('Name')->sortable(),
            Column::make('template_name')->label('Template Name')->sortable(),
            Column::make('response_code')->label('Response Code')->component('BadgeCell')->sortable(),
            Column::make('rel_type')->label('Relation type')->component('BadgeCell')->sortable(),
            Column::make('created_at')->label('Created')->component('DateCell')->sortable(),
        ];
    }
}
