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
                 
                "resources/js/jsbyNisma.js",
                "resources/js/app.js",
                "resources/js/tools.js",
            ],
            refresh: true,
        }),
    ],
});
