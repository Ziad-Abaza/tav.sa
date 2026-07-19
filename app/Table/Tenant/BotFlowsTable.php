<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class BotFlowsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('name')
                ->label('Name')
                ->sortable()
                ->primary()
                ->component('LinkCell')
                ->meta(['urlPath' => 'bot-flows/edit', 'urlKey' => 'id']),
            Column::make('description')->label('Description'),
            Column::make('is_active')->label('Active')->sortable()->component('ToggleCell'),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
