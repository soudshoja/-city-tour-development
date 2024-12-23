import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/cityCssByNisma.css",
                "resources/css/style.css",
                "resources/js/jsbyNisma.js",
                "resources/js/app.js",
            ],
            refresh: true,
        }),
    ],
});
