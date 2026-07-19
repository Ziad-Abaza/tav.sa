<?php

namespace App\Livewire\Tenant\Group;

use App\Models\Tenant\Group;
use App\Rules\PurifiedInput;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class GroupList extends Component
{
    use WithPagination;

    public $group = [];

    public $showGroupModal = false;

    public $confirmingDeletion = false;

    public $group_id = null;

    public $tenant_id;

    protected $listeners = [
        'editGroup' => 'editGroup',
        'confirmDelete' => 'confirmDelete',
    ];

    public function mount()
    {
        if (! checkPermission('tenant.group.view')) {
            $this->notify([
                'type' => 'danger',
                'message' => t('access_denied_note'),
            ]);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }
        $this->resetForm();
        $this->group = ['name' => ''];
        $this->tenant_id = tenant_id();
    }

    protected function rules()
    {
        return [
            'group.name' => [
                'required',
                Rule::unique('groups', 'name')->where(function ($query) {
                    return $query->where('tenant_id', tenant_id());
                })
                    ->ignore($this->group['id'] ?? null),
                new PurifiedInput(t('sql_injection_error')),
                'max:150',
            ],
        ];
    }

    public function createGroupPage()
    {
        $this->resetForm();
        $this->showGroupModal = true;
    }

    public function save()
    {
        if (checkPermission(['tenant.group.create', 'tenant.group.create'])) {

            $this->validate();

            $isNew = empty($this->group['id']);

            if ($isNew) {
                $groupModel = new Group;
            } else {
                $groupModel = Group::findOrFail($this->group['id']);
            }

            $groupModel->name = $this->group['name'];
            $groupModel->tenant_id = tenant_id();
            $groupModel->save();

            $this->showGroupModal = false;

            $message = $isNew
                ? t('group_saved_successfully')
                : t('group_update_successfully');

            $this->notify(['type' => 'success', 'message' => $message]);
            $this->dispatch('group-table-refresh');
        }
    }

    public function editGroup($groupId)
    {
        $groupModel = Group::findOrFail($groupId);
        $this->group = [
            'id' => $groupModel->id,
            'name' => $groupModel->name,
        ];
        $this->resetValidation();
        $this->showGroupModal = true;
    }

    public function confirmDelete($groupId)
    {
        $this->group_id = $groupId;
        $this->confirmingDeletion = true;
    }

    public function delete()
    {
        if (checkPermission('tenant.group.delete')) {

            $group = Group::find($this->group_id);

            if ($group) {
                $group->delete();
            }

            $this->confirmingDeletion = false;
            $this->resetForm();
            $this->group_id = null;
            $this->resetPage();

            $this->notify(['type' => 'success', 'message' => t('group_delete_successfully')]);
            $this->dispatch('group-table-refresh');
        }
    }

    private function resetForm()
    {
        $this->resetExcept('group');
        $this->resetValidation();
        $this->group = ['name' => ''];
    }

    public function refreshTable()
    {
        $this->dispatch('group-table-refresh');
    }

    public function render()
    {
        return view('livewire.tenant.group.group-list');
    }
}
