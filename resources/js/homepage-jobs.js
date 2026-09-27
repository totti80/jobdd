// Native overflow remains usable without JS. Only visual copies repeat; tab order stays unique.
document.querySelectorAll('[data-jobs-carousel]').forEach(section => {
    const rail = section.querySelector('[data-jobs-rail]');
    const controls = section.querySelector('[data-rail-controls]');
    if (!rail || !controls) return;
    const cards = [...rail.children];
    const previous = controls.querySelector('[data-rail-prev]');
    const next = controls.querySelector('[data-rail-next]');
    const toggle = controls.querySelector('[data-rail-toggle]');
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
    let loopWidth = 0;
    let paused = false;
    let pointerDown = false;
    let hovered = false;
    let resumeAt = 0;
    let frame = 0;
    let last = 0;
    let position = rail.scrollLeft;

    const blocked = () => paused || reduced.matches || hovered || pointerDown
        || section.matches(':focus-within') || document.hidden || performance.now() < resumeAt;
    const step = () => cards[0].getBoundingClientRect().width + (parseFloat(getComputedStyle(rail).gap) || 0);
    const tick = time => {
        if (!blocked() && loopWidth) {
            position += Math.min(time - (last || time), 64) * 0.025;
            if (position >= loopWidth) position -= loopWidth;
            rail.scrollLeft = position;
        } else position = rail.scrollLeft;
        last = time;
        frame = requestAnimationFrame(tick);
    };
    const sync = () => {
        cancelAnimationFrame(frame);
        rail.querySelectorAll('[data-rail-copy]').forEach(copy => copy.remove());
        loopWidth = 0;
        const overflowing = rail.scrollWidth > rail.clientWidth + 1;
        controls.hidden = !overflowing;
        toggle.hidden = reduced.matches;
        if (overflowing && !reduced.matches) {
            loopWidth = step() * cards.length;
            cards.forEach(card => {
                const copy = card.cloneNode(true);
                copy.removeAttribute('data-new-job');
                copy.setAttribute('data-rail-copy', '');
                copy.setAttribute('aria-hidden', 'true');
                copy.querySelectorAll('a').forEach(link => {
                    link.tabIndex = -1;
                    // Mouse activation keeps focus out of the accessibility-hidden copy.
                    link.addEventListener('mousedown', event => event.preventDefault());
                });
                rail.append(copy);
            });
            position = rail.scrollLeft % loopWidth;
            rail.scrollLeft = position;
            last = 0;
            frame = requestAnimationFrame(tick);
        }
    };
    const move = direction => {
        resumeAt = performance.now() + 2500;
        rail.scrollBy({ left: direction * step(), behavior: reduced.matches ? 'instant' : 'smooth' });
    };
    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    toggle.addEventListener('click', () => {
        paused = !paused;
        toggle.setAttribute('aria-pressed', String(paused));
        toggle.textContent = paused ? '自動送りを再開' : '自動送りを停止';
    });
    section.addEventListener('pointerenter', event => { if (event.pointerType === 'mouse') hovered = true; });
    section.addEventListener('pointerleave', () => { hovered = false; });
    rail.addEventListener('pointerdown', () => { pointerDown = true; });
    const release = () => { pointerDown = false; resumeAt = performance.now() + 2500; };
    window.addEventListener('pointerup', release);
    window.addEventListener('pointercancel', release);
    rail.addEventListener('wheel', () => { resumeAt = performance.now() + 2500; }, { passive: true });
    rail.addEventListener('keydown', event => {
        if (event.target !== rail || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') move(event.key === 'ArrowLeft' ? -1 : 1);
        else rail.scrollTo({ left: event.key === 'Home' ? 0 : step() * cards.length - rail.clientWidth - 16, behavior: 'instant' });
    });
    // Keyboard focus never wraps or resets. The browser reveals each original link naturally.
    reduced.addEventListener('change', sync);
    new ResizeObserver(sync).observe(rail);
    sync();
});
