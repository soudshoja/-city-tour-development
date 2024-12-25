console.log("Hello from App.js");

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// Get the body element and the saved theme from localStorage (default is "light")
const body = document.body;
const savedTheme = localStorage.getItem("theme") || "light"; // If no saved theme, default to light

// Apply the saved theme to the body element
body.classList.add(savedTheme);

// Function to toggle the theme between light and dark
function toggleTheme() {
    const currentTheme = body.classList.contains("dark") ? "dark" : "light";
    const newTheme = currentTheme === "light" ? "dark" : "light"; // Toggle between light and dark

    // Remove both light and dark classes to reset the theme
    body.classList.remove("light", "dark");
    // Add the new theme (light or dark)
    body.classList.add(newTheme);
    // Save the selected theme to localStorage
    localStorage.setItem("theme", newTheme);

    // Update the icon based on the new theme
    updateIcon(newTheme);
}

// Function to update the SVG icon based on the theme
function updateIcon(theme) {
    const lightSVG = document.getElementById("lightSVG");
    const darkSVG = document.getElementById("darkSVG");

    if (lightSVG && darkSVG) {
        if (theme === "dark") {
            lightSVG.style.display = "none";
            darkSVG.style.display = "inline"; // Ensure visibility
        } else {
            lightSVG.style.display = "inline"; // Ensure visibility
            darkSVG.style.display = "none";
        }
    }
}

// Add event listener for the button to toggle themes
// Changed to target the button itself inside the div for better event handling
document.getElementById("themeToggle").addEventListener("click", toggleTheme);

// On page load, check the saved theme and apply it
const currentTheme = localStorage.getItem("theme") || "light";
body.classList.add(currentTheme);
updateIcon(currentTheme);

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// tooltip js

document.addEventListener("mouseover", (e) => {
    const target = e.target.closest("[data-tooltip]");
    if (target) {
        target.classList.add("tooltip-active");
    }
});

document.addEventListener("mouseout", (e) => {
    const target = e.target.closest("[data-tooltip]");
    if (target) {
        target.classList.remove("tooltip-active");
    }
});
