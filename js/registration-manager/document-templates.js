/**
 * Registration Manager - Document template library
 *
 * Shared helpers + Preact components for the "Contrat" tab's document-template
 * library: header/footer/parental-authorization/attendance-attestation/signature
 * sections are now named, reusable templates (create/duplicate/edit/set-default)
 * instead of a single free-text block per event. Only "Description de l'activité"
 * stays per-event (AI-assisted), and is passed in separately by the caller.
 */
(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var Modals = global.MjRegMgrModals || {};

    if (!preact || !hooks || !Utils || !Modals.Modal) {
        console.warn('[MjRegMgr] Dependances manquantes pour document-templates.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useRef = hooks.useRef;
    var Modal = Modals.Modal;
    var getString = Utils.getString;

    // Keep in sync with MjDocumentTemplates::known_sections() (PHP).
    var SECTIONS = [
        { key: 'header', label: 'En-tête', variant: null },
        { key: 'parental_authorization', label: 'Autorisation parentale', variant: 'guardian' },
        { key: 'attendance_attestation', label: 'Attestation de présence (membre autonome)', variant: 'autonomous' },
        { key: 'signature_guardian', label: 'Espace signature parentale', variant: 'guardian' },
        { key: 'signature_autonomous', label: 'Espace signature membre autonome', variant: 'autonomous' },
        { key: 'footer', label: 'Pied de page', variant: null },
        { key: 'member_header', label: 'En-tête (fiche membre)', variant: null },
        { key: 'member_content', label: "Contenu (fiche d'inscription)", variant: null },
        { key: 'member_footer', label: 'Pied de page (fiche membre)', variant: null },
    ];

    function sectionLabel(sectionKey) {
        var found = SECTIONS.filter(function (s) { return s.key === sectionKey; })[0];
        return found ? found.label : sectionKey;
    }

    function interpolate(text, variables) {
        if (!text) return '';
        var result = String(text);
        if (!variables) return result;
        Object.keys(variables).forEach(function (key) {
            var regex = new RegExp('\\[' + key + '\\]', 'gi');
            result = result.replace(regex, variables[key] || '');
        });
        return result;
    }

    /**
     * @param {Object} templatesBySection - { section: [ {id,name,content,is_default}, ... ] }
     * @param {Object} selectionMap - { section: templateId }
     * @param {string} section
     * @returns {string} raw (uninterpolated) HTML content
     */
    function resolveTemplateContent(templatesBySection, selectionMap, section) {
        var list = (templatesBySection && templatesBySection[section]) || [];
        var selectedId = selectionMap && selectionMap[section] ? parseInt(selectionMap[section], 10) : 0;

        var found = null;
        if (selectedId) {
            found = list.filter(function (t) { return parseInt(t.id, 10) === selectedId; })[0] || null;
        }
        if (!found) {
            found = list.filter(function (t) { return !!t.is_default; })[0] || null;
        }

        return found ? (found.content || '') : '';
    }

    /**
     * Ordered header/auth/description/signature/footer blocks for one document,
     * picking the autonome/non-autonome variant for auth + signature.
     *
     * @param {boolean} isAutonomous
     * @param {string} descriptionHtml - the event's per-event "Description de l'activité"
     * @returns {Array<{cssClass:string, section:string, html:string}>}
     */
    function resolveDocumentBlocks(templatesBySection, selectionMap, isAutonomous, descriptionHtml) {
        var authSection = isAutonomous ? 'attendance_attestation' : 'parental_authorization';
        var signatureSection = isAutonomous ? 'signature_autonomous' : 'signature_guardian';

        return [
            { cssClass: 'mj-regdoc-header', section: 'header', html: resolveTemplateContent(templatesBySection, selectionMap, 'header') },
            { cssClass: 'mj-regdoc-auth', section: authSection, html: resolveTemplateContent(templatesBySection, selectionMap, authSection) },
            { cssClass: 'mj-regdoc-content', section: null, html: descriptionHtml || '' },
            { cssClass: 'mj-regdoc-signature', section: signatureSection, html: resolveTemplateContent(templatesBySection, selectionMap, signatureSection) },
            { cssClass: 'mj-regdoc-footer', section: 'footer', html: resolveTemplateContent(templatesBySection, selectionMap, 'footer') },
        ];
    }

    /**
     * Ordered header/content/footer blocks for the member "fiche
     * d'inscription" contract — always the section defaults, since (unlike
     * events) there's no per-instance template selection for member contracts.
     *
     * @returns {Array<{cssClass:string, section:string, html:string}>}
     */
    function resolveMemberContractBlocks(templatesBySection) {
        return [
            { cssClass: 'mj-regdoc-header', section: 'member_header', html: resolveTemplateContent(templatesBySection, {}, 'member_header') },
            { cssClass: 'mj-regdoc-content', section: 'member_content', html: resolveTemplateContent(templatesBySection, {}, 'member_content') },
            { cssClass: 'mj-regdoc-footer', section: 'member_footer', html: resolveTemplateContent(templatesBySection, {}, 'member_footer') },
        ];
    }

    /**
     * Renders one member dynamic-field's stored value as an HTML fragment,
     * insertable via [dynfield_<id>] in the member contract — mirrors
     * buildDynFieldContractHtml() in
     * includes/core/ajax/admin/registration-manager.php. Choice-type fields
     * (radio/dropdown/checklist) render as a "QCM" list of every option with
     * the member's answer(s) pre-checked (☑) instead of a plain value.
     *
     * @param {Object} df - one entry of member.dynamicFields
     * @returns {string} HTML fragment
     */
    function buildDynFieldContractHtml(df) {
        var type = df.type;
        var title = Utils.escapeHtml(df.title || '');
        var rawValue = df.value || '';

        if (type === 'radio' || type === 'dropdown' || type === 'checklist') {
            var options = df.options || [];
            var selected = [];
            var otherText = '';

            if (type === 'checklist') {
                try {
                    var arr = JSON.parse(rawValue || '[]');
                    if (Array.isArray(arr)) {
                        arr.forEach(function (entry) {
                            if (typeof entry === 'string' && entry.indexOf('__other:') === 0) {
                                selected.push('__other');
                                otherText = entry.substring(8);
                            } else {
                                selected.push(entry);
                            }
                        });
                    }
                } catch (e) { /* keep selected empty on parse failure */ }
            } else if (typeof rawValue === 'string' && rawValue.indexOf('__other:') === 0) {
                selected.push('__other');
                otherText = rawValue.substring(8);
            } else if (rawValue) {
                selected.push(rawValue);
            }

            var rows = options.map(function (opt) {
                var checked = selected.indexOf(opt) !== -1;
                return '<span style="display:inline-block;margin:0 1.2em 0.3em 0;white-space:nowrap;' + (checked ? 'font-weight:700;' : '') + '">'
                    + (checked ? '&#9745;' : '&#9744;') + ' ' + Utils.escapeHtml(String(opt)) + '</span>';
            });

            if (df.allowOther) {
                var otherChecked = selected.indexOf('__other') !== -1;
                var otherLabel = df.otherLabel || 'Autre';
                if (otherChecked && otherText) {
                    otherLabel += ' : ' + otherText;
                } else {
                    // No answer to show (unchecked, or checked but blanked for
                    // "Aperçu document vierge") — leave a dotted line to fill in by hand.
                    otherLabel += ' : ' + new Array(21).join('.');
                }
                rows.push('<span style="display:inline-block;margin:0 1.2em 0.3em 0;white-space:nowrap;' + (otherChecked ? 'font-weight:700;' : '') + '">'
                    + (otherChecked ? '&#9745;' : '&#9744;') + ' ' + Utils.escapeHtml(otherLabel) + '</span>');
            }

            return '<div style="margin:0.35em 0;"><h3 style="margin:0 0 0.2em 0;">' + title + '</h3>'
                + '<div>' + rows.join(' ') + '</div></div>';
        }

        if (type === 'checkbox') {
            var isChecked = rawValue === '1';
            return '<span style="display:inline-block;' + (isChecked ? 'font-weight:700;' : '') + '">'
                + (isChecked ? '&#9745;' : '&#9744;') + ' ' + title + '</span>';
        }

        // text / textarea / fallback: plain value.
        return Utils.escapeHtml(rawValue);
    }

    /**
     * [dynfield_<id>] variables for the member contract preview, mirroring
     * buildMemberContractDynFieldVariables() (PHP) — one entry per custom
     * field defined for members ('title' fields are skipped, they carry no
     * value).
     *
     * @param {Array} dynamicFields - member.dynamicFields
     * @param {boolean} [blank] - "Aperçu document vierge": ignore the
     *   member's stored answers and render every field unanswered (no
     *   option checked, empty text) — mirrors buildMemberContractDynFieldVariables()
     *   (PHP) called with $blank = true.
     * @returns {Object} { 'dynfield_<id>': htmlFragment }
     */
    function buildMemberContractDynFieldVariables(dynamicFields, blank) {
        var variables = {};
        (dynamicFields || []).forEach(function (df) {
            if (df.type === 'title') return;
            variables['dynfield_' + df.id] = buildDynFieldContractHtml(blank ? Object.assign({}, df, { value: '' }) : df);
        });
        return variables;
    }

    /**
     * Concatenate resolved blocks into `<div class="...">...</div>` fragments,
     * interpolating variables and skipping empty blocks — mirrors the PHP
     * renderRegistrationDocumentBlocksHtml()/normalizeRegistrationDocumentBlocks().
     */
    function buildBlocksHtml(blocks, variables) {
        return blocks
            .map(function (block) {
                var html = interpolate(block.html, variables);
                if (!html) return '';
                return '<div class="' + block.cssClass + '">' + html + '</div>';
            })
            .filter(function (fragment) { return fragment !== ''; })
            .join('');
    }

    var DOC_PREVIEW_STYLE = ''
        + '.mj-regdoc-header{font-size:11px;color:#333;}'
        + '.mj-regdoc-auth{font-size:12px;margin:10px 0;}'
        + '.mj-regdoc-content{font-size:11px;min-height:100px;}'
        + '.mj-regdoc-signature{font-size:12px;margin-top:28px;}'
        + '.mj-regdoc-footer{font-size:10pt;color:#666;}';

    /**
     * One section's template picker: dropdown + New/Duplicate/Edit/Set-default.
     */
    function TemplateSectionPicker(props) {
        var section = props.section;
        var templates = props.templates || [];
        var selectedId = props.selectedId || 0;
        var onSelect = props.onSelect;
        var onCreate = props.onCreate;
        var onDuplicate = props.onDuplicate;
        var onEdit = props.onEdit;
        var onSetDefault = props.onSetDefault;
        var onDelete = props.onDelete;
        var busy = !!props.busy;

        var defaultTemplate = templates.filter(function (t) { return !!t.is_default; })[0] || null;
        var effectiveId = selectedId || (defaultTemplate ? defaultTemplate.id : 0);
        var current = templates.filter(function (t) { return parseInt(t.id, 10) === parseInt(effectiveId, 10); })[0] || null;

        return h('div', { class: 'mj-regmgr-doctpl' }, [
            h('div', { class: 'mj-regmgr-doctpl__label' }, sectionLabel(section)),
            h('div', { class: 'mj-regmgr-doctpl__row' }, [
                h('select', {
                    class: 'mj-regmgr-doctpl__select',
                    value: String(effectiveId || ''),
                    disabled: busy,
                    onChange: function (e) {
                        var id = parseInt(e.target.value, 10) || 0;
                        onSelect && onSelect(id || null);
                    },
                }, templates.map(function (tpl) {
                    var label = tpl.name + (tpl.is_default ? ' (défaut)' : '');
                    return h('option', { value: String(tpl.id), key: tpl.id }, label);
                })),
                h('button', { type: 'button', class: 'mj-btn mj-btn--secondary mj-regmgr-doctpl__btn', disabled: busy, onClick: function () { onCreate && onCreate(section); } }, '+ Nouveau'),
                current && h('button', { type: 'button', class: 'mj-btn mj-btn--secondary mj-regmgr-doctpl__btn', disabled: busy, onClick: function () { onDuplicate && onDuplicate(current); } }, 'Dupliquer'),
                current && h('button', { type: 'button', class: 'mj-btn mj-btn--secondary mj-regmgr-doctpl__btn', disabled: busy, onClick: function () { onEdit && onEdit(current); } }, 'Modifier'),
                current && !current.is_default && h('button', { type: 'button', class: 'mj-btn mj-btn--secondary mj-regmgr-doctpl__btn', disabled: busy, onClick: function () { onSetDefault && onSetDefault(current); } }, 'Définir par défaut'),
                current && !current.is_default && h('button', { type: 'button', class: 'mj-btn mj-btn--danger-outline mj-regmgr-doctpl__btn', disabled: busy, onClick: function () { onDelete && onDelete(current); } }, 'Supprimer'),
            ]),
            current && h('div', {
                class: 'mj-regmgr-doctpl__preview',
                dangerouslySetInnerHTML: { __html: current.content || '<em>(modèle vide)</em>' },
            }),
        ]);
    }

    /**
     * Simple create/rename/edit modal for one template's name + raw HTML content.
     *
     * @param {Array} [props.variableGroups] - optional, same shape as
     * REGDOC_VARIABLE_GROUPS (js/registration-manager/app.js): shows an
     * "Insérer une variable" picker above the textarea that inserts
     * [token] at the cursor position.
     */
    function TemplateEditModal(props) {
        var isOpen = props.isOpen;
        var title = props.title || 'Modèle de document';
        var initialName = props.initialName || '';
        var initialContent = props.initialContent || '';
        var onSave = props.onSave;
        var onClose = props.onClose;
        var saving = !!props.saving;
        var error = props.error || '';
        var variableGroups = Array.isArray(props.variableGroups) ? props.variableGroups : null;

        var _name = useState(initialName);
        var name = _name[0];
        var setName = _name[1];
        var _content = useState(initialContent);
        var content = _content[0];
        var setContent = _content[1];
        var _variablesOpen = useState(false);
        var variablesOpen = _variablesOpen[0];
        var setVariablesOpen = _variablesOpen[1];
        var _referenceOpen = useState(false);
        var referenceOpen = _referenceOpen[0];
        var setReferenceOpen = _referenceOpen[1];
        var textareaRef = useRef(null);

        useEffect(function () {
            if (isOpen) {
                setName(initialName);
                setContent(initialContent);
                setVariablesOpen(false);
                setReferenceOpen(false);
            }
        }, [isOpen, initialName, initialContent]);

        function insertVariable(token) {
            var el = textareaRef.current;
            if (el && typeof el.selectionStart === 'number') {
                var start = el.selectionStart;
                var end = el.selectionEnd;
                var next = content.slice(0, start) + token + content.slice(end);
                setContent(next);
                var cursorPos = start + token.length;
                setTimeout(function () {
                    el.focus();
                    if (typeof el.setSelectionRange === 'function') {
                        el.setSelectionRange(cursorPos, cursorPos);
                    }
                }, 0);
            } else {
                setContent(content + token);
            }
            setVariablesOpen(false);
        }

        var variablesButton = variableGroups && variableGroups.length > 0 && h('div', { class: 'mj-regmgr-editor-variables' }, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--secondary mj-regmgr-editor-variables__toggle',
                onClick: function (event) {
                    event.preventDefault();
                    setVariablesOpen(function (open) { return !open; });
                },
            }, '{ } Insérer une variable'),
            variablesOpen && h('div', { class: 'mj-regmgr-editor-variables__menu' }, variableGroups.map(function (group) {
                return h('div', { class: 'mj-regmgr-editor-variables__group', key: group.label }, [
                    h('div', { class: 'mj-regmgr-editor-variables__group-label' }, group.label),
                    group.items.map(function (item) {
                        return h('button', {
                            type: 'button',
                            class: 'mj-regmgr-editor-variables__item',
                            key: item.token,
                            onClick: function () { insertVariable(item.token); },
                        }, [h('code', null, item.token), h('span', null, ' — ' + item.description)]);
                    }),
                ]);
            })),
        ]);

        // Read-only reference panel: the full list of every available
        // variable (grouped, with its description), for consultation while
        // typing — unlike variablesButton above, it doesn't insert anything.
        var referenceButton = variableGroups && variableGroups.length > 0 && h('div', { style: 'margin-bottom:8px;' }, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--secondary',
                onClick: function (event) {
                    event.preventDefault();
                    setReferenceOpen(function (open) { return !open; });
                },
            }, (referenceOpen ? '▾ ' : '▸ ') + 'Liste de toutes les variables disponibles'),
            referenceOpen && h('div', {
                style: 'margin-top:6px;padding:10px 12px;border:1px solid #e2e8f0;border-radius:6px;background:#f8fafc;font-size:12px;max-height:260px;overflow-y:auto;',
            }, variableGroups.map(function (group) {
                return h('div', { key: group.label, style: 'margin-bottom:10px;' }, [
                    h('div', { style: 'font-weight:700;text-transform:uppercase;font-size:11px;letter-spacing:0.5px;color:#64748b;margin-bottom:4px;' }, group.label),
                    group.items.map(function (item) {
                        return h('div', { key: item.token, style: 'padding:2px 0;' }, [
                            h('code', { style: 'background:#e8f4fc;color:#0369a1;padding:1px 5px;border-radius:3px;font-family:\'SFMono-Regular\',Consolas,\'Liberation Mono\',Menlo,Courier,monospace;font-size:11px;' }, item.token),
                            h('span', null, ' — ' + item.description),
                        ]);
                    }),
                ]);
            })),
        ]);

        var footer = h(Fragment, null, [
            h('button', { type: 'button', class: 'mj-btn mj-btn--secondary', onClick: onClose, disabled: saving }, 'Annuler'),
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--primary',
                disabled: saving || !name.trim(),
                onClick: function () { onSave && onSave({ name: name.trim(), content: content }); },
            }, saving ? 'Enregistrement…' : 'Enregistrer'),
        ]);

        return h(Modal, { isOpen: isOpen, onClose: onClose, title: title, size: 'medium', footer: footer }, [
            h('div', { class: 'mj-regmgr-doctpl-edit' }, [
                h('label', { class: 'mj-regmgr-doctpl-edit__label' }, [
                    'Nom du modèle',
                    h('input', {
                        type: 'text', class: 'mj-regmgr-doctpl-edit__input', value: name,
                        onInput: function (e) { setName(e.target.value); },
                    }),
                ]),
                h('label', { class: 'mj-regmgr-doctpl-edit__label' }, [
                    'Contenu (HTML)',
                    h('div', { style: 'display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;' }, [variablesButton, referenceButton]),
                    h('textarea', {
                        ref: textareaRef,
                        class: 'mj-regmgr-doctpl-edit__textarea', rows: 10, value: content,
                        onInput: function (e) { setContent(e.target.value); },
                    }),
                ]),
                error && h('div', { class: 'mj-regmgr-alert mj-regmgr-alert--error' }, error),
            ]),
        ]);
    }

    global.MjRegMgrDocumentTemplates = {
        SECTIONS: SECTIONS,
        PREVIEW_STYLE: DOC_PREVIEW_STYLE,
        sectionLabel: sectionLabel,
        interpolate: interpolate,
        resolveTemplateContent: resolveTemplateContent,
        resolveDocumentBlocks: resolveDocumentBlocks,
        resolveMemberContractBlocks: resolveMemberContractBlocks,
        buildBlocksHtml: buildBlocksHtml,
        buildDynFieldContractHtml: buildDynFieldContractHtml,
        buildMemberContractDynFieldVariables: buildMemberContractDynFieldVariables,
        TemplateSectionPicker: TemplateSectionPicker,
        TemplateEditModal: TemplateEditModal,
    };

})(window);
