const floatingActions = document.getElementById("floatingActions");
const closeTaskFloatingActions = document.getElementById(
    "closeTaskFloatingActions"
);
const selectAllCheckbox = document.getElementById("selectAll");
const rowCheckboxes = document.querySelectorAll(".rowCheckbox");
const createInvoiceBtn = document.getElementById("createInvoiceBtn");

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener("change", function () {
        rowCheckboxes.forEach(
            (checkbox) => (checkbox.checked = selectAllCheckbox.checked)
        );
        toggleCreateInvoiceButton(); // Update button state
    });
}

const toggleCreateInvoiceButton = () => {
    const isAnySelected = Array.from(rowCheckboxes).some(
        (checkbox) => checkbox.checked
    );
    createInvoiceBtn.disabled = !isAnySelected;
};

rowCheckboxes.forEach((checkbox) => {
    checkbox.addEventListener("change", function () {
        // Update the "Select All" checkbox state
        const allChecked = Array.from(rowCheckboxes).every((cb) => cb.checked);
        selectAllCheckbox.checked = allChecked;

        // Update button state
        toggleCreateInvoiceButton();

        // Show or hide the floating div based on any checkbox selection
        const isAnyChecked = Array.from(rowCheckboxes).some((cb) => cb.checked);
        if (isAnyChecked) {
            floatingActions.classList.remove("hidden");
        } else {
            floatingActions.classList.add("hidden");
        }
    });
});

toggleCreateInvoiceButton();

createInvoiceBtn.addEventListener("click", function () {
    const selectedTaskIds = Array.from(rowCheckboxes)
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.value);

    if (selectedTaskIds.length === 0) {
        alert("No tasks selected!");
        return;
    }

    console.log(selectedTaskIds);
    const route = this.getAttribute("data-route");
    const url = route + "?task_ids=" + selectedTaskIds.join(",");

    window.location.href = url;
});

closeTaskFloatingActions.addEventListener("click", function () {
    floatingActions.classList.add("hidden");
});