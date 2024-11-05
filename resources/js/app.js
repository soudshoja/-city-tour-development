import Alpine from "alpinejs";
import "@fortawesome/fontawesome-free/css/all.css";

// sidebar toggle
window.Alpine = Alpine;

Alpine.start();

document.addEventListener("alpine:init", () => {
    Alpine.store("sidebar", {
        open: false,
        toggle() {
            this.open = !this.open;
        },
        close() {
            this.open = false;
        },
    });
});

// Dark mode toggle
document.addEventListener("DOMContentLoaded", function () {
    const darkModeToggle = document.getElementById("darkModeToggle");
    const lightModeIcon = document.getElementById("lightModeIcon");
    const darkModeIcon = document.getElementById("darkModeIcon");

    // Check localStorage for dark mode preference
    const isDarkMode = localStorage.getItem("darkMode") === "true";
    if (isDarkMode) {
        document.documentElement.classList.add("dark");
        darkModeIcon.classList.remove("hidden");
        lightModeIcon.classList.add("hidden");
    } else {
        darkModeIcon.classList.add("hidden");
        lightModeIcon.classList.remove("hidden");
    }

    darkModeToggle.addEventListener("click", function () {
        const darkModeEnabled =
            document.documentElement.classList.toggle("dark");
        localStorage.setItem("darkMode", darkModeEnabled);

        // Toggle Icons
        if (darkModeEnabled) {
            darkModeIcon.classList.remove("hidden");
            lightModeIcon.classList.add("hidden");
        } else {
            darkModeIcon.classList.add("hidden");
            lightModeIcon.classList.remove("hidden");
        }
    });
});

// tasklist page js
document.addEventListener("DOMContentLoaded", function () {
    // Handle task count display
    const taskCount = window.taskCount;

    document.getElementById("TasksData").innerText = taskCount;

    // Select all functionality
    const selectAllSVG = document.getElementById("selectAllSVG");
    const rowCheckboxes = document.querySelectorAll(".rowCheckbox");

    selectAllSVG.addEventListener("click", () => {
        const allChecked = Array.from(rowCheckboxes).every(
            (checkbox) => checkbox.checked
        );
        rowCheckboxes.forEach((checkbox) => (checkbox.checked = !allChecked));
    });

    // Update SVG color based on checkbox selection
    rowCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener("change", () => {
            const allChecked = Array.from(rowCheckboxes).every(
                (cb) => cb.checked
            );
            selectAllSVG.style.fill = allChecked ? "#4fd1c5" : "#1C274C"; // Change color accordingly
        });
    });

    // Handle the "Add Task" form submission
    const editableCells = document.querySelectorAll('[contenteditable="true"]');
    editableCells.forEach((cell) => {
        cell.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault(); // Prevent the default behavior (line break)

                const taskId = this.getAttribute("data-id");
                const field = this.getAttribute("data-field");
                const value = this.textContent.trim();

                // Disable the cell temporarily to prevent multiple submissions
                this.setAttribute("contenteditable", "false");

                // Send an AJAX request to update the task
                saveTaskField(taskId, field, value, this);

                // Recalculate the total if one of the relevant fields is updated
                if (
                    field === "price" ||
                    field === "surcharge" ||
                    field === "tax"
                ) {
                    calculateTotal(taskId);
                }
            }
        });
    });

    function calculateTotal(taskId) {
        // Find the row that matches the task ID
        const row = document
            .querySelector(`[data-id="${taskId}"]`)
            .closest("tr");
        const price =
            parseFloat(
                row.querySelector('[data-field="price"]').textContent.trim()
            ) || 0;
        const surcharge =
            parseFloat(
                row.querySelector('[data-field="surcharge"]').textContent.trim()
            ) || 0;
        const tax =
            parseFloat(
                row.querySelector('[data-field="tax"]').textContent.trim()
            ) || 0;

        // Calculate the total
        const total = price + surcharge + tax;

        // Update the total field in the UI
        row.querySelector('[data-field="total"]').textContent =
            total.toFixed(2);

        // Optionally, you can send the updated total back to the server
        saveTaskField(
            taskId,
            "total",
            total.toFixed(2),
            row.querySelector('[data-field="total"]')
        );
    }

    function saveTaskField(taskId, field, value, cell) {
        fetch(`/tasks-update/${taskId}`, {
            method: "PUT",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document
                    .querySelector('meta[name="csrf-token"]')
                    .getAttribute("content"),
            },
            body: JSON.stringify({
                [field]: value,
            }),
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    // Task updated successfully, re-enable the cell for further edits
                    cell.setAttribute("contenteditable", "true");
                    cell.textContent = value; // Update the cell content to the saved value

                    // Show custom notification
                    showNotification("Task updated successfully!", "success");
                } else {
                    // Show an error message if saving failed
                    showNotification(
                        "Error updating task: " +
                            (data.message || "Unknown error."),
                        "error"
                    );
                    cell.setAttribute("contenteditable", "true"); // Re-enable the cell
                }
            })
            .catch((error) => {
                console.error("Error:", error); // Log the error to the console for debugging
                showNotification(
                    "Error updating task. Please try again.",
                    "error"
                );
                cell.setAttribute("contenteditable", "true"); // Re-enable the cell
            });
    }

    function showNotification(message, type = "success") {
        const notification = document.getElementById("notification");
        const notificationMessage = document.getElementById(
            "notificationMessage"
        );

        // Set message and notification color based on type
        notificationMessage.textContent = message;
        if (type === "success") {
            notification.classList.add("bg-green-500");
            notification.classList.remove("bg-red-500");
        } else if (type === "error") {
            notification.classList.add("bg-red-500");
            notification.classList.remove("bg-green-500");
        }

        // Show the notification
        notification.classList.remove("hidden");
        notification.classList.add("show");

        // Hide the notification after 3 seconds
        setTimeout(() => {
            notification.classList.remove("show");
            setTimeout(() => {
                notification.classList.add("hidden");
            }, 500); // Delay hiding for smooth transition
        }, 3000);
    }
});

// ./tasklist page js

// COA page js

// search in COA page

document.getElementById("search-icon").addEventListener("click", function () {
    const searchInput = document.getElementById("search-input");
    if (searchInput.classList.contains("hidden")) {
        searchInput.classList.remove("hidden");
        searchInput.classList.add("visible");
        searchInput.focus(); // Automatically focus the input when it appears
    } else {
        searchInput.classList.remove("visible");
        searchInput.classList.add("hidden");
        searchInput.value = ""; // Clear the input when hiding
        filterItems(""); // Reset the filtering
    }
});

document.getElementById("search-input").addEventListener("input", function () {
    const query = this.value.toLowerCase();
    filterItems(query);
});

function filterItems(query) {
    // Select all search items
    const items = document.querySelectorAll(".search-item");
    items.forEach((item) => {
        // Check if the item's text includes the search query
        if (item.textContent.toLowerCase().includes(query)) {
            item.style.display = ""; // Show the item
        } else {
            item.style.display = "none"; // Hide the item
        }
    });
}
