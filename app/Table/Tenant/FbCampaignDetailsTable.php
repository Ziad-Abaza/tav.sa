<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class FbCampaignDetailsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('contact_name')->label(t('contact'))->sortable(false),
            Column::make('facebook_psid')->label(t('psid'))->sortable(false),
            Column::make('status')->label(t('status'))->sortable(),
            Column::make('message_status')->label(t('message_status'))->sortable(),
            Column::make('response_message')->label(t('response'))->sortable(false),
            Column::make('created_at')->label(t('created_at'))->component('DateCell')->sortable(),
        ];
    }
}
