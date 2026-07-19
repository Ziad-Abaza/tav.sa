<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class MessageBotTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('SR.NO')->width('60px')->class('text-center')->sortable(),
            Column::make('name')->label('Name')->sortable(),
            Column::make('reply_type')->label('Type')->sortable(),
            Column::make('trigger')->label('Trigger Keyword')->sortable(),
            Column::make('rel_type')->label('Relation Type')->component('BadgeCell')->sortable(),
            Column::make('is_bot_active')->label('Active')->sortable()->component('ToggleCell'),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
