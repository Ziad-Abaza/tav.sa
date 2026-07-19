<?php

use App\Http\Controllers\Tenant\Api\Table\activity_log\ActivityLogsController;
use App\Http\Controllers\Tenant\Api\Table\ai_prompt\AiPromptController;
use App\Http\Controllers\Tenant\Api\Table\bot_flows\BotFlowController;
use App\Http\Controllers\Tenant\Api\Table\campaigns\CampaignDetailController;
use App\Http\Controllers\Tenant\Api\Table\campaigns\CampaignExecutedController;
use App\Http\Controllers\Tenant\Api\Table\campaigns\CampaignsController;
use App\Http\Controllers\Tenant\Api\Table\canned_reply\CannedReplyController;
use App\Http\Controllers\Tenant\Api\Table\contacts\ContactController;
use App\Http\Controllers\Tenant\Api\Table\contacts\ContactImportController;
use App\Http\Controllers\Tenant\Api\Table\customFields\CustomFieldsController;
use App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FacebookMessengerTemplateController;
use App\Http\Controllers\Tenant\Api\Table\group\GroupController;
use App\Http\Controllers\Tenant\Api\Table\invoices\InvoiceController;
use App\Http\Controllers\Tenant\Api\Table\languages\TenantLangaugeController;
use App\Http\Controllers\Tenant\Api\Table\languages\TenantLanguageLineController;
use App\Http\Controllers\Tenant\Api\Table\message_bot\MessageBotController;
use App\Http\Controllers\Tenant\Api\Table\role\RoleController;
use App\Http\Controllers\Tenant\Api\Table\role\TenantRoleAssigneeController;
use App\Http\Controllers\Tenant\Api\Table\source\SourceController;
use App\Http\Controllers\Tenant\Api\Table\staffs\StaffController;
use App\Http\Controllers\Tenant\Api\Table\status\StatusController;
use App\Http\Controllers\Tenant\Api\Table\TableSettingsController;
use App\Http\Controllers\Tenant\Api\Table\template_bots\TemplateBotsController;
use App\Http\Controllers\Tenant\Api\Table\whatsapp_template\WhatsappTemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DataTable API Routes
|--------------------------------------------------------------------------
|
| These routes handle all DataTable related API endpoints for tenant.
| They are automatically scoped to the current tenant.
|
*/

