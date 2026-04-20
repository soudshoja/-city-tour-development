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

                //component
                "resources/css/component/ajax-searchable.css",

                //setting
                "resources/css/settings/main.css",
                "resources/css/settings/index.css",
                "resources/css/settings/agent-loss.css",
                "resources/css/settings/notification.css",

                //system setting
                "resources/css/system-setting/main.css",
                "resources/css/system-setting/hotel.css",

                //client
                "resources/css/client/show.css",

                "resources/css/refund.css",
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
