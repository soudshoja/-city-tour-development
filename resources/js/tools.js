/// refresh icon

document.querySelectorAll(".refresh-icon").forEach((icon) => {
    icon.addEventListener("click", () => {
        window.location.href = window.location.href; // Forces a fresh request to the server

        const svg = icon.querySelector("svg");
        svg.classList.add("rotate");
        setTimeout(() => {
            svg.classList.remove("rotate");
            console.log("Content refreshed!");
        }, 1000);
    });
});

/// search

// document.addEventListener("DOMContentLoaded", function () {
//     const searchInput = document.getElementById("searchInput");
//     const table = document.getElementById("myTable");
//     const rows = Array.from(table.querySelector("tbody").rows); // Get all rows

//     // Function to filter rows based on search input
//     function filterTable() {
//         const query = searchInput.value.toLowerCase(); // Get the search query
//         rows.forEach((row) => {
//             const cells = Array.from(row.cells); // Get all cells in the row
//             const rowText = cells
//                 .map((cell) => cell.textContent.toLowerCase())
//                 .join(" "); // Combine text from all cells
//             if (rowText.includes(query)) {
//                 row.style.display = ""; // Show row if it matches the query
//             } else {
//                 row.style.display = "none"; // Hide row if it doesn't match
//             }
//         });
//     }

//     // Event listener for the search input
//     searchInput.addEventListener("input", filterTable);
// });
