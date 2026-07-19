<?php

namespace App\Table\Admin\languages;

use App\Table\BaseTable;
use App\Table\Column;

class AdminLanguageLineTable extends BaseTable
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

        $languageName = 'Translation';
        $languageCode = null;

        if ($referer) {

            $path = parse_url($referer, PHP_URL_PATH);
            $segments = explode('/', trim($path, '/'));

            $languageCode = $segments[2] ?? null;

            if ($languageCode) {
                $languageName = getLanguage($languageCode, ['name'])->name ?? 'Translation';
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
