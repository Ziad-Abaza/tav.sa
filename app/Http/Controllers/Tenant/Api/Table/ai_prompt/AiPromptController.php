<?php

namespace App\Http\Controllers\Tenant\Api\Table\ai_prompt;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AiPrompt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPromptController extends Controller
{
    private array $sortable = ['name', 'prompt_action'];

    private array $searchable = ['name', 'action'];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.activity_log.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();
        $query = AiPrompt::query()
            ->selectRaw('ai_prompts.*, (SELECT COUNT(*) FROM ai_prompts i2 WHERE i2.id <= ai_prompts.id AND i2.tenant_id = ?) as row_num', [$tenantId])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->values()->map(
                fn ($row, $index) => [
                    'id' => $row->id,
                    'sr_no' => $from + $index,
                    'name' => $row->name,
                    'prompt_action' => $row->action,
                    'can_edit' => checkPermission('tenant.ai_prompt.edit'),
                    'can_delete' => checkPermission('tenant.ai_prompt.delete'),
                ]
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
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
