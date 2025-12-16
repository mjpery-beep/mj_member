(function () {
    const config = window.mjMemberGrimlinsGallery;

    if (!config || !config.ajaxUrl || !config.nonce) {
        return;
    }

    const rootSelector = '[data-grimlins-gallery]';

    function handleDeleteClick(event) {
        const trigger = event.target.closest('[data-grimlins-gallery-delete]');
        if (!trigger) {
            return;
        }

        event.preventDefault();

        const session = trigger.getAttribute('data-session');
        if (!session) {
            return;
        }

        const message = (config.i18n && config.i18n.confirmDelete) || 'Supprimer définitivement ?';
        if (!window.confirm(message)) {
            return;
        }

        trigger.disabled = true;
        trigger.classList.add('is-deleting');

        const formData = new URLSearchParams();
        formData.append('action', 'mj_member_delete_grimlins_session');
        formData.append('nonce', config.nonce);
        formData.append('session', session);

        fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: formData.toString(),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('http_error');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error('api_error');
                }

                const card = trigger.closest('.mj-grimlins-gallery__item');
                if (card) {
                    card.remove();
                }
            })
            .catch(function () {
                const fallback = (config.i18n && config.i18n.deleteError) || 'Suppression impossible.';
                window.alert(fallback);
                trigger.disabled = false;
                trigger.classList.remove('is-deleting');
            });
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest(rootSelector)) {
            return;
        }

        handleDeleteClick(event);
    });
})();
