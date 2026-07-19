<?php

namespace App\Livewire\Admin\Tenant;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantDeletionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TenantList extends Component
{
    public Tenant $tenant;

    public User $user;

    public $tenantId;

    public $confirmingDeletion = false;

    public $deleteMode = 'after_subscription'; // 'instant' or 'after_subscription'

    public $tenantHasSubscription = false;

    protected $listeners = [
        'editTenant' => 'editTenant',
        'confirmDelete' => 'confirmDelete',
        'viewTenant' => 'viewTenant',
        'confirmTenantRegistration' => 'confirmTenantRegistration',
        'restoreTenant' => 'restoreTenant',
    ];

    public function mount()
    {
        if (! checkPermission('admin.tenants.view')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect(route('admin.dashboard'));
        }
    }

    public function createTenant()
    {
        $this->redirect(tenant_route('admin.tenants.save'));
    }

    public function editTenant($tenantId)
    {
        if (! checkPermission('admin.tenants.edit')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect(route('admin.dashboard'));
        }
        $this->tenant = Tenant::findOrFail($tenantId);
        $this->redirect(route('admin.tenants.save', ['tenantId' => $tenantId]));
    }

    public function viewTenant($tenantId)
    {
        $this->redirect(route('admin.tenants.view', ['tenantId' => $tenantId]));
    }

    public function confirmDelete($tenantId)
    {

        if (! checkPermission('admin.tenants.delete')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect(route('admin.dashboard'));
        }

        $this->tenantId = $tenantId;

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($tenantId);
        $this->tenantHasSubscription = $tenant->subscriptions()->exists();
        $this->deleteMode = $this->tenantHasSubscription ? 'after_subscription' : 'instant';

        $this->confirmingDeletion = true;
    }

    public function delete()
    {
        if (! checkPermission('admin.tenants.delete')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->findOrFail($this->tenantId);

        // Prevent self-deletion
        $adminUser = $tenant->adminUser;
        if ($adminUser && $adminUser->id == Auth::id()) {
            $this->notify(['type' => 'warning', 'message' => t('cannot_delete_your_own_tenant')]);
            $this->confirmingDeletion = false;

            return;
        }

        try {
            $deletionService = app(TenantDeletionService::class);

            if ($this->deleteMode === 'instant') {
                $deletionService->deleteAllTenantData($tenant);
                $this->notify(['type' => 'success', 'message' => t('tenant_deleted_instantly')]);
            } else {
                $success = $deletionService->markTenantForDeletion($tenant);

                if ($success) {
                    $this->notify(['type' => 'success', 'message' => t('tenant_marked_for_deletion_successfully')]);
                } else {
                    $this->notify(['type' => 'danger', 'message' => t('tenant_deletion_failed')]);
                }
            }
        } catch (\Exception $e) {
            $this->notify(['type' => 'danger', 'message' => t('tenant_deletion_failed')]);
        }

        $this->confirmingDeletion = false;
        $this->dispatch('tenant-table-refresh');
    }

    public function confirmTenantRegistration($tenantId)
    {
        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->notify(['type' => 'danger', 'message' => t('tenant_not_found')]);

            return;
        }

        // Update email_verified_at on the related user
        $user = User::where('tenant_id', $tenant->id)->first();
        if ($user) {
            $user->email_verified_at = now();
            $user->save();
        }

        $this->notify(['type' => 'success', 'message' => t('tenant_verified_successfully')]);
        $this->dispatch('tenant-table-refresh');
    }

    public function restoreTenant($tenantId)
    {
        if (! checkPermission('admin.tenants.delete')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $tenant = Tenant::withoutGlobalScopes()->find($tenantId);

        if (! $tenant) {
            $this->notify(['type' => 'danger', 'message' => t('tenant_not_found')]);

            return;
        }

        try {
            $deletionService = app(TenantDeletionService::class);
            $success = $deletionService->restoreTenant($tenant);

            if ($success) {
                $this->notify(['type' => 'success', 'message' => t('tenant_restored_successfully')]);
            } else {
                $this->notify(['type' => 'danger', 'message' => t('tenant_restore_failed')]);
            }
        } catch (\Exception $e) {
            $this->notify(['type' => 'danger', 'message' => t('tenant_restore_failed')]);
        }

        $this->dispatch('tenant-table-refresh');
    }

    public function refreshTable()
    {
        $this->dispatch('tenant-table-refresh');
    }

    public function render()
    {
        return view('livewire.admin.tenant.tenant-list');
    }
}