Route::prefix('api')->name('api.')->group(function () {
    // Table column settings — resource slug maps to BaseTable subclass
    Route::get('/table/{resource}/settings', TableSettingsController::class)
        ->name('table.settings');

    Route::get('/contacts', [ContactController::class, 'index'])
        ->name('contacts.index');
    Route::get('/contacts/export', [ContactController::class, 'export'])
        ->name('contacts.export');
    Route::get('/contact-filters', [ContactController::class, 'filters'])
        ->name('contacts.filters');
    Route::patch('/contacts/{contact}/toggle-enabled', [ContactController::class, 'toggleEnabled'])
        ->name('contacts.toggle-enabled');
    Route::patch('/contacts/{contact}/toggle-opted-out', [ContactController::class, 'toggleOptedOut'])
        ->name('contacts.toggle-opted-out');

    // export must come BEFORE any {id} wildcard routes
    Route::get('/bot-flows', [BotFlowController::class, 'index'])
        ->name('bot-flows.index');
    Route::get('/bot-flows/export', [BotFlowController::class, 'export'])
        ->name('bot-flows.export');
    Route::patch('/bot-flows/{botFlow}/toggle-active', [BotFlowController::class, 'toggleActive'])
        ->name('bot-flows.toggle-active');

    // Invoices
    Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');

    // AI-Prompt
    Route::get('/ai-prompt', [AiPromptController::class, 'index'])->name('ai-prompt.index');

    // Whatsapp Template
    Route::get('/whatsapp-template', [WhatsappTemplateController::class, 'index'])->name('whatsapp-template.index');
    Route::get('/whatsapp-template-filters', [WhatsappTemplateController::class, 'filters'])->name('whatsapp-template.filters');
    Route::delete('/whatsapp-template/{template}', [WhatsappTemplateController::class, 'destroy'])->name('whatsapp-template.delete');

    // Canned Reply
    Route::get('/canned-reply', [CannedReplyController::class, 'index']);
    Route::patch('/canned-reply/{cannedReply}/toggle-public', [CannedReplyController::class, 'togglePublic'])->name('form.toggle-public');

    // Main Invoices
    Route::get('/maininvoices', [InvoiceController::class, 'maininvoices'])->name('maininvoices.index');

    // custom field
    Route::get('/custom-fields', [CustomFieldsController::class, 'index'])->name('custom-fields.index');
    Route::patch('/custom-fields/{customField}/toggle-active', [CustomFieldsController::class, 'toggleActive'])->name('custom-fields.toggle-active');
    Route::patch('/custom-fields/{customField}/toggle-required', [CustomFieldsController::class, 'toggleRequired'])->name('custom-fields.toggle-required');
    Route::patch('/custom-fields/{customField}/toggle-show-on-table', [CustomFieldsController::class, 'toggleShowOnTable'])->name('custom-fields.toggle-show-on-table');

    // Tenant Languages
    Route::get('/tenant-languages', [TenantLangaugeController::class, 'index'])->name('tenant-languages.index');

    // Tenant Language lines
    Route::get('/tenant-language-lines', [TenantLanguageLineController::class, 'index'])->name('tenant-language-lines.index');
    Route::patch('/tenant-language-lines/{key}/field', [TenantLanguageLineController::class, 'updateField'])->name('tenant-language-lines.update-field');
    Route::get('/tenant-language-lines/export', [TenantLanguageLineController::class, 'export'])->name('tenant-language-lines.export');

    // Role
    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/role-assignees', [TenantRoleAssigneeController::class, 'index'])->name('role-assignees.index');

    // status
    Route::get('/status', [StatusController::class, 'index'])->name('status-index');

    // source
    Route::get('/source', [SourceController::class, 'index'])->name('source-index');

    // group
    Route::get('/group', [GroupController::class, 'index'])->name('group-index');

    // Staff
    Route::get('/staffs', [StaffController::class, 'index'])->name('staffs.index');
    Route::patch('/staff/{staff}/toggle-active', [StaffController::class, 'toggleActive'])->name('staff.toggle-active');

    // template bots
    Route::get('/template-bots', [TemplateBotsController::class, 'index'])->name('template-bots.index');
    Route::patch('/template-bots/{templateBot}/toggle-active', [TemplateBotsController::class, 'toggleActive'])->name('template-bots.toggle-active');

    // Campaigns
    Route::get('/campaigns', [CampaignsController::class, 'index'])->name('campaigns.index');
    Route::get('/campaign-filters', [CampaignsController::class, 'filters'])->name('campaigns.filters');

    // activity logs
    Route::get('/activity-logs', [ActivityLogsController::class, 'index'])->name('activity-logs.index');

    // campaign detail
    Route::get('/campaign-details', [CampaignDetailController::class, 'index'])->name('campaign-details.index');
    Route::get('/campaign-details/export', [CampaignDetailController::class, 'export'])->name('campaign-details.export');

    // campaign executed
    Route::get('/campaign-executed', [CampaignExecutedController::class, 'index'])->name('campaign-executed.index');
    Route::get('/campaign-executed/export', [CampaignExecutedController::class, 'export'])->name('campaign-executed.export');

    // message bot
    Route::get('/message-bot', [MessageBotController::class, 'index'])->name('message-bot.index');
    Route::patch('/message-bot/{message_bot}/toggle-active', [MessageBotController::class, 'toggleActive'])->name('message-bot.toggle-active');
    Route::get('/message-bot-filters', [MessageBotController::class, 'filters'])->name('message-bot.filters');

    // contact import
    Route::get('/contact-imports', [ContactImportController::class, 'index'])->name('contact-imports.index');

    // Facebook Messenger Templates
    Route::get('/facebook-messenger-templates', [FacebookMessengerTemplateController::class, 'index'])
        ->name('facebook-messenger-templates.index');
    Route::get('/facebook-messenger-template-filters', [FacebookMessengerTemplateController::class, 'filters'])
        ->name('facebook-messenger-templates.filters');
    Route::delete('/facebook-messenger-templates/{id}', [FacebookMessengerTemplateController::class, 'destroy'])
        ->name('facebook-messenger-templates.destroy');
    Route::patch('/facebook-messenger-templates/{id}/toggle-active', [FacebookMessengerTemplateController::class, 'toggleActive'])
        ->name('facebook-messenger-templates.toggle-active');

    // Facebook Messenger Campaigns
    Route::get('/fb-messenger-campaigns', [\App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FacebookMessengerCampaignController::class, 'index'])
        ->name('fb-messenger-campaigns.index');
    Route::get('/fb-messenger-campaign-filters', [\App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FacebookMessengerCampaignController::class, 'filters'])
        ->name('fb-messenger-campaigns.filters');
    Route::delete('/fb-messenger-campaigns/{id}', [\App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FacebookMessengerCampaignController::class, 'destroy'])
        ->name('fb-messenger-campaigns.destroy');
    Route::patch('/fb-messenger-campaigns/{id}/toggle-pause', [\App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FacebookMessengerCampaignController::class, 'togglePause'])
        ->name('fb-messenger-campaigns.toggle-pause');

    // FB Messenger Campaign Details DataTable
    Route::get('/fb-campaign-details', [\App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FbMessengerCampaignDetailController::class, 'index'])
        ->name('fb-campaign-details.index');
    Route::get('/fb-campaign-details/filters', [\App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FbMessengerCampaignDetailController::class, 'filters'])
        ->name('fb-campaign-details.filters');
    Route::get('/fb-campaign-details/export', [\App\Http\Controllers\Tenant\Api\Table\facebookmessenger\FbMessengerCampaignDetailController::class, 'export'])
        ->name('fb-campaign-details.export');
});
