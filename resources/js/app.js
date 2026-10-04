import './jobdd-ui';

import './jobdd-map';

import './company-authoring';

import './public-navigation';

import './homepage-jobs';

import './job-detail';

const backToTop = document.querySelector('[data-back-to-top]');
if (backToTop) {
    const updateVisibility = () => { backToTop.hidden = window.scrollY < 400; };
    window.addEventListener('scroll', updateVisibility, { passive: true });
    window.addEventListener('pageshow', updateVisibility);
    backToTop.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
    });
    updateVisibility();
}
