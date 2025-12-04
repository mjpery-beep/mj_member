(function ($, window) {
    'use strict';

    var settings = window.mjMemberContactMessages || {};
    var i18n = settings.i18n || {};

    function label(key, fallback) {
        if (typeof i18n[key] === 'string' && i18n[key].length > 0) {
            return i18n[key];
        }
        return fallback;
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (match) {
            switch (match) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case '\'':
                    return '&#039;';
                default:
                    return match;
            }
        });
    }

    function normalizeBodyMarkup(source) {
        if (!source) {
            return '';
        }

        if (source.indexOf('<') !== -1) {
            return source;
        }

        var normalized = source.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        var paragraphs = normalized.split(/\n{2,}/);
        var html = paragraphs.map(function (paragraph) {
            return '<p>' + paragraph.replace(/\n/g, '<br>') + '</p>';
        }).join('');

        return html;
    }

    function renderActivity($form, entry) {
        if (!entry) {
            return;
        }

        var $item = $form.closest('.mj-contact-messages__item');
        if (!$item.length) {
            return;
        }

        var $list = $item.find('.mj-contact-messages__activity');
        if (!$list.length) {
            $list = $('<ul class="mj-contact-messages__activity"></ul>');
            var $anchor = $form.closest('.mj-contact-messages__quick-reply');
            if ($anchor.length) {
                $anchor.before($list);
            } else {
                $item.append($list);
            }
        }

        var heading = '';
        if (entry.action === 'reply_sent') {
            heading = label('teamHeading', 'Réponse de l’équipe MJ');
        } else if (entry.action === 'reply_owner') {
            heading = label('ownerHeading', 'Votre réponse');
        }

        var note = entry.note || '';
        var time = entry.time_human || '';
        var meta = entry.meta || {};
        var author = meta.author_name || '';
        var bodyHtml = normalizeBodyMarkup(meta.body || '');

        var $entry = $('<li class="mj-contact-messages__activity-item"></li>');
        var $header = $('<div class="mj-contact-messages__activity-header"></div>');

        if (time) {
            $header.append('<span class="mj-contact-messages__activity-time">' + escapeHtml(time) + '</span>');
        }

        if (heading) {
            $header.append('<span class="mj-contact-messages__activity-heading">' + escapeHtml(heading) + '</span>');
        } else if (note) {
            $header.append('<span class="mj-contact-messages__activity-note">' + escapeHtml(note) + '</span>');
        }

        $entry.append($header);

        if (heading && note) {
            $entry.append('<div class="mj-contact-messages__activity-note">' + escapeHtml(note) + '</div>');
        }

        if (author) {
            $entry.append('<div class="mj-contact-messages__activity-author">' + escapeHtml(author) + '</div>');
        }

        if (bodyHtml) {
            $entry.append($('<div class="mj-contact-messages__activity-body"></div>').html(bodyHtml));
        }

        $list.append($entry);
    }

    function handleSubmit(event) {
        event.preventDefault();

        var $form = $(this);
        if ($form.hasClass('is-submitting')) {
            return;
        }

        var ajaxUrl = $form.data('ajaxUrl');
        var nonce = $form.data('nonce');
        var recipient = $form.data('recipient');
        var subject = $form.data('subject');
        var parentId = $form.data('parentId');
        var senderName = $form.data('senderName') || '';
        var senderEmail = $form.data('senderEmail') || '';
        var memberId = $form.data('memberId') || '';
        var source = $form.data('source') || '';

        var $textarea = $form.find('textarea[name="reply_body"]');
        var messageValue = $.trim($textarea.val());
        var $feedback = $form.find('.mj-contact-messages__quick-reply-feedback');
        var $submit = $form.find('button[type="submit"]');

        if (!ajaxUrl || !nonce || !senderEmail) {
            $feedback.text(label('configError', 'Configuration invalide pour la réponse rapide.')).addClass('is-error').removeClass('is-success');
            return;
        }

        if (!messageValue) {
            $feedback.text(label('required', 'Merci de saisir un message.')).addClass('is-error').removeClass('is-success');
            $textarea.trigger('focus');
            return;
        }

        $form.addClass('is-submitting');
        $feedback.removeClass('is-error is-success').text('');

        if (!$submit.data('defaultLabel')) {
            $submit.data('defaultLabel', $submit.text());
        }

        $submit.prop('disabled', true).text(label('sending', 'Envoi...'));

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'mj_member_submit_contact_message',
                nonce: nonce,
                recipient: recipient,
                subject: subject,
                parent_message_id: parentId,
                name: senderName,
                email: senderEmail,
                member_id: memberId,
                source: source,
                message: messageValue
            }
        }).done(function (response) {
            if (response && response.success) {
                var responseData = response.data || {};
                var successMessage = responseData.message ? responseData.message : label('success', 'Votre réponse a bien été envoyée.');
                $feedback.text(successMessage).addClass('is-success').removeClass('is-error');
                $textarea.val('');

                if (responseData.activity) {
                    renderActivity($form, responseData.activity);
                }

                if (responseData.status_key) {
                    var $item = $form.closest('.mj-contact-messages__item');
                    if ($item.length) {
                        var $status = $item.find('.mj-contact-messages__status');
                        if ($status.length) {
                            $status.text(responseData.status_label || responseData.status_key);
                        }

                        var isUnread = responseData.is_read === 0 || responseData.is_read === '0';
                        if (isUnread) {
                            $item.addClass('is-unread');
                            var $badge = $item.find('.mj-contact-messages__badge');
                            if (!$badge.length) {
                                $badge = $('<span class="mj-contact-messages__badge"></span>');
                                $item.find('.mj-contact-messages__item-header').append($badge);
                            }
                            $badge.text(label('badgeUnread', 'Non lu'));
                        } else {
                            $item.removeClass('is-unread');
                            $item.find('.mj-contact-messages__badge').remove();
                        }
                    }
                }

                var detailsEl = $form.closest('details').get(0);
                if (detailsEl) {
                    detailsEl.open = false;
                }
            } else {
                var errorMessage = label('genericError', 'Une erreur est survenue. Merci de réessayer.');
                if (response && response.data && response.data.message) {
                    errorMessage = response.data.message;
                }
                $feedback.text(errorMessage).addClass('is-error').removeClass('is-success');
            }
        }).fail(function (jqXHR) {
            var errorMessage = label('genericError', 'Une erreur est survenue. Merci de réessayer.');
            if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                errorMessage = jqXHR.responseJSON.data.message;
            }
            $feedback.text(errorMessage).addClass('is-error').removeClass('is-success');
        }).always(function () {
            $form.removeClass('is-submitting');
            $submit.prop('disabled', false).text($submit.data('defaultLabel') || label('submit', 'Envoyer'));
        });
    }

    $(document).on('submit', '.mj-contact-messages__quick-reply-form', handleSubmit);
})(window.jQuery, window);
