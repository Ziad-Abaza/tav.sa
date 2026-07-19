<?php

namespace Corbital\Installer\Http\Middleware;

use Closure;
use Corbital\Installer\Installer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNotInstalled
{
    /**
     * The installer instance.
     */
    protected Installer $installer;

    /**
     * Create a new middleware instance.
     */
    public function __construct(Installer $installer)
    {
        $this->installer = $installer;
    }

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip redirect for install routes and public test route
        if (
            $request->is(config('installer.routes.prefix').'/*') || $request->is(config('installer.routes.prefix'))
            || $request->is(patterns: 'pre-install')
        ) {
            return $next($request);
        }

        // If application is not installed, redirect to install page
        if (! $this->installer->isAppInstalled()) {
            return redirect()->to(config('installer.routes.prefix', 'install'));
        }

        do_action('app.middleware.redirect_if_not_installed', $request);

        return $next($request);
    }
}
