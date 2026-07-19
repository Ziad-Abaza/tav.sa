<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class TemplateBotsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('name')->label('Name'),
            Column::make('reply_type')->label('Reply Type'),
            Column::make('trigger_keyword')->label('Trigger Keyword'),
            Column::make('relation_type')->label('Relation Type')->component('BadgeCell'),
            Column::make('is_bot_active')->label('Active')->component('ToggleCell'),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
