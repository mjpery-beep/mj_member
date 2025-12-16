jQuery(document).ready(function($) {
    const allowedRoles = Array.isArray(mjMembers.allowedRoles) ? mjMembers.allowedRoles : [];
    const roleLabels = typeof mjMembers.roleLabels === 'object' && mjMembers.roleLabels !== null ? mjMembers.roleLabels : {};
    const statusLabels = typeof mjMembers.statusLabels === 'object' && mjMembers.statusLabels !== null ? mjMembers.statusLabels : { active: 'Actif', inactive: 'Inactif' };
    const photoConsentLabels = typeof mjMembers.photoConsentLabels === 'object' && mjMembers.photoConsentLabels !== null ? mjMembers.photoConsentLabels : { 1: 'Accepté', 0: 'Refusé' };
    const volunteerLabels = typeof mjMembers.volunteerLabels === 'object' && mjMembers.volunteerLabels !== null ? mjMembers.volunteerLabels : { yes: 'Bénévole', no: 'Non bénévole' };
    const labels = typeof mjMembers.labels === 'object' && mjMembers.labels !== null ? mjMembers.labels : {};
    const utils = window.MjMemberUtils || {};
    const escapeHtml = typeof utils.escapeHtml === 'function'
        ? utils.escapeHtml
        : function(value) {
            return $('<div>').text(value == null ? '' : value).html();
        };

    function normalizeValue(value) {
        return value == null ? '' : String(value);
    }

    function getLabel(key, fallback) {
        if (Object.prototype.hasOwnProperty.call(labels, key)) {
            const value = labels[key];
            return value == null ? fallback : String(value);
        }
        return fallback;
    }

    function parseBooleanFlag(value) {
        const normalized = normalizeValue(value).toLowerCase();
        return !(normalized === '' || normalized === '0' || normalized === 'false' || normalized === 'no' || normalized === 'non');
    }

    function formatIsoDateDisplay(value) {
        const normalized = normalizeValue(value);
        if (normalized === '') {
            return '';
        }

        const isoCandidate = normalized.slice(0, 10);
        const parts = isoCandidate.split('-');
        if (parts.length === 3) {
            const year = parts[0];
            const month = parts[1];
            const day = parts[2];
            if (year.length === 4) {
                return day.padStart(2, '0') + '/' + month.padStart(2, '0') + '/' + year;
            }
        }

        return normalized;
    }

    function formatPaymentDateDisplay(value, requiresPayment) {
        if (!requiresPayment) {
            return escapeHtml(getLabel('paymentNotRequired', 'Non concerné'));
        }

        const normalized = normalizeValue(value);
        if (normalized === '') {
            return escapeHtml(getLabel('paymentNone', 'Aucun paiement'));
        }

        return escapeHtml(formatIsoDateDisplay(normalized));
    }

    function updatePaymentMetaAppearance($cell, requiresPayment, normalizedValue) {
        if (!requiresPayment) {
            $cell.addClass('mj-payment-meta-value--muted');
            return;
        }

        const normalized = normalizeValue(normalizedValue);
        if (normalized === '') {
            $cell.addClass('mj-payment-meta-value--muted');
        } else {
            $cell.removeClass('mj-payment-meta-value--muted');
        }
    }

    function clearFieldMessage($cell) {
        const memberId = normalizeValue($cell.data('member-id'));
        const fieldName = normalizeValue($cell.data('field-name'));

        if (memberId === '' || fieldName === '') {
            return;
        }

        const selector = '.mj-inline-feedback';
        const filterMatches = function() {
            const siblingMemberId = normalizeValue($(this).attr('data-member-id'));
            const siblingFieldName = normalizeValue($(this).attr('data-field-name'));
            return siblingMemberId === memberId && siblingFieldName === fieldName;
        };

        $cell.siblings(selector).filter(filterMatches).remove();

        const $parent = $cell.parent();
        if ($parent.length) {
            $parent.children(selector).filter(filterMatches).remove();
        }

        const $td = $cell.closest('td');
        if ($td.length) {
            $td.children(selector).filter(filterMatches).remove();
        }
    }

    function showFieldMessage($cell, message, type) {
        if (!message) {
            return;
        }

        const memberId = normalizeValue($cell.data('member-id'));
        const fieldName = normalizeValue($cell.data('field-name'));

        if (memberId === '' || fieldName === '') {
            return;
        }

        clearFieldMessage($cell);

        const $feedback = $('<div>')
            .addClass('mj-inline-feedback')
            .attr('data-member-id', memberId)
            .attr('data-field-name', fieldName)
            .text(message);

        if (type === 'error') {
            $feedback.addClass('mj-inline-feedback--error');
        } else if (type === 'success') {
            $feedback.addClass('mj-inline-feedback--success');
        }

        const $parent = $cell.parent();
        if ($parent.length && $parent[0] !== $cell[0]) {
            $feedback.insertBefore($cell);
            return;
        }

        const $td = $cell.closest('td');
        if ($td.length && $td[0] !== $cell[0]) {
            $feedback.insertBefore($cell);
            return;
        }

        $cell.before($feedback);
    }

    function formatRoleBadge(role) {
        const key = normalizeValue(role);
        const label = roleLabels[key] || key;
        return '<span class="badge" style="display:inline-flex;align-items:center;gap:4px;background-color:#eef1ff;color:#1d2b6b;padding:3px 8px;border-radius:12px;font-size:12px;">' + escapeHtml(label) + '</span>';
    }

    function formatStatusBadge(status) {
        const key = normalizeValue(status).toLowerCase();
        const label = statusLabels[key] || statusLabels[normalizeValue(status)] || key;
        const isActive = key === 'active';
        const background = isActive ? '#28a745' : '#fd7e14';
        return '<span class="badge" style="background-color:' + background + ';color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;">' + escapeHtml(label) + '</span>';
    }

    function formatPhotoConsentBadge(value) {
        const normalized = normalizeValue(value);
        const accepted = normalized === '1' || normalized === 'true' || normalized === 'yes';
        const label = accepted ? (photoConsentLabels['1'] || 'Accepté') : (photoConsentLabels['0'] || 'Refusé');
        const background = accepted ? '#28a745' : '#d63638';
        return '<span class="badge" style="background-color:' + background + ';color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;">' + escapeHtml(label) + '</span>';
    }

    function formatVolunteerBadge(value) {
        const normalized = normalizeValue(value).toLowerCase();
        const isVolunteer = !(normalized === '' || normalized === '0' || normalized === 'false' || normalized === 'no' || normalized === 'non');
        const label = isVolunteer ? (volunteerLabels.yes || 'Bénévole') : (volunteerLabels.no || 'Non bénévole');
        const background = isVolunteer ? '#fef3c7' : '#e2e8f0';
        const color = isVolunteer ? '#92400e' : '#64748b';
        return '<span class="badge" style="background-color:' + background + ';color:' + color + ';padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;">' + escapeHtml(label) + '</span>';
    }

    function formatEmailLink(email) {
        const clean = normalizeValue(email);
        if (clean === '') {
            return 'N/A';
        }
        const escaped = escapeHtml(clean);
        return '<a href="mailto:' + escaped + '">' + escaped + '</a>';
    }

    function getDisplayHtml(fieldName, fieldValue) {
        const normalized = normalizeValue(fieldValue);
        switch (fieldName) {
            case 'status':
                return formatStatusBadge(normalized);
            case 'role':
                return formatRoleBadge(normalized);
            case 'photo_usage_consent':
                return formatPhotoConsentBadge(normalized);
            case 'is_volunteer':
                return formatVolunteerBadge(normalized);
            case 'email':
                return formatEmailLink(normalized);
            case 'date_last_payement':
                return formatPaymentDateDisplay(normalized, true);
            case 'birth_date':
                if (normalized === '') {
                    return '<span style="color:#999;">—</span>';
                }

                var parts = normalized.split('-');
                if (parts.length === 3) {
                    var year = parseInt(parts[0], 10);
                    var month = parseInt(parts[1], 10);
                    var day = parseInt(parts[2], 10);

                    if (!Number.isNaN(year) && !Number.isNaN(month) && !Number.isNaN(day)) {
                        var today = new Date();
                        var age = today.getFullYear() - year;
                        var monthDiff = (today.getMonth() + 1) - month;
                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < day)) {
                            age -= 1;
                        }
                        if (age < 0) {
                            age = 0;
                        }

                        var formattedDay = String(day).padStart(2, '0');
                        var formattedMonth = String(month).padStart(2, '0');
                        var formattedDate = formattedDay + '/' + formattedMonth + '/' + year;
                        var ageLabel = age + ' ans';

                        return escapeHtml(ageLabel) + '<br><span class="mj-birth-date-display" style="color:#555;font-size:12px;">' + escapeHtml(formattedDate) + '</span>';
                    }
                }

                return escapeHtml(normalized);
            default:
                return escapeHtml(normalized);
        }
    }

    function buildSelect(fieldName, memberId, initialValue) {
        const value = normalizeValue(initialValue);
        let choices = [];

        if (fieldName === 'status') {
            choices = ['active', 'inactive'].map(function(key) {
                return {
                    value: key,
                    label: statusLabels[key] || key,
                };
            });
        } else if (fieldName === 'role') {
            const roles = allowedRoles.length ? allowedRoles : Object.keys(roleLabels);
            choices = roles
                .map(function(roleKey) {
                    const normalizedKey = normalizeValue(roleKey);
                    if (normalizedKey === '') {
                        return null;
                    }
                    return {
                        value: normalizedKey,
                        label: roleLabels[normalizedKey] || normalizedKey,
                    };
                })
                .filter(Boolean);
        } else if (fieldName === 'photo_usage_consent') {
            choices = [
                { value: '1', label: photoConsentLabels['1'] || 'Accepté' },
                { value: '0', label: photoConsentLabels['0'] || 'Refusé' },
            ];
        } else if (fieldName === 'is_volunteer') {
            choices = [
                { value: '1', label: volunteerLabels.yes || 'Bénévole' },
                { value: '0', label: volunteerLabels.no || 'Non bénévole' },
            ];
        }

        if (choices.length > 0) {
            const cancelLabel = getLabel('cancel', 'Annuler');
            let buttons = '';
            choices.forEach(function(choice) {
                const optionValue = normalizeValue(choice.value);
                const isActive = optionValue === value;
                const activeClass = isActive ? ' mj-inline-choice__btn--active' : '';
                buttons += '<button type="button" class="mj-inline-choice__btn' + activeClass + '" data-value="' + escapeHtml(optionValue) + '" aria-pressed="' + (isActive ? 'true' : 'false') + '">' + escapeHtml(choice.label) + '</button>';
            });

            const memberIdAttr = escapeHtml(memberId);
            const fieldNameAttr = escapeHtml(fieldName);

            return ''
                + '<div class="mj-inline-choice" data-member-id="' + memberIdAttr + '" data-field-name="' + fieldNameAttr + '">' 
                + '<input type="hidden" class="mj-inline-choice__input" data-member-id="' + memberIdAttr + '" data-field-name="' + fieldNameAttr + '" value="' + escapeHtml(value) + '">' 
                + '<div class="mj-inline-choice__grid">' + buttons + '</div>' 
                + '<button type="button" class="mj-inline-choice__cancel" aria-label="' + escapeHtml(cancelLabel) + '">' + escapeHtml(cancelLabel) + '</button>' 
                + '</div>';
        }

        // Fallback pour les champs inattendus : select classique
        const safeValue = escapeHtml(value);
        return '<select class="mj-inline-input" data-member-id="' + escapeHtml(memberId) + '" data-field-name="' + escapeHtml(fieldName) + '">' + '<option value="' + safeValue + '" selected>' + safeValue + '</option>' + '</select>';
    }

    function buildInput(fieldType, fieldName, memberId, value) {
        const normalized = normalizeValue(value);
        if (fieldType === 'select' || fieldName === 'status' || fieldName === 'role' || fieldName === 'photo_usage_consent' || fieldName === 'is_volunteer') {
            return buildSelect(fieldName, memberId, normalized);
        }

        const inputType = fieldType === 'date' ? 'date' : (fieldType === 'email' ? 'email' : 'text');
        return '<input type="' + inputType + '" class="mj-inline-input" data-member-id="' + escapeHtml(memberId) + '" data-field-name="' + escapeHtml(fieldName) + '" value="' + escapeHtml(normalized) + '">';
    }

    function restoreEditableCell($cell, memberId, fieldName, displayHtml, fieldValue) {
        $cell.html(displayHtml);
        $cell.attr('data-member-id', memberId);
        $cell.attr('data-field-name', fieldName);

        $cell.removeData('saving');
        if (typeof fieldValue !== 'undefined') {
            const normalized = normalizeValue(fieldValue);
            $cell.attr('data-field-value', normalized);
            $cell.data('field-value', normalized);
        } else {
            $cell.removeAttr('data-field-value');
            $cell.removeData('field-value');
        }

        $cell.removeData('original-html');
        $cell.removeData('original-field-value');
        $cell.removeData('original-display');
        $cell.addClass('mj-editable');
        attachEditClick($cell);
    }

    function activateChoiceEditor($choice, $cell, originalFieldValue, originalHtml) {
        const memberId = $choice.data('member-id');
        const fieldName = $choice.data('field-name');
        const $hiddenInput = $choice.find('.mj-inline-choice__input');
        const $buttons = $choice.find('.mj-inline-choice__btn');
        const $cancel = $choice.find('.mj-inline-choice__cancel');
        const initialValue = normalizeValue(originalFieldValue);

        $buttons.each(function(index) {
            $(this).attr('data-choice-index', index);
        });

        function focusChoice(index) {
            const $target = $buttons.filter(function() {
                return Number($(this).attr('data-choice-index')) === index;
            });
            if ($target.length) {
                $target.focus();
            }
        }

        function restore() {
            restoreEditableCell($cell, memberId, fieldName, originalHtml, originalFieldValue);
        }

        function commit(value) {
            const normalized = normalizeValue(value);
            $hiddenInput.val(normalized);
            saveInlineEdit($hiddenInput, $cell, originalFieldValue, originalHtml);
        }

        $buttons.on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            commit($(this).data('value'));
        });

        $buttons.on('keydown', function(event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                event.stopPropagation();
                commit($(this).data('value'));
            }
        });

        $choice.on('keydown', function(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                restore();
                return;
            }

            const navigationKeys = ['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'];
            if (navigationKeys.indexOf(event.key) === -1) {
                return;
            }

            const $focused = $(document.activeElement);
            if (!$focused.hasClass('mj-inline-choice__btn')) {
                return;
            }

            const currentIndex = Number($focused.attr('data-choice-index'));
            if (Number.isNaN(currentIndex)) {
                return;
            }

            event.preventDefault();

            const total = $buttons.length;
            if (!total) {
                return;
            }

            let nextIndex = currentIndex;
            if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                nextIndex = (currentIndex + 1) % total;
            } else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                nextIndex = (currentIndex - 1 + total) % total;
            }

            focusChoice(nextIndex);
        });

        $cancel.on('click', function(event) {
            event.preventDefault();
            event.stopPropagation();
            restore();
        });

        const $active = $buttons.filter('.mj-inline-choice__btn--active');
        if ($active.length) {
            $active.first().focus();
        } else if ($buttons.length) {
            $buttons.first().focus();
        }

        if (normalizeValue($hiddenInput.val()) === '') {
            $hiddenInput.val(initialValue);
        }
    }

    function saveInlineEdit($input, $cell, originalFieldValue, originalHtml) {
        const memberId = $input.data('member-id');
        const fieldName = $input.data('field-name');
        const fieldValue = normalizeValue($input.val());
        const initialValue = normalizeValue(originalFieldValue);

        // Prevent duplicate requests when Enter triggers blur immediately after
        if ($cell.data('saving')) {
            return;
        }

        if (fieldValue === initialValue) {
            restoreEditableCell($cell, memberId, fieldName, originalHtml, originalFieldValue);
            return;
        }

        $cell.data('saving', true);
        $input.prop('disabled', true);

        $.post(mjMembers.ajaxurl, {
            action: 'mj_inline_edit_member',
            nonce: mjMembers.nonce,
            member_id: memberId,
            field_name: fieldName,
            field_value: fieldValue
        }, function(response) {
            if (response && response.success) {
                const serverValue = response.data && typeof response.data.value !== 'undefined' ? response.data.value : fieldValue;
                const normalized = normalizeValue(serverValue);
                let displayHtml = getDisplayHtml(fieldName, normalized);
                let requiresPaymentFlag = null;

                clearFieldMessage($cell);

                if (fieldName === 'date_last_payement') {
                    const requiresAttr = $cell.attr('data-requires-payment');
                    requiresPaymentFlag = requiresAttr === undefined ? true : parseBooleanFlag(requiresAttr);
                    displayHtml = formatPaymentDateDisplay(normalized, requiresPaymentFlag);
                }

                restoreEditableCell($cell, memberId, fieldName, displayHtml, normalized);

                if (fieldName === 'date_last_payement') {
                    updatePaymentMetaAppearance($cell, requiresPaymentFlag === null ? true : requiresPaymentFlag, normalized);
                }

                showNotice('Mise à jour réussie', 'success');
            } else {
                const message = response && response.data && response.data.message ? response.data.message : 'Erreur inconnue';
                restoreEditableCell($cell, memberId, fieldName, originalHtml, originalFieldValue);
                showFieldMessage($cell, message, 'error');
                showNotice('Erreur: ' + message, 'error');
            }
        }).fail(function() {
            restoreEditableCell($cell, memberId, fieldName, originalHtml, originalFieldValue);
            showFieldMessage($cell, 'Erreur de communication', 'error');
            showNotice('Erreur de communication', 'error');
        });
    }

    function attachEditClick($cell) {
        $cell.off('click').on('click', function(e) {
            e.preventDefault();

            const $thisCell = $(this);
            const memberId = $thisCell.data('member-id');
            const fieldName = $thisCell.data('field-name');
            const fieldType = $thisCell.data('field-type') || 'text';
            const fieldValueAttr = $thisCell.attr('data-field-value');
            const originalFieldValue = typeof fieldValueAttr !== 'undefined' ? fieldValueAttr : $thisCell.text().trim();
            const originalHtml = $thisCell.html();

            $thisCell.data('original-html', originalHtml);
            $thisCell.data('original-field-value', originalFieldValue);

            const inputHtml = buildInput(fieldType, fieldName, memberId, originalFieldValue);

            $thisCell.removeClass('mj-editable');
            $thisCell.html(inputHtml);

            const $choice = $thisCell.find('.mj-inline-choice');
            if ($choice.length) {
                activateChoiceEditor($choice, $thisCell, originalFieldValue, originalHtml);
                return;
            }

            const $input = $thisCell.find('.mj-inline-input');
            const isSelect = $input.is('select');

            $input.focus();
            if (!isSelect) {
                $input.select();
            }

            const commitChange = function() {
                saveInlineEdit($input, $thisCell, originalFieldValue, originalHtml);
            };

            if (isSelect) {
                $input.on('change', function() {
                    commitChange();
                });
            }

            $input.on('blur', function() {
                commitChange();
            }).on('keypress', function(event) {
                if (event.which === 13 && !isSelect) {
                    commitChange();
                }
            }).on('keydown', function(event) {
                if (event.which === 27) {
                    event.preventDefault();
                    restoreEditableCell($thisCell, memberId, fieldName, originalHtml, originalFieldValue);
                }
            });
        });
    }

    function showNotice(message, type) {
        const noticeHtml = '<div class="notice notice-' + type + ' is-dismissible"><p>' + escapeHtml(message) + '</p></div>';
        $('.mj-members-container').prepend(noticeHtml);

        setTimeout(function() {
            $('.mj-members-container .notice').fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }

    $('.mj-editable').each(function() {
        attachEditClick($(this));
    });
});
