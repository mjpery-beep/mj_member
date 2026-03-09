/**
 * MJ Member – Expenses Widget (Notes de Frais)
 *
 * Depends on: mj-member-utils (escapeHtml, toInt)
 */
(function () {
  'use strict';

  /* ── globals ─────────────────────────────────────────── */
  var cfg = window.mjExpenses || {};
  var i18n = cfg.i18n || {};
  var ajaxUrl = cfg.ajaxUrl || '';
  var nonce = cfg.nonce || '';
  var isCoordinator = !!cfg.isCoordinator;
  var currentMemberId = parseInt(cfg.memberId, 10) || 0;
  var ownExpenses = cfg.ownExpenses || [];
  var allExpenses = cfg.allExpenses || [];
  var events = cfg.events || [];
  var projects = cfg.projects || [];
  var members = cfg.members || [];
  var statusLabels = cfg.statusLabels || {};

  /* ── helpers ─────────────────────────────────────────── */
  var esc = window.escapeHtml || function (s) { return String(s).replace(/[&<>"']/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); };

  function $(sel, ctx) { return (ctx || document).querySelector(sel); }
  function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

  function formatAmount(v) {
    return parseFloat(v || 0).toFixed(2).replace('.', ',') + ' ' + (i18n.currency || '€');
  }

  function formatDate(d) {
    if (!d) return '';
    var dt = new Date(d.replace(' ', 'T'));
    if (isNaN(dt)) return d;
    return dt.toLocaleDateString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function statusClass(s) {
    var map = { pending: 'warning', approved: 'info', reimbursed: 'success', rejected: 'danger' };
    return 'mj-expenses__badge--' + (map[s] || 'default');
  }

  function statusLabel(s) {
    return statusLabels[s] || s;
  }

  function formatPaymentSummary(exp) {
    var methodLabels = {
      'cash_mj': i18n.cashMj || 'Cash MJ',
      'cb_mj': i18n.cbMj || 'CB MJ',
      'cash_perso': i18n.cashPerso || 'Cash personnel',
      'cb_perso': i18n.cbPerso || 'CB personnel'
    };
    
    if (!exp.is_paid) {
      return '<span class="mj-expenses__payment-status mj-expenses__payment-status--unpaid">' + esc(i18n.unpaid || 'À payer') + '</span>';
    }
    
    var method = methodLabels[exp.payment_method] || exp.payment_method || '-';
    var date = exp.payment_date ? '<br><small>(' + formatDate(exp.payment_date) + ')</small>' : '';
    var colorClass = '';
    
    // Determine color class based on payment method
    if (exp.payment_method === 'cash_mj' || exp.payment_method === 'cb_mj') {
      colorClass = ' mj-expenses__payment-status--mj';
    } else if (exp.payment_method === 'cash_perso' || exp.payment_method === 'cb_perso') {
      colorClass = ' mj-expenses__payment-status--perso';
    }
    
    return '<span class="mj-expenses__payment-status mj-expenses__payment-status--paid' + colorClass + '">' + esc(method) + date + '</span>';
  }

  /* ── AJAX helper ─────────────────────────────────────── */
  function post(action, data, cb) {
    var fd = data instanceof FormData ? data : null;
    if (!fd) {
      fd = new FormData();
      Object.keys(data).forEach(function (k) {
        var v = data[k];
        if (Array.isArray(v)) {
          v.forEach(function (item) { fd.append(k + '[]', item); });
        } else {
          fd.append(k, v);
        }
      });
    }
    fd.append('action', action);
    fd.append('nonce', nonce);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', ajaxUrl, true);
    xhr.onload = function () {
      var json;
      try { json = JSON.parse(xhr.responseText); } catch (e) { json = { success: false }; }
      cb(json);
    };
    xhr.onerror = function () { cb({ success: false }); };
    xhr.send(fd);
  }

  /* ── state ───────────────────────────────────────────── */
  var state = {
    tab: isCoordinator ? 'all' : 'own',
    filterMember: 0,
    filterProject: 0,
    filterEvent: 0,
    filterStatus: '',
    filterPaymentMethod: '',
    toggleApproved: false,
    toggleRejected: false,
    toggleReimbursed: false,
    toggleAccountingized: false,
    showForm: false,
    editExpense: null, // expense object when editing, null for create
  };

  /* ── render ──────────────────────────────────────────── */
  function getRoot() {
    return document.getElementById('mj-expenses-app');
  }

  function render() {
    var root = getRoot();
    if (!root) return;

    var html = '';

    // Tabs (coordinator only)
    if (isCoordinator) {
      var ownCount = ownExpenses.length;
      var allCount = allExpenses.length;
      var pendingCount = 0;
      allExpenses.forEach(function (e) { if (e.status === 'pending') pendingCount++; });
      html += '<div class="mj-expenses__tabs">';
      html += '<button class="mj-expenses__tab' + (state.tab === 'own' ? ' mj-expenses__tab--active' : '') + '" data-tab="own">';
      html += '<span class="mj-expenses__tab-icon">\uD83D\uDCDD</span> ';
      html += esc(i18n.myExpenses || 'Mes notes de frais');
      if (ownCount > 0) html += ' <span class="mj-expenses__tab-badge">' + ownCount + '</span>';
      html += '</button>';
      html += '<button class="mj-expenses__tab' + (state.tab === 'all' ? ' mj-expenses__tab--active' : '') + '" data-tab="all">';
      html += '<span class="mj-expenses__tab-icon">\uD83D\uDCCA</span> ';
      html += esc(i18n.allExpenses || 'Toutes les notes de frais');
      if (pendingCount > 0) html += ' <span class="mj-expenses__tab-badge mj-expenses__tab-badge--warning">' + pendingCount + '</span>';
      html += '</button>';
      html += '</div>';
    }

    // Add button
    html += '<div class="mj-expenses__actions-bar">';
    html += '<button class="mj-expenses__btn mj-expenses__btn--primary" id="mj-exp-add-btn">+ ' + esc(i18n.newExpense || 'Nouvelle note') + '</button>';
    html += '</div>';

    // Form (hidden by default)
    if (state.showForm) {
      html += renderForm();
    }

    // Filters (coordinator - all tab)
    if (isCoordinator && state.tab === 'all') {
      html += renderFilters();
    }

    // Table
    var list = state.tab === 'all' ? filterExpenses(allExpenses) : ownExpenses;
    html += renderTable(list, state.tab === 'all');

    // Keep title/intro in place
    var titleEl = root.querySelector('.mj-expenses-widget__title');
    var introEl = root.querySelector('.mj-expenses-widget__intro');
    var loadEl = root.querySelector('.mj-expenses-widget__loading');
    if (loadEl) loadEl.remove();

    // Find or create content container
    var contentEl = root.querySelector('.mj-expenses__content');
    if (!contentEl) {
      contentEl = document.createElement('div');
      contentEl.className = 'mj-expenses__content';
      root.appendChild(contentEl);
    }
    contentEl.innerHTML = html;

    bindEvents(contentEl);
  }

  function renderForm() {
    var ed = state.editExpense; // null = create, object = edit
    var isEdit = !!ed;
    var h = '<div class="mj-expenses__form-overlay" id="mj-exp-form-overlay">';
    h += '<div class="mj-expenses__form-modal">';
    h += '<h2>' + esc(isEdit ? (i18n.editExpense || 'Modifier la note de frais') : (i18n.newExpense || 'Nouveau docuement')) + '</h2>';
    h += '<form id="mj-exp-form" enctype="multipart/form-data">';
    if (isEdit) {
      h += '<input type="hidden" name="expense_id" value="' + ed.id + '" />';
    }

    // Amount
    h += '<div class="mj-expenses__field">';
    h += '<label>' + esc(i18n.amount) + ' *</label>';
    h += '<input type="number" name="amount" step="0.01" min="0.01" required class="mj-expenses__input" value="' + (isEdit ? ed.amount : '') + '" />';
    h += '</div>';

    // Description
    h += '<div class="mj-expenses__field">';
    h += '<label>' + esc(i18n.description) + ' *</label>';
    h += '<textarea name="description" rows="3" required class="mj-expenses__input">' + (isEdit ? esc(ed.description || '') : '') + '</textarea>';
    h += '</div>';

    // Project
    h += '<div class="mj-expenses__field">';
    h += '<label>' + esc(i18n.project) + '</label>';
    h += '<select name="project_id" class="mj-expenses__input">';
    h += '<option value="">' + esc(i18n.noProject) + '</option>';
    projects.forEach(function (p) {
      var sel = (isEdit && ed.project_id === p.id) ? ' selected' : '';
      h += '<option value="' + p.id + '"' + sel + '>' + esc(p.title) + '</option>';
    });
    h += '</select>';
    h += '</div>';

    // Events (multi-select)
    h += '<div class="mj-expenses__field">';
    h += '<label>' + esc(i18n.events) + '</label>';
    h += '<select name="event_ids" multiple class="mj-expenses__input mj-expenses__input--multi" size="4">';
    var editEventIds = (isEdit && ed.event_ids) ? ed.event_ids : [];
    events.forEach(function (ev) {
      var sel = (editEventIds.indexOf(ev.id) !== -1) ? ' selected' : '';
      h += '<option value="' + ev.id + '"' + sel + '>' + esc(ev.title) + '</option>';
    });
    h += '</select>';
    h += '<small>' + esc('Ctrl+clic pour sélectionner plusieurs') + '</small>';
    h += '</div>';

    // Receipt (multiple)
    h += '<div class="mj-expenses__field">';
    h += '<label>' + esc(i18n.receipt) + '</label>';
    if (isEdit && ed.receipts && ed.receipts.length) {
      h += '<div class="mj-expenses__edit-receipts">';
      h += '<small>' + ed.receipts.length + ' fichier(s) existant(s) :</small>';
      h += '<ul class="mj-expenses__receipt-list" id="mj-exp-receipt-list">';
      ed.receipts.forEach(function (r) {
        var receiptUrl = ajaxUrl + '?action=mj_expense_receipt&expense_id=' + ed.id + '&file_index=' + r.index + '&nonce=' + encodeURIComponent(nonce);
        var ext = (r.ext || '').toLowerCase();
        var isImg = (ext === 'jpg' || ext === 'jpeg' || ext === 'png' || ext === 'gif' || ext === 'webp');
        h += '<li class="mj-expenses__receipt-item" data-file-index="' + r.index + '">';
        if (isImg) {
          h += '<img src="' + receiptUrl + '" class="mj-expenses__receipt-item-thumb" alt="" />';
        } else {
          h += '<span class="mj-expenses__receipt-item-icon">PDF</span>';
        }
        h += '<a href="' + receiptUrl + '" target="_blank" class="mj-expenses__receipt-item-name">' + esc((r.ext || '').toUpperCase() + ' #' + (r.index + 1)) + '</a>';
        h += '<button type="button" class="mj-expenses__receipt-item-delete" data-remove-index="' + r.index + '" title="' + esc(i18n.delete || 'Supprimer') + '">&times;</button>';
        h += '</li>';
      });
      h += '</ul>';
      h += '</div>';
    }
    h += '<input type="file" name="receipts[]" multiple accept="image/jpeg,image/png,image/gif,application/pdf" class="mj-expenses__input" />';
    h += '<small>' + esc('Plusieurs fichiers possibles (PDF, JPG, PNG, GIF – max 10 Mo chacun)') + '</small>';
    h += '</div>';

    // Payment section
    h += '<div class="mj-expenses__divider"></div>';
    h += '<h4>' + esc('Paiement') + '</h4>';

    // Is paid checkbox
    h += '<div class="mj-expenses__field">';
    h += '<label class="mj-expenses__checkbox-label">';
    h += '<input type="checkbox" name="is_paid" class="mj-expenses__checkbox" value="1"' + (isEdit && ed.is_paid ? ' checked' : '') + ' id="mj-exp-is-paid" />';
    h += ' ' + esc(i18n.isPaid);
    h += '</label>';
    h += '</div>';

    // Payment method and date (conditional)
    h += '<div id="mj-exp-payment-details" class="mj-expenses__payment-details" style="display: ' + (isEdit && ed.is_paid ? 'block' : 'none') + ';">';
    h += '<div class="mj-expenses__field">';
    h += '<label>' + esc(i18n.paymentMethod) + '</label>';
    h += '<select name="payment_method" class="mj-expenses__input" id="mj-exp-payment-method">';
    h += '<option value="">' + esc('— Choisir —') + '</option>';
    h += '<option value="cash_mj"' + (isEdit && ed.payment_method === 'cash_mj' ? ' selected' : '') + '>' + esc(i18n.cashMj) + '</option>';
    h += '<option value="cb_mj"' + (isEdit && ed.payment_method === 'cb_mj' ? ' selected' : '') + '>' + esc(i18n.cbMj) + '</option>';
    h += '<option value="cash_perso"' + (isEdit && ed.payment_method === 'cash_perso' ? ' selected' : '') + '>' + esc(i18n.cashPerso) + '</option>';
    h += '<option value="cb_perso"' + (isEdit && ed.payment_method === 'cb_perso' ? ' selected' : '') + '>' + esc(i18n.cbPerso) + '</option>';
    h += '</select>';
    h += '</div>';

    h += '<div class="mj-expenses__field">';
    h += '<label>' + esc(i18n.paymentDate) + '</label>';
    h += '<input type="date" name="payment_date" class="mj-expenses__input" value="' + (isEdit && ed.payment_date ? ed.payment_date : '') + '" />';
    h += '</div>';

    // Bank statement (conditional - CB Perso only)
    h += '<div id="mj-exp-bank-statement" class="mj-expenses__field" style="display: ' + (isEdit && ed.payment_method === 'cb_perso' ? 'block' : 'none') + ';">';
    h += '<label>' + esc(i18n.bankStatement) + '</label>';
    if (isEdit && ed.bank_statement) {
      h += '<div class="mj-expenses__existing-file">';
      h += '<small>Fichier existant : ' + esc(ed.bank_statement) + '</small>';
      h += '</div>';
    }
    h += '<input type="file" name="bank_statement" accept="application/pdf,image/jpeg,image/png" class="mj-expenses__input" />';
    h += '</div>';
    h += '</div>';

    // Buttons
    h += '<div class="mj-expenses__form-buttons">';
    h += '<button type="submit" class="mj-expenses__btn mj-expenses__btn--primary">' + esc(isEdit ? (i18n.save || 'Enregistrer') : i18n.submit) + '</button>';
    h += '<button type="button" class="mj-expenses__btn mj-expenses__btn--secondary" id="mj-exp-cancel-btn">' + esc(i18n.cancel) + '</button>';
    h += '</div>';

    h += '<div id="mj-exp-form-msg" class="mj-expenses__msg"></div>';
    h += '</form>';
    h += '</div></div>';
    return h;
  }

  function renderFilters() {
    var h = '<div class="mj-expenses__filters">';

    // Row 1: Dropdowns
    h += '<div class="mj-expenses__filters-row">';

    // Member filter
    h += '<select class="mj-expenses__filter" data-filter="member">';
    h += '<option value="0">' + esc(i18n.allMembers) + '</option>';
    members.forEach(function (m) {
      var sel = state.filterMember === m.id ? ' selected' : '';
      h += '<option value="' + m.id + '"' + sel + '>' + esc(m.name) + '</option>';
    });
    h += '</select>';

    // Project filter
    h += '<select class="mj-expenses__filter" data-filter="project">';
    h += '<option value="0">' + esc(i18n.allProjects) + '</option>';
    projects.forEach(function (p) {
      var sel = state.filterProject === p.id ? ' selected' : '';
      h += '<option value="' + p.id + '"' + sel + '>' + esc(p.title) + '</option>';
    });
    h += '</select>';

    // Event filter
    h += '<select class="mj-expenses__filter" data-filter="event">';
    h += '<option value="0">' + esc(i18n.allEvents) + '</option>';
    events.forEach(function (ev) {
      var sel = state.filterEvent === ev.id ? ' selected' : '';
      h += '<option value="' + ev.id + '"' + sel + '>' + esc(ev.title) + '</option>';
    });
    h += '</select>';

    // Payment method filter
    h += '<select class="mj-expenses__filter" data-filter="paymentMethod">';
    h += '<option value="">' + esc('Tous les paiements') + '</option>';
    h += '<option value="unpaid"' + (state.filterPaymentMethod === 'unpaid' ? ' selected' : '') + '>' + esc('Non payé') + '</option>';
    h += '<option value="cash_mj"' + (state.filterPaymentMethod === 'cash_mj' ? ' selected' : '') + '>' + esc(i18n.cashMj || 'Cash MJ') + '</option>';
    h += '<option value="cb_mj"' + (state.filterPaymentMethod === 'cb_mj' ? ' selected' : '') + '>' + esc(i18n.cbMj || 'CB MJ') + '</option>';
    h += '<option value="cash_perso"' + (state.filterPaymentMethod === 'cash_perso' ? ' selected' : '') + '>' + esc(i18n.cashPerso || 'Cash personnel') + '</option>';
    h += '<option value="cb_perso"' + (state.filterPaymentMethod === 'cb_perso' ? ' selected' : '') + '>' + esc(i18n.cbPerso || 'CB personnel') + '</option>';
    h += '</select>';

    h += '</div>';

    // Row 2: Status toggle buttons
    h += '<div class="mj-expenses__filters-row mj-expenses__status-toggles">';
    var toggles = [
      { key: 'toggleApproved',       status: 'approved',       label: 'Approuvé',     color: 'info' },
      { key: 'toggleRejected',       status: 'rejected',       label: 'Rejeté',       color: 'danger' },
      { key: 'toggleReimbursed',     status: 'reimbursed',     label: 'Remboursé',    color: 'success' },
      { key: 'toggleAccountingized', status: 'accountingized', label: 'Comptabilisé', color: 'neutral' }
    ];
    toggles.forEach(function (t) {
      var active = state[t.key];
      h += '<button type="button" class="mj-expenses__toggle mj-expenses__toggle--' + t.color + (active ? ' mj-expenses__toggle--active' : '') + '" data-toggle="' + t.key + '">';
      h += esc(t.label);
      h += '</button>';
    });
    h += '</div>';

    h += '</div>';
    return h;
  }

  function filterExpenses(list) {
    // Collect active status toggles
    var activeToggles = [];
    if (state.toggleApproved) activeToggles.push('approved');
    if (state.toggleRejected) activeToggles.push('rejected');
    if (state.toggleReimbursed) activeToggles.push('reimbursed');
    if (state.toggleAccountingized) activeToggles.push('accountingized');

    return list.filter(function (exp) {
      if (state.filterMember > 0 && exp.member_id !== state.filterMember) return false;
      if (state.filterProject > 0 && exp.project_id !== state.filterProject) return false;
      if (state.filterEvent > 0) {
        if (!exp.event_ids || exp.event_ids.indexOf(state.filterEvent) === -1) return false;
      }
      if (state.filterStatus !== '' && exp.status !== state.filterStatus) return false;

      // Payment method filter
      if (state.filterPaymentMethod !== '') {
        if (state.filterPaymentMethod === 'unpaid') {
          if (exp.is_paid) return false;
        } else {
          if (exp.payment_method !== state.filterPaymentMethod) return false;
        }
      }

      // Status toggle filters (OR logic: show if matches any active toggle)
      if (activeToggles.length > 0) {
        if (activeToggles.indexOf(exp.status) === -1) return false;
      }

      return true;
    });
  }

  function renderTable(list, showMember) {
    if (!list.length) {
      return '<p class="mj-expenses__empty">' + esc(i18n.noExpenses) + '</p>';
    }

    // Summary
    var total = 0;
    var pendingTotal = 0;
    list.forEach(function (exp) {
      total += exp.amount;
      if (exp.status === 'pending') pendingTotal += exp.amount;
    });

    var h = '<div class="mj-expenses__summary">';
    h += '<span class="mj-expenses__summary-item"><strong>' + esc(i18n.total) + ':</strong> ' + formatAmount(total) + '</span>';
    if (pendingTotal > 0) {
      h += '<span class="mj-expenses__summary-item"><strong>' + esc(i18n.pendingAmount) + ':</strong> ' + formatAmount(pendingTotal) + '</span>';
    }
    h += '</div>';

    h += '<div class="mj-expenses__table-wrap">';
    h += '<table class="mj-expenses__table">';
    h += '<thead><tr>';
    if (showMember) h += '<th>' + esc(i18n.member) + '</th>';
    h += '<th>' + esc('Détail') + '</th>';
    h += '<th>' + esc(i18n.status) + '</th>';
    h += '<th>' + esc(i18n.paymentMethod) + '</th>';
    h += '<th>' + esc('Justificatifs') + '</th>';
    h += '<th>' + esc(i18n.actions) + '</th>';
    h += '</tr></thead><tbody>';;

    list.forEach(function (exp) {
      h += '<tr data-id="' + exp.id + '">';
      
      // Member column with avatar
      if (showMember) {
        var memberContent = '<div class="mj-expenses__member-inner">';
        // Find member in membersList to get avatar
        var memberObj = members.find(function (m) { return m.id === exp.member_id; });
        if (memberObj && memberObj.avatar) {
          memberContent += '<img src="' + memberObj.avatar + '" alt="' + esc(memberObj.name) + '" class="mj-expenses__member-avatar" loading="lazy" />';
        }
        memberContent += '<span class="mj-expenses__member-name">' + esc(exp.member_name) + '</span>';
        memberContent += '</div>';
        h += '<td class="mj-expenses__member-cell">' + memberContent + '</td>';
      }
      
      // Detail column (combines date, description, amount, project)
      h += '<td class="mj-expenses__detail-cell">';
      h += '<div class="mj-expenses__detail-date">' + esc(formatDate(exp.created_at)) + '</div>';
      h += '<div class="mj-expenses__detail-desc"><strong>' + esc(exp.description || '-') + '</strong></div>';
      h += '<div class="mj-expenses__detail-amount">' + formatAmount(exp.amount) + '</div>';
      if (exp.project_name) {
        h += '<div class="mj-expenses__detail-project"><small>Projet: ' + esc(exp.project_name) + '</small></div>';
      }
      h += '</td>';
      h += '<td><span class="mj-expenses__badge ' + statusClass(exp.status) + '">' + esc(statusLabel(exp.status)) + '</span>';
      if (exp.reviewer_comment) {
        h += '<br><small class="mj-expenses__comment">' + esc(exp.reviewer_comment) + '</small>';
      }
      h += '</td>';

      // Payment method column
      h += '<td class="mj-expenses__payment-method-cell">' + formatPaymentSummary(exp) + '</td>';

      // Combined Justificatifs column (receipts + bank statement)
      h += '<td class="mj-expenses__justificatifs-cell">';
      var hasJustificatifs = (exp.receipts && exp.receipts.length) || exp.bank_statement;
      
      if (hasJustificatifs) {
        h += '<div class="mj-expenses__justificatifs-list">';
        
        // Receipts
        if (exp.receipts && exp.receipts.length) {
          exp.receipts.forEach(function (r, idx) {
            var receiptUrl = ajaxUrl + '?action=mj_expense_receipt&expense_id=' + exp.id + '&file_index=' + r.index + '&nonce=' + encodeURIComponent(nonce);
            var ext = (r.ext || '').toLowerCase();
            var isPdf = ext === 'pdf';
            
            h += '<div class="mj-expenses__justificatif-item">';
            h += '<span class="mj-expenses__justificatif-label">📎 Reçu</span>';
            
            if (isPdf) {
              h += '<a href="#" class="mj-expenses__justificatif-link mj-expenses__justificatif-pdf mj-expenses__viewer-trigger" data-url="' + receiptUrl + '" data-type="pdf" data-title="Reçu PDF" title="Voir reçu">';
              h += '<span class="mj-expenses__pdf-icon">📄</span>';
              h += '</a>';
            } else {
              h += '<a href="#" class="mj-expenses__justificatif-link mj-expenses__viewer-trigger" data-url="' + receiptUrl + '" data-type="image" data-title="Reçu" title="Voir reçu">';
              h += '<span class="mj-expenses__pdf-icon">🖼️</span>';
              h += '</a>';
            }
            h += '</div>';
          });
        }
        
        // Bank statement
        if (exp.bank_statement) {
          var bankUrl = ajaxUrl + '?action=mj_expense_bank_statement&expense_id=' + exp.id + '&nonce=' + encodeURIComponent(nonce);
          var bankExt = (exp.bank_statement.split('.').pop() || '').toLowerCase();
          var isBankPdf = bankExt === 'pdf';
          
          h += '<div class="mj-expenses__justificatif-item">';
          h += '<span class="mj-expenses__justificatif-label">🏦 Extrait CB</span>';
          
          if (isBankPdf) {
            h += '<a href="#" class="mj-expenses__justificatif-link mj-expenses__justificatif-pdf mj-expenses__viewer-trigger" data-url="' + bankUrl + '" data-type="pdf" data-title="Extrait CB PDF" title="Voir extrait">';
            h += '<span class="mj-expenses__pdf-icon">📄</span>';
            h += '</a>';
          } else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(bankExt) !== -1) {
            h += '<a href="#" class="mj-expenses__justificatif-link mj-expenses__viewer-trigger" data-url="' + bankUrl + '" data-type="image" data-title="Extrait CB" title="Voir extrait">';
            h += '<span class="mj-expenses__pdf-icon">🖼️</span>';
            h += '</a>';
          } else {
            h += '<a href="' + bankUrl + '" target="_blank" class="mj-expenses__justificatif-link">Fichier</a>';
          }
          h += '</div>';
        }
        
        h += '</div>';
      } else {
        h += '–';
      }
      h += '</td>';

      h += '<td class="mj-expenses__actions-cell">';
      h += '<div class="mj-expenses__actions">';
      var canOwnerEdit = (exp.member_id === currentMemberId && exp.status === 'pending');
      if (isCoordinator) {
        // Step 1: Approve/Reject
        if (exp.status === 'pending') {
          h += '<button class="mj-expenses__btn mj-expenses__btn--xs mj-expenses__btn--success" data-action="approve" data-id="' + exp.id + '" title="Valider">' + esc(i18n.approve) + '</button>';
          h += '<button class="mj-expenses__btn mj-expenses__btn--xs mj-expenses__btn--danger" data-action="reject" data-id="' + exp.id + '" title="Refuser">' + esc(i18n.reject) + '</button>';
        }
        
        // Step 2: Mark as reimbursed (only for cash_perso, cb_perso)
        if (exp.status === 'approved' && (exp.payment_method === 'cash_perso' || exp.payment_method === 'cb_perso')) {
          h += '<button class="mj-expenses__btn mj-expenses__btn--xs mj-expenses__btn--success" data-action="mark-reimbursed" data-id="' + exp.id + '" title="Marquer remboursé">' + esc(i18n.markAsReimbursed) + '</button>';
        }
        
        // Step 3: Accountingized (archive)
        if (exp.status === 'reimbursed' || (exp.payment_method && ['cash_mj', 'cb_mj'].indexOf(exp.payment_method) !== -1 && exp.status === 'approved')) {
          h += '<button class="mj-expenses__btn mj-expenses__btn--xs mj-expenses__btn--info" data-action="accountingize" data-id="' + exp.id + '" title="Comptabiliser">' + esc(i18n.accountingized) + '</button>';
        }
        
        h += '<button class="mj-expenses__btn mj-expenses__btn--xs mj-expenses__btn--danger-outline" data-action="delete" data-id="' + exp.id + '" title="Supprimer">' + esc(i18n.delete) + '</button>';
      }
      if (canOwnerEdit) {
        h += '<button class="mj-expenses__btn mj-expenses__btn--xs mj-expenses__btn--primary" data-action="edit" data-id="' + exp.id + '" title="Modifier">' + esc(i18n.edit || 'Modifier') + '</button>';
        if (!isCoordinator) {
          h += '<button class="mj-expenses__btn mj-expenses__btn--xs mj-expenses__btn--danger-outline" data-action="delete" data-id="' + exp.id + '" title="Supprimer">' + esc(i18n.delete) + '</button>';
        }
      }
      h += '</div>';
      h += '</td>';
      h += '</tr>';
    });

    h += '</tbody></table></div>';
    return h;
  }

  /* ── event binding ───────────────────────────────────── */
  function bindEvents(ctx) {
    // Tabs
    $$('.mj-expenses__tab', ctx).forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.tab = btn.dataset.tab;
        render();
      });
    });

    // Add button
    var addBtn = $('#mj-exp-add-btn', ctx);
    if (addBtn) {
      addBtn.addEventListener('click', function () {
        state.editExpense = null;
        state.showForm = true;
        render();
      });
    }

    // Cancel button
    var cancelBtn = $('#mj-exp-cancel-btn', ctx);
    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        state.showForm = false;
        state.editExpense = null;
        render();
      });
    }

    // Overlay click
    var overlay = $('#mj-exp-form-overlay', ctx);
    if (overlay) {
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
          state.showForm = false;
          state.editExpense = null;
          render();
        }
      });
    }

    // Receipt delete buttons inside edit form
    $$('.mj-expenses__receipt-item-delete', ctx).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var li = btn.closest('.mj-expenses__receipt-item');
        if (!li) return;
        li.classList.add('mj-expenses__receipt-item--removed');
        btn.style.display = 'none';
        // Add a hidden input so the server knows which file to delete
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'delete_files[]';
        input.value = btn.dataset.removeIndex;
        var form = btn.closest('form');
        if (form) form.appendChild(input);
      });
    });

    // Payment details toggle
    var isPaidCheckbox = $('#mj-exp-is-paid', ctx);
    var paymentDetails = $('#mj-exp-payment-details', ctx);
    if (isPaidCheckbox && paymentDetails) {
      isPaidCheckbox.addEventListener('change', function () {
        paymentDetails.style.display = isPaidCheckbox.checked ? 'block' : 'none';
      });
    }

    // Bank statement toggle (CB Perso)
    var paymentMethod = $('#mj-exp-payment-method', ctx);
    var bankStatement = $('#mj-exp-bank-statement', ctx);
    if (paymentMethod && bankStatement) {
      paymentMethod.addEventListener('change', function () {
        bankStatement.style.display = paymentMethod.value === 'cb_perso' ? 'block' : 'none';
      });
    }

    // Form submit
    var form = $('#mj-exp-form', ctx);
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var msgEl = $('#mj-exp-form-msg', ctx);
        var fd = new FormData(form);
        var isEdit = !!state.editExpense;

        // Gather multi-select values for event_ids
        var eventSelect = form.querySelector('select[name="event_ids"]');
        if (eventSelect) {
          Array.prototype.slice.call(eventSelect.selectedOptions).forEach(function (opt) {
            fd.append('event_ids[]', opt.value);
          });
          fd.delete('event_ids');
        }

        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) submitBtn.disabled = true;

        var action = isEdit ? 'mj_expense_update' : 'mj_expense_create';
        post(action, fd, function (res) {
          if (submitBtn) submitBtn.disabled = false;
          if (res.success && res.data && res.data.expense) {
            var updated = res.data.expense;
            if (isEdit) {
              // Replace in lists
              [ownExpenses, allExpenses].forEach(function (list) {
                for (var i = 0; i < list.length; i++) {
                  if (list[i].id === updated.id) { list[i] = updated; break; }
                }
              });
            } else {
              ownExpenses.unshift(updated);
              if (isCoordinator) allExpenses.unshift(updated);
            }
            state.showForm = false;
            state.editExpense = null;
            render();
          } else {
            if (msgEl) {
              msgEl.textContent = (res.data && res.data.message) || i18n.error;
              msgEl.className = 'mj-expenses__msg mj-expenses__msg--error';
            }
          }
        });
      });
    }

    // Filters
    $$('.mj-expenses__filter', ctx).forEach(function (sel) {
      sel.addEventListener('change', function () {
        var key = sel.dataset.filter;
        var val = sel.value;
        if (key === 'member') state.filterMember = parseInt(val, 10) || 0;
        if (key === 'project') state.filterProject = parseInt(val, 10) || 0;
        if (key === 'event') state.filterEvent = parseInt(val, 10) || 0;
        if (key === 'status') state.filterStatus = val;
        if (key === 'paymentMethod') state.filterPaymentMethod = val;
        render();
      });
    });

    // Status toggle buttons
    $$('.mj-expenses__toggle', ctx).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.dataset.toggle;
        if (key && state.hasOwnProperty(key)) {
          state[key] = !state[key];
          render();
        }
      });
    });

    // Action buttons
    $$('[data-action]', ctx).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var action = btn.dataset.action;
        var id = parseInt(btn.dataset.id, 10);
        if (!id) return;

        if (action === 'edit') {
          // Find expense in lists
          var expToEdit = null;
          ownExpenses.forEach(function (e) { if (e.id === id) expToEdit = e; });
          if (!expToEdit) allExpenses.forEach(function (e) { if (e.id === id) expToEdit = e; });
          if (expToEdit && expToEdit.status === 'pending') {
            state.editExpense = expToEdit;
            state.showForm = true;
            render();
          }
        } else if (action === 'delete') {
          if (!confirm(i18n.confirmDelete)) return;
          post('mj_expense_delete', { expense_id: id }, function (res) {
            if (res.success) {
              removeExpense(id);
              render();
            } else {
              alert((res.data && res.data.message) || i18n.error);
            }
          });
        } else if (action === 'approve') {
          post('mj_expense_update_status', { expense_id: id, status: 'approved' }, function (res) {
            if (res.success) {
              updateExpenseStatus(id, 'approved');
              render();
            } else {
              alert((res.data && res.data.message) || i18n.error);
            }
          });
        } else if (action === 'mark-reimbursed') {
          post('mj_expense_update_status', { expense_id: id, status: 'reimbursed' }, function (res) {
            if (res.success) {
              updateExpenseStatus(id, 'reimbursed');
              render();
            } else {
              alert((res.data && res.data.message) || i18n.error);
            }
          });
        } else if (action === 'accountingize') {
          post('mj_expense_update_status', { expense_id: id, status: 'accountingized' }, function (res) {
            if (res.success) {
              updateExpenseStatus(id, 'accountingized');
              render();
            } else {
              alert((res.data && res.data.message) || i18n.error);
            }
          });
        } else if (action === 'reject') {
          var reason = prompt(i18n.rejectionReason);
          if (reason === null) return;
          if (!reason.trim()) {
            alert(i18n.rejectionReasonRequired);
            return;
          }
          post('mj_expense_update_status', { expense_id: id, status: 'rejected', comment: reason }, function (res) {
            if (res.success) {
              updateExpenseStatus(id, 'rejected', reason);
              render();
            } else {
              alert((res.data && res.data.message) || i18n.error);
            }
          });
        }
      });
    });

    // Viewer triggers
    $$('.mj-expenses__viewer-trigger', ctx).forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();
        var url = link.dataset.url;
        var type = link.dataset.type;
        var title = link.dataset.title || '';
        openViewer(url, type, title);
      });
    });
  }

  /* ── Viewer Modal ─────────────────────────────────────── */
  function openViewer(url, type, title) {
    var overlay = document.createElement('div');
    overlay.className = 'mj-expenses__viewer-overlay';
    overlay.id = 'mj-expenses-viewer';

    var header = '<div class="mj-expenses__viewer-header">';
    header += '<h3 class="mj-expenses__viewer-title">' + esc(title) + '</h3>';
    header += '<div class="mj-expenses__viewer-actions">';
    header += '<button class="mj-expenses__viewer-btn" id="mj-viewer-download" title="Télécharger">';
    header += '⬇️ Télécharger';
    header += '</button>';
    header += '<button class="mj-expenses__viewer-btn" id="mj-viewer-print" title="Imprimer">';
    header += '🖨️ Imprimer';
    header += '</button>';
    header += '<button class="mj-expenses__viewer-btn mj-expenses__viewer-btn--close" id="mj-viewer-close" title="Fermer">';
    header += '×';
    header += '</button>';
    header += '</div>';
    header += '</div>';

    var content;
    if (type === 'image') {
      content = '<div class="mj-expenses__viewer-content">';
      content += '<img src="' + url + '" alt="' + esc(title) + '" class="mj-expenses__viewer-image" id="mj-viewer-content" />';
      content += '</div>';
    } else {
      content = '<div class="mj-expenses__viewer-content">';
      content += '<iframe src="' + url + '" class="mj-expenses__viewer-iframe" id="mj-viewer-content"></iframe>';
      content += '</div>';
    }

    overlay.innerHTML = header + content;
    document.body.appendChild(overlay);

    // Close handlers
    var closeBtn = document.getElementById('mj-viewer-close');
    var closeViewer = function () {
      document.body.removeChild(overlay);
    };

    if (closeBtn) {
      closeBtn.addEventListener('click', closeViewer);
    }

    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) {
        closeViewer();
      }
    });

    // Escape key
    var escHandler = function (e) {
      if (e.key === 'Escape') {
        closeViewer();
        document.removeEventListener('keydown', escHandler);
      }
    };
    document.addEventListener('keydown', escHandler);

    // Download handler
    var downloadBtn = document.getElementById('mj-viewer-download');
    if (downloadBtn) {
      downloadBtn.addEventListener('click', function () {
        var link = document.createElement('a');
        link.href = url;
        link.download = title || 'document';
        link.target = '_blank';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
      });
    }

    // Print handler
    var printBtn = document.getElementById('mj-viewer-print');
    if (printBtn) {
      printBtn.addEventListener('click', function () {
        if (type === 'image') {
          var img = document.getElementById('mj-viewer-content');
          var win = window.open('');
          win.document.write('<html><head><title>' + esc(title) + '</title></head><body style="margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh;">');
          win.document.write('<img src="' + img.src + '" style="max-width:100%;height:auto;" />');
          win.document.write('</body></html>');
          win.document.close();
          win.focus();
          setTimeout(function () {
            win.print();
            win.close();
          }, 250);
        } else {
          var iframe = document.getElementById('mj-viewer-content');
          iframe.contentWindow.print();
        }
      });
    }
  }

  function removeExpense(id) {
    ownExpenses = ownExpenses.filter(function (e) { return e.id !== id; });
    allExpenses = allExpenses.filter(function (e) { return e.id !== id; });
  }

  function updateExpenseStatus(id, newStatus, comment) {
    [ownExpenses, allExpenses].forEach(function (list) {
      list.forEach(function (e) {
        if (e.id === id) {
          e.status = newStatus;
          if (comment) e.reviewer_comment = comment;
        }
      });
    });
  }

  /* ── init ────────────────────────────────────────────── */
  function init() {
    var root = getRoot();
    if (!root) return;
    if (!cfg.hasAccess && !isCoordinator) return;
    render();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
