<?php

namespace Modules\Tickets\Table;

use App\Table\BaseTable;
use App\Table\Column;

class TenantTicketTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('ticket_id')
                ->label('Id')
                ->primary(),

            Column::make('subject')
                ->label(t('subject')),

            Column::make('department_name')
                ->label(t('department'))
                ->meta([
                    'variant' => 'gray',
                ]),

            Column::make('priority')
                ->label(t('priority'))
                ->component('StatusBadgeCell')
                ->meta([
                    'type' => 'priority',
                ]),

            Column::make('status')
                ->label(t('status'))
                ->component('StatusBadgeCell')
                ->meta([
                    'type' => 'status',
                ]),

            Column::make('created_at')
                ->label(t('created_at'))
                ->component('DateCell')
                ->meta([
                    'since' => true,
                    'tooltip' => true,
                ]),
        ];
    }
}
