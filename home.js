const searchIcon = document.getElementById("search-icon");
const searchBarContainer = document.getElementById("search-bar-container");
const searchBar = document.getElementById("search-bar");

searchIcon.addEventListener("click", () => {
    searchBarContainer.classList.toggle("hidden");
    if (!searchBarContainer.classList.contains("hidden")) {
        searchBar.focus();
    }
});