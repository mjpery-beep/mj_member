/**
 * Employee Documents Widget (Preact + Hooks)
 *
 * Staff members see their own documents organised in tabs.
 *
 * @package MJ_Member
 */
(function () {
    'use strict';

    var _p = window.preact;
    var h = _p.h, render = _p.render;
    var _ph = window.preactHooks;
    var useState = _ph.useState, useEffect = _ph.useEffect, useCallback = _ph.useCallback,
        useMemo = _ph.useMemo;

    /* ================================================================ *
     *  Helpers                                                          *
     * ================================================================ */

    var TABS = [
        { key: 'payslip',  icon: 'fa-file-invoice-dollar' },
        { key: 'contract', icon: 'fa-file-signature' },
        { key: 'misc',     icon: 'fa-file-alt' }
    ];

    var TAB_LABELS = { payslip: 'tabPayslip', contract: 'tabContract', misc: 'tabMisc' };

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' o';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' Ko';
        return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
    }

    function formatDate(str) {
        if (!str) return '';
        var d = new Date(str);
        return d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function getMonthLabel(month, i18n) {
        if (!month) return '';
        var idx = month - 1;
        return i18n.months && i18n.months[idx] ? i18n.months[idx] : '';
    }

    function post(url, body) {
        return fetch(url, { method: 'POST', body: body }).then(function (r) { return r.json(); });
    }

    /* ================================================================ *
     *  Tab bar (segmented control)                                      *
     * ================================================================ */

    function TabBar(_ref) {
        var active = _ref.active, onChange = _ref.onChange, counts = _ref.counts, i18n = _ref.i18n;
        return h('div', { className: 'mj-empdocs__tabs' },
            TABS.map(function (t) {
                var cls = 'mj-empdocs__tab' + (active === t.key ? ' mj-empdocs__tab--active' : '');
                var count = counts[t.key] || 0;
                return h('button', {
                    key: t.key,
                    className: cls,
                    onClick: function () { onChange(t.key); },
                    type: 'button'
                },
                    h('i', { className: 'fas ' + t.icon }),
                    ' ',
                    i18n[TAB_LABELS[t.key]],
                    count > 0 ? h('span', { className: 'mj-empdocs__tab-badge' }, count) : null
                );
            })
        );
    }

    /* ================================================================ *
     *  Document card                                                    *
     * ================================================================ */

    function DocumentCard(_ref) {
        var doc = _ref.doc, i18n = _ref.i18n, cfg = _ref.cfg;

        var handleDownload = useCallback(function () {
            var url = cfg.ajaxUrl + '?action=mj_empdocs_download&doc_id=' + doc.id + '&nonce=' + encodeURIComponent(cfg.nonce);
            window.open(url, '_blank');
        }, [doc.id, cfg]);

        var periodLabel = '';
        if (doc.payslipMonth && doc.payslipYear) {
            periodLabel = getMonthLabel(doc.payslipMonth, i18n) + ' ' + doc.payslipYear;
        }

        var isPdf = doc.mimeType === 'application/pdf';

        return h('div', { className: 'mj-empdocs__card' },
            h('div', { className: 'mj-empdocs__card-icon' },
                h('i', { className: isPdf ? 'fas fa-file-pdf' : 'fas fa-file-image' })
            ),
            h('div', { className: 'mj-empdocs__card-body' },
                h('div', { className: 'mj-empdocs__card-title' }, doc.label || doc.originalName),
                h('div', { className: 'mj-empdocs__card-meta' },
                    periodLabel ? h('span', { className: 'mj-empdocs__card-period' },
                        h('i', { className: 'fas fa-calendar-alt' }), ' ', periodLabel
                    ) : null,
                    h('span', { className: 'mj-empdocs__card-size' }, formatBytes(doc.fileSize)),
                    doc.documentDate ? h('span', { className: 'mj-empdocs__card-date' }, formatDate(doc.documentDate)) : null
                )
            ),
            h('div', { className: 'mj-empdocs__card-actions' },
                h('button', {
                    className: 'mj-empdocs__btn mj-empdocs__btn--download',
                    title: i18n.download || 'Télécharger',
                    onClick: handleDownload,
                    type: 'button'
                }, h('i', { className: 'fas fa-download' }))
            )
        );
    }

    /* ================================================================ *
     *  Document list                                                    *
     * ================================================================ */

    function DocumentList(_ref) {
        var documents = _ref.documents, type = _ref.type, i18n = _ref.i18n, cfg = _ref.cfg;

        var filtered = useMemo(function () {
            return documents.filter(function (d) { return d.docType === type; })
                .sort(function (a, b) {
                    // Payslips: most recent first (year desc, month desc)
                    if (type === 'payslip') {
                        if (a.payslipYear !== b.payslipYear) return (b.payslipYear || 0) - (a.payslipYear || 0);
                        return (b.payslipMonth || 0) - (a.payslipMonth || 0);
                    }
                    // Others: most recent date first
                    return (b.documentDate || '').localeCompare(a.documentDate || '');
                });
        }, [documents, type]);

        if (filtered.length === 0) {
            return h('div', { className: 'mj-empdocs__empty' },
                h('i', { className: 'fas fa-folder-open' }),
                h('p', null, i18n.noDocuments)
            );
        }

        return h('div', { className: 'mj-empdocs__list' },
            filtered.map(function (doc) {
                return h(DocumentCard, { key: doc.id, doc: doc, i18n: i18n, cfg: cfg });
            })
        );
    }

    /* ================================================================ *
     *  Main App                                                         *
     * ================================================================ */

    function EmployeeDocumentsApp(_ref) {
        var config = _ref.config;

        var cfg = config;
        var i18n = cfg.i18n;

        var _t = useState('payslip');
        var activeTab = _t[0], setActiveTab = _t[1];

        var _d = useState(cfg.documents || []);
        var documents = _d[0], setDocuments = _d[1];

        var _l = useState(!cfg.preview);
        var loading = _l[0], setLoading = _l[1];

        // Load documents on mount
        useEffect(function () {
            if (cfg.preview || !cfg.hasAccess) {
                setLoading(false);
                return;
            }
            var fd = new FormData();
            fd.append('action', 'mj_empdocs_get_my_documents');
            fd.append('nonce', cfg.nonce);
            post(cfg.ajaxUrl, fd).then(function (res) {
                if (res.success && res.data && res.data.documents) {
                    setDocuments(res.data.documents);
                }
                setLoading(false);
            }).catch(function () { setLoading(false); });
        }, []);

        var counts = useMemo(function () {
            var c = { payslip: 0, contract: 0, misc: 0 };
            documents.forEach(function (d) { if (c.hasOwnProperty(d.docType)) c[d.docType]++; });
            return c;
        }, [documents]);

        if (!cfg.hasAccess) {
            return h('div', { className: 'mj-empdocs' },
                h('p', { className: 'mj-empdocs__notice' }, i18n.noAccess)
            );
        }

        return h('div', { className: 'mj-empdocs' },
            cfg.title ? h('h2', { className: 'mj-empdocs__title' }, cfg.title) : null,

            // Tabs
            h(TabBar, { active: activeTab, onChange: setActiveTab, counts: counts, i18n: i18n }),

            // Content
            loading
                ? h('div', { className: 'mj-empdocs__loading' },
                    h('div', { className: 'mj-empdocs__spinner' }),
                    h('span', null, 'Chargement…')
                )
                : h(DocumentList, {
                    documents: documents,
                    type: activeTab,
                    i18n: i18n,
                    cfg: cfg
                })
        );
    }

    /* ================================================================ *
     *  Mount                                                            *
     * ================================================================ */

    function init() {
        var containers = document.querySelectorAll('[data-mj-employee-documents-widget]');
        containers.forEach(function (el) {
            if (el.dataset.mjInit) return;
            el.dataset.mjInit = 'true';
            var raw = el.getAttribute('data-config');
            var config = {};
            try { config = JSON.parse(raw || '{}'); } catch (e) { /* */ }
            render(h(EmployeeDocumentsApp, { config: config }), el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Elementor editor support
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-employee-documents.default', init);
    }
})();
