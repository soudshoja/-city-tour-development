import defaultTheme from "tailwindcss/defaultTheme";
import forms from "@tailwindcss/forms";

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ["Figtree", ...defaultTheme.fontFamily.sans],
            },
            colors: {
                "white-light": "#f5f5f5",
                dark: "#000",
                primary: "#2945A2",
            },
            backgroundColor: {
                dark: "#000",
            },
            textColor: {
                dark: "#000",
            },
            zIndex: {
                60: 60
            },
            width: {
                120: "30rem",
                160: "40rem",
            },
            maxWidth: {
                120: "30rem",
                160: "40rem",
            },
            height: {
                120: "30rem",
                160: "40rem",
                'near-full' : "80%",
            },
            maxHeight: {
                120: "30rem",
                160: "40rem",
                'near-full' : "80%",
            },
        },
    },

    darkMode: "class",
    plugins: [forms],
};
