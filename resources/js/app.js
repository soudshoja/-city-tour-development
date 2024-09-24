import Alpine from "alpinejs";

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

//login

// dashboard

const outputConsole = document.querySelector(".output-console");

var commandStart = [
    "Performing DNS Lookups for",
    "Searching ",
    "Analyzing ",
    "Estimating Approximate Location of ",
    "Compressing ",
    "Requesting Authorization From : ",
    "wget -a -t ",
    "tar -xzf ",
    "Entering Location ",
    "Compilation Started of ",
    "Downloading ",
];

var commandParts = [
    "Data Structure",
    "http://wwjd.com?au&2",
    "Texture",
    "TPS Reports",
    " .... Searching ... ",
    "http://zanb.se/?23&88&far=2",
    "http://ab.ret45-33/?timing=1ww",
];

var commandResponses = [
    "Authorizing ",
    "Authorized...",
    "Access Granted..",
    "Going Deeper....",
    "Compression Complete.",
    "Compilation of Data Structures Complete..",
    "Entering Security Console...",
    "Encryption Unsuccessful Attempting Retry...",
    "Waiting for response...",
    "....Searching...",
    "Calculating Space Requirements ",
];

var isProcessing = false;
var processTime = 0;
var lastProcess = 0;

function consoleOutput() {
    var textEl = document.createElement("p");

    if (isProcessing) {
        textEl = document.createElement("span");
        textEl.textContent += Math.random() + " ";
        if (Date.now() > lastProcess + processTime) {
            isProcessing = false;
        }
    } else {
        var commandType = ~~(Math.random() * 4);
        switch (commandType) {
            case 0:
                textEl.textContent =
                    commandStart[~~(Math.random() * commandStart.length)] +
                    commandParts[~~(Math.random() * commandParts.length)];
                break;
            case 3:
                isProcessing = true;
                processTime = ~~(Math.random() * 5000);
                lastProcess = Date.now();
                break;
            default:
                textEl.textContent =
                    commandResponses[
                        ~~(Math.random() * commandResponses.length)
                    ];
                break;
        }
    }

    outputConsole.scrollTop = outputConsole.scrollHeight;
    outputConsole.appendChild(textEl);

    // Remove excess lines if the output console gets too long
    if (outputConsole.scrollHeight > window.innerHeight) {
        var removeNodes = outputConsole.querySelectorAll("*");
        for (var n = 0; n < ~~(removeNodes.length / 3); n++) {
            outputConsole.removeChild(removeNodes[n]);
        }
    }

    setTimeout(consoleOutput, ~~(Math.random() * 200));
}

setTimeout(function () {
    outputConsole.style.height = (window.innerHeight / 3) * 2 + "px";
    outputConsole.style.top = window.innerHeight / 3 + "px";

    consoleOutput();
}, 200);

document.addEventListener("DOMContentLoaded", function () {
    setTimeout(function () {
        var flashMessage = document.getElementById("flash-message");
        if (flashMessage) {
            flashMessage.style.transition = "opacity 0.5s ease";
            flashMessage.style.opacity = "0";
            setTimeout(function () {
                flashMessage.remove();
            }, 500);
        }
    }, 5000);
});
