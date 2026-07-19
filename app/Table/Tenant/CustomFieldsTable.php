<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class CustomFieldsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    public function columns(): array
    {
        return [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('custom_field_name')->label('Custom Field Name'),
            Column::make('field_name')->label('Field Name')->sortable(),
            Column::make('is_active')->label('Active')->component('ToggleCell'),
            Column::make('custom_field_type')->label('Custom Field Type'),
            Column::make('is_required')->label('Required')->component('ToggleCell'),
            Column::make('show_on_table')->label('Show on Table')->component('ToggleCell'),
            Column::make('created_at')->label('Created')->sortable(),
        ];
    }
}
