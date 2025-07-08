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

/* const searchInput = document.getElementById("searchInput");

if (searchInput) {
    const table = document.getElementById("myTable");
    const rows = Array.from(table.querySelector("tbody").rows); 

    function filterTable() {
        const query = searchInput.value.toLowerCase(); 
        rows.forEach((row) => {
            const cells = Array.from(row.cells); 
            const rowText = cells
                .map((cell) => cell.textContent.toLowerCase())
                .join(" "); 
            if (rowText.includes(query)) {
                row.style.display = ""; 
            } else {
                row.style.display = "none"; 
            }
        });
    }

    
    searchInput.addEventListener("input", filterTable);
} */
