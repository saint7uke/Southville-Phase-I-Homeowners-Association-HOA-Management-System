import { useLayoutEffect } from 'react';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export default function useLandingAnimations(pageRef) {
    useLayoutEffect(() => {
        const context = gsap.context(() => {
            const media = gsap.matchMedia();

            media.add('(prefers-reduced-motion: no-preference)', () => {
                gsap.timeline({ defaults: { ease: 'power3.out' } })
                    .from('[data-nav]', { autoAlpha: 0, y: -20, duration: 0.65 })
                    .from('[data-hero-reveal]', { autoAlpha: 0, y: 30, duration: 0.7, stagger: 0.11 }, '-=0.25')
                    .from('[data-scroll-indicator]', { autoAlpha: 0, y: 10, duration: 0.45 }, '-=0.1');

                gsap.utils.toArray('[data-reveal-group]').forEach((group) => {
                    const items = group.querySelectorAll('[data-reveal]');
                    if (!items.length) return;

                    gsap.from(items, {
                        autoAlpha: 0,
                        y: 34,
                        duration: 0.7,
                        stagger: 0.09,
                        ease: 'power2.out',
                        scrollTrigger: { trigger: group, start: 'top 82%', once: true },
                    });
                });

                document.querySelectorAll('[data-counter]').forEach((counter) => {
                    const target = Number(counter.dataset.counter || 0);
                    const suffix = counter.dataset.suffix || '';
                    const state = { value: 0 };

                    gsap.to(state, {
                        value: target,
                        duration: 1.35,
                        ease: 'power2.out',
                        snap: { value: 1 },
                        scrollTrigger: { trigger: counter, start: 'top 88%', once: true },
                        onUpdate: () => { counter.textContent = `${state.value}${suffix}`; },
                    });
                });

                gsap.utils.toArray('[data-benefit]').forEach((item, index) => {
                    gsap.from(item, {
                        autoAlpha: 0,
                        x: index % 2 === 0 ? -36 : 36,
                        duration: 0.7,
                        ease: 'power2.out',
                        scrollTrigger: { trigger: item, start: 'top 86%', once: true },
                    });
                });
            });

            media.add('(min-width: 769px) and (prefers-reduced-motion: no-preference)', () => {
                gsap.to('[data-hero-image]', {
                    yPercent: 12,
                    scale: 1.08,
                    ease: 'none',
                    scrollTrigger: { trigger: '#home', start: 'top top', end: 'bottom top', scrub: 0.8 },
                });
                gsap.to('[data-hero-content]', {
                    yPercent: -8,
                    ease: 'none',
                    scrollTrigger: { trigger: '#home', start: 'top top', end: 'bottom top', scrub: 0.8 },
                });
                gsap.to('[data-parallax-back]', {
                    yPercent: -10,
                    ease: 'none',
                    scrollTrigger: { trigger: '[data-parallax-section]', start: 'top bottom', end: 'bottom top', scrub: 1 },
                });
                gsap.to('[data-parallax-front]', {
                    yPercent: 8,
                    ease: 'none',
                    scrollTrigger: { trigger: '[data-parallax-section]', start: 'top bottom', end: 'bottom top', scrub: 1 },
                });
            });

            return () => media.revert();
        }, pageRef);

        return () => context.revert();
    }, [pageRef]);
}
