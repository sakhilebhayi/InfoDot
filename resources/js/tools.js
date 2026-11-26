// The Business Tools page's motion. The page is complete without this file: it only adds an opening, reveals on scroll and a slow
// parallax, all with transform, opacity and clip-path, and none of it when the visitor has asked for reduced motion. Two small helpers
// work with or without motion because they are navigation, not decoration: the rail that shows which group you are in, and the
// highlight on the platform a "what does your business need" link lands on.
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { arrive, arriveEach } from './motion.js';

gsap.registerPlugin(ScrollTrigger);

const root = document.documentElement;
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// A need's link lands on a platform: mark it for a moment so the eye finds it.
document.querySelectorAll('[data-need-link], .bt-pillar__tools a, .bt-tool__works a').forEach((link) => {
    link.addEventListener('click', () => {
        const target = document.getElementById(`tool-${(link.dataset.needLink || link.getAttribute('href') || '').replace(/^#?tool-/, '')}`);
        if (!target) return;
        target.classList.add('is-matched');
        window.setTimeout(() => target.classList.remove('is-matched'), 2600);
    });
});

// The rail beside the platforms follows the group that is on screen.
const railLinks = new Map([...document.querySelectorAll('[data-rail-link]')].map((a) => [a.dataset.railLink, a]));
gsap.utils.toArray('[data-group]').forEach((group) => {
    const key = group.id.replace(/^group-/, '');
    ScrollTrigger.create({
        trigger: group, start: 'top 45%', end: 'bottom 45%',
        onToggle: (self) => {
            if (!self.isActive) return;
            railLinks.forEach((a) => a.classList.remove('is-active'));
            railLinks.get(key)?.classList.add('is-active');
        },
    });
});

// The layout's own timer shows the page if this file is late. If it has already fired, the visitor is looking at the finished page:
// hiding it again to animate it in would be a flicker, so the motion is skipped altogether.
const shownAlready = root.classList.contains('motion-failsafe');

if (root.classList.contains('has-motion') && !reduce && !shownAlready) {
    window.clearTimeout(window.__motionFailsafe);

    // If anything below fails to run, show the page rather than leave it blank.
    const failsafe = window.setTimeout(() => root.classList.add('motion-failsafe'), 1500);

    // The opening: the words arrive, then the screens settle into the yard one after another and the gate lands last.
    const intro = gsap.timeline({ defaults: { ease: 'expo.out' } });
    intro.call(() => window.clearTimeout(failsafe), [], 1.1);
    intro
        .fromTo('[data-bt-hero]', { y: 26, autoAlpha: 0 }, { y: 0, autoAlpha: 1, duration: 1, stagger: 0.12 }, 0.05)
        .fromTo('.bt-bay', { y: 70, scale: 0.94, autoAlpha: 0 }, { y: 0, scale: 1, autoAlpha: 1, duration: 1.2, stagger: 0.1 }, 0.1)
        .fromTo('.bt-gate', { scale: 0.85, autoAlpha: 0 }, { scale: 1, autoAlpha: 1, duration: 0.8 }, 0.9);

    // Leaving the opening: each screen drifts at its own depth, so the yard has distance. The words lift away a little.
    const heroEnd = { trigger: '.bt-hero', start: 'top top', end: 'bottom top', scrub: true };
    gsap.utils.toArray('.bt-bay').forEach((bay) => {
        gsap.to(bay, { yPercent: -parseFloat(bay.dataset.depth || '1') * 7, ease: 'none', scrollTrigger: heroEnd });
    });
    gsap.to('.bt-hero__copy', { y: -44, ease: 'none', scrollTrigger: heroEnd });

    // Whatever comes into view rises into place, grouped so a section's parts arrive one after another.
    // Content starts arriving the moment its top edge is on screen and is fully there within about half a second, so a normal scroll
    // never lands on blank space. A group that arrives together shares at most 0.3s of stagger, however many parts it has.
    // R-68: only what is still below the screen is hidden, each part arrives once and stays (motion.js).
    arriveEach(ScrollTrigger, '[data-reveal]');

    // Discover, Manage, Grow: each coloured plate wipes in from the left.
    gsap.utils.toArray('[data-pillar]').forEach((pillar) => {
        const plate = pillar.querySelector('.bt-pillar__word');
        if (!plate) return;
        arrive(ScrollTrigger, pillar, plate, { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)', duration: 0.7, ease: 'expo.out', clearProps: 'clipPath' });
    });

    // Each group opens with its stencilled plate stamped in.
    gsap.utils.toArray('.bt-plate--lg').forEach((plate) => {
        arrive(ScrollTrigger, plate, plate, { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)', duration: 0.8, ease: 'expo.out', clearProps: 'clipPath' });
    });

    // Every platform is its own bay: its screen is uncovered from the side its text is not on, the picture settles as you pass,
    // then the story and the capabilities arrive in turn.
    gsap.utils.toArray('[data-tool]').forEach((tool) => {
        const frame = tool.querySelector('.bt-frame');
        const img = frame?.querySelector('img');
        const variant = [...tool.classList].find((c) => c.startsWith('bt-tool--'))?.slice(9);
        const from = variant === 'a' ? 'inset(0 100% 0 0)' : variant === 'b' ? 'inset(0 0 0 100%)' : 'inset(100% 0 0 0)';
        if (frame) {
            arrive(ScrollTrigger, tool, frame, { clipPath: from }, { clipPath: 'inset(0% 0% 0% 0%)', duration: 0.8, ease: 'expo.out', clearProps: 'clipPath' });
        }
        if (img) {
            gsap.fromTo(img, { scale: 1.12 }, {
                scale: 1, ease: 'none',
                scrollTrigger: { trigger: frame, start: 'top bottom', end: 'center center', scrub: true },
            });
        }
        const parts = tool.querySelectorAll('.bt-tool__body > *');
        if (parts.length) {
            arrive(ScrollTrigger, tool, parts, { y: 22, autoAlpha: 0 },
                { y: 0, autoAlpha: 1, duration: 0.5, stagger: Math.min(0.05, 0.3 / parts.length), ease: 'power3.out', clearProps: 'transform' });
        }
        const lines = tool.querySelectorAll('.bt-tool__caps li');
        if (lines.length) {
            arrive(ScrollTrigger, tool, lines, { clipPath: 'inset(0 0 100% 0)' },
                { clipPath: 'inset(0 0 0% 0)', duration: 0.5, stagger: Math.min(0.05, 0.3 / lines.length), ease: 'power3.out', clearProps: 'clipPath', delay: 0.1 });
        }
    });

    // How access works: the line fills as the four steps are passed, and each marker lights once the line has reached it.
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

    // The closing line is written across the page once.
    const ctaTitle = document.querySelector('[data-cta-title]');
    if (ctaTitle) {
        arrive(ScrollTrigger, document.querySelector('.bt-cta'), ctaTitle, { clipPath: 'inset(0 100% 0 0)' }, { clipPath: 'inset(0 0% 0 0)', duration: 1.1, ease: 'expo.out', clearProps: 'clipPath' });
    }

    window.addEventListener('load', () => ScrollTrigger.refresh());
}
