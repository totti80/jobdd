// Manual-only rail. Native scrolling and links remain available without JavaScript.
document.querySelectorAll('[data-jobs-carousel]').forEach(section => {
    const rail = section.querySelector('[data-jobs-rail]');
    const controls = section.querySelector('[data-rail-controls]');
    if (!rail || !controls) return;
    const previous = controls.querySelector('[data-rail-prev]');
    const next = controls.querySelector('[data-rail-next]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const sync = () => {
        const end = Math.max(0, rail.scrollWidth - rail.clientWidth);
        controls.hidden = end <= 1;
        // Keep buttons focusable at an edge so activating one never loses keyboard focus.
        previous.setAttribute('aria-disabled', String(rail.scrollLeft <= 1));
        next.setAttribute('aria-disabled', String(rail.scrollLeft >= end - 1));
    };
    const move = direction => {
        const button = direction < 0 ? previous : next;
        if (button.getAttribute('aria-disabled') === 'true') return;
        const card = rail.querySelector('[data-new-job]');
        const gap = parseFloat(getComputedStyle(rail).columnGap) || 0;
        rail.scrollBy({ left: direction * (card.getBoundingClientRect().width + gap), behavior: reducedMotion.matches ? 'instant' : 'smooth' });
    };
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    rail.addEventListener('scroll', sync, { passive: true });
    rail.addEventListener('keydown', event => {
        if (event.target !== rail || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') move(event.key === 'ArrowLeft' ? -1 : 1);
        else rail.scrollTo({ left: event.key === 'Home' ? 0 : rail.scrollWidth, behavior: 'instant' });
    });
    if ('ResizeObserver' in window) new ResizeObserver(sync).observe(rail);
    window.addEventListener('resize', sync);
    sync();
});
