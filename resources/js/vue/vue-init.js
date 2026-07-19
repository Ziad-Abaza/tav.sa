import { createApp } from "vue";
import vSelect from "vue-select";
import "vue-select/dist/vue-select.css";

// Vue components
import BotFlowBuilder from "../components/BotFlowBuilder.vue";
import ThemeStyleSettings from "../theme/components/ThemeStyleSettings.vue";
import ThemeStyleSwatch from "../theme/components/ThemeStyleSwatch.vue";
import WhatsAppTemplateManager from "../dynamic-template/components/WhatsAppTemplateManager.vue";
import InitiateTemplate from "../initiate-template/InitiateTemplate.vue";
import TemplateBotManager from "../template-bot/TemplateBotManager.vue";
import InitiateChatTemplate from "../initiate-template/chat/InitiateChatTemplate.vue";
import CampaignManager from "../campaign/CampaignManager.vue";
import FBMessengerCampaignManager from "../fb-messenger-campaign/FBMessengerCampaignManager.vue";
import FacebookMessengerTemplate from "../facebook-messanger/FacebookMessengerTemplate.vue";

// DataTable initialization
import { initializeDataTables } from "./datatable-init.js";
// Store Vue app instances for dynamic mounting
const vueAppInstances = {};

// 🔹 Mount Initiate Template
window.mountInitiateTemplate = function () {
    const id = "initiate-template";
    const el = document.getElementById(id);
    if (!el || vueAppInstances[id]) return;
    const app = createApp({});
    app.component("v-select", vSelect);
    app.component("initiate-template", InitiateTemplate);
    app.mount(`#${id}`);

    vueAppInstances[id] = app;
};

// 🔹 Mount Initiate Chat Template
window.mountInitiateChatTemplate = function () {
    const id = "initiate-chat-template";
    const el = document.getElementById(id);
    if (!el || vueAppInstances[id]) return;
    const app = createApp({});
    app.component("v-select", vSelect);
    app.component("initiate-chat-template", InitiateChatTemplate);
    app.mount(`#${id}`);

    vueAppInstances[id] = app;
};
// Watch for modal opening and remount Vue
export function initializeVueApps() {
    const initApps = () => {
        // Bot Flow Builder
        if (
            document.getElementById("bot-flow-builder") &&
            !vueAppInstances["bot-flow-builder"]
        ) {
            const app = createApp({});
            app.component("v-select", vSelect);
            app.component("bot-flow-builder", BotFlowBuilder);
            app.mount("#bot-flow-builder");
            vueAppInstances["bot-flow-builder"] = app;
        }

        // WhatsApp Dynamic Templates
        if (
            document.getElementById("dynamic-templates") &&
            !vueAppInstances["dynamic-templates"]
        ) {
            const app = createApp({});
            app.component("v-select", vSelect);
            app.component("whatsapp-template-manager", WhatsAppTemplateManager);
            app.mount("#dynamic-templates");
            vueAppInstances["dynamic-templates"] = app;
        }

        // Theme Style App
        if (
            document.getElementById("theme-style-app") &&
            !vueAppInstances["theme-style-app"]
        ) {
            const app = createApp({});
            app.component("theme-style-settings", ThemeStyleSettings);
            app.component("theme-style-swatch", ThemeStyleSwatch);
            app.mount("#theme-style-app");
            vueAppInstances["theme-style-app"] = app;
        }
        // Template bot
        if (
            document.getElementById("template-bot") &&
            !vueAppInstances["template-bot"]
        ) {
            const app = createApp({});
            app.component("v-select", vSelect);
            app.component("template-bot", TemplateBotManager);
            app.mount("#template-bot");
            vueAppInstances["template-bot"] = app;
        }
        // InitiateTemplate
        if (
            document.getElementById("initiate-template") &&
            !vueAppInstances["initiate-template"]
        ) {
            const app = createApp({});
            app.component("v-select", vSelect);
            app.component("initiate-template", InitiateTemplate);
            app.mount("#initiate-template");
            vueAppInstances["initiate-template"] = app;
        }
        // Campaign Manager
        if (
            document.getElementById("campaign-manager") &&
            !vueAppInstances["campaign-manager"]
        ) {
            const app = createApp({});
            app.component("v-select", vSelect);
            app.component("campaign-manager", CampaignManager);
            app.mount("#campaign-manager");
            vueAppInstances["campaign-manager"] = app;
        }

        // FB Messenger Campaign Manager
        const fbCampaignManagerEl = document.getElementById("fb-messenger-campaign-manager");
        if (fbCampaignManagerEl && !vueAppInstances["fb-messenger-campaign-manager"]) {
            const app = createApp(FBMessengerCampaignManager, {
                campaignId: fbCampaignManagerEl.getAttribute("data-campaign-id") || null,
                tenantSubdomain: fbCampaignManagerEl.getAttribute("data-subdomain"),
                statusesData: fbCampaignManagerEl.getAttribute("data-statuses") || "[]",
                sourcesData: fbCampaignManagerEl.getAttribute("data-sources") || "[]",
                groupsData: fbCampaignManagerEl.getAttribute("data-groups") || "[]",
            });
            app.component("v-select", vSelect);
            app.mount("#fb-messenger-campaign-manager");
            vueAppInstances["fb-messenger-campaign-manager"] = app;
        }

            // Facebook Messenger Template
        if (
            document.getElementById("facebook-messenger-template") &&
            !vueAppInstances["facebook-messenger-template"]
        ) {
            const app = createApp({});
            app.component("v-select", vSelect);
            app.component("facebook-messenger-template", FacebookMessengerTemplate);
            app.mount("#facebook-messenger-template");
            vueAppInstances["facebook-messenger-template"] = app;
        }

        // Initialize DataTables
        initializeDataTables(vueAppInstances);
    };

    document.addEventListener("DOMContentLoaded", initApps);
    document.addEventListener("livewire:navigated", initApps);

    // Also try to initialize immediately in case DOMContentLoaded already fired
    if (document.readyState === "loading") {
        // Still loading, wait for DOMContentLoaded
    } else {
        // DOM already loaded, initialize now
        initApps();
    }
}
