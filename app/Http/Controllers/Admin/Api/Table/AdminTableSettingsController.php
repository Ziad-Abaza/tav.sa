<?php

namespace App\Http\Controllers\Admin\Api\Table;

use App\Http\Controllers\Controller;
use App\Table\Admin\AdminInvoiceTable;
use App\Table\Admin\AdminLanguageTable;
use App\Table\Admin\AdminmaininvoiceTable;
use App\Table\Admin\AdminRoleAssigneeTable;
use App\Table\Admin\AdminRoleTable;
use App\Table\Admin\AdminTenantLanguageTable;
use App\Table\Admin\CouponTable;
use App\Table\Admin\CreditTable;
use App\Table\Admin\CurrencyTable;
use App\Table\Admin\DepartmentTable;
use App\Table\Admin\languages\AdminLanguageLineTable;
use App\Table\Admin\PageTable;
use App\Table\Admin\SubscriptionTable;
use App\Table\Admin\TaxTable;
use App\Table\Admin\TenantsTable;
use App\Table\Admin\TransactionTable;
use App\Table\Admin\UserTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTableSettingsController extends Controller
{
    /**
     * Maps URL resource slugs to their BaseTable class.
     * Add new admin resources here — no other file needs to change.
     *
     * @var array<string, class-string>
     */
    private array $registry = [
        'tenants' => TenantsTable::class,
        'subscriptions' => SubscriptionTable::class,
        'tax' => TaxTable::class,
        'page' => PageTable::class,
        'invoices' => AdminInvoiceTable::class,
        'credit' => CreditTable::class,
        'maininvoice' => AdminmaininvoiceTable::class,
        'transaction' => TransactionTable::class,
        'department' => DepartmentTable::class,
        'admin-languages' => AdminLanguageTable::class,
        'admin-tenant-languages' => AdminTenantLanguageTable::class,
        'admin-roles' => AdminRoleTable::class,
        'admin-role-assignees' => AdminRoleAssigneeTable::class,
        'users' => UserTable::class,
        'admin-language-lines' => AdminLanguageLineTable::class,
        'currency' => CurrencyTable::class,
        'coupon' => CouponTable::class,
    ];

    public function __invoke(Request $request, string $resource): JsonResponse
    {
        $registry = apply_filters('admin_table_settings_register', $this->registry);

        if (! isset($registry[$resource])) {
            return response()->json(['message' => 'Unknown resource'], 404);
        }

        $table = new $registry[$resource];

        return response()->json($table->settings());
    }
}
