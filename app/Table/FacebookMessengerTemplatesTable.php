<?php

namespace App\Table;

class FacebookMessengerTemplatesTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('name')->label('Name')->sortable()->primary()
                ->component('LinkCell')
                ->meta(['urlPath' => 'facebook-messenger/templates', 'urlKey' => 'id', 'labelKeys' => ['name']]),
            Column::make('content_type')->label('Type')->sortable()->component('BadgeCell'),
            Column::make('message_preview')->label('Message Preview'),
            Column::make('is_active')->label('Active')->sortable()->component('ToggleCell'),
            Column::make('sending_count')->label('Sent')->sortable()->class('text-center'),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];
    }
}
