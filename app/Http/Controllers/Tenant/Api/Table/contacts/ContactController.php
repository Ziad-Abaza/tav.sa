<?php

namespace App\Http\Controllers\Tenant\Api\Table\contacts;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Contact;
use App\Models\Tenant\CustomField;
use App\Models\Tenant\Group;
use App\Models\Tenant\Source;
use App\Models\Tenant\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    /** @var string[] Whitelisted sortable columns — prevents SQL injection */
    private array $sortable = [
        'firstname',
        'lastname',
        'type',
        'phone',
        'email',
        'company',
        'is_enabled',
        'is_opted_out',
        'created_at',
    ];

    /** @var string[] Columns included in global search */
    private array $searchable = [
        'firstname',
        'lastname',
        'phone',
        'email',
        'company',
    ];

    /**
     * Main data endpoint - returns paginated contact data
     */
    public function index(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.contact.view', 'tenant.contact.view_own'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $subdomain = tenant_subdomain();
        $tenantId = tenant_id();

        $query = Contact::fromTenant($subdomain)
            ->where('tenant_id', $tenantId)
            ->with([
                'user:id,firstname,lastname,avatar',
                'status:id,name,color',
                'source:id,name',
            ]);

        // Restrict to own contacts if user only has view_own permission
        if (! checkPermission('tenant.contact.view') && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', Auth::id());
        }

        // Apply filters and search
        $this->applyFilters($query, $request);

        // Sort (whitelist enforced)
        $this->applySorting($query, $request);

        // Paginate (capped at 1000)
        $perPage = min($request->integer('per_page', 25), 1000);
        $paginator = $query->paginate($perPage);

        // Transform each contact row for the frontend
        $from = $paginator->firstItem() ?? 0;
        $allGroups = Group::where('tenant_id', $tenantId)
            ->pluck('name', 'id');

        $customFields = CustomField::getCustomFields();

        $items = collect($paginator->items())->map(
            function (Contact $contact, int $index) use ($from, $allGroups, $customFields): array {

                $groupIds = $contact->getGroupIds();

                $groups = collect($groupIds)
                    ->map(function ($id) use ($allGroups) {
                        return isset($allGroups[$id])
                            ? [
                                'id' => (int) $id,
                                'name' => $allGroups[$id],
                            ]
                            : null;
                    })
                    ->filter()
                    ->values()
                    ->toArray();

                // 🔥 BUILD CUSTOM FIELD DATA PER CONTACT
                $customData = [];

                foreach ($customFields as $field) {

                    $value = $contact->getCustomFieldValue($field['field_name']);

                    // format number like Filament
                    if ($field['field_type'] === 'number' && is_numeric($value)) {
                        $value = number_format($value);
                    }

                    $customData['custom_field_'.$field['field_name']] =
                        $value ?: null;
                }

                return array_merge([
                    'id' => $contact->id,
                    'sr_no' => $from + $index,
                    'firstname' => $contact->firstname,
                    'lastname' => $contact->lastname,
                    'name' => trim($contact->firstname.' '.$contact->lastname),
                    'company' => $contact->company,
                    'type' => $contact->type,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_enabled' => (bool) $contact->is_enabled,
                    'is_opted_out' => (bool) $contact->is_opted_out,
                    'groups' => $groups,

                    'status' => $contact->status ? [
                        'id' => $contact->status->id,
                        'name' => $contact->status->name,
                        'color' => $contact->status->color,
                    ] : null,

                    'source' => $contact->source ? [
                        'id' => $contact->source->id,
                        'name' => $contact->source->name,
                    ] : null,

                    'user' => $contact->user ? [
                        'id' => $contact->user->id,
                        'firstname' => $contact->user->firstname,
                        'lastname' => $contact->user->lastname,
                        'avatar' => $contact->user->avatar,
                    ] : null,

                    'created_at' => $contact->created_at?->toISOString(),
                    'can_view' => checkPermission('tenant.contact.view'),
                    'can_edit' => checkPermission('tenant.contact.edit'),
                    'can_delete' => checkPermission('tenant.contact.delete'),

                ], $customData); // 🔥 MERGE CUSTOM FIELDS HERE
            }
        );

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
     * Get filter options for the contact table
     */
    public function filters(Request $request): JsonResponse
    {
        if (! checkPermission(['tenant.contact.view', 'tenant.contact.view_own'])) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }
        $tenantId = tenant_id();

        $statuses = Status::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (Status $s) => [
                'value' => $s->id,
                'label' => $s->name,
                'color' => $s->color,
            ]);

        $sources = Source::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Source $s) => [
                'value' => $s->id,
                'label' => $s->name,
            ]);

        $users = User::where('tenant_id', $tenantId)
            ->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname'])
            ->map(fn (User $u) => [
                'value' => $u->id,
                'label' => trim($u->firstname.' '.$u->lastname),
            ]);

        return response()->json([
            'types' => [
                ['value' => 'customer', 'label' => 'Customer'],
                ['value' => 'lead', 'label' => 'Lead'],
                ['value' => 'guest', 'label' => 'Guest'],
            ],
            'statuses' => $statuses,
            'sources' => $sources,
            'users' => $users,
        ]);
    }

    /**
     * Toggle the is_enabled flag on a contact.
     */
    public function toggleEnabled(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.contact.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $contact = Contact::fromTenant(tenant_subdomain())
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $contact->update(['is_enabled' => ! $contact->is_enabled]);

        $statusMessage = t('status_updated_successfully');

        return response()->json([
            'value' => (bool) $contact->is_enabled,
            'message' => $statusMessage,
        ]);
    }

    /**
     * Toggle the is_opted_out flag on a contact.
     */
    public function toggleOptedOut(string $subdomain, string $id): JsonResponse
    {
        if (! checkPermission('tenant.contact.edit')) {
            return response()->json(['message' => t('access_denied_note')], 403);
        }

        $contact = Contact::fromTenant(tenant_subdomain())
            ->where('tenant_id', tenant_id())
            ->findOrFail($id);

        $contact->update(['is_opted_out' => ! $contact->is_opted_out]);

        return response()->json([
            'value' => (bool) $contact->is_opted_out,
            'message' => $contact->is_opted_out
                ? t('contact_added_to_opted_out')
                : t('contact_removed_from_opted_out'),
        ]);
    }

    /**
     * Export contacts as CSV
     */
    public function export(Request $request): StreamedResponse
    {
        if (! checkPermission(['tenant.contact.view', 'tenant.contact.view_own'])) {
            abort(403, t('access_denied_note'));
        }

        $subdomain = tenant_subdomain();
        $tenantId = tenant_id();

        $query = Contact::fromTenant($subdomain)
            ->where('tenant_id', $tenantId)
            ->with([
                'user:id,firstname,lastname',
                'status:id,name',
                'source:id,name',
            ]);

        // Restrict to own contacts if user only has view_own permission
        if (! checkPermission('tenant.contact.view') && checkPermission('tenant.contact.view_own')) {
            $query->where('assigned_id', Auth::id());
        }

        // Apply same filters as the table view
        $this->applyFilters($query, $request);

        // Apply same sorting as the table view
        $this->applySorting($query, $request);

        // Export exactly what the user sees — same page + per_page as the table
        $perPage = min($request->integer('per_page', 25), 1000);
        $page = max($request->integer('page', 1), 1);
        $contacts = $query->forPage($page, $perPage)->get();

        // Build CSV in memory — small dataset, no streaming needed
        $csv = Writer::createFromString();
        $csv->insertOne([
            'First Name',
            'Last Name',
            'Company',
            'Type',
            'Phone',
            'Email',
            'Status',
            'Source',
            'Assigned To',
            'Active',
            'Opted Out',
            'Created At',
        ]);

        foreach ($contacts as $contact) {
            $csv->insertOne([
                $contact->firstname,
                $contact->lastname,
                $contact->company,
                $contact->type,
                $contact->phone,
                $contact->email,
                $contact->status?->name,
                $contact->source?->name,
                $contact->user ? trim($contact->user->firstname.' '.$contact->user->lastname) : '',
                $contact->is_enabled ? 'Yes' : 'No',
                $contact->is_opted_out ? 'Yes' : 'No',
                $contact->created_at?->format('Y-m-d H:i:s'),
            ]);
        }

        $filename = 'contacts-page'.$page.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv->toString()),
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
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

        // Filters
        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($statusId = $request->integer('status_id')) {
            $query->where('status_id', $statusId);
        }

        if ($sourceId = $request->integer('source_id')) {
            $query->where('source_id', $sourceId);
        }

        if ($assignedId = $request->integer('assigned_id')) {
            $query->where('assigned_id', $assignedId);
        }

        if ($groupId = $request->integer('group_id')) {
            $query->whereJsonContains('group_id', $groupId);
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
