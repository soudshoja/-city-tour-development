console.log("Hello from App.js");

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
