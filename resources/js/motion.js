// The motion system on the GSAP side (R-64 phase 2). The numbers here are the same as the CSS tokens in infodot.css (--ix-dur-*, --ix-rise);
// tests/Feature/MotionTokensTest.php fails if they drift. CSS handles hover, focus and small state changes by itself; this file is for what CSS
// cannot do well: staggered reveals as content scrolls in, and measured expand and collapse. Nothing here runs for a visitor who asked for reduced
// motion: reveal() shows the content at once and expand()/collapse() just set the end state.
import gsap from 'gsap';

/** Seconds, as GSAP wants them. Keep equal to the CSS custom properties. */
export const DUR = { fast: 0.12, base: 0.2, slow: 0.3, scene: 0.4, sceneSlow: 0.6 };

/** Pixels an element rises while it fades in. Keep equal to --ix-rise. */
export const RISE = 24;

/** GSAP equivalents of --ix-ease-out and --ix-ease-in-out. */
export const EASE = { out: 'expo.out', inOut: 'power2.inOut' };

/** Gap between the parts of a group that arrive one after another. */
export const STAGGER = 0.08;

export const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Parts of a section rising into place, one after another. Pass the elements that came into view together. Clears what it set, so the layout is
 * exactly what the stylesheet says once it is done.
 */
export function reveal(elements, { delay = 0 } = {}) {
    const targets = gsap.utils.toArray(elements);
    if (targets.length === 0) return null;

    if (reducedMotion()) {
        gsap.set(targets, { clearProps: 'opacity,visibility,transform' });
        return null;
    }

    return gsap.fromTo(
        targets,
        { y: RISE, autoAlpha: 0 },
        // However many parts arrive together, the last one is never more than 0.3s behind the first, so nothing waits off-stage.
        // Only the movement is cleared at the end. The element keeps its own "visible" so no stylesheet rule can hide it again (R-68).
        { y: 0, autoAlpha: 1, duration: DUR.scene + 0.2, ease: EASE.out, stagger: Math.min(STAGGER, 0.3 / targets.length), delay, overwrite: true, clearProps: 'transform' },
    );
}

/*
 * R-68: nothing disappears while you scroll. The stylesheet no longer hides scroll content; these two helpers hide only what is still below the
 * screen when the page's motion starts, bring it in once, and never hide it again. Anything already on screen or scrolled past (a restored scroll
 * position, an anchor link) is left exactly as the stylesheet draws it. A part is shown the first time its trigger is reached OR passed, so a fast
 * fling or a jump cannot skip it, and after every scroll a sweep shows anything that has reached the screen and is somehow still waiting.
 */

/** Where one-shot reveals start: the moment the element's top edge is on screen. */
export const REVEAL_LINE = 0.98;

const waiting = new Set();

/** True while the element's top edge is still below the reveal line. */
const belowScreen = (el) => el.getBoundingClientRect().top > window.innerHeight * REVEAL_LINE;

function settle(el) {
    if (!waiting.delete(el)) return;
    // Whatever its from-state was, the part ends as the stylesheet draws it, fully visible.
    gsap.set(el, { autoAlpha: 1, clearProps: 'transform,clipPath' });
}

let sweeping = false;

function startSweep(ScrollTrigger) {
    if (sweeping) return;
    sweeping = true;
    const sweep = () => waiting.forEach((el) => { if (!belowScreen(el)) settle(el); });
    ScrollTrigger.addEventListener('scrollEnd', sweep);
    ScrollTrigger.addEventListener('refresh', sweep);
    window.addEventListener('load', sweep);
}

/**
 * One part (or a set of parts sharing one trigger) arriving once. `from` and `to` are GSAP vars; `to` must not carry a scrollTrigger.
 * When `trigger` is already on screen or above it, nothing is hidden and nothing animates.
 */
export function arrive(ScrollTrigger, trigger, targets, from, to) {
    const els = gsap.utils.toArray(targets);
    if (!trigger || els.length === 0 || reducedMotion() || !belowScreen(trigger)) return;

    startSweep(ScrollTrigger);
    gsap.set(els, from);
    els.forEach((el) => waiting.add(el));

    let done = false;
    const go = () => {
        if (done) return;
        done = true;
        // From here the part is on its way to visible; the sweep leaves it alone.
        els.forEach((el) => waiting.delete(el));
        gsap.to(els, { ...to, autoAlpha: to.autoAlpha ?? 1, overwrite: true });
    };
    ScrollTrigger.create({ trigger, start: `top ${REVEAL_LINE * 100}%`, once: true, onEnter: go, onLeave: go, onEnterBack: go, onLeaveBack: go });
}

/**
 * Many independent parts arriving as they reach the screen, grouped when several arrive together. Same promise as arrive(): only parts still below
 * the screen are hidden, each is shown the first time it is reached or passed, and none is hidden again.
 */
export function arriveEach(ScrollTrigger, selector, { rise = 16, duration = 0.5 } = {}) {
    if (reducedMotion()) return;
    const els = gsap.utils.toArray(selector).filter(belowScreen);
    if (els.length === 0) return;

    startSweep(ScrollTrigger);
    gsap.set(els, { y: rise, autoAlpha: 0 });
    els.forEach((el) => waiting.add(el));

    const show = (batch) => {
        const fresh = batch.filter((el) => waiting.has(el));
        if (fresh.length === 0) return;
        fresh.forEach((el) => waiting.delete(el));
        gsap.to(fresh, {
            y: 0, autoAlpha: 1, duration, ease: 'power3.out', stagger: Math.min(0.06, 0.3 / fresh.length), overwrite: true, clearProps: 'transform',
        });
    };
    ScrollTrigger.batch(els, { start: `top ${REVEAL_LINE * 100}%`, once: true, onEnter: show, onLeave: show, onEnterBack: show, onLeaveBack: show });
}

/** Opens a collapsed block to its natural height and leaves it at `auto`. */
export function expand(element) {
    if (reducedMotion()) {
        gsap.set(element, { height: 'auto', autoAlpha: 1, clearProps: 'overflow' });
        return null;
    }

    return gsap.fromTo(element, { height: 0, autoAlpha: 0, overflow: 'hidden' }, { height: 'auto', autoAlpha: 1, duration: DUR.slow, ease: EASE.inOut, clearProps: 'overflow' });
}

/** Closes a block to nothing. */
export function collapse(element) {
    if (reducedMotion()) {
        gsap.set(element, { height: 0, autoAlpha: 0 });
        return null;
    }

    return gsap.to(element, { height: 0, autoAlpha: 0, overflow: 'hidden', duration: DUR.slow, ease: EASE.inOut });
}
