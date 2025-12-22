/**
 * Registration Manager - Event Editor Components
 * Front-end form to edit an event from the registration manager widget
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Missing dependencies for event-editor.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useMemo = hooks.useMemo;
    var useCallback = hooks.useCallback;
    var useRef = hooks.useRef;

    var classNames = Utils.classNames;
    var rawGetString = Utils.getString;

    var DEFAULT_STRINGS = {
        accentColor: 'Couleur pastel',
        accentColorHint: "Selectionnez une couleur d accent pour cet evenement. Laissez vide pour utiliser la couleur du type.",
        ageMax: 'Age maximum',
        ageMin: 'Age minimum',
        allowGuardian: 'Autoriser les tuteurs',
        allowGuardianToggle: "Les tuteurs peuvent s inscrire eux memes a cet evenement",
        allowGuardianHint: "Par defaut, seuls les jeunes peuvent s inscrire.",
        animateurs: 'Animateurs referents',
        animateursHint: "Selectionnez un ou plusieurs animateurs referents (optionnel); chacun recevra une notification lors des nouvelles inscriptions.",
        article: 'Article',
        articleCategory: 'Categorie',
        articlesSection: 'Article lie',
        articlesSectionHint: 'Selectionnez un article publie pour le relier a cet evenement.',
        capacityAlertHint: 'Un email est envoye quand les places restantes sont inferieures ou egales au seuil.',
        capacityHint: "Laisser 0 pour ne pas limiter les inscriptions ni activer d alerte.",
        capacityNotify: "Seuil d alerte",
        capacityTotal: 'Places max',
        capacityWaitlist: "Liste d attente",
        contentSection: 'Description',
        contentSectionHint: "Presentez l evenement en detail pour les membres et le public.",
        coverId: 'ID de couverture',
        coverLabel: 'Visuel',
        coverSelect: 'Selectionner un fichier',
        coverReplace: 'Remplacer le fichier',
        coverEmpty: 'Aucun visuel selectionne.',
        coverPreviewAlt: 'Apercu du visuel de couverture',
        coverModalTitle: 'Choisir un visuel de couverture',
        description: 'Description detaillee',
        editorEmpty: "Selectionnez un evenement pour commencer la modification.",
        editorTitleFallback: 'Modifier l evenement',
        eventPrice: 'Tarif',
        eventStatus: 'Statut',
        eventTitle: 'Titre',
        eventType: 'Type',
        fixedDate: 'Jour',
        fixedEndTime: 'Fin',
        fixedHint: 'Utilisez cette option pour un evenement sur une seule journee avec un creneau horaire.',
        fixedStartTime: 'Debut',
        freeParticipation: 'Participation libre',
        freeParticipationToggle: "Il n est pas necessaire de s inscrire a cet evenement",
        freeParticipationHint: "Aucune reservation n est recue. L evenement reste visible dans l espace membre.",
        generalSection: 'Informations principales',
        generalSectionHint: "Renseignez les informations essentielles de l evenement.",
        loading: 'Chargement...',
        location: 'Lieu',
        locationHint: 'Administrez les lieux depuis la page des lieux du tableau de bord.',
        locationSection: 'Lieu et equipe',
        locationSectionHint: "Choisissez le lieu d accueil et les referents associes.",
        noArticle: 'Aucun article',
        noCategory: 'Choisir une categorie',
        noLocation: 'Aucun lieu defini',
        occurrenceChoiceAll: 'Inscrire automatiquement sur toutes les occurrences',
        occurrenceChoiceMember: 'Les membres choisissent leurs occurrences',
        occurrenceModeHint: "Ce parametre s applique aux evenements recurrents, en serie ou aux stages. En mode automatique, chaque inscription couvre toutes les occurrences disponibles.",
        occurrenceSelectionMode: 'Gestion des occurrences',
        rangeEnd: 'Fin',
        rangeHint: 'Choisissez cette option pour un evenement etale sur plusieurs jours.',
        rangeStart: 'Debut',
        recurringEndTime: 'Fin',
        recurringFrequency: 'Frequence',
        recurringInterval: 'Intervalle',
        recurringMonthly: 'Mensuel',
        recurringOrdinal: 'Ordre',
        recurringShowDateRange: 'Masquer la periode (date de debut et date de fin) sur la page evenement',
        recurringStartDate: 'Jour',
        recurringStartTime: 'Debut',
        recurringUntil: "Jusqu au (optionnel)",
        recurringUntilHint: 'Laisser vide pour poursuivre la recurrence sans date de fin.',
        recurringWeekday: 'Jour de semaine',
        recurringWeekly: 'Hebdomadaire',
        recurringWeekdaysHint: "Cochez les jours souhaites et definissez les plages horaires pour chaque jour (optionnel).",
        registrationDeadline: "Date limite d inscription",
        registrationDeadlineHint: "Laisser vide pour utiliser la date par defaut (14 jours avant le debut).",
        registrationSection: 'Inscriptions et acces',
        registrationSectionHint: "Configurez l ouverture des inscriptions, les capacites et les publics autorises.",
        reloadEditor: 'Reinitialiser le formulaire',
        remove: 'Supprimer',
        requiresValidation: 'Validation des inscriptions',
        requiresValidationToggle: 'Confirmer manuellement chaque inscription recue pour cet evenement',
        requiresValidationHint: 'Decochez pour valider automatiquement les reservations envoyees par les membres.',
        save: 'Enregistrer',
        saving: 'Enregistrement...',
        scheduleMode: 'Mode de planification',
        scheduleModeFixed: 'Date fixe (debut et fin le meme jour)',
        scheduleModeHint: 'Choisissez la facon de planifier l evenement; les sections ci dessous s adaptent au mode selectionne.',
        scheduleModeRange: 'Plage de dates (plusieurs jours consecutifs)',
        scheduleModeRecurring: 'Recurrence (hebdomadaire ou mensuelle)',
        scheduleModeSeries: 'Serie de dates personnalisees',
        scheduleSection: 'Planification',
        scheduleSectionHint: "Definissez les dates et la frequence de l evenement.",
        seriesAdd: 'Ajouter une date',
        seriesDescription: 'Ajoutez chaque date de facon individuelle. Utilisez ce mode pour un planning non recurrent.',
        seriesEmpty: 'Aucune date ajoutee.',
        volunteers: 'Benevoles referents',
        volunteersHint: "Choisissez des benevoles referents (optionnel); ils recevront les notifications d inscription pour cet evenement.",
        errorHintTitleRequired: 'Ajoutez un titre visible dans les listings et sur la fiche evenement.',
        errorHintStatusInvalid: 'Choisissez un statut propose dans le tableau de bord.',
        errorHintTypeInvalid: 'Choisissez un type d evenement configure dans le tableau de bord.',
        errorHintAgeRange: 'Verifiez les bornes d age, la valeur mini doit rester inferieure ou egale a la valeur maxi.',
        errorHintCapacityThreshold: 'Ajustez le seuil d alerte pour qu il reste inferieur au nombre total de places.',
        errorHintFixedDate: 'Renseignez la date et les horaires dans le mode Date unique.',
        errorHintFixedDateInvalid: 'Utilisez le selecteur de date pour fournir une valeur valide.',
        errorHintFixedTimeOrder: 'Renseignez une heure de fin superieure a l heure de debut.',
        errorHintRangeDates: 'Renseignez les deux bornes dans le mode Plage de dates.',
        errorHintRangeOrder: 'La date de fin doit suivre la date de debut.',
        errorHintRecurringStart: 'Indiquez la premiere occurrence avec sa date et son heure.',
        errorHintRecurringDate: 'Utilisez une date valide pour la premiere occurrence.',
        errorHintRecurringTime: 'Utilisez un format d heure valide pour la recurrence.',
        errorHintRecurringWeekdays: 'Cochez un ou plusieurs jours concernes pour la recurrence.',
        errorHintSeries: 'Ajoutez chaque occurrence dans la liste pour la serie personnalisee.',
        errorHintScheduleStart: 'Renseignez la date de debut du planning.',
        errorHintScheduleEnd: 'Renseignez la date de fin du planning.',
        errorHintAnimateurInvalid: 'Verifiez la selection des animateurs referents disponibles.',
        errorHintVolunteerInvalid: 'Verifiez la selection des benevoles referents disponibles.',
    };

    function getString(strings, key, fallback) {
        var defaultValue = Object.prototype.hasOwnProperty.call(DEFAULT_STRINGS, key)
            ? DEFAULT_STRINGS[key]
            : fallback;
        return rawGetString(strings, key, defaultValue);
    }

    function normalizeErrorKey(message) {
        if (!message) {
            return '';
        }
        var text = String(message);
        if (typeof text.normalize === 'function') {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        text = text.toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
        return text;
    }

    var ERROR_HINT_KEYS = {
        'le titre est obligatoire': 'errorHintTitleRequired',
        'le statut selectionne est invalide': 'errorHintStatusInvalid',
        'le type selectionne est invalide': 'errorHintTypeInvalid',
        'l age minimum doit etre inferieur ou egal a l age maximum': 'errorHintAgeRange',
        'le seuil d alerte doit etre inferieur au nombre total de places': 'errorHintCapacityThreshold',
        'la date et l heure de debut sont obligatoires pour un horaire fixe': 'errorHintFixedDate',
        'la date de debut est invalide': 'errorHintFixedDateInvalid',
        'l heure de fin doit etre posterieure a l heure de debut': 'errorHintFixedTimeOrder',
        'les dates de debut et de fin de la plage sont obligatoires': 'errorHintRangeDates',
        'la date de fin doit etre posterieure a la date de debut': 'errorHintRangeOrder',
        'la date et l heure de debut de la recurrence sont obligatoires': 'errorHintRecurringStart',
        'la date de debut de la recurrence est invalide': 'errorHintRecurringDate',
        'l heure de fin de la recurrence est invalide': 'errorHintRecurringTime',
        'selectionnez au moins un jour pour la recurrence hebdomadaire': 'errorHintRecurringWeekdays',
        'ajoutez au moins une date valide pour la serie': 'errorHintSeries',
        'la date de debut est obligatoire': 'errorHintScheduleStart',
        'la date de fin est obligatoire': 'errorHintScheduleEnd',
        'un animateur selectionne est invalide': 'errorHintAnimateurInvalid',
        'un benevole selectionne est invalide': 'errorHintVolunteerInvalid',
    };

    function getErrorHint(strings, message) {
        var key = normalizeErrorKey(message);
        var hintKey = ERROR_HINT_KEYS[key];
        if (!hintKey) {
            return '';
        }
        var fallback = Object.prototype.hasOwnProperty.call(DEFAULT_STRINGS, hintKey)
            ? DEFAULT_STRINGS[hintKey]
            : '';
        var hint = getString(strings, hintKey, fallback);
        return hint || '';
    }

    function normalizeHexColor(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var trimmed = value.trim();
        if (!trimmed) {
            return '';
        }
        if (trimmed.charAt(0) !== '#') {
            trimmed = '#' + trimmed;
        }
        if (trimmed.length === 4) {
            var r = trimmed.charAt(1);
            var g = trimmed.charAt(2);
            var b = trimmed.charAt(3);
            trimmed = '#' + r + r + g + g + b + b;
        }
        if (/^#([0-9a-fA-F]{6})$/.test(trimmed)) {
            return trimmed.toLowerCase();
        }
        return value;
    }

    function ensureArray(value) {
        if (Array.isArray(value)) {
            return value.slice();
        }
        return [];
    }

    function parseSeriesItems(raw) {
        if (!raw) {
            return [];
        }
        if (Array.isArray(raw)) {
            return raw.slice();
        }
        if (typeof raw === 'string') {
            try {
                var parsed = JSON.parse(raw);
                return Array.isArray(parsed) ? parsed : [];
            } catch (e) {
                return [];
            }
        }
        return [];
    }

    function ScheduleEditor(props) {
        var form = props.form;
        var meta = props.meta;
        var options = props.options || {};
        var strings = props.strings || {};
        var onChangeForm = props.onChangeForm;
        var onChangeMeta = props.onChangeMeta;
        var onToggleWeekday = props.onToggleWeekday;
        var onWeekdayTimeChange = props.onWeekdayTimeChange;
        var seriesItems = props.seriesItems || [];
        var onAddSeriesItem = props.onAddSeriesItem;
        var onUpdateSeriesItem = props.onUpdateSeriesItem;
        var onRemoveSeriesItem = props.onRemoveSeriesItem;

        var scheduleMode = form.event_schedule_mode || 'fixed';
        var scheduleWeekdays = options.schedule_weekdays || {};
        var scheduleMonthOrdinals = options.schedule_month_ordinals || {};
        var currentWeekdays = ensureArray(form.event_recurring_weekdays);
        var showDateRange = !!meta.scheduleShowDateRange;
        var weekdayTimes = meta.scheduleWeekdayTimes || {};

        var frequency = form.event_recurring_frequency || 'weekly';
        var interval = form.event_recurring_interval || 1;

        return h('div', { class: 'mj-regmgr-event-editor__section' }, [
            h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                h('h2', null, getString(strings, 'scheduleSection', 'Planification')),
                h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'scheduleSectionHint', 'Ajustez les dates et horaires de l\'evenement.')),
            ]),

            h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                    h('label', null, getString(strings, 'scheduleMode', 'Mode de planification')),
                    h('select', {
                        value: scheduleMode,
                        onChange: function (e) {
                            var value = e.target.value;
                            onChangeForm('event_schedule_mode', value);
                            onChangeMeta('scheduleMode', value);
                        },
                    }, [
                        h('option', { value: 'fixed' }, getString(strings, 'scheduleModeFixed', 'Date unique')),
                        h('option', { value: 'range' }, getString(strings, 'scheduleModeRange', 'Plage de dates')),
                        h('option', { value: 'recurring' }, getString(strings, 'scheduleModeRecurring', 'Recurrent')),
                        h('option', { value: 'series' }, getString(strings, 'scheduleModeSeries', 'Serie personnalisee')),
                    ]),
                ]),
            ]),

            scheduleMode === 'fixed' && h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'fixedDate', 'Date')),
                    h('input', {
                        type: 'date',
                        value: form.event_fixed_date || '',
                        onChange: function (e) { onChangeForm('event_fixed_date', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'fixedStartTime', 'Heure de debut')),
                    h('input', {
                        type: 'time',
                        value: form.event_fixed_start_time || '',
                        onChange: function (e) { onChangeForm('event_fixed_start_time', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'fixedEndTime', 'Heure de fin')),
                    h('input', {
                        type: 'time',
                        value: form.event_fixed_end_time || '',
                        onChange: function (e) { onChangeForm('event_fixed_end_time', e.target.value); },
                    }),
                ]),
            ]),

            scheduleMode === 'range' && h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'rangeStart', 'Debut (AAAA-MM-JJ HH:MM)')),
                    h('input', {
                        type: 'text',
                        value: form.event_range_start || '',
                        placeholder: '2024-05-01 09:00',
                        onChange: function (e) { onChangeForm('event_range_start', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'rangeEnd', 'Fin (AAAA-MM-JJ HH:MM)')),
                    h('input', {
                        type: 'text',
                        value: form.event_range_end || '',
                        placeholder: '2024-05-01 18:00',
                        onChange: function (e) { onChangeForm('event_range_end', e.target.value); },
                    }),
                ]),
            ]),

            scheduleMode === 'recurring' && h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringStartDate', 'Date de debut')),
                    h('input', {
                        type: 'date',
                        value: form.event_recurring_start_date || '',
                        onChange: function (e) { onChangeForm('event_recurring_start_date', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringStartTime', 'Heure de debut')),
                    h('input', {
                        type: 'time',
                        value: form.event_recurring_start_time || '',
                        onChange: function (e) { onChangeForm('event_recurring_start_time', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringEndTime', 'Heure de fin')),
                    h('input', {
                        type: 'time',
                        value: form.event_recurring_end_time || '',
                        onChange: function (e) { onChangeForm('event_recurring_end_time', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringFrequency', 'Frequence')),
                    h('select', {
                        value: frequency,
                        onChange: function (e) { onChangeForm('event_recurring_frequency', e.target.value); },
                    }, [
                        h('option', { value: 'weekly' }, getString(strings, 'recurringWeekly', 'Hebdomadaire')),
                        h('option', { value: 'monthly' }, getString(strings, 'recurringMonthly', 'Mensuel')),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringInterval', 'Intervalle')),
                    h('input', {
                        type: 'number',
                        min: '1',
                        step: '1',
                        value: interval,
                        onChange: function (e) {
                            var raw = e.target.value;
                            var value = raw === '' ? '' : Math.max(1, parseInt(raw, 10) || 1);
                            onChangeForm('event_recurring_interval', value);
                        },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox-row' }, [
                    h('label', null, getString(strings, 'recurringShowDateRange', 'Afficher la plage de dates dans les details')),
                    h('input', {
                        type: 'checkbox',
                        checked: showDateRange,
                        onChange: function (e) { onChangeMeta('scheduleShowDateRange', e.target.checked); },
                    }),
                ]),
            ]),

            scheduleMode === 'recurring' && frequency === 'weekly' && h('div', { class: 'mj-regmgr-event-editor__weekdays' }, [
                h('div', { class: 'mj-regmgr-event-editor__weekdays-list' }, Object.keys(scheduleWeekdays).map(function (key) {
                    var label = scheduleWeekdays[key];
                    var isActive = currentWeekdays.indexOf(key) !== -1;
                    var times = weekdayTimes[key] || { start: '', end: '' };
                    return h('div', { key: key, class: classNames('mj-regmgr-weekday', { 'mj-regmgr-weekday--active': isActive }) }, [
                        h('label', { class: 'mj-regmgr-weekday__label' }, [
                            h('input', {
                                type: 'checkbox',
                                checked: isActive,
                                onChange: function () { onToggleWeekday(key); },
                            }),
                            h('span', null, label),
                        ]),
                        isActive && h('div', { class: 'mj-regmgr-weekday__times' }, [
                            h('input', {
                                type: 'time',
                                value: times.start || '',
                                onChange: function (e) { onWeekdayTimeChange(key, 'start', e.target.value); },
                            }),
                            h('span', { class: 'mj-regmgr-weekday__time-sep' }, '->'),
                            h('input', {
                                type: 'time',
                                value: times.end || '',
                                onChange: function (e) { onWeekdayTimeChange(key, 'end', e.target.value); },
                            }),
                        ]),
                    ]);
                })),
            ]),

            scheduleMode === 'recurring' && frequency === 'monthly' && h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringOrdinal', 'Ordre')),
                    h('select', {
                        value: form.event_recurring_month_ordinal || 'first',
                        onChange: function (e) { onChangeForm('event_recurring_month_ordinal', e.target.value); },
                    }, Object.keys(scheduleMonthOrdinals).map(function (key) {
                        return h('option', { key: key, value: key }, scheduleMonthOrdinals[key]);
                    })),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringWeekday', 'Jour de semaine')),
                    h('select', {
                        value: form.event_recurring_month_weekday || 'saturday',
                        onChange: function (e) { onChangeForm('event_recurring_month_weekday', e.target.value); },
                    }, Object.keys(scheduleWeekdays).map(function (key) {
                        return h('option', { key: key, value: key }, scheduleWeekdays[key]);
                    })),
                ]),
            ]),

            scheduleMode === 'recurring' && h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringUntil', 'Jusqu\'au (optionnel)')),
                    h('input', {
                        type: 'date',
                        value: form.event_recurring_until || '',
                        onChange: function (e) { onChangeForm('event_recurring_until', e.target.value); },
                    }),
                ]),
            ]),

            scheduleMode === 'series' && h('div', { class: 'mj-regmgr-event-editor__series' }, [
                h('div', { class: 'mj-regmgr-event-editor__series-header' }, [
                    h('p', null, getString(strings, 'seriesDescription', 'Ajoutez chaque date de l\'evenement.')),
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--secondary mj-btn--small',
                        onClick: onAddSeriesItem,
                    }, getString(strings, 'seriesAdd', 'Ajouter une date')),
                ]),
                seriesItems.length === 0 && h('p', { class: 'mj-regmgr-event-editor__series-empty' }, getString(strings, 'seriesEmpty', 'Aucune date ajoutee.')),
                seriesItems.length > 0 && h('div', { class: 'mj-regmgr-event-editor__series-list' }, seriesItems.map(function (item, index) {
                    return h('div', { key: index, class: 'mj-regmgr-event-editor__series-row' }, [
                        h('input', {
                            type: 'date',
                            value: item.date || '',
                            onChange: function (e) { onUpdateSeriesItem(index, 'date', e.target.value); },
                        }),
                        h('input', {
                            type: 'time',
                            value: item.start_time || '',
                            onChange: function (e) { onUpdateSeriesItem(index, 'start_time', e.target.value); },
                        }),
                        h('input', {
                            type: 'time',
                            value: item.end_time || '',
                            onChange: function (e) { onUpdateSeriesItem(index, 'end_time', e.target.value); },
                        }),
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--ghost mj-btn--small',
                            onClick: function () { onRemoveSeriesItem(index); },
                        }, getString(strings, 'remove', 'Supprimer')),
                    ]);
                })),
            ]),
        ]);
    }

    function EventEditor(props) {
        var data = props.data;
        var eventSummary = props.eventSummary;
        var loading = props.loading;
        var saving = props.saving;
        var errors = props.errors || [];
        var onSubmit = props.onSubmit;
        var onReload = props.onReload;
        var strings = props.strings || {};

        var initialValues = data ? data.values : null;
        var initialOptions = data ? data.options : {};
        var initialMeta = data ? data.meta : {};
        var initialCoverUrl = eventSummary && eventSummary.coverUrl ? eventSummary.coverUrl : '';

        var _formState = useState(initialValues || {});
        var formState = _formState[0];
        var setFormState = _formState[1];

        var _metaState = useState(initialMeta || {});
        var metaState = _metaState[0];
        var setMetaState = _metaState[1];

        var _seriesItems = useState(parseSeriesItems(initialValues && initialValues.event_series_items));
        var seriesItems = _seriesItems[0];
        var setSeriesItems = _seriesItems[1];

        var _isDirty = useState(false);
        var isDirty = _isDirty[0];
        var setIsDirty = _isDirty[1];

        var _coverPreview = useState(initialCoverUrl);
        var coverPreview = _coverPreview[0];
        var setCoverPreview = _coverPreview[1];

        var mediaFrameRef = useRef(null);
        var accentTouchedRef = useRef(initialValues && initialValues.event_accent_color ? initialValues.event_accent_color !== '' : false);
        var accentLastRef = useRef(initialValues && initialValues.event_accent_color ? initialValues.event_accent_color : '');
        var previousTypeRef = useRef(initialValues && initialValues.event_type ? initialValues.event_type : '');
        var typeDefaultColors = useMemo(function () {
            var map = {};
            var raw = initialOptions && initialOptions.type_choice_attributes ? initialOptions.type_choice_attributes : {};
            Object.keys(raw).forEach(function (typeKey) {
                if (!Object.prototype.hasOwnProperty.call(raw, typeKey)) {
                    return;
                }
                var attr = raw[typeKey];
                if (!attr || typeof attr !== 'object') {
                    return;
                }
                var defaultColor = '';
                if (Object.prototype.hasOwnProperty.call(attr, 'data-default-color')) {
                    defaultColor = attr['data-default-color'];
                } else if (Object.prototype.hasOwnProperty.call(attr, 'data-default-colour')) {
                    defaultColor = attr['data-default-colour'];
                }
                if (typeof defaultColor === 'string' && defaultColor !== '') {
                    var normalized = normalizeHexColor(defaultColor);
                    map[typeKey] = normalized !== '' ? normalized : defaultColor;
                }
            });
            return map;
        }, [initialOptions]);
        var allowGuardianId = useMemo(function () {
            return 'mj-regmgr-allow-guardian-' + Math.random().toString(36).slice(2);
        }, []);
        var requiresValidationId = useMemo(function () {
            return 'mj-regmgr-requires-validation-' + Math.random().toString(36).slice(2);
        }, []);
        var freeParticipationId = useMemo(function () {
            return 'mj-regmgr-free-participation-' + Math.random().toString(36).slice(2);
        }, []);

        useEffect(function () {
            if (!data) {
                setFormState({});
                setMetaState({});
                setSeriesItems([]);
                setIsDirty(false);
                setCoverPreview('');
                accentTouchedRef.current = false;
                accentLastRef.current = '';
                previousTypeRef.current = '';
                return;
            }
            var nextValues = data.values ? Object.assign({}, data.values) : {};
            var nextMeta = data.meta ? JSON.parse(JSON.stringify(data.meta)) : {};
            setFormState(nextValues);
            setMetaState(nextMeta);
            setSeriesItems(parseSeriesItems(nextValues.event_series_items));
            setIsDirty(false);
            var nextCover = eventSummary && eventSummary.coverUrl ? eventSummary.coverUrl : '';
            setCoverPreview(nextCover);
            accentTouchedRef.current = !!(nextValues.event_accent_color && nextValues.event_accent_color !== '');
            accentLastRef.current = nextValues.event_accent_color || '';
            previousTypeRef.current = nextValues.event_type || '';
        }, [data, eventSummary]);

        useEffect(function () {
            if (isDirty) {
                return;
            }
            var nextCover = eventSummary && eventSummary.coverUrl ? eventSummary.coverUrl : '';
            if (coverPreview === nextCover) {
                return;
            }
            setCoverPreview(nextCover);
        }, [eventSummary, isDirty, coverPreview]);

        useEffect(function () {
            var currentType = formState.event_type || '';
            var previousType = previousTypeRef.current || '';
            if (currentType === previousType) {
                return;
            }
            previousTypeRef.current = currentType;

            if (!accentTouchedRef.current) {
                accentLastRef.current = formState.event_accent_color || '';
                return;
            }

            var manualAccent = accentLastRef.current || '';
            var currentAccent = formState.event_accent_color || '';
            var defaultAccent = '';
            if (typeDefaultColors && Object.prototype.hasOwnProperty.call(typeDefaultColors, currentType)) {
                defaultAccent = typeDefaultColors[currentType] || '';
            }

            var normalizeForCompare = function (value) {
                if (typeof value !== 'string') {
                    return '';
                }
                var normalized = normalizeHexColor(value);
                var candidate = normalized !== '' ? normalized : value;
                return candidate.trim().toLowerCase();
            };

            var currentComparable = normalizeForCompare(currentAccent);
            var manualComparable = normalizeForCompare(manualAccent);
            var defaultComparable = normalizeForCompare(defaultAccent);

            if (defaultComparable && currentComparable === defaultComparable && manualComparable !== defaultComparable) {
                if (manualAccent !== formState.event_accent_color) {
                    updateFormValue('event_accent_color', manualAccent);
                }
                return;
            }

            if (!defaultComparable && manualComparable === '' && currentComparable !== manualComparable) {
                updateFormValue('event_accent_color', manualAccent);
            }
        }, [formState.event_type, formState.event_accent_color, typeDefaultColors, updateFormValue]);

        var updateFormValue = useCallback(function (key, value) {
            setFormState(function (prev) {
                var next = Object.assign({}, prev);
                next[key] = value;
                return next;
            });
            setIsDirty(true);
        }, []);

        var updateMetaValue = useCallback(function (key, value) {
            setMetaState(function (prev) {
                var next = Object.assign({}, prev);
                next[key] = value;
                return next;
            });
            setIsDirty(true);
        }, []);

        var toggleArrayValue = useCallback(function (key, item, normalizer) {
            setFormState(function (prev) {
                var current = ensureArray(prev[key]);
                var normalized = normalizer ? normalizer(item) : item;
                var index = current.indexOf(normalized);
                if (index === -1) {
                    current.push(normalized);
                } else {
                    current.splice(index, 1);
                }
                var next = Object.assign({}, prev);
                next[key] = current;
                return next;
            });
            setIsDirty(true);
        }, []);

        var toggleWeekday = useCallback(function (weekday) {
            toggleArrayValue('event_recurring_weekdays', weekday);
        }, [toggleArrayValue]);

        var updateWeekdayTime = useCallback(function (weekday, field, value) {
            setMetaState(function (prev) {
                var next = Object.assign({}, prev);
                var times = Object.assign({}, next.scheduleWeekdayTimes || {});
                var entry = Object.assign({}, times[weekday] || { start: '', end: '' });
                entry[field] = value;
                times[weekday] = entry;
                next.scheduleWeekdayTimes = times;
                return next;
            });
            setIsDirty(true);
        }, []);

        var handleSeriesAdd = useCallback(function () {
            setSeriesItems(function (prev) {
                var next = prev.slice();
                next.push({ date: '', start_time: '', end_time: '' });
                return next;
            });
            setIsDirty(true);
        }, []);

        var handleSeriesUpdate = useCallback(function (index, field, value) {
            setSeriesItems(function (prev) {
                var next = prev.slice();
                if (!next[index]) {
                    return next;
                }
                var item = Object.assign({}, next[index]);
                item[field] = value;
                next[index] = item;
                return next;
            });
            setIsDirty(true);
        }, []);

        var handleSeriesRemove = useCallback(function (index) {
            setSeriesItems(function (prev) {
                var next = prev.slice();
                next.splice(index, 1);
                return next;
            });
            setIsDirty(true);
        }, []);

        var handleNumberChange = useCallback(function (key, value) {
            if (value === '') {
                updateFormValue(key, '');
                return;
            }
            var parsed = parseInt(value, 10);
            if (isNaN(parsed)) {
                updateFormValue(key, value);
            } else {
                updateFormValue(key, parsed);
            }
        }, [updateFormValue]);

        var handleFloatChange = useCallback(function (key, value) {
            if (value === '') {
                updateFormValue(key, '');
                return;
            }
            var parsed = parseFloat(value.replace(',', '.'));
            if (isNaN(parsed)) {
                updateFormValue(key, value);
            } else {
                updateFormValue(key, parsed);
            }
        }, [updateFormValue]);

        var handleAccentChange = useCallback(function (value) {
            accentTouchedRef.current = true;
            accentLastRef.current = value;
            updateFormValue('event_accent_color', value);
        }, [updateFormValue]);

        var handleSelectCover = useCallback(function () {
            var wpGlobal = global.wp;
            if (!wpGlobal || !wpGlobal.media || typeof wpGlobal.media !== 'function') {
                return;
            }
            if (!mediaFrameRef.current) {
                mediaFrameRef.current = wpGlobal.media({
                    title: getString(strings, 'coverModalTitle', 'Choisir un visuel de couverture'),
                    button: { text: getString(strings, 'coverSelect', 'Selectionner un fichier') },
                    multiple: false,
                    library: { type: 'image' },
                });
                mediaFrameRef.current.on('select', function () {
                    var frame = mediaFrameRef.current;
                    if (!frame) {
                        return;
                    }
                    var state = typeof frame.state === 'function' ? frame.state() : frame.state;
                    if (!state || typeof state.get !== 'function') {
                        return;
                    }
                    var selection = state.get('selection');
                    if (!selection || typeof selection.first !== 'function') {
                        return;
                    }
                    var attachment = selection.first();
                    if (!attachment || typeof attachment.toJSON !== 'function') {
                        return;
                    }
                    var details = attachment.toJSON();
                    var id = details && details.id ? parseInt(details.id, 10) || 0 : 0;
                    updateFormValue('event_cover_id', id);
                    var url = '';
                    if (details) {
                        if (details.sizes && details.sizes.medium && details.sizes.medium.url) {
                            url = details.sizes.medium.url;
                        } else if (details.url) {
                            url = details.url;
                        }
                    }
                    setCoverPreview(url);
                });
            }
            var frameInstance = mediaFrameRef.current;
            if (!frameInstance) {
                return;
            }
            var syncSelection = function () {
                var state = typeof frameInstance.state === 'function' ? frameInstance.state() : frameInstance.state;
                if (!state || typeof state.get !== 'function') {
                    return;
                }
                var selection = state.get('selection');
                if (!selection || typeof selection.reset !== 'function') {
                    return;
                }
                selection.reset();
                var currentId = formState.event_cover_id ? parseInt(formState.event_cover_id, 10) || 0 : 0;
                if (currentId <= 0) {
                    return;
                }
                var attachment = wpGlobal.media.attachment(currentId);
                if (!attachment) {
                    return;
                }
                if (typeof attachment.fetch === 'function') {
                    attachment.fetch();
                }
                selection.add(attachment);
            };
            if (typeof frameInstance.once === 'function') {
                frameInstance.once('open', syncSelection);
            } else if (typeof frameInstance.on === 'function') {
                frameInstance.on('open', function handleOpenOnce() {
                    if (typeof frameInstance.off === 'function') {
                        frameInstance.off('open', handleOpenOnce);
                    }
                    syncSelection();
                });
            } else {
                syncSelection();
            }
            frameInstance.open();
        }, [strings, updateFormValue, formState.event_cover_id, setCoverPreview]);

        var handleRemoveCover = useCallback(function () {
            updateFormValue('event_cover_id', 0);
            setCoverPreview('');
        }, [updateFormValue]);

        var handleSubmit = useCallback(function (e) {
            e.preventDefault();
            if (!onSubmit) {
                return;
            }
            var payloadForm = Object.assign({}, formState);
            payloadForm.event_series_items = JSON.stringify(seriesItems);
            if (payloadForm.event_accent_color) {
                payloadForm.event_accent_color = normalizeHexColor(payloadForm.event_accent_color);
            }
            if (typeof payloadForm.event_animateur_ids !== 'undefined') {
                payloadForm.event_animateur_ids = ensureArray(payloadForm.event_animateur_ids).map(function (id) {
                    return parseInt(id, 10) || 0;
                });
            }
            if (typeof payloadForm.event_volunteer_ids !== 'undefined') {
                payloadForm.event_volunteer_ids = ensureArray(payloadForm.event_volunteer_ids).map(function (id) {
                    return parseInt(id, 10) || 0;
                });
            }
            var payloadMeta = Object.assign({}, metaState);
            var result = onSubmit(payloadForm, payloadMeta);
            if (result && typeof result.then === 'function') {
                result.then(function () {
                    setIsDirty(false);
                }).catch(function () {
                    // keep dirty flag so user can retry
                });
            }
        }, [onSubmit, formState, metaState, seriesItems]);

        var animateurOptions = useMemo(function () {
            var map = initialOptions.animateurs || {};
            return Object.keys(map).map(function (id) {
                return { id: parseInt(id, 10) || 0, label: map[id] };
            });
        }, [initialOptions.animateurs]);

        var volunteerOptions = useMemo(function () {
            var map = initialOptions.volunteers || {};
            return Object.keys(map).map(function (id) {
                return { id: parseInt(id, 10) || 0, label: map[id] };
            });
        }, [initialOptions.volunteers]);

        var selectedAnimateurs = ensureArray(formState.event_animateur_ids);
        var selectedVolunteers = ensureArray(formState.event_volunteer_ids);

        var wpMediaAvailable = !!(global.wp && global.wp.media && typeof global.wp.media === 'function');

        var canSubmit = !!onSubmit && !saving && !loading && isDirty;

        return h('form', { class: 'mj-regmgr-event-editor', onSubmit: handleSubmit }, [
            h('div', { class: 'mj-regmgr-event-editor__header' }, [
                h('div', { class: 'mj-regmgr-event-editor__title' }, [
                    eventSummary ? h('h2', null, eventSummary.title || getString(strings, 'editorTitleFallback', 'Modifier l\'evenement')) : h('h2', null, getString(strings, 'editorTitleFallback', 'Modifier l\'evenement')),
                    eventSummary && h('p', { class: 'mj-regmgr-event-editor__subtitle' }, [
                        '#' + eventSummary.id + ' · ' + (eventSummary.statusLabel || eventSummary.status || ''),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-event-editor__actions' }, [
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--ghost',
                        onClick: onReload,
                        disabled: loading || saving,
                    }, getString(strings, 'reloadEditor', 'Reinitialiser le formulaire')),
                    h('button', {
                        type: 'submit',
                        class: 'mj-btn mj-btn--primary',
                        disabled: !canSubmit,
                    }, saving ? getString(strings, 'saving', 'Enregistrement...') : getString(strings, 'save', 'Enregistrer')),
                ]),
            ]),

            errors.length > 0 && h('div', { class: 'mj-regmgr-event-editor__errors' }, [
                h('ul', { class: 'mj-regmgr-event-editor__errors-list' }, errors.map(function (error, index) {
                    var hint = getErrorHint(strings, error);
                    return h('li', { key: index, class: 'mj-regmgr-event-editor__errors-item' }, [
                        h('span', { class: 'mj-regmgr-event-editor__error-message' }, error),
                        hint ? h('span', { class: 'mj-regmgr-event-editor__error-hint' }, hint) : null,
                    ]);
                })),
            ]),

            loading && h('div', { class: 'mj-regmgr-event-editor__loading' }, [
                h('div', { class: 'mj-regmgr-loading__spinner' }),
                h('p', null, getString(strings, 'loading', 'Chargement...')),
            ]),

            !loading && !data && h('div', { class: 'mj-regmgr-event-editor__empty' }, [
                h('p', null, getString(strings, 'editorEmpty', 'Selectionnez un evenement pour commencer la modification.')),
            ]),

            !loading && data && h(Fragment, null, [
                h('div', { class: 'mj-regmgr-event-editor__section' }, [
                    h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                        h('h2', null, getString(strings, 'generalSection', 'Informations principales')),
                        h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'generalSectionHint', 'Renseignez les informations essentielles de l evenement.')),
                    ]),
                    h('div', { class: 'mj-regmgr-form-grid' }, [
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'eventTitle', 'Titre')),
                            h('input', {
                                type: 'text',
                                value: formState.event_title || '',
                                onChange: function (e) { updateFormValue('event_title', e.target.value); },
                                required: true,
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'eventStatus', 'Statut')),
                            h('select', {
                                value: formState.event_status || '',
                                onChange: function (e) { updateFormValue('event_status', e.target.value); },
                            }, Object.keys(initialOptions.status_choices || {}).map(function (key) {
                                return h('option', { key: key, value: key }, initialOptions.status_choices[key]);
                            })),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'eventType', 'Type')),
                            h('select', {
                                value: formState.event_type || '',
                                onChange: function (e) { updateFormValue('event_type', e.target.value); },
                            }, Object.keys(initialOptions.type_choices || {}).map(function (key) {
                                return h('option', { key: key, value: key }, initialOptions.type_choices[key]);
                            })),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'accentColor', 'Couleur pastel')),
                            h('div', { class: 'mj-regmgr-color-input' }, [
                                h('input', {
                                    type: 'color',
                                    value: normalizeHexColor(formState.event_accent_color || '') || '#000000',
                                    onChange: function (e) { handleAccentChange(e.target.value); },
                                }),
                                h('input', {
                                    type: 'text',
                                    value: formState.event_accent_color || '',
                                    onChange: function (e) { handleAccentChange(e.target.value); },
                                    placeholder: '#123456',
                                }),
                            ]),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'accentColorHint', "Selectionnez une couleur d accent pour cet evenement. Laissez vide pour utiliser la couleur du type.")),
                        ]),
                        wpMediaAvailable ? h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                            h('label', null, getString(strings, 'coverLabel', 'Visuel')),
                            h('div', { class: 'mj-regmgr-media-control' }, [
                                h('div', {
                                    class: classNames('mj-regmgr-media-control__preview', {
                                        'mj-regmgr-media-control__preview--empty': !coverPreview,
                                    }),
                                }, coverPreview ? h('img', {
                                    src: coverPreview,
                                    alt: getString(strings, 'coverPreviewAlt', 'Apercu du visuel de couverture'),
                                }) : h('span', { class: 'mj-regmgr-media-control__placeholder' }, getString(strings, 'coverEmpty', 'Aucun visuel selectionne.'))),
                                h('div', { class: 'mj-regmgr-media-control__content' }, [
                                    h('p', { class: 'mj-regmgr-media-control__meta' }, formState.event_cover_id ? '#' + formState.event_cover_id : getString(strings, 'coverEmpty', 'Aucun visuel selectionne.')),
                                    h('div', { class: 'mj-regmgr-media-control__actions' }, [
                                        h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--ghost',
                                            onClick: handleSelectCover,
                                            disabled: loading || saving,
                                        }, formState.event_cover_id ? getString(strings, 'coverReplace', 'Remplacer le fichier') : getString(strings, 'coverSelect', 'Selectionner un fichier')),
                                        formState.event_cover_id ? h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--ghost mj-btn--sm',
                                            onClick: handleRemoveCover,
                                            disabled: loading || saving,
                                        }, getString(strings, 'remove', 'Supprimer')) : null,
                                    ]),
                                ]),
                            ]),
                        ]) : h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'coverId', 'ID de couverture')),
                            h('input', {
                                type: 'number',
                                value: formState.event_cover_id || '',
                                onChange: function (e) { handleNumberChange('event_cover_id', e.target.value); },
                                min: '0',
                            }),
                        ]),
                    ]),
                ]),

                h('div', { class: 'mj-regmgr-event-editor__section' }, [
                    h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                        h('h2', null, getString(strings, 'locationSection', 'Lieu et equipe')),
                        h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'locationSectionHint', "Choisissez le lieu d accueil et les referents associes.")),
                    ]),
                    h('div', { class: 'mj-regmgr-form-grid' }, [
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'location', 'Lieu')),
                            h('select', {
                                value: formState.event_location_id || 0,
                                onChange: function (e) { updateFormValue('event_location_id', parseInt(e.target.value, 10) || 0); },
                            }, [
                                h('option', { value: 0 }, getString(strings, 'noLocation', 'Aucun lieu defini')),
                                Object.keys(initialOptions.locations || {}).map(function (key) {
                                    return h('option', { key: key, value: key }, initialOptions.locations[key]);
                                }),
                            ]),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'locationHint', 'Administrez les lieux depuis la page des lieux du tableau de bord.')),
                        ]),
                    ]),
                    animateurOptions.length > 0 && h('div', { class: 'mj-regmgr-multiselect' }, [
                        h('p', { class: 'mj-regmgr-multiselect__label' }, getString(strings, 'animateurs', 'Animateurs referents')),
                        h('div', { class: 'mj-regmgr-multiselect__options' }, animateurOptions.map(function (option) {
                            var checked = selectedAnimateurs.indexOf(option.id) !== -1;
                            return h('label', { key: option.id, class: 'mj-regmgr-multiselect__option' }, [
                                h('input', {
                                    type: 'checkbox',
                                    checked: checked,
                                    onChange: function () {
                                        toggleArrayValue('event_animateur_ids', option.id, function (value) {
                                            return parseInt(value, 10) || 0;
                                        });
                                    },
                                }),
                                h('span', null, option.label),
                            ]);
                        })),
                        h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'animateursHint', "Selectionnez un ou plusieurs animateurs referents (optionnel); chacun recevra une notification lors des nouvelles inscriptions.")),
                    ]),
                    volunteerOptions.length > 0 && h('div', { class: 'mj-regmgr-multiselect' }, [
                        h('p', { class: 'mj-regmgr-multiselect__label' }, getString(strings, 'volunteers', 'Benevoles referents')),
                        h('div', { class: 'mj-regmgr-multiselect__options' }, volunteerOptions.map(function (option) {
                            var checked = selectedVolunteers.indexOf(option.id) !== -1;
                            return h('label', { key: option.id, class: 'mj-regmgr-multiselect__option' }, [
                                h('input', {
                                    type: 'checkbox',
                                    checked: checked,
                                    onChange: function () {
                                        toggleArrayValue('event_volunteer_ids', option.id, function (value) {
                                            return parseInt(value, 10) || 0;
                                        });
                                    },
                                }),
                                h('span', null, option.label),
                            ]);
                        })),
                        h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'volunteersHint', 'Choisissez des benevoles referents (optionnel); ils recevront les notifications d inscription pour cet evenement.')),
                    ]),
                ]),

                h('div', { class: 'mj-regmgr-event-editor__section' }, [
                    h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                        h('h2', null, getString(strings, 'registrationSection', 'Inscriptions et acces')),
                        h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'registrationSectionHint', "Configurez l ouverture des inscriptions, les capacites et les publics autorises.")),
                    ]),
                    h('div', { class: 'mj-regmgr-form-grid' }, [
                        h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full mj-regmgr-form-field--checkbox' }, [
                            h('span', { class: 'mj-regmgr-form-label' }, getString(strings, 'allowGuardian', 'Autoriser les tuteurs')),
                            h('label', { class: 'mj-regmgr-checkbox' }, [
                                h('input', {
                                    type: 'checkbox',
                                    id: allowGuardianId,
                                    checked: !!formState.event_allow_guardian_registration,
                                    onChange: function (e) { updateFormValue('event_allow_guardian_registration', e.target.checked); },
                                }),
                                h('span', null, getString(strings, 'allowGuardianToggle', "Les tuteurs peuvent s inscrire eux memes a cet evenement")),
                            ]),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'allowGuardianHint', "Par defaut, seuls les jeunes peuvent s inscrire.")),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full mj-regmgr-form-field--checkbox' }, [
                            h('span', { class: 'mj-regmgr-form-label' }, getString(strings, 'requiresValidation', 'Validation des inscriptions')),
                            h('label', { class: 'mj-regmgr-checkbox' }, [
                                h('input', {
                                    type: 'checkbox',
                                    id: requiresValidationId,
                                    checked: formState.event_requires_validation !== false,
                                    onChange: function (e) { updateFormValue('event_requires_validation', e.target.checked); },
                                }),
                                h('span', null, getString(strings, 'requiresValidationToggle', 'Confirmer manuellement chaque inscription recue pour cet evenement')),
                            ]),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'requiresValidationHint', 'Decochez pour valider automatiquement les reservations envoyees par les membres.')),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full mj-regmgr-form-field--checkbox' }, [
                            h('span', { class: 'mj-regmgr-form-label' }, getString(strings, 'freeParticipation', 'Participation libre')),
                            h('label', { class: 'mj-regmgr-checkbox' }, [
                                h('input', {
                                    type: 'checkbox',
                                    id: freeParticipationId,
                                    checked: !!formState.event_free_participation,
                                    onChange: function (e) { updateFormValue('event_free_participation', e.target.checked); },
                                }),
                                h('span', null, getString(strings, 'freeParticipationToggle', "Il n est pas necessaire de s inscrire a cet evenement")),
                            ]),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'freeParticipationHint', "Aucune reservation n est recue. L evenement reste visible dans l espace membre.")),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'capacityTotal', 'Places max')),
                            h('input', {
                                type: 'number',
                                value: formState.event_capacity_total || '',
                                min: '0',
                                onChange: function (e) { handleNumberChange('event_capacity_total', e.target.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'capacityWaitlist', "Liste d attente")),
                            h('input', {
                                type: 'number',
                                value: formState.event_capacity_waitlist || '',
                                min: '0',
                                onChange: function (e) { handleNumberChange('event_capacity_waitlist', e.target.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'capacityNotify', "Seuil d alerte")),
                            h('input', {
                                type: 'number',
                                value: formState.event_capacity_notify_threshold || '',
                                min: '0',
                                onChange: function (e) { handleNumberChange('event_capacity_notify_threshold', e.target.value); },
                            }),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'capacityHint', "Laisser 0 pour ne pas limiter les inscriptions ni activer d alerte.")),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'capacityAlertHint', 'Un email est envoye quand les places restantes sont inferieures ou egales au seuil.')),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'ageMin', 'Age minimum')),
                            h('input', {
                                type: 'number',
                                value: formState.event_age_min || '',
                                min: '0',
                                onChange: function (e) { handleNumberChange('event_age_min', e.target.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'ageMax', 'Age maximum')),
                            h('input', {
                                type: 'number',
                                value: formState.event_age_max || '',
                                min: '0',
                                onChange: function (e) { handleNumberChange('event_age_max', e.target.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'eventPrice', 'Tarif')),
                            h('input', {
                                type: 'number',
                                step: '0.01',
                                value: formState.event_price === 0 ? 0 : (formState.event_price || ''),
                                onChange: function (e) { handleFloatChange('event_price', e.target.value); },
                                min: '0',
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'registrationDeadline', "Date limite d inscription")),
                            h('input', {
                                type: 'text',
                                value: formState.event_date_deadline || '',
                                placeholder: '2024-05-10 12:00',
                                onChange: function (e) { updateFormValue('event_date_deadline', e.target.value); },
                            }),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'registrationDeadlineHint', "Laisser vide pour utiliser la date par defaut (14 jours avant le debut).")),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'occurrenceSelectionMode', 'Gestion des occurrences')),
                            h('select', {
                                value: formState.event_occurrence_selection_mode || 'member_choice',
                                onChange: function (e) { updateFormValue('event_occurrence_selection_mode', e.target.value); },
                            }, [
                                h('option', { value: 'member_choice' }, getString(strings, 'occurrenceChoiceMember', 'Les membres choisissent leurs occurrences')),
                                h('option', { value: 'all_occurrences' }, getString(strings, 'occurrenceChoiceAll', 'Inscrire automatiquement sur toutes les occurrences')),
                            ]),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'occurrenceModeHint', 'Ce parametre s applique aux evenements recurrents, en serie ou aux stages. En mode automatique, chaque inscription couvre toutes les occurrences disponibles.')),
                        ]),
                    ]),
                ]),

                h(ScheduleEditor, {
                    form: formState,
                    meta: metaState,
                    options: initialOptions,
                    strings: strings,
                    onChangeForm: updateFormValue,
                    onChangeMeta: updateMetaValue,
                    onToggleWeekday: toggleWeekday,
                    onWeekdayTimeChange: updateWeekdayTime,
                    seriesItems: seriesItems,
                    onAddSeriesItem: handleSeriesAdd,
                    onUpdateSeriesItem: handleSeriesUpdate,
                    onRemoveSeriesItem: handleSeriesRemove,
                }),

                h('div', { class: 'mj-regmgr-event-editor__section' }, [
                    h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                        h('h2', null, getString(strings, 'contentSection', 'Description')),
                        h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'contentSectionHint', 'Redigez le contenu presente aux membres et visiteurs.')),
                    ]),
                    h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                        h('label', { class: 'mj-regmgr-sr-only' }, getString(strings, 'description', 'Description')),
                        h('textarea', {
                            value: formState.event_description || '',
                            rows: 6,
                            onChange: function (e) { updateFormValue('event_description', e.target.value); },
                        }),
                    ]),
                ]),

                h('div', { class: 'mj-regmgr-event-editor__section' }, [
                    h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                        h('h2', null, getString(strings, 'articlesSection', 'Contenu lie')),
                        h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'articlesSectionHint', 'Liez un article ou une categorie pour completer la fiche evenement.')),
                    ]),
                    h('div', { class: 'mj-regmgr-form-grid' }, [
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'articleCategory', 'Categorie d\'article')),
                            h('select', {
                                value: formState.event_article_cat || 0,
                                onChange: function (e) { updateFormValue('event_article_cat', parseInt(e.target.value, 10) || 0); },
                            }, [
                                h('option', { value: 0 }, getString(strings, 'noCategory', 'Non defini')),
                                Object.keys(initialOptions.article_categories || {}).map(function (key) {
                                    return h('option', { key: key, value: key }, initialOptions.article_categories[key]);
                                }),
                            ]),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'article', 'Article')),
                            h('select', {
                                value: formState.event_article_id || 0,
                                onChange: function (e) { updateFormValue('event_article_id', parseInt(e.target.value, 10) || 0); },
                            }, [
                                h('option', { value: 0 }, getString(strings, 'noArticle', 'Aucun article')),
                                Object.keys(initialOptions.articles || {}).map(function (key) {
                                    return h('option', { key: key, value: key }, initialOptions.articles[key]);
                                }),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    global.MjRegMgrEventEditor = {
        EventEditor: EventEditor,
    };

})(window);
