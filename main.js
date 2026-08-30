document.addEventListener("DOMContentLoaded", () => {
    const menuToggle = document.querySelector(".menu-toggle");
    const mainNav = document.querySelector(".main-nav");

    if (menuToggle && mainNav) {
        menuToggle.addEventListener("click", () => {
            mainNav.classList.toggle("is-open");
        });
    }

    const carousel = document.querySelector("[data-carousel]");
    if (!carousel) return;

    const slides = Array.from(carousel.querySelectorAll(".hero-slide"));
    const prev = carousel.querySelector("[data-prev]");
    const next = carousel.querySelector("[data-next]");
    const dotsWrap = carousel.querySelector("[data-dots]");
    let current = 0;
    let timer;

    const dots = slides.map((_, index) => {
        const button = document.createElement("button");
        button.type = "button";
        button.setAttribute("aria-label", `الشريحة ${index + 1}`);
        button.addEventListener("click", () => show(index, true));
        dotsWrap.appendChild(button);
        return button;
    });

    function show(index, resetTimer = false) {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => slide.classList.toggle("is-active", i === current));
        dots.forEach((dot, i) => dot.classList.toggle("is-active", i === current));
        if (resetTimer) restart();
    }

    function restart() {
        window.clearInterval(timer);
        timer = window.setInterval(() => show(current + 1), 5200);
    }

    prev?.addEventListener("click", () => show(current - 1, true));
    next?.addEventListener("click", () => show(current + 1, true));

    let touchStartX = null;
    carousel.addEventListener("touchstart", event => {
        touchStartX = event.changedTouches[0].clientX;
    }, { passive: true });
    carousel.addEventListener("touchend", event => {
        if (touchStartX === null) return;
        const delta = event.changedTouches[0].clientX - touchStartX;
        if (Math.abs(delta) > 45) show(current + (delta > 0 ? 1 : -1), true);
        touchStartX = null;
    }, { passive: true });

    carousel.addEventListener("mouseenter", () => window.clearInterval(timer));
    carousel.addEventListener("mouseleave", restart);

    show(0);
    restart();
});
