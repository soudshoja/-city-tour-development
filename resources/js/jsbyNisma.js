///////////////////////////////////////////////////////////////////////////////////////////////////////////

// search for a branch and agent for company user
document.addEventListener("DOMContentLoaded", function () {
    // Branch Search
    // document
    //     .getElementById("branch-search")
    //     .addEventListener("input", function () {
    //         let query = this.value;

    //         if (query.length >= 3) {
    //             fetch(`/search-branch?name=${query}`, {
    //                 method: "GET",
    //                 headers: {
    //                     Accept: "application/json",
    //                     "X-CSRF-TOKEN": document
    //                         .querySelector('meta[name="csrf-token"]')
    //                         .getAttribute("content"),
    //                 },
    //             })
    //                 .then((response) => response.json())
    //                 .then((data) => {
    //                     const resultsList = document.getElementById(
    //                         "branch-results-list"
    //                     );
    //                     const resultsDiv = document.getElementById(
    //                         "branch-search-results"
    //                     );
    //                     resultsList.innerHTML = "";

    //                     if (data.length > 0) {
    //                         data.forEach((branch) => {
    //                             const li = document.createElement("li");
    //                             li.classList.add(
    //                                 "flex",
    //                                 "justify-between",
    //                                 "items-center",
    //                                 "bg-gray-100",
    //                                 "p-3",
    //                                 "rounded-lg",
    //                                 "shadow-sm"
    //                             );
    //                             li.innerHTML = `
    //                                 <strong class="text-lg text-gray-800">${branch.name}</strong>
    //                                 <button class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-all" data-id="${branch.id}" data-name="${branch.name}">
    //                                     Select
    //                                 </button>
    //                             `;
    //                             resultsList.appendChild(li);

    //                             li.querySelector("button").addEventListener(
    //                                 "click",
    //                                 function () {
    //                                     document.getElementById(
    //                                         "selected-branch-name"
    //                                     ).textContent = branch.name;
    //                                     document.getElementById(
    //                                         "selected-branch-id"
    //                                     ).value = branch.id; // Save the branch ID
    //                                     document
    //                                         .getElementById("selected-branch")
    //                                         .classList.remove("hidden");
    //                                     resultsDiv.classList.add("hidden");
    //                                 }
    //                             );
    //                         });
    //                         resultsDiv.classList.remove("hidden");
    //                     } else {
    //                         resultsList.innerHTML =
    //                             '<li class="text-gray-500">No branches found.</li>';
    //                         resultsDiv.classList.remove("hidden");
    //                     }
    //                 });
    //         } else {
    //             document
    //                 .getElementById("branch-search-results")
    //                 .classList.add("hidden");
    //         }
    //     });

    // Remove Branch
    document
        .getElementById("remove-branch")
        ?.addEventListener("click", function () {
            document.getElementById("selected-branch-name").textContent = "";
            document.getElementById("selected-branch").classList.add("hidden");
            document.getElementById("selected-branch-id").value = "";
        });

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////

    // Agent Search
    // document
    //     .getElementById("Agent-search")
    //     .addEventListener("input", function () {
    //         let query = this.value;
    //         const selectedBranchId =
    //             document.getElementById("selected-branch-id").value; // Ensure branch ID is stored in a hidden input.

    //         if (query.length >= 3 && selectedBranchId) {
    //             fetch(
    //                 `/search-agent?name=${query}&branch_id=${selectedBranchId}`,
    //                 {
    //                     method: "GET",
    //                     headers: {
    //                         Accept: "application/json",
    //                         "X-CSRF-TOKEN": document
    //                             .querySelector('meta[name="csrf-token"]')
    //                             .getAttribute("content"),
    //                     },
    //                 }
    //             )
    //                 .then((response) => response.json())
    //                 .then((data) => {
    //                     const resultsList =
    //                         document.getElementById("agent-results-list");
    //                     const resultsDiv = document.getElementById(
    //                         "agent-search-results"
    //                     );
    //                     resultsList.innerHTML = "";

    //                     if (data.length > 0) {
    //                         data.forEach((agent) => {
    //                             const li = document.createElement("li");
    //                             li.classList.add(
    //                                 "flex",
    //                                 "justify-between",
    //                                 "items-center",
    //                                 "bg-gray-100",
    //                                 "p-3",
    //                                 "rounded-lg",
    //                                 "shadow-sm"
    //                             );
    //                             li.innerHTML = `
    //                             <strong class="text-lg text-gray-800">${agent.name}</strong>
    //                             <button class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-all" data-id="${agent.id}" data-name="${agent.name}">
    //                                 Select
    //                             </button>
    //                         `;
    //                             resultsList.appendChild(li);

    //                             li.querySelector("button").addEventListener(
    //                                 "click",
    //                                 function () {
    //                                     document.getElementById(
    //                                         "selected-agent-name"
    //                                     ).textContent = agent.name;
    //                                     document
    //                                         .getElementById("selected-agent")
    //                                         .classList.remove("hidden");
    //                                     resultsDiv.classList.add("hidden");
    //                                 }
    //                             );
    //                         });
    //                         resultsDiv.classList.remove("hidden");
    //                     } else {
    //                         resultsList.innerHTML =
    //                             '<li class="text-gray-500">No agents found.</li>';
    //                         resultsDiv.classList.remove("hidden");
    //                     }
    //                 });
    //         } else {
    //             document
    //                 .getElementById("agent-search-results")
    //                 .classList.add("hidden");
    //         }
    //     });

    // Remove Agent
    document
        .getElementById("remove-agent")
        ?.addEventListener("click", function () {
            document.getElementById("selected-agent-name").textContent = "";
            document.getElementById("selected-agent").classList.add("hidden");
        });
});

