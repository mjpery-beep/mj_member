jQuery(document).ready(function($) {
    const config = window.mjEventsList || {};
    const ajaxurl = config.ajaxurl || '';
    const nonce = config.nonce || '';
    const inlineEditNonce = config.inlineEditNonce || '';
    
    const statusLabels = typeof config.statusLabels === 'object' && config.statusLabels !== null ? config.statusLabels : {
        'actif': 'Actif',
        'brouillon': 'Brouillon',
        'passe': 'Passé'
    };
    
    const typeLabels = typeof config.typeLabels === 'object' && config.typeLabels !== null ? config.typeLabels : {
        'stage': 'Stage',
        'soiree': 'Soirée',
        'sortie': 'Sortie',
        'atelier': 'Atelier'
    };
    
    const labels = typeof config.labels === 'object' && config.labels !== null ? config.labels : {};
    
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
    
    function formatStatusBadge(status) {
        const key = normalizeValue(status);
        const label = statusLabels[key] || key;
        
        let background = '#6c757d';
        if (key === 'actif') {
            background = '#28a745';
        } else if (key === 'brouillon') {
            background = '#ffc107';
        } else if (key === 'passe') {
            background = '#6c757d';
        }
        
        return '<span class="badge" style="background-color:' + background + ';color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;">' + escapeHtml(label) + '</span>';
    }
    
    function formatTypeBadge(type) {
        const key = normalizeValue(type);
        const label = typeLabels[key] || key;
        
        const typeColors = {
            'stage': '#0026FF',
            'soiree': '#C300FF',
            'sortie': '#FF5100',
            'atelier': '#0C8301'
        };
        const background = typeColors[key] || '#6c757d';
        
        return '<span class="badge" style="background-color:' + background + ';color:#fff;padding:3px 8px;border-radius:12px;font-size:12px;display:inline-block;">' + escapeHtml(label) + '</span>';
    }
    
    function getDisplayHtml(fieldName, fieldValue) {
        const normalized = normalizeValue(fieldValue);
        switch (fieldName) {
            case 'status':
                return formatStatusBadge(normalized);
            case 'type':
                return formatTypeBadge(normalized);
            default:
                return escapeHtml(normalized);
        }
    }
    
    function clearFieldMessage($cell) {
        const eventId = normalizeValue($cell.data('event-id'));
        const fieldName = normalizeValue($cell.data('field-name'));
        
        if (eventId === '' || fieldName === '') {
            return;
        }
        
        const selector = '.mj-inline-feedback';
        const filterMatches = function() {
            const siblingEventId = normalizeValue($(this).attr('data-event-id'));
            const siblingFieldName = normalizeValue($(this).attr('data-field-name'));
            return siblingEventId === eventId && siblingFieldName === fieldName;
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
        
        const eventId = normalizeValue($cell.data('event-id'));
        const fieldName = normalizeValue($cell.data('field-name'));
        
        if (eventId === '' || fieldName === '') {
            return;
        }
        
        clearFieldMessage($cell);
        
        const $feedback = $('<div>')
            .addClass('mj-inline-feedback')
            .attr('data-event-id', eventId)
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
    
    // Gestion de l'édition inline des champs status
    $(document).on('click', '.mj-editable[data-event-id][data-field-name="status"]', function(e) {
        e.stopPropagation();
        
        const $this = $(this);
        if ($this.find('select').length) {
            return;
        }
        
        const eventId = normalizeValue($this.data('event-id'));
        const fieldName = normalizeValue($this.data('field-name'));
        const currentValue = normalizeValue($this.data('field-value'));
        
        const originalHtml = $this.html();
        
        const $select = $('<select>')
            .addClass('mj-inline-edit-select')
            .css({
                'width': '100%',
                'min-width': '120px',
                'padding': '4px',
                'border': '1px solid #8c8f94',
                'border-radius': '4px'
            });
        
        Object.keys(statusLabels).forEach(function(statusKey) {
            const $option = $('<option>')
                .val(statusKey)
                .text(statusLabels[statusKey]);
            
            if (statusKey === currentValue) {
                $option.prop('selected', true);
            }
            
            $select.append($option);
        });
        
        $this.html($select);
        $select.focus();
        
        const save = function() {
            const newValue = $select.val();
            
            if (newValue === currentValue) {
                $this.html(originalHtml);
                return;
            }
            
            clearFieldMessage($this);
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'mj_inline_edit_event',
                    nonce: inlineEditNonce,
                    event_id: eventId,
                    field_name: fieldName,
                    field_value: newValue
                },
                beforeSend: function() {
                    $select.prop('disabled', true);
                },
                success: function(response) {
                    if (response.success) {
                        const savedValue = response.data.value != null ? response.data.value : newValue;
                        $this.data('field-value', savedValue);
                        $this.html(getDisplayHtml(fieldName, savedValue));
                        showFieldMessage($this, getLabel('updateSuccess', 'Mis à jour avec succès'), 'success');
                        
                        setTimeout(function() {
                            clearFieldMessage($this);
                        }, 3000);
                    } else {
                        $this.html(originalHtml);
                        const errorMessage = response.data && response.data.message ? response.data.message : getLabel('updateError', 'Erreur lors de la mise à jour');
                        showFieldMessage($this, errorMessage, 'error');
                    }
                },
                error: function() {
                    $this.html(originalHtml);
                    showFieldMessage($this, getLabel('ajaxError', 'Erreur de communication avec le serveur'), 'error');
                }
            });
        };
        
        const cancel = function() {
            $this.html(originalHtml);
        };
        
        $select.on('change', save);
        $select.on('blur', function() {
            setTimeout(cancel, 200);
        });
        
        $select.on('keydown', function(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                cancel();
            } else if (event.key === 'Enter') {
                event.preventDefault();
                save();
            }
        });
    });
    
    // Gestion des filtres AJAX de la table
    function refreshTable(extraParams) {
        const params = {
            action: 'mj_fetch_events_table',
            nonce: nonce
        };
        
        // Récupérer les valeurs actuelles des filtres
        const $filterStatus = $('#filter-by-status');
        const $filterType = $('#filter-by-type');
        const $search = $('input[name="s"]');
        const $paged = $('input[name="paged"]');
        
        if ($filterStatus.length && $filterStatus.val()) {
            params.filter_status = $filterStatus.val();
        }
        
        if ($filterType.length && $filterType.val()) {
            params.filter_type = $filterType.val();
        }
        
        if ($search.length && $search.val()) {
            params.s = $search.val();
        }
        
        if ($paged.length && $paged.val()) {
            params.paged = $paged.val();
        }
        
        // Fusionner avec les paramètres supplémentaires
        if (extraParams && typeof extraParams === 'object') {
            Object.keys(extraParams).forEach(function(key) {
                params[key] = extraParams[key];
            });
        }
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: params,
            beforeSend: function() {
                $('.tablenav .spinner').addClass('is-active');
            },
            success: function(response) {
                if (response.success && response.data && response.data.table) {
                    $('.wp-list-table').replaceWith(response.data.table);
                    
                    // Mettre à jour les valeurs des filtres cachés
                    if (response.data.filters) {
                        if (response.data.filters.status && $filterStatus.length) {
                            $filterStatus.val(response.data.filters.status);
                        }
                        if (response.data.filters.type && $filterType.length) {
                            $filterType.val(response.data.filters.type);
                        }
                        if (response.data.filters.search && $search.length) {
                            $search.val(response.data.filters.search);
                        }
                    }
                    
                    // Mettre à jour la pagination
                    if (response.data.pagination && $paged.length) {
                        $paged.val(response.data.pagination.current_page || 1);
                    }
                }
            },
            complete: function() {
                $('.tablenav .spinner').removeClass('is-active');
            }
        });
    }
    
    // Bind filtres
    $(document).on('change', '#filter-by-status, #filter-by-type', function() {
        refreshTable({ paged: 1 });
    });
    
    // Bind recherche
    $(document).on('click', '#search-submit', function(e) {
        e.preventDefault();
        refreshTable({ paged: 1 });
    });
    
    // Bind pagination
    $(document).on('click', '.tablenav-pages a', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        if (!url) {
            return;
        }
        
        const match = url.match(/paged=(\d+)/);
        if (match && match[1]) {
            refreshTable({ paged: match[1] });
        }
    });
});
