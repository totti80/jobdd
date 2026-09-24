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

    const valid = value => Array.isArray(value) ? value.filter(item => item && typeof item.id === 'string' && /^[1-9]\d{0,15}$/.test(item.id) && Number.isSafeInteger(Number(item.id)) && Number(item.id) > 0 && typeof item.label === 'string').filter((item, index, all) => all.findIndex(other => other.id === item.id) === index).slice(0, 3).map(item => ({ id: item.id, label: item.label.slice(0, 300) })) : [];
    const read = storageKey => { try { return valid(JSON.parse(sessionStorage.getItem(storageKey) || '[]')); } catch { return []; } };
    root.querySelectorAll('[data-compare-entry]').forEach(entry => {
        const update = () => {
            const link = entry.querySelector('[data-compare-entry-link]');
            const selected = read('jobdd:compare:' + entry.dataset.queryId);
            link.hidden = selected.length < 2 || entry.dataset.invalidSelection === 'true';
            if (link.hidden) return;
            const url = new URL(entry.dataset.resolveUrl, window.location.href);
            selected.forEach(item => url.searchParams.append('jobs[]', item.id));
            link.href = url.href;
            link.textContent = `選択した${selected.length}件を比較する`;
        };
        update();
        window.addEventListener('pageshow', update);
    });
    root.querySelectorAll('[data-compare-add]').forEach(link => link.addEventListener('click', () => {
        const storageKey = 'jobdd:compare:' + link.dataset.queryId;
        const selected = read(storageKey);
        if (selected.length < 3 && !selected.some(item => item.id === link.dataset.compareAdd)) {
            selected.push({ id: link.dataset.compareAdd, label: link.dataset.jobLabel });
            try { sessionStorage.setItem(storageKey, JSON.stringify(valid(selected))); } catch { /* List-page fallback remains available. */ }
        }
    }));

    const form = root.querySelector('#compare-selection');
    if (!form) return;
    const checkboxes = [...root.querySelectorAll('input[name="jobs[]"][form="compare-selection"]')];
    const selectedList = root.querySelector('[data-selected-jobs]');
    const counts = root.querySelectorAll('[data-selection-count]');
    const buttons = root.querySelectorAll('[data-compare-submit]');
    const announcement = root.querySelector('[data-selection-announcement]');
    const bar = root.querySelector('[data-mobile-compare]');

    const storageKey = 'jobdd:compare:' + form.dataset.queryId;
    let selected = read(storageKey);
    const render = (notice = '') => {
        try { sessionStorage.setItem(storageKey, JSON.stringify(selected)); } catch {
            notice = notice || 'このブラウザではページをまたぐ選択保持を利用できません。';
        }
        checkboxes.forEach(input => { input.checked = selected.some(item => item.id === input.value); });
        form.querySelectorAll('[data-selected-id]').forEach(input => input.remove());
        selected.forEach(item => {
            const input = document.createElement('input');
            input.type = 'hidden'; input.name = 'jobs[]'; input.value = item.id; input.dataset.selectedId = '';
            form.append(input);
        });
        // Hidden inputs preserve selection order, including jobs on other pages.
        checkboxes.forEach(input => { input.removeAttribute('name'); });
        const count = selected.length;
        const message = count === 0 ? '比較する求人を2〜3件選んでください' : count === 1 ? '比較中1求人。あと1件選んでください' : '比較中' + count + '求人';
        counts.forEach(node => { node.textContent = message; });
        buttons.forEach(button => button.setAttribute('aria-disabled', String(count < 2 || count > 3)));
        checkboxes.forEach(input => { input.closest('[data-job-id]').dataset.selected = String(input.checked); });
        selectedList.replaceChildren();
        selected.forEach(selection => {
            const item = document.createElement('li');
            item.className = 'flex min-w-0 items-start justify-between gap-3 border-t border-slate-200 py-3';
            const name = document.createElement('span');
            name.className = 'min-w-0 text-sm leading-6';
            name.textContent = selection.label;
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'jobdd-link min-h-11 shrink-0 px-2';
            remove.textContent = '解除';
            remove.setAttribute('aria-label', selection.label + 'を比較から解除');
            remove.addEventListener('click', () => { selected = selected.filter(item => item.id !== selection.id); render('比較から解除しました。'); root.dispatchEvent(new Event('jobdd:show-list')); (checkboxes.find(input => input.value === selection.id) || buttons[0])?.focus(); });
            item.append(name, remove); selectedList.append(item);
        });
        announcement.textContent = notice || message;
    };
    checkboxes.forEach(input => input.addEventListener('change', () => {
        if (input.checked && selected.length >= 3) {
            render('比較できるのは3求人までです');
        } else {
            selected = selected.filter(item => item.id !== input.value);
            if (input.checked) selected.push({ id: input.value, label: input.dataset.jobLabel });
            render();
        }
    }));
    form.addEventListener('submit', event => {
        const count = selected.length;
        if (count < 2 || count > 3) {
            event.preventDefault();
            render();
            const target = checkboxes.find(input => !input.checked) || checkboxes[0];
            root.dispatchEvent(new Event('jobdd:show-list'));
            target?.focus();
        }
    });
    // The detail CTA joins the selection retained for this query in this tab.
    const requestedJob = new URLSearchParams(window.location.search).get('select_job');
    const requestedInput = /^\d+$/.test(requestedJob || '')
        ? checkboxes.find(input => input.value === requestedJob) : null;
    if (requestedInput && !selected.some(item => item.id === requestedInput.value) && selected.length < 3) {
        selected.push({ id: requestedInput.value, label: requestedInput.dataset.jobLabel });
    }
    const full = requestedJob && !selected.some(item => item.id === requestedJob) && selected.length >= 3;
    render(full ? '比較できるのは3求人までです。解除してから追加してください。' : '');
    if (requestedJob) {
        const url = new URL(window.location.href); url.searchParams.delete('select_job');
        history.replaceState(null, '', url);
    }
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
    window.addEventListener('pageshow', event => { if (event.persisted) { selected = read(storageKey); render(); } });
}

document.querySelectorAll('[data-jobdd-root]').forEach(initializeJobdd);
