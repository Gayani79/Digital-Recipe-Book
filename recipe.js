document.querySelectorAll(".card button").forEach((button) => {
    button.addEventListener("click", () => {
        const recipeName = button.closest(".card").querySelector("h2, h4").textContent;
        alert(`Viewing details for ${recipeName}`);
    });
});

const searchIcon = document.querySelector(".search-icon a");
searchIcon.addEventListener("click", (event) => {
    event.preventDefault();
    const searchTerm = prompt("Enter a recipe name or keyword:");
    if (searchTerm) {
        alert(`Searching for recipes related to: ${searchTerm}`);
        
    }
});

const profileIcon = document.querySelector(".profile a");
profileIcon.addEventListener("click", (event) => {
    event.preventDefault();
    alert("Redirecting to your profile...");
    
});

document.querySelectorAll(".footer-icons ul li a").forEach((link) => {
    link.addEventListener("click", (event) => {
        event.preventDefault();
        const platform = link.querySelector("i").classList[1];
        alert(`Redirecting to our ${platform.split("-")[2]} page`);
    });
});

if (window.innerWidth < 480) {
    console.log("You are on a small screen, and the layout is optimized for mobile!");
}

