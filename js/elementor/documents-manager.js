/**
 * MJ Member – Documents Manager Widget (Nextcloud + Google Drive)
 *
 * Provides a file browser, upload, create folder, rename, delete,
 * and — when the backend is Nextcloud with Collabora / OnlyOffice —
 * inline document editing via an iframe.
 *
 * Dependencies: mj-member-utils (escapeHtml, toInt)
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ *
   * Helpers                                                             *
   * ------------------------------------------------------------------ */

  var esc = window.mjMemberUtils && window.mjMemberUtils.escapeHtml
    ? window.mjMemberUtils.escapeHtml
    : function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

  function ajax(action, data, onSuccess, onError) {
    var cfg = window.mjMemberDocuments;
    if (!cfg) return;

    var fd;
    if (data instanceof FormData) {
      fd = data;
    } else {
      fd = new FormData();
      Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    }
    fd.append('action', action);
    fd.append('nonce', cfg.nonce);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', cfg.ajaxUrl, true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4) return;
      try {
        var resp = JSON.parse(xhr.responseText);
        if (resp.success) {
          onSuccess(resp.data);
        } else {
          var msg = (resp.data && resp.data.message) || cfg.i18n.errorGeneric;
          (onError || showError)(msg);
        }
      } catch (e) {
        (onError || showError)(cfg.i18n.errorGeneric);
      }
    };
    xhr.send(fd);
  }

  function formatSize(bytes) {
    if (!bytes || bytes <= 0) return '';
    if (bytes < 1024) return bytes + ' o';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
    return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
  }

  function formatDate(raw) {
    if (!raw) return '';
    try {
      var d = new Date(raw);
      return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch (_) {
      return raw;
    }
  }

  function mimeIcon(item) {
    if (item.type === 'folder') return '\uD83D\uDCC1'; // 📁
    var mt = (item.mimeType || '').toLowerCase();
    var name = (item.name || '').toLowerCase();
    if (mt.indexOf('pdf') !== -1 || name.endsWith('.pdf')) return '\uD83D\uDCC4'; // 📄
    if (mt.indexOf('image') !== -1) return '\uD83D\uDDBC\uFE0F'; // 🖼️
    if (mt.indexOf('spreadsheet') !== -1 || mt.indexOf('excel') !== -1 || name.endsWith('.xlsx') || name.endsWith('.ods') || name.endsWith('.csv')) return '\uD83D\uDCCA'; // 📊
    if (mt.indexOf('presentation') !== -1 || mt.indexOf('powerpoint') !== -1 || name.endsWith('.pptx') || name.endsWith('.odp')) return '\uD83D\uDCFD\uFE0F'; // 📽️
    if (mt.indexOf('wordprocessing') !== -1 || mt.indexOf('msword') !== -1 || mt.indexOf('opendocument.text') !== -1 || name.endsWith('.docx') || name.endsWith('.odt')) return '\uD83D\uDCDD'; // 📝
    if (mt.indexOf('text') !== -1 || name.endsWith('.md') || name.endsWith('.txt')) return '\uD83D\uDCC3'; // 📃
    if (mt.indexOf('zip') !== -1 || mt.indexOf('archive') !== -1) return '\uD83D\uDCE6'; // 📦
    return '\uD83D\uDCC4'; // 📄 default
  }

  function isEditableFile(item) {
    if (item.type === 'folder') return false;
    var name = (item.name || '').toLowerCase();
    var editable = ['.docx', '.xlsx', '.pptx', '.odt', '.ods', '.odp', '.txt', '.md'];
    for (var i = 0; i < editable.length; i++) {
      if (name.endsWith(editable[i])) return true;
    }
    return false;
  }

  /* ------------------------------------------------------------------ *
   * Widget class                                                        *
   * ------------------------------------------------------------------ */

  function DocumentsWidget(root) {
    this.root = root;
    this.cfg = window.mjMemberDocuments || {};
    this.widgetCfg = {};
    this.currentFolderId = '';
    this.loading = false;
    this.editorOpen = false;
    this.init();
  }

  DocumentsWidget.prototype.init = function () {
    try {
      this.widgetCfg = JSON.parse(this.root.getAttribute('data-config') || '{}');
    } catch (_) {
      this.widgetCfg = {};
    }

    if (!this.widgetCfg.hasAccess || !this.widgetCfg.isConfigured) return;

    this.currentFolderId = this.widgetCfg.defaultFolderId || this.cfg.rootFolderId || '';
    this.render();

    if (this.widgetCfg.preview && this.widgetCfg.previewData) {
      this.renderFolder(this.widgetCfg.previewData);
    } else {
      this.loadFolder(this.currentFolderId);
    }
  };

  /* -- Skeleton rendering ------------------------------------------- */

  DocumentsWidget.prototype.render = function () {
    var i18n = this.cfg.i18n || {};
    var c = this.widgetCfg;
    var isNC = this.cfg.backend === 'nextcloud';

    var html = '<div class="mj-documents-widget__inner">';

    if (c.title || c.intro) {
      html += '<div class="mj-documents-widget__header">';
      if (c.title) html += '<h2 class="mj-documents-widget__title">' + esc(c.title) + '</h2>';
      if (c.intro) html += '<div class="mj-documents-widget__intro">' + c.intro + '</div>';
      html += '</div>';
    }

    html += '<div class="mj-documents-widget__toolbar">';
    html += '  <div class="mj-documents-widget__breadcrumbs" data-role="breadcrumbs"></div>';
    html += '  <div class="mj-documents-widget__actions">';

    if (c.allowCreateFolder !== false) {
      html += '<button type="button" class="mj-documents-widget__action" data-role="create-folder">\uD83D\uDCC2 ' + esc(i18n.createFolder || 'Nouveau dossier') + '</button>';
    }

    if (isNC) {
      html += '<button type="button" class="mj-documents-widget__action mj-documents-widget__action--new-doc" data-role="create-document">\uD83D\uDCDD ' + esc(i18n.createDocument || 'Nouveau document') + '</button>';
    }

    if (c.allowUpload !== false) {
      html += '<label class="mj-documents-widget__action mj-documents-widget__action--upload">';
      html += '  \uD83D\uDCE4 ' + esc(i18n.upload || 'Téléverser');
      html += '  <input type="file" class="mj-documents-widget__upload-input" data-role="upload" multiple />';
      html += '</label>';
    }

    html += '  </div>';
    html += '</div>';

    html += '<div class="mj-documents-widget__feedback" data-role="feedback" style="display:none;"></div>';
    html += '<div class="mj-documents-widget__list" data-role="list"></div>';
    html += '</div>';

    // Editor overlay
    html += '<div class="mj-documents-widget__editor-overlay" data-role="editor-overlay" style="display:none;">';
    html += '  <div class="mj-documents-widget__editor-header">';
    html += '    <span class="mj-documents-widget__editor-title" data-role="editor-title"></span>';
    html += '    <button type="button" class="mj-documents-widget__editor-close" data-role="editor-close">\u2716 ' + esc(i18n.closeEditor || 'Fermer') + '</button>';
    html += '  </div>';
    html += '  <iframe class="mj-documents-widget__editor-frame" data-role="editor-frame" src="about:blank" allow="clipboard-read; clipboard-write"></iframe>';
    html += '</div>';

    this.root.innerHTML = html;
    this.bindEvents();
  };

  /* -- Events -------------------------------------------------------- */

  DocumentsWidget.prototype.bindEvents = function () {
    var self = this;
    var root = this.root;

    var createFolderBtn = root.querySelector('[data-role="create-folder"]');
    if (createFolderBtn) {
      createFolderBtn.addEventListener('click', function () { self.onCreateFolder(); });
    }

    var createDocBtn = root.querySelector('[data-role="create-document"]');
    if (createDocBtn) {
      createDocBtn.addEventListener('click', function () { self.onCreateDocument(); });
    }

    var uploadInput = root.querySelector('[data-role="upload"]');
    if (uploadInput) {
      uploadInput.addEventListener('change', function () { self.onUpload(this); });
    }

    var closeEditor = root.querySelector('[data-role="editor-close"]');
    if (closeEditor) {
      closeEditor.addEventListener('click', function () { self.closeEditor(); });
    }
  };

  /* -- AJAX operations ---------------------------------------------- */

  DocumentsWidget.prototype.loadFolder = function (folderId) {
    var self = this;
    var i18n = this.cfg.i18n || {};
    var actions = this.cfg.actions || {};
    this.setLoading(true);
    this.clearError();

    ajax(actions.list, { folderId: folderId || '' }, function (data) {
      self.currentFolderId = folderId || '';
      self.setLoading(false);
      self.renderFolder(data);
    }, function (msg) {
      self.setLoading(false);
      self.showError(msg || i18n.errorGeneric);
    });
  };

  DocumentsWidget.prototype.onCreateFolder = function () {
    var i18n = this.cfg.i18n || {};
    var name = prompt(i18n.createFolderPrompt || 'Nom du nouveau dossier :');
    if (!name || !name.trim()) return;

    var self = this;
    var actions = this.cfg.actions || {};
    this.setLoading(true);

    ajax(actions.createFolder, { parentId: this.currentFolderId, name: name.trim() },
      function () { self.loadFolder(self.currentFolderId); },
      function (msg) { self.setLoading(false); self.showError(msg); }
    );
  };

  DocumentsWidget.prototype.onCreateDocument = function () {
    var i18n = this.cfg.i18n || {};

    // Dropdown of document types
    var types = [
      { ext: '.docx', label: i18n.newDocx || 'Document texte (.docx)' },
      { ext: '.xlsx', label: i18n.newXlsx || 'Tableur (.xlsx)' },
      { ext: '.pptx', label: i18n.newPptx || 'Présentation (.pptx)' },
      { ext: '.odt',  label: i18n.newOdt  || 'Document texte (.odt)' },
      { ext: '.md',   label: i18n.newMd   || 'Note Markdown (.md)' },
    ];

    var choice = prompt(
      (i18n.createDocumentPrompt || 'Nom du document (ex: rapport.docx) :') +
      '\n\nTypes disponibles :\n' + types.map(function (t, i) { return (i + 1) + '. ' + t.label; }).join('\n') +
      '\n\n(Tapez le nom complet avec extension)'
    );

    if (!choice || !choice.trim()) return;

    var self = this;
    var actions = this.cfg.actions || {};
    this.setLoading(true);

    ajax(actions.createDocument, { parentId: this.currentFolderId, name: choice.trim() },
      function () { self.loadFolder(self.currentFolderId); },
      function (msg) { self.setLoading(false); self.showError(msg); }
    );
  };

  DocumentsWidget.prototype.onRename = function (item) {
    var i18n = this.cfg.i18n || {};
    var newName = prompt(i18n.renamePrompt || 'Nouveau nom :', item.name);
    if (!newName || newName.trim() === '' || newName.trim() === item.name) return;

    var self = this;
    var actions = this.cfg.actions || {};
    this.setLoading(true);

    ajax(actions.rename, { itemId: item.id, name: newName.trim() },
      function () { self.loadFolder(self.currentFolderId); },
      function (msg) { self.setLoading(false); self.showError(msg); }
    );
  };

  DocumentsWidget.prototype.onDelete = function (item) {
    var i18n = this.cfg.i18n || {};
    var msg = (i18n.confirmDelete || 'Supprimer « %s » ?').replace('%s', item.name);
    if (!confirm(msg)) return;

    var self = this;
    var actions = this.cfg.actions || {};
    this.setLoading(true);

    ajax(actions['delete'], { itemId: item.id },
      function () { self.loadFolder(self.currentFolderId); },
      function (msg) { self.setLoading(false); self.showError(msg); }
    );
  };

  DocumentsWidget.prototype.onUpload = function (input) {
    if (!input.files || !input.files.length) return;

    var self = this;
    var i18n = this.cfg.i18n || {};
    var actions = this.cfg.actions || {};
    this.setLoading(true);
    this.showFeedback(i18n.uploadInProgress || 'Téléversement en cours…');

    var fd = new FormData();
    fd.append('parentId', this.currentFolderId);
    for (var i = 0; i < input.files.length; i++) {
      fd.append('files[]', input.files[i]);
    }

    ajax(actions.upload, fd,
      function () { input.value = ''; self.loadFolder(self.currentFolderId); },
      function (msg) { input.value = ''; self.setLoading(false); self.showError(msg); }
    );
  };

  DocumentsWidget.prototype.onDirectEdit = function (item) {
    var self = this;
    var i18n = this.cfg.i18n || {};
    var actions = this.cfg.actions || {};
    this.setLoading(true);

    ajax(actions.directEdit, { filePath: item.id },
      function (data) {
        self.setLoading(false);
        if (data && data.url) {
          self.openEditor(data.url, item.name);
        } else {
          self.showError(i18n.editorNotAvailable || 'Éditeur non disponible.');
        }
      },
      function (msg) {
        self.setLoading(false);
        self.showError(msg || i18n.editorNotAvailable || 'Éditeur non disponible.');
      }
    );
  };

  /* -- Rendering ---------------------------------------------------- */

  DocumentsWidget.prototype.renderFolder = function (data) {
    this.renderBreadcrumbs(data.breadcrumbs || []);
    this.renderItems(data.items || []);
  };

  DocumentsWidget.prototype.renderBreadcrumbs = function (crumbs) {
    var self = this;
    var i18n = this.cfg.i18n || {};
    var el = this.root.querySelector('[data-role="breadcrumbs"]');
    if (!el) return;

    el.innerHTML = '';
    crumbs.forEach(function (crumb, idx) {
      if (idx > 0) {
        var sep = document.createElement('span');
        sep.className = 'mj-documents-widget__breadcrumb-separator';
        sep.textContent = '/';
        el.appendChild(sep);
      }
      var btn = document.createElement('button');
      btn.className = 'mj-documents-widget__breadcrumb';
      btn.textContent = idx === 0 ? (i18n.breadcrumbRoot || crumb.name || 'Racine') : crumb.name;
      btn.type = 'button';

      if (idx === crumbs.length - 1) {
        btn.disabled = true;
      } else {
        btn.addEventListener('click', function () {
          self.loadFolder(crumb.id);
        });
      }
      el.appendChild(btn);
    });
  };

  DocumentsWidget.prototype.renderItems = function (items) {
    var self = this;
    var i18n = this.cfg.i18n || {};
    var listEl = this.root.querySelector('[data-role="list"]');
    if (!listEl) return;

    listEl.innerHTML = '';

    if (!items.length) {
      listEl.innerHTML = '<p class="mj-documents-widget__empty">' + esc(i18n.empty || 'Aucun fichier.') + '</p>';
      return;
    }

    var isNC = this.cfg.backend === 'nextcloud';

    items.forEach(function (item) {
      var row = document.createElement('div');
      row.className = 'mj-documents-widget__item';

      // Main clickable area
      var main = document.createElement('button');
      main.type = 'button';
      main.className = 'mj-documents-widget__item-main';

      var icon = document.createElement('span');
      icon.className = 'mj-documents-widget__item-icon';
      icon.textContent = mimeIcon(item);

      var name = document.createElement('span');
      name.className = 'mj-documents-widget__item-name';
      name.textContent = item.name;

      main.appendChild(icon);
      main.appendChild(name);

      if (item.type === 'folder') {
        main.addEventListener('click', function () { self.loadFolder(item.id); });
      } else if (item.webViewLink) {
        main.addEventListener('click', function () { window.open(item.webViewLink, '_blank'); });
      } else {
        main.disabled = true;
      }

      // Meta
      var meta = document.createElement('span');
      meta.className = 'mj-documents-widget__item-meta';
      var metaParts = [];
      if (item.size > 0) metaParts.push(formatSize(item.size));
      if (item.modifiedTime) metaParts.push(formatDate(item.modifiedTime));
      meta.textContent = metaParts.join(' · ');

      // Actions
      var actions = document.createElement('div');
      actions.className = 'mj-documents-widget__item-actions';

      // Edit button (Nextcloud only, for editable files)
      if (isNC && isEditableFile(item)) {
        var editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'mj-documents-widget__item-action mj-documents-widget__item-action--edit';
        editBtn.textContent = '\u270F\uFE0F ' + (i18n.edit || 'Modifier');
        editBtn.addEventListener('click', function () { self.onDirectEdit(item); });
        actions.appendChild(editBtn);
      }

      // Rename
      if (self.widgetCfg.allowRename !== false) {
        var renameBtn = document.createElement('button');
        renameBtn.type = 'button';
        renameBtn.className = 'mj-documents-widget__item-action';
        renameBtn.textContent = i18n.rename || 'Renommer';
        renameBtn.addEventListener('click', function () { self.onRename(item); });
        actions.appendChild(renameBtn);
      }

      // Delete (Nextcloud only)
      if (isNC) {
        var deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'mj-documents-widget__item-action mj-documents-widget__item-action--delete';
        deleteBtn.textContent = '\uD83D\uDDD1\uFE0F ' + (i18n['delete'] || 'Supprimer');
        deleteBtn.addEventListener('click', function () { self.onDelete(item); });
        actions.appendChild(deleteBtn);
      }

      row.appendChild(main);
      row.appendChild(meta);
      row.appendChild(actions);
      listEl.appendChild(row);
    });
  };

  /* -- Editor overlay ----------------------------------------------- */

  DocumentsWidget.prototype.openEditor = function (url, title) {
    var overlay = this.root.querySelector('[data-role="editor-overlay"]');
    var frame = this.root.querySelector('[data-role="editor-frame"]');
    var titleEl = this.root.querySelector('[data-role="editor-title"]');
    if (!overlay || !frame) return;

    this.editorOpen = true;
    if (titleEl) titleEl.textContent = title || '';
    frame.src = url;
    overlay.style.display = '';
    document.body.style.overflow = 'hidden';
  };

  DocumentsWidget.prototype.closeEditor = function () {
    var overlay = this.root.querySelector('[data-role="editor-overlay"]');
    var frame = this.root.querySelector('[data-role="editor-frame"]');
    if (!overlay || !frame) return;

    this.editorOpen = false;
    frame.src = 'about:blank';
    overlay.style.display = 'none';
    document.body.style.overflow = '';

    // Refresh file list after editing
    this.loadFolder(this.currentFolderId);
  };

  /* -- UI helpers --------------------------------------------------- */

  DocumentsWidget.prototype.setLoading = function (on) {
    this.loading = on;
    var inner = this.root.querySelector('.mj-documents-widget__inner');
    if (inner) {
      inner.classList.toggle('is-loading', on);
    }
  };

  DocumentsWidget.prototype.showError = function (msg) {
    var el = this.root.querySelector('[data-role="feedback"]');
    if (!el) return;
    el.textContent = msg;
    el.style.display = '';
  };

  DocumentsWidget.prototype.showFeedback = function (msg) {
    var el = this.root.querySelector('[data-role="feedback"]');
    if (!el) return;
    el.style.color = '#2563eb';
    el.textContent = msg;
    el.style.display = '';
  };

  DocumentsWidget.prototype.clearError = function () {
    var el = this.root.querySelector('[data-role="feedback"]');
    if (!el) return;
    el.textContent = '';
    el.style.display = 'none';
    el.style.color = '';
  };

  var showError = function (msg) {
    // Global fallback; individual widget instances override
    if (typeof console !== 'undefined') console.error('[Documents]', msg);
  };

  /* ------------------------------------------------------------------ *
   * Bootstrap all widgets on the page                                   *
   * ------------------------------------------------------------------ */

  function initAll() {
    var widgets = document.querySelectorAll('[data-mj-member-documents-widget]');
    for (var i = 0; i < widgets.length; i++) {
      if (!widgets[i]._mjDocsInit) {
        widgets[i]._mjDocsInit = true;
        new DocumentsWidget(widgets[i]);
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
