/**
 * Registration Manager - AI Generation Modal
 * Composant modal dedie a la generation IA
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var Modals = global.MjRegMgrModals || {};

    if (!preact || !hooks || !Utils || !Modals.Modal) {
        console.warn('[MjRegMgr] Dependances manquantes pour modal-ia.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;

    var Modal = Modals.Modal;
    var getString = Utils.getString;

    function GenerateAiModal(props) {
        var isOpen = props.isOpen;
        var onClose = props.onClose;
        var onGenerate = props.onGenerate;
        var type = props.type || 'description';
        var contextData = props.contextData || {};
        var strings = props.strings || {};
        var config = props.config || {};

        var _note = useState('');
        var note = _note[0];
        var setNote = _note[1];

        var _generating = useState(false);
        var generating = _generating[0];
        var setGenerating = _generating[1];

        var _error = useState('');
        var error = _error[0];
        var setError = _error[1];

        var _promptOpen = useState(false);
        var promptOpen = _promptOpen[0];
        var setPromptOpen = _promptOpen[1];

        var _selectedFields = useState({});
        var selectedFields = _selectedFields[0];
        var setSelectedFields = _selectedFields[1];

        var fieldDefinitions = [
            { key: 'event_name', description: 'Nom de l\'evenement', lineLabel: 'Nom de l\'evenement' },
            { key: 'event_type', description: 'Type d\'evenement', lineLabel: 'Type' },
            { key: 'event_date_deadline', description: 'Date limite d\'inscription', lineLabel: 'Date limite d\'inscription' },
            { key: 'event_price', description: 'Tarif', lineLabel: 'Tarif' },
            { key: 'event_location', description: 'Lieu', lineLabel: 'Lieu' },
            { key: 'event_location_address', description: 'Adresse du lieu', lineLabel: 'Adresse du lieu' },
            { key: 'event_age_min', description: 'Age minimum', lineLabel: 'Age minimum' },
            { key: 'event_age_max', description: 'Age maximum', lineLabel: 'Age maximum' },
            { key: 'event_capacity', description: 'Capacite totale', lineLabel: 'Capacite totale' },
            { key: 'event_occurrences', description: 'Occurrences de l\'evenement', lineLabel: 'Occurrences', isList: true },
            { key: 'event_animateurs', description: 'Animateurs associes', lineLabel: 'Animateurs associes', isList: true },
        ];

        var normalizeFieldValues = function (key, value) {
            if (key === 'event_occurrences' || key === 'event_animateurs') {
                if (Array.isArray(value)) {
                    return value
                        .map(function (item) { return item === undefined || item === null ? '' : String(item).trim(); })
                        .filter(function (item) { return item !== ''; });
                }

                if (typeof value === 'string' && value.trim() !== '') {
                    return [value.trim()];
                }

                return [];
            }

            var raw = value === undefined || value === null ? '' : String(value).trim();
            if (raw === '') return [];
            if (key === 'event_price') {
                if (raw === '0' || raw === '0.00') {
                    return [getString(strings, 'eventPriceFree', 'Gratuit')];
                }
                return [/€/.test(raw) ? raw : (raw + ' €')];
            }
            return [raw];
        };

        var getFieldValues = function (key) {
            return normalizeFieldValues(key, contextData[key]);
        };

        useEffect(function () {
            if (isOpen) {
                setNote('');
                setError('');
                setGenerating(false);
                setPromptOpen(false);

                var initialSelection = {};
                fieldDefinitions.forEach(function (field) {
                    initialSelection[field.key] = getFieldValues(field.key).length > 0;
                });
                setSelectedFields(initialSelection);
            }
        }, [isOpen, type]);

        var toggleField = useCallback(function (fieldKey) {
            setSelectedFields(function (prev) {
                var next = Object.assign({}, prev);
                next[fieldKey] = !prev[fieldKey];
                return next;
            });
        }, []);

        var handleGenerate = useCallback(function () {
            if (generating || !onGenerate) return;

            setGenerating(true);
            setError('');

            var includedFields = fieldDefinitions
                .filter(function (field) { return !!selectedFields[field.key]; })
                .map(function (field) { return field.key; });

            Promise.resolve(onGenerate({
                hint: note.trim(),
                includedFields: includedFields,
            }))
                .then(function () {
                    setGenerating(false);
                    onClose();
                })
                .catch(function (err) {
                    setGenerating(false);
                    var msg = err && err.message ? err.message : getString(strings, 'aiGenerateError', 'Impossible de generer le texte.');
                    setError(msg);
                });
        }, [generating, onGenerate, note, selectedFields, strings, onClose]);

        var typeLabel = type === 'description'
            ? getString(strings, 'aiModalDescriptionTitle', 'Generer une description')
            : getString(strings, 'aiModalRegDocTitle', "Generer un document d'inscription");

        var footer = h(Fragment, null, [
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--secondary',
                onClick: onClose,
                disabled: generating,
            }, getString(strings, 'cancel', 'Annuler')),
            h('button', {
                type: 'button',
                class: 'mj-btn mj-btn--primary',
                onClick: handleGenerate,
                disabled: generating,
            }, generating
                ? getString(strings, 'aiGenerating', 'Generation en cours...')
                : getString(strings, 'aiGenerate', 'Generer')),
        ]);

        var contextItems = fieldDefinitions.map(function (field) {
            var values = getFieldValues(field.key);
            return {
                key: field.key,
                description: field.description,
                lineLabel: field.lineLabel,
                values: values,
                value: values.length > 0 ? values[0] : '',
                isList: !!field.isList,
                checked: !!selectedFields[field.key],
            };
        });

        var siteName = config.aiSiteName || '';

        var contextLines = [];
        contextItems.forEach(function (item) {
            if (!item.checked || !Array.isArray(item.values) || item.values.length === 0) return;

            if (item.isList || item.values.length > 1) {
                contextLines.push(item.lineLabel + ' :');
                item.values.forEach(function (entry) {
                    contextLines.push('- ' + entry);
                });
                return;
            }

            contextLines.push(item.lineLabel + ' : ' + item.values[0]);
        });
        if (note.trim()) {
            contextLines.push('Informations complementaires fournies par l\'organisateur : ' + note.trim());
        }
        var contextBlock = contextLines.join('\n');

        var systemPromptBase;
        var userPromptPreview;
        if (type === 'description') {
            systemPromptBase = config.aiDescriptionPrompt || '';
            if (!systemPromptBase && siteName) {
                systemPromptBase = 'Tu es un assistant redacteur pour une association jeunesse (' + siteName + '). Tu rediges des descriptions d\'evenements en francais, de maniere claire, engageante et adaptee a un public familial. Reponds uniquement avec le texte de la description, sans titre ni introduction.';
            }
            systemPromptBase += '\n\nFormat de sortie obligatoire: retourne uniquement du HTML valide (pas de Markdown, pas de triple backticks). Utilise des balises simples adaptees au rendu web (ex: <p>, <strong>, <ul>, <li>).';
            userPromptPreview = 'Redige une description attrayante en HTML pour l\'evenement suivant:\n\n' + contextBlock;
        } else {
            systemPromptBase = config.aiRegDocPrompt || '';
            if (!systemPromptBase && siteName) {
                systemPromptBase = 'Tu es un assistant pour une association jeunesse (' + siteName + '). Tu rediges des documents d\'inscription en francais. Le document doit contenir les informations essentielles sur l\'evenement et les instructions pour les participants. Utilise les variables entre crochets (ex : [member_name], [event_name]) pour personnaliser le document. Reponds uniquement avec le contenu du document.';
            }
            systemPromptBase += '\n\nFormat de sortie obligatoire: retourne uniquement du HTML valide (pas de Markdown, pas de triple backticks). Structure le document avec des balises HTML (<h2>, <p>, <ul>, <li>, <strong>) sans code fence.';
            userPromptPreview = 'Redige un document d\'inscription en HTML pour l\'evenement suivant:\n\n' + contextBlock;
        }

        return h(Modal, {
            isOpen: isOpen,
            onClose: onClose,
            title: typeLabel,
            size: 'medium',
            footer: footer,
        }, [
            h('div', { class: 'mj-regmgr-generate-ai' }, [
                contextItems.length > 0 && h('div', { class: 'mj-regmgr-generate-ai__context' }, [
                    h('h3', { class: 'mj-regmgr-generate-ai__context-title' },
                        getString(strings, 'aiContextLabel', 'Donnees injectees')
                    ),
                    h('div', { class: 'mj-regmgr-generate-ai__context-grid' },
                        contextItems.map(function (item) {
                            return h('label', { key: item.key, class: 'mj-regmgr-generate-ai__context-item' }, [
                                h('span', { class: 'mj-regmgr-generate-ai__context-header' }, [
                                    h('input', {
                                        type: 'checkbox',
                                        checked: item.checked,
                                        onChange: function () { toggleField(item.key); },
                                        disabled: generating,
                                    }),
                                    h('span', { class: 'mj-regmgr-generate-ai__context-meta' }, [
                                        h('span', { class: 'mj-regmgr-generate-ai__context-label' }, item.description),
                                        item.isList && h('span', { class: 'mj-regmgr-generate-ai__context-count' },
                                            item.values.length + ' element' + (item.values.length > 1 ? 's' : '')
                                        ),
                                    ]),
                                ]),
                                item.values.length > 0
                                    ? (item.isList
                                        ? h('ul', { class: 'mj-regmgr-generate-ai__context-list' },
                                            item.values.map(function (value, idx) {
                                                return h('li', { key: item.key + '-' + idx, class: 'mj-regmgr-generate-ai__context-list-item' }, value);
                                            })
                                        )
                                        : h('span', { class: 'mj-regmgr-generate-ai__context-value' }, item.value)
                                    )
                                    : h('span', { class: 'mj-regmgr-generate-ai__context-empty' }, 'Aucune donnee'),
                            ]);
                        })
                    ),
                ]),

                h('div', { class: 'mj-regmgr-generate-ai__note' }, [
                    h('label', { for: 'ai-generate-note' },
                        getString(strings, 'aiNotePlaceholder', 'Instruction complementaire (optionnelle)')
                    ),
                    h('textarea', {
                        id: 'ai-generate-note',
                        class: 'mj-regmgr-textarea',
                        placeholder: getString(strings, 'aiNoteHint', 'Ex: Mettez l\'accent sur les activites en plein air...'),
                        value: note,
                        onInput: function (e) { setNote(e.target.value); },
                        rows: 3,
                        disabled: generating,
                    }),
                ]),

                h('div', { class: 'mj-regmgr-generate-ai__prompt-preview' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-generate-ai__prompt-toggle',
                        onClick: function () { setPromptOpen(function (v) { return !v; }); },
                    }, [
                        h('svg', {
                            width: 14,
                            height: 14,
                            viewBox: '0 0 24 24',
                            fill: 'none',
                            stroke: 'currentColor',
                            'stroke-width': 2,
                            'stroke-linecap': 'round',
                            'stroke-linejoin': 'round',
                            style: 'flex-shrink:0; transition: transform 200ms; transform: rotate(' + (promptOpen ? '90deg' : '0deg') + ')',
                        }, [
                            h('polyline', { points: '9 18 15 12 9 6' }),
                        ]),
                        getString(strings, 'aiPromptPreviewLabel', 'Apercu du prompt envoye'),
                    ]),

                    promptOpen && h('div', { class: 'mj-regmgr-generate-ai__prompt-body' }, [
                        h('div', { class: 'mj-regmgr-generate-ai__prompt-section' }, [
                            h('div', { class: 'mj-regmgr-generate-ai__prompt-section-label' },
                                getString(strings, 'aiSystemPromptLabel', 'Instructions systeme')
                            ),
                            h('pre', { class: 'mj-regmgr-generate-ai__prompt-pre' }, systemPromptBase),
                        ]),
                        h('div', { class: 'mj-regmgr-generate-ai__prompt-section' }, [
                            h('div', { class: 'mj-regmgr-generate-ai__prompt-section-label' },
                                getString(strings, 'aiUserPromptLabel', 'Requete utilisateur')
                            ),
                            h('pre', { class: 'mj-regmgr-generate-ai__prompt-pre' }, userPromptPreview),
                        ]),
                    ]),
                ]),

                error && h('div', { class: 'mj-regmgr-alert mj-regmgr-alert--error' }, error),
            ]),
        ]);
    }

    global.MjRegMgrGenerateAiModal = GenerateAiModal;
    if (global.MjRegMgrModals && typeof global.MjRegMgrModals === 'object') {
        global.MjRegMgrModals.GenerateAiModal = GenerateAiModal;
    }

})(window);
