// The landing page's motion. The page is complete without this file: it only adds a short opening, reveals on scroll and a
// slow parallax, all with transform and opacity, and none of it when the visitor has asked for reduced motion.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const root = document.documentElement;
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const input = document.getElementById('lp-q');

// A person with a pointer and a keyboard can start typing at once; on a touch screen this would only open the keyboard.
if (input && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
    input.focus({ preventScroll: true });
}

// The placeholder shows what people look up, one at a time, until the visitor focuses or types. Never when motion is reduced.
if (input && !reduce) {
    let examples = [];
    try { examples = JSON.parse(input.dataset.examples || '[]'); } catch (e) { examples = []; }
    let i = 0;
    const timer = examples.length > 1 ? window.setInterval(() => {
        if (document.activeElement === input || input.value !== '') return;
        i = (i + 1) % examples.length;
        input.placeholder = examples[i];
    }, 3600) : null;
    input.addEventListener('input', () => { if (timer && input.value !== '') window.clearInterval(timer); }, { once: true });
}

if (root.classList.contains('has-motion') && !reduce) {
    // If anything below fails to run, show the page rather than leave it blank.
    const failsafe = window.setTimeout(() => root.classList.add('motion-failsafe'), 3500);

    // The failsafe only matters if the animation never starts (a script error, a throttled tab); once it has started it is not needed.
    const hero = gsap.timeline({ defaults: { ease: 'expo.out' }, onStart: () => window.clearTimeout(failsafe) });
    hero
        .fromTo('.lp-hero__bg', { scale: 1.08 }, { scale: 1, duration: 2.4, ease: 'power2.out' }, 0)
        .fromTo('.lp-hero__title', { y: 28, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 1 }, 0.1)
        .fromTo('.lp-hero__sub', { y: 20, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.9 }, 0.3)
        .fromTo('.lp-search', { y: 24, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 1 }, 0.45)
        .fromTo('.lp-chips li', { y: 14, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.7, stagger: 0.07 }, 0.75)
        .fromTo('.lp-hero__ask', { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.6 }, 1.2);

    // The chips are children of a hidden list: let the list itself be visible so only they move.
    gsap.set('.lp-chips', { autoAlpha: 1 });

    // Below the hero: whatever comes into view rises into place, grouped so a section's parts arrive one after another.
    ScrollTrigger.batch('[data-reveal]', {
        start: 'top 90%',
        once: true,
        onEnter: (els) => gsap.fromTo(els, { y: 26, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.85, stagger: 0.09, ease: 'power3.out', overwrite: true }),
    });

    // Photographs drift a little against the scroll, so the page has depth without anything looping.
    gsap.utils.toArray('[data-parallax]').forEach((frame) => {
        const img = frame.querySelector('img');
        if (!img) return;
        gsap.fromTo(img, { yPercent: -5 }, {
            yPercent: 5, ease: 'none',
            scrollTrigger: { trigger: frame, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });

    // The journey line fills as the stages are passed, and each stage lights up once the line has reached it.
    const list = document.querySelector('[data-journey]');
    if (list) {
        const stages = gsap.utils.toArray('[data-stage]', list);
        list.style.setProperty('--lp-progress', '0.02');
        stages[0]?.classList.add('is-passed');
        ScrollTrigger.create({
            trigger: list, start: 'top 80%', end: 'bottom 55%', scrub: 0.4,
            onUpdate: (self) => {
                list.style.setProperty('--lp-progress', String(Math.max(0.02, self.progress)));
                stages.forEach((stage, index) => stage.classList.toggle('is-passed', index === 0 || self.progress >= index / (stages.length - 1) * 0.92));
            },
        });
    }

    window.addEventListener('load', () => ScrollTrigger.refresh());
}
