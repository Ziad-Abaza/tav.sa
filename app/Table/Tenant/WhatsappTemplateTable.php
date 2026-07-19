<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class WhatsappTemplateTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('row_num')
                ->label('SR.NO')
                ->width('60px')
                ->sortable()
                ->class('text-center'),

            Column::make('template_name')
                ->label('Template Name')
                ->sortable()
                ->primary(),

            Column::make('language')
                ->label('Language')
                ->sortable(),

            Column::make('category')
                ->label('Category')
                ->sortable()
                ->component('BadgeCell'),

            Column::make('header_data_format')
                ->label('Template Type')
                ->sortable(),

            Column::make('status')
                ->label('Status')
                ->sortable()
                ->component('BadgeCell'),

            Column::make('template_type')
                ->label('Type')
                ->sortable(),

            Column::make('body_data')
                ->label('Body Data')
                ->sortable(),

        ];
    }
}
