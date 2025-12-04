(function ($) {
    'use strict';

    function parseConfig($wrapper) {
        var raw = $wrapper.data('config');
        if (!raw) {
            return {};
        }

        if (typeof raw === 'object') {
            return raw;
        }

        try {
            return JSON.parse(raw);
        } catch (error) {
            return {};
        }
    }

    function showFeedback($wrapper, message, type) {
        var $feedback = $wrapper.find('.mj-contact-form__feedback');
        if ($feedback.length === 0) {
            return;
        }

        $feedback.removeClass('is-success is-error');
        if (type === 'success') {
            $feedback.addClass('is-success');
        } else if (type === 'error') {
            $feedback.addClass('is-error');
        }
        $feedback.text(message || '');
    }

    function setPending($form, isPending) {
        var $button = $form.find('.mj-contact-form__submit');
        if ($button.length) {
            $button.prop('disabled', isPending);
        }
        if (isPending) {
            $form.addClass('is-submitting');
        } else {
            $form.removeClass('is-submitting');
        }
    }

    function collectFormData($form) {
        var recipientValue = '';
        var $hiddenRecipient = $form.find('[data-recipient-field]');
        if ($hiddenRecipient.length) {
            recipientValue = $.trim($hiddenRecipient.val());
        } else {
            var $checkedRadio = $form.find('input[name="recipient"]:checked');
            if ($checkedRadio.length) {
                recipientValue = $.trim($checkedRadio.val());
            } else {
                var $select = $form.find('select[name="recipient"]');
                if ($select.length) {
                    recipientValue = $.trim($select.val());
                }
            }
        }

        return {
            name: $.trim($form.find('input[name="name"]').val()),
            email: $.trim($form.find('input[name="email"]').val()),
            recipient: recipientValue,
            subject: $.trim($form.find('[name="subject"]').first().val()),
            message: $.trim($form.find('textarea[name="message"]').val()),
            source: $.trim($form.find('input[name="source"]').val()),
            nonce: $.trim($form.find('input[name="nonce"]').val()),
            member_id: $.trim($form.find('input[name="member_id"]').val())
        };
    }

    function validatePayload(payload) {
        if (!payload.name) {
            return false;
        }
        if (!payload.email) {
            return false;
        }
        if (!payload.recipient) {
            return false;
        }
        if (!payload.message) {
            return false;
        }
        return true;
    }

    function syncRecipientField($form) {
        var $hiddenRecipient = $form.find('[data-recipient-field]');
        if ($hiddenRecipient.length === 0) {
            return;
        }

        var generalValue = $.trim(($form.data('general-value') || '').toString());
        var $generalToggle = $form.find('[data-recipient-general]');
        if ($generalToggle.length && $generalToggle.prop('checked') && generalValue) {
            $hiddenRecipient.val(generalValue);
            return;
        }

        var $checkedIndividual = $form.find('input[name="recipient_individual"]:checked');
        if ($checkedIndividual.length) {
            $hiddenRecipient.val($.trim($checkedIndividual.val()));
        } else {
            $hiddenRecipient.val('');
        }
    }

    function refreshRecipientSelection($form) {
        var $options = $form.find('.mj-contact-form__recipient-option');
        if ($options.length === 0) {
            return;
        }

        syncRecipientField($form);

        var $hiddenRecipient = $form.find('[data-recipient-field]');
        var selectedValue = $hiddenRecipient.length ? $.trim($hiddenRecipient.val()) : '';

        $options.each(function () {
            var $option = $(this);
            var $input = $option.find('.mj-contact-form__recipient-input');
            if ($input.length === 0) {
                $option.removeClass('is-selected');
                return;
            }

            var optionValue = $.trim(($input.val() || '').toString());
            if (selectedValue && optionValue === selectedValue) {
                $option.addClass('is-selected');
            } else {
                $option.removeClass('is-selected');
            }
        });

        toggleIndividualListVisibility($form);
    }

    function toggleIndividualListVisibility($form) {
        var $list = $form.find('[data-recipient-list]');
        if ($list.length === 0) {
            return;
        }

        var $generalToggle = $form.find('[data-recipient-general]');
        if ($generalToggle.length && $generalToggle.prop('checked')) {
            $list.addClass('is-hidden');
        } else {
            $list.removeClass('is-hidden');
        }
    }

    function initializeRecipientSelection($context) {
        $context.find('.mj-contact-form__form').each(function () {
            refreshRecipientSelection($(this));
        });
    }

    $(document).on('change', '.mj-contact-form__recipient-input', function () {
        var $input = $(this);
        var $form = $input.closest('.mj-contact-form__form');

        if ($input.is('[data-recipient-general]')) {
            if ($input.prop('checked')) {
                $form.find('input[name="recipient_individual"]').prop('checked', false);
            } else {
                if (!$form.find('input[name="recipient_individual"]:checked').length) {
                    var $first = $form.find('input[name="recipient_individual"]').first();
                    if ($first.length) {
                        $first.prop('checked', true);
                    }
                }
            }
        } else {
            if ($input.prop('checked')) {
                $form.find('[data-recipient-general]').prop('checked', false);
            }
        }

        refreshRecipientSelection($form);
    });

    $(function () {
        initializeRecipientSelection($(document));
    });

    $(document).on('submit', '.mj-contact-form__form', function (event) {
        event.preventDefault();

        var $form = $(this);
        var $wrapper = $form.closest('.mj-contact-form');
        var config = parseConfig($wrapper);
        var payload = collectFormData($form);

        if (!validatePayload(payload)) {
            showFeedback($wrapper, config.errorMessage || 'Veuillez vérifier le formulaire.', 'error');
            return;
        }

        if (config.isPreview) {
            showFeedback($wrapper, config.successMessage || 'Message envoyé (aperçu).', 'success');
            return;
        }

        setPending($form, true);
        showFeedback($wrapper, '', '');

        var requestData = $.extend({}, payload, {
            action: 'mj_member_submit_contact_message'
        });

        $.ajax({
            url: config.ajaxUrl || (window.ajaxurl ? window.ajaxurl : ''),
            method: 'POST',
            data: requestData,
            dataType: 'json'
        }).done(function (response) {
            if (response && response.success) {
                $form.trigger('reset');
                refreshRecipientSelection($form);
                showFeedback($wrapper, (response.data && response.data.message) || config.successMessage || 'Merci !', 'success');
            } else {
                var message = config.errorMessage || 'Une erreur est survenue. Merci de réessayer.';
                if (response && response.data && response.data.message) {
                    message = response.data.message;
                }
                showFeedback($wrapper, message, 'error');
            }
        }).fail(function () {
            showFeedback($wrapper, config.errorMessage || 'Une erreur est survenue. Merci de réessayer.', 'error');
        }).always(function () {
            setPending($form, false);
        });
    });
})(jQuery);
