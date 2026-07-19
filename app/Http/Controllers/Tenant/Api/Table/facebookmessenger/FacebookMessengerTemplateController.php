<?php

namespace App\Http\Controllers\Tenant\Api\Table\facebookmessenger;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FacebookMessengerTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FacebookMessengerTemplateController extends Controller
{
    /** @var string[] Whitelisted sortable columns */
    private array $sortable = [
        'name',
        'content_type',
        'is_active',
        'sending_count',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'name',
        'description',
        'message_text',
    ];

    /**
     * Main data endpoint - returns paginated template data
     */
    public function index(Request $request): JsonResponse
    {
        $subdomain = tenant_subdomain();
        $tenantId = tenant_id();

        $query = FacebookMessengerTemplate::query()
            ->where('tenant_id', $tenantId);

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each template row for the frontend
        $from = $paginator->firstItem() ?? 0;
        $items = collect($paginator->items())->map(function (FacebookMessengerTemplate $template, int $index) use ($from): array {
            return [
                'id' => $template->id,
                'sr_no' => $from + $index,
                'name' => $template->name,
                'description' => $template->description,
                'content_type' => $template->content_type,
                'media_url' => $template->media_url,
                'media_filename' => $template->media_filename,
                'message_text' => $template->message_text,
                'message_preview' => Str::limit($template->message_text, 50),
                'buttons' => $template->buttons,
                'button_count' => $template->getButtonCount(),
                'is_active' => (bool) $template->is_active,
                'sending_count' => $template->getTotalSentCount(),
                'copied_from_template_id' => $template->copied_from_template_id,
                'created_at' => $template->created_at?->toISOString(),
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

    /**
     * Get filter options for the template table
     */
    public function filters(Request $request): JsonResponse
    {
        return response()->json([
            'content_types' => collect(FacebookMessengerTemplate::contentTypes())
                ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])
                ->values(),
            'is_active' => [
                ['value' => '1', 'label' => 'Active'],
                ['value' => '0', 'label' => 'Inactive'],
            ],
        ]);
    }

    /**
     * Delete a template
     */
    public function destroy(string $subdomain, int $id): JsonResponse
    {

        $template = FacebookMessengerTemplate::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        if ($template->media_url) {
            $urlPath = parse_url($template->media_url, PHP_URL_PATH);
            $relativePath = ltrim(str_replace('/storage/', '', $urlPath), '/');
            Storage::disk('public')->delete($relativePath);
        }

        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }

    /**
     * Toggle the is_active flag
     */
    public function toggleActive(string $subdomain, int $id): JsonResponse
    {
        $template = FacebookMessengerTemplate::query()
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $template->update(['is_active' => ! $template->is_active]);

        return response()->json([
            'value' => (bool) $template->is_active,
            'message' => $template->is_active
                ? 'Template activated'
                : 'Template deactivated',
        ]);
    }

    /**
     * Apply filters to the query
     */
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

        // Content type filter
        if ($contentType = $request->string('content_type')->toString()) {
            $query->where('content_type', $contentType);
        }

        // Active filter
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
    }

    /**
     * Apply sorting to the query
     */
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