///////////////////////////////////////////////////////////////////////////////////////////////////////////

// Search Clients
// document
//     .getElementById("client-search-input")
//     .addEventListener("input", function () {
//         const query = this.value.trim();
//         const resultsDiv = document.getElementById("client-search-results");
//         const resultsList = document.getElementById("client-results-list");

//         if (query.length === 0) {
//             resultsDiv.classList.add("hidden");
//             return;
//         }

//         fetch(`/search-client?name=${query}`, {
//             method: "GET",
//             headers: { Accept: "application/json" },
//         })
//             .then((response) => response.json())
//             .then((data) => {
//                 resultsList.innerHTML = "";

//                 if (data.length > 0) {
//                     data.forEach((client) => {
//                         const li = document.createElement("li");
//                         li.classList.add(
//                             "flex",
//                             "justify-between",
//                             "items-center",
//                             "bg-gray-100",
//                             "p-3",
//                             "rounded-lg",
//                             "shadow-sm"
//                         );
//                         li.innerHTML = `
//                         <div>
//                             <strong class="text-lg text-gray-800">${
//                                 client.name
//                             }</strong>
//                             <p class="text-sm text-gray-600">${
//                                 client.email || "No Email"
//                             } - ${client.phone || "No Phone"}</p>
//                         </div>
//                         <button class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-all" data-id="${
//                             client.id
//                         }" data-name="${client.name}">
//                             Select
//                         </button>
//                     `;
//                         resultsList.appendChild(li);

//                         // Add click listener to select client
//                         li.querySelector("button").addEventListener(
//                             "click",
//                             function () {
//                                 document.getElementById(
//                                     "selected-client-name"
//                                 ).textContent = client.name;
//                                 document.getElementById(
//                                     "selected-client-id"
//                                 ).value = client.id;
//                                 document
//                                     .getElementById("selected-client")
//                                     .classList.remove("hidden");
//                                 resultsDiv.classList.add("hidden");
//                             }
//                         );
//                     });
//                     resultsDiv.classList.remove("hidden");
//                 } else {
//                     resultsList.innerHTML =
//                         '<li class="text-gray-500">No clients found.</li>';
//                     resultsDiv.classList.remove("hidden");
//                 }
//             })
//             .catch((error) => {
//                 console.error("Error fetching clients:", error);
//             });
//     });

// Remove Client
document
    .getElementById("remove-client")
    ?.addEventListener("click", function () {
        document.getElementById("selected-client-name").textContent = "";
        document.getElementById("selected-client").classList.add("hidden");
        document.getElementById("selected-client-id").value = "";
    });

///////////////////////////////////////////////////////////////////////////////////////////////////////////

// Search for items
// document
//     .getElementById("Item-search-input")
//     .addEventListener("input", function () {
//         const query = this.value.trim();
//         const resultsDiv = document.getElementById("Item-search-results");
//         const resultsList = document.getElementById("Item-results-list");

//         // Hide results if query is empty
//         if (query.length === 0) {
//             resultsDiv.classList.add("hidden");
//             return;
//         }

//         // Fetch items based on search query
//         fetch(`/search-item?q=${query}`, {
//             method: "GET",
//             headers: { Accept: "application/json" },
//         })
//             .then((response) => response.json())
//             .then((data) => {
//                 resultsList.innerHTML = "";

