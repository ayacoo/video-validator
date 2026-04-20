import Viewport from '@typo3/backend/viewport.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';

const navigateTo = (url) => {
    const parsed = new URL(url, window.location.origin);
    Viewport.ContentContainer.setUrl(parsed.pathname + parsed.search);
};

const translate = (key, fallback) => {
    if (typeof TYPO3 !== 'undefined' && TYPO3.lang && TYPO3.lang[key]) {
        return TYPO3.lang[key];
    }
    return fallback;
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

const initRefreshButtons = () => {
    document.querySelectorAll('.video-validator-refresh').forEach((button) => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            const fileUid = parseInt(button.dataset.fileUid || '0', 10);
            if (!fileUid) {
                return;
            }
            const url = (TYPO3 && TYPO3.settings && TYPO3.settings.ajaxUrls)
                ? TYPO3.settings.ajaxUrls['videovalidator_refresh']
                : null;
            if (!url) {
                return;
            }

            button.disabled = true;
            button.classList.add('disabled');
            try {
                const response = await new AjaxRequest(url).post({ fileUid });
                const data = await response.resolve();
                if (data && data.success) {
                    Notification.success(
                        translate('videovalidator.refresh.success.title', 'Status refreshed'),
                        translate('videovalidator.refresh.success.message', 'The video status has been updated.'),
                    );
                    // Reload the module content so the status badge reflects the new state
                    navigateTo(window.location.pathname + window.location.search);
                } else {
                    throw new Error((data && data.message) || 'Refresh failed');
                }
            } catch (err) {
                Notification.error(
                    translate('videovalidator.refresh.error.title', 'Refresh failed'),
                    (err && err.message) || translate('videovalidator.refresh.error.message', 'The video status could not be refreshed.'),
                );
            } finally {
                button.disabled = false;
                button.classList.remove('disabled');
            }
        });
    });
};

initFilterForm();
initPagination();
initResetLink();
initRefreshButtons();
