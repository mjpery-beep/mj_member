(function ($) {
    const fieldId = 'mj_email_content';
    const smsFieldId = 'mj-sms-body';
    let editorReady = false;
    let pendingContent = null;
    let isSending = false;
    let cancelRequested = false;

    /**
     * Parse JSON from raw AJAX text, stripping any leading BOM.
     * Returns the parsed object or null on failure.
     */
    function parseBomJson(rawText) {
        try {
            return JSON.parse(rawText.replace(/^\uFEFF/, ''));
        } catch (e) {
            return null;
        }
    }

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

    function setSmsContent(value) {
        const textarea = document.getElementById(smsFieldId);
        if (!textarea) {
            return;
        }
        textarea.value = value || '';
    }

    function setWhatsappContent(value) {
        const textarea = document.getElementById('mj-whatsapp-body');
        if (!textarea) {
            return;
        }
        textarea.value = value || '';
    }

    function updateSmsFieldsetVisibility() {
        const fieldset = document.getElementById('mj-sms-fieldset');
        const checkbox = document.getElementById('mj-channel-sms');
        const textarea = document.getElementById(smsFieldId);

        if (!fieldset || !checkbox) {
            return;
        }

        const isActive = checkbox.checked;
        fieldset.classList.toggle('mj-hidden', !isActive);

        if (textarea) {
            textarea.disabled = !isActive;
        }
    }

    function updateWhatsappFieldsetVisibility() {
        const fieldset = document.getElementById('mj-whatsapp-fieldset');
        const checkbox = document.getElementById('mj-channel-whatsapp');
        const textarea = document.getElementById('mj-whatsapp-body');

        if (!fieldset || !checkbox) {
            return;
        }

        const isActive = checkbox.checked;
        fieldset.classList.toggle('mj-hidden', !isActive);

        if (textarea) {
            textarea.disabled = !isActive;
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
        const $stopButton = $('#mj-email-stop');
        if ($stopButton.length) {
            $stopButton.addClass('mj-hidden').prop('disabled', false);
        }
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

    function formatPhones(phones) {
        if (!Array.isArray(phones) || !phones.length) {
            return '';
        }
        return phones.join(', ');
    }

    function createLogEntry(recipient, status, message, emails, phones) {
        const effectiveStatus = status || 'pending';
        const $entry = $('<div>', { class: 'mj-email-log-entry ' + statusClass(effectiveStatus) });

        const labels = {
            show: getLocalized('previewShow') || 'Voir le message',
            hide: getLocalized('previewHide') || 'Masquer le message',
            subject: getLocalized('previewSubjectLabel') || 'Sujet',
            sms: getLocalized('previewSmsLabel') || 'SMS',
            smsRendered: getLocalized('previewSmsRenderedLabel') || 'SMS envoyé',
            smsRaw: getLocalized('previewSmsRawLabel') || 'Texte saisi'
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

        const emailLabelText = getLocalized('logEmailsLabel') || 'Email(s)';
        const phoneLabelText = getLocalized('logPhonesLabel') || 'SMS';
        const smsInlineLabelText = getLocalized('smsInlineLabel') || phoneLabelText;
        const errorsLabelText = getLocalized('logErrorDetailsLabel') || '';

        const $emails = $('<div>', { class: 'mj-email-log-entry__emails' });
        const formattedEmails = formatEmails(emails);
        if (formattedEmails) {
            $emails.text(emailLabelText + ' : ' + formattedEmails);
        } else {
            $emails.addClass('mj-hidden');
        }

        const $phones = $('<div>', { class: 'mj-email-log-entry__phones' });
        const formattedPhones = formatPhones(phones);
        if (formattedPhones) {
            $phones.text(phoneLabelText + ' : ' + formattedPhones);
        } else {
            $phones.addClass('mj-hidden');
        }

        const $message = $('<div>', { class: 'mj-email-log-entry__message' });
        if (message) {
            $message.text(message);
        }

        const $smsInline = $('<div>', { class: 'mj-email-log-entry__sms-inline mj-hidden' });

        const $preview = $('<div>', { class: 'mj-email-log-entry__preview mj-hidden' });
        const $previewEmail = $('<div>', { class: 'mj-email-log-entry__preview-block mj-email-log-entry__preview-block--email' });
        const $previewSubject = $('<div>', { class: 'mj-email-log-entry__preview-subject' });
        const $previewBody = $('<div>', { class: 'mj-email-log-entry__preview-body' });
        $previewEmail.append($previewSubject, $previewBody);

        const $previewSms = $('<div>', { class: 'mj-email-log-entry__preview-block mj-email-log-entry__preview-block--sms mj-hidden' });
        const $previewSmsHeading = $('<div>', { class: 'mj-email-log-entry__preview-subject' });
        const $previewSmsBody = $('<div>', { class: 'mj-email-log-entry__preview-body mj-email-log-entry__preview-body--sms' });
        const $previewSmsRaw = $('<div>', { class: 'mj-email-log-entry__preview-note mj-hidden' });
        $previewSms.append($previewSmsHeading, $previewSmsBody, $previewSmsRaw);

        $preview.append($previewEmail, $previewSms);

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

        $entry.append($header, $emails, $phones, $message, $smsInline, $preview);
        $entry.data('statusEl', $status);
        $entry.data('emailsEl', $emails);
        $entry.data('phonesEl', $phones);
        $entry.data('messageEl', $message);
        $entry.data('smsInlineEl', $smsInline);
        $entry.data('toggleEl', $toggle);
        $entry.data('statusBadgeEl', $statusBadge);
        $entry.data('previewEl', $preview);
        $entry.data('previewSubjectEl', $previewSubject);
        $entry.data('previewBodyEl', $previewBody);
        $entry.data('previewSmsWrapperEl', $previewSms);
        $entry.data('previewSmsHeadingEl', $previewSmsHeading);
        $entry.data('previewSmsBodyEl', $previewSmsBody);
        $entry.data('previewSmsRawEl', $previewSmsRaw);
        $entry.data('previewLabels', labels);
        $entry.data('emailsLabel', emailLabelText);
        $entry.data('phonesLabel', phoneLabelText);
        $entry.data('smsInlineLabel', smsInlineLabelText);
        $entry.data('errorsLabel', errorsLabelText);

        return $entry;
    }

    function updateLogEntry($entry, status, message, emails, phones, errors, preview, smsPreview, meta) {
        const $statusEl = $entry.data('statusEl');
        const $emailsEl = $entry.data('emailsEl');
        const $phonesEl = $entry.data('phonesEl');
        const $messageEl = $entry.data('messageEl');

        $entry.removeClass('is-pending is-success is-error is-skipped').addClass(statusClass(status));

        if ($statusEl && $statusEl.length) {
            $statusEl.text(statusLabel(status));
        }

        const emailLabel = $entry.data('emailsLabel') || (getLocalized('logEmailsLabel') || 'Email(s)');
        if ($emailsEl && $emailsEl.length) {
            const formattedEmails = formatEmails(emails);
            if (formattedEmails) {
                $emailsEl.text(emailLabel + ' : ' + formattedEmails).removeClass('mj-hidden');
            } else {
                $emailsEl.addClass('mj-hidden').text('');
            }
        }

        const phoneLabel = $entry.data('phonesLabel') || (getLocalized('logPhonesLabel') || 'SMS');
        if ($phonesEl && $phonesEl.length) {
            const formattedPhones = formatPhones(phones);
            if (formattedPhones) {
                $phonesEl.text(phoneLabel + ' : ' + formattedPhones).removeClass('mj-hidden');
            } else {
                $phonesEl.addClass('mj-hidden').text('');
            }
        }

        if ($messageEl && $messageEl.length) {
            $messageEl.text(message || '');
        }

        const $smsInlineEl = $entry.data('smsInlineEl');
        const smsInlineLabel = $entry.data('smsInlineLabel') || (getLocalized('smsInlineLabel') || 'SMS');
        if ($smsInlineEl && $smsInlineEl.length) {
            if (smsPreview && (smsPreview.body || smsPreview.raw)) {
                const smsText = smsPreview.body || smsPreview.raw || '';
                $smsInlineEl.text(smsInlineLabel + ' : ' + smsText).removeClass('mj-hidden');
            } else {
                $smsInlineEl.addClass('mj-hidden').text('');
            }
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
            const errorsLabel = $entry.data('errorsLabel') || (getLocalized('logErrorDetailsLabel') || '');
            const $errors = $('<div>', { class: 'mj-email-log-entry__errors' });
            if (errorsLabel) {
                $errors.append($('<div>', { class: 'mj-email-log-entry__errors-title', text: errorsLabel }));
            }

            if (errors.length === 1) {
                $errors.append($('<div>', { class: 'mj-email-log-entry__errors-item', text: errors[0] }));
            } else {
                const $list = $('<ul>', { class: 'mj-email-log-entry__errors-list' });
                errors.forEach(function (err) {
                    $list.append($('<li>', { text: err }));
                });
                $errors.append($list);
            }

            $entry.append($errors);
        }

        const $toggle = $entry.data('toggleEl');
        const $previewEl = $entry.data('previewEl');
        const $previewSubjectEl = $entry.data('previewSubjectEl');
        const $previewBodyEl = $entry.data('previewBodyEl');
        const $previewSmsWrapper = $entry.data('previewSmsWrapperEl');
        const $previewSmsHeading = $entry.data('previewSmsHeadingEl');
        const $previewSmsBody = $entry.data('previewSmsBodyEl');
        const $previewSmsRaw = $entry.data('previewSmsRawEl');
        const labels = $entry.data('previewLabels') || {
            show: 'Voir le message',
            hide: 'Masquer le message',
            subject: 'Sujet',
            sms: 'SMS',
            smsRendered: 'SMS envoyé',
            smsRaw: 'Texte saisi'
        };

        if ($toggle && $previewEl && $previewSubjectEl && $previewBodyEl) {
            const hasEmailPreview = preview && (preview.html || preview.body || preview.subject);
            const hasSmsPreview = smsPreview && (smsPreview.body || smsPreview.raw);
            const hasAnyPreview = hasEmailPreview || hasSmsPreview;
            const isSmsOnly = !hasEmailPreview && hasSmsPreview;

            if (hasAnyPreview) {
                if (hasEmailPreview) {
                    const bodyHtml = preview.body || preview.html || '';
                    const subjectText = preview.subject || '';
                    $previewSubjectEl.text(labels.subject + ' : ' + subjectText);
                    if (bodyHtml) {
                        $previewBodyEl.html(bodyHtml);
                    } else {
                        $previewBodyEl.empty();
                    }
                    $previewBodyEl.removeClass('mj-hidden');
                } else {
                    $previewSubjectEl.empty();
                    $previewBodyEl.empty().addClass('mj-hidden');
                }

                if ($previewSmsWrapper && $previewSmsHeading && $previewSmsBody && $previewSmsRaw) {
                    if (hasSmsPreview) {
                        $previewSmsHeading.text(labels.sms);
                        const rendered = smsPreview.body || '';
                        const rawMessage = smsPreview.raw || '';
                        $previewSmsBody.text(labels.smsRendered + ' : ' + rendered);

                        if (rawMessage && rawMessage !== rendered) {
                            $previewSmsRaw.text(labels.smsRaw + ' : ' + rawMessage).removeClass('mj-hidden');
                        } else if (rawMessage && !rendered) {
                            $previewSmsRaw.text(labels.smsRaw + ' : ' + rawMessage).removeClass('mj-hidden');
                        } else {
                            $previewSmsRaw.addClass('mj-hidden').empty();
                        }

                        $previewSmsWrapper.removeClass('mj-hidden');
                    } else {
                        $previewSmsWrapper.addClass('mj-hidden');
                        $previewSmsHeading.empty();
                        $previewSmsBody.empty();
                        $previewSmsRaw.addClass('mj-hidden').empty();
                    }
                }

                if (isSmsOnly) {
                    $previewEl.removeClass('mj-hidden');
                    $toggle.addClass('mj-hidden');
                } else {
                    $toggle.removeClass('mj-hidden');
                    if ($previewEl.hasClass('mj-hidden')) {
                        $toggle.text(labels.show);
                    } else {
                        $toggle.text(labels.hide);
                    }
                }
            } else {
                $toggle.addClass('mj-hidden');
                $toggle.text(labels.show);
                $previewEl.addClass('mj-hidden');
                $previewSubjectEl.empty();
                $previewBodyEl.empty();
                if ($previewSmsWrapper && $previewSmsHeading && $previewSmsBody && $previewSmsRaw) {
                    $previewSmsWrapper.addClass('mj-hidden');
                    $previewSmsHeading.empty();
                    $previewSmsBody.empty();
                    $previewSmsRaw.addClass('mj-hidden').empty();
                }
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
            const defaultMessage = getLocalized('skippedNoEmail') || getLocalized('logSkipped');
            const message = item.reason || defaultMessage;
            const $entry = createLogEntry(item, 'skipped', message, item.emails || [], item.phones || []);
            updateLogEntry($entry, 'skipped', message, item.emails || [], item.phones || [], [], null, null, null);
            $log.append($entry);
        });
    }

    function sendEmailForRecipient(recipient, requestPayload, $summary, $log, counters) {
        const $entry = createLogEntry(recipient, 'pending', '', recipient.emails || [], recipient.phones || []);
        $log.append($entry);

        const payload = $.extend({}, requestPayload, {
            action: 'mj_member_send_single_email',
            nonce: mjSendEmails.nonce,
            member_id: recipient.member_id
        });

        return $.ajax({
            url: mjSendEmails.ajaxUrl,
            method: 'POST',
            dataType: 'text',
            data: payload
        }).done(function (rawText) {
            var response = parseBomJson(rawText);
            if (!response || !response.success || !response.data) {
                counters.failed += 1;
                updateLogEntry($entry, 'failed', getLocalized('sendError'), recipient.emails || [], recipient.phones || [], [], null, null, null);
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

            updateLogEntry(
                $entry,
                status,
                data.message || '',
                data.emails || recipient.emails || [],
                data.phones || recipient.phones || [],
                data.errors || [],
                data.preview || null,
                data.smsPreview || null,
                data
            );
            updateSummaryCounts($summary, counters);
        }).fail(function () {
            counters.failed += 1;
            updateLogEntry($entry, 'failed', getLocalized('sendError'), recipient.emails || [], recipient.phones || [], [], null, null, null);
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
        const $testButton = $('#mj-send-email-test');
        const $testModeInput = $('#mj-email-test-mode');
        const $stopButton = $('#mj-email-stop');

        function disableForm() {
            $submitButton.prop('disabled', true);
            $testButton.prop('disabled', true);
            $form.addClass('is-sending');
        }

        function enableForm() {
            $submitButton.prop('disabled', false);
            $testButton.prop('disabled', false);
            $form.removeClass('is-sending');
        }

        function setStopButtonState(visible, disabled) {
            if (!$stopButton.length) {
                return;
            }
            $stopButton.toggleClass('mj-hidden', !visible);
            $stopButton.prop('disabled', !!disabled);
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
                    dataType: 'text',
                    data: {
                        action: 'mj_get_email_template',
                        nonce: mjSendEmails.nonce,
                        template_id: templateId
                    }
                }).done(function (rawText) {
                    var response = parseBomJson(rawText);
                    if (!response) {
                        window.alert(mjSendEmails.errorLoadTemplate);
                        return;
                    }
                    
                    if (!response || !response.success || !response.data) {
                        window.alert(mjSendEmails.errorLoadTemplate);
                        return;
                    }


                    try {
                        const data = response.data;

                        if (subjectInput.length && typeof data.subject === 'string') {
                            subjectInput.val(data.subject);
                        }

                        if (typeof data.content === 'string') {
                            updateEditorContent(data.content);
                        }

                        if (typeof data.sms_content === 'string') {
                            setSmsContent(data.sms_content);
                        }

                        if (typeof data.whatsapp_content === 'string') {
                            setWhatsappContent(data.whatsapp_content);
                        }
                        
                    } catch (error) {
                        console.error('Erreur chargement template:', error);
                        window.alert(mjSendEmails.errorLoadTemplate);
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

        if ($testButton.length) {
            $testButton.on('click', function () {
                if ($testModeInput.length) {
                    $testModeInput.val('1');
                }
                $form.trigger('submit');
            });
        }

        if ($stopButton.length) {
            $stopButton.on('click', function () {
                if (!isSending || cancelRequested) {
                    return;
                }
                cancelRequested = true;
                setStopButtonState(true, true);
                const note = getLocalized('sendCanceled') || 'Envoi interrompu.';
                $summary.append($('<span>', { class: 'mj-email-progress-note', text: ' ' + note }));
            });
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
            if ($testModeInput.length && $testModeInput.val() === '1') {
                $testModeInput.val('0');
            }
            const payload = $.extend({}, formData, {
                action: 'mj_member_prepare_email_send',
                nonce: mjSendEmails.nonce
            });

            isSending = true;
            cancelRequested = false;
            disableForm();
            resetProgress($progress, $summary, $log);
            $summary.text(getLocalized('logPending') || 'Préparation…');
            setStopButtonState(true, false);

            $.ajax({
                url: mjSendEmails.ajaxUrl,
                method: 'POST',
                dataType: 'text',
                data: payload
            }).done(function (rawText) {
                var response = parseBomJson(rawText);
                if (!response) {
                    setStopButtonState(false, false);
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
                        setStopButtonState(false, false);
                        if (data.message) {
                            $summary.addClass('is-error');
                            $summary.append($('<span>', { class: 'mj-email-progress-note', text: ' ' + data.message }));
                        }
                    } else {
                        const message = data.message || getLocalized('prepareError');
                        setStopButtonState(false, false);
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
                    setStopButtonState(false, false);
                    return;
                }

                let index = 0;

                function processNext() {
                    if (index >= queue.length) {
                        finishSending($summary, counters, enableForm);
                        setStopButtonState(false, false);
                        return;
                    }

                    const recipient = queue[index++];
                    sendEmailForRecipient(recipient, request, $summary, $log, counters).always(function () {
                        if (cancelRequested) {
                            finishSending($summary, counters, enableForm, false);
                            setStopButtonState(false, false);
                            return;
                        }
                        processNext();
                    });
                }

                processNext();
            }).fail(function () {
                setStopButtonState(false, false);
                handlePrepareError($summary, getLocalized('prepareError'), enableForm);
            });
        });

        const smsChannel = document.getElementById('mj-channel-sms');
        if (smsChannel) {
            smsChannel.addEventListener('change', updateSmsFieldsetVisibility);
            updateSmsFieldsetVisibility();
        }

        const whatsappChannel = document.getElementById('mj-channel-whatsapp');
        if (whatsappChannel) {
            whatsappChannel.addEventListener('change', updateWhatsappFieldsetVisibility);
            updateWhatsappFieldsetVisibility();
        }
    });
})(window.jQuery);
