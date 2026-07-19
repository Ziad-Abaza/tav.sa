<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Multitenancy\TenantStatusService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DetermineTenantFromPath
{
    // Tenant prefix, if using consistent prefix pattern (http://domain.com/tenant/{tenant_name})
    protected $tenantPrefix = '';

    // Use tenant prefix pattern or direct pattern (http://domain.com/{tenant_name})
    protected $useTenantPrefix = false;

    public function __construct(
        protected TenantStatusService $statusService
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $this->useTenantPrefix = config('multitenancy.use_tenant_prefix', false);
        $this->tenantPrefix = config('multitenancy.tenant_prefix_name', '');
        // Get the actual request path
        $path = $request->path();

        // Get application base path from script name, if running in a subfolder
        $scriptName = $request->server->get('SCRIPT_NAME', '');
        $appBasePath = '';

        // Extract base path from script name (e.g., /waba from /waba/index.php)
        if ($scriptName && $scriptName !== '/index.php' && $scriptName !== 'index.php') {
            $directory = dirname($scriptName);
            if ($directory !== '/' && $directory !== '\\' && $directory !== '.') {
                $appBasePath = trim($directory, '/');
            }
        }

        // Remove base path from the beginning of the path if it exists
        if (! empty($appBasePath) && strpos($path, $appBasePath) === 0) {
            $path = substr($path, strlen($appBasePath));
            $path = trim($path, '/');
        }

        $segments = explode('/', $path);

        // Skip tenant resolution for reserved paths (read from config)
        $reservedPaths = config('restrictions.subdomains', []);

        // Check if we're in a prefix-based URL (tenant/{tenant_name}) or direct URL ({tenant_name})
        if (empty($segments[0])) {
            return $next($request);
        }

        $tenantIdentifier = null;
        $usedPrefix = false;

        // Check for tenant prefix pattern: /tenant/{tenant_name}
        if ($this->useTenantPrefix) {
            if ($segments[0] === $this->tenantPrefix && ! empty($segments[1])) {
                $tenantIdentifier = $segments[1];
                $usedPrefix = true;
            }
            // Check for direct pattern: /{tenant_name}
            elseif (! in_array($segments[0], $reservedPaths)) {
                $tenantIdentifier = $segments[0];
                $usedPrefix = false;
            }
        }

        // No tenant identified, continue with regular middleware chain
        if (is_null($tenantIdentifier)) {
            return $next($request);
        }

        // Find tenant using standardized cache key and TTL
        $tenant = Cache::remember("tenant:subdomain:{$tenantIdentifier}", now()->addMinutes(30), function () use ($tenantIdentifier) {
            return Tenant::where('subdomain', $tenantIdentifier)->first();
        });

        if ($tenant) {
            // Apply tenant status warning to session using centralized service
            $this->statusService->applyStatusToSession($tenant);

            // Make this tenant active - Spatie handles context management
            $tenant->makeCurrent();

            // Store subdomain in request attributes for easy access
            $request->attributes->set('subdomain', $tenantIdentifier);

            // Rewrite the URL, preserving the base path if it exists
            $this->rewriteRequestPath($request, $tenantIdentifier, $appBasePath, $usedPrefix);
        } else {
            // Either return 404 or redirect to not-found page
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Tenant not found'], 404);
            }

            return redirect()->route('tenant.not-found', ['subdomain' => $tenantIdentifier]);
        }

        return $next($request);
    }

    /**
     * Rewrites the request path to remove tenant prefix while preserving base path
     *
     * @param  Request  $request  The request instance
     * @param  string  $identifier  The tenant identifier to remove
     * @param  string  $appBasePath  The application base path
     * @param  bool  $usedPrefix  Whether the URL used the tenant prefix pattern
     */
    private function rewriteRequestPath(Request $request, string $identifier, string $appBasePath = '', bool $usedPrefix = false): void
    {
        $requestUri = $request->server->get('REQUEST_URI');

        // Build pattern considering base path and whether the tenant prefix was used
        $basePath = ! empty($appBasePath) ? $appBasePath.'/' : '';

        if ($usedPrefix) {
            // Pattern for /tenant/{tenant_name}/
            $pattern = "#^/({$basePath})?{$this->tenantPrefix}/{$identifier}(/|$)#";
        } else {
            // Pattern for /{tenant_name}/
            $pattern = "#^/({$basePath})?{$identifier}(/|$)#";
        }

        // The replacement preserves the base path if it exists
        $replacement = ! empty($appBasePath) ? "/{$appBasePath}/" : '/';

        if (! preg_match($pattern, $requestUri)) {
            return;
        }

        $newRequestUri = preg_replace($pattern, $replacement, $requestUri);

        $request->server->set('REQUEST_URI', $newRequestUri);

        if (method_exists($request, 'setPathInfo')) {
            $pathInfo = $request->getPathInfo();
            $newPathInfo = preg_replace($pattern, $replacement, $pathInfo);

            $request->setPathInfo($newPathInfo);
        }
    }
}
