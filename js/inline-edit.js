jQuery(document).ready(function($) {
    const allowedRoles = Array.isArray(mjMembers.allowedRoles) ? mjMembers.allowedRoles : [];
    const roleLabels = typeof mjMembers.roleLabels === 'object' && mjMembers.roleLabels !== null ? mjMembers.roleLabels : {};
    const statusLabels = typeof mjMembers.statusLabels === 'object' && mjMembers.statusLabels !== null ? mjMembers.statusLabels : { active: 'Actif', inactive: 'Inactif' };
    const photoConsentLabels = typeof mjMembers.photoConsentLabels === 'object' && mjMembers.photoConsentLabels !== null ? mjMembers.photoConsentLabels : { 1: 'Accepté', 0: 'Refusé' };
    const labels = typeof mjMembers.labels === 'object' && mjMembers.labels !== null ? mjMembers.labels : {};

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

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
        let options = '';

        if (fieldName === 'status') {
            ['active', 'inactive'].forEach(function(key) {
                const label = statusLabels[key] || key;
                const selected = key === value ? ' selected' : '';
                options += '<option value="' + escapeHtml(key) + '"' + selected + '>' + escapeHtml(label) + '</option>';
            });
        } else if (fieldName === 'role') {
            const roles = allowedRoles.length ? allowedRoles : Object.keys(roleLabels);
            roles.forEach(function(roleKey) {
                const normalizedKey = normalizeValue(roleKey);
                if (normalizedKey === '') {
                    return;
                }
                const label = roleLabels[normalizedKey] || normalizedKey;
                const selected = normalizedKey === value ? ' selected' : '';
                options += '<option value="' + escapeHtml(normalizedKey) + '"' + selected + '>' + escapeHtml(label) + '</option>';
            });
        } else if (fieldName === 'photo_usage_consent') {
            [['1', photoConsentLabels['1'] || 'Accepté'], ['0', photoConsentLabels['0'] || 'Refusé']].forEach(function(pair) {
                const selected = pair[0] === value ? ' selected' : '';
                options += '<option value="' + escapeHtml(pair[0]) + '"' + selected + '>' + escapeHtml(pair[1]) + '</option>';
            });
        }

        if (options === '') {
            options = '<option value="' + escapeHtml(value) + '" selected>' + escapeHtml(value) + '</option>';
        }

        return '<select class="mj-inline-input" data-member-id="' + escapeHtml(memberId) + '" data-field-name="' + escapeHtml(fieldName) + '">' + options + '</select>';
    }

    function buildInput(fieldType, fieldName, memberId, value) {
        const normalized = normalizeValue(value);
        if (fieldType === 'select' || fieldName === 'status' || fieldName === 'role' || fieldName === 'photo_usage_consent') {
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
                showNotice('Erreur: ' + message, 'error');
            }
        }).fail(function() {
            restoreEditableCell($cell, memberId, fieldName, originalHtml, originalFieldValue);
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
