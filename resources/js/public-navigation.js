// Native details remains usable without JavaScript. Escape restores keyboard focus.
document.querySelectorAll('[data-site-menu]').forEach(menu => {
    const desktop = window.matchMedia('(min-width: 1200px)');
    const sync = () => { menu.open = desktop.matches; };
    sync();
    desktop.addEventListener('change', sync);
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape' && !desktop.matches && menu.open) {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
});
