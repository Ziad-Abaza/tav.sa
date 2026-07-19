<?php

namespace App\Http\Controllers\Tenant\Api\Table\canned_reply;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CannedReply;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CannedReplyController extends Controller
{
    private array $sortable = ['title', 'description', 'is_public', 'created_at'];

    private array $searchable = ['title', 'description'];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('tenant.canned_reply.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();

        $query = CannedReply::query()
            ->selectRaw('*, (SELECT COUNT(*) FROM canned_replies i2 WHERE i2.id <= canned_replies.id AND i2.tenant_id = ?) as row_num', [$tenantId])
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
                    'title' => $row->title,
                    'description' => $row->description,
                    'public' => (bool) $row->is_public,
                    'created_at' => $row->created_at?->toISOString(),
                    'can_edit' => checkPermission(['tenant.canned_reply.edit']),
                    'can_delete' => checkPermission(['tenant.canned_reply.delete']),

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

    /**
     * Toggle the is_public flag on a canned reply.
     */
    public function togglePublic(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.canned_reply.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $cannedReply = CannedReply::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $cannedReply->update([
            'is_public' => ! $cannedReply->is_public,
        ]);

        return response()->json([
            'value' => (bool) $cannedReply->is_public,
            'message' => $cannedReply->is_public
                ? t('canned_reply_activate')
                : t('canned_reply_deactivate'),
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
