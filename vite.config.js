import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/guest.css",

                //task
                "resources/css/task/index.css",

                //components
                "resources/css/component/ajax-searchable.css",

                "resources/css/refund.css",
                "resources/css/settings/main.css",
                "resources/css/settings/index.css",
                "resources/css/settings/agent-loss.css",
                "resources/css/settings/notification.css",
                "resources/css/system-setting/main.css",
                "resources/css/system-setting/hotel.css",
                "resources/css/lock-management/index.css",
                "resources/css/payment-link/index.css",
                "resources/js/jsbyNisma.js",
                "resources/js/app.js",
                "resources/js/tools.js",
                "resources/css/agent/index.css",
            ],
            refresh: true,
        }),
    ],
});
