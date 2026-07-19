<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Multitenancy\TenantStatusService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureTenantSecurity
{
    public function __construct(
        protected TenantStatusService $statusService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip if no tenant or no authenticated user
        if (! Tenant::checkCurrent() || ! Auth::check()) {
            return response()->view('errors.404', [], 404);
        }

        $user = Auth::user();
        $tenant = Tenant::current();

        // Check if user belongs to current tenant or is super admin
        if ($user->tenant_id !== $tenant->id && ! $user->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if tenant is active using centralized status service
        if (! $this->statusService->isAccessible($tenant)) {
            $warning = $this->statusService->getStatusWarning($tenant);

            session()->put('tenant_status_warning', $warning);
            session()->flash('notification', [
                'type' => 'warning',
                'message' => $warning['message'].' You can still create support tickets.',
            ]);

            return redirect()->to(tenant_route('tenant.tickets.index'));
        }

        session()->forget('tenant_status_warning');

        return $next($request);
    }
}
