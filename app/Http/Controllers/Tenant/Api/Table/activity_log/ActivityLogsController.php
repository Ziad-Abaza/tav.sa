<?php

namespace App\Http\Controllers\Tenant\Api\Table\activity_log;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WmActivityLog;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogsController extends Controller
{
    /**
     * Main data endpoint - returns paginated contact data
     */
    private array $sortable = [
        'category',
        'name',
        'template_name',
        'response_code',
        'rel_type',
        'created_at',
    ];

    private array $searchable = [
        'category',
        'name',
        'template_name',
        'response_code',
        'rel_type',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.activity_log.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();
        $activity_logs = WmActivityLog::query()
            ->leftJoin('template_bots', function ($join) {
                $join->on('wm_activity_logs.category_id', '=', 'template_bots.id')
                    ->where('wm_activity_logs.category', '=', 'template_bot');
            })
            ->leftJoin('message_bots', function ($join) {
                $join->on('wm_activity_logs.category_id', '=', 'message_bots.id')
                    ->where('wm_activity_logs.category', '=', 'message_bot');
            })
            ->leftJoin('campaigns', function ($join) {
                $join->on('wm_activity_logs.category_id', '=', 'campaigns.id')
                    ->where('wm_activity_logs.category', '=', 'campaign');
            })
            ->leftJoin('whatsapp_templates', 'template_bots.template_id', '=', 'whatsapp_templates.template_id')
            ->where('wm_activity_logs.tenant_id', tenant_id())
            ->select(
                'wm_activity_logs.*',
                DB::raw('(SELECT COUNT(*) FROM wm_activity_logs i2 WHERE i2.id <= wm_activity_logs.id AND i2.tenant_id = '.$tenantId.') as row_num'),
                DB::raw("COALESCE(template_bots.name, message_bots.name, campaigns.name, '-') as name"),
                DB::raw("
                COALESCE(
                    CASE
                        WHEN wm_activity_logs.category = 'template_bot'
                            AND wm_activity_logs.category_id = template_bots.id
                            THEN (SELECT template_name FROM whatsapp_templates WHERE whatsapp_templates.template_id = template_bots.template_id LIMIT 1)
                        WHEN wm_activity_logs.category = 'campaign'
                            AND wm_activity_logs.category_id = campaigns.id
                            THEN (SELECT template_name FROM whatsapp_templates WHERE whatsapp_templates.template_id = campaigns.template_id LIMIT 1)
                        WHEN wm_activity_logs.category = 'Initiate Chat'
                            THEN (
                            SELECT template_name
                            FROM whatsapp_templates
                             WHERE whatsapp_templates.template_id = JSON_UNQUOTE(JSON_EXTRACT(wm_activity_logs.category_params, '$.templateId'))
                             LIMIT 1)
                        ELSE '-'
                    END, '-') as template_name
                ")
            );

        $this->applyFilters($activity_logs, $request);
        $this->applySorting($activity_logs, $request);

        $perPage = min($request->integer('per_page', 25), 1000);

        $paginator = $activity_logs->latest()->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function ($field, $index) use ($paginator) {

            return [
                'id' => $field->id,
                'sr_no' => $paginator->firstItem() + $index,
                'category' => t($field->category),
                'name' => $field->name,
                'template_name' => $field->template_name,
                'response_code' => $field->response_code,
                'rel_type' => $field->rel_type,
                'created_at' => $field->created_at,
                'can_delete' => checkPermission(['tenant.activity_log.delete']),
                'can_view' => checkPermission(['tenant.activity_log.view']),
            ];
        });

        return response()->json([
            'data' => $items,
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

            $query->where(function (Builder $q) use ($search): void {

                // Direct columns from wm_activity_logs
                $q->orWhere('wm_activity_logs.category', 'like', "%{$search}%")
                    ->orWhere('wm_activity_logs.response_code', 'like', "%{$search}%")
                    ->orWhere('wm_activity_logs.rel_type', 'like', "%{$search}%");

                // Joined table columns
                $q->orWhere('template_bots.name', 'like', "%{$search}%")
                    ->orWhere('message_bots.name', 'like', "%{$search}%")
                    ->orWhere('campaigns.name', 'like', "%{$search}%");

                // Template name search
                $q->orWhere('whatsapp_templates.template_name', 'like', "%{$search}%");
            });
        }
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, $this->sortable, true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }
    }
}
