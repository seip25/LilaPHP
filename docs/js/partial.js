document.addEventListener("DOMContentLoaded", async () => {
    // Dynamically load aside navigation if target exists
    const sidebar = document.getElementById("sidebar");
    if (sidebar) {
        try {
            const response = await fetch("partials/aside.html");
            if (response.ok) {
                sidebar.innerHTML = await response.text();
            }
        } catch (e) {
            console.warn("Could not load partials/aside.html", e);
        }
    }

    // Theme toggling and mobile menu logic
    const html = document.documentElement;
    function toggleTheme() {
        html.classList.toggle("dark");
        localStorage.setItem("theme", html.classList.contains("dark") ? "dark" : "light");
    }

    const themeToggleBtn = document.getElementById("theme-toggle");
    if (themeToggleBtn) themeToggleBtn.addEventListener("click", toggleTheme);

    const navThemeBtn = document.getElementById("theme-toggle-nav");
    if (navThemeBtn) navThemeBtn.addEventListener("click", toggleTheme);

    if (
        localStorage.theme === "dark" ||
        (!("theme" in localStorage) && window.matchMedia("(prefers-color-scheme: dark)").matches)
    ) {
        html.classList.add("dark");
    } else {
        html.classList.remove("dark");
    }

    const menuToggle = document.getElementById("menu-toggle");
    if (menuToggle && sidebar) {
        menuToggle.addEventListener("click", () => {
            sidebar.classList.toggle("-translate-x-full");
        });
    }

    if (sidebar) {
        sidebar.querySelectorAll("a").forEach((link) => {
            link.addEventListener("click", () => {
                if (window.innerWidth < 1024) {
                    sidebar.classList.add("-translate-x-full");
                }
            });
        });
    }

    if (window.hljs) {
        window.hljs.highlightAll();
    }
});
