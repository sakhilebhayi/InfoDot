// Motion for the public inner pages (Contact, Businesses, About, What InfoDot does, Questions people ask). Every page is complete without
// this file: it only adds an opening, reveals as parts come into view, and a slow drift on photographs, using transform, opacity and
// clip-path. Nothing here runs for a visitor who asked for reduced motion, and the layout's own timer shows the page if this file is late.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { reducedMotion, reveal } from './motion.js';

gsap.registerPlugin(ScrollTrigger);

const root = document.documentElement;
const reduce = reducedMotion();
const shownAlready = root.classList.contains('motion-failsafe');

if (root.classList.contains('has-motion') && !reduce && !shownAlready) {
    window.clearTimeout(window.__motionFailsafe);
    let intro = null;
    // If the opening has not finished (a slow device, a throttled tab), show the finished page instead of a half-drawn one.
    const failsafe = window.setTimeout(() => {
        root.classList.add('motion-failsafe');
        if (intro) intro.progress(1);
    }, 1500);

    // The opening: the plate stamps in, the words arrive in turn, then the photograph is uncovered from the side.
    intro = gsap.timeline({ defaults: { ease: 'expo.out' } });
    intro.call(() => window.clearTimeout(failsafe), [], 1.2);
    intro
        .fromTo('[data-pg-plate]', { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)', duration: 0.8, clearProps: 'clipPath' }, 0)
        .fromTo('[data-pg-in]', { y: 24, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 0.9, stagger: 0.1 }, 0.1)
        .fromTo('[data-pg-photo]', { clipPath: 'inset(0 0 0 100%)' }, { clipPath: 'inset(0 0 0 0%)', duration: 1.2, clearProps: 'clipPath' }, 0.15);

    // The photograph settles as it is passed.
    gsap.utils.toArray('[data-pg-photo] img').forEach((img) => {
        gsap.fromTo(img, { scale: 1.12 }, {
            scale: 1, ease: 'none',
            scrollTrigger: { trigger: img.closest('[data-pg-photo]'), start: 'top bottom', end: 'bottom 40%', scrub: true },
        });
    });

    // Whatever comes into view rises into place, grouped so a section's parts arrive one after another.
    ScrollTrigger.batch('[data-pg-reveal], .pg-prose > h2, .pg-prose > h2 + *', {
        start: 'top 98%',
        once: true,
        onEnter: (els) => reveal(els),
    });

    // A stencilled code plate is stamped as its section arrives.
    gsap.utils.toArray('[data-pg-stamp]').forEach((plate) => {
        gsap.fromTo(plate, { clipPath: 'inset(0 100% 0 0)' }, {
            clipPath: 'inset(0 0% 0 0)', duration: 0.7, ease: 'expo.out', clearProps: 'clipPath',
            scrollTrigger: { trigger: plate, start: 'top 98%', once: true },
        });
    });

    window.addEventListener('load', () => ScrollTrigger.refresh());
}
