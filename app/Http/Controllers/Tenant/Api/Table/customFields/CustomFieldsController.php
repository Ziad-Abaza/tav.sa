<?php

namespace App\Http\Controllers\Tenant\Api\Table\customFields;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CustomField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomFieldsController extends Controller
{
    /**
     * Main data endpoint - returns paginated contact data
     */
    private array $searchable = [
        'field_label',
        'field_name',
        'field_type',
    ];

    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.custom_fields.view'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $tenantId = tenant_id();

        $custom_fields = CustomField::query()
            ->selectRaw('custom_fields.*, (SELECT COUNT(*) FROM custom_fields i2 WHERE i2.id <= custom_fields.id AND i2.tenant_id = ?) as row_num', [$tenantId])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($custom_fields, $request);

        $perPage = min($request->integer('per_page', 25), 1000);

        $paginator = $custom_fields->latest()->paginate($perPage);

        $from = $paginator->firstItem() ?? 0;

        $items = collect($paginator->items())->map(function ($field, $index) use ($paginator) {

            return [
                'id' => $field->id,
                'sr_no' => $paginator->firstItem() + $index,
                'custom_field_name' => $field->field_label,
                'field_name' => $field->field_name,
                'custom_field_type' => $field->field_type,
                'is_active' => (bool) $field->is_active,
                'is_required' => (bool) $field->is_required,
                'show_on_table' => (bool) $field->show_on_table,
                'created_at' => $field->created_at?->diffForHumans(),
                'can_delete' => checkPermission(['tenant.custom_fields.delete']),
                'can_edit' => checkPermission(['tenant.custom_fields.edit']),
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
        // Global search
        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $q) use ($search): void {
                foreach ($this->searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }
    }

    public function toggleActive(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.custom_fields.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $customField = CustomField::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $customField->update(['is_active' => ! $customField->is_active]);

        return response()->json([
            'value' => (bool) $customField->is_active,
            'message' => t('custom_field_updated_successfully'),
        ]);
    }

    public function toggleRequired(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.custom_fields.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $customField = CustomField::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $customField->update(['is_required' => ! $customField->is_required]);

        return response()->json([
            'value' => (bool) $customField->is_required,
            'message' => t('custom_field_updated_successfully'),
        ]);
    }

    public function toggleShowOnTable(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.custom_fields.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $customField = CustomField::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $customField->update(['show_on_table' => ! $customField->show_on_table]);

        return response()->json([
            'value' => (bool) $customField->show_on_table,
            'message' => t('custom_field_updated_successfully'),
        ]);
    }
}
