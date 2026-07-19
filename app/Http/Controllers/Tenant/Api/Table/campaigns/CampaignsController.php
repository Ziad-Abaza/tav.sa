<?php

namespace App\Http\Controllers\Tenant\Api\Table\campaigns;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Campaign;
use App\Models\Tenant\Contact;
use App\Models\Tenant\TemplateBot;
use App\Models\Tenant\WhatsappTemplate;
use Auth;
use DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignsController extends Controller
{
    /**
     * Main data endpoint - returns paginated contact data
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.campaigns.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();

        $campaigns = Campaign::query()
            ->select([
                'campaigns.*',
                'whatsapp_templates.template_name',

                DB::raw("(SELECT COUNT(*) FROM campaign_details
                WHERE campaign_details.campaign_id = campaigns.id
                AND campaign_details.tenant_id = {$tenantId}
                AND (message_status = 'delivered' OR message_status = 'read')
            ) as delivered"),

                DB::raw("(SELECT COUNT(*) FROM campaign_details
                WHERE campaign_details.campaign_id = campaigns.id
                AND campaign_details.tenant_id = {$tenantId}
                AND message_status = 'read'
            ) as read_by"),

                DB::raw('ROW_NUMBER() OVER (ORDER BY campaigns.created_at DESC) as row_num'),

                DB::raw("(SELECT COUNT(*) FROM campaign_details
                WHERE campaign_details.campaign_id = campaigns.id
                AND campaign_details.tenant_id = {$tenantId}
            ) as total_details"),

                DB::raw("(SELECT COUNT(*) FROM campaign_details
                WHERE campaign_details.campaign_id = campaigns.id
                AND campaign_details.tenant_id = {$tenantId}
                AND status = 1
            ) as pending_count"),

                DB::raw("(SELECT COUNT(*) FROM campaign_details
                WHERE campaign_details.campaign_id = campaigns.id
                AND campaign_details.tenant_id = {$tenantId}
                AND status = 1 AND message_status != 'sent'
            ) as in_queue_count"),

                DB::raw("(SELECT COUNT(*) FROM campaign_details
                WHERE campaign_details.campaign_id = campaigns.id
                AND campaign_details.tenant_id = {$tenantId}
                AND message_status = 'sent'
            ) as executed_count"),

                DB::raw("(SELECT COUNT(*) FROM campaign_details
                WHERE campaign_details.campaign_id = campaigns.id
                AND campaign_details.tenant_id = {$tenantId}
                AND message_status = 'failed'
            ) as failed_count"),
            ])
            ->leftJoin('whatsapp_templates', function ($join) use ($tenantId) {
                $join->on('campaigns.template_id', '=', 'whatsapp_templates.template_id')
                    ->where('whatsapp_templates.tenant_id', '=', $tenantId);
            })
            ->where('campaigns.tenant_id', $tenantId);

        $perPage = min($request->integer('per_page', 25), 1000);

        $this->applyFilters($campaigns, $request);
        $this->applySorting($campaigns, $request);

        $paginator = $campaigns->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        if (! checkPermission('tenant.contact.view')) {

            if (checkPermission('tenant.contact.view_own')) {

                $user = Auth::user();

                if (
                    $user->user_type === 'tenant' &&
                    $user->tenant_id === $tenantId &&
                    $user->is_admin === false
                ) {
                    $staffId = $user->id;
                    $tenantSubdomain = tenant_subdomain_by_tenant_id($user->tenant_id);
                    $contactTable = Contact::fromTenant($tenantSubdomain)->getModel()->getTable();

                    $campaigns->whereExists(function ($subquery) use ($staffId, $contactTable) {
                        $subquery->select(DB::raw(1))
                            ->from('campaign_details')
                            ->join($contactTable, 'campaign_details.rel_id', '=', $contactTable.'.id')
                            ->whereColumn('campaign_details.campaign_id', 'campaigns.id')
                            ->where($contactTable.'.assigned_id', $staffId);
                    });
                }
            }
        }

        $items = collect($paginator->items())->map(function ($campaign, $index) use ($paginator) {

            return [
                'id' => $campaign->id,
                'sr_no' => $paginator->firstItem() + $index,
                'campaign_name' => $campaign->name,
                'template_name' => $campaign->template_name,
                'rel_type' => ucfirst($campaign->rel_type ?? 'N/A'),
                'status' => $campaign->status_badge,
                'sending_count' => (int) $campaign->total_details,
                'delivered' => (int) $campaign->delivered,
                'read_by' => (int) $campaign->read_by,
                'created_at' => $campaign->created_at,
                'can_view' => checkPermission('tenant.campaigns.show_campaign'),
                'can_edit' => checkPermission('tenant.campaigns.edit'),
                'can_delete' => checkPermission('tenant.campaigns.delete'),
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

    public function filters(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.campaigns.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }
        $tenantId = tenant_id();

        $templates = $templates = WhatsappTemplate::query()
            ->selectRaw('whatsapp_templates.*, 
            (SELECT COUNT(*) FROM whatsapp_templates i2 
            WHERE i2.id <= whatsapp_templates.id 
            AND i2.tenant_id = ?) as row_num', [$tenantId])
            ->where('tenant_id', $tenantId)
            ->get()
            ->map(fn (WhatsappTemplate $template) => [
                'value' => $template->template_id,
                'label' => $template->template_name,
            ]);

        return response()->json([
            'templates' => $templates,
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        // Filter by template_id (use campaigns table explicitly)
        if ($request->filled('template_id')) {
            $query->where('campaigns.template_id', $request->input('template_id'));
        }

        // Filter by relation_type
        if ($request->filled('relation_type')) {
            $query->where('campaigns.rel_type', $request->input('relation_type'));
        }

        // Filter by created_from date
        if ($request->filled('created_from')) {
            $query->whereDate('campaigns.created_at', '>=', $request->input('created_from'));
        }

        // Filter by created_until date
        if ($request->filled('created_until')) {
            $query->whereDate('campaigns.created_at', '<=', $request->input('created_until'));
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search) {
                $q->orWhere('name', 'like', "%{$search}%");
            });
        }
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        switch ($sort) {
            case 'campaign_name':
                $query->orderBy('campaigns.name', $direction);
                break;

            case 'template_name':
                $query->orderBy('whatsapp_templates.template_name', $direction);
                break;

            case 'rel_type':
                $query->orderBy('campaigns.rel_type', $direction);
                break;

            case 'created_at':
                $query->orderBy('campaigns.created_at', $direction);
                break;

            case 'sending_count':
                $query->orderBy('total_details', $direction);
                break;

            case 'delivered':
                $query->orderBy('delivered', $direction);
                break;

            case 'read_by':
                $query->orderBy('read_by', $direction);
                break;

            default:
                $query->orderBy('campaigns.created_at', 'desc');
                break;
        }
    }

    public function toggleActive(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.campaigns.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $templateBot = TemplateBot::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $templateBot->update(['is_bot_active' => ! $templateBot->is_bot_active]);

        return response()->json([
            'value' => (bool) $templateBot->is_bot_active,
            'message' => t('template_bot_updated_successfully'),
        ]);
    }
}
