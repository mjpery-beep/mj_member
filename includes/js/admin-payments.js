jQuery(document).ready(function($) {
    // Simple modal element
    var $modal = $('<div id="mj-qr-modal" style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center;">' +
        '<div style="background:#fff; padding:30px; max-width:550px; width:90%; border-radius:8px; text-align:center; position:relative; box-shadow:0 5px 20px rgba(0,0,0,0.3);">' +
        '<button class="button" id="mj-qr-close" style="position:absolute; right:10px; top:10px; padding:5px 10px;">✕ Fermer</button>' +
        '<div id="mj-qr-content" style="margin-top:20px;"></div>' +
        '</div></div>');
    $('body').append($modal);

    $(document).on('click', '.mj-show-qr-btn', function(e) {
        e.preventDefault();
        var memberId = $(this).data('member-id');
        var $btn = $(this);
        $btn.prop('disabled', true).text('⏳ Génération...');

        $.post(mjPayments.ajaxurl, { 
            action: 'mj_admin_get_qr', 
            member_id: memberId, 
            nonce: mjPayments.nonce 
        }, function(resp) {
            $btn.prop('disabled', false).text('QR paiement');
            
            if (!resp || !resp.success) {
                console.error('QR Error:', resp);
                alert('Erreur: ' + (resp.data ? resp.data : 'Impossible de générer le QR code'));
                return;
            }
            
            var data = resp.data;
            // Escape HTML to prevent XSS
            var escapeHtml = function(text) {
                var map = {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#039;'
                };
                return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
            };
            
            var isStripe = data.stripe_session_id ? true : false;
            var paymentMethod = isStripe ? '💳 Stripe' : '🔗 Lien simple';
            var url = data.checkout_url || data.confirm_url || '';
            
            var html = '<h3 style="margin-top:0; color:#0073aa;">Demande de paiement</h3>' +
                '<p style="font-size:12px; color:#666;"><strong>Méthode:</strong> ' + paymentMethod + '</p>' +
                '<p><strong>Montant:</strong> ' + escapeHtml(data.amount) + ' €</p>' +
                '<p><strong>ID Paiement:</strong> #' + escapeHtml(data.payment_id) + '</p>' +
                '<div style="margin:20px 0; padding:15px; background:#f9f9f9; border-radius:4px; border:2px dashed #0073aa;">' +
                '<p style="margin:0 0 15px 0;"><strong>📱 QR Code (scannez avec un téléphone):</strong></p>' +
                '<p><a href="' + escapeHtml(url) + '" target="_blank" title="Cliquer pour confirmer le paiement">' +
                '<img src="' + escapeHtml(data.qr_url) + '" style="max-width:100%; max-height:300px; border:2px solid #ddd; padding:8px; border-radius:4px; background:white;" alt="QR Code Paiement">' +
                '</a></p>' +
                '</div>' +
                '<p style="font-size:12px; color:#666; margin:10px 0;">Scannez le QR-code ou cliquez sur l\'image pour ouvrir le paiement</p>' +
                '<hr style="margin:15px 0; border:none; border-top:1px solid #ddd;">' +
                '<p><strong>🔗 Lien direct:</strong></p>' +
                '<input type="text" readonly value="' + escapeHtml(url) + '" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; font-size:11px; margin-bottom:10px;" onclick="this.select()">' +
                '<button class="button" type="button" onclick="copyToClipboard(this, \'' + escapeHtml(url).replace(/'/g, '\\\'') + '\')">📋 Copier le lien</button>' +
                '<p style="font-size:11px; color:#999; margin:10px 0 0 0;"><em>Vous pouvez partager ce lien par email ou SMS</em></p>';
                
            $('#mj-qr-content').html(html);
            $('#mj-qr-modal').css('display', 'flex');
        }, 'json')
        .fail(function(jqXHR, textStatus, errorThrown) {
            $btn.prop('disabled', false).text('QR paiement');
            console.error('AJAX Error:', textStatus, errorThrown);
            alert('Erreur de communication avec le serveur: ' + textStatus);
        });
    });

    $(document).on('click', '.mj-mark-paid-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var memberId = $btn.data('member-id');
        if (!memberId) {
            return;
        }

        if (!window.confirm('Confirmer que ce membre a remis sa cotisation ?')) {
            return;
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Validation...');

        $.post(mjPayments.ajaxurl, {
            action: 'mj_member_mark_paid',
            member_id: memberId,
            nonce: mjPayments.nonce
        }, function(resp) {
            if (!resp || !resp.success) {
                var message = (resp && resp.data && resp.data.message) ? resp.data.message : 'Impossible d\'enregistrer la cotisation.';
                alert(message);
                $btn.prop('disabled', false).text(originalText);
                return;
            }

            var successMessage = (resp.data && resp.data.message) ? resp.data.message : 'Cotisation enregistrée.';
            if (resp.data && resp.data.recorded_by && resp.data.recorded_by.name) {
                successMessage += '\n' + 'Enregistré par : ' + resp.data.recorded_by.name + ' (ID ' + resp.data.recorded_by.id + ')';
            }
            alert(successMessage);
            window.location.reload();
        }, 'json').fail(function(jqXHR, textStatus) {
            alert('Erreur de communication avec le serveur: ' + textStatus);
            $btn.prop('disabled', false).text(originalText);
        });
    });

    $(document).on('click', '#mj-qr-close', function(e) {
        e.preventDefault();
        $('#mj-qr-modal').hide();
    });

    // Close modal when clicking outside
    $(document).on('click', '#mj-qr-modal', function(e) {
        if (e.target === this) {
            $('#mj-qr-modal').hide();
        }
    });

    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    function loadPaymentHistory(memberId, triggerButton, filterValue) {
        if (!memberId) {
            return;
        }

        var $modal = $('#mj-qr-modal');
        var selectedFilter = (typeof filterValue === 'string' && filterValue.length > 0) ? filterValue : ($modal.attr('data-active-filter') || 'all');

        $modal.attr('data-member-id', memberId);
        $modal.attr('data-active-filter', selectedFilter);

        if (triggerButton && triggerButton.length) {
            if (triggerButton.hasClass('mj-payment-history-btn')) {
                triggerButton.data('mjOriginalText', triggerButton.text());
                triggerButton.prop('disabled', true).text('⏳ Chargement...');
            } else if (triggerButton.hasClass('mj-payment-filter-btn')) {
                triggerButton.prop('disabled', true).addClass('is-loading');
            }
        }

        $.post(mjPayments.ajaxurl, {
            action: 'mj_admin_get_payment_history',
            member_id: memberId,
            payment_filter: selectedFilter,
            nonce: mjPayments.nonce
        }, function(resp) {
            if (triggerButton && triggerButton.length) {
                if (triggerButton.hasClass('mj-payment-history-btn')) {
                    var resetText = triggerButton.data('mjOriginalText') || '💳 Historique';
                    triggerButton.prop('disabled', false).text(resetText);
                } else if (triggerButton.hasClass('mj-payment-filter-btn')) {
                    triggerButton.prop('disabled', false).removeClass('is-loading');
                }
            }

            if (!resp || !resp.success) {
                console.error('Payment History Error:', resp);
                alert('Erreur: ' + (resp && resp.data ? resp.data : 'Impossible de récupérer l\'historique des paiements'));
                return;
            }

            var data = resp.data || {};
            var canDelete = !!data.can_delete;
            var activeFilter = data.active_filter || selectedFilter || 'all';
            $modal.attr('data-active-filter', activeFilter);

            var html = '<h3 style="margin-top:0; color:#0073aa;">Historique des paiements</h3>';

            if (Array.isArray(data.filters) && data.filters.length) {
                html += '<div class="mj-payment-history-filters" style="margin-bottom:15px; display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-start;">';
                data.filters.forEach(function(filter) {
                    var value = (filter && filter.value) ? filter.value : 'all';
                    var label = (filter && filter.label) ? filter.label : value;
                    var isActive = value === activeFilter;
                    var buttonClass = isActive ? 'button button-primary' : 'button button-secondary';
                    html += '<button type="button" class="' + buttonClass + ' mj-payment-filter-btn" data-filter="' + value + '">' + escapeHtml(label) + '</button>';
                });
                html += '</div>';
            }

            if (Array.isArray(data.payments) && data.payments.length > 0) {
                html += '<ul style="list-style:none; padding:0;">';
                data.payments.forEach(function(payment) {
                    html += '<li style="margin-bottom:10px; padding:10px; border:1px solid #ddd; border-radius:4px; position:relative;">' +
                        '<strong>Date:</strong> ' + escapeHtml(payment.date) + '<br>' +
                        '<strong>Montant:</strong> ' + escapeHtml(payment.amount) + ' €<br>' +
                        (payment.context_label ? '<strong>Type:</strong> ' + escapeHtml(payment.context_label) + '<br>' : '') +
                        (payment.event && payment.event.title ? '<strong>Événement:</strong> ' + escapeHtml(payment.event.title) + '<br>' : '') +
                        '<strong>Référence:</strong> ' + escapeHtml(payment.reference) + '<br>' +
                        '<strong>Statut:</strong> ' + escapeHtml(payment.status_label || payment.status || 'Inconnu') + '<br>' +
                        '<strong>Méthode:</strong> ' + escapeHtml(payment.method || '') +
                        (canDelete ? '<div style="margin-top:8px;"><button class="button button-small mj-delete-payment-btn" data-member-id="' + escapeHtml(memberId) + '" data-history-id="' + escapeHtml(payment.history_id || '') + '" data-payment-id="' + escapeHtml(payment.payment_id || '') + '">🗑️ Supprimer</button></div>' : '') +
                        '</li>';
                });
                html += '</ul>';
            } else {
                html += '<p>Aucun paiement trouvé pour ce filtre.</p>';
            }

            $('#mj-qr-content').html(html);
            $('#mj-qr-modal').css('display', 'flex');
        }, 'json')
        .fail(function(jqXHR, textStatus, errorThrown) {
            if (triggerButton && triggerButton.length) {
                if (triggerButton.hasClass('mj-payment-history-btn')) {
                    var resetText = triggerButton.data('mjOriginalText') || '💳 Historique';
                    triggerButton.prop('disabled', false).text(resetText);
                } else if (triggerButton.hasClass('mj-payment-filter-btn')) {
                    triggerButton.prop('disabled', false).removeClass('is-loading');
                }
            }
            console.error('AJAX Error:', textStatus, errorThrown);
            alert('Erreur de communication avec le serveur: ' + textStatus);
        });
    }

    $(document).on('click', '.mj-payment-history-btn', function(e) {
        e.preventDefault();
        var memberId = $(this).data('member-id');
        loadPaymentHistory(memberId, $(this));
    });

    $(document).on('click', '.mj-payment-filter-btn', function(e) {
        e.preventDefault();
        var memberId = $('#mj-qr-modal').attr('data-member-id');
        if (!memberId) {
            return;
        }
        var filter = $(this).data('filter') || 'all';
        loadPaymentHistory(memberId, $(this), filter);
    });

    $(document).on('click', '.mj-delete-payment-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var memberId = $btn.data('member-id');
        var historyId = $btn.data('history-id');
        var paymentId = $btn.data('payment-id');

        if (!confirm('Supprimer ce paiement ?')) {
            return;
        }

        $btn.prop('disabled', true).text('Suppression...');

        $.post(mjPayments.ajaxurl, {
            action: 'mj_admin_delete_payment',
            member_id: memberId,
            history_id: historyId,
            payment_id: paymentId,
            nonce: mjPayments.nonce
        }, function(resp) {
            if (!resp || !resp.success) {
                console.error('Delete Payment Error:', resp);
                alert('Impossible de supprimer le paiement: ' + (resp && resp.data ? resp.data : 'erreur inconnue'));
                $btn.prop('disabled', false).text('🗑️ Supprimer');
                return;
            }

            // Reload history list
            var currentFilter = $('#mj-qr-modal').attr('data-active-filter') || 'all';
            loadPaymentHistory(memberId, null, currentFilter);
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            alert('Erreur de communication avec le serveur: ' + textStatus);
            $btn.prop('disabled', false).text('🗑️ Supprimer');
        });
    });
});

// Helper function to copy to clipboard
function copyToClipboard(button, text) {
    var tempInput = document.createElement('input');
    tempInput.value = text;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    
    var originalText = button.textContent;
    button.textContent = '✅ Copié!';
    setTimeout(function() {
        button.textContent = originalText;
    }, 2000);
}

