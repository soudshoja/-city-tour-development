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

