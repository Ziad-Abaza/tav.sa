<?php

namespace App\Http\Controllers\Tenant\Api\Table\languages;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantLanguageLineController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = ['key', 'value'];

    public function index(Request $request): JsonResponse
    {
        $languageCode = $request->get('languageCode');

        if (! $languageCode) {
            return response()->json([
                'message' => 'Language code is required',
            ], 422);
        }

        $translations = getLanguageJson($languageCode);

        $rows = collect($translations)->map(function ($value, $key) {
            return [
                'id' => (string) $key,
                'key' => (string) $key,
                'english' => getLangugeValue('en', $key),
                'value' => $value,
            ];
        });

        //  SEARCH
        if ($search = $request->get('q')) {
            $search = strtolower($search);

            $rows = $rows->filter(
                fn ($row) => str_contains(strtolower($row['key']), $search) ||
                    str_contains(strtolower($row['english']), $search) ||
                    str_contains(strtolower($row['value']), $search)
            );
        }

        // SORTING
        $sort = $request->get('sort', 'key'); // default sort
        $dir = $request->get('direction', 'asc');

        if (in_array($sort, $this->sortable, true)) {
            $rows = $dir === 'desc'
                ? $rows->sortByDesc($sort)
                : $rows->sortBy($sort);
        }

        $rows = $rows->values();

        // PAGINATION
        $perPage = min((int) $request->get('per_page', 25), 1000);
        $page = (int) $request->get('page', 1);

        $paginated = $rows->forPage($page, $perPage)->values();

        return response()->json([
            'data' => $paginated,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $rows->count(),
                'last_page' => (int) ceil($rows->count() / $perPage),
            ],
        ]);
    }

    /**
     * Export bot flows as CSV — same filters/page as the table view.
     */
    public function export(Request $request): StreamedResponse
    {
        $languageCode = $request->get('languageCode');

        if (! $languageCode) {
            abort(422, 'Language code missing');
        }

        $translations = getLanguageJson($languageCode);

        $rows = collect($translations)->map(function ($value, $key) {
            return [
                'english' => getLangugeValue('en', $key),
                'value' => $value,
            ];
        });

        // Optional search filter
        if ($search = $request->get('q')) {
            $search = strtolower($search);

            $rows = $rows->filter(
                fn ($row) => str_contains(strtolower($row['english']), $search) ||
                    str_contains(strtolower($row['value']), $search)
            );
        }

        // Optional sorting
        $sort = $request->get('sort', 'english');
        $dir = $request->get('direction', 'asc');

        if (in_array($sort, ['english', 'value'])) {
            $rows = $rows->sortBy($sort, SORT_NATURAL, $dir === 'desc');
        }

        $csv = Writer::createFromString('');

        $csv->insertOne([
            'English',
            strtoupper($languageCode),
        ]);

        foreach ($rows as $row) {
            $csv->insertOne([
                $row['english'],
                $row['value'],
            ]);
        }

        $filename = "translations-{$languageCode}-".now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    /**
     * PATCH /{tenant}/api/tenant-language-lines/{key}/field
     * Inline edit save
     */
    public function updateField(Request $request, string $subdomain, string $key): JsonResponse
    {
        $tenantId = tenant_id();
        $languageCode = $request->get('languageCode');
        $value = $request->get('value');

        if (! $languageCode) {
            return response()->json([
                'message' => 'Language code missing',
            ], 422);
        }

        $translations = getLanguageJson($languageCode);

        if (! array_key_exists($key, $translations)) {
            return response()->json([
                'message' => 'Invalid translation key',
            ], 404);
        }

        // Normalize spaces
        $normalize = fn ($val) => trim(str_replace("\u{A0}", ' ', $val ?? ''));

        if ($normalize($translations[$key]) === $normalize($value)) {
            return response()->json([
                'message' => 'No changes',
            ]);
        }

        // Sanitize
        $value = strip_tags($value);
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // Prevent JSON injection
        if (preg_match('/^[\[\{].*[\]\}]$/', trim($value))) {
            return response()->json([
                'message' => 'Translation cannot be JSON',
            ], 422);
        }

        $translations[$key] = $value;

        File::put(
            resource_path("lang/translations/tenant/{$tenantId}/tenant_{$languageCode}.json"),
            json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Clear translation cache
        $locale = Session::get('locale', config('app.locale'));
        Cache::forget("translations.{$tenantId}_tenant_{$locale}");

        return response()->json([
            'message' => 'Translation updated successfully',
            'row' => [

                'id' => (string) $key,
                'key' => (string) $key,
                'value' => $value,
                'english' => getLangugeValue('en', $key),
            ],
        ]);
    }
}
