<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class TaxTable extends BaseTable
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

            Column::make('name')
                ->label(t('name'))
                ->sortable()
                ->primary(),

            Column::make('rate')
                ->label('Rate (%)')
                ->sortable(),

            Column::make('description')
                ->label('Description')
                ->sortable()
                ->component('TextLimitCell')
                ->meta(['limit' => 50]),
        ];
    }
}
