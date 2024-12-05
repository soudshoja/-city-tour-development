import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";

export default defineConfig({
    plugins: [
        laravel({
            input: [
                "resources/css/app.css",
                "resources/css/style.css",
                "resources/css/animate.css",
                "resources/js/dashboard.js",
                "resources/js/app.js",
                "resources/css/perfect-scrollbar.min.css", 
                "resources/css/cityCssByNisma.css",
                "resources/js/jsbyNisma.js",
            ],
            refresh: true,
        }),
    ],
});
