(function ($) {
    const fieldId = 'mj_email_content';
    let editorReady = false;
    let pendingContent = null;
    let isSending = false;

    function getLocalized(key) {
        if (!window.mjSendEmails || !mjSendEmails.i18n) {
            return '';
        }
        return mjSendEmails.i18n[key] || '';
    }

    function applyContentToEditor(content) {
        const normalized = content || '';

        if (typeof tinymce !== 'undefined') {
            const editor = tinymce.get(fieldId);
            if (editor) {
                editor.setContent(normalized, { format: 'html' });
                editor.save();
            }
        }

        const textarea = document.getElementById(fieldId);
        if (textarea) {
            textarea.value = normalized;
        }

        if (typeof QTags !== 'undefined' && QTags.instances) {
            const instance = QTags.instances[fieldId];
            if (instance) {
                instance.value = normalized;
            }
        }
    }

    function updateEditorContent(content) {
        pendingContent = content;
        if (editorReady) {
            applyContentToEditor(content);
        }
    }

    function toggleRecipientSections() {
        const memberSelect = document.getElementById('mj-member-select');
        const segmentWrapper = document.getElementById('mj-email-segments');
        if (!memberSelect || !segmentWrapper) {
            return;
        }

        const hasMemberSelection = memberSelect.value !== '';
        const radios = segmentWrapper.querySelectorAll('input[name="target"]');

        if (hasMemberSelection) {
            segmentWrapper.classList.add('mj-hidden');
            radios.forEach(function (radio) {
                radio.dataset.mjPrevChecked = radio.checked ? '1' : '0';
                radio.checked = false;
                radio.disabled = true;
            });
        } else {
            segmentWrapper.classList.remove('mj-hidden');
            let restored = false;
            radios.forEach(function (radio, index) {
                radio.disabled = false;
                if (!restored && radio.dataset.mjPrevChecked === '1') {
                    radio.checked = true;
                    restored = true;
                }
                radio.dataset.mjPrevChecked = '0';
            });
            if (!restored && radios.length) {
                radios[0].checked = true;
            }
        }
    }

    function serializeForm($form) {
        const data = {};
        $form.serializeArray().forEach(function (field) {
            data[field.name] = field.value;
        });
        return data;
    }

    function ensureProgressVisible($progress) {
        if ($progress.hasClass('mj-hidden')) {
            $progress.removeClass('mj-hidden');
        }
    }

    function resetProgress($progress, $summary, $log) {
        ensureProgressVisible($progress);
        $summary.removeClass('is-error').text('');
        $log.empty();
    }

    function statusClass(status) {
        if (status === 'sent') {
            return 'is-success';
        }
        if (status === 'failed') {
            return 'is-error';
        }
        if (status === 'skipped') {
            return 'is-skipped';
        }
        return 'is-pending';
    }

    function statusLabel(status) {
        if (status === 'sent') {
            return getLocalized('logSent') || 'OK';
        }
        if (status === 'failed') {
            return getLocalized('logFailed') || 'Erreur';
        }
        if (status === 'skipped') {
            return getLocalized('logSkipped') || 'Ignoré';
        }
        return getLocalized('logPending') || '';
    }

    function formatEmails(emails) {
        if (!Array.isArray(emails) || !emails.length) {
            return '';
        }
        return emails.join(', ');
    }

    function createLogEntry(recipient, status, message, emails) {
        const effectiveStatus = status || 'pending';
        const $entry = $('<div>', { class: 'mj-email-log-entry ' + statusClass(effectiveStatus) });

        const labels = {
            show: getLocalized('previewShow') || 'Voir le message',
            hide: getLocalized('previewHide') || 'Masquer le message',
            subject: getLocalized('previewSubjectLabel') || 'Sujet'
        };

        const $header = $('<div>', { class: 'mj-email-log-entry__header' });
        const $name = $('<span>', { class: 'mj-email-log-entry__name', text: recipient.label || '' });
        const $statusGroup = $('<span>', { class: 'mj-email-log-entry__status-group' });
        const $status = $('<span>', { class: 'mj-email-log-entry__status', text: statusLabel(effectiveStatus) });
        const $statusBadge = $('<span>', {
            class: 'mj-email-log-entry__badge mj-email-log-entry__badge--test mj-hidden',
            text: getLocalized('statusTestMode') || 'Mode test'
        });
        const $toggle = $('<button>', { type: 'button', class: 'button-link mj-email-log-entry__toggle mj-hidden', text: labels.show });

        $statusGroup.append($status, $statusBadge, $toggle);
        $header.append($name, $statusGroup);

        const $emails = $('<div>', { class: 'mj-email-log-entry__emails' });
        if (emails && emails.length) {
            $emails.text(formatEmails(emails));
        }

        const $message = $('<div>', { class: 'mj-email-log-entry__message' });
        if (message) {
            $message.text(message);
        }

        const $preview = $('<div>', { class: 'mj-email-log-entry__preview mj-hidden' });
        const $previewSubject = $('<div>', { class: 'mj-email-log-entry__preview-subject' });
        const $previewBody = $('<div>', { class: 'mj-email-log-entry__preview-body' });
        $preview.append($previewSubject, $previewBody);

        $toggle.on('click', function () {
            const isHidden = $preview.hasClass('mj-hidden');
            if (isHidden) {
                $preview.removeClass('mj-hidden');
                $toggle.text(labels.hide);
            } else {
                $preview.addClass('mj-hidden');
                $toggle.text(labels.show);
            }
        });

        $entry.append($header, $emails, $message, $preview);
        $entry.data('statusEl', $status);
        $entry.data('emailsEl', $emails);
        $entry.data('messageEl', $message);
        $entry.data('toggleEl', $toggle);
        $entry.data('statusBadgeEl', $statusBadge);
        $entry.data('previewEl', $preview);
        $entry.data('previewSubjectEl', $previewSubject);
        $entry.data('previewBodyEl', $previewBody);
        $entry.data('previewLabels', labels);

        return $entry;
    }

    function updateLogEntry($entry, status, message, emails, errors, preview, meta) {
        const $statusEl = $entry.data('statusEl');
        const $emailsEl = $entry.data('emailsEl');
        const $messageEl = $entry.data('messageEl');

        $entry.removeClass('is-pending is-success is-error is-skipped').addClass(statusClass(status));

        if ($statusEl && $statusEl.length) {
            $statusEl.text(statusLabel(status));
        }

        if ($emailsEl && $emailsEl.length) {
            $emailsEl.text(formatEmails(emails));
        }

        if ($messageEl && $messageEl.length) {
            $messageEl.text(message || '');
        }

        const isTestMode = !!(meta && meta.testMode);
        $entry.toggleClass('is-test-mode', isTestMode);

        const $statusBadge = $entry.data('statusBadgeEl');
        if ($statusBadge && $statusBadge.length) {
            if (isTestMode) {
                $statusBadge.removeClass('mj-hidden');
            } else {
                $statusBadge.addClass('mj-hidden');
            }
        }

        $entry.find('.mj-email-log-entry__errors').remove();
        if (errors && errors.length) {
            const $errors = $('<div>', { class: 'mj-email-log-entry__errors', text: errors.join(' | ') });
            $entry.append($errors);
        }

        const $toggle = $entry.data('toggleEl');
        const $previewEl = $entry.data('previewEl');
        const $previewSubjectEl = $entry.data('previewSubjectEl');
        const $previewBodyEl = $entry.data('previewBodyEl');
        const labels = $entry.data('previewLabels') || { show: 'Voir le message', hide: 'Masquer le message', subject: 'Sujet' };

        if ($toggle && $previewEl && $previewSubjectEl && $previewBodyEl) {
            const hasPreview = preview && (preview.html || preview.body || preview.subject);
            if (hasPreview) {
                const bodyHtml = preview.body || preview.html || '';
                const subjectText = preview.subject || '';
                $toggle.removeClass('mj-hidden');
                $previewSubjectEl.text(labels.subject + ' : ' + subjectText);
                if (bodyHtml) {
                    $previewBodyEl.html(bodyHtml);
                } else {
                    $previewBodyEl.empty();
                }
                if ($previewEl.hasClass('mj-hidden')) {
                    $toggle.text(labels.show);
                } else {
                    $toggle.text(labels.hide);
                }
            } else {
                $toggle.addClass('mj-hidden');
                $toggle.text(labels.show);
                $previewEl.addClass('mj-hidden');
                $previewSubjectEl.empty();
                $previewBodyEl.empty();
            }
        }
    }

    function updateSummaryCounts($summary, counters) {
        const template = getLocalized('summary') || 'Récapitulatif : %1$s envoyé(s), %2$s échec(s), %3$s ignoré(s).';
        let text = template;
        text = text.replace('%1$s', counters.sent);
        text = text.replace('%2$s', counters.failed);
        text = text.replace('%3$s', counters.skipped);
        let html = '<span class="mj-email-summary-line">' + text + '</span>';
        if (counters.testMode) {
            const note = getLocalized('summaryTestMode') || 'Mode test actif : aucun email réel n\'a été envoyé.';
            html += '<span class="mj-email-progress-note mj-email-progress-note--test">' + note + '</span>';
        }
        $summary.html(html);
    }

    function renderSkippedRecipients(skipped, $log, counters) {
        if (!Array.isArray(skipped) || !skipped.length) {
            return;
        }

        const title = getLocalized('skippedTitle');
        if (title) {
            $log.append($('<div>', { class: 'mj-email-log-heading', text: title }));
        }

        skipped.forEach(function (item) {
            counters.skipped += 1;
            const message = getLocalized('skippedNoEmail') || getLocalized('logSkipped');
            const $entry = createLogEntry(item, 'skipped', message, item.emails || []);
            updateLogEntry($entry, 'skipped', message, item.emails || [], [], null, null);
            $log.append($entry);
        });
    }

    function sendEmailForRecipient(recipient, requestPayload, $summary, $log, counters) {
        const $entry = createLogEntry(recipient, 'pending', '', recipient.emails || []);
        $log.append($entry);

        const payload = $.extend({}, requestPayload, {
            action: 'mj_member_send_single_email',
            nonce: mjSendEmails.nonce,
            member_id: recipient.member_id
        });

        return $.ajax({
            url: mjSendEmails.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: payload
        }).done(function (response) {
            if (!response || !response.success || !response.data) {
                counters.failed += 1;
                updateLogEntry($entry, 'failed', getLocalized('sendError'), recipient.emails || [], [], null, null);
                updateSummaryCounts($summary, counters);
                return;
            }

            const data = response.data;
            const status = data.status || 'sent';
            if (status === 'sent') {
                counters.sent += 1;
            } else if (status === 'failed') {
                counters.failed += 1;
            } else if (status === 'skipped') {
                counters.skipped += 1;
            }

            if (typeof data.testMode !== 'undefined') {
                counters.testMode = counters.testMode || !!data.testMode;
            }

            updateLogEntry($entry, status, data.message || '', data.emails || recipient.emails || [], data.errors || [], data.preview || null, data);
            updateSummaryCounts($summary, counters);
        }).fail(function () {
            counters.failed += 1;
            updateLogEntry($entry, 'failed', getLocalized('sendError'), recipient.emails || [], [], null, null);
            updateSummaryCounts($summary, counters);
        });
    }

    function finishSending($summary, counters, enableForm, showFinishedNote) {
        updateSummaryCounts($summary, counters);
        const finishedLabel = getLocalized('finished');
        if (finishedLabel && showFinishedNote !== false) {
            $summary.append($('<span>', { class: 'mj-email-progress-finished', text: ' ' + finishedLabel }));
        }
        if (typeof enableForm === 'function') {
            enableForm();
        }
        isSending = false;
    }

    function handlePrepareError($summary, message, enableForm) {
        $summary.addClass('is-error').text(message || getLocalized('prepareError') || 'Erreur.');
        if (typeof enableForm === 'function') {
            enableForm();
        }
        isSending = false;
    }

    $(document).ready(function () {
        const templateSelect = $('#mj-email-template');
        const subjectInput = $('#mj-email-subject');
        const memberSelect = $('#mj-member-select');
        const loader = $('#mj-template-loading');
        const $form = $('#mj-send-email-form');
        const $progress = $('#mj-email-progress');
        const $summary = $('#mj-email-progress-summary');
        const $log = $('#mj-email-progress-log');
        const $submitButton = $form.find('button[type="submit"]');

        function disableForm() {
            $submitButton.prop('disabled', true);
            $form.addClass('is-sending');
        }

        function enableForm() {
            $submitButton.prop('disabled', false);
            $form.removeClass('is-sending');
        }

        if (loader.length) {
            loader.attr('aria-hidden', 'true').addClass('mj-hidden');
        }

        $(document).on('tinymce-editor-init', function (event, editor) {
            if (editor && editor.id === fieldId) {
                editorReady = true;
                if (pendingContent !== null) {
                    applyContentToEditor(pendingContent);
                }
            }
        });

        if (memberSelect.length) {
            memberSelect.on('change', toggleRecipientSections);
            toggleRecipientSections();
        }

        if (templateSelect.length) {
            templateSelect.on('change', function () {
                const templateId = $(this).val();
                if (!templateId) {
                    if (loader.length) {
                        loader.attr('aria-hidden', 'true').addClass('mj-hidden');
                    }
                    return;
                }

                if (loader.length) {
                    loader.attr('aria-hidden', 'false').removeClass('mj-hidden');
                }

                $.ajax({
                    url: mjSendEmails.ajaxUrl,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'mj_get_email_template',
                        nonce: mjSendEmails.nonce,
                        template_id: templateId
                    }
                }).done(function (response) {
                    if (!response || !response.success || !response.data) {
                        window.alert(mjSendEmails.errorLoadTemplate);
                        return;
                    }

                    const data = response.data;
                    if (subjectInput.length && typeof data.subject === 'string') {
                        subjectInput.val(data.subject);
                    }

                    if (typeof data.content === 'string') {
                        updateEditorContent(data.content);
                    }
                }).fail(function () {
                    window.alert(mjSendEmails.errorLoadTemplate);
                }).always(function () {
                    if (loader.length) {
                        loader.attr('aria-hidden', 'true').addClass('mj-hidden');
                    }
                });
            });

            if (templateSelect.val()) {
                templateSelect.trigger('change');
            }
        }

        $form.on('submit', function (event) {
            event.preventDefault();
            if (isSending) {
                return;
            }

            if (typeof tinymce !== 'undefined' && typeof tinymce.triggerSave === 'function') {
                tinymce.triggerSave();
            }

            const formData = serializeForm($form);
            const payload = $.extend({}, formData, {
                action: 'mj_member_prepare_email_send',
                nonce: mjSendEmails.nonce
            });

            isSending = true;
            disableForm();
            resetProgress($progress, $summary, $log);
            $summary.text(getLocalized('logPending') || 'Préparation…');

            $.ajax({
                url: mjSendEmails.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function (response) {
                if (!response) {
                    handlePrepareError($summary, getLocalized('prepareError'), enableForm);
                    return;
                }

                if (!response.success) {
                    const data = response.data || {};
                    const skippedOnly = Array.isArray(data.skipped) ? data.skipped : [];
                    if (skippedOnly.length) {
                        const counters = { sent: 0, failed: 0, skipped: 0, testMode: !!data.testModeEnabled, total: skippedOnly.length };
                        resetProgress($progress, $summary, $log);
                        renderSkippedRecipients(skippedOnly, $log, counters);
                        finishSending($summary, counters, enableForm, false);
                        if (data.message) {
                            $summary.addClass('is-error');
                            $summary.append($('<span>', { class: 'mj-email-progress-note', text: ' ' + data.message }));
                        }
                    } else {
                        const message = data.message || getLocalized('prepareError');
                        handlePrepareError($summary, message, enableForm);
                    }
                    return;
                }

                const data = response.data;
                const request = data.request || {};
                const queue = Array.isArray(data.sendQueue) ? data.sendQueue : [];
                const skipped = Array.isArray(data.skipped) ? data.skipped : [];
                const counters = { sent: 0, failed: 0, skipped: 0, testMode: !!data.testModeEnabled, total: queue.length };

                resetProgress($progress, $summary, $log);
                renderSkippedRecipients(skipped, $log, counters);
                updateSummaryCounts($summary, counters);

                if (!queue.length) {
                    finishSending($summary, counters, enableForm);
                    return;
                }

                let index = 0;

                function processNext() {
                    if (index >= queue.length) {
                        finishSending($summary, counters, enableForm);
                        return;
                    }

                    const recipient = queue[index++];
                    sendEmailForRecipient(recipient, request, $summary, $log, counters).always(function () {
                        processNext();
                    });
                }

                processNext();
            }).fail(function () {
                handlePrepareError($summary, getLocalized('prepareError'), enableForm);
            });
        });
    });
})(window.jQuery);
