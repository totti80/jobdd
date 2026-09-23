document.querySelectorAll('[data-authoring-rows]').forEach((section) => {
    const rows = section.querySelector('[data-rows]');
    const add = section.querySelector('[data-add-row]');
    const status = section.querySelector('[data-row-status]');
    const refresh = () => {
        add.disabled = rows.children.length >= 30;
        status.textContent = `${rows.children.length} / 30行`;
    };
    section.addEventListener('click', (event) => {
        if (event.target.closest('[data-remove-row]')) {
            event.target.closest('[data-row]').remove();
            refresh();
        }
        if (event.target.closest('[data-add-row]') && rows.children.length < 30) {
            const index = Number(section.dataset.nextIndex);
            section.dataset.nextIndex = index + 1;
            rows.insertAdjacentHTML('beforeend', section.querySelector('template').innerHTML.replaceAll('__INDEX__', index));
            rows.lastElementChild.querySelector('input, select, textarea').focus();
            refresh();
        }
    });
    section.addEventListener('change', (event) => {
        if (!event.target.name?.endsWith('[tool_key]')) return;
        const name = event.target.closest('[data-row]').querySelector('input[name$="[tool_name]"]');
        if (event.target.value && event.target.value !== 'other_tool' && (!name.value || name.value === name.dataset.preset)) {
            name.value = event.target.selectedOptions[0].textContent;
            name.dataset.preset = name.value;
        }
    });
    refresh();
});
