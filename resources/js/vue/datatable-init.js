import { createApp } from "vue";
import ContactDataTable from "../datatable/tenant/contact/ContactDataTable.vue";
import BotFlowDataTable from "../datatable/tenant/bot_flow/BotFlowDataTable.vue";
import InvoiceDataTable from "../datatable/tenant/invoice/InvoiceDataTable.vue";
import CustomFieldDataTable from "../datatable/tenant/custom_field/CustomFieldDataTable.vue";
import TenantLanguageDatatable from "../datatable/tenant/language/TenantLanguageDatatable.vue";
import TenantDataTable from "../datatable/admin/tenants/TenantDataTable.vue";
import MainInvoiceDataTable from "../datatable/tenant/invoice/MainInvoiceDataTable.vue";
import TenantLanguageLineDataTable from "../datatable/tenant/language/TenantLanguageLineDataTable.vue";
import StatusDataTable from "../datatable/tenant/status/StatusDataTable.vue";
import SourceDataTable from "../datatable/tenant/source/SourceDataTable.vue";
import GroupDataTable from "../datatable/tenant/group/GroupDataTable.vue";
import RoleDataTable from "../datatable/tenant/role/RoleDataTable.vue";
import StaffDataTable from "../datatable/tenant/staff/StaffDataTable.vue";
import RoleAssigneeDataTable from "../datatable/tenant/role/RoleAssigneeDataTable.vue";
import TenantTicketDataTable from "../datatable/tenant/ticket/TenantTicketDataTable.vue";
import SubscriptionDataTable from "../datatable/admin/subscription/SubscriptionDataTable.vue";
import TemplateBotDataTable from "../datatable/tenant/template_bot/TemplateBotDataTable.vue";
import AiPromptDataTable from "../datatable/tenant/ai_prompt/AiPromptDataTable.vue";
import CannedReplyDataTable from "../datatable/tenant/canned_reply/CannedReplyDataTable.vue";
import WhatsappTemplateDataTable from "../datatable/tenant/template/WhatsappTemplateDataTable.vue";
import CampaignDataTable from "../datatable/tenant/campaign/CampaignDataTable.vue";
import TaxDataTable from "../datatable/admin/Tax/TaxDataTable.vue";
import PageDataTable from "../datatable/admin/Pages/PageDataTable.vue";
import AdminInvoiceDataTable from "../datatable/admin/subscription/AdminInvoiceDataTable.vue";
import ActivityLogsDataTable from "../datatable/tenant/activity_log/ActivityLogsDataTable.vue";
import CampaignDetailDataTable from "../datatable/tenant/campaign/CampaignDetailDataTable.vue";
import CampaignExecutedDataTable from "../datatable/tenant/campaign/CampaignExecutedDataTable.vue";
import CreditDataTable from "../datatable/admin/credit_management/CreditDataTable.vue";
import TransactionDataTable from "../datatable/admin/transaction/TransactionDataTable.vue";
import AdminmaininvoiceDataTable from "../datatable/admin/invoice/AdminmaininvoiceDataTable.vue";
import MessageBotDataTable from "../datatable/tenant/message_bot/MessageBotDataTable.vue";
import DepartmentDataTable from "../datatable/admin/Department/DepartmentDataTable.vue";
import TicketsDataTable from "../datatable/admin/tickets/TicketsDataTable.vue";
import AdminLanguageDataTable from "../datatable/admin/language/AdminLanguageDataTable.vue";
import AdminRoleDataTable from "../datatable/admin/role/AdminRoleDataTable.vue";
import UserDataTable from "../datatable/admin/user/UserDataTable.vue";
import AdminLanguageLineDataTable from "../datatable/admin/language/AdminLanguageLineDataTable.vue";
import CurrencyDataTable from "../datatable/admin/Currency/CurrencyDataTable.vue";
import CouponDataTable from "../datatable/admin/Coupon/CouponDataTable.vue";
import ContactImportDataTable from "../datatable/tenant/contact/ContactImportDataTable.vue";
import AdminTenantLanguageDataTable from "../datatable/admin/language/AdminTenantLanguageDataTable.vue";
import AdminRoleAssigneeDataTable from "../datatable/admin/role/AdminRoleAssigneeDataTable.vue";
import FacebookMessengerTemplateDataTable from "../datatable/tenant/facebook_messenger/FacebookMessengerTemplateDataTable.vue";
import FacebookMessengerTemplateEditor from "../facebook-messanger/FacebookMessengerTemplateEditor.vue";
import FacebookMessengerCampaignDataTable from "../datatable/tenant/facebook_messenger/FacebookMessengerCampaignDataTable.vue";
import FbMessengerCampaignDetailDataTable from "../datatable/tenant/facebook_messenger/FbMessengerCampaignDetailDataTable.vue";

