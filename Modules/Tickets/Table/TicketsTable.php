<?php

namespace Modules\Tickets\Table;

use App\Table\BaseTable;
use App\Table\Column;

class TicketsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('ticket_id')->label('Id')->width('60px')->class('text-center'),
            Column::make('subject')->label('Subject'),
            Column::make('tenant')->label('Tenant'),
            Column::make('priority')->label('Priority')->component('StatusBadgeCell'),
            Column::make('status')->label('Status')->component('StatusBadgeCell'),
            Column::make('created_at')->label('Created At')->sortable()->component('DateCell'),
        ];
    }
}
