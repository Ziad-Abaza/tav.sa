<?php

namespace App\Http\Controllers\Tenant\Api\Table\facebookmessenger;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FbMessengerCampaignDetail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FbMessengerCampaignDetailController extends Controller
{
    private array $sortable = [
        'id',
        'contact_name',
        'facebook_psid',
        'message_text',
        'status',
        'message_status',
        'created_at',
    ];

    private array $searchable = ['firstname', 'lastname', 'facebook_psid', 'message_text'];

    public function index(Request $request): JsonResponse
    {
        $campaignId = $request->get('campaign_id');
        $tenantId = tenant_id();
        $subdomain = tenant_subdomain();

        $query = FbMessengerCampaignDetail::query()
            ->leftJoin($subdomain.'_contacts as contact', 'fb_messenger_campaign_details.contact_id', '=', 'contact.id')
            ->where('fb_messenger_campaign_details.campaign_id', $campaignId)
            ->where('fb_messenger_campaign_details.tenant_id', $tenantId)
            ->select([
                'fb_messenger_campaign_details.id',
                'fb_messenger_campaign_details.facebook_psid',
                DB::raw("CONCAT(COALESCE(contact.firstname, ''), ' ', COALESCE(contact.lastname, '')) as contact_name"),
                'fb_messenger_campaign_details.message_text',
                'fb_messenger_campaign_details.status',
                'fb_messenger_campaign_details.message_status',
                'fb_messenger_campaign_details.fb_message_id',
                'fb_messenger_campaign_details.response_message',
                'fb_messenger_campaign_details.created_at',
                'fb_messenger_campaign_details.updated_at',
            ]);

        // 🔎 Search
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->orWhere('contact.firstname', 'like', "%{$search}%")
                    ->orWhere('contact.lastname', 'like', "%{$search}%")
                    ->orWhere('fb_messenger_campaign_details.facebook_psid', 'like', "%{$search}%")
                    ->orWhere('fb_messenger_campaign_details.message_text', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->get('status') !== '' && $request->get('status') !== null) {
            $query->where('fb_messenger_campaign_details.status', $request->get('status'));
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
            'columns' => $this->getColumns(),
            'filters' => $this->getFilters(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function filters(): JsonResponse
    {
        return response()->json([
            'filters' => $this->getFilters(),
        ]);
    }

    protected function getColumns(): array
    {
        return [
            ['key' => 'contact_name', 'label' => t('contact'), 'sortable' => true],
            ['key' => 'facebook_psid', 'label' => t('facebook_psid'), 'sortable' => true],
            ['key' => 'message_text', 'label' => t('message'), 'sortable' => false, 'truncate' => 50],
            ['key' => 'status', 'label' => t('status'), 'sortable' => true],
            ['key' => 'message_status', 'label' => t('delivery_status'), 'sortable' => true],
            ['key' => 'created_at', 'label' => t('created_at'), 'sortable' => true, 'format' => 'datetime'],
        ];
    }

    protected function getFilters(): array
    {
        return [
            [
                'key' => 'status',
                'label' => t('status'),
                'type' => 'select',
                'options' => [
                    ['value' => '', 'label' => t('all')],
                    ['value' => '1', 'label' => t('pending')],
                    ['value' => '2', 'label' => t('sent')],
                    ['value' => '0', 'label' => t('failed')],
                ],
            ],
        ];
    }

    public function export(Request $request): StreamedResponse
    {
        $campaignId = $request->get('campaign_id');
        $tenantId = tenant_id();
        $subdomain = tenant_subdomain();

        $query = FbMessengerCampaignDetail::query()
            ->leftJoin($subdomain.'_contacts as contact', 'fb_messenger_campaign_details.contact_id', '=', 'contact.id')
            ->where('fb_messenger_campaign_details.campaign_id', $campaignId)
            ->where('fb_messenger_campaign_details.tenant_id', $tenantId)
            ->select([
                'fb_messenger_campaign_details.id',
                DB::raw("CONCAT(COALESCE(contact.firstname, ''), ' ', COALESCE(contact.lastname, '')) as name"),
                'fb_messenger_campaign_details.facebook_psid',
                'fb_messenger_campaign_details.message_text',
                'fb_messenger_campaign_details.status',
                'fb_messenger_campaign_details.message_status',
                'fb_messenger_campaign_details.fb_message_id',
                'fb_messenger_campaign_details.response_message',
            ]);

        // 🔎 Search (optional)
        if ($search = $request->get('q')) {
            if ($search !== 'undefined') {
                $query->where(function ($q) use ($search) {
                    $q->orWhere('contact.firstname', 'like', "%{$search}%")
                        ->orWhere('contact.lastname', 'like', "%{$search}%")
                        ->orWhere('fb_messenger_campaign_details.facebook_psid', 'like', "%{$search}%")
                        ->orWhere('fb_messenger_campaign_details.message_text', 'like', "%{$search}%");
                });
            }
        }

        // Status filter
        if ($request->has('status') && $request->get('status') !== '' && $request->get('status') !== null) {
            $query->where('fb_messenger_campaign_details.status', $request->get('status'));
        }

        // ↕ Sorting (safe)
        $sort = $request->get('sort');
        $dir = $request->get('direction');

        $sort = in_array($sort, ['id', 'created_at'], true) ? $sort : 'id';
        $dir = $dir === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $dir);

        $rows = $query->get();

        $filename = 'fb-messenger-campaign-recipients.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM (important for Excel)
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // ✅ Headers
            fputcsv($handle, [
                'ID',
                'Name',
                'Facebook PSID',
                'Message',
                'Status',
                'Delivery Status',
                'FB Message ID',
                'Response',
            ]);

            // ✅ Rows
            foreach ($rows as $row) {
                $statusLabel = match ((int) $row->status) {
                    0 => 'Failed',
                    1 => 'Pending',
                    2 => 'Sent',
                    default => 'Unknown',
                };

                fputcsv($handle, [
                    $row->id,
                    $row->name,
                    $row->facebook_psid,
                    $row->message_text,
                    $statusLabel,
                    $row->message_status ?? '-',
                    $row->fb_message_id ?? '-',
                    $row->response_message ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
