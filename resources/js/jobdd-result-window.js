// A display window over the existing server page. Never sort, fetch or alter selection.
function initializeResultWindow(root) {
    const list = root.querySelector('[data-result-window]');
    if (!list || list.dataset.windowInitialized) return;
    const cards = [...list.querySelectorAll('[data-job-id]')];
    if (!cards.length) return;
    const range = list.querySelector('[data-result-range]');
    const controls = list.querySelector('[data-result-controls]');
    const previous = list.querySelector('[data-result-previous]');
    const next = list.querySelector('[data-result-next]');
    const end = list.querySelector('[data-result-end]');
    const pagination = root.querySelector('[data-result-pagination]');
    const offset = Number(list.dataset.offset);
    let start = 0;
    const render = (focus = false) => {
        cards.forEach((card, index) => { card.hidden = index < start || index >= start + 3; });
        range.textContent = `${offset + start + 1}〜${offset + Math.min(start + 3, cards.length)}件を表示しています`;
        previous.disabled = start === 0;
        const last = start + 3 >= cards.length;
        next.hidden = last && !list.dataset.nextUrl;
        next.textContent = last ? list.dataset.nextLabel : '次の3件を見る';
        end.hidden = !last || Boolean(list.dataset.nextUrl);
        pagination.dataset.windowEnd = String(last);
        if (focus) {
            range.focus({ preventScroll: true });
            range.scrollIntoView({ block: 'start', behavior: 'instant' });
        }
    };
    next.addEventListener('click', () => {
        if (start + 3 < cards.length) { start += 3; render(true); }
        else if (list.dataset.nextUrl) window.location.assign(list.dataset.nextUrl);
    });
    previous.addEventListener('click', () => { start = Math.max(0, start - 3); render(true); });
    // A comparison removal may return focus to a card outside the current window.
    root.addEventListener('jobdd:reveal-job', event => {
        const index = cards.findIndex(card => card.dataset.jobId === event.detail);
        if (index !== -1) { start = Math.floor(index / 3) * 3; render(); }
    });
    render();
    controls.hidden = cards.length <= 3 && !list.dataset.nextUrl;
    list.dataset.windowInitialized = 'true';
}

document.querySelectorAll('[data-jobdd-root]').forEach(initializeResultWindow);
