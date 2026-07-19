<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class CampaignsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('campaign_name')->label('Campaign Name')->sortable(),
            Column::make('template_name')->label('Template')->sortable(),
            Column::make('rel_type')->label('Relation type')->component('BadgeCell')->sortable(),
            Column::make('status')->label('Status')->component('StatusBadgeCell'),
            Column::make('sending_count')->label('Total')->sortable(),
            Column::make('delivered')->label('Delivered to')->sortable(),
            Column::make('read_by')->label('Read by')->sortable()->sortable(),
            Column::make('created_at')->label('Created at')->component('DateCell')->sortable(),
        ];
    }
}