//                 if (data.length > 0) {
//                     // If results found, display them
//                     data.forEach((item) => {
//                         const li = document.createElement("li");
//                         li.classList.add(
//                             "flex",
//                             "justify-between",
//                             "items-center",
//                             "bg-gray-100",
//                             "p-3",
//                             "rounded-lg",
//                             "shadow-sm"
//                         );
//                         li.innerHTML = `
//                         <div>
//                             <strong class="text-lg text-gray-800">${
//                                 item.client_email
//                             }</strong>
//                             <p class="text-sm text-gray-600">${
//                                 item.description || "No description"
//                             }</p>
//                         </div>
//                         <button class="btn btn-success px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-all" data-id="${
//                             item.id
//                         }" data-name="${item.trip_name}">
//                             Select
//                         </button>
//                     `;
//                         resultsList.appendChild(li);

//                         // Add click listener to select item
//                         li.querySelector("button").addEventListener(
//                             "click",
//                             function () {
//                                 const selectedItems =
//                                     JSON.parse(
//                                         localStorage.getItem("selectedItems")
//                                     ) || [];

//                                 // Check if the item is already selected
//                                 const itemIndex = selectedItems.findIndex(
//                                     (selectedItem) =>
//                                         selectedItem.id === item.id
//                                 );

//                                 if (itemIndex === -1) {
//                                     // Item is not selected, so add it
//                                     selectedItems.push({
//                                         id: item.id,
//                                         name: item.trip_name,
//                                     });
//                                 } else {
//                                     // Item is already selected, remove it
//                                     selectedItems.splice(itemIndex, 1);
//                                 }

//                                 // Store the selected items in localStorage
//                                 localStorage.setItem(
//                                     "selectedItems",
//                                     JSON.stringify(selectedItems)
//                                 );

//                                 // Update UI
//                                 updateSelectedItemsUI(selectedItems);
//                             }
//                         );
//                     });
//                     resultsDiv.classList.remove("hidden");
//                 } else {
//                     // Display "No items found" if no results
//                     resultsList.innerHTML =
//                         '<li class="text-gray-500">No items found.</li>';
//                     resultsDiv.classList.remove("hidden");
//                 }
//             })
//             .catch((error) => {
//                 console.error("Error fetching items:", error);
//                 resultsList.innerHTML =
//                     '<li class="text-red-500">Error fetching items.</li>';
//                 resultsDiv.classList.remove("hidden");
//             });
//     });

// Function to update the UI with the selected items
function updateSelectedItemsUI(selectedItems) {
    const selectedItemDiv = document.getElementById("selected-Item");
    const selectedItemList = document.getElementById("selected-Item-list");

    // Clear the current list
    selectedItemList.innerHTML = "";

    selectedItems.forEach((item) => {
        const li = document.createElement("li");
        li.classList.add(
            "flex",
            "justify-between",
            "items-center",
            "bg-gray-200",
            "p-2",
            "rounded-lg",
            "mb-2"
        );
        li.innerHTML = `
            <span class="text-lg text-gray-800">${item.name}</span>
            <button class="ml-2 text-red-600" onclick="removeItem('${item.id}')">Remove</button>
        `;
        selectedItemList.appendChild(li);
    });

    if (selectedItems.length === 0) {
        selectedItemDiv.classList.add("hidden");
    } else {
        selectedItemDiv.classList.remove("hidden");
    }
}

// Remove item from the selected items list
function removeItem(itemId) {
    const selectedItems =
        JSON.parse(localStorage.getItem("selectedItems")) || [];

    // Remove the item from the array
    const updatedItems = selectedItems.filter((item) => item.id !== itemId);

    // Store the updated list in localStorage
    localStorage.setItem("selectedItems", JSON.stringify(updatedItems));

    // Update the UI
    updateSelectedItemsUI(updatedItems);
}

// Submit selected items to the backend
function submitSelectedItems() {
    const selectedItems =
        JSON.parse(localStorage.getItem("selectedItems")) || [];

    // If no items are selected, show an alert or handle accordingly
    if (selectedItems.length === 0) {
        alert("Please select at least one item.");
        return;
    }

    // Send selected items to the backend via POST request
    fetch("/select-item", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document
                .querySelector('meta[name="csrf-token"]')
                .getAttribute("content"),
        },
        body: JSON.stringify({
            selected_items: selectedItems,
        }),
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                console.log(
                    "Items selected successfully:",
                    data.selected_items
                );
                alert("Items selected successfully!");
            } else {
                console.error("Failed to select items:", data.message);
                alert("Failed to select items.");
            }
        })
        .catch((error) => {
            console.error("Error submitting selected items:", error);
            alert("Error submitting selected items.");
        });
}

///////////////////////////////////////////////////////////////////////////////////////////////////////////
