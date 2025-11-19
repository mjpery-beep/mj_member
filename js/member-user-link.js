jQuery(function($) {
    const config = window.mjMemberUsers || {};
    const roles = config.roles || {};
    const i18n = config.i18n || {};

    const modalHtml = `
<div id="mj-user-link-modal" class="mj-user-link-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:100000; align-items:center; justify-content:center;">
    <div class="mj-user-link-dialog" style="background:#fff; padding:24px 28px; border-radius:8px; max-width:480px; width:90%; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.25);">
        <button type="button" class="button mj-user-link-close" style="position:absolute; top:12px; right:12px;">✕</button>
        <h2 class="mj-user-link-title" style="margin-top:0; font-size:20px;"></h2>
        <p class="mj-user-link-subtitle" style="margin-bottom:18px; color:#555;"></p>
        <form id="mj-link-user-form">
            <input type="hidden" name="member_id" value="">
            <div class="mj-field" style="margin-bottom:16px;">
                <label for="mj-account-password" style="display:block; font-weight:600; margin-bottom:4px;">${i18n.accountPasswordLabel || 'Mot de passe du compte WordPress'}</label>
                <input type="password" id="mj-account-password" name="manual_password" autocomplete="new-password" style="width:100%;">
                <small style="color:#666;">${i18n.accountPasswordHint || 'Laissez vide pour générer un mot de passe automatique.'}</small>
            </div>
            <div class="mj-field" style="margin-bottom:18px;">
                <label for="mj-role-select" style="display:block; font-weight:600; margin-bottom:4px;">${i18n.roleLabel || 'Rôle WordPress attribué'}</label>
                <select id="mj-role-select" name="role" required style="width:100%;">
                    <option value="">${i18n.chooseRolePlaceholder || 'Sélectionnez un rôle…'}</option>
                </select>
            </div>
            <div class="mj-user-link-feedback" style="display:none; margin-bottom:16px; padding:12px; border-radius:4px;"></div>
            <div class="mj-user-link-password" style="display:none; margin-bottom:16px; padding:12px; border-radius:4px; background:#f0f6ff; color:#0b4a99;"></div>
            <div class="mj-user-link-actions" style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="button mj-user-link-cancel">${i18n.cancel || 'Annuler'}</button>
                <button type="submit" class="button button-primary mj-user-link-submit"></button>
            </div>
        </form>
    </div>
</div>`;

    $('body').append(modalHtml);

    const $modal = $('#mj-user-link-modal');
    const $dialog = $modal.find('.mj-user-link-dialog');
    const $form = $('#mj-link-user-form');
    const $title = $modal.find('.mj-user-link-title');
    const $subtitle = $modal.find('.mj-user-link-subtitle');
    const $roleSelect = $('#mj-role-select');
    const $manualPasswordInput = $('#mj-account-password');
    const $feedback = $modal.find('.mj-user-link-feedback');
    const $passwordBox = $modal.find('.mj-user-link-password');
    const $submitButton = $modal.find('.mj-user-link-submit');

    Object.keys(roles).forEach(function(roleKey) {
        $roleSelect.append('<option value="' + roleKey + '">' + roles[roleKey] + '</option>');
    });

    let currentMemberId = null;
    let isUpdateMode = false;

    function openModal(memberId, memberName, hasUser) {
        currentMemberId = memberId;
        isUpdateMode = hasUser === '1';

        $title.text(isUpdateMode ? (i18n.titleUpdate || 'Mettre à jour le compte WordPress') : (i18n.titleCreate || 'Créer un compte WordPress'));
        $subtitle.text(memberName ? memberName : '');
        $submitButton.text(isUpdateMode ? (i18n.submitUpdate || 'Mettre à jour') : (i18n.submitCreate || 'Créer le compte'));
        $form[0].reset();
        $form.find('input[name="member_id"]').val(memberId);
        $feedback.hide().removeClass('notice-success notice-error');
        $passwordBox.hide().empty();

        $modal.css('display', 'flex');
        setTimeout(function() {
            $dialog.attr('aria-hidden', 'false');
            $manualPasswordInput.trigger('focus');
        }, 10);
    }

    function closeModal() {
        $modal.hide();
        $dialog.attr('aria-hidden', 'true');
        currentMemberId = null;
        isUpdateMode = false;
    }

    function showMessage(message, type) {
        $feedback.text(message).show();
        if (type === 'success') {
            $feedback.css({ background: '#ecf7ed', color: '#1e4620', border: '1px solid #b7dfb9' });
        } else {
            $feedback.css({ background: '#fdecea', color: '#611a15', border: '1px solid #f5c6cb' });
        }
    }

    function renderPassword(login, password) {
        if (!password) {
            return;
        }

        const html = '<strong>' + (i18n.passwordLabel || 'Mot de passe généré :') + '</strong><br>' +
            '<code style="display:inline-block; margin:8px 0; padding:4px 8px; background:#fff; border-radius:4px;">' + $('<div>').text(password).html() + '</code>' +
            '<br><small>' + (login ? 'Login : ' + $('<div>').text(login).html() : '') + '</small>' +
            '<br><button type="button" class="button button-small mj-user-link-copy" data-password="' + $('<div>').text(password).html() + '">Copier</button>';

        $passwordBox.html(html).show();
    }

    function handleAjaxStart() {
        $submitButton.prop('disabled', true).attr('aria-busy', 'true');
    }

    function handleAjaxEnd() {
        $submitButton.prop('disabled', false).attr('aria-busy', 'false');
    }

    function copyToClipboard(text) {
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showMessage(i18n.copySuccess || 'Mot de passe copié dans le presse-papiers.', 'success');
            }).catch(function() {
                showMessage(i18n.errorGeneric || 'Une erreur est survenue. Merci de réessayer.', 'error');
            });
        } else {
            const temp = $('<textarea>').val(text).css({ position: 'fixed', left: '-9999px' });
            $('body').append(temp);
            temp[0].select();
            try {
                document.execCommand('copy');
                showMessage(i18n.copySuccess || 'Mot de passe copié dans le presse-papiers.', 'success');
            } catch (err) {
                showMessage(i18n.errorGeneric || 'Une erreur est survenue. Merci de réessayer.', 'error');
            }
            temp.remove();
        }
    }

    $(document).on('click', '.mj-link-user-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const memberId = $btn.data('member-id');
        const memberName = $btn.data('member-name') || '';
        const hasUser = $btn.data('has-user') ? String($btn.data('has-user')) : '0';
        openModal(memberId, memberName, hasUser);
    });

    $modal.on('click', '.mj-user-link-close, .mj-user-link-cancel', function(e) {
        e.preventDefault();
        closeModal();
    });

    $modal.on('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    $(document).on('keyup', function(e) {
        if (e.key === 'Escape' && $modal.is(':visible')) {
            closeModal();
        }
    });

    $form.on('submit', function(e) {
        e.preventDefault();
        if (!currentMemberId) {
            showMessage(i18n.errorGeneric || 'Une erreur est survenue. Merci de réessayer.', 'error');
            return;
        }

        const role = $roleSelect.val();
        if (!role) {
            $roleSelect.focus();
            showMessage(i18n.errorGeneric || 'Une erreur est survenue. Merci de réessayer.', 'error');
            return;
        }

        handleAjaxStart();
        $feedback.hide();
        $passwordBox.hide();

        $.ajax({
            method: 'POST',
            url: config.ajaxurl,
            data: {
                action: 'mj_link_member_user',
                nonce: config.nonce,
                member_id: currentMemberId,
                manual_password: $manualPasswordInput.val(),
                role: role
            }
        }).done(function(response) {
            if (!response || !response.success || !response.data) {
                showMessage((response && response.data && response.data.message) || (i18n.errorGeneric || 'Une erreur est survenue. Merci de réessayer.'), 'error');
                return;
            }

            const data = response.data;
            showMessage(data.message || (i18n.successLinked || 'Le compte WordPress est maintenant lié.'), 'success');
            renderPassword(data.login || '', data.generated_password || '');

            const $button = $('.mj-link-user-btn[data-member-id="' + currentMemberId + '"]');
            if ($button.length) {
                $button.text('Compte WP').data('has-user', '1');
            }

            if (data.user_edit_url) {
                const $cell = $button.closest('td');
                if ($cell.length && $cell.find('a[href="' + data.user_edit_url + '"]').length === 0) {
                    $('<a>', {
                        href: data.user_edit_url,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        class: 'button button-small mj-view-user-link',
                        text: 'Voir le compte'
                    }).appendTo($cell);
                }
            }

            $manualPasswordInput.val('');
        }).fail(function(xhr) {
            var message = i18n.errorGeneric || 'Une erreur est survenue. Merci de réessayer.';
            if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                message = xhr.responseJSON.data.message;
            }
            showMessage(message, 'error');
        }).always(function() {
            handleAjaxEnd();
        });
    });

    $modal.on('click', '.mj-user-link-copy', function(e) {
        e.preventDefault();
        const password = $(this).data('password');
        copyToClipboard(password);
    });
});
