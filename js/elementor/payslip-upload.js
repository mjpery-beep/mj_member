/**
 * Payslip Upload Widget (Preact + Hooks)
 *
 * Coordinator-only: bulk payslip upload with per-employee drag-and-drop zones.
 * Uses the same AJAX endpoints / nonce as the Employee Documents widget.
 *
 * @package MJ_Member
 */
(function () {
    'use strict';

    var _p = window.preact;
    var h = _p.h, render = _p.render;
    var _ph = window.preactHooks;
    var useState = _ph.useState, useCallback = _ph.useCallback, useMemo = _ph.useMemo, useRef = _ph.useRef;

    /* ================================================================ *
     *  Helpers                                                          *
     * ================================================================ */

    function post(url, body) {
        return fetch(url, { method: 'POST', body: body }).then(function (r) { return r.json(); });
    }

    /* ================================================================ *
     *  Employee drop zone                                               *
     * ================================================================ */

    function EmployeeDropZone(_ref) {
        var employee = _ref.employee, year = _ref.year, month = _ref.month, cfg = _ref.cfg, i18n = _ref.i18n, onUploaded = _ref.onUploaded;

        var _s = useState('idle'); // idle | dragover | uploading | done | error
        var status = _s[0], setStatus = _s[1];
        var _m = useState('');
        var message = _m[0], setMessage = _m[1];
        var fileRef = useRef(null);

        var handleFile = useCallback(function (file) {
            if (!file) return;

            var allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'];
            if (allowed.indexOf(file.type) === -1) {
                setStatus('error');
                setMessage(i18n.invalidFormat);
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                setStatus('error');
                setMessage(i18n.fileTooBig);
                return;
            }

            setStatus('uploading');
            setMessage(i18n.uploading);

            var fd = new FormData();
            fd.append('action', 'mj_empdocs_upload');
            fd.append('nonce', cfg.nonce);
            fd.append('memberId', employee.id);
            fd.append('docType', 'payslip');
            fd.append('payslipMonth', month);
            fd.append('payslipYear', year);
            fd.append('file', file);

            post(cfg.ajaxUrl, fd).then(function (res) {
                if (res.success) {
                    setStatus('done');
                    setMessage(i18n.uploaded);
                    if (onUploaded) onUploaded(res.data.document);
                    setTimeout(function () { setStatus('idle'); setMessage(''); }, 2000);
                } else {
                    setStatus('error');
                    setMessage(res.data && res.data.message ? res.data.message : i18n.uploadError);
                }
            }).catch(function () {
                setStatus('error');
                setMessage(i18n.uploadError);
            });
        }, [employee, year, month, cfg, i18n, onUploaded]);

        var onDragOver = useCallback(function (e) {
            e.preventDefault();
            if (status !== 'uploading') setStatus('dragover');
        }, [status]);

        var onDragLeave = useCallback(function (e) {
            e.preventDefault();
            if (status === 'dragover') setStatus('idle');
        }, [status]);

        var onDrop = useCallback(function (e) {
            e.preventDefault();
            if (status === 'uploading') return;
            var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
            handleFile(file);
        }, [status, handleFile]);

        var onClick = useCallback(function () {
            if (status === 'uploading') return;
            if (fileRef.current) fileRef.current.click();
        }, [status]);

        var onFileChange = useCallback(function (e) {
            var file = e.target.files && e.target.files[0];
            if (file) handleFile(file);
            e.target.value = '';
        }, [handleFile]);

        var zoneClass = 'mj-payslip__dropzone' +
            (status === 'dragover' ? ' mj-payslip__dropzone--active' : '') +
            (status === 'uploading' ? ' mj-payslip__dropzone--uploading' : '') +
            (status === 'done' ? ' mj-payslip__dropzone--done' : '') +
            (status === 'error' ? ' mj-payslip__dropzone--error' : '');

        return h('div', { className: zoneClass, onDragOver: onDragOver, onDragLeave: onDragLeave, onDrop: onDrop, onClick: onClick },
            h('input', {
                ref: fileRef, type: 'file', className: 'mj-payslip__dropzone-input',
                accept: '.pdf,.jpg,.jpeg,.png,.gif',
                onChange: onFileChange
            }),
            h('div', { className: 'mj-payslip__dropzone-avatar' },
                employee.avatar
                    ? h('img', { src: employee.avatar, alt: employee.name, className: 'mj-payslip__avatar-img' })
                    : h('span', { className: 'mj-payslip__avatar-initials' }, employee.initials)
            ),
            h('div', { className: 'mj-payslip__dropzone-info' },
                h('span', { className: 'mj-payslip__dropzone-name' }, employee.name),
                status === 'idle' ? h('span', { className: 'mj-payslip__dropzone-hint' }, i18n.dropHint) : null,
                status === 'dragover' ? h('span', { className: 'mj-payslip__dropzone-hint mj-payslip__dropzone-hint--active' }, i18n.dropHintActive) : null,
                (status === 'uploading' || status === 'done' || status === 'error')
                    ? h('span', { className: 'mj-payslip__dropzone-status mj-payslip__dropzone-status--' + status }, message)
                    : null
            ),
            status === 'uploading' ? h('div', { className: 'mj-payslip__dropzone-spinner' }) : null,
            status === 'done' ? h('i', { className: 'fas fa-check mj-payslip__dropzone-check' }) : null
        );
    }

    /* ================================================================ *
     *  Bulk upload panel                                                *
     * ================================================================ */

    function BulkUploadPanel(_ref) {
        var employees = _ref.employees, cfg = _ref.cfg, i18n = _ref.i18n;

        var now = new Date();
        var _y = useState(now.getFullYear());
        var year = _y[0], setYear = _y[1];
        var _mo = useState(now.getMonth() + 1);
        var month = _mo[0], setMonth = _mo[1];

        var _uploads = useState([]);
        var recentUploads = _uploads[0], setRecentUploads = _uploads[1];

        var years = useMemo(function () {
            var arr = [];
            var cur = now.getFullYear();
            for (var y = cur; y >= cur - 5; y--) arr.push(y);
            return arr;
        }, []);

        var handleUploaded = useCallback(function (doc) {
            setRecentUploads(function (prev) { return [doc].concat(prev); });
        }, []);

        return h('div', { className: 'mj-payslip' },
            h('div', { className: 'mj-payslip__panel' },
                h('h3', { className: 'mj-payslip__title' },
                    h('i', { className: 'fas fa-cloud-upload-alt' }), ' ', i18n.uploadTitle
                ),
                h('div', { className: 'mj-payslip__selectors' },
                    h('div', { className: 'mj-payslip__selector' },
                        h('label', { className: 'mj-payslip__selector-label' }, i18n.year),
                        h('select', {
                            className: 'mj-payslip__select',
                            value: year,
                            onChange: function (e) { setYear(parseInt(e.target.value, 10)); }
                        },
                            years.map(function (y) { return h('option', { key: y, value: y }, y); })
                        )
                    ),
                    h('div', { className: 'mj-payslip__selector' },
                        h('label', { className: 'mj-payslip__selector-label' }, i18n.month),
                        h('select', {
                            className: 'mj-payslip__select',
                            value: month,
                            onChange: function (e) { setMonth(parseInt(e.target.value, 10)); }
                        },
                            i18n.months.map(function (name, idx) {
                                return h('option', { key: idx + 1, value: idx + 1 }, name);
                            })
                        )
                    )
                ),
                h('div', { className: 'mj-payslip__dropzones' },
                    employees.map(function (emp) {
                        return h(EmployeeDropZone, {
                            key: emp.id, employee: emp, year: year, month: month,
                            cfg: cfg, i18n: i18n, onUploaded: handleUploaded
                        });
                    })
                ),
                recentUploads.length > 0
                    ? h('div', { className: 'mj-payslip__recent' },
                        h('h4', { className: 'mj-payslip__recent-title' },
                            h('i', { className: 'fas fa-check-circle' }),
                            ' Fichiers envoyés (' + recentUploads.length + ')'
                        ),
                        h('ul', { className: 'mj-payslip__recent-list' },
                            recentUploads.map(function (doc, idx) {
                                return h('li', { key: idx, className: 'mj-payslip__recent-item' },
                                    h('i', { className: 'fas fa-file-pdf' }),
                                    ' ',
                                    doc.label || doc.originalName || doc.original_name || 'Document'
                                );
                            })
                        )
                    )
                    : null
            )
        );
    }

    /* ================================================================ *
     *  Main App                                                         *
     * ================================================================ */

    function PayslipUploadApp(_ref) {
        var config = _ref.config;

        var cfg  = config;
        var i18n = cfg.i18n;

        if (!cfg.hasAccess || !cfg.isCoordinator) {
            return h('div', { className: 'mj-payslip' },
                h('p', { className: 'mj-payslip__notice' }, i18n.noAccess)
            );
        }

        if (!cfg.employees || cfg.employees.length === 0) {
            return h('div', { className: 'mj-payslip' },
                h('p', { className: 'mj-payslip__notice' }, 'Aucun employé trouvé.')
            );
        }

        return h(BulkUploadPanel, { employees: cfg.employees, cfg: cfg, i18n: i18n });
    }

    /* ================================================================ *
     *  Mount                                                            *
     * ================================================================ */

    function init() {
        var containers = document.querySelectorAll('[data-mj-payslip-upload-widget]');
        containers.forEach(function (el) {
            if (el.dataset.mjInit) return;
            el.dataset.mjInit = 'true';
            var raw = el.getAttribute('data-config');
            var config = {};
            try { config = JSON.parse(raw || '{}'); } catch (e) { /* */ }
            render(h(PayslipUploadApp, { config: config }), el);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Elementor editor support
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-payslip-upload.default', init);
    }
})();
