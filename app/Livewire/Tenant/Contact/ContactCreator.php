<?php

namespace App\Livewire\Tenant\Contact;

use App\Models\Tenant\Contact;
use App\Models\Tenant\ContactNote;
use App\Models\Tenant\CustomField;
use App\Models\Tenant\Group;
use App\Models\Tenant\Source;
use App\Models\Tenant\Status;
use App\Models\User;
use App\Rules\PurifiedInput;
use App\Services\FeatureService;
use App\Traits\WithTenantContext;
use Corbital\LaravelEmails\Facades\Email;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ContactCreator extends Component
{
    use WithTenantContext;

    public array $contact = [];

    public string $notes_description = '';

    public array $notes = [];

    public $id;

    public $noteId;

    public $confirmingDeletion = false;

    public ?int $contactId = null;

    public int $initialNotesCount;

    public $tab = 'contact-details';

    public ?string $notetab = null;

    public $tenant_id;

    public $tenant_subdomain;

    protected $featureLimitChecker;

    public $grouped_id = null;

    // Array for multiple groups
    public $group_ids = [];

    public $groups = [];

    public $customFields = [];

    public $customFieldsData = [];

    public function boot(FeatureService $featureLimitChecker)
    {
        $this->featureLimitChecker = $featureLimitChecker;
        $this->bootWithTenantContext();
    }

    public function mount()
    {
        if (! checkPermission(['tenant.contact.create', 'tenant.contact.edit'])) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect(tenant_route('tenant.dashboard'));
        }

        $this->tenant_id = tenant_id();
        $this->tenant_subdomain = tenant_subdomain_by_tenant_id($this->tenant_id);

        $this->mountWithTenantContext();
        $this->id = $this->getId();
        $this->contactId = request()->route('contactId');

        $this->loadCustomFields();

        // Load contact data if editing
        if ($this->contactId) {
            $contactModel = Contact::fromTenant($this->tenant_subdomain)->findOrFail($this->contactId);
            $this->contact = [
                'id' => $contactModel->id,
                'firstname' => $contactModel->firstname,
                'lastname' => $contactModel->lastname,
                'company' => $contactModel->company,
                'type' => $contactModel->type,
                'description' => $contactModel->description,
                'country_id' => $contactModel->country_id,
                'zip' => $contactModel->zip,
                'city' => $contactModel->city,
                'state' => $contactModel->state,
                'address' => $contactModel->address,
                'assigned_id' => $contactModel->assigned_id,
                'status_id' => $contactModel->status_id,
                'source_id' => $contactModel->source_id,
                'email' => $contactModel->email,
                'website' => $contactModel->website,
                'phone' => $contactModel->phone,
            ];
            $this->group_ids = $contactModel->getGroupIds();

            // Set legacy grouped_id for backward compatibility if needed
            $this->grouped_id = ! empty($this->group_ids) ? $this->group_ids[0] : null;

            if ($contactModel->custom_fields_data) {
                foreach ($contactModel->custom_fields_data as $fieldName => $value) {
                    $this->customFieldsData[$fieldName] = $value;
                }
            }
        } else {
            // For new contacts
            $this->contact = [
                'firstname' => '',
                'lastname' => '',
                'company' => '',
                'type' => 'customer',
                'description' => '',
                'country_id' => null,
                'zip' => '',
                'city' => '',
                'state' => '',
                'address' => '',
                'assigned_id' => null,
                'status_id' => null,
                'source_id' => null,
                'email' => '',
                'website' => '',
                'phone' => '',
            ];
            $this->group_ids = [];
            $this->grouped_id = null;
        }

        $this->loadGroups();

        $this->notetab = request()->query('notetab');
        $this->initialNotesCount = ContactNote::fromTenant($this->tenant_subdomain)->count();
        $this->loadNotes();

        if ($this->notetab === 'notes') {
            $this->tab = 'notes';
        }
    }

    public function loadCustomFields()
    {
        $tenant = current_tenant();

        $this->customFields = CustomField::where('tenant_id', $tenant->id)
            ->active()
            ->ordered()
            ->get()
            ->toArray();

        // Initialize custom fields data with default values
        foreach ($this->customFields as $field) {
            if (! isset($this->customFieldsData[$field['field_name']])) {
                if ($field['field_type'] === 'checkbox') {
                    // Initialize checkboxes as empty array
                    $this->customFieldsData[$field['field_name']] = [];
                } else {
                    $this->customFieldsData[$field['field_name']] = $field['default_value'] ?? '';
                }
            } elseif ($field['field_type'] === 'checkbox' && ! is_array($this->customFieldsData[$field['field_name']])) {
                // Convert existing non-array checkbox data to array
                $this->customFieldsData[$field['field_name']] = [];
            }
        }
    }

    private function loadGroups()
    {
        $this->groups = Group::where('tenant_id', $this->tenant_id)
            ->select(['id', 'name'])
            ->get()
            ->map(function ($group) {
                $name = $group->name;

                return [
                    'id' => $group->id,
                    'name' => $name,
                ];
            });
    }

    protected function rules()
    {
        $contactTable = Contact::fromTenant($this->tenant_subdomain)->getTable();

        $rules = [
            'contact.firstname' => ['required', 'string', new PurifiedInput(t('sql_injection_error')), 'max:191'],
            'contact.lastname' => ['required', 'string', new PurifiedInput(t('sql_injection_error')), 'max:191'],
            'contact.company' => ['nullable', 'string', new PurifiedInput(t('sql_injection_error')), 'max:191'],
            'contact.type' => ['required', 'in:customer,lead,guest'],
            'contact.description' => ['nullable', 'string', new PurifiedInput(t('sql_injection_error')), 'max:65535'],
            'contact.country_id' => ['nullable', 'integer'],
            'contact.zip' => ['nullable', 'string', new PurifiedInput(t('sql_injection_error')), 'max:15'],
            'contact.city' => ['nullable', 'string', new PurifiedInput(t('sql_injection_error')), 'max:100'],
            'contact.state' => ['nullable', 'string', new PurifiedInput(t('sql_injection_error')), 'max:100'],
            'contact.address' => ['nullable', 'string', new PurifiedInput(t('sql_injection_error')), 'max:500'],
            'contact.assigned_id' => ['nullable'],
            'contact.status_id' => ['required', 'exists:statuses,id'],
            'contact.source_id' => ['required', 'exists:sources,id'],
            'contact.email' => ['nullable', 'email', 'max:191', Rule::unique($contactTable, 'email')->ignore($this->contact['id'] ?? null)->where(function ($query) {
                return $query->where('tenant_id', $this->tenant_id);
            })],
            'contact.website' => ['nullable', 'url', new PurifiedInput(t('sql_injection_error')), 'max:100'],
            'contact.phone' => ['required', 'regex:/^\+?[0-9]+$/', new PurifiedInput(t('sql_injection_error')), Rule::unique($contactTable, 'phone')->ignore($this->contact['id'] ?? null)->where(function ($query) {
                return $query->where('tenant_id', $this->tenant_id);
            })],
            'group_ids' => [
                'nullable',
                'array',
            ],
            'group_ids.*' => [
                'exists:groups,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $group = Group::find($value);
                    }
                },
            ],
            // For backward compatibility - not actually used in the update
            'grouped_id' => [
                'nullable',
            ],
        ];

        foreach ($this->customFields as $field) {
            $fieldRules = [];

            if ($field['is_required']) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field['field_type']) {
                case 'text':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
                case 'textarea':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:2000';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'dropdown':
                    if (! empty($field['field_options'])) {
                        $fieldRules[] = 'in:'.implode(',', $field['field_options']);
                    }
                    break;
                case 'checkbox':
                    $fieldRules = ['array'];
                    if (! empty($field['field_options'])) {
                        $rules["customFieldsData.{$field['field_name']}.*"] = ['string', Rule::in($field['field_options'])];
                    }
                    break;
            }

            $rules["customFieldsData.{$field['field_name']}"] = $fieldRules;
        }

        return $rules;
    }

    public function loadNotes()
    {
        if (empty($this->contact['id'])) {
            $this->notes = [];

            return;
        }

        $notes = new ContactNote;
        $notes->setTable($this->tenant_subdomain.'_contact_notes');

        $this->notes = ContactNote::fromTenant($this->tenant_subdomain)
            ->where('contact_id', $this->contact['id'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($note) => [
                'id' => $note->id,
                'notes_description' => $note->notes_description,
                'created_at' => $note->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    public function validateNotesDescription()
    {
        $this->validate([
            'notes_description' => ['nullable', 'string'],
        ]);
    }

    public function addNote()
    {
        $this->validate([
            'notes_description' => ['required', 'string'],
        ]);

        ContactNote::fromTenant($this->tenant_subdomain)->create([
            'notes_description' => $this->notes_description,
            'contact_id' => $this->contact['id'],
            'tenant_id' => $this->tenant_id,
        ]);

        $this->notes_description = '';
        $this->notify([
            'type' => 'success',
            'message' => t('note_added_successfully'),
        ]);

        $this->loadNotes();
    }

    public function confirmDelete($noteId)
    {
        $this->noteId = $noteId;

        $this->confirmingDeletion = true;
    }

    public function removeNote()
    {
        ContactNote::fromTenant($this->tenant_subdomain)->where('id', $this->noteId)->delete();

        $this->confirmingDeletion = false;
        $this->notify([
            'type' => 'success',
            'message' => t('note_delete_successfully'),
        ]);

        $this->loadNotes();
    }

    public function save()
    {
        if (checkPermission(['tenant.contact.create', 'tenant.contact.edit'])) {
            $this->validate();

            $isNewContact = empty($this->contact['id']);

            if ($isNewContact) {
                $contactModel = new Contact;
                $contactModel->setTable($this->tenant_subdomain.'_contacts');
                $contactModel->is_enabled = true;
            } else {
                $contactModel = Contact::fromTenant($this->tenant_subdomain)->findOrFail($this->contact['id']);
            }

            // Map array data to model
            $contactModel->firstname = $this->contact['firstname'];
            $contactModel->lastname = $this->contact['lastname'];
            $contactModel->company = $this->contact['company'];
            $contactModel->type = $this->contact['type'];
            $contactModel->description = $this->contact['description'];
            $contactModel->country_id = $this->contact['country_id'];
            $contactModel->zip = $this->contact['zip'];
            $contactModel->city = $this->contact['city'];
            $contactModel->state = $this->contact['state'];
            $contactModel->address = $this->contact['address'];
            $contactModel->assigned_id = $this->contact['assigned_id'] ?: null;
            $contactModel->status_id = $this->contact['status_id'];
            $contactModel->source_id = $this->contact['source_id'];
            $contactModel->email = $this->contact['email'];
            $contactModel->website = $this->contact['website'];
            $contactModel->phone = $this->contact['phone'];

            $assignedChanged = $contactModel->isDirty('assigned_id');

            if (! is_null($contactModel->assigned_id) && ($assignedChanged || is_null($contactModel->getOriginal('assigned_id')))) {
                $contactModel->dateassigned = now();
            }

            $contactModel->addedfrom = Auth::user()->id;

            $notesChanged = $isNewContact ? false : (ContactNote::fromTenant($this->tenant_subdomain)->where('contact_id', $this->contact['id'])->exists() !== ($this->initialNotesCount > 0));

            // For new contacts, check if creating one more would exceed the limit
            if ($isNewContact) {
                $limit = $this->featureLimitChecker->getLimit('contacts');

                // Skip limit check if unlimited (-1) or no limit set (null)
                if ($limit !== null && $limit !== -1) {
                    $currentCount = Contact::fromTenant($this->tenant_subdomain)->where('tenant_id', tenant_id())->count();

                    if ($currentCount >= $limit) {
                        $this->notify([
                            'type' => 'warning',
                            'message' => t('contact_limit_reached_upgrade_plan'),
                        ]);

                        return;
                    }
                }
            }

            $groupsToSet = $this->group_ids ?: [];

            // Handle legacy grouped_id if it exists (backward compatibility)
            if (empty($groupsToSet) && isset($this->grouped_id) && $this->grouped_id) {
                $groupsToSet = [$this->grouped_id];
            }

            // Set groups using the safe method
            $contactModel->setGroupIds($groupsToSet);

            $contactModel->custom_fields_data = $this->prepareCustomFieldsData();

            if ($contactModel->isDirty() || $notesChanged) {
                $contactModel->tenant_id = $this->tenant_id;
                $contactModel->save();

                // Update local contact array with saved ID
                $this->contact['id'] = $contactModel->id;

                // Update local group_ids from saved contact
                $this->group_ids = $contactModel->getGroupIds();

                // Set grouped_id to first group for backward compatibility (if needed)
                $this->grouped_id = ! empty($this->group_ids) ? $this->group_ids[0] : null;

                if ($isNewContact) {
                    $this->featureLimitChecker->trackUsage('contacts');
                }

                if (($isNewContact || $assignedChanged) && can_send_email('tenant-new-contact-assigned', 'tenant_email_templates', $this->tenant_id) && is_smtp_valid()) {
                    $this->send_content_assigned_mail();
                }

                $this->initialNotesCount = ContactNote::fromTenant($this->tenant_subdomain)->where('contact_id', $contactModel->id)->count();

                $this->notify([
                    'type' => 'success',
                    'message' => $isNewContact
                        ? t('contact_created_successfully')
                        : t('contact_update_successfully'),
                ], true);
            }

            return $this->redirect(tenant_route('tenant.contacts.list'));
        }
    }

    private function prepareCustomFieldsData(): array
    {
        $preparedData = [];

        foreach ($this->customFields as $field) {
            $fieldName = $field['field_name'];
            $value = $this->customFieldsData[$fieldName] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            // Handle checkbox fields
            if ($field['field_type'] === 'checkbox') {
                if (is_array($value)) {
                    $selectedOptions = array_values(array_filter($value, function ($option) use ($field) {
                        return in_array($option, $field['field_options']);
                    }));
                    if (! empty($selectedOptions)) {
                        $preparedData[$fieldName] = $selectedOptions;
                    }
                }

                continue;
            }

            // Handle other field types
            switch ($field['field_type']) {
                case 'number':
                    $preparedData[$fieldName] = is_numeric($value) ? (float) $value : null;
                    break;
                case 'date':
                    try {
                        $preparedData[$fieldName] = \Carbon\Carbon::parse($value)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $preparedData[$fieldName] = $value;
                    }
                    break;
                default:
                    $preparedData[$fieldName] = $value;
                    break;
            }
        }

        return $preparedData;
    }

    public function getHasCustomFieldsProperty(): bool
    {
        return ! empty($this->customFields);
    }

    public function send_content_assigned_mail()
    {
        try {
            $assignee = User::select('email', 'firstname', 'lastname')->find($this->contact['assigned_id']);
            if ($assignee && is_smtp_valid()) {
                $assignedEmail = $assignee->email;
                $content = render_email_template('tenant-new-contact-assigned', ['userId' => Auth::user()->id, 'contactId' => $this->contact['id'], 'tenantId' => $this->tenant_id], 'tenant_email_templates');
                $subject = get_email_subject('tenant-new-contact-assigned', ['userId' => Auth::user()->id, 'contactId' => $this->contact['id'], 'tenantId' => $this->tenant_id], 'tenant_email_templates');

                $result = Email::to($assignedEmail)
                    ->subject($subject)
                    ->content($content)
                    ->send();
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    public function cancel()
    {
        $this->resetValidation();
        $this->redirect(tenant_route('tenant.contacts.list'), navigate: true);
    }

    public function getRemainingLimitProperty()
    {
        return $this->featureLimitChecker->getRemainingLimit('contacts', Contact::class);
    }

    public function getIsUnlimitedProperty()
    {
        return $this->remainingLimit === null;
    }

    public function getHasReachedLimitProperty()
    {
        return $this->featureLimitChecker->hasReachedLimit('contacts', Contact::class);
    }

    public function getStatusesProperty()
    {
        return Status::all();
    }

    public function getSourcesProperty()
    {
        return Source::all();
    }

    public function getCountriesProperty()
    {
        return get_country_list();
    }

    public function getUsersProperty()
    {
        return User::where('tenant_id', $this->tenant_id)->get();
    }

    public function render()
    {
        return view('livewire.tenant.contact.contact-creator');
    }
}
