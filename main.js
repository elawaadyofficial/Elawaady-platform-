document.addEventListener("DOMContentLoaded", () => {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const menuToggle = document.querySelector(".menu-toggle");
    const mainNav = document.querySelector(".main-nav");
    const header = document.querySelector(".site-header");

    if (menuToggle && mainNav) {
        menuToggle.addEventListener("click", () => {
            mainNav.classList.toggle("is-open");
            menuToggle.setAttribute("aria-expanded", mainNav.classList.contains("is-open") ? "true" : "false");
        });
    }

    let lastScrollY = window.scrollY;
    window.addEventListener("scroll", () => {
        const y = window.scrollY;
        header?.classList.toggle("is-scrolled", y > 16);
        if (header && y > 180 && y > lastScrollY + 8) header.classList.add("is-hidden");
        if (header && y < lastScrollY - 8) header.classList.remove("is-hidden");
        lastScrollY = y;
    }, { passive: true });

    const revealTargets = document.querySelectorAll(
        ".section-title-row,.benefits-grid,.category-scroller,.product-grid,.feature-banner,.section-head,.reviews-grid,.stats-panel,.faq-layout,.payment-inner"
    );
    revealTargets.forEach(el => {
        el.classList.add(el.matches(".benefits-grid,.product-grid") ? "reveal-stagger" : "reveal");
    });

    if (!reduceMotion && "IntersectionObserver" in window) {
        const revealObserver = new IntersectionObserver(entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("is-visible");
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: .12, rootMargin: "0px 0px -7% 0px" });
        revealTargets.forEach(el => revealObserver.observe(el));
    } else {
        revealTargets.forEach(el => el.classList.add("is-visible"));
    }

    document.querySelectorAll(".product-card").forEach(card => {
        if (reduceMotion || window.matchMedia("(pointer: coarse)").matches) return;
        card.addEventListener("pointermove", event => {
            const rect = card.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width - .5;
            const y = (event.clientY - rect.top) / rect.height - .5;
            card.style.transform = `perspective(900px) rotateX(${(-y * 6).toFixed(2)}deg) rotateY(${(x * 7).toFixed(2)}deg) translateY(-5px)`;
        });
        card.addEventListener("pointerleave", () => {
            card.style.transform = "";
        });
    });

    const setupDragScroller = scroller => {
        if (!scroller) return;
        let down = false;
        let startX = 0;
        let startScroll = 0;
        scroller.addEventListener("pointerdown", event => {
            down = true;
            startX = event.clientX;
            startScroll = scroller.scrollLeft;
            scroller.classList.add("is-dragging");
            scroller.setPointerCapture?.(event.pointerId);
        });
        scroller.addEventListener("pointermove", event => {
            if (!down) return;
            scroller.scrollLeft = startScroll - (event.clientX - startX) * 1.25;
        });
        const stop = () => {
            down = false;
            scroller.classList.remove("is-dragging");
        };
        scroller.addEventListener("pointerup", stop);
        scroller.addEventListener("pointercancel", stop);
        scroller.addEventListener("pointerleave", stop);
    };

    // Every rail is draggable, not just the category one: a mouse has no swipe,
    // and a row that only moves by trackpad is a row most people never scroll.
    document.querySelectorAll(".exd-rail").forEach(setupDragScroller);

    const categoryScroller = document.querySelector(".category-scroller");

    if (categoryScroller) {
        const titleRow = categoryScroller.closest(".section")?.querySelector(".section-title-row");
        const tools = document.createElement("div");
        tools.className = "carousel-tools";
        tools.innerHTML = '<button type="button" aria-label="السابق">‹</button><button type="button" aria-label="التالي">›</button>';
        titleRow?.appendChild(tools);
        const [prevCat, nextCat] = tools.querySelectorAll("button");
        const scrollCategories = direction => categoryScroller.scrollBy({ left: direction * Math.min(620, categoryScroller.clientWidth * .82), behavior: reduceMotion ? "auto" : "smooth" });
        prevCat?.addEventListener("click", () => scrollCategories(1));
        nextCat?.addEventListener("click", () => scrollCategories(-1));
    }

    const reviews = document.querySelector(".reviews-grid");
    if (reviews && !reduceMotion && !window.matchMedia("(pointer: coarse)").matches) {
        let reviewTimer = window.setInterval(() => {
            const first = reviews.firstElementChild;
            if (!first) return;
            reviews.scrollBy({ left: -(first.getBoundingClientRect().width + 16), behavior: "smooth" });
            window.setTimeout(() => {
                if (reviews.scrollLeft + reviews.clientWidth >= reviews.scrollWidth - 15) reviews.scrollTo({ left: 0, behavior: "smooth" });
            }, 900);
        }, 3600);
        reviews.addEventListener("mouseenter", () => window.clearInterval(reviewTimer), { once: true });
    }

    const paymentTrack = document.querySelector(".payment-placeholders");
    if (paymentTrack && !reduceMotion) {
        [...paymentTrack.children].forEach(item => paymentTrack.appendChild(item.cloneNode(true)));
        let offset = 0;
        const tickPayments = () => {
            offset -= .35;
            const half = paymentTrack.scrollWidth / 2;
            if (Math.abs(offset) >= half) offset = 0;
            paymentTrack.style.transform = `translateX(${offset}px)`;
            requestAnimationFrame(tickPayments);
        };
        requestAnimationFrame(tickPayments);
    }

    const carousel = document.querySelector("[data-carousel]");
    if (!carousel) return;

    const slides = Array.from(carousel.querySelectorAll(".hero-slide"));
    const prev = carousel.querySelector("[data-prev]");
    const next = carousel.querySelector("[data-next]");
    const dotsWrap = carousel.querySelector("[data-dots]");
    const duration = 5200;
    let current = 0;
    let timer;
    let paused = false;
    let touchStartX = null;

    const progress = document.createElement("div");
    progress.className = "hero-progress";
    progress.innerHTML = "<i></i>";
    progress.style.setProperty("--hero-duration", `${duration / 1000}s`);
    carousel.appendChild(progress);

    const dots = slides.map((_, index) => {
        const button = document.createElement("button");
        button.type = "button";
        button.setAttribute("aria-label", `الشريحة ${index + 1}`);
        button.addEventListener("click", () => show(index, true));
        dotsWrap?.appendChild(button);
        return button;
    });

    function animateProgress() {
        if (reduceMotion) return;
        progress.classList.remove("is-running");
        void progress.offsetWidth;
        progress.classList.add("is-running");
    }

    function show(index, resetTimer = false) {
        current = (index + slides.length) % slides.length;
        slides.forEach((slide, i) => {
            const active = i === current;
            slide.classList.toggle("is-active", active);
            slide.setAttribute("aria-hidden", active ? "false" : "true");
        });
        dots.forEach((dot, i) => dot.classList.toggle("is-active", i === current));
        animateProgress();
        if (resetTimer) restart();
    }

    function restart() {
        window.clearInterval(timer);
        if (reduceMotion || paused) return;
        timer = window.setInterval(() => show(current + 1), duration);
    }

    prev?.addEventListener("click", () => show(current - 1, true));
    next?.addEventListener("click", () => show(current + 1, true));

    carousel.addEventListener("touchstart", event => {
        touchStartX = event.changedTouches[0].clientX;
        paused = true;
        window.clearInterval(timer);
    }, { passive: true });
    carousel.addEventListener("touchend", event => {
        if (touchStartX !== null) {
            const delta = event.changedTouches[0].clientX - touchStartX;
            if (Math.abs(delta) > 45) show(current + (delta > 0 ? 1 : -1));
        }
        touchStartX = null;
        paused = false;
        restart();
    }, { passive: true });

    carousel.addEventListener("mouseenter", () => {
        paused = true;
        window.clearInterval(timer);
        progress.classList.remove("is-running");
    });
    carousel.addEventListener("mouseleave", () => {
        paused = false;
        animateProgress();
        restart();
    });

    if (!reduceMotion && !window.matchMedia("(pointer: coarse)").matches) {
        const visual = carousel.querySelector(".hero-visual");
        carousel.addEventListener("pointermove", event => {
            if (!visual) return;
            const rect = carousel.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width - .5;
            const y = (event.clientY - rect.top) / rect.height - .5;
            visual.style.transform = `translate3d(${(x * 14).toFixed(1)}px,${(y * 10).toFixed(1)}px,0)`;
        });
        carousel.addEventListener("pointerleave", () => {
            if (visual) visual.style.transform = "";
        });
    }

    document.addEventListener("visibilitychange", () => {
        if (document.hidden) window.clearInterval(timer);
        else restart();
    });

    show(0);
    restart();
});
