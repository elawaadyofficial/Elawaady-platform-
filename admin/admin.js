/* Dashboard chrome behaviour. Kept out of the markup so the layout carries no
   inline handlers and a strict content policy stays possible. */
(function () {
    "use strict";

    var sidebar = document.getElementById("admin-sidebar");
    var overlay = document.getElementById("sidebar-overlay");
    if (!sidebar) { return; }

    function open()  { sidebar.classList.add("open");    if (overlay) { overlay.classList.add("active"); }    document.body.classList.add("sidebar-open"); }
    function close() { sidebar.classList.remove("open"); if (overlay) { overlay.classList.remove("active"); } document.body.classList.remove("sidebar-open"); }

    document.addEventListener("click", function (event) {
        if (event.target.closest("[data-toggle-sidebar]")) { sidebar.classList.contains("open") ? close() : open(); }
        if (event.target.closest("[data-close-sidebar]"))  { close(); }
        if (event.target === overlay) { close(); }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape") { close(); }
    });

    /* A destructive button says what it is about to do before it does it. */
    document.addEventListener("submit", function (event) {
        var confirmText = event.target.getAttribute("data-confirm");
        if (confirmText && !window.confirm(confirmText)) { event.preventDefault(); }
    });
})();
