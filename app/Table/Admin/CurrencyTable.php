<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class CurrencyTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('id')->label('SR.NO')->width('60px')->class('text-center')->sortable(),
            Column::make('name')
                ->label('Name')->sortable(),
            Column::make('symbol')->label('symbol')->sortable(),
            Column::make('is_default')->label('Base Currency')->sortable()->component('ToggleCell'),
        ];
    }
}