/**
 * Initialize all DataTable Vue components
 * @param {Object} vueAppInstances - Object to store Vue app instances
 */
export function initializeDataTables(vueAppInstances) {
    // Contact DataTable
    const contactDtEl = document.getElementById("contact-datatable");
    if (contactDtEl && !vueAppInstances["contact-datatable"]) {
        const app = createApp(ContactDataTable, {
            subdomain: contactDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#contact-datatable");
        vueAppInstances["contact-datatable"] = app;
    }

    // BotFlow DataTable
    const botFlowDtEl = document.getElementById("botflow-datatable");
    if (botFlowDtEl && !vueAppInstances["botflow-datatable"]) {
        const app = createApp(BotFlowDataTable, {
            subdomain: botFlowDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#botflow-datatable");
        vueAppInstances["botflow-datatable"] = app;
    }

    // Invoice DataTable
    const invoiceDtEl = document.getElementById("invoice-datatable");
    if (invoiceDtEl && !vueAppInstances["invoice-datatable"]) {
        const app = createApp(InvoiceDataTable, {
            subdomain: invoiceDtEl.getAttribute("data-subdomain"),
            subscriptionId: invoiceDtEl.getAttribute("data-subscription-id"),
        });
        app.mount("#invoice-datatable");
        vueAppInstances["invoice-datatable"] = app;
    }

    // AiPrompt DataTable
    const AiPromptDtEl = document.getElementById("aiprompt-datatable");
    if (AiPromptDtEl && !vueAppInstances["aiprompt-datatable"]) {
        const app = createApp(AiPromptDataTable, {
            subdomain: AiPromptDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#aiprompt-datatable");
        vueAppInstances["aiprompt-datatable"] = app;
    }

    // WhatsappTemplate DataTable
    const whatsappTemplateDtEl = document.getElementById("whatsapptemplate-datatable");
    if (whatsappTemplateDtEl && !vueAppInstances["whatsapptemplate-datatable"]) {
        const app = createApp(WhatsappTemplateDataTable, {
            subdomain: whatsappTemplateDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#whatsapptemplate-datatable");
        vueAppInstances["whatsapptemplate-datatable"] = app;
    }

    // Canned-reply DataTable
    const CannedReplyDtEl = document.getElementById("cannedreply-datatable");
    if (CannedReplyDtEl && !vueAppInstances["cannedreply-datatable"]) {
        const app = createApp(CannedReplyDataTable, {
            subdomain: CannedReplyDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#cannedreply-datatable");
        vueAppInstances["cannedreply-datatable"] = app;
    }

    // Admin Tenant DataTable (no subdomain — uses /admin/api base)
    const tenantDtEl = document.getElementById("admin-tenant-datatable");
    if (tenantDtEl && !vueAppInstances["admin-tenant-datatable"]) {
        const app = createApp(TenantDataTable);
        app.mount("#admin-tenant-datatable");
        vueAppInstances["admin-tenant-datatable"] = app;
    }

    // Role DataTable
    const roleDtEl = document.getElementById("role-datatable");
    if (roleDtEl && !vueAppInstances["role-datatable"]) {
        const app = createApp(RoleDataTable, {
            subdomain: roleDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#role-datatable");
        vueAppInstances["role-datatable"] = app;
    }

    //invoice DataTabel
    const maininvoiceDtEl = document.getElementById("maininvoice-datatable");
    if (maininvoiceDtEl && !vueAppInstances["maininvoice-datatable"]) {
        const app = createApp(MainInvoiceDataTable, {
            subdomain: maininvoiceDtEl.getAttribute("data-subdomain"),
            subscriptionId: maininvoiceDtEl.getAttribute("data-subscription-id"),
        });
        app.mount("#maininvoice-datatable");
        vueAppInstances["maininvoice-datatable"] = app;
    }

    // TenantLanguage DataTable
    const tenantLanguageDtEl = document.getElementById("tenant-langauge-datatable");
    if (tenantLanguageDtEl && !vueAppInstances["tenant-langauge-datatable"]) {
        const app = createApp(TenantLanguageDatatable, {
            subdomain: tenantLanguageDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#tenant-langauge-datatable");
        vueAppInstances["tenant-langauge-datatable"] = app;
    }
    //status
    const StatusDtEl = document.getElementById("status-datatable");
    if (StatusDtEl && !vueAppInstances["status-datatable"]) {
        const app = createApp(StatusDataTable, {
            subdomain: StatusDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#status-datatable");
        vueAppInstances["status-datatable"] = app;
    }

    // Tenant Language lines DataTable
    const el = document.getElementById("tenant-language-lines-datatable");
    if (el && !vueAppInstances["tenant-language-lines-datatable"]) {
        const subdomain = el.dataset.subdomain;
        const languageCode = el.dataset.languageCode;

        const app = createApp(TenantLanguageLineDataTable, {
            subdomain,
            languageCode,
        });

        app.mount("#tenant-language-lines-datatable");
        vueAppInstances["tenant-language-lines-datatable"] = app;
    }

    // Customefield DataTable
    const customfieldDtEl = document.getElementById("customfield-datatable");
    if (customfieldDtEl && !vueAppInstances["customfield-datatable"]) {
        const app = createApp(CustomFieldDataTable, {
            subdomain: customfieldDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#customfield-datatable");
        vueAppInstances["customfield-datatable"] = app;
    }

    // Source DataTable
    const SourceDtEl = document.getElementById("source-datatable");
    if (SourceDtEl && !vueAppInstances["source-datatable"]) {
        const app = createApp(SourceDataTable, {
            subdomain: SourceDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#source-datatable");
        vueAppInstances["source-datatable"] = app;
    }
    // Staff DataTable
    const staffDtEl = document.getElementById("staff-datatable");
    if (staffDtEl && !vueAppInstances["staff-datatable"]) {
        const app = createApp(StaffDataTable, {
            subdomain: staffDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#staff-datatable");
        vueAppInstances["staff-datatable"] = app;
    }

    //Group DataTable
    const GroupDtEl = document.getElementById("group-datatable");
    if (GroupDtEl && !vueAppInstances["group-datatable"]) {
        const app = createApp(GroupDataTable, {
            subdomain: GroupDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#group-datatable");
        vueAppInstances["group-datatable"] = app;
    }

    // Tenant RoleAssignee DataTable
    const rl = document.getElementById("role-assignee-datatable");
    if (rl && !vueAppInstances["role-assignee-datatable"]) {
        const subdomain = rl.dataset.subdomain;
        const roleId = rl.dataset.roleId;

        const app = createApp(RoleAssigneeDataTable, {
            subdomain,
            roleId,
        });
        app.mount("#role-assignee-datatable");
        vueAppInstances["role-assignee-datatable"] = app;
    }

    // Tenant Ticket DataTable
    const tenantTicketDtEl = document.getElementById("tenant-ticket-datatable");
    if (tenantTicketDtEl && !vueAppInstances["tenant-ticket-datatable"]) {
        const app = createApp(TenantTicketDataTable, {
            subdomain: tenantTicketDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#tenant-ticket-datatable");
        vueAppInstances["tenant-ticket-datatable"] = app;
    }

    // Admin Subscription DataTable (no subdomain — uses /admin/api base)
    const subscriptionDtEl = document.getElementById("admin-subscription-datatable");
    if (subscriptionDtEl && !vueAppInstances["admin-subscription-datatable"]) {
        const app = createApp(SubscriptionDataTable);
        app.mount("#admin-subscription-datatable");
        vueAppInstances["admin-subscription-datatable"] = app;
    }

    // Admin Invoice DataTable
    const admininvoiceDtEl = document.getElementById("admin-invoice-datatable");
    if (admininvoiceDtEl && !vueAppInstances["admin-invoice-datatable"]) {
        const app = createApp(AdminInvoiceDataTable, {
            subscriptionId: admininvoiceDtEl.getAttribute("data-subscription-id"),
        });
        app.mount("#admin-invoice-datatable");
        vueAppInstances["admin-invoice-datatable"] = app;
    }

    // Template bot DataTable
    const templatebotsDtEl = document.getElementById("template-bot-datatable");
    if (templatebotsDtEl && !vueAppInstances["template-bot-datatable"]) {
        const app = createApp(TemplateBotDataTable, {
            subdomain: templatebotsDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#template-bot-datatable");
        vueAppInstances["template-bot-datatable"] = app;
    }

    // Tax DataTable
    const taxDtEl = document.getElementById("tax-datatable");
    if (taxDtEl && !vueAppInstances["tax-datatable"]) {
        const app = createApp(TaxDataTable);
        app.mount("#tax-datatable");
        vueAppInstances["tax-datatable"] = app;

    }

    // Page DataTable
    const pageDtEl = document.getElementById("pages-datatable");
    if (pageDtEl && !vueAppInstances["pages-datatable"]) {
        const app = createApp(PageDataTable);
        app.mount("#pages-datatable");
        vueAppInstances["pages-datatable"] = app;

    }

    // CreditManagement DataTable
    const creditDtEl = document.getElementById("credit-datatable");
    if (creditDtEl && !vueAppInstances["credit-datatable"]) {
        const app = createApp(CreditDataTable);
        app.mount("#credit-datatable");
        vueAppInstances["credit-datatable"] = app;

    }

    // Transaction DataTable
    const transactionDtEl = document.getElementById("transaction-datatable");
    if (transactionDtEl && !vueAppInstances["transaction-datatable"]) {
        const app = createApp(TransactionDataTable);
        app.mount("#transaction-datatable");
        vueAppInstances["transaction-datatable"] = app;

    }

    // AdminmainInvoice DataTable
    const adminmaininvoiceDtEl = document.getElementById("adminmaininvoice-datatable");
    if (adminmaininvoiceDtEl && !vueAppInstances["adminmaininvoice-datatable"]) {
        const app = createApp(AdminmaininvoiceDataTable);
        app.mount("#adminmaininvoice-datatable");
        vueAppInstances["adminmaininvoice-datatable"] = app;

    }

    // Campaigns datatable
    const campaignsDtEl = document.getElementById("campaigns-datatable");
    if (campaignsDtEl && !vueAppInstances["campaigns-datatable"]) {
        const app = createApp(CampaignDataTable, {
            subdomain: campaignsDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#campaigns-datatable");
        vueAppInstances["campaigns-datatable"] = app;
    }

    // MessageBot DataTable
    const MessageBotDtEl = document.getElementById("messagebot-datatable");
    if (MessageBotDtEl && !vueAppInstances["messagebot-datatable"]) {
        const app = createApp(MessageBotDataTable, {
            subdomain: MessageBotDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#messagebot-datatable");
        vueAppInstances["messagebot-datatable"] = app;
    }

    //Department DataTable
    const DepartmentDtEl = document.getElementById("department-datatable");
    if (DepartmentDtEl && !vueAppInstances["department-datatable"]) {
        const app = createApp(DepartmentDataTable);
        app.mount("#department-datatable");
        vueAppInstances["department-datatable"] = app;
    }

    //ActivityLogs DataTable
    const ActivitylogsDtEl = document.getElementById("activity-logs-datatable");
    if (ActivitylogsDtEl && !vueAppInstances["activity-logs-datatable"]) {
        const app = createApp(ActivityLogsDataTable, {
            subdomain: ActivitylogsDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#activity-logs-datatable");
        vueAppInstances["activity-logs-datatable"] = app;
    }

    // campaign details table
    const cd = document.getElementById("campaign-details-datatable");
    if (cd && !vueAppInstances["campaign-details-datatable"]) {
        const subdomain = cd.dataset.subdomain;
        const campaignId = cd.dataset.campaignId;

        const app = createApp(CampaignDetailDataTable, {
            subdomain,
            campaignId,
        });
        app.mount("#campaign-details-datatable");
        vueAppInstances["campaign-details-datatable"] = app;
    }

    //campaign executed table
    const ce = document.getElementById("campaign-executed-datatable");
    if (ce && !vueAppInstances["campaign-executed-datatable"]) {
        const subdomain = ce.dataset.subdomain;
        const campaignId = ce.dataset.campaignId;

        const app = createApp(CampaignExecutedDataTable, {
            subdomain,
            campaignId,
        });
        app.mount("#campaign-executed-datatable");
        vueAppInstances["campaign-executed-datatable"] = app;
    }


    // admin tickets
    const ticketsDtEl = document.getElementById("tickets-datatable");
    if (ticketsDtEl && !vueAppInstances["tickets-datatable"]) {
        const app = createApp(TicketsDataTable);
        app.mount("#tickets-datatable");
        vueAppInstances["tickets-datatable"] = app;
    }

    // Admin Language DataTable (
    const adminLanguageDtEl = document.getElementById("admin-languages-datatable");
    if (adminLanguageDtEl && !vueAppInstances["admin-languages-datatable"]) {
        const app = createApp(AdminLanguageDataTable);
        app.mount("#admin-languages-datatable");
        vueAppInstances["admin-languages-datatable"] = app;
    }

    // Admin Tenant Language DataTable 
    const adminTenantLanguageDtEl = document.getElementById("admin-tenant-languages-datatable");
    if (adminTenantLanguageDtEl && !vueAppInstances["admin-tenant-languages-datatable"]) {
        const app = createApp(AdminTenantLanguageDataTable);
        app.mount("#admin-tenant-languages-datatable");
        vueAppInstances["admin-tenant-languages-datatable"] = app;
    }

    //Currency
    const CurrencyDtEl = document.getElementById("currency-datatable");
    if (CurrencyDtEl && !vueAppInstances["currency-datatable"]) {
        const app = createApp(CurrencyDataTable);
        app.mount("#currency-datatable");
        vueAppInstances["currency-datatable"] = app;
    }

    // Admin Role DataTable
    const AroleDtEl = document.getElementById("admin-roles-datatable");
    if (AroleDtEl && !vueAppInstances["admin-roles-datatable"]) {
        const app = createApp(AdminRoleDataTable);
        app.mount("#admin-roles-datatable");
        vueAppInstances["admin-roles-datatable"] = app;

    }

    // Admin Role Assignee DataTable
    const arl = document.getElementById("admin-role-assignees-datatable");
    if (arl && !vueAppInstances["admin-role-assignees-datatable"]) {
        const roleId = arl.dataset.roleId;
        const app = createApp(AdminRoleAssigneeDataTable, {

            roleId,
        });
        app.mount("#admin-role-assignees-datatable");
        vueAppInstances["admin-role-assignees-datatable"] = app;
    }

    // Admin User DataTable
    const userDtEl = document.getElementById("users-datatable");
    if (userDtEl && !vueAppInstances["users-datatable"]) {
        const app = createApp(UserDataTable);
        app.mount("#users-datatable");
        vueAppInstances["users-datatable"] = app;
    }

    // Admin Language lines DataTable
    const tl = document.getElementById("admin-language-lines-datatable");
    if (tl && !vueAppInstances["admin-language-lines-datatable"]) {
        const languageCode = tl.dataset.languageCode;
        const app = createApp(AdminLanguageLineDataTable, {
            languageCode,
        });
        app.mount("#admin-language-lines-datatable");
        vueAppInstances["admin-language-lines-datatable"] = app;
    }

    //Coupon DataTable
    const CouponDtEl = document.getElementById("coupon-datatable");
    if (CouponDtEl && !vueAppInstances["coupon-datatable"]) {
        const app = createApp(CouponDataTable);
        app.mount("#coupon-datatable");
        vueAppInstances["coupon-datatable"] = app;
    }

    // contact imports
    const contactImportsDtEl = document.getElementById("contact-imports-datatable");
    if (contactImportsDtEl && !vueAppInstances["contact-imports-datatable"]) {
        const app = createApp(ContactImportDataTable, {
            subdomain: contactImportsDtEl.getAttribute("data-subdomain"),
        });
        app.mount("#contact-imports-datatable");
        vueAppInstances["contact-imports-datatable"] = app;
    }
 
    // Facebook Messenger Template DataTable
    const fbTemplateEl = document.getElementById("fb-messenger-template-datatable");
    if (fbTemplateEl && !vueAppInstances["fb-messenger-template-datatable"]) {
        const app = createApp(FacebookMessengerTemplateDataTable, {
            subdomain: fbTemplateEl.getAttribute("data-subdomain"),
        });
        app.mount("#fb-messenger-template-datatable");
        vueAppInstances["fb-messenger-template-datatable"] = app;
    }

    // Facebook Messenger Template Create/Edit page
    const fbTemplateCreateEl = document.getElementById("fb-messenger-template-create");
    if (fbTemplateCreateEl && !vueAppInstances["fb-messenger-template-create"]) {
        const app = createApp(FacebookMessengerTemplateEditor, {
            subdomain: fbTemplateCreateEl.getAttribute("data-subdomain"),
            template: window.fbTemplateData ?? null,
        });
        app.mount("#fb-messenger-template-create");
        vueAppInstances["fb-messenger-template-create"] = app;
    }

    // Facebook Messenger Campaign DataTable
    const fbCampaignEl = document.getElementById("fb-messenger-campaign-datatable");
    if (fbCampaignEl && !vueAppInstances["fb-messenger-campaign-datatable"]) {
        const app = createApp(FacebookMessengerCampaignDataTable, {
            subdomain: fbCampaignEl.getAttribute("data-subdomain"),
        });
        app.mount("#fb-messenger-campaign-datatable");
        vueAppInstances["fb-messenger-campaign-datatable"] = app;
    }

    // FB Messenger Campaign Detail DataTable
    const fbCampaignDetailEl = document.getElementById("fb-campaign-details-datatable");
    if (fbCampaignDetailEl && !vueAppInstances["fb-campaign-details-datatable"]) {
        const app = createApp(FbMessengerCampaignDetailDataTable, {
            subdomain: fbCampaignDetailEl.getAttribute("data-subdomain"),
            campaignId: fbCampaignDetailEl.getAttribute("data-campaign-id"),
        });
        app.mount("#fb-campaign-details-datatable");
        vueAppInstances["fb-campaign-details-datatable"] = app;
    }
}
