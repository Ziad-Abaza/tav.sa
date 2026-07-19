<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class PageTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')
                ->label(t('id'))
                ->sortable(),

            Column::make('title')
                ->label('Title')
                ->sortable()
                ->primary(),

            Column::make('slug')
                ->label('Slug')
                ->sortable(),

            Column::make('status')
                ->label(t('active'))
                ->sortable()
                ->component('ToggleCell'),

            Column::make('order')
                ->label('Order')
                ->sortable(),
        ];
    }
}
