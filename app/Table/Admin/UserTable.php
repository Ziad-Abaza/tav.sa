<?php

namespace App\Table\Admin;

use App\Table\BaseTable;
use App\Table\Column;

class UserTable extends BaseTable
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

            Column::make('firstname')
                ->label(t('name'))
                ->sortable(),

            Column::make('phone')
                ->label(t('phone'))
                ->sortable(),

            Column::make('email')
                ->label(t('email'))
                ->sortable(),

            Column::make('active')
                ->label(t('active')),

            Column::make('created_at')
                ->label(t('created_at'))
                ->sortable()
                ->component('DateCell'),
        ];
    }
}
