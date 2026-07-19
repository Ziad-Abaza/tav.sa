<?php

namespace App\Http\Controllers\Tenant\Api\Table\whatsapp_template;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WhatsappTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappTemplateController extends Controller
{
    private array $sortable = ['row_num', 'template_name', 'language', 'category', 'header_data_format', 'status', 'template_type', 'body_data'];

    private array $searchable = ['template_name', 'language', 'body_data'];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission('tenant.template.view')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();

        $query = WhatsappTemplate::query()
            ->selectRaw('whatsapp_templates.*, 
        (SELECT COUNT(*) FROM whatsapp_templates i2 
         WHERE i2.id <= whatsapp_templates.id 
         AND i2.tenant_id = ?) as row_num', [$tenantId])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);

        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);
        $from = $paginator->firstItem() ?? 0;

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($row, $index) => [
                'id' => $row->id,
                'row_num' => $from + $index,
                'template_name' => $row->template_name,
                'template_id' => $row->template_id,
                'language' => $row->language,
                'category' => $row->category,
                'header_data_format' => $row->header_data_format ?? '-',
                'status' => $row->status,
                'template_type' => $row->template_type,
                'body_data' => $row->body_data,
                'can_delete' => checkPermission(['tenant.template.delete']),
                'can_edit' => checkPermission(['tenant.template.edit']),

            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function destroy(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.template.delete')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $template = WhatsappTemplate::where('tenant_id', tenant_id())
            ->findOrFail($id);

        $template->delete();

        return response()->json([
            'message' => t('template_deleted_successfully'),
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

        if ($request->filled('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->filled('header_data_format')) {
            $query->where('header_data_format', $request->header_data_format);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
    }

    public function filters(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.template.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }
        $tenantId = tenant_id();

        $templates = WhatsappTemplate::query()
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

        $languages = WhatsappTemplate::where('tenant_id', $tenantId)
            ->whereNotNull('language')
            ->where('language', '!=', '')
            ->distinct()
            ->orderBy('language')
            ->pluck('language')
            ->map(fn ($code) => [
                'value' => $code,
                'label' => $code,
            ]);

        $categories = [
            ['value' => 'MARKETING', 'label' => 'MARKETING'],
            ['value' => 'UTILITY', 'label' => 'UTILITY'],
            ['value' => 'AUTHENTICATION', 'label' => 'OTP'],
        ];

        $statuses = [
            ['value' => 'APPROVED', 'label' => 'APPROVED'],
            ['value' => 'PENDING', 'label' => 'PENDING'],
            ['value' => 'REJECTED', 'label' => 'REJECTED'],
        ];

        $templateTypes = WhatsappTemplate::where('tenant_id', $tenantId)
            ->whereNotNull('header_data_format')
            ->where('header_data_format', '!=', '')
            ->distinct()
            ->orderBy('header_data_format')
            ->pluck('header_data_format')
            ->map(fn ($format) => [
                'value' => $format,
                'label' => $format,
            ]);

        return response()->json([
            'templates' => $templates,
            'language' => $languages,
            'category' => $categories,
            'header_data_format' => $templateTypes,
            'status' => $statuses,
        ]);
    }

    private function applySorting(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $dir = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        if ($sort === 'row_num') {
            $query->orderBy('id', $dir);

            return;
        }
        $query->orderBy(
            in_array($sort, $this->sortable, true) ? $sort : 'created_at',
            $dir
        );
    }
}
