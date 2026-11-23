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

// The layout's own timer shows the page if this file is late. If it has already fired, the visitor is looking at the finished
// page: hiding it again to animate it in would be a flicker, so the motion is skipped altogether.
const shownAlready = root.classList.contains('motion-failsafe');

if (root.classList.contains('has-motion') && !reduce && !shownAlready) {
    window.clearTimeout(window.__motionFailsafe);

    // If anything below fails to run, show the page rather than leave it blank.
    const failsafe = window.setTimeout(() => root.classList.add('motion-failsafe'), 1500);

    // Starting is not enough to stand the failsafe down: a throttled tab starts the timeline and then barely advances it.
    // It is cleared only once the title has actually arrived.
    const hero = gsap.timeline({ defaults: { ease: 'expo.out' } });
    hero.call(() => window.clearTimeout(failsafe), [], 1.1);
    hero
        .fromTo('.lp-hero__bg', { scale: 1.08 }, { scale: 1, duration: 2.4, ease: 'power2.out' }, 0)
        .fromTo('.lp-hero__title', { y: 28, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 1 }, 0.1)
        .fromTo('.lp-hero__sub', { y: 20, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.9 }, 0.3)
        .fromTo('.lp-search', { y: 24, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 1 }, 0.45)
        .fromTo('.hp-hero__actions', { y: 16, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.8 }, 0.75)
        .fromTo('.lp-hero__ask', { autoAlpha: 0 }, { autoAlpha: 1, duration: 0.6 }, 1.2);

    // Leaving the hero: the photograph drifts down slower than the page, the words lift away a little faster.
    const heroEnd = { trigger: '.lp-hero', start: 'top top', end: 'bottom top', scrub: true };
    gsap.to('.lp-hero__bg', { yPercent: 9, ease: 'none', scrollTrigger: heroEnd });
    gsap.to('[data-hero-inner]', { y: -56, ease: 'none', scrollTrigger: heroEnd });

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

    // Lines that fill as their steps are passed: how it works, and who InfoDot is for. Each step lights once the line has reached it.
    gsap.utils.toArray('[data-journey]').forEach((list) => {
        const stages = gsap.utils.toArray('[data-stage]', list);
        if (!stages.length) return;
        list.style.setProperty('--lp-progress', '0.02');
        stages[0].classList.add('is-passed');
        ScrollTrigger.create({
            trigger: list, start: 'top 80%', end: 'bottom 55%', scrub: 0.4,
            onUpdate: (self) => {
                list.style.setProperty('--lp-progress', String(Math.max(0.02, self.progress)));
                stages.forEach((stage, index) => stage.classList.toggle('is-passed', index === 0 || self.progress >= index / Math.max(1, stages.length - 1) * 0.92));
            },
        });
    });

    // Discover, Manage, Grow: the coloured plate wipes in from the left, then the points under it appear one line at a time.
    gsap.utils.toArray('[data-pillar]').forEach((pillar) => {
        const plate = pillar.querySelector('.hp-pillar__plate');
        const lines = pillar.querySelectorAll('.hp-pillar__list li');
        if (plate) {
            gsap.fromTo(plate, { clipPath: 'inset(0 100% 0 0)' }, {
                clipPath: 'inset(0 0% 0 0)', duration: 1, ease: 'expo.out', clearProps: 'clipPath',
                scrollTrigger: { trigger: pillar, start: 'top 82%', once: true },
            });
        }
        if (lines.length) {
            gsap.fromTo(lines, { clipPath: 'inset(0 0 100% 0)', y: 10 }, {
                clipPath: 'inset(0 0 0% 0)', y: 0, duration: 0.7, stagger: 0.09, ease: 'power3.out', clearProps: 'clipPath,transform',
                scrollTrigger: { trigger: pillar, start: 'top 72%', once: true },
            });
        }
    });

    // The tools: each group's names come into focus one after another as the group arrives.
    gsap.utils.toArray('[data-group]').forEach((group) => {
        const logos = group.querySelectorAll('[data-logo]');
        if (!logos.length) return;
        gsap.fromTo(logos, { filter: 'blur(9px)', scale: 0.9 }, {
            filter: 'blur(0px)', scale: 1, duration: 0.8, stagger: 0.06, ease: 'power3.out', clearProps: 'filter,transform',
            scrollTrigger: { trigger: group, start: 'top 88%', once: true },
        });
    });

    // A question becomes knowledge: the line runs through four stations, and the stamp lands on the third.
    const chain = document.querySelector('[data-chain]');
    if (chain) {
        const stations = gsap.utils.toArray('[data-station]', chain);
        const stamp = chain.querySelector('[data-stamp]');
        chain.style.setProperty('--lp-progress', '0.02');
        stations[0]?.classList.add('is-lit');
        ScrollTrigger.create({
            trigger: chain, start: 'top 78%', end: 'bottom 50%', scrub: 0.4,
            onUpdate: (self) => {
                chain.style.setProperty('--lp-progress', String(Math.max(0.02, self.progress)));
                stations.forEach((station, index) => station.classList.toggle('is-lit', index === 0 || self.progress >= index / Math.max(1, stations.length - 1) * 0.9));
                if (stamp && self.progress >= 2 / Math.max(1, stations.length - 1) * 0.9) stamp.classList.add('is-stamped');
            },
        });
    }

    // The closing line is written across the page once, and the photographs breathe in as they pass.
    const ctaTitle = document.querySelector('[data-cta-title]');
    if (ctaTitle) {
        gsap.fromTo(ctaTitle, { clipPath: 'inset(0 100% 0 0)' }, {
            clipPath: 'inset(0 0% 0 0)', duration: 1.1, ease: 'expo.out', clearProps: 'clipPath',
            scrollTrigger: { trigger: '.hp-cta', start: 'top 78%', once: true },
        });
    }
    gsap.utils.toArray('.lp-photo__frame img, .lp-band__frame img').forEach((img) => {
        gsap.fromTo(img, { scale: 1.1 }, {
            scale: 1, ease: 'none',
            scrollTrigger: { trigger: img.parentElement, start: 'top bottom', end: 'center center', scrub: true },
        });
    });

    window.addEventListener('load', () => ScrollTrigger.refresh());
}
