<?php

namespace App\Multitenancy\Tasks;

/**
 * @deprecated Use SwitchTenantContextTask instead. This class was renamed because
 *             it doesn't actually switch databases - it sets MySQL session variables
 *             and container bindings for the current tenant context.
 * @see SwitchTenantContextTask
 */
class SwitchTenantDatabaseTask extends SwitchTenantContextTask
{
    // Extends the renamed class for backward compatibility.
    // Will be removed in a future version.
}
