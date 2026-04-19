import Viewport from '@typo3/backend/viewport.js';

const navigateTo = (url) => {
    const parsed = new URL(url, window.location.origin);
    Viewport.ContentContainer.setUrl(parsed.pathname + parsed.search);
};

const initFilterForm = () => {
    const form = document.getElementById('video-validator-filter');
    if (!form) {
        return;
    }
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const actionUrl = new URL(form.action, window.location.origin);
        new URLSearchParams(new FormData(form)).forEach((value, key) => {
            actionUrl.searchParams.set(key, value);
        });
        actionUrl.searchParams.set('page', '1');
        navigateTo(actionUrl.pathname + actionUrl.search);
    });
};

const initPagination = () => {
    document.querySelectorAll('.video-validator-pagination a[href]').forEach((link) => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            navigateTo(link.getAttribute('href'));
        });
    });
};

const initResetLink = () => {
    const link = document.getElementById('video-validator-reset');
    if (!link) {
        return;
    }
    link.addEventListener('click', (e) => {
        e.preventDefault();
        navigateTo(link.getAttribute('href'));
    });
};

initFilterForm();
initPagination();
initResetLink();
