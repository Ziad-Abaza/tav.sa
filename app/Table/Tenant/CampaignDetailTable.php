<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class CampaignDetailTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')
                ->sortable()
                ->label(t('ids')),

            Column::make('contact_name')
                ->sortable()
                ->label(t('name')),

            Column::make('phone')
                ->sortable()
                ->label(t('phone')),

            Column::make('body_message')
                ->label(t('message'))
                ->component('WrapTextCell'),

            Column::make('message_status')
                ->label(t('sent_status'))
                ->component('StatusBadgeCell'),
        ];
    }
}
