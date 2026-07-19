<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class FbMessengerCampaignsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('name')->label(t('campaign_name'))->sortable(),
            Column::make('template_name')->label(t('template'))->sortable(false),
            Column::make('status')->label(t('status'))->component('StatusBadgeCell'),
            Column::make('total_count')->label(t('total'))->sortable(),
            Column::make('sent_count')->label(t('sent'))->sortable(false),
            Column::make('failed_count')->label(t('failed'))->sortable(false),
            Column::make('scheduled_send_time')->label(t('scheduled_at'))->component('DateCell')->sortable(),
            Column::make('created_at')->label(t('created_at'))->component('DateCell')->sortable(),
        ];
    }
}
