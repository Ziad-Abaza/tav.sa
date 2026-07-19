<?php

namespace App\Table\Tenant;

use App\Table\BaseTable;
use App\Table\Column;

class TenantLanguageLineTable extends BaseTable
{
    protected int $perPage = 25;

    protected string $defaultSort = 'key';

    protected string $defaultDirection = 'desc';

    public $value;

    public $languageCode;

    public $tenantId;

    public function columns(): array
    {

        $referer = request()->header('Referer');

        $languageCode = null;

        if ($referer) {

            $path = parse_url($referer, PHP_URL_PATH);
            $segments = explode('/', trim($path, '/'));

            $languageCode = $segments[2] ?? null;

            if ($languageCode) {

                $languageName = getLanguage($languageCode, ['name'])->name ?? 'value';
            }
        }

        return [
            Column::make('english')
                ->label('English'),

            Column::make('value')
                ->label($languageName ?? 'Translation'),

        ];
    }
}
