// Reveal as content scrolls into view (R-64 phase 9). Elements marked data-ix-reveal rise and fade in, a little apart from each other when several arrive
// together. Built on IntersectionObserver (the browser tells us when something enters: there is no scroll handler and no per-frame measuring) and on CSS
// transitions with the motion tokens; nothing here needs GSAP. Without this file, or under reduced motion, nothing is hidden and nothing moves.

const GROUP_LIMIT = 8; // beyond this many in one group the stagger stops growing, so a long list never waits

function start() {
    const items = [...document.querySelectorAll('[data-ix-reveal]')];
    if (items.length === 0 || window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) return;

    const root = document.documentElement;
    root.classList.add('ix-reveal-ready');

    // If anything keeps the observer from running (a slow device, a throttled tab), show everything rather than leave a page half-drawn.
    const failsafe = window.setTimeout(() => items.forEach((el) => el.classList.add('is-in')), 1800);

    const observer = new IntersectionObserver((entries) => {
        window.clearTimeout(failsafe);

        entries.filter((e) => e.isIntersecting).forEach((entry, index) => {
            entry.target.style.setProperty('--i', String(Math.min(index, GROUP_LIMIT)));
            entry.target.classList.add('is-in');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });

    items.forEach((el) => observer.observe(el));
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
else start();
