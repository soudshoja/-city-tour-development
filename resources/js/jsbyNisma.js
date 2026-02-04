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

