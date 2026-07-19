<?php

namespace App\Http\Controllers\Tenant\Api\Table\campaigns;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CampaignDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignDetailController extends Controller
{
    private array $sortable = [
        'id',
        'contact_name',
        'phone',
        'body_message',
        'message_status',
        'created_at',
    ];

    private array $searchable = ['firstname', 'lastname', 'phone', 'body_message'];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.campaigns.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $campaignId = $request->get('campaign_id');
        $tenantId = tenant_id();
        $subdomain = tenant_subdomain();

        $query = CampaignDetail::query()
            ->join($subdomain.'_contacts as contact', 'campaign_details.rel_id', '=', 'contact.id')
            ->where('campaign_details.campaign_id', $campaignId)
            ->where('campaign_details.status', 1)
            ->where('campaign_details.tenant_id', $tenantId)
            ->select([
                'campaign_details.id',
                'contact.phone',
                DB::raw("CONCAT(contact.firstname, ' ', contact.lastname) as contact_name"),
                DB::raw("
                    CONCAT(
                        COALESCE(campaign_details.header_message, ''),
                        '\n\n',
                        COALESCE(campaign_details.body_message, ''),
                        '\n\n',
                        COALESCE(campaign_details.footer_message, '')
                    ) as body_message
                "),
                'campaign_details.message_status',
                'campaign_details.created_at',
            ]);

        // 🔎 Search
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('contact.firstname', 'like', "%{$search}%")
                    ->orWhere('contact.lastname', 'like', "%{$search}%")
                    ->orWhere('contact.phone', 'like', "%{$search}%")
                    ->orWhere('campaign_details.body_message', 'like', "%{$search}%");
            });
        }

        // ↕ Sorting
        $sort = $request->get('sort', 'created_at');
        $dir = $request->get('direction', 'desc');

        $query->orderBy(
            in_array($sort, $this->sortable, true) ? $sort : 'created_at',
            $dir === 'asc' ? 'asc' : 'desc'
        );

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        if (! checkPermission(['tenant.campaigns.view'])) {
            abort(403, t('access_denied_note'));
        }

        $campaignId = $request->get('campaign_id');
        $tenantId = tenant_id();
        $subdomain = tenant_subdomain();

        $query = CampaignDetail::query()
            ->join($subdomain.'_contacts as contact', 'campaign_details.rel_id', '=', 'contact.id')
            ->where('campaign_details.campaign_id', $campaignId)
            ->where('campaign_details.status', 1)
            ->where('campaign_details.tenant_id', $tenantId)
            ->select([
                'campaign_details.id',
                DB::raw("CONCAT(contact.firstname, ' ', contact.lastname) as name"),
                'contact.phone',
                DB::raw("
                CONCAT(
                    COALESCE(campaign_details.header_message, ''),
                    '\n\n',
                    COALESCE(campaign_details.body_message, ''),
                    '\n\n',
                    COALESCE(campaign_details.footer_message, '')
                ) as message
            "),
                'campaign_details.message_status',
            ]);

        // 🔎 Search (optional)
        if ($search = $request->get('q')) {
            if ($search !== 'undefined') {
                $query->where(function ($q) use ($search) {
                    $q->orWhere('contact.firstname', 'like', "%{$search}%")
                        ->orWhere('contact.lastname', 'like', "%{$search}%")
                        ->orWhere('contact.phone', 'like', "%{$search}%")
                        ->orWhere('campaign_details.body_message', 'like', "%{$search}%");
                });
            }
        }

        // ↕ Sorting (safe)
        $sort = $request->get('sort');
        $dir = $request->get('direction');

        $sort = in_array($sort, ['id', 'created_at'], true) ? $sort : 'id';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $dir);

        $rows = $query->get();

        $filename = 'contact-list.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM (important for Excel)
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // ✅ Headers
            fputcsv($handle, [
                'ID',
                'Name',
                'Phone',
                'Message',
                'Sent Status',
            ]);

            // ✅ Rows
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->id,
                    $row->name,
                    $row->phone,
                    $row->message,
                    ucfirst($row->message_status),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
