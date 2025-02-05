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
                koromiko: {
                    50: "#fff8ed",
                    100: "#fff0d4",
                    200: "#ffdea9",
                    300: "#ffbe60",
                    400: "#fea239",
                    500: "#fc8513",
                    600: "#ed6909",
                    700: "#c54f09",
                    800: "#9c3e10",
                    900: "#7e3510",
                    950: "#441906",
                },
            },
            backgroundColor: {
                dark: "#000",
            },
            textColor: {
                dark: "#000",
            },
            zIndex: {
                60: 60,
            },
            // width: {
            //     120: "30rem",
            //     160: "40rem",
            // },
            // maxWidth: {
            //     120: "30rem",
            //     160: "40rem",
            // },
            spacing: {
                18: "4.5rem",
                120: "30rem",
                160: "40rem",
                "near-full": "80%",
            },
        },
    },

    darkMode: "class",
    plugins: [forms],
};
