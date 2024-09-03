import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'white-light': '#f5f5f5', // Custom color for bg-white-light
                'dark': '#1f1f1f', // Set the dark color to #1f1f1f
            },
            backgroundColor: {
                'dark': '#1f1f1f', // Replace the existing dark background color
            },
            textColor: {
                'dark': '#1f1f1f', // Replace the existing dark text color
            },
        },
    },

    darkMode: 'class', // Enable dark mode using class
    plugins: [forms],
};
