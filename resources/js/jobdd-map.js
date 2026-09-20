function initializeMap(root) {
    const toggle = root.querySelector('[data-map-toggle]');
    if (!toggle || toggle.dataset.initialized) return;
    const list = root.querySelector('[data-list-view]');
    const map = root.querySelector('[data-map-view]');
    const layout = root.querySelector('[data-discovery-layout]');
    const error = root.querySelector('[data-map-error]');
    const content = root.querySelector('[data-map-content]');
    const views = [...toggle.querySelectorAll('[data-jobdd-view]')];
    const listButton = views.find(button => button.dataset.jobddView === 'list');
    let failed = false;
    const showView = (view) => {
        const isMap = view === 'map';
        list.hidden = isMap;
        map.hidden = !isMap;
        layout.classList.toggle('jobdd-map-active', isMap);
        views.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.jobddView === view)));
    };
    const fail = () => {
        failed = true;
        error.hidden = false;
        if (content) content.hidden = true;
        showView('map');
        error.querySelector('button').focus();
    };
    const guard = handler => event => {
        try { handler(event); } catch { fail(); }
    };
    views.forEach(button => button.addEventListener('click', guard(() => showView(button.dataset.jobddView))));
    root.addEventListener('jobdd:show-list', () => showView('list'));
    root.querySelector('[data-map-return]').addEventListener('click', () => { showView('list'); listButton.focus(); });
    try {
        const markers = [...map.querySelectorAll('[data-map-marker]')];
        const missing = map.querySelector('[data-map-missing]');
        const cards = [...map.querySelectorAll('[data-map-job]')];
        const heading = map.querySelector('[data-map-heading]');
        const announcement = map.querySelector('[data-map-announcement]');
        const clear = map.querySelector('[data-map-clear]');
        let selectedKey = null;
        let origin = null;
        const selectRegion = (key, label, button) => {
            selectedKey = key;
            origin = button;
            markers.forEach(marker => {
                const selected = marker.dataset.mapMarker === key;
                marker.setAttribute('aria-pressed', String(selected));
                marker.setAttribute('aria-label', `${marker.dataset.region}。${marker.dataset.count}件の求人候補${selected ? '。選択中' : ''}`);
            });
            missing?.setAttribute('aria-pressed', String(key === 'unknown'));
            let count = 0;
            cards.forEach(card => {
                card.hidden = card.dataset.pointKey !== key;
                card.dataset.mapSelected = 'false';
                card.querySelector('[data-map-select-job]').setAttribute('aria-pressed', 'false');
                if (!card.hidden) count++;
            });
            heading.textContent = key ? label : '府県を選ぶと求人候補を表示します';
            announcement.textContent = !key ? '表示する府県を選んでください。比較の選択とは別の操作です。' : count ? `このページ内の${count}件を一覧と同じ順序で表示しています。` : 'このページには、この府県の求人候補はありません。';
            clear.hidden = !key;
        };
        markers.forEach(marker => marker.addEventListener('click', guard(() => {
            selectRegion(marker.dataset.mapMarker, marker.dataset.region, marker);
            heading.focus();
        })));
        missing?.addEventListener('click', guard(() => { selectRegion('unknown', '位置表示未設定の求人', missing); heading.focus(); }));
        cards.forEach(card => card.querySelector('[data-map-select-job]').addEventListener('click', guard(() => {
            cards.forEach(other => {
                const selected = other === card;
                other.dataset.mapSelected = String(selected);
                other.querySelector('[data-map-select-job]').setAttribute('aria-pressed', String(selected));
            });
            announcement.textContent = '位置図で求人を選択しました。比較には追加していません。';
        })));
        clear?.addEventListener('click', guard(() => {
            const previous = origin;
            selectRegion(null, '', null);
            previous?.focus();
        }));
        // Restore list on refresh/back-cache; never change canonical comparison inputs.
        window.addEventListener('pageshow', () => {
            showView('list');
            if (!failed && selectedKey) selectRegion(null, '', null);
        });
        showView('list');
        toggle.dataset.initialized = 'true';
        toggle.hidden = false;
    } catch {
        // Initialization failure keeps the usable list and exposes a recovery notice.
        toggle.hidden = false;
        fail();
    }
}

document.querySelectorAll('[data-jobdd-root]').forEach(initializeMap);
