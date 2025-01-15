import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/cityCss.css",
                "resources/css/style.css",
                "resources/js/jsbyNisma.js",
                "resources/js/app.js",
                "resources/js/tools.js",
                "resources/js/nice-select2.js",
            ],
            refresh: true,
        }),
    ],
});
