<?php

namespace App\Http\Controllers\Admin\Api\Table\languages;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminLanguageLineController extends Controller
{
    private array $sortable = ['key', 'english', 'value'];

    private ?array $englishData = null;

    /*
    |--------------------------------------------------------------------------
    | INDEX (Matches PowerGrid datasource())
    |--------------------------------------------------------------------------
    */
    public function index(Request $request): JsonResponse
    {
        $languageCode = $request->get('languageCode');

        if (! $languageCode) {
            return response()->json(['message' => 'Language code is required'], 422);
        }
        $pageUrl = $request->header('Referer');

        $isTenantMode = str_contains($pageUrl, '/tenant-languages/');

        $englishData = $this->getEnglishData($isTenantMode);

        $filePath = $this->getLanguageFilePath($languageCode, $isTenantMode);

        $languageData = File::exists($filePath)
            ? json_decode(File::get($filePath), true) ?? []
            : [];

        $rows = collect($englishData)->map(function ($englishValue, $key) use ($languageData) {
            return [
                'id' => (string) $key,
                'key' => (string) $key,
                'english' => $englishValue,
                'value' => $languageData[$key] ?? '',
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | SEARCH (Same as PowerGrid searchable())
        |--------------------------------------------------------------------------
        */
        if ($search = $request->get('q')) {
            $search = strtolower($search);

            $rows = $rows->filter(fn ($row) => str_contains(strtolower($row['key']), $search) ||
                str_contains(strtolower($row['english']), $search) ||
                str_contains(strtolower($row['value']), $search)
            );
        }

        /*
        |--------------------------------------------------------------------------
        | SORTING
        |--------------------------------------------------------------------------
        */
        $sort = $request->get('sort', 'key');
        $dir = $request->get('direction', 'asc');

        if (in_array($sort, $this->sortable, true)) {
            $rows = $dir === 'desc'
                ? $rows->sortByDesc($sort)
                : $rows->sortBy($sort);
        }

        $rows = $rows->values();

        /*
        |--------------------------------------------------------------------------
        | PAGINATION
        |--------------------------------------------------------------------------
        */
        $perPage = min((int) $request->get('per_page', 25), 1000);
        $page = max((int) $request->get('page', 1), 1);

        return response()->json([
            'data' => $rows->forPage($page, $perPage)->values(),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $rows->count(),
                'last_page' => (int) ceil($rows->count() / $perPage),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | INLINE UPDATE (Matches onUpdatedEditable())
    |--------------------------------------------------------------------------
    */
    public function updateField(Request $request, string $key): JsonResponse
    {
        $languageCode = $request->get('languageCode');

        if (! $languageCode) {
            return response()->json(['message' => 'Language code missing'], 422);
        }

        $pageUrl = $request->header('Referer');

        $isTenantMode = str_contains($pageUrl, '/tenant-languages/');

        $filePath = $this->getLanguageFilePath($languageCode, $isTenantMode);

        $languageData = File::exists($filePath)
            ? json_decode(File::get($filePath), true) ?? []
            : [];

        $currentValue = $languageData[$key] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Normalize (same as PowerGrid)
        |--------------------------------------------------------------------------
        */
        $normalize = fn ($val) => trim(str_replace("\u{A0}", ' ', $val ?? ''));

        $normalizedCurrent = $normalize($currentValue);
        $value = $normalize($request->get('value'));

        if ($normalizedCurrent === $value) {
            return response()->json(['message' => 'No changes']);
        }

        /*
        |--------------------------------------------------------------------------
        | Sanitize (same as PowerGrid)
        |--------------------------------------------------------------------------
        */
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        /*
        |--------------------------------------------------------------------------
        | Prevent JSON injection
        |--------------------------------------------------------------------------
        */
        if (preg_match('/^[\[\{].*[\]\}]$/', trim($value))) {
            return response()->json([
                'message' => 'The translation cannot be a JSON object or array',
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Update value (same behavior as PowerGrid)
        |--------------------------------------------------------------------------
        */
        $languageData[$key] = e($value);

        File::put(
            $filePath,
            json_encode($languageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        /*
        |--------------------------------------------------------------------------
        | Clear cache (same logic as component)
        |--------------------------------------------------------------------------
        */
        $locale = Session::get('locale', config('app.locale'));

        if ($isTenantMode && function_exists('tenant_id') && tenant_id()) {
            Cache::forget('translations.'.tenant_id()."_tenant_{$locale}");
        } else {
            Cache::forget("translations.{$locale}");
        }

        return response()->json([
            'message' => 'Translation updated successfully',
            'row' => [
                'id' => (string) $key,
                'key' => (string) $key,
                'english' => $this->getEnglishValue($key, $isTenantMode),
                'value' => e($value),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | EXPORT (Matches datasource logic)
    |--------------------------------------------------------------------------
    */
    public function export(Request $request): StreamedResponse
    {
        $languageCode = $request->get('languageCode');

        if (! $languageCode) {
            abort(422, 'Language code missing');
        }

        $isTenantMode = str_contains($request->url(), '/tenant-languages/');

        $englishData = $this->getEnglishData($isTenantMode);
        $filePath = $this->getLanguageFilePath($languageCode, $isTenantMode);

        $languageData = File::exists($filePath)
            ? json_decode(File::get($filePath), true) ?? []
            : [];

        $csv = Writer::createFromString('');
        $csv->setOutputBOM(Writer::BOM_UTF8);

        $csv->insertOne(['English', strtoupper($languageCode)]);

        foreach ($englishData as $key => $englishValue) {
            $csv->insertOne([
                $englishValue,
                $languageData[$key] ?? '',
            ]);
        }

        $filename = "translations-{$languageCode}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers (Exact same logic as component)
    |--------------------------------------------------------------------------
    */
    private function getEnglishData(bool $isTenantMode): array
    {
        if ($this->englishData === null) {
            $file = $isTenantMode
                ? resource_path('lang/tenant_en.json')
                : resource_path('lang/en.json');

            $this->englishData = File::exists($file)
                ? json_decode(File::get($file), true) ?? []
                : [];
        }

        return $this->englishData;
    }

    private function getEnglishValue(string $key, bool $isTenantMode): string
    {
        $english = $this->getEnglishData($isTenantMode);

        return $english[$key] ?? '';
    }

    private function getLanguageFilePath(string $code, bool $isTenantMode): string
    {
        if ($code === 'en') {
            return $isTenantMode
                ? resource_path('lang/tenant_en.json')
                : resource_path('lang/en.json');
        }

        return $isTenantMode
            ? public_path("lang/tenant_{$code}.json")
            : resource_path("lang/translations/{$code}.json");
    }
}
