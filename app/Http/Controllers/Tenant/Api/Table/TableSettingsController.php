<?php

namespace App\Http\Controllers\Tenant\Api\Table;

use App\Http\Controllers\Controller;
use App\Table\FacebookMessengerTemplatesTable;
use App\Table\Tenant\ActivityLogsTable;
use App\Table\Tenant\AiPromptTable;
use App\Table\Tenant\BotFlowsTable;
use App\Table\Tenant\CampaignDetailTable;
use App\Table\Tenant\CampaignExecutedTable;
use App\Table\Tenant\CampaignsTable;
use App\Table\Tenant\CannedReplyTable;
use App\Table\Tenant\ContactImportTable;
use App\Table\Tenant\ContactsTable;
use App\Table\Tenant\CustomFieldsTable;
use App\Table\Tenant\FbCampaignDetailsTable;
use App\Table\Tenant\FbMessengerCampaignsTable;
use App\Table\Tenant\GroupTable;
use App\Table\Tenant\InvoiceTable;
use App\Table\Tenant\MainInvoiceTable;
use App\Table\Tenant\MessageBotTable;
use App\Table\Tenant\RoleTable;
use App\Table\Tenant\SourceTable;
use App\Table\Tenant\StaffTable;
use App\Table\Tenant\StatusTable;
use App\Table\Tenant\TemplateBotsTable;
use App\Table\Tenant\TenantLanguageLineTable;
use App\Table\Tenant\TenantLanguageTable;
use App\Table\Tenant\TenantRoleAssigneeTable;
use App\Table\Tenant\WhatsappTemplateTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableSettingsController extends Controller
{
    /**
     * Maps URL resource slugs to their BaseTable class.
     * Add new resources here — no other file needs to change.
     *
     * @var array<string, class-string>
     */
    private array $registry = [
        'contacts' => ContactsTable::class,
        'bot-flows' => BotFlowsTable::class,
        'ai-prompt' => AiPromptTable::class,
        'canned-reply' => CannedReplyTable::class,
        'whatsapp-template' => WhatsappTemplateTable::class,
        'message-bot' => MessageBotTable::class,
        'invoices' => InvoiceTable::class,
        'roles' => RoleTable::class,
        'maininvoices' => MainInvoiceTable::class,
        'tenant-languages' => TenantLanguageTable::class,
        'custom-fields' => CustomFieldsTable::class,
        'tenant-language-lines' => TenantLanguageLineTable::class,
        'status' => StatusTable::class,
        'source' => SourceTable::class,
        'staffs' => StaffTable::class,
        'group' => GroupTable::class,
        'role-assignees' => TenantRoleAssigneeTable::class,
        'template-bots' => TemplateBotsTable::class,
        'campaigns' => CampaignsTable::class,
        'campaign-details' => CampaignDetailTable::class,
        'campaign-executed' => CampaignExecutedTable::class,
        'activity-logs' => ActivityLogsTable::class,
        'contact-imports' => ContactImportTable::class,
        'facebook-messenger-templates' => FacebookMessengerTemplatesTable::class,
        'fb-messenger-campaigns' => FbMessengerCampaignsTable::class,
        'fb-campaign-details' => FbCampaignDetailsTable::class,
    ];

    public function __invoke(Request $request, string $subdomain, string $resource): JsonResponse
    {
        $registry = apply_filters('table_settings_register', $this->registry);

        if (! isset($registry[$resource])) {
            return response()->json(['message' => 'Unknown resource'], 404);
        }

        $table = new $registry[$resource];

        return response()->json($table->settings());
    }
}
