<?php

namespace App\Http\Controllers\Admin\Api\Table\pages;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PageTableController extends Controller
{
    private array $sortable = ['id', 'title', 'slug', 'status', 'order', 'created_at'];

    private array $searchable = ['title', 'slug', 'order'];

    public function index(Request $request): JsonResponse
    {
        $query = Page::query();

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'slug' => $row->slug,
                'status' => (bool) $row->status,
                'order' => $row->order,
                'can_edit' => checkPermission(['admin.pages.edit']),
                'can_delete' => checkPermission(['admin.pages.delete']),
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function toggleStatus(string $id): JsonResponse
    {
        if (! checkPermission('admin.pages.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $page = Page::findOrFail($id);

        $page->status = ! $page->status;
        $page->save();

        Cache::forget('menu_items');

        return response()->json([
            'value' => (bool) $page->status,
            'message' => $page->status
                ? t('page_active')
                : t('page_deactive'),
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search) {
                foreach ($this->searchable as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy(
            in_array($sort, $this->sortable, true) ? $sort : 'created_at',
            $dir
        );
    }
}
