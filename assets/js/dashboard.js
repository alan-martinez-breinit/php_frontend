(function () {
    /* ---- Perfil dropdown ---- */
    var toggle = document.getElementById("profile-toggle");
    var dropdown = document.getElementById("profile-dropdown");
    var menu = document.getElementById("profile-menu");

    if (toggle && dropdown && menu) {
        toggle.addEventListener("click", function (e) {
            e.stopPropagation();
            dropdown.classList.toggle("show");
        });

        document.addEventListener("click", function (e) {
            if (!menu.contains(e.target)) {
                dropdown.classList.remove("show");
            }
        });
    }
})();
