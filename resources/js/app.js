import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('alpine:init', () => {
    Alpine.store('sidebar', {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    });
});




document.addEventListener('DOMContentLoaded', function () {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const lightModeIcon = document.getElementById('lightModeIcon');
    const darkModeIcon = document.getElementById('darkModeIcon');

    // Check localStorage for dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    if (isDarkMode) {
        document.documentElement.classList.add('dark');
        darkModeIcon.classList.remove('hidden');
        lightModeIcon.classList.add('hidden');
    } else {
        darkModeIcon.classList.add('hidden');
        lightModeIcon.classList.remove('hidden');
    }

    darkModeToggle.addEventListener('click', function () {
        const darkModeEnabled = document.documentElement.classList.toggle('dark');
        localStorage.setItem('darkMode', darkModeEnabled);

        // Toggle Icons
        if (darkModeEnabled) {
            darkModeIcon.classList.remove('hidden');
            lightModeIcon.classList.add('hidden');
        } else {
            darkModeIcon.classList.add('hidden');
            lightModeIcon.classList.remove('hidden');
        }
    });
});


//login



// dashboard


