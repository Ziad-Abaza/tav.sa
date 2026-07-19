<?php

namespace App\Table\Tenant;

use App\Models\Tenant\CustomField;
use App\Table\BaseTable;
use App\Table\Column;

class ContactsTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'created_at';

    protected string $defaultDirection = 'desc';

    protected ?array $customFields = null;

    public function columns(): array
    {
        $columns = [
            Column::make('sr_no')->label('#')->width('60px')->class('text-center'),
            Column::make('name')->label('Name')->sortable()->primary()
                ->component('LinkCell')
                ->meta([
                    'urlPath' => 'contacts/contact',
                    'urlKey' => 'id',
                    'labelKeys' => ['firstname', 'lastname'],
                ]),
            Column::make('type')->label('Type')->sortable()->component('BadgeCell'),
            Column::make('phone')->label('Phone')->sortable(),
            Column::make('status')->label('Status')->component('StatusBadgeCell'),
            Column::make('source')->label('Source'),
            Column::make('groups')->label('Group'),
            Column::make('user')->label('Assigned')->component('AvatarCell'),
            Column::make('initiate_chat')->label('Initiate Chat')->class('text-center'),
            Column::make('is_opted_out')->label('Opted Out')->sortable()->component('ToggleCell'),
            Column::make('is_enabled')->label('Active')->sortable()->component('ToggleCell'),
            Column::make('created_at')->label('Created')->sortable()->component('DateCell'),
        ];

        foreach ($this->getCustomFields() as $field) {

            $class = match ($field['field_type']) {
                'number' => 'text-right',
                'date' => 'whitespace-nowrap',
                default => ''
            };

            $columns[] = Column::make('custom_field_'.$field['field_name'])
                ->label($field['field_label'])
                ->class($class);
        }

        return $columns;
    }

    protected function getCustomFields(): array
    {
        return CustomField::getCustomFields();
    }
}
