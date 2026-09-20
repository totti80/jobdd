function initializeJobdd(root) {
    if (root.dataset.uiInitialized) return;
    root.dataset.uiInitialized = 'true';

    const errors = root.querySelector('[data-input-errors]');
    if (errors) {
        errors.focus();
        errors.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-error-target]');
            if (!link) return;
            const input = document.getElementById(link.dataset.errorTarget);
            if (input) { event.preventDefault(); input.focus(); }
        });
    }

    const comparison = root.querySelector('.jobdd-comparison');
    if (comparison && typeof ResizeObserver !== 'undefined') {
        const header = comparison.querySelector('thead');
        const measureHeader = () => comparison.style.setProperty('--jobdd-header-height', header.getBoundingClientRect().height + 'px');
        new ResizeObserver(measureHeader).observe(header);
        measureHeader();
    }

    const form = root.querySelector('#compare-selection');
    if (!form) return;
    const checkboxes = [...root.querySelectorAll('input[name="jobs[]"][form="compare-selection"]')];
    const selectedList = root.querySelector('[data-selected-jobs]');
    const counts = root.querySelectorAll('[data-selection-count]');
    const buttons = root.querySelectorAll('[data-compare-submit]');
    const announcement = root.querySelector('[data-selection-announcement]');
    const bar = root.querySelector('[data-mobile-compare]');

    const render = (notice = '') => {
        const selected = checkboxes.filter(input => input.checked);
        const count = selected.length;
        const message = count === 0 ? '比較する求人を2〜3件選んでください' : count === 1 ? '比較中1求人。あと1件選んでください' : '比較中' + count + '求人';
        counts.forEach(node => { node.textContent = message; });
        buttons.forEach(button => button.setAttribute('aria-disabled', String(count < 2 || count > 3)));
        checkboxes.forEach(input => { input.closest('[data-job-id]').dataset.selected = String(input.checked); });
        selectedList.replaceChildren();
        selected.forEach(input => {
            const item = document.createElement('li');
            item.className = 'flex min-w-0 items-start justify-between gap-3 border-t border-slate-200 py-3';
            const name = document.createElement('span');
            name.className = 'min-w-0 text-sm leading-6';
            name.textContent = input.dataset.jobLabel;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'jobdd-link min-h-11 shrink-0 px-2';
            remove.textContent = '解除';
            remove.setAttribute('aria-label', input.dataset.jobLabel + 'を比較から解除');
            remove.addEventListener('click', () => { input.checked = false; render('比較から解除しました。'); root.dispatchEvent(new Event('jobdd:show-list')); input.focus(); });
            item.append(name, remove); selectedList.append(item);
        });
        announcement.textContent = notice || message;
    };
    checkboxes.forEach(input => input.addEventListener('change', () => {
        if (checkboxes.filter(box => box.checked).length > 3) {
            input.checked = false;
            render('比較できるのは3求人までです');
        } else render();
    }));
    form.addEventListener('submit', event => {
        const count = checkboxes.filter(input => input.checked).length;
        if (count < 2 || count > 3) {
            event.preventDefault();
            render();
            const target = checkboxes.find(input => !input.checked) || checkboxes[0];
            root.dispatchEvent(new Event('jobdd:show-list'));
            target?.focus();
        }
    });
    render();
    // Without ResizeObserver the form stays in normal flow; no obscuring fixed bar.
    if (bar && typeof ResizeObserver !== 'undefined') {
        root.classList.add('jobdd-selection-enhanced');
        const measure = () => {
            const height = bar.getBoundingClientRect().height;
            root.style.setProperty('--jobdd-bar-height', height + 'px');
            document.documentElement.style.scrollPaddingBottom = height + 16 + 'px';
        };
        new ResizeObserver(measure).observe(bar);
        measure();
    }
    window.addEventListener('pageshow', () => render());
}

document.querySelectorAll('[data-jobdd-root]').forEach(initializeJobdd);
