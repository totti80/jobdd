// Keep the local illustration visible until the external image has actually loaded.
document.querySelectorAll('[data-eyecatch]').forEach((figure) => {
    const image = figure.querySelector('[data-eyecatch-external]');
    if (!image) return;
    const fallback = figure.querySelector('[data-eyecatch-fallback]');
    const caption = figure.querySelector('[data-eyecatch-caption]');
    image.addEventListener('load', () => {
        image.hidden = false;
        fallback.hidden = true;
        caption.textContent = '画像出典：掲載元求人ページ';
    });
    image.addEventListener('error', () => {
        image.hidden = true;
        fallback.hidden = false;
        caption.textContent = 'JobDDイメージ画像';
    });
    image.src = image.dataset.src;
});

document.querySelectorAll('[data-detail-application]').forEach((link) => {
    link.addEventListener('click', () => {
        // Native target=_blank owns navigation; logging neither waits nor opens another window.
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrf) return;
        [link.dataset.selectedUrl, link.dataset.contactUrl].forEach((url) => {
            fetch(url, {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ user_query_id: null, application_route_id: Number(link.dataset.detailApplication) }),
            }).catch(() => {});
        });
    });
});
