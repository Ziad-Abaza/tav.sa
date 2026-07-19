<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class CampaignExecutedTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'id';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')
                ->label('ID')
                ->sortable(),

            Column::make('contact_name')
                ->label('Name')
                ->sortable(),

            Column::make('phone')
                ->label('Phone')
                ->sortable(),

            Column::make('message')
                ->label('Message')
                ->sortable(),

            Column::make('message_status')
                ->label('Sent Status')
                ->sortable()
                ->component('StatusBadgeCell'),

            Column::make('response_message')
                ->label('Status Message')
                ->sortable(),
        ];
    }
}
