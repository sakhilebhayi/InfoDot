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
        { y: 0, autoAlpha: 1, duration: DUR.scene + 0.2, ease: EASE.out, stagger: Math.min(STAGGER, 0.3 / targets.length), delay, overwrite: true, clearProps: 'opacity,visibility,transform' },
    );
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
