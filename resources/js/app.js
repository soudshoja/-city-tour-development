console.log("Hello from App.js");

// mode switcher js

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

// Function to update the icon based on the current theme
function updateIcon(theme) {
    const icon = document.getElementById("themeIcon");

    if (theme === "light") {
        // Light mode: Set dark icon (sun)
        icon.setAttribute(
            "d",
            "M12 21a9 9 0 0 0 8.997-9.252a7 7 0 0 1-10.371-8.643A9 9 0 0 0 12 21"
        ); // Sun icon path
        icon.setAttribute("fill", "none");
        icon.setAttribute("stroke", "#333333");
    } else {
        // Dark mode: Set light icon (moon)
        icon.setAttribute(
            "d",
            "M12 15q1.25 0 2.125-.875T15 12t-.875-2.125T12 9t-2.125.875T9 12t.875 2.125T12 15m0 2q-2.075 0-3.537-1.463T7 12t1.463-3.537T12 7t3.538 1.463T17 12t-1.463 3.538T12 17m-7-4H1v-2h4zm18 0h-4v-2h4zM11 5V1h2v4zm0 18v-4h2v4zM6.4 7.75L3.875 5.325L5.3 3.85l2.4 2.5zm12.3 12.4l-2.425-2.525L17.6 16.25l2.525 2.425zM16.25 6.4l2.425-2.525L20.15 5.3l-2.5 2.4zM3.85 18.7l2.525-2.425L7.75 17.6l-2.425 2.525zM12 12"
        ); // Moon icon path
        icon.setAttribute("fill", "#ffffff");
    }
}

// Add event listener for the button to toggle themes
document.getElementById("themeToggle").addEventListener("click", toggleTheme);

// On page load, check the saved theme and apply it
const currentTheme = localStorage.getItem("theme") || "light";
body.classList.add(currentTheme);
updateIcon(currentTheme);

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
