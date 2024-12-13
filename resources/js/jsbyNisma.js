console.log("Hello from jsbyNisma.js");

// search branch

document
    .getElementById("branch-search")
    .addEventListener("input", function (e) {
        const query = e.target.value.trim();
        const resultsContainer = document.getElementById("branch-results");

        if (query.length > 0) {
            fetch(
                `{{ route('branches.search') }}?query=${encodeURIComponent(
                    query
                )}`
            )
                .then((response) => {
                    if (!response.ok) {
                        throw new Error(
                            `HTTP error! Status: ${response.status}`
                        );
                    }
                    return response.json();
                })
                .then((data) => {
                    resultsContainer.innerHTML = "";
                    resultsContainer.classList.remove("hidden");

                    if (data.length > 0) {
                        data.forEach((branch) => {
                            const li = document.createElement("li");
                            li.className =
                                "px-4 py-2 hover:bg-gray-100 cursor-pointer";
                            li.textContent = branch.name;
                            resultsContainer.appendChild(li);

                            li.addEventListener("click", () => {
                                document.getElementById("branch-search").value =
                                    branch.name;
                                resultsContainer.classList.add("hidden");
                            });
                        });
                    } else {
                        resultsContainer.innerHTML =
                            '<li class="px-4 py-2 text-gray-500">No results found</li>';
                    }
                })
                .catch((error) => {
                    console.error("Error fetching branches:", error);
                    resultsContainer.innerHTML =
                        '<li class="px-4 py-2 text-red-500">Failed to fetch results</li>';
                    resultsContainer.classList.remove("hidden");
                });
        } else {
            resultsContainer.classList.add("hidden");
            resultsContainer.innerHTML = ""; // Clear results
        }
    });
