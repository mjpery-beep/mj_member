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

        var _name = useState(initialName);
        var name = _name[0];
        var setName = _name[1];
        var _content = useState(initialContent);
        var content = _content[0];
        var setContent = _content[1];

        useEffect(function () {
            if (isOpen) {
                setName(initialName);
                setContent(initialContent);
            }
        }, [isOpen, initialName, initialContent]);

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
                    h('textarea', {
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
        buildBlocksHtml: buildBlocksHtml,
        TemplateSectionPicker: TemplateSectionPicker,
        TemplateEditModal: TemplateEditModal,
    };

})(window);
