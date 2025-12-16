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
                <label for="mj-account-login" style="display:block; font-weight:600; margin-bottom:4px;">${i18n.accountLoginLabel || 'Identifiant du compte WordPress'}</label>
                <input type="text" id="mj-account-login" name="manual_login" autocomplete="username" style="width:100%;" maxlength="60" placeholder="${i18n.accountLoginPlaceholder || 'Ex. prenom.nom'}">
                <small style="color:#666; display:block; margin-top:4px;">${i18n.accountLoginHint || 'Choisissez un identifiant unique (lettres, chiffres, points et tirets). Laissez vide pour proposer automatiquement un identifiant.'}</small>
            </div>
            <div class="mj-field" style="margin-bottom:16px;">
                <label for="mj-account-password" style="display:block; font-weight:600; margin-bottom:4px;">${i18n.accountPasswordLabel || 'Mot de passe du compte WordPress'}</label>
                <div class="mj-password-row" style="display:flex; gap:8px;">
                    <input type="password" id="mj-account-password" name="manual_password" autocomplete="new-password" style="flex:1 1 auto;">
                    <button type="button" class="button mj-user-link-suggest" style="flex:0 0 auto; white-space:nowrap;">${i18n.suggestPassword || 'Suggérer un mot de passe'}</button>
                </div>
                <small style="color:#666; display:block; margin-top:4px;">${i18n.accountPasswordHint || 'Laissez vide pour générer un mot de passe automatique ou utilisez la suggestion sécurisée (7 caractères).'}</small>
                <div class="mj-password-suggestion" style="display:none; margin-top:10px; padding:10px; background:#f0f6ff; border-radius:6px; color:#0b4a99;"></div>
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
    const $manualLoginInput = $('#mj-account-login');
    const $manualPasswordInput = $('#mj-account-password');
    const $feedback = $modal.find('.mj-user-link-feedback');
    const $passwordBox = $modal.find('.mj-user-link-password');
    const $suggestionBox = $modal.find('.mj-password-suggestion');
    const $submitButton = $modal.find('.mj-user-link-submit');
    const utils = window.MjMemberUtils || {};
    const escapeHtml = typeof utils.escapeHtml === 'function'
        ? utils.escapeHtml
        : function(value) {
            return $('<div>').text(value == null ? '' : value).html();
        };

    const SUGGESTED_PASSWORD_LENGTH = 7;
    const roleStyles = {
        administrator: { icon: '👑', classSuffix: 'administrator' },
        editor: { icon: '📝', classSuffix: 'editor' },
        author: { icon: '✍️', classSuffix: 'author' },
        contributor: { icon: '🧾', classSuffix: 'contributor' },
        subscriber: { icon: '🙋', classSuffix: 'subscriber' },
        'shop-manager': { icon: '🛒', classSuffix: 'shop-manager' },
        default: { icon: '👤', classSuffix: 'default' }
    };
    let lastSuggestedPassword = '';

    Object.keys(roles).forEach(function(roleKey) {
        $roleSelect.append('<option value="' + roleKey + '">' + roles[roleKey] + '</option>');
    });

    let currentMemberId = null;
    let isUpdateMode = false;

    function openModal(memberId, memberName, hasUser, currentLogin, currentWpRole) {
        currentMemberId = memberId;
        isUpdateMode = hasUser === '1';

        $title.text(isUpdateMode ? (i18n.titleUpdate || 'Mettre à jour le compte WordPress') : (i18n.titleCreate || 'Créer un compte WordPress'));
        $subtitle.text(memberName ? memberName : '');
        $submitButton.text(isUpdateMode ? (i18n.submitUpdate || 'Mettre à jour') : (i18n.submitCreate || 'Créer le compte'));
        $form[0].reset();
        $form.find('input[name="member_id"]').val(memberId);
        $manualLoginInput.val((currentLogin || '').trim());
        if (currentWpRole && $roleSelect.find('option[value="' + currentWpRole + '"]').length) {
            $roleSelect.val(currentWpRole);
        } else {
            $roleSelect.val('');
        }
        $feedback.hide().removeClass('notice-success notice-error');
        $passwordBox.hide().empty();
        $suggestionBox.hide().empty();
        lastSuggestedPassword = '';

        $modal.css('display', 'flex');
        setTimeout(function() {
            $dialog.attr('aria-hidden', 'false');
            if ($manualLoginInput.length) {
                if ($manualLoginInput.val()) {
                    $manualLoginInput[0].select();
                }
                $manualLoginInput.trigger('focus');
            } else {
                $manualPasswordInput.trigger('focus');
            }
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

        const escapedPassword = escapeHtml(password);
        const escapedLogin = login ? escapeHtml(login) : '';
        const html = '<strong>' + (i18n.passwordLabel || 'Mot de passe généré :') + '</strong><br>' +
            '<code style="display:inline-block; margin:8px 0; padding:4px 8px; background:#fff; border-radius:4px;">' + escapedPassword + '</code>' +
            '<br><small>' + (escapedLogin ? 'Login : ' + escapedLogin : '') + '</small>' +
            '<br><button type="button" class="button button-small mj-user-link-copy" data-password="' + escapedPassword + '">' + (i18n.copyLabel || 'Copier') + '</button>';

        $passwordBox.html(html).show();
    }

    function renderSuggestion(password) {
        if (!password) {
            $suggestionBox.hide().empty();
            lastSuggestedPassword = '';
            return;
        }

        const escapedPassword = escapeHtml(password);
        const html = '<strong>' + (i18n.suggestedPasswordLabel || 'Mot de passe suggéré :') + '</strong><br>' +
            '<code style="display:inline-block; margin:6px 0; padding:4px 8px; background:#fff; border-radius:4px; font-size:13px;">' + escapedPassword + '</code>' +
            '<br><button type="button" class="button button-small mj-user-link-copy" data-password="' + escapedPassword + '">' + (i18n.copyLabel || 'Copier') + '</button>';

        $suggestionBox.html(html).show();
        lastSuggestedPassword = password;
    }

    function normalizeRoleKey(roleKey) {
        if (!roleKey) {
            return '';
        }
        return String(roleKey).trim().toLowerCase().replace(/[^a-z0-9_-]/g, '').replace(/_/g, '-');
    }

    function getRoleVisual(roleKey) {
        const normalized = normalizeRoleKey(roleKey);
        if (normalized && roleStyles[normalized]) {
            return {
                icon: roleStyles[normalized].icon,
                classSuffix: roleStyles[normalized].classSuffix,
                normalized: normalized
            };
        }

        if (normalized) {
            return {
                icon: roleStyles.default.icon,
                classSuffix: normalized,
                normalized: normalized
            };
        }

        return {
            icon: roleStyles.default.icon,
            classSuffix: roleStyles.default.classSuffix,
            normalized: ''
        };
    }

    function updateLoginCell(memberId, login, roleKey, roleLabel, editUrl) {
        const $button = $('.mj-link-user-btn[data-member-id="' + memberId + '"]');
        if (!$button.length) {
            return;
        }

        const $cell = $button.closest('.mj-login-cell');
        if (!$cell.length) {
            return;
        }

        let resolvedEditUrl = editUrl;
        if (!resolvedEditUrl) {
            resolvedEditUrl = $button.attr('data-user-edit-url') || '';
        } else {
            $button.attr('data-user-edit-url', resolvedEditUrl);
            $button.data('user-edit-url', resolvedEditUrl);
        }

        const trimmedLogin = (login || '').trim();
        let $pill = $cell.find('.mj-login-pill');
        if (trimmedLogin) {
            if (!$pill.length) {
                $pill = $('<span>', { 'class': 'mj-login-pill' });
                const $actions = $cell.find('.mj-login-actions').first();
                if ($actions.length) {
                    $pill.insertBefore($actions);
                } else {
                    $cell.prepend($pill);
                }
            }

            const roleVisual = getRoleVisual(roleKey);
            $pill.removeClass(function(_, className) {
                return (className.match(/mj-login-pill--role-[^\s]+/g) || []).join(' ');
            });
            $pill.removeClass('mj-login-pill--missing');

            if (roleVisual.classSuffix) {
                $pill.addClass('mj-login-pill--role-' + roleVisual.classSuffix);
            }

            const iconHtml = roleVisual.icon ? '<span class="mj-login-icon" aria-hidden="true">' + escapeHtml(roleVisual.icon) + '</span>' : '';
            let markup = iconHtml + '<span class="mj-login-text">' + escapeHtml(trimmedLogin) + '</span>';
            if (resolvedEditUrl) {
                markup = '<a href="' + escapeHtml(resolvedEditUrl) + '" target="_blank" rel="noopener noreferrer">' + markup + '</a>';
            }
            $pill.html(markup);

            if (roleLabel) {
                const template = i18n.roleTitleTemplate || 'Rôle WordPress : %s';
                $pill.attr('title', template.replace('%s', roleLabel));
            } else {
                $pill.removeAttr('title');
            }
        } else if ($pill.length) {
            $pill.remove();
        }
    }

    function randomChar(charset) {
        const randomIndex = Math.floor(Math.random() * charset.length);
        return charset.charAt(randomIndex);
    }

    function shuffleArray(array) {
        for (let i = array.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            const temp = array[i];
            array[i] = array[j];
            array[j] = temp;
        }
        return array;
    }

    function generateSecurePassword(length) {
        const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lower = 'abcdefghijkmnopqrstuvwxyz';
        const digits = '23456789';
        const symbols = '!@$%&*?';
        const all = upper + lower + digits + symbols;

        let passwordChars = [
            randomChar(upper),
            randomChar(lower),
            randomChar(digits),
            randomChar(symbols)
        ];

        while (passwordChars.length < length) {
            passwordChars.push(randomChar(all));
        }

        passwordChars = shuffleArray(passwordChars).slice(0, length);
        return passwordChars.join('');
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
        const loginValue = $btn.data('login') ? String($btn.data('login')) : '';
        const wpRole = $btn.data('wp-role') ? String($btn.data('wp-role')) : '';
        openModal(memberId, memberName, hasUser, loginValue, wpRole);
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

        const manualLoginValue = ($manualLoginInput.val() || '').trim();
        $manualLoginInput.val(manualLoginValue);

        $.ajax({
            method: 'POST',
            url: config.ajaxurl,
            data: {
                action: 'mj_link_member_user',
                nonce: config.nonce,
                member_id: currentMemberId,
                manual_login: manualLoginValue,
                manual_password: $manualPasswordInput.val(),
                role: role
            }
        }).done(function(response) {
            if (!response || !response.success || !response.data) {
                showMessage((response && response.data && response.data.message) || (i18n.errorGeneric || 'Une erreur est survenue. Merci de réessayer.'), 'error');
                return;
            }

            const data = response.data;
            const memberLogin = data.member_login || data.login || '';
            const roleKey = data.role || '';
            const roleLabel = data.role_label || '';
            const editUrl = data.user_edit_url || '';
            const userEmail = data.user_email || '';

            showMessage(data.message || (i18n.successLinked || 'Le compte WordPress est maintenant lié.'), 'success');
            renderPassword(memberLogin, data.generated_password || '');

            const $button = $('.mj-link-user-btn[data-member-id="' + currentMemberId + '"]');
            if ($button.length) {
                $button
                    .data('has-user', '1')
                    .data('login', memberLogin)
                    .data('wp-role', roleKey)
                    .data('wp-role-label', roleLabel)
                    .removeClass('mj-member-login-action--create')
                    .addClass('mj-member-login-action')
                    .html('🔎 ' + (i18n.detailsLabel || 'Détails'));

                $button.attr('data-has-user', '1');
                $button.attr('data-login', memberLogin);
                $button.attr('data-wp-role', roleKey);
                $button.attr('data-wp-role-label', roleLabel);
                if (editUrl) {
                    $button.attr('data-user-edit-url', editUrl);
                    $button.data('user-edit-url', editUrl);
                }
            }

            updateLoginCell(currentMemberId, memberLogin, roleKey, roleLabel, editUrl);

            $manualPasswordInput.val('');
            $manualLoginInput.val(memberLogin);
            $suggestionBox.hide().empty();
            lastSuggestedPassword = '';

            const $resetButton = $('.mj-reset-password-btn[data-member-id="' + currentMemberId + '"]');
            if ($resetButton.length) {
                if (memberLogin) {
                    $resetButton.attr('data-login', memberLogin);
                    $resetButton.data('login', memberLogin);
                }
                if (userEmail) {
                    $resetButton.attr('data-email', userEmail);
                    $resetButton.data('email', userEmail);
                }
            }
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

    $modal.on('click', '.mj-user-link-suggest', function(e) {
        e.preventDefault();
        const suggested = generateSecurePassword(SUGGESTED_PASSWORD_LENGTH);
        $manualPasswordInput.val(suggested);
        if ($manualPasswordInput[0]) {
            $manualPasswordInput[0].focus();
            $manualPasswordInput[0].select();
        }
        showMessage(i18n.passwordSuggested || 'Mot de passe suggéré et rempli.', 'success');
        renderSuggestion(suggested);
    });

    $manualPasswordInput.on('input', function() {
        const currentValue = $manualPasswordInput.val();
        if (!currentValue) {
            renderSuggestion('');
            return;
        }

        if (lastSuggestedPassword && currentValue === lastSuggestedPassword) {
            renderSuggestion(lastSuggestedPassword);
        } else if (lastSuggestedPassword) {
            $suggestionBox.hide().empty();
        }
    });
});
