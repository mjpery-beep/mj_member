/**
 * Registration Manager - Event Editor Components
 * Front-end form to edit an event from the registration manager widget
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var Modals = global.MjRegMgrModals || null;

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
    var Modal = Modals && Modals.Modal ? Modals.Modal : null;

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
        contentSectionHint: "Présentez l'événement en détail pour les membres et le public.",
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
        eventSlug: 'Slug',
        eventSlugHint: 'Identifiant URL. Laissez vide pour generer automatiquement depuis le titre.',
        eventType: 'Type',
        eventEmoji: 'Emoticone',
        eventEmojiHint: 'Facultatif, s affiche dans les listes et apercus.',
        eventEmojiPlaceholder: 'Ex: 🎉',
        eventEmojiPicker: 'Choisir',
        eventEmojiPickerClose: 'Fermer',
        eventEmojiClear: 'Effacer',
        eventEmojiSuggestions: 'Suggestions',
        eventEmojiSearchPlaceholder: 'Rechercher un emoji',
        eventEmojiSearchNoResult: 'Aucun emoji ne correspond a votre recherche.',
        eventEmojiAllCategory: 'Tout',
        fixedDate: 'Jour',
        fixedEndTime: 'Fin',
        fixedHint: 'Utilisez cette option pour un evenement sur une seule journee avec un creneau horaire.',
        fixedStartTime: 'Debut',
        freeParticipation: 'Participation libre',
        freeParticipationToggle: "Il n est pas necessaire de s inscrire a cet evenement",
        freeParticipationHint: "Aucune reservation n est recue. L evenement reste visible dans l espace membre.",
        attendanceList: 'Liste de presence',
        attendanceAllMembersToggle: 'Afficher tous les membres dans la liste de presence',
        attendanceAllMembersHint: 'Permet de pointer les membres autorises meme sans inscription prealable.',
        attendanceAllMembers: 'Liste de presence : tous les membres',
        attendanceRegisteredOnly: 'Liste de presence : inscrits uniquement',
        generalSection: 'Informations principales',
        generalSectionHint: "Renseignez les informations essentielles de l evenement.",
        loading: 'Chargement...',
        location: 'Lieu',
        locationHint: 'Administrez les lieux depuis la page des lieux du tableau de bord.',
        manageLocationHint: 'Ajoutez ou editez un lieu sans quitter ce formulaire.',
        addLocation: 'Ajouter un lieu',
        addLocationLink: 'Ajouter un lieu',
        editLocation: 'Modifier le lieu',
        removeLocationLink: 'Retirer',
        locationSection: 'Lieux',
        locationSectionHint: "Associez un ou plusieurs lieux a l'evenement avec leur role.",
        teamSection: 'Equipe',
        teamSectionHint: "Selectionnez les animateurs et benevoles referents pour cet evenement.",
        locationLinksTitle: 'Lieux associes',
        locationLinksHint: 'Ajoutez plusieurs lieux avec differents roles: depart, activite, retour, etc.',
        locationLinksEmpty: 'Aucun lieu associe.',
        locationSelectPlaceholder: 'Choisir un lieu',
        locationTypeDeparture: 'Lieu de depart',
        locationTypeActivity: "Lieu d activite",
        locationTypeReturn: 'Lieu de retour',
        locationTypeOther: 'Autre',
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
        recurringAgendaTitle: 'Occurrences planifiees',
        recurringAgendaHint: 'Cliquez sur une date pour la desactiver ou la reactiver.',
        recurringAgendaWeekLabel: 'Semaine du {date}',
        recurringAgendaWeekOccurrences: '{count} occurrence(s)',
        recurringAgendaWeekCancelled: '{count} annulee(s)',
        recurringAgendaWeekExcluded: '{count} exclue(s)',
        recurringAgendaToggleShow: 'Afficher les occurrences',
        recurringAgendaToggleHide: 'Masquer les occurrences',
        recurringAgendaTotalLabel: 'Occurrences',
        recurringAgendaCancelledLabel: 'Annulees',
        recurringAgendaExcludedLabel: 'Exclusions',
        recurringAgendaEmpty: 'Configurez la recurrence pour visualiser les occurrences.',
        recurringAgendaDisabled: 'Exclu',
        recurringAgendaCancelled: 'Annule',
        recurringAgendaCancelPrompt: "Motif d'annulation (optionnel)",
        recurringAgendaExceptionTitle: "Gestion de l occurrence",
        recurringAgendaExceptionSubtitle: 'Choisissez comment traiter cette occurrence.',
        recurringAgendaExceptionCancelOption: 'Annuler la seance (afficher un motif)',
        recurringAgendaExceptionExcludeOption: 'Exclure de la serie (masquer sans motif)',
        recurringAgendaExceptionReasonLabel: "Motif d annulation",
        recurringAgendaExceptionReasonPlaceholder: 'Animateur absent, meteo, ...',
        recurringAgendaExceptionMissingReason: 'Indiquez un motif pour confirmer l annulation.',
        recurringAgendaExceptionDateLabel: 'Date',
        recurringAgendaExceptionTimeLabel: 'Horaire',
        recurringAgendaExceptionCancel: 'Fermer',
        recurringAgendaExceptionSave: 'Enregistrer',
        recurringAgendaExceptionRestore: 'Reactiver l occurrence',
        registrationDeadline: "Date limite d inscription",
        registrationDeadlineHint: "Laisser vide pour ne pas fixer de date limite.",
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

    function formatTemplate(template, replacements) {
        if (!template) {
            return '';
        }
        var result = String(template);
        if (!replacements || typeof replacements !== 'object') {
            return result;
        }
        Object.keys(replacements).forEach(function (repKey) {
            var token = '{' + repKey + '}';
            var value = replacements[repKey];
            result = result.split(token).join(value);
        });
        return result;
    }

    function splitDateTimeParts(value) {
        if (!value) {
            return { date: '', time: '' };
        }
        var raw = String(value).trim().replace('T', ' ');
        if (!raw) {
            return { date: '', time: '' };
        }
        var segments = raw.split(' ');
        var date = segments[0] || '';
        var time = segments[1] || '';
        if (time.length > 5) {
            time = time.slice(0, 5);
        }
        return { date: date, time: time };
    }

    function mergeDateTimeParts(date, time) {
        var cleanDate = (date || '').trim();
        var cleanTime = (time || '').trim();
        if (!cleanDate && !cleanTime) {
            return '';
        }
        if (!cleanDate) {
            return '';
        }
        if (!cleanTime) {
            return cleanDate;
        }
        return cleanDate + ' ' + cleanTime;
    }

    function normalizeScheduleMode(value) {
        var mode = typeof value === 'string' ? value : '';
        if (mode === 'fixed' || mode === 'range' || mode === 'recurring' || mode === 'series') {
            return mode;
        }
        return 'fixed';
    }

    function formatDateTimeLocalValue(value) {
        if (!value) {
            return '';
        }
        var parts = splitDateTimeParts(value);
        if (!parts.date) {
            return '';
        }
        if (!parts.time) {
            return parts.date;
        }
        var time = parts.time.length > 5 ? parts.time.slice(0, 5) : parts.time;
        return parts.date + 'T' + time;
    }

    function normalizeDateTimeLocalValue(value) {
        if (!value) {
            return '';
        }
        var normalized = String(value).replace('T', ' ').trim();
        if (normalized === '') {
            return '';
        }
        if (/^[0-9]{4}-[0-9]{2}-[0-9]{2}\s[0-9]{2}:[0-9]{2}$/.test(normalized)) {
            return normalized + ':00';
        }
        return normalized;
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

    function normalizeScheduleExceptionEntry(entry) {
        if (entry === null || typeof entry === 'undefined') {
            return null;
        }

        var dateCandidate = '';
        var reasonCandidate = '';

        if (typeof entry === 'string' || typeof entry === 'number') {
            dateCandidate = String(entry);
        } else if (typeof entry === 'object') {
            if (Array.isArray(entry)) {
                if (entry.length > 0) {
                    dateCandidate = entry[0];
                }
                if (entry.length > 1) {
                    reasonCandidate = entry[1];
                }
            } else {
                if (Object.prototype.hasOwnProperty.call(entry, 'date')) {
                    dateCandidate = entry.date;
                }
                if (Object.prototype.hasOwnProperty.call(entry, 'reason')) {
                    reasonCandidate = entry.reason;
                }
            }
        }

        var normalizedDate = normalizeIsoDate(dateCandidate);
        if (!normalizedDate) {
            return null;
        }

        var normalizedReason = '';
        if (typeof reasonCandidate === 'string') {
            normalizedReason = reasonCandidate.replace(/\s+/g, ' ').trim();
            if (normalizedReason.length > 200) {
                normalizedReason = normalizedReason.slice(0, 200);
            }
        }

        return normalizedReason !== ''
            ? { date: normalizedDate, reason: normalizedReason }
            : { date: normalizedDate };
    }

    function normalizeScheduleExceptions(value) {
        var map = {};
        ensureArray(value).forEach(function (item) {
            var entry = normalizeScheduleExceptionEntry(item);
            if (!entry) {
                return;
            }
            if (map[entry.date]) {
                if (!map[entry.date].reason && entry.reason) {
                    map[entry.date].reason = entry.reason;
                }
                return;
            }
            map[entry.date] = {
                date: entry.date,
                reason: entry.reason ? entry.reason : '',
            };
        });

        return Object.keys(map)
            .sort()
            .map(function (dateKey) {
                var stored = map[dateKey];
                if (stored.reason) {
                    return { date: dateKey, reason: stored.reason };
                }
                return { date: dateKey };
            });
    }

    function findScheduleExceptionIndex(list, date) {
        if (!Array.isArray(list) || !date) {
            return -1;
        }
        for (var idx = 0; idx < list.length; idx += 1) {
            var entry = list[idx];
            if (entry && entry.date === date) {
                return idx;
            }
        }
        return -1;
    }

    function scheduleExceptionsToMap(list) {
        var map = {};
        normalizeScheduleExceptions(list).forEach(function (entry) {
            map[entry.date] = entry.reason ? { reason: entry.reason } : { reason: '' };
        });
        return map;
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
            } catch (error) {
                return [];
            }
        }
        return [];
    }

    var WEEKDAY_KEYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    var WEEKDAY_NUMBERS = {
        monday: 1,
        tuesday: 2,
        wednesday: 3,
        thursday: 4,
        friday: 5,
        saturday: 6,
        sunday: 7,
    };
    var ORDINAL_INDEX = {
        first: 1,
        second: 2,
        third: 3,
        fourth: 4,
        last: -1,
    };
    var MAX_PREVIEW_OCCURRENCES = 120;
    var MAX_PREVIEW_ITERATIONS = 520;

    function normalizeIsoDate(value) {
        if (!value) {
            return '';
        }
        var text = String(value).trim();
        var match = /^([0-9]{4})-([0-9]{2})-([0-9]{2})$/.exec(text);
        if (!match) {
            return '';
        }
        return match[1] + '-' + match[2] + '-' + match[3];
    }

    function parseIsoDate(value) {
        var normalized = normalizeIsoDate(value);
        if (!normalized) {
            return null;
        }
        var segments = normalized.split('-');
        var year = parseInt(segments[0], 10);
        var month = parseInt(segments[1], 10) - 1;
        var day = parseInt(segments[2], 10);
        if (isNaN(year) || isNaN(month) || isNaN(day)) {
            return null;
        }
        return new Date(year, month, day);
    }

    function normalizeTime(value) {
        if (!value) {
            return '';
        }
        var text = String(value).trim();
        var match = /^([0-9]{1,2}):([0-9]{2})$/.exec(text);
        if (!match) {
            return '';
        }
        var hours = parseInt(match[1], 10);
        var minutes = parseInt(match[2], 10);
        if (isNaN(hours) || isNaN(minutes)) {
            return '';
        }
        if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
            return '';
        }
        var paddedHours = hours < 10 ? '0' + hours : String(hours);
        var paddedMinutes = minutes < 10 ? '0' + minutes : String(minutes);
        return paddedHours + ':' + paddedMinutes;
    }

    function pickTimeFromWeekdayTimes(weekdayTimes, field, preferEarliest) {
        if (!weekdayTimes || typeof weekdayTimes !== 'object') {
            return '';
        }
        var best = '';
        Object.keys(weekdayTimes).forEach(function (weekday) {
            if (!Object.prototype.hasOwnProperty.call(weekdayTimes, weekday)) {
                return;
            }
            var entry = weekdayTimes[weekday];
            if (!entry || typeof entry !== 'object') {
                return;
            }
            var candidate = normalizeTime(entry[field]);
            if (!candidate) {
                return;
            }
            if (!best) {
                best = candidate;
                return;
            }
            if (preferEarliest && candidate < best) {
                best = candidate;
                return;
            }
            if (!preferEarliest && candidate > best) {
                best = candidate;
            }
        });
        return best;
    }

    function sanitizeExceptionReason(value) {
        if (!value) {
            return '';
        }
        var text = String(value).replace(/\s+/g, ' ').trim();
        if (text.length > 200) {
            text = text.slice(0, 200);
        }
        return text;
    }

    function extractTimeFromDateValue(value) {
        if (typeof value !== 'string') {
            return '';
        }
        if (value.length >= 16) {
            return normalizeTime(value.slice(11, 16));
        }
        return '';
    }

    function formatIsoDate(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }
        var year = date.getFullYear();
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return year + '-' + month + '-' + day;
    }

    function formatDateDisplay(date) {
        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }
        if (typeof date.toLocaleDateString === 'function') {
            try {
                return date.toLocaleDateString(undefined, {
                    weekday: 'long',
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                });
            } catch (localeError) {
                return formatIsoDate(date);
            }
        }
        return formatIsoDate(date);
    }

    function formatTimeValue(value) {
        if (!value) {
            return '';
        }
        var text = String(value).trim();
        if (/^[0-9]{2}:[0-9]{2}:[0-9]{2}$/.test(text)) {
            return text.slice(0, 5);
        }
        if (/^[0-9]{2}:[0-9]{2}$/.test(text)) {
            return text;
        }
        return '';
    }

    function formatTimeRange(start, end) {
        var startLabel = formatTimeValue(start);
        var endLabel = formatTimeValue(end);
        if (startLabel && endLabel) {
            return startLabel + ' - ' + endLabel;
        }
        if (startLabel) {
            return startLabel;
        }
        if (endLabel) {
            return endLabel;
        }
        return '';
    }

    function formatDateTimeLabel(value) {
        if (!value) {
            return '';
        }
        var parts = splitDateTimeParts(value);
        if (!parts.date) {
            return String(value);
        }
        var dateObj = parseIsoDate(parts.date);
        var dateLabel = dateObj ? formatDateDisplay(dateObj) : parts.date;
        if (parts.time) {
            var time = parts.time.length > 5 ? parts.time.slice(0, 5) : parts.time;
            return dateLabel + ' ' + time;
        }
        return dateLabel;
    }

    function addDays(date, amount) {
        var next = new Date(date.getTime());
        next.setDate(next.getDate() + amount);
        return next;
    }

    function addMonths(date, amount) {
        var next = new Date(date.getTime());
        next.setMonth(next.getMonth() + amount);
        return next;
    }

    function startOfWeek(date) {
        var base = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        var day = base.getDay();
        var diff = day === 0 ? -6 : 1 - day;
        base.setDate(base.getDate() + diff);
        return base;
    }

    function weekdayNumberFromDate(date) {
        var day = date.getDay();
        return day === 0 ? 7 : day;
    }

    function weekdayToNumber(key) {
        if (!key) {
            return null;
        }
        var normalized = String(key).toLowerCase();
        var number = WEEKDAY_NUMBERS[normalized];
        return typeof number === 'number' ? number : null;
    }

    function resolveMonthlyOccurrence(monthCursor, ordinalKey, weekdayNum) {
        var ordinalValue = ORDINAL_INDEX[ordinalKey] || 1;
        var monthStart = new Date(monthCursor.getFullYear(), monthCursor.getMonth(), 1);

        if (ordinalValue === -1) {
            var lastDay = new Date(monthCursor.getFullYear(), monthCursor.getMonth() + 1, 0);
            while (weekdayNumberFromDate(lastDay) !== weekdayNum) {
                lastDay.setDate(lastDay.getDate() - 1);
            }
            return new Date(lastDay.getFullYear(), lastDay.getMonth(), lastDay.getDate());
        }

        var count = 0;
        var candidate = new Date(monthStart.getTime());
        while (candidate.getMonth() === monthStart.getMonth()) {
            if (weekdayNumberFromDate(candidate) === weekdayNum) {
                count += 1;
                if (count === ordinalValue) {
                    return new Date(candidate.getFullYear(), candidate.getMonth(), candidate.getDate());
                }
            }
            candidate.setDate(candidate.getDate() + 1);
        }

        return null;
    }

    function createAgendaEntry(dateObj, startTime, endTime, exceptionsMap) {
        var isoDate = formatIsoDate(dateObj);
        var exceptionMeta = isoDate && exceptionsMap ? exceptionsMap[isoDate] : null;
        var isExcluded = !!(exceptionMeta);
        var reason = exceptionMeta && typeof exceptionMeta.reason === 'string' ? exceptionMeta.reason : '';

        var entry = {
            date: isoDate,
            dateLabel: formatDateDisplay(dateObj),
            timeLabel: formatTimeRange(startTime, endTime),
            disabled: isExcluded,
        };

        if (reason) {
            entry.reason = reason;
        }

        return entry;
    }

    function buildRecurringWeeklyAgenda(config) {
        var weekdays = ensureArray(config.weekdays).map(function (weekday) {
            return weekdayToNumber(weekday);
        }).filter(function (value) {
            return typeof value === 'number';
        }).sort(function (left, right) {
            return left - right;
        });

        if (!weekdays.length) {
            return [];
        }

        var occurrences = [];
        var weekCursor = startOfWeek(config.startDate);
        var safety = 0;

        while (occurrences.length < config.maxOccurrences && safety < MAX_PREVIEW_ITERATIONS) {
            var currentWeek = new Date(weekCursor.getTime());

            for (var i = 0; i < weekdays.length; i++) {
                var weekdayNum = weekdays[i];
                var dayOffset = weekdayNum - 1;
                var occurrenceDate = addDays(currentWeek, dayOffset);

                if (occurrenceDate < config.startDate) {
                    continue;
                }
                if (occurrenceDate > config.untilDate) {
                    return occurrences;
                }

                var weekdayKey = WEEKDAY_KEYS[weekdayNum - 1] || 'monday';
                var timeOverride = config.weekdayTimes[weekdayKey] || {};
                var startTime = timeOverride.start || config.defaultStartTime;
                var endTime = timeOverride.end || config.defaultEndTime;

                occurrences.push(createAgendaEntry(occurrenceDate, startTime, endTime, config.exceptionsMap));

                if (occurrences.length >= config.maxOccurrences) {
                    return occurrences;
                }
            }

            weekCursor = addDays(weekCursor, config.interval * 7);
            safety += 1;
        }

        return occurrences;
    }

    function buildRecurringMonthlyAgenda(config) {
        var weekdayNum = weekdayToNumber(config.monthWeekday);
        if (weekdayNum === null) {
            weekdayNum = 6;
        }
        var occurrences = [];
        var monthCursor = new Date(config.startDate.getFullYear(), config.startDate.getMonth(), 1);
        var safety = 0;

        while (occurrences.length < config.maxOccurrences && safety < MAX_PREVIEW_ITERATIONS) {
            var occurrenceDate = resolveMonthlyOccurrence(monthCursor, config.monthOrdinal, weekdayNum);
            if (!occurrenceDate) {
                monthCursor = addMonths(monthCursor, config.interval);
                safety += 1;
                continue;
            }

            if (occurrenceDate < config.startDate) {
                monthCursor = addMonths(monthCursor, config.interval);
                safety += 1;
                continue;
            }

            if (occurrenceDate > config.untilDate) {
                break;
            }

            occurrences.push(createAgendaEntry(occurrenceDate, config.defaultStartTime, config.defaultEndTime, config.exceptionsMap));

            monthCursor = addMonths(monthCursor, config.interval);
            safety += 1;
        }

        return occurrences;
    }

    function buildRecurringAgenda(config) {
        var startDate = parseIsoDate(config.startDate);
        if (!startDate) {
            return [];
        }

        var untilDate = parseIsoDate(config.untilDate);
        if (!untilDate) {
            untilDate = addMonths(startDate, 6);
        }
        untilDate.setHours(23, 59, 59, 999);

        var exceptionsMap = scheduleExceptionsToMap(config.exceptions);

        var baseConfig = {
            startDate: startDate,
            untilDate: untilDate,
            interval: Math.max(1, parseInt(config.interval, 10) || 1),
            defaultStartTime: config.startTime || '',
            defaultEndTime: config.endTime || '',
            weekdayTimes: config.weekdayTimes || {},
            exceptionsMap: exceptionsMap,
            maxOccurrences: config.maxOccurrences || MAX_PREVIEW_OCCURRENCES,
            monthOrdinal: config.monthOrdinal || 'first',
            monthWeekday: config.monthWeekday || 'saturday',
        };

        if (config.frequency === 'monthly') {
            return buildRecurringMonthlyAgenda(baseConfig);
        }

        baseConfig.weekdays = config.weekdays || [];
        return buildRecurringWeeklyAgenda(baseConfig);
    }

    function copyWeekdayTimes(source) {
        var result = {};
        if (!source || typeof source !== 'object') {
            return result;
        }
        Object.keys(source).forEach(function (weekday) {
            if (!Object.prototype.hasOwnProperty.call(source, weekday)) {
                return;
            }
            var entry = source[weekday];
            if (!entry || typeof entry !== 'object') {
                return;
            }
            result[weekday] = {
                start: entry.start ? String(entry.start) : '',
                end: entry.end ? String(entry.end) : '',
            };
        });
        return result;
    }

    function createSchedulePayloadSnapshot(mode, form, context) {
        var payload = { mode: normalizeScheduleMode(mode) };
        var extras = context && typeof context === 'object' ? context : {};
        var exceptions = Array.isArray(extras.exceptions) ? extras.exceptions : [];
        var seriesItems = Array.isArray(extras.seriesItems) ? extras.seriesItems : [];
        var weekdayTimesSource = extras.weekdayTimes || {};
        var showDateRange = !!extras.showDateRange;

        if (payload.mode === 'fixed') {
            payload.date = form.event_fixed_date || '';
            payload.start_time = form.event_fixed_start_time || '';
            payload.end_time = form.event_fixed_end_time || '';
        } else if (payload.mode === 'range') {
            payload.start = form.event_range_start || '';
            payload.end = form.event_range_end || '';
        } else if (payload.mode === 'recurring') {
            payload.frequency = form.event_recurring_frequency === 'monthly' ? 'monthly' : 'weekly';
            payload.interval = form.event_recurring_interval ? parseInt(form.event_recurring_interval, 10) || 1 : 1;
            payload.weekdays = ensureArray(form.event_recurring_weekdays);
            payload.weekday_times = copyWeekdayTimes(weekdayTimesSource);
            payload.start_date = form.event_recurring_start_date || '';
            payload.start_time = form.event_recurring_start_time || '';
            payload.end_time = form.event_recurring_end_time || '';
            payload.ordinal = form.event_recurring_month_ordinal || 'first';
            payload.weekday = form.event_recurring_month_weekday || 'saturday';
            payload.until = form.event_recurring_until || '';
            payload.show_date_range = showDateRange;
            payload.exceptions = normalizeScheduleExceptions(extras.exceptions);
        } else if (payload.mode === 'series') {
            payload.items = seriesItems.map(function (item) {
                return {
                    date: item && item.date ? item.date : '',
                    start_time: item && item.start_time ? item.start_time : '',
                    end_time: item && item.end_time ? item.end_time : '',
                };
            });
        }

        payload.version = payload.version || 'event-editor';
        return payload;
    }

    function groupRecurringAgendaEntries(entries, strings) {
        var list = Array.isArray(entries) ? entries : [];
        if (!list.length) {
            return {
                groups: [],
                stats: { total: 0, cancelled: 0, excluded: 0 },
            };
        }

        var map = {};
        var stats = { total: list.length, cancelled: 0, excluded: 0 };

        list.forEach(function (entry) {
            if (!entry || !entry.date) {
                return;
            }
            var dateObj = parseIsoDate(entry.date);
            var weekStart = dateObj ? startOfWeek(dateObj) : null;
            var weekKey = weekStart ? formatIsoDate(weekStart) : entry.date;
            if (!map[weekKey]) {
                map[weekKey] = {
                    key: weekKey,
                    date: weekStart,
                    entries: [],
                    cancelled: 0,
                    excluded: 0,
                };
            }
            var group = map[weekKey];
            var cloned = Object.assign({}, entry);
            if (entry.disabled) {
                if (entry.reason) {
                    group.cancelled += 1;
                    stats.cancelled += 1;
                } else {
                    group.excluded += 1;
                    stats.excluded += 1;
                }
            }
            group.entries.push(cloned);
        });

        var groups = Object.keys(map).sort().map(function (key) {
            var bucket = map[key];
            var labelDate = bucket.date ? formatDateDisplay(bucket.date) : key;
            var label = formatTemplate(getString(strings, 'recurringAgendaWeekLabel', 'Semaine du {date}'), { date: labelDate });
            var parts = [
                formatTemplate(getString(strings, 'recurringAgendaWeekOccurrences', '{count} occurrence(s)'), { count: bucket.entries.length }),
            ];
            if (bucket.cancelled > 0) {
                parts.push(formatTemplate(getString(strings, 'recurringAgendaWeekCancelled', '{count} annulee(s)'), { count: bucket.cancelled }));
            }
            if (bucket.excluded > 0) {
                parts.push(formatTemplate(getString(strings, 'recurringAgendaWeekExcluded', '{count} exclue(s)'), { count: bucket.excluded }));
            }
            var summary = parts.join(' · ');
            var entriesWithMeta = bucket.entries.map(function (entry) {
                return Object.assign({}, entry, {
                    weekLabel: label,
                    weekSummary: summary,
                });
            });
            return {
                key: key,
                label: label,
                summary: summary,
                entries: entriesWithMeta,
                cancelled: bucket.cancelled,
                excluded: bucket.excluded,
            };
        });

        return {
            groups: groups,
            stats: stats,
        };
    }

    function computeOccurrenceSummary(schedulePayload) {
        if (!schedulePayload || typeof schedulePayload !== 'object') {
            return null;
        }
        var occurrences = Array.isArray(schedulePayload.occurrences) ? schedulePayload.occurrences : [];
        var items = Array.isArray(schedulePayload.items) ? schedulePayload.items : [];
        var source = occurrences.length > 0 ? occurrences : items;
        if (!source.length) {
            return { count: 0 };
        }
        var first = null;
        var last = null;
        source.forEach(function (entry) {
            if (!entry || typeof entry !== 'object') {
                return;
            }
            var start = '';
            var end = '';
            if (entry.start) {
                start = String(entry.start);
            } else if (entry.date && entry.start_time) {
                start = entry.date + ' ' + entry.start_time;
            } else if (entry.date) {
                start = String(entry.date);
            }
            if (entry.end) {
                end = String(entry.end);
            } else if (entry.date && entry.end_time) {
                end = entry.date + ' ' + entry.end_time;
            } else if (entry.date && entry.start_time) {
                end = entry.date + ' ' + entry.start_time;
            } else if (entry.date) {
                end = String(entry.date);
            }
            if (start) {
                if (!first || start < first) {
                    first = start;
                }
            }
            if (end) {
                if (!last || end > last) {
                    last = end;
                }
            } else if (start) {
                if (!last || start > last) {
                    last = start;
                }
            }
        });
        return {
            count: source.length,
            first: first,
            last: last,
        };
    }

    function normalizeEmojiSearchValue(value) {
        if (!value) {
            return '';
        }
        var text = String(value).toLowerCase();
        if (typeof text.normalize === 'function') {
            text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return text.replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function createEmojiHelper(definition) {
        var categories = [];
        var flat = [];

        if (Array.isArray(definition)) {
            definition.forEach(function (categoryDef, categoryIndex) {
                if (!categoryDef) {
                    return;
                }

                var key = categoryDef.key ? String(categoryDef.key) : 'category-' + categoryIndex;
                var label = categoryDef.label ? String(categoryDef.label) : key;
                var rawItems = Array.isArray(categoryDef.items) ? categoryDef.items : [];

                var items = rawItems.map(function (rawItem) {
                    var symbol = '';
                    var name = '';
                    var keywords = [];

                    if (typeof rawItem === 'string') {
                        symbol = rawItem;
                    } else if (rawItem && typeof rawItem === 'object') {
                        symbol = rawItem.symbol || '';
                        if (rawItem.name) {
                            name = String(rawItem.name);
                        }
                        if (Array.isArray(rawItem.keywords)) {
                            keywords = rawItem.keywords.map(String);
                        } else if (rawItem.keywords) {
                            keywords = [String(rawItem.keywords)];
                        }
                    }

                    symbol = sanitizeEmojiValue(symbol);
                    if (!symbol) {
                        return null;
                    }

                    var searchParts = [symbol, name].concat(keywords);
                    var searchIndex = searchParts.map(normalizeEmojiSearchValue).filter(Boolean).join(' ');

                    return {
                        symbol: symbol,
                        name: name,
                        keywords: keywords,
                        search: searchIndex,
                        category: key,
                        categoryLabel: label,
                    };
                }).filter(Boolean);

                if (!items.length) {
                    return;
                }

                var category = {
                    key: key,
                    label: label,
                    items: items,
                };

                categories.push(category);
                flat = flat.concat(items);
            });
        }

        return {
            getCategories: function () {
                return categories.slice();
            },
            listAll: function () {
                return flat.slice();
            },
            filter: function (options) {
                var query = options && options.query ? normalizeEmojiSearchValue(options.query) : '';
                var categoryKey = options && options.category ? String(options.category) : null;
                var target = categoryKey
                    ? categories.filter(function (category) { return category.key === categoryKey; })
                    : categories;

                return target.map(function (category) {
                    var items = category.items.filter(function (item) {
                        if (!query) {
                            return true;
                        }
                        return item.search.indexOf(query) !== -1;
                    });

                    return {
                        key: category.key,
                        label: category.label,
                        items: items,
                    };
                });
            },
        };
    }

    function parseEmojiBlock(block) {
        if (typeof block !== 'string') {
            return [];
        }
        return block.split('\n').map(function (line) {
            var trimmed = line.trim();
            if (!trimmed || trimmed.charAt(0) === '#') {
                return null;
            }
            var parts = trimmed.split('|');
            var symbol = parts[0] ? parts[0].trim() : '';
            if (!symbol) {
                return null;
            }
            var name = parts[1] ? parts[1].trim() : '';
            var keywords = [];
            if (parts.length > 2) {
                keywords = parts[2].split(',').map(function (part) {
                    return part.trim();
                }).filter(Boolean);
            }
            return {
                symbol: symbol,
                name: name,
                keywords: keywords,
            };
        }).filter(Boolean);
    }

    function buildFlagEntries(records) {
        if (!Array.isArray(records)) {
            return [];
        }
        var displayNames = null;
        if (typeof Intl !== 'undefined' && typeof Intl.DisplayNames === 'function') {
            try {
                displayNames = new Intl.DisplayNames(['fr', 'en'], { type: 'region' });
            } catch (displayNameError) {
                displayNames = null;
            }
        }

        return records.map(function (entry) {
            var code = '';
            var label = '';
            var supplementalKeywords = [];

            if (typeof entry === 'string') {
                var parts = entry.split('|');
                code = (parts[0] || '').trim().toUpperCase();
                if (parts.length > 1) {
                    label = (parts[1] || '').trim();
                }
                if (parts.length > 2) {
                    supplementalKeywords = parts[2].split(',').map(function (part) {
                        return part.trim();
                    }).filter(Boolean);
                }
            } else if (entry && typeof entry === 'object') {
                code = entry.code ? String(entry.code).trim().toUpperCase() : '';
                label = entry.name ? String(entry.name).trim() : '';
                if (Array.isArray(entry.keywords)) {
                    supplementalKeywords = entry.keywords.map(function (keyword) {
                        return String(keyword).trim();
                    }).filter(Boolean);
                }
            }

            if (!code || code.length !== 2) {
                return null;
            }

            var base = 0x1F1E6;
            var first = code.charCodeAt(0);
            var second = code.charCodeAt(1);
            if (first < 65 || first > 90 || second < 65 || second > 90) {
                return null;
            }

            var symbol = String.fromCodePoint(base + (first - 65)) + String.fromCodePoint(base + (second - 65));
            var resolvedLabel = label;
            if (!resolvedLabel && displayNames) {
                try {
                    resolvedLabel = displayNames.of(code) || '';
                } catch (nameError) {
                    resolvedLabel = '';
                }
            }
            if (!resolvedLabel) {
                resolvedLabel = code;
            }

            var keywords = ['drapeau', 'flag', code.toLowerCase()];
            var asciiLabel = normalizeEmojiSearchValue(resolvedLabel);
            if (asciiLabel) {
                asciiLabel.split(' ').forEach(function (part) {
                    if (part && keywords.indexOf(part) === -1) {
                        keywords.push(part);
                    }
                });
            }

            supplementalKeywords.forEach(function (keyword) {
                var value = normalizeEmojiSearchValue(keyword);
                if (!value) {
                    return;
                }
                value.split(' ').forEach(function (chunk) {
                    if (chunk && keywords.indexOf(chunk) === -1) {
                        keywords.push(chunk);
                    }
                });
            });

            return {
                symbol: symbol,
                name: resolvedLabel,
                keywords: keywords,
            };
        }).filter(Boolean);
    }

    var DEFAULT_EMOJI_LIBRARY = (function () {
        var categories = [
            {
                key: 'smileys',
                label: 'Smileys & Emotion',
                block: [
                    "😀|Grinning Face|smile,joie,heureux",
                    "😃|Grinning Face With Big Eyes|smile,joie,enthousiasme",
                    "😄|Grinning Face With Smiling Eyes|smile,joie,beam",
                    "😁|Beaming Face With Smiling Eyes|sourire,heureux,yeux",
                    "😆|Grinning Squinting Face|rire,joie,hilarant",
                    "😅|Grinning Face With Sweat|soulagement,rire,sueur",
                    "😂|Face With Tears Of Joy|rire,joie,mdr",
                    "🤣|Rolling On The Floor Laughing|rire,mdr,folie",
                    "😊|Smiling Face With Smiling Eyes|smile,doux,heureux",
                    "😇|Smiling Face With Halo|ange,gentil,innocent",
                    "🙂|Slightly Smiling Face|smile,leger,cordial",
                    "🙃|Upside Down Face|ironie,humour,retourne",
                    "😉|Winking Face|clin,complice,humour",
                    "😌|Relieved Face|soulagement,calme,zen",
                    "😍|Smiling Face With Hearts|amour,coeur,admirer",
                    "🥰|Smiling Face With Hearts|coeur,amour,tendre",
                    "😘|Face Blowing A Kiss|baiser,coeur,amour",
                    "😗|Kissing Face|baiser,tendre,doux",
                    "😙|Kissing Face With Smiling Eyes|baiser,sourire,tendre",
                    "😚|Kissing Face With Closed Eyes|baiser,affection,doux",
                    "😋|Face Savoring Food|delicieux,gourmand,yummy",
                    "😛|Face With Tongue|blague,fun,grimace",
                    "😜|Winking Face With Tongue|taquin,fun,grimace",
                    "😝|Squinting Face With Tongue|grimace,folie,rire",
                    "🤑|Money Mouth Face|argent,gain,business",
                    "🤗|Smiling Face With Open Hands|calin,accueil,merci",
                    "🤭|Face With Hand Over Mouth|surprise,secret,oh",
                    "🤫|Shushing Face|silence,secret,chut",
                    "🤔|Thinking Face|idee,reflexion,question",
                    "🤨|Face With Raised Eyebrow|sceptique,doute,question",
                    "🧐|Face With Monocle|analyse,inspecter,serieux",
                    "🤓|Nerd Face|geek,lecture,smart",
                    "😎|Smiling Face With Sunglasses|cool,detente,style",
                    "🤩|Star Struck|admiration,etoiles,fan",
                    "🥳|Partying Face|fete,anniversaire,joie",
                    "😏|Smirking Face|satisfait,malice,complice",
                    "😒|Unamused Face|bof,blase,doute",
                    "😞|Disappointed Face|decu,triste,baisse",
                    "😔|Pensive Face|pensif,triste,reflexion",
                    "😟|Worried Face|inquiet,stress,peur",
                    "😕|Confused Face|confus,perdu,question",
                    "🙁|Slightly Frowning Face|triste,mecontent,leger",
                    "☹️|Frowning Face|triste,decu,negatif",
                    "😣|Persevering Face|stress,tension,effort",
                    "😖|Confounded Face|frustration,trouble,stress",
                    "😫|Tired Face|fatigue,epuise,souffle",
                    "😩|Weary Face|fatigue,sature,stress",
                    "🥺|Pleading Face|supplication,silvousplait,coeur",
                    "😢|Crying Face|pleurer,triste,chagrin",
                    "😭|Loudly Crying Face|pleure,fort,triste",
                    "😤|Face With Steam From Nose|determination,colere,effort",
                    "😠|Angry Face|colere,rouge,furieux",
                    "😡|Pouting Face|furieux,colere,gronder",
                    "🤬|Face With Symbols On Mouth|injure,furieux,colere",
                    "🤯|Exploding Head|mindblown,idee,shock",
                    "😳|Flushed Face|gene,surpris,rougir",
                    "🥵|Hot Face|chaleur,coupchaud,ete",
                    "🥶|Cold Face|froid,hiver,glacial",
                    "😱|Face Screaming In Fear|cri,peur,horreur",
                    "😨|Fearful Face|peur,inquiet,crainte",
                    "😰|Anxious Face With Sweat|stress,peur,sueur",
                    "😥|Sad But Relieved Face|soulagement,triste,pleurs",
                    "😓|Downcast Face With Sweat|stress,travail,fatigue",
                    "🤤|Drooling Face|envie,gourmand,desir",
                    "😴|Sleeping Face|sommeil,dodo,fatigue",
                    "😪|Sleepy Face|sommeil,fatigue,baille",
                    "😮|Face With Open Mouth|surprise,choque,ouvert",
                    "😯|Hushed Face|surpris,calme,silence",
                    "😲|Astonished Face|surpris,shock,etonne",
                    "😵|Dizzy Face|vertige,etourdi,tourne",
                    "😵‍💫|Face With Spiral Eyes|vertige,hypnose,etonne",
                    "🤐|Zipper Mouth Face|secret,silence,chut",
                    "🥴|Woozy Face|etourdi,alcool,fatigue",
                    "🤢|Nauseated Face|degout,malade,poison",
                    "🤮|Face Vomiting|malade,gastro,degout",
                    "🤧|Sneezing Face|rhume,allergie,malade",
                    "😷|Face With Medical Mask|masque,malade,sante",
                    "🤒|Face With Thermometer|fievre,malade,sante",
                    "🤕|Face With Head-Bandage|blessure,accident,sante",
                    "🫠|Melting Face|chaleur,gene,fondre",
                    "🫢|Face With Open Eyes And Hand Over Mouth|surpris,secret,shock",
                    "🫣|Face With Peeking Eye|curieux,timide,peur",
                    "🫡|Saluting Face|respect,salut,serieux",
                    "🫥|Dotted Line Face|invisible,timidite,silence",
                    "🫤|Face With Diagonal Mouth|incertain,doute,meh",
                    "😶|Face Without Mouth|silence,mute,secret",
                    "😶‍🌫️|Face In Clouds|reve,flou,meteo",
                    "😐|Neutral Face|neutre,calme,plat",
                    "😑|Expressionless Face|neutre,plat,silence",
                    "😬|Grimacing Face|malais,stress,awkward",
                    "🫨|Shaking Face|tremble,secousse,shock",
                    "🤠|Cowboy Hat Face|western,fun,joie",
                    "😈|Smiling Face With Horns|diable,fete,malice",
                    "👿|Angry Face With Horns|demon,colere,mechant",
                    "👹|Ogre|oni,japon,monstre",
                    "👺|Goblin|tengu,masque,monstre",
                    "💀|Skull|pirate,halloween,danger",
                    "☠️|Skull And Crossbones|danger,toxique,poison",
                    "👻|Ghost|halloween,esprit,boo",
                    "👽|Alien|ovni,extra,space",
                    "👾|Alien Monster|retro,jeu,arcade",
                    "🤖|Robot|tech,futur,bot",
                    "😺|Grinning Cat Face|chat,smile,joie",
                    "😸|Grinning Cat With Smiling Eyes|chat,joie,sourire",
                    "😹|Cat With Tears Of Joy|chat,rire,joie",
                    "😻|Smiling Cat With Heart Eyes|chat,amour,adorable",
                    "😼|Cat With Wry Smile|chat,malicieux,coquin",
                    "😽|Kissing Cat|chat,bisou,affection",
                    "🙀|Weary Cat|chat,shock,peur",
                    "😿|Crying Cat|chat,triste,pleur",
                    "😾|Pouting Cat|chat,colere,mecontent",
                    "💩|Pile Of Poo|blague,humour,mdr",
                    "❤️|Red Heart|coeur,amour,passion",
                    "🩷|Pink Heart|coeur,rose,affection",
                    "🧡|Orange Heart|coeur,amitie,gratitude",
                    "💛|Yellow Heart|coeur,soleil,amitie",
                    "💚|Green Heart|coeur,nature,espoir",
                    "💙|Blue Heart|coeur,confiance,paix",
                    "💜|Purple Heart|coeur,solidarite,creativite",
                    "🖤|Black Heart|coeur,style,goth",
                    "🤍|White Heart|coeur,pur,paix",
                    "🤎|Brown Heart|coeur,chaleur,terre",
                    "💔|Broken Heart|rupture,triste,amour",
                    "❣️|Heart Exclamation|coeur,attention,amour",
                    "💕|Two Hearts|coeur,amour,affection",
                    "💞|Revolving Hearts|coeur,romance,douceur",
                    "💓|Beating Heart|coeur,rythme,amour",
                    "💗|Growing Heart|coeur,progression,joie",
                    "💖|Sparkling Heart|coeur,etincelle,magie",
                    "💘|Heart With Arrow|amour,cupidon,valentin",
                    "💝|Heart With Ribbon|cadeau,coeur,amour",
                    "💟|Heart Decoration|coeur,decoration,style",
                    "💌|Love Letter|lettre,coeur,romance",
                    "💤|Zzz|sommeil,nuits,repos",
                    "💢|Anger Symbol|colere,impact,comic",
                    "💥|Collision|boom,impact,bang",
                    "💦|Sweat Droplets|eau,effort,gouttes",
                    "💨|Dashing Away|vitesse,vent,mouvement",
                    "💫|Dizzy Symbol|etoiles,magie,vertige",
                    "💬|Speech Balloon|message,discussion,chat",
                    "🗨️|Left Speech Bubble|discussion,parole,message",
                    "🗯️|Right Anger Bubble|colere,parole,comic",
                    "💭|Thought Balloon|idee,penser,revasser",
                    "💮|White Flower|reussite,gratitude,merci"
                ].join('\n'),
            },
            {
                key: 'people',
                label: 'People & Body',
                block: [
                    "👋|Waving Hand|salut,bonjour,aurevoir",
                    "🤚|Raised Back Of Hand|salut,stop,main",
                    "🖐️|Hand With Fingers Splayed|main,stop,gestuelle",
                    "✋|Raised Hand|stop,main,attention",
                    "🖖|Vulcan Salute|prosper,longue,vie",
                    "👌|Ok Hand|ok,accord,main",
                    "🤌|Pinched Fingers|italien,precision,question",
                    "🤏|Pinching Hand|petit,dose,gestuelle",
                    "✌️|Victory Hand|victoire,paix,main",
                    "🤞|Crossed Fingers|chance,espoir,main",
                    "🤟|Love-You Gesture|amour,language,main",
                    "🤘|Sign Of The Horns|rock,concert,metal",
                    "🤙|Call Me Hand|telephone,aloha,contact",
                    "👈|Backhand Index Pointing Left|gauche,indiquer,main",
                    "👉|Backhand Index Pointing Right|droite,indiquer,main",
                    "👆|Backhand Index Pointing Up|haut,indiquer,main",
                    "🖕|Middle Finger|grossier,interdit,insulte",
                    "👇|Backhand Index Pointing Down|bas,indiquer,main",
                    "👍|Thumbs Up|ok,validation,like",
                    "👎|Thumbs Down|non,refus,dislike",
                    "✊|Raised Fist|solidarite,poing,force",
                    "👊|Oncoming Fist|poing,impact,check",
                    "🤛|Left-Facing Fist|poing,frappe,amical",
                    "🤜|Right-Facing Fist|poing,frappe,amical",
                    "👏|Clapping Hands|bravo,applaudir,soutien",
                    "🙌|Raising Hands|bravo,joie,victoire",
                    "👐|Open Hands|partage,accueil,main",
                    "🤲|Palms Up Together|priere,offrir,aide",
                    "🤝|Handshake|accord,partenariat,cooperation",
                    "🙏|Folded Hands|merci,priere,respect",
                    "✍️|Writing Hand|ecrire,signature,note",
                    "💅|Nail Polish|beaute,style,manucure",
                    "🤳|Selfie|photo,smartphone,partage",
                    "💪|Flexed Biceps|force,sport,muscle",
                    "🦾|Mechanical Arm|cyborg,robot,force",
                    "🦿|Mechanical Leg|prothese,robot,force",
                    "🦵|Leg|jambe,sport,corps",
                    "🦶|Foot|pied,marche,corps",
                    "👂|Ear|ecoute,son,corps",
                    "👃|Nose|odorat,corps,sante",
                    "🧠|Brain|idee,intelligence,neuro",
                    "🫀|Anatomical Heart|sante,medical,coeur",
                    "🫁|Lungs|respiration,sante,medical",
                    "🦷|Tooth|dentiste,sante,dent",
                    "🦴|Bone|os,squelette,science",
                    "👀|Eyes|voir,regard,attention",
                    "👁️|Eye|vision,regard,oeil",
                    "🧔|Person With Beard|personne,barbe,style",
                    "🧑|Person|neutre,personne,profil",
                    "👶|Baby|bebe,naissance,famille",
                    "🧒|Child|enfant,neutre,jeunesse",
                    "👦|Boy|enfant,garcon,famille",
                    "👧|Girl|enfant,fille,famille",
                    "👩|Woman|adulte,femme,famille",
                    "👨|Man|adulte,homme,famille",
                    "🧑‍🦰|Person With Red Hair|personne,cheveux,roux",
                    "🧑‍🦱|Person With Curly Hair|personne,cheveux,boucles",
                    "🧑‍🦳|Person With White Hair|personne,cheveux,blanc",
                    "🧑‍🦲|Person Bald|personne,cheveux,chauve",
                    "👱‍♀️|Woman Blond Hair|femme,blond,coiffure",
                    "👱‍♂️|Man Blond Hair|homme,blond,coiffure",
                    "👩‍🦰|Woman Red Hair|femme,roux,cheveux",
                    "👨‍🦰|Man Red Hair|homme,roux,cheveux",
                    "👩‍🦱|Woman Curly Hair|femme,boucles,cheveux",
                    "👨‍🦱|Man Curly Hair|homme,boucles,cheveux",
                    "👩‍🦳|Woman White Hair|femme,cheveux,blanc",
                    "👨‍🦳|Man White Hair|homme,cheveux,blanc",
                    "👩‍🦲|Woman Bald|femme,chauve,cheveux",
                    "👨‍🦲|Man Bald|homme,chauve,cheveux",
                    "🧑‍⚕️|Health Worker|medecin,infirmier,sante",
                    "👩‍⚕️|Woman Health Worker|medecin,infirmiere,sante",
                    "👨‍⚕️|Man Health Worker|medecin,infirmier,sante",
                    "🧑‍🎓|Student|etudiant,ecole,formation",
                    "👩‍🎓|Woman Student|etudiante,ecole,formation",
                    "👨‍🎓|Man Student|etudiant,ecole,formation",
                    "🧑‍🏫|Teacher|prof,formation,classe",
                    "👩‍🏫|Woman Teacher|professeur,classe,education",
                    "👨‍🏫|Man Teacher|professeur,classe,education",
                    "🧑‍⚖️|Judge|justice,tribunal,metier",
                    "👩‍⚖️|Woman Judge|justice,tribunal,metier",
                    "👨‍⚖️|Man Judge|justice,tribunal,metier",
                    "🧑‍🌾|Farmer|agriculture,ferme,metier",
                    "👩‍🌾|Woman Farmer|agriculture,ferme,metier",
                    "👨‍🌾|Man Farmer|agriculture,ferme,metier",
                    "🧑‍🍳|Cook|chef,cuisine,metier",
                    "👩‍🍳|Woman Cook|chef,cuisine,metier",
                    "👨‍🍳|Man Cook|chef,cuisine,metier",
                    "🧑‍🔧|Mechanic|reparation,metier,atelier",
                    "👩‍🔧|Woman Mechanic|reparation,metier,atelier",
                    "👨‍🔧|Man Mechanic|reparation,metier,atelier",
                    "🧑‍🏭|Factory Worker|industrie,metier,ouvrier",
                    "👩‍🏭|Woman Factory Worker|industrie,metier,ouvriere",
                    "👨‍🏭|Man Factory Worker|industrie,metier,ouvrier",
                    "🧑‍💼|Office Worker|bureau,metier,corporate",
                    "👩‍💼|Woman Office Worker|bureau,metier,manager",
                    "👨‍💼|Man Office Worker|bureau,metier,manager",
                    "🧑‍🔬|Scientist|science,laboratoire,recherche",
                    "👩‍🔬|Woman Scientist|science,laboratoire,recherche",
                    "👨‍🔬|Man Scientist|science,laboratoire,recherche",
                    "🧑‍💻|Technologist|dev,code,metier",
                    "👩‍💻|Woman Technologist|dev,code,metier",
                    "👨‍💻|Man Technologist|dev,code,metier",
                    "🧑‍🎤|Singer|musique,scene,metier",
                    "👩‍🎤|Woman Singer|musique,scene,metier",
                    "👨‍🎤|Man Singer|musique,scene,metier",
                    "🧑‍🎨|Artist|art,peinture,metier",
                    "👩‍🎨|Woman Artist|art,peinture,metier",
                    "👨‍🎨|Man Artist|art,peinture,metier",
                    "🧑‍✈️|Pilot|avion,metier,voyage",
                    "👩‍✈️|Woman Pilot|avion,metier,voyage",
                    "👨‍✈️|Man Pilot|avion,metier,voyage",
                    "🧑‍🚀|Astronaut|espace,metier,science",
                    "👩‍🚀|Woman Astronaut|espace,metier,science",
                    "👨‍🚀|Man Astronaut|espace,metier,science",
                    "🧑‍🚒|Firefighter|secours,metier,urgence",
                    "👩‍🚒|Woman Firefighter|secours,metier,urgence",
                    "👨‍🚒|Man Firefighter|secours,metier,urgence",
                    "👮|Police Officer|police,securite,metier",
                    "👮‍♀️|Woman Police Officer|police,securite,metier",
                    "👮‍♂️|Man Police Officer|police,securite,metier",
                    "🕵️|Detective|enquete,metier,espion",
                    "🕵️‍♀️|Woman Detective|enquete,metier,espion",
                    "🕵️‍♂️|Man Detective|enquete,metier,espion",
                    "💂|Guard|royaume,securite,metier",
                    "💂‍♀️|Woman Guard|royaume,securite,metier",
                    "💂‍♂️|Man Guard|royaume,securite,metier",
                    "🥷|Ninja|stealth,culture,japon",
                    "👷|Construction Worker|chantier,metier,securite",
                    "👷‍♀️|Woman Construction Worker|chantier,metier,securite",
                    "👷‍♂️|Man Construction Worker|chantier,metier,securite",
                    "🤴|Prince|royal,famille,couronne",
                    "👸|Princess|royal,famille,couronne",
                    "🤵|Person In Tuxedo|mariage,evenement,tenue",
                    "🤵‍♀️|Woman In Tuxedo|mariage,evenement,tenue",
                    "👰|Bride With Veil|mariage,evenement,tenue",
                    "👰‍♂️|Man With Veil|mariage,inclusif,tenue",
                    "👰‍♀️|Woman With Veil|mariage,tradition,tenue",
                    "🤰|Pregnant Woman|grossesse,famille,soin",
                    "🫃|Pregnant Man|grossesse,famille,inclusif",
                    "🫄|Pregnant Person|grossesse,famille,inclusif",
                    "🤱|Breast-Feeding|maternel,soin,bebe",
                    "👩‍🍼|Woman Feeding Baby|bebe,nourrir,soin",
                    "👨‍🍼|Man Feeding Baby|bebe,nourrir,soin",
                    "🧑‍🍼|Person Feeding Baby|bebe,nourrir,soin",
                    "🙇|Person Bowing|respect,reverence,salut",
                    "🙇‍♀️|Woman Bowing|respect,reverence,salut",
                    "🙇‍♂️|Man Bowing|respect,reverence,salut",
                    "💁|Person Tipping Hand|info,accueil,service",
                    "💁‍♀️|Woman Tipping Hand|info,accueil,service",
                    "💁‍♂️|Man Tipping Hand|info,accueil,service",
                    "🙅|Person Gesturing No|refus,non,stop",
                    "🙅‍♀️|Woman Gesturing No|refus,non,stop",
                    "🙅‍♂️|Man Gesturing No|refus,non,stop",
                    "🙆|Person Gesturing Ok|ok,accord,gestuelle",
                    "🙆‍♀️|Woman Gesturing Ok|ok,accord,gestuelle",
                    "🙆‍♂️|Man Gesturing Ok|ok,accord,gestuelle",
                    "🙋|Person Raising Hand|question,participer,main",
                    "🙋‍♀️|Woman Raising Hand|question,participer,main",
                    "🙋‍♂️|Man Raising Hand|question,participer,main",
                    "🧏|Deaf Person|accessibilite,inclusion,langue",
                    "🧏‍♀️|Deaf Woman|accessibilite,inclusion,langue",
                    "🧏‍♂️|Deaf Man|accessibilite,inclusion,langue",
                    "🙍|Person Frowning|triste,decu,visage",
                    "🙍‍♀️|Woman Frowning|triste,decu,visage",
                    "🙍‍♂️|Man Frowning|triste,decu,visage",
                    "🙎|Person Pouting|mecontent,visage,attitude",
                    "🙎‍♀️|Woman Pouting|mecontent,visage,attitude",
                    "🙎‍♂️|Man Pouting|mecontent,visage,attitude",
                    "👪|Family|famille,parents,enfants",
                    "👨‍👩‍👦|Family Man Woman Boy|famille,parents,enfant",
                    "👨‍👩‍👧|Family Man Woman Girl|famille,parents,enfant",
                    "👨‍👩‍👧‍👦|Family Man Woman Girl Boy|famille,parents,enfants",
                    "👨‍👩‍👦‍👦|Family Man Woman Boys|famille,parents,enfants",
                    "👨‍👩‍👧‍👧|Family Man Woman Girls|famille,parents,enfants",
                    "👨‍👨‍👦|Family Men Boy|famille,inclusif,enfant",
                    "👨‍👨‍👧|Family Men Girl|famille,inclusif,enfant",
                    "👨‍👨‍👧‍👦|Family Men Girl Boy|famille,inclusif,enfants",
                    "👨‍👨‍👦‍👦|Family Men Boys|famille,inclusif,enfants",
                    "👨‍👨‍👧‍👧|Family Men Girls|famille,inclusif,enfants",
                    "👩‍👩‍👦|Family Women Boy|famille,inclusif,enfant",
                    "👩‍👩‍👧|Family Women Girl|famille,inclusif,enfant",
                    "👩‍👩‍👧‍👦|Family Women Girl Boy|famille,inclusif,enfants",
                    "👩‍👩‍👦‍👦|Family Women Boys|famille,inclusif,enfants",
                    "👩‍👩‍👧‍👧|Family Women Girls|famille,inclusif,enfants",
                    "👨‍👦|Family Man Boy|famille,parent,enfant",
                    "👨‍👦‍👦|Family Man Boys|famille,parent,enfants",
                    "👨‍👧|Family Man Girl|famille,parent,enfant",
                    "👨‍👧‍👦|Family Man Girl Boy|famille,parent,enfants",
                    "👨‍👧‍👧|Family Man Girls|famille,parent,enfants",
                    "👩‍👦|Family Woman Boy|famille,parent,enfant",
                    "👩‍👦‍👦|Family Woman Boys|famille,parent,enfants",
                    "👩‍👧|Family Woman Girl|famille,parent,enfant",
                    "👩‍👧‍👦|Family Woman Girl Boy|famille,parent,enfants",
                    "👩‍👧‍👧|Family Woman Girls|famille,parent,enfants",
                    "🧑‍🤝‍🧑|People Holding Hands|amitie,groupe,inclusif",
                    "👭|Women Holding Hands|amitie,groupe,femmes",
                    "👫|Woman And Man Holding Hands|amitie,couple,marche",
                    "👬|Men Holding Hands|amitie,groupe,hommes",
                    "💑|Couple With Heart|amour,couple,romance",
                    "👩‍❤️‍👨|Couple Woman Man Heart|amour,couple,hetero",
                    "👩‍❤️‍👩|Couple Women Heart|amour,couple,femmes",
                    "👨‍❤️‍👨|Couple Men Heart|amour,couple,hommes",
                    "💏|Kiss|baiser,couple,romance",
                    "👩‍❤️‍💋‍👨|Kiss Woman Man|baiser,couple,hetero",
                    "👩‍❤️‍💋‍👩|Kiss Women|baiser,couple,femmes",
                    "👨‍❤️‍💋‍👨|Kiss Men|baiser,couple,hommes",
                    "💃|Woman Dancing|danse,soiree,fete",
                    "🕺|Man Dancing|danse,soiree,fete",
                    "🪩|Mirror Ball|danse,disco,soirée",
                    "🕴️|Person In Suit Levitating|cool,retro,danse"
                ].join('\n'),
            },
            {
                key: 'animals',
                label: 'Animals & Nature',
                block: [
                    "🐵|Monkey Face|singe,animal,jungle",
                    "🐒|Monkey|singe,animal,foret",
                    "🦍|Gorilla|gorille,animal,foret",
                    "🦧|Orangutan|orangutan,animal,foret",
                    "🐶|Dog Face|chien,animal,compagnon",
                    "🐕|Dog|chien,animal,compagnon",
                    "🦮|Guide Dog|chien,guide,assistance",
                    "🐕‍🦺|Service Dog|chien,service,assistance",
                    "🐩|Poodle|chien,toilettage,caniche",
                    "🐺|Wolf|loup,animal,sauvage",
                    "🦊|Fox|renard,animal,sauvage",
                    "🦝|Raccoon|raton,animal,nuit",
                    "🐱|Cat Face|chat,animal,compagnon",
                    "🐈|Cat|chat,animal,domestique",
                    "🐈‍⬛|Black Cat|chat,noir,animal",
                    "🦁|Lion|lion,animal,savane",
                    "🐯|Tiger Face|tigre,animal,sauvage",
                    "🐅|Tiger|tigre,animal,foret",
                    "🐆|Leopard|leopard,animal,safari",
                    "🐴|Horse Face|cheval,animal,ferme",
                    "🐎|Horse|cheval,animal,course",
                    "🦄|Unicorn|licorne,animal,magie",
                    "🫎|Moose|elan,animal,foret",
                    "🦓|Zebra|zebre,animal,savane",
                    "🦌|Deer|cerf,animal,foret",
                    "🦬|Bison|bison,animal,plaine",
                    "🐮|Cow Face|vache,animal,ferme",
                    "🐂|Ox|boeuf,animal,travail",
                    "🐃|Water Buffalo|buffle,animal,ferme",
                    "🐄|Cow|vache,animal,lait",
                    "🐷|Pig Face|cochon,animal,ferme",
                    "🐖|Pig|cochon,animal,ferme",
                    "🐗|Boar|sanglier,animal,foret",
                    "🐽|Pig Nose|cochon,animal,nez",
                    "🐏|Ram|belier,animal,ferme",
                    "🐑|Ewe|brebis,animal,laine",
                    "🐐|Goat|chevre,animal,ferme",
                    "🐪|Camel|chameau,animal,desert",
                    "🐫|Two-Hump Camel|chameau,desert,voyage",
                    "🦙|Llama|lama,animal,montagne",
                    "🦒|Giraffe|girafe,animal,savane",
                    "🐘|Elephant|elephant,animal,safari",
                    "🦣|Mammoth|mammouth,prehistoire,animal",
                    "🦏|Rhinoceros|rhino,animal,safari",
                    "🦛|Hippopotamus|hippopotame,animal,river",
                    "🐭|Mouse Face|souris,animal,petit",
                    "🐁|Mouse|souris,animal,petit",
                    "🐀|Rat|rat,animal,ville",
                    "🐹|Hamster|hamster,animal,compagnie",
                    "🐰|Rabbit Face|lapin,animal,paques",
                    "🐇|Rabbit|lapin,animal,rapide",
                    "🐿️|Chipmunk|tamia,animal,foret",
                    "🦫|Beaver|castor,animal,barrage",
                    "🦔|Hedgehog|herisson,animal,forest",
                    "🦇|Bat|chauvesouris,animal,nuit",
                    "🐻|Bear|ours,animal,foret",
                    "🐻‍❄️|Polar Bear|ours,glace,arctique",
                    "🐨|Koala|koala,animal,australie",
                    "🐼|Panda|panda,animal,bambou",
                    "🦥|Sloth|paresseux,animal,foret",
                    "🦦|Otter|loutre,animal,riviere",
                    "🦨|Skunk|mouffette,animal,odeur",
                    "🦘|Kangaroo|kangourou,animal,australie",
                    "🦡|Badger|blaireau,animal,foret",
                    "🦃|Turkey|dinde,animal,ferme",
                    "🐔|Chicken|poulet,animal,ferme",
                    "🐓|Rooster|coq,animal,ferme",
                    "🐣|Hatching Chick|poussin,naissance,animal",
                    "🐤|Chick|poussin,animal,ferme",
                    "🐥|Front-Facing Chick|poussin,animal,jaune",
                    "🐦|Bird|oiseau,animal,vol",
                    "🐧|Penguin|manchot,animal,antarctique",
                    "🕊️|Dove|colombe,paix,animal",
                    "🦅|Eagle|aigle,animal,rapace",
                    "🦆|Duck|canard,animal,ferme",
                    "🦢|Swan|cygne,animal,grace",
                    "🦉|Owl|hibou,animal,nuit",
                    "🦤|Dodo|dodo,animal,disparu",
                    "🦩|Flamingo|flamant,animal,rose",
                    "🦚|Peacock|paon,animal,plumes",
                    "🦜|Parrot|perroquet,animal,tropical",
                    "🪿|Goose|oie,animal,ferme",
                    "🪺|Nest With Eggs|nid,oiseau,oeufs",
                    "🐸|Frog|grenouille,animal,marais",
                    "🐊|Crocodile|crocodile,animal,riviere",
                    "🐢|Turtle|tortue,animal,ocean",
                    "🦎|Lizard|lezard,animal,desert",
                    "🐍|Snake|serpent,animal,foret",
                    "🐲|Dragon Face|dragon,mythe,asie",
                    "🐉|Dragon|dragon,mythe,asie",
                    "🦕|Sauropod|dinosaure,prehistoire,long",
                    "🦖|T-Rex|dinosaure,prehistoire,tyrannosaure",
                    "🐳|Spouting Whale|baleine,animal,ocean",
                    "🐋|Whale|baleine,animal,mer",
                    "🐬|Dolphin|dauphin,animal,mer",
                    "🦭|Seal|phoque,animal,mer",
                    "🐟|Fish|poisson,animal,mer",
                    "🐠|Tropical Fish|poisson,animal,tropical",
                    "🐡|Blowfish|poisson,animal,gonfle",
                    "🦈|Shark|requin,animal,mer",
                    "🐙|Octopus|pieuvre,animal,mer",
                    "🦑|Squid|calamar,animal,mer",
                    "🦐|Shrimp|crevette,animal,mer",
                    "🦞|Lobster|homard,animal,mer",
                    "🦀|Crab|crabe,animal,mer",
                    "🐚|Spiral Shell|coquillage,plage,mer",
                    "🪸|Coral|corail,mer,reef",
                    "🪼|Jellyfish|meduse,mer,animal",
                    "🐌|Snail|escargot,animal,pluie",
                    "🦋|Butterfly|papillon,animal,jardin",
                    "🐛|Bug|insecte,animal,foret",
                    "🐜|Ant|fourmi,insecte,colonie",
                    "🐝|Honeybee|abeille,insecte,miel",
                    "🪲|Beetle|scarabee,insecte,foret",
                    "🐞|Lady Beetle|coccinelle,insecte,jardin",
                    "🦗|Cricket|criquet,insecte,chanson",
                    "🪳|Cockroach|cafard,insecte,maison",
                    "🦟|Mosquito|moustique,insecte,piqure",
                    "🪰|Fly|mouche,insecte,ete",
                    "🪱|Worm|ver,insecte,sol",
                    "🦠|Microbe|microbe,germes,sante",
                    "🌵|Cactus|desert,plante,nature",
                    "🎄|Christmas Tree|sapin,arbre,hiver",
                    "🌲|Evergreen Tree|sapin,arbre,foret",
                    "🌳|Deciduous Tree|arbre,nature,foret",
                    "🌴|Palm Tree|palme,plage,tropical",
                    "🌱|Seedling|germe,plante,nature",
                    "🌿|Herb|plante,nature,arome",
                    "☘️|Shamrock|trefle,plante,chance",
                    "🍀|Four Leaf Clover|trefle,chance,plante",
                    "🎍|Pine Decoration|bambou,nouvelan,plante",
                    "🪴|Potted Plant|plante,interieur,decor",
                    "🍁|Maple Leaf|feuille,automne,nature",
                    "🍂|Fallen Leaf|feuille,automne,foret",
                    "🍃|Leaf Fluttering|feuille,vent,nature",
                    "🍄|Mushroom|champignon,foret,plante",
                    "🌰|Chestnut|chataigne,automne,foret",
                    "🪵|Wood|bois,foret,matiere",
                    "🪹|Empty Nest|nid,vide,oiseau",
                    "☀️|Sun|soleil,meteo,jour",
                    "🌤️|Sun Behind Small Cloud|meteo,soleil,nuage",
                    "⛅|Sun Behind Cloud|meteo,nuage,jour",
                    "🌥️|Sun Behind Large Cloud|meteo,nuage,jour",
                    "☁️|Cloud|meteo,nuage,temps",
                    "🌦️|Sun Behind Rain Cloud|pluie,meteo,soleil",
                    "🌧️|Cloud With Rain|pluie,meteo,temps",
                    "⛈️|Cloud With Lightning And Rain|orage,meteo,pluie",
                    "🌩️|Cloud With Lightning|orage,meteo,eclair",
                    "🌨️|Cloud With Snow|neige,meteo,hiver",
                    "❄️|Snowflake|neige,hiver,meteo",
                    "☃️|Snowman With Snow|neige,hiver,bonhomme",
                    "⛄|Snowman|neige,hiver,bonhomme",
                    "🌬️|Wind Face|vent,meteo,hiver",
                    "🌪️|Tornado|tornade,meteo,tempete",
                    "🌫️|Fog|brouillard,meteo",
                    "🌈|Rainbow|arcenciel,meteo,nature",
                    "🌂|Closed Umbrella|parapluie,pluie,accessoire",
                    "☂️|Umbrella|pluie,meteo,accessoire",
                    "☔|Umbrella With Rain|pluie,meteo,nature",
                    "⚡|High Voltage|eclair,meteo,energie",
                    "🌊|Water Wave|vague,mer,nature",
                    "🔥|Fire|feu,energie,chaleur",
                    "💧|Droplet|eau,goutte,meteo",
                    "🌙|Crescent Moon|lune,nuit,meteo",
                    "🌕|Full Moon|lune,nuit,pleine",
                    "🌑|New Moon|lune,nuit,cycle",
                    "🌟|Glowing Star|etoile,nuit,magie",
                    "⭐|Star|etoile,nuit,magie",
                    "🌠|Shooting Star|etoile,fugitive,voeu",
                    "🌌|Milky Way|galaxie,espace,nuit",
                    "🛸|Flying Saucer|ovni,espace,alien"
                ].join('\n'),
            },
            {
                key: 'food',
                label: 'Food & Drink',
                block: [
                    "🍏|Green Apple|fruit,pomme,vert",
                    "🍎|Red Apple|fruit,pomme,sante",
                    "🍐|Pear|fruit,poire,vert",
                    "🍊|Tangerine|fruit,orange,vitamine",
                    "🍋|Lemon|fruit,citron,acide",
                    "🍌|Banana|fruit,banane,energie",
                    "🍉|Watermelon|fruit,pasteque,ete",
                    "🍇|Grapes|fruit,raisin,degustation",
                    "🍓|Strawberry|fruit,fraise,ete",
                    "🫐|Blueberries|fruit,myrtille,antioxydant",
                    "🍈|Melon|fruit,melon,ete",
                    "🍒|Cherries|fruit,cerise,ete",
                    "🍑|Peach|fruit,peche,rose",
                    "🥭|Mango|fruit,mangue,tropical",
                    "🍍|Pineapple|fruit,ananas,tropical",
                    "🥥|Coconut|fruit,noixcoco,tropical",
                    "🥝|Kiwi|fruit,kiwi,vitamine",
                    "🍅|Tomato|legume,tomate,cuisine",
                    "🍆|Eggplant|legume,aubergine,cuisine",
                    "🥑|Avocado|legume,avocat,brunch",
                    "🥦|Broccoli|legume,brocoli,vert",
                    "🥬|Leafy Green|legume,vert,sante",
                    "🥒|Cucumber|legume,concombre,salade",
                    "🌶️|Hot Pepper|piment,epice,rouge",
                    "🌽|Ear Of Corn|mais,legume,grille",
                    "🥕|Carrot|legume,carotte,orange",
                    "🧄|Garlic|ail,epice,cuisine",
                    "🧅|Onion|oignon,legume,cuisine",
                    "🥔|Potato|legume,pomme,terre",
                    "🍠|Roasted Sweet Potato|patate,douce,legume",
                    "🥐|Croissant|viennoiserie,patisserie,france",
                    "🥯|Bagel|pain,bagel,petitdejeuner",
                    "🍞|Bread|pain,boulangerie,aliment",
                    "🥖|Baguette Bread|baguette,pain,france",
                    "🥨|Pretzel|bretzel,sale,aperitif",
                    "🧀|Cheese Wedge|fromage,plateau,aliment",
                    "🥚|Egg|oeuf,proteine,cuisine",
                    "🍳|Cooking|poele,oeuf,cuisine",
                    "🧈|Butter|beurre,cuisine,toast",
                    "🥞|Pancakes|crepes,dejeuner,sirop",
                    "🧇|Waffle|gaufre,petitdejeuner,sirop",
                    "🥓|Bacon|bacon,petitdejeuner,proteine",
                    "🥩|Cut Of Meat|viande,steak,protein",
                    "🍗|Poultry Leg|poulet,viande,repas",
                    "🍖|Meat On Bone|viande,grill,barbecue",
                    "🌭|Hot Dog|sandwich,fastfood,barbecue",
                    "🍔|Hamburger|burger,repas,fastfood",
                    "🍟|French Fries|frites,fastfood,repas",
                    "🍕|Pizza|pizza,italie,repas",
                    "🫓|Flatbread|pain,galette,cuisine",
                    "🥪|Sandwich|sandwich,dejeuner,rapide",
                    "🥙|Stuffed Flatbread|kebab,wrap,repas",
                    "🧆|Falafel|falafel,vegetarien,repas",
                    "🌮|Taco|taco,mexique,repas",
                    "🌯|Burrito|burrito,mexique,repas",
                    "🫔|Tamale|tamale,mexique,repas",
                    "🥗|Green Salad|salade,vegetal,repas",
                    "🥘|Shallow Pan Of Food|paella,plat,partage",
                    "🫕|Fondue|fondue,fromage,convivial",
                    "🥫|Canned Food|conserve,repas,stock",
                    "🍝|Spaghetti|pates,italie,repas",
                    "🍜|Steaming Bowl|ramen,soupe,bol",
                    "🍲|Pot Of Food|soupe,ragoût,repas",
                    "🍛|Curry Rice|curry,riz,repas",
                    "🍣|Sushi|sushi,japon,repas",
                    "🍱|Bento Box|bento,japon,repas",
                    "🥟|Dumpling|ravioli,asie,repas",
                    "🍤|Fried Shrimp|crevette,tempura,frite",
                    "🍙|Rice Ball|onigiri,riz,japon",
                    "🍚|Cooked Rice|riz,repas,bol",
                    "🍘|Rice Cracker|galette,riz,snack",
                    "🍢|Oden|brochette,asie,repas",
                    "🍡|Dango|mochi,brochette,dessert",
                    "🍧|Shaved Ice|glace,ete,dessert",
                    "🍨|Ice Cream|glace,creme,dessert",
                    "🍦|Soft Ice Cream|glace,soft,dessert",
                    "🥧|Pie|tarte,dessert,partage",
                    "🧁|Cupcake|cupcake,dessert,patisserie",
                    "🍰|Shortcake|gateau,fraise,dessert",
                    "🎂|Birthday Cake|gateau,anniversaire,fete",
                    "🍮|Custard|flan,creme,dessert",
                    "🍭|Lollipop|bonbon,sucre,gouter",
                    "🍬|Candy|bonbon,sucre,douceur",
                    "🍫|Chocolate Bar|chocolat,douceur,dessert",
                    "🍿|Popcorn|popcorn,cinema,grignoter",
                    "🧋|Bubble Tea|bubble,the,boisson",
                    "🧃|Beverage Box|jus,boisson,portable",
                    "🧉|Mate|mate,boisson,energie",
                    "🧊|Ice Cube|glacons,froid,boisson",
                    "🥤|Cup With Straw|boisson,soda,frais",
                    "🥛|Glass Of Milk|lait,boisson,calcium",
                    "🫗|Pouring Liquid|versement,boisson,buvette",
                    "☕|Hot Beverage|cafe,the,chauffe",
                    "🫖|Teapot|the,service,boisson",
                    "🍵|Teacup Without Handle|the,matcha,boisson",
                    "🍶|Sake|sake,japon,alcool",
                    "🍺|Beer Mug|biere,alcool,cheers",
                    "🍻|Clinking Beer Mugs|biere,cheers,amis",
                    "🥂|Clinking Glasses|toast,celebration,champagne",
                    "🍷|Wine Glass|vin,alcool,degustation",
                    "🥃|Tumbler Glass|whisky,alcool,spiritueux",
                    "🍸|Cocktail Glass|cocktail,soiree,boisson",
                    "🍹|Tropical Drink|cocktail,tropical,vacances",
                    "🍾|Bottle With Popping Cork|champagne,celebration,fete",
                    "🍽️|Fork And Knife With Plate|repas,table,diner",
                    "🍴|Fork And Knife|couverts,repas,table",
                    "🥢|Chopsticks|baguettes,asie,repas",
                    "🧂|Salt|sel,assaisonnement,cuisine"
                ].join('\n'),
            },
            {
                key: 'travel',
                label: 'Travel & Places',
                block: [
                    "🗺️|World Map|carte,voyage,plan",
                    "🧭|Compass|boussole,orientation,aventure",
                    "🧳|Luggage|bagage,voyage,valise",
                    "🪪|Identification Card|identite,document,voyage",
                    "🛢️|Oil Drum|baril,industrie,transport",
                    "🚗|Automobile|voiture,voyage,route",
                    "🚕|Taxi|taxi,transport,ville",
                    "🚙|Sport Utility Vehicle|voiture,suv,route",
                    "🚌|Bus|bus,transport,public",
                    "🚎|Trolleybus|trolley,transport,public",
                    "🏎️|Racing Car|course,voiture,vitesse",
                    "🚓|Police Car|police,voiture,urgence",
                    "🚑|Ambulance|ambulance,urgence,sante",
                    "🚒|Fire Engine|pompiers,urgence,camion",
                    "🚐|Minibus|minibus,transport,groupe",
                    "🛻|Pickup Truck|pickup,transport,charge",
                    "🚚|Delivery Truck|livraison,transport,camion",
                    "🚛|Articulated Lorry|semi,transport,camion",
                    "🚜|Tractor|tracteur,agri,champ",
                    "🦽|Manual Wheelchair|mobilite,accessibilite,deplacement",
                    "🦼|Motorized Wheelchair|mobilite,accessibilite,vehicule",
                    "🛴|Kick Scooter|trottinette,urbain,transport",
                    "🛹|Skateboard|skate,urbain,glisse",
                    "🛼|Roller Skate|roller,patin,glisse",
                    "🚲|Bicycle|velo,transport,urbain",
                    "🛵|Motor Scooter|scooter,urbain,transport",
                    "🛺|Auto Rickshaw|tuktuk,transport,asie",
                    "🏍️|Motorcycle|moto,transport,vitesse",
                    "🚨|Police Car Light|alerte,urgence,signal",
                    "🚥|Horizontal Traffic Light|signalisation,route,feu",
                    "🚦|Vertical Traffic Light|signalisation,route,feu",
                    "🛣️|Motorway|autoroute,route,transport",
                    "🛤️|Railway Track|rail,transport,train",
                    "🅿️|Parking|parking,voiture,stationnement",
                    "🛑|Stop Sign|stop,signal,route",
                    "⛽|Fuel Pump|essence,station,carburant",
                    "🚧|Construction|travaux,route,securite",
                    "⚓|Anchor|bateau,port,maritime",
                    "⛵|Sailboat|voilier,mer,navigation",
                    "🛶|Canoe|canoe,pleinair,eau",
                    "🚤|Speedboat|bateau,vitesse,mer",
                    "🛥️|Motor Boat|bateau,plaisance,mer",
                    "🛳️|Passenger Ship|croisiere,mer,voyage",
                    "⛴️|Ferry|ferry,transport,mer",
                    "🚢|Ship|navire,mer,voyage",
                    "✈️|Airplane|avion,voyage,aerien",
                    "🛩️|Small Airplane|avion,leger,voyage",
                    "🛫|Airplane Departure|depart,avion,aeroport",
                    "🛬|Airplane Arrival|arrivee,avion,aeroport",
                    "🛸|Flying Saucer|ovni,espace,voyage",
                    "🚁|Helicopter|helico,transport,aerien",
                    "🚀|Rocket|fusée,espace,lancement",
                    "🛰️|Satellite|satellite,espace,communication",
                    "🛎️|Bellhop Bell|hotel,reception,service",
                    "🧺|Basket|pique-nique,panier,sortie",
                    "🏧|ATM Sign|banque,argent,retrait",
                    "🏠|House|maison,logement,domicile",
                    "🏡|House With Garden|maison,jardin,famille",
                    "🏘️|Houses|quartier,maisons,voisin",
                    "🏚️|Derelict House|maison,abandon,renovation",
                    "🏢|Office Building|bureau,immeuble,travail",
                    "🏣|Japanese Post Office|poste,japon,service",
                    "🏤|Post Office|poste,service,public",
                    "🏥|Hospital|hopital,sante,medical",
                    "🏦|Bank|banque,finance,argent",
                    "🏨|Hotel|hotel,sejour,voyage",
                    "🏩|Love Hotel|hotel,romance,sejour",
                    "🏪|Convenience Store|boutique,magasin,nuit",
                    "🏫|School|ecole,education,apprentissage",
                    "🏬|Department Store|magasin,centre,shopping",
                    "🏭|Factory|usine,industrie,production",
                    "🏯|Japanese Castle|chateau,japon,histoire",
                    "🏰|Castle|chateau,histoire,tourisme",
                    "💒|Wedding|mariage,chapelle,evenement",
                    "🗼|Tokyo Tower|tour,tokyo,monument",
                    "🗽|Statue Of Liberty|statue,newyork,monument",
                    "🗿|Moai|moai,ile,monument",
                    "🕌|Mosque|mosquee,lueur,culte",
                    "🕍|Synagogue|synagogue,culte,histoire",
                    "⛪|Church|eglise,culte,histoire",
                    "🛕|Hindu Temple|temple,hinde,culte",
                    "🕋|Kaaba|kaaba,culte,pelerinage",
                    "⛩️|Shinto Shrine|temple,japon,culte",
                    "🗾|Map Of Japan|japon,carte,geo",
                    "🎢|Roller Coaster|parc,attraction,loisir",
                    "🎡|Ferris Wheel|parc,manège,loisir",
                    "🎠|Carousel Horse|manège,parc,enfant",
                    "⛲|Fountain|fontaine,parc,ville",
                    "⛺|Tent|camping,nature,pleinair",
                    "🏕️|Camping|camping,nuit,nature",
                    "🏖️|Beach With Umbrella|plage,vacances,soleil",
                    "🏜️|Desert|desert,sable,voyage",
                    "🏝️|Desert Island|ile,plage,vacances",
                    "🏞️|National Park|parc,nature,randonnee",
                    "🏟️|Stadium|stade,sport,evenement",
                    "🏛️|Classical Building|batiment,histoire,musee",
                    "🏗️|Building Construction|construction,chantier,travaux",
                    "🧱|Brick|brique,materiaux,chantier",
                    "🪨|Rock|roche,nature,decor",
                    "🪵|Wood|bois,materiaux,construction",
                    "🛖|Hut|hutte,tradition,village",
                    "🌋|Volcano|volcan,nature,eruption",
                    "🏔️|Snow-Capped Mountain|montagne,neige,alpin",
                    "⛰️|Mountain|montagne,nature,randonnee",
                    "🗻|Mount Fuji|montfuji,japon,monument",
                    "🕰️|Mantelpiece Clock|horloge,temps,salon",
                    "🕑|Clock Two|horloge,heure,temps",
                    "🪂|Parachute|parachute,saut,air",
                    "🎑|Moon Viewing Ceremony|fete,lune,japon",
                    "🎆|Fireworks|feu,artifice,fete",
                    "🎇|Sparkler|etincelle,celebration,fete",
                    "🏮|Red Paper Lantern|lanterne,asie,fete",
                    "🪔|Diya Lamp|diwali,lumiere,fete",
                    "🕗|Clock|temps,heure,rendezvous"
                ].join('\n'),
            },
            {
                key: 'activities',
                label: 'Activities & Leisure',
                block: [
                    "⚽|Soccer Ball|football,sport,match",
                    "🏀|Basketball|basket,sport,equipe",
                    "🏈|American Football|football,americano,sport",
                    "⚾|Baseball|baseball,sport,match",
                    "🥎|Softball|softball,sport,lancer",
                    "🎾|Tennis|tennis,sport,raquette",
                    "🏐|Volleyball|volley,sport,plage",
                    "🏉|Rugby Football|rugby,sport,equipe",
                    "🥏|Flying Disc|frisbee,sport,pleinair",
                    "🎱|Pool 8 Ball|billard,jeu,salon",
                    "🪀|Yo-Yo|yoyo,jeu,retro",
                    "🏓|Ping Pong|pingpong,sport,raquette",
                    "🏸|Badminton|badminton,sport,raquette",
                    "🥊|Boxing Glove|boxe,sport,combat",
                    "🥋|Martial Arts Uniform|karate,judo,artmartial",
                    "🥅|Goal Net|but,sport,match",
                    "⛳|Flag In Hole|golf,sport,green",
                    "⛸️|Ice Skate|patinage,hiver,sport",
                    "🎿|Skis|ski,hiver,montagne",
                    "🛷|Sled|luge,hiver,neige",
                    "🥌|Curling Stone|curling,hiver,neige",
                    "🏂|Snowboarder|snowboard,hiver,glisse",
                    "🏄|Surfer|surf,mer,glisse",
                    "🏄‍♀️|Woman Surfing|surf,femme,glisse",
                    "🏄‍♂️|Man Surfing|surf,homme,glisse",
                    "🏊|Swimmer|natation,sport,piscine",
                    "🏊‍♀️|Woman Swimming|natation,femme,piscine",
                    "🏊‍♂️|Man Swimming|natation,homme,piscine",
                    "🚣|Person Rowing Boat|aviron,sport,bateau",
                    "🚣‍♀️|Woman Rowing Boat|aviron,femme,bateau",
                    "🚣‍♂️|Man Rowing Boat|aviron,homme,bateau",
                    "🚴|Person Biking|cyclisme,sport,velo",
                    "🚴‍♀️|Woman Biking|cyclisme,femme,velo",
                    "🚴‍♂️|Man Biking|cyclisme,homme,velo",
                    "🚵|Mountain Biking|vtt,sport,montagne",
                    "🚵‍♀️|Woman Mountain Biking|vtt,femme,montagne",
                    "🚵‍♂️|Man Mountain Biking|vtt,homme,montagne",
                    "🤼|People Wrestling|lutte,sport,combat",
                    "🤼‍♀️|Women Wrestling|lutte,femme,combat",
                    "🤼‍♂️|Men Wrestling|lutte,homme,combat",
                    "🤸|Person Cartwheeling|gymnastique,sport,acro",
                    "🤸‍♀️|Woman Cartwheeling|gymnastique,femme,acro",
                    "🤸‍♂️|Man Cartwheeling|gymnastique,homme,acro",
                    "🤺|Person Fencing|escrime,sport,combat",
                    "🤾|Person Playing Handball|handball,sport,match",
                    "🤾‍♀️|Woman Playing Handball|handball,femme,sport",
                    "🤾‍♂️|Man Playing Handball|handball,homme,sport",
                    "🤽|Person Playing Water Polo|waterpolo,sport,piscine",
                    "🤽‍♀️|Woman Playing Water Polo|waterpolo,femme,sport",
                    "🤽‍♂️|Man Playing Water Polo|waterpolo,homme,sport",
                    "🏋️|Person Lifting Weights|musculation,sport,force",
                    "🏋️‍♀️|Woman Lifting Weights|musculation,femme,force",
                    "🏋️‍♂️|Man Lifting Weights|musculation,homme,force",
                    "🧘|Person In Lotus Position|yoga,zen,meditation",
                    "🧘‍♀️|Woman In Lotus Position|yoga,femme,zen",
                    "🧘‍♂️|Man In Lotus Position|yoga,homme,zen",
                    "🏌️|Person Golfing|golf,sport,green",
                    "🏌️‍♀️|Woman Golfing|golf,femme,swing",
                    "🏌️‍♂️|Man Golfing|golf,homme,swing",
                    "🏇|Horse Racing|cheval,course,hippodrome",
                    "🤹|Person Juggling|jonglage,cirque,loisir",
                    "🤹‍♀️|Woman Juggling|jonglage,femme,cirque",
                    "🤹‍♂️|Man Juggling|jonglage,homme,cirque",
                    "🧗|Person Climbing|escalade,sport,montagne",
                    "🧗‍♀️|Woman Climbing|escalade,femme,montagne",
                    "🧗‍♂️|Man Climbing|escalade,homme,montagne",
                    "🧖|Person In Steamy Room|spa,bain,detente",
                    "🧖‍♀️|Woman In Steamy Room|sauna,femme,detente",
                    "🧖‍♂️|Man In Steamy Room|sauna,homme,detente",
                    "🏆|Trophy|trophee,victoire,prix",
                    "🥇|1st Place Medal|or,victoire,prix",
                    "🥈|2nd Place Medal|argent,victoire,prix",
                    "🥉|3rd Place Medal|bronze,victoire,prix",
                    "🏅|Sports Medal|medaille,sport,prix",
                    "🎖️|Military Medal|medaille,honneur,distinction",
                    "🎗️|Reminder Ribbon|ruban,soutien,cause",
                    "🎫|Ticket|billet,entree,evenement",
                    "🎟️|Admission Tickets|billets,evenement,concert",
                    "🎪|Circus Tent|cirque,spectacle,loisir",
                    "🎭|Performing Arts|theatre,scene,culture",
                    "🎨|Artist Palette|art,peinture,couleurs",
                    "🖌️|Paintbrush|peinture,outil,atelier",
                    "🖍️|Crayon|dessin,couleur,atelier",
                    "🎼|Musical Score|musique,partition,lecture",
                    "🎧|Headphone|musique,son,ecoute",
                    "🎷|Saxophone|musique,jazz,instrument",
                    "🎺|Trumpet|trompette,musique,fanfar",
                    "🎸|Guitar|guitare,musique,scene",
                    "🎻|Violin|violon,musique,classique",
                    "🥁|Drum|batterie,musique,rythme",
                    "🎹|Musical Keyboard|piano,clavier,musique",
                    "🎤|Microphone|micro,scene,chante",
                    "🎙️|Studio Microphone|studio,enregistrement,son",
                    "🎚️|Level Slider|audio,mixage,studio",
                    "🎛️|Control Knobs|audio,mixage,studio",
                    "🎬|Clapper Board|cinema,tournage,film",
                    "🎥|Movie Camera|cinema,video,tournage",
                    "🎦|Cinema|projecteur,film,salle",
                    "📽️|Film Projector|projecteur,retro,cinema",
                    "📹|Video Camera|camera,video,tournage",
                    "📸|Camera With Flash|photo,lumiere,shoot",
                    "📷|Camera|photo,image,appareil",
                    "🎞️|Film Frames|film,bobine,retros",
                    "🧩|Puzzle Piece|puzzle,jeu,logique",
                    "🎮|Video Game|gaming,console,loisir",
                    "🕹️|Joystick|gaming,retro,arcade",
                    "🎰|Slot Machine|casino,jeu,hasard",
                    "🎲|Game Die|jeu,societe,hasard",
                    "♟️|Chess Pawn|echec,jeu,strategie",
                    "🧿|Nazar Amulet|protection,porte,bonheur",
                    "🎯|Direct Hit|cible,jeu,precision",
                    "🎳|Bowling|bowling,loisir,piste",
                    "🎣|Fishing Pole|peche,loisir,nature",
                    "🪁|Kite|cerfvolant,pleinair,jeu",
                    "🪃|Boomerang|boomerang,jeu,retour",
                    "🪢|Knot|noeud,corde,scout",
                    "🪣|Bucket|seau,loisir,plage",
                    "🪤|Mouse Trap|piège,jeu,humour",
                    "🪘|Long Drum|musique,tam-tam,rythme",
                    "🪗|Accordion|musique,accordeon,folklore",
                    "🪇|Maracas|musique,maracas,rythme",
                    "🪈|Flute|musique,flute,instrument"
                ].join('\n'),
            },
            {
                key: 'objects',
                label: 'Objects & Gear',
                block: [
                    "⌚|Watch|montre,temps,accessoire",
                    "⏰|Alarm Clock|reveil,alarme,matin",
                    "⏱️|Stopwatch|chrono,temps,sport",
                    "⏲️|Timer Clock|minuteur,temps,cuisine",
                    "⌛|Hourglass Done|sablier,temps,attente",
                    "⏳|Hourglass Not Done|sablier,attente,progression",
                    "📶|Antenna Bars|signal,reseau,connexion",
                    "📱|Mobile Phone|telephone,smartphone,appareil",
                    "📲|Mobile Phone With Arrow|telephone,envoi,partage",
                    "☎️|Telephone|telephone,fixe,appel",
                    "📞|Telephone Receiver|telephone,appel,contact",
                    "📟|Pager|pager,retro,tech",
                    "📠|Fax Machine|fax,retro,office",
                    "📺|Television|tele,tv,media",
                    "📻|Radio|radio,audio,son",
                    "📡|Satellite Antenna|antenne,signal,communication",
                    "🛰️|Satellite|satellite,espace,orbite",
                    "🎥|Movie Camera|camera,video,tournage",
                    "📷|Camera|photo,image,appareil",
                    "📸|Camera With Flash|photo,lumiere,shoot",
                    "📹|Video Camera|camera,video,record",
                    "📼|Videocassette|cassette,retro,video",
                    "💻|Laptop|ordinateur,portable,travail",
                    "🖥️|Desktop Computer|ordinateur,bureau,travail",
                    "🖨️|Printer|imprimante,office,document",
                    "⌨️|Keyboard|clavier,ordinateur,peripherique",
                    "🖱️|Computer Mouse|souris,ordinateur,peripherique",
                    "🖲️|Trackball|trackball,ordinateur,peripherique",
                    "🎧|Headphone|casque,audio,musique",
                    "🔈|Speaker Low Volume|haut-parleur,audio,son",
                    "🔉|Speaker Medium Volume|haut-parleur,audio,volume",
                    "🔊|Speaker High Volume|haut-parleur,audio,fort",
                    "📢|Loudspeaker|annonce,son,public",
                    "📣|Megaphone|annonce,voix,haut",
                    "🔔|Bell|cloche,son,alerte",
                    "🔕|Bell With Slash|silence,muet,cloche",
                    "🔌|Electric Plug|prise,electricite,energie",
                    "🔋|Battery|batterie,energie,charge",
                    "🪫|Low Battery|batterie,faible,alerte",
                    "💡|Light Bulb|idee,lumiere,energie",
                    "🔦|Flashlight|lampe,torche,lumiere",
                    "🕯️|Candle|bougie,lumiere,ambiance",
                    "🪔|Diya Lamp|diya,lumiere,fete",
                    "🧯|Fire Extinguisher|extincteur,securite,incendie",
                    "🛢️|Oil Drum|baril,carburant,energie",
                    "🧰|Toolbox|boite,outil,bricolage",
                    "🧲|Magnet|aimant,science,force",
                    "🪛|Screwdriver|tournevis,outil,bricolage",
                    "🔧|Wrench|cle,outil,reparation",
                    "🔩|Nut And Bolt|boulon,fixation,atelier",
                    "⚙️|Gear|rouage,mecanique,systeme",
                    "🛠️|Hammer And Wrench|reparation,outil,atelier",
                    "⚒️|Hammer And Pick|mine,outil,chantier",
                    "🗜️|Clamp|serre,atelier,pression",
                    "🪚|Carpentry Saw|scie,outil,bois",
                    "🪓|Axe|hache,outil,bois",
                    "🔨|Hammer|marteau,outil,bricolage",
                    "⛏️|Pick|pioche,outil,miner",
                    "🪤|Mouse Trap|piege,maison,controle",
                    "🪜|Ladder|echelle,bricolage,hauteur",
                    "🪝|Hook|crochet,outil,suspension",
                    "🧱|Brick|brique,construction,mur",
                    "🪨|Rock|roche,pierre,construction",
                    "🪵|Wood|bois,ressource,construction",
                    "🧮|Abacus|boulier,calcul,education",
                    "🪙|Coin|piece,monnaie,finance",
                    "💰|Money Bag|argent,sac,finance",
                    "💳|Credit Card|carte,paiement,banque",
                    "💴|Banknote With Yen|billet,argent,yen",
                    "💶|Banknote With Euro|billet,argent,euro",
                    "💷|Banknote With Pound|billet,argent,livre",
                    "💵|Banknote With Dollar|billet,argent,dollar",
                    "💸|Money With Wings|argent,depense,perte",
                    "🧾|Receipt|ticket,preuve,achat",
                    "🪪|Identification Card|identite,carte,identifiant",
                    "💼|Briefcase|porte-documents,bureau,travail",
                    "✉️|Envelope|courrier,message,mail",
                    "📧|E-Mail|email,mail,message",
                    "📬|Mailbox With Raised Flag|courrier,reception,lettre",
                    "📭|Mailbox With Lowered Flag|courrier,attente,lettre",
                    "📮|Postbox|boite,poste,lettre",
                    "📦|Package|colis,livraison,paquet",
                    "🗳️|Ballot Box With Ballot|vote,election,urne",
                    "📥|Inbox Tray|boite,entree,courrier",
                    "📤|Outbox Tray|boite,sortie,courrier",
                    "📫|Closed Mailbox With Raised Flag|courrier,notification,poste",
                    "📪|Closed Mailbox With Lowered Flag|courrier,ferme,poste",
                    "📂|Open File Folder|dossier,organisation,documents",
                    "📁|File Folder|dossier,documents,bureau",
                    "🗂️|Card Index Dividers|classement,documents,bureau",
                    "🗃️|Card File Box|fichier,archive,documents",
                    "🗄️|File Cabinet|archives,bureau,rangement",
                    "🗑️|Wastebasket|poubelle,bureau,nettoyage",
                    "📄|Document|document,papier,texte",
                    "📃|Page With Curl|document,page,bureau",
                    "📜|Scroll|manuscrit,histoire,document",
                    "📑|Bookmark Tabs|marque-page,documents,organisation",
                    "📋|Clipboard|bloc,notes,controle",
                    "🗒️|Spiral Notepad|bloc,notes,ecriture",
                    "🗓️|Spiral Calendar|calendrier,agenda,planning",
                    "📆|Tear-Off Calendar|calendrier,date,planning",
                    "📅|Calendar|agenda,date,evenement",
                    "📊|Bar Chart|statistiques,rapport,analyse",
                    "📈|Chart Increasing|croissance,graphique,hausse",
                    "📉|Chart Decreasing|baisse,graphique,analyse",
                    "📇|Card Index|fichier,contact,rolodex",
                    "🖊️|Pen|stylo,ecriture,bureau",
                    "🖋️|Fountain Pen|stylo,plume,signature",
                    "✒️|Black Nib|stylo,plume,calligraphie",
                    "✏️|Pencil|crayon,ecriture,sketch",
                    "🖍️|Crayon|couleur,dessin,atelier",
                    "🖌️|Paintbrush|pinceau,peinture,art",
                    "📝|Memo|notes,ecriture,todo",
                    "🧷|Safety Pin|epingle,couture,fixer",
                    "📎|Paperclip|trombone,documents,attache",
                    "🖇️|Linked Paperclips|trombones,documents,ensemble",
                    "📌|Pushpin|punaise,notes,fixer",
                    "📍|Round Pushpin|punaise,position,carte",
                    "📏|Straight Ruler|regle,mesure,geometrie",
                    "📐|Triangular Ruler|equerre,mesure,geometrie",
                    "🧴|Lotion Bottle|flacon,cosmetique,beaute",
                    "🧼|Soap|savon,hygiene,nettoyage",
                    "🪥|Toothbrush|brosse,dent,hygiene",
                    "🪒|Razor|rasoir,hygiene,soin",
                    "🧽|Sponge|eponge,nettoyage,maison",
                    "🪣|Bucket|seau,nettoyage,maison",
                    "🪠|Plunger|deboucheur,plomberie,maison",
                    "🧹|Broom|balai,nettoyage,maison",
                    "🧺|Basket|panier,rangement,maison",
                    "🧻|Roll Of Paper|papier,toilette,consommable",
                    "🪑|Chair|chaise,meuble,interieur",
                    "🛋️|Couch And Lamp|canape,salon,interieur",
                    "🛏️|Bed|lit,chambre,repos",
                    "🪟|Window|fenetre,interieur,luminosite",
                    "🚪|Door|porte,interieur,maison",
                    "🪞|Mirror|miroir,reflet,decor",
                    "🖼️|Framed Picture|cadre,photo,decor",
                    "🪆|Nesting Dolls|poupee,russe,decor",
                    "🪅|Piñata|pinata,celebration,jeu",
                    "🎁|Wrapped Gift|cadeau,fete,surprise",
                    "🎀|Ribbon|ruban,decor,cadeau",
                    "🎗️|Reminder Ribbon|ruban,soutien,cause",
                    "🎎|Japanese Dolls|poupee,japon,decor",
                    "🎏|Carp Streamer|poisson,banniere,festival",
                    "🎐|Wind Chime|cloche,vent,zen",
                    "🎉|Party Popper|fete,celebration,confetti",
                    "🎊|Confetti Ball|fete,celebration,confetti",
                    "🎋|Tanabata Tree|bambou,voeux,japon",
                    "🎌|Crossed Flags|drapeau,cross,festival",
                    "🏮|Red Paper Lantern|lanterne,asie,decor",
                    "🛍️|Shopping Bags|shopping,achats,commerce",
                    "🛒|Shopping Cart|chariot,magasin,achats",
                    "🎒|Backpack|sac,ecole,bagage",
                    "👝|Clutch Bag|pochette,sac,mode",
                    "👛|Purse|porte-monnaie,sac,mode",
                    "👜|Handbag|sac,a-main,mode",
                    "🎓|Graduation Cap|diplome,etude,ceremonie",
                    "🎩|Top Hat|chapeau,style,evenement",
                    "👒|Woman’s Hat|chapeau,mode,soleil",
                    "🧢|Billed Cap|casquette,style,casual",
                    "👓|Glasses|lunettes,vision,accessoire",
                    "🕶️|Sunglasses|lunettes,soleil,style",
                    "👔|Necktie|cravate,mode,travail",
                    "👕|T-Shirt|vetement,cotton,casual",
                    "👖|Jeans|pantalon,vetement,denim",
                    "🧥|Coat|mantel,vetement,hiver",
                    "🧣|Scarf|echarpe,vetement,hiver",
                    "🧤|Gloves|gants,vetement,hiver",
                    "🧦|Socks|chaussettes,vetement,pied",
                    "👗|Dress|robe,mode,femme",
                    "👘|Kimono|kimono,vetement,japon",
                    "🩱|One-Piece Swimsuit|maillot,baignade,plage",
                    "👙|Bikini|bikini,plage,ete",
                    "🩳|Shorts|short,vetement,ete",
                    "🥻|Sari|sari,vetement,inde",
                    "🩲|Briefs|sous-vetement,maillot,plage",
                    "🥾|Hiking Boot|chaussure,rando,pleinair",
                    "👞|Man’s Shoe|chaussure,formel,mode",
                    "👟|Running Shoe|chaussure,sport,course",
                    "🥿|Flat Shoe|chaussure,femme,confort",
                    "👠|High-Heeled Shoe|talon,mode,femme",
                    "👡|Sandal|sandale,ete,mode",
                    "🩴|Thong Sandal|tongs,plage,ete",
                    "👢|Boot|botte,mode,hiver",
                    "👑|Crown|couronne,royale,prestige",
                    "💍|Ring|bague,engagement,bijou",
                    "💎|Gem Stone|bijou,diamant,luxe",
                    "🪬|Hamsa|amulette,protection,spirit",
                    "🧿|Nazar Amulet|amulette,protection,regard",
                    "📿|Prayer Beads|priere,mala,spirit",
                    "🔮|Crystal Ball|voyance,magie,avenir",
                    "🩺|Stethoscope|medical,sante,docteur",
                    "💉|Syringe|injection,medical,sante",
                    "💊|Pill|medicament,sante,pharma",
                    "🩹|Adhesive Bandage|pansement,sante,soin",
                    "🩼|Crutch|bequille,medical,soutien",
                    "🩻|X-Ray|radio,medical,diagnostic",
                    "🦽|Manual Wheelchair|mobilite,handicap,accessibilite",
                    "🦼|Motorized Wheelchair|mobilite,assistance,accessibilite",
                    "🛡️|Shield|bouclier,protection,securite",
                    "🔑|Key|cle,acces,serrure",
                    "🗝️|Old Key|clef,ancien,serrure",
                    "🔒|Locked|cadenas,ferme,secure",
                    "🔓|Unlocked|cadenas,ouvert,acces",
                    "🔐|Locked With Key|securise,ferme,protection",
                    "🔏|Locked With Pen|confidentiel,signature,secure",
                    "⚔️|Crossed Swords|epee,combat,arme",
                    "🗡️|Dagger|dague,arme,combat",
                    "🔪|Kitchen Knife|couteau,cuisine,outil",
                    "🪃|Boomerang|boomerang,jeu,retour",
                    "🧨|Firecracker|petard,celebration,fete",
                    "🪄|Magic Wand|magie,illusion,sorcier",
                    "🪩|Mirror Ball|disco,soiree,danse",
                    "🧸|Teddy Bear|nounours,enfant,jeu",
                    "🪀|Yo-Yo|jeu,retro,loisir",
                    "🕹️|Joystick|console,retro,arcade",
                    "🎮|Video Game|jeu,console,gaming",
                    "🔭|Telescope|telescope,astronomie,observation",
                    "🔬|Microscope|microscope,science,recherche",
                    "🧪|Test Tube|science,chimie,labo",
                    "🧫|Petri Dish|science,labo,culture",
                    "🧬|DNA|genetique,science,recherche",
                    "⚗️|Alembic|chimie,distillation,labo",
                    "🛎️|Bellhop Bell|reception,service,sonnette",
                    "🛗|Elevator|ascenseur,transport,batiment",
                    "🪧|Placard|pancarte,manifestation,affiche",
                    "🏷️|Label|etiquette,prix,tag",
                    "🪢|Knot|noeud,corde,attache"
                ].join('\n'),
            },
        ];

        return categories.map(function (category) {
            var items;
            if (Array.isArray(category.items)) {
                items = category.items.slice();
            } else {
                items = parseEmojiBlock(category.block || '');
            }
            return {
                key: category.key,
                label: category.label,
                items: items,
            };
        }).filter(function (category) {
            return Array.isArray(category.items) && category.items.length > 0;
        });
    })();

    var DEFAULT_EMOJI_HELPER = createEmojiHelper(DEFAULT_EMOJI_LIBRARY);

    function sliceGraphemes(text, max) {
        if (typeof text !== 'string' || !max || max <= 0) {
            return '';
        }
        if (typeof Intl !== 'undefined' && typeof Intl.Segmenter === 'function') {
            try {
                var segmenter = new Intl.Segmenter(undefined, { granularity: 'grapheme' });
                var iterator = segmenter.segment(text);
                var collected = '';
                var count = 0;
                if (iterator && typeof Symbol === 'function' && typeof iterator[Symbol.iterator] === 'function') {
                    var iter = iterator[Symbol.iterator]();
                    var step = iter.next();
                    while (!step.done && count < max) {
                        collected += step.value.segment;
                        count++;
                        step = iter.next();
                    }
                    return collected;
                }
            } catch (segmenterError) {
                // ignore segmenter issues and fall back to code point slicing
            }
        }
        var units;
        try {
            units = Array.from(text);
        } catch (arrayError) {
            units = String(text).split('');
        }
        return units.slice(0, max).join('');
    }

    function sanitizeEmojiValue(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var normalized = value.replace(/\s+/g, ' ').trim();
        if (normalized === '') {
            return '';
        }
        var limited = sliceGraphemes(normalized, 8);
        if (limited.length > 16) {
            limited = limited.slice(0, 16);
        }
        return limited;
    }

    function normalizeEventFormValues(values) {
        if (!values || typeof values !== 'object') {
            return {};
        }
        var next = Object.assign({}, values);
        if (Object.prototype.hasOwnProperty.call(next, 'event_emoji')) {
            next.event_emoji = sanitizeEmojiValue(next.event_emoji);
        }
        return next;
    }

    function RichTextEditorField(props) {
        var value = typeof props.value === 'string' ? props.value : '';
        var onChange = typeof props.onChange === 'function' ? props.onChange : function () {};
        var rows = props.rows || 6;
        var className = props.className || '';
        var editorAvailable = !!(global.wp && global.wp.editor && typeof global.wp.editor.initialize === 'function');

        var idRef = useRef(null);
        if (!idRef.current) {
            idRef.current = 'mj-regmgr-editor-' + Math.random().toString(36).slice(2);
        }
        var editorId = idRef.current;
        var textareaRef = useRef(null);
        var editorRef = useRef(null);
        var syncingRef = useRef(false);
        var valueRef = useRef(value || '');
        valueRef.current = value || '';

        useEffect(function () {
            if (!editorAvailable) {
                return undefined;
            }

            var textarea = textareaRef.current;
            if (!textarea) {
                return undefined;
            }

            if (global.wp.editor && typeof global.wp.editor.remove === 'function') {
                try {
                    global.wp.editor.remove(editorId);
                } catch (removeError) {
                    // ignore cleanup issues
                }
            }

            textarea.value = valueRef.current;

            var editorSettings = {
                mediaButtons: false,
                quicktags: false,
                tinymce: {
                    branding: false,
                    menubar: false,
                    statusbar: true,
                    toolbar1: 'bold italic underline | bullist numlist | link unlink | undo redo',
                    plugins: 'lists link paste',
                    wpautop: true,
                },
            };

            try {
                global.wp.editor.initialize(editorId, editorSettings);
            } catch (initError) {
                console.error('[MjRegMgr] Failed to initialise editor', initError);
                return undefined;
            }

            var editor = global.tinymce ? global.tinymce.get(editorId) : null;
            if (!editor) {
                return undefined;
            }

            editorRef.current = editor;

            var handleContentChange = function () {
                if (syncingRef.current) {
                    return;
                }
                var content = editor.getContent();
                if (content !== valueRef.current) {
                    valueRef.current = content;
                    onChange(content);
                }
            };

            editor.on('Change KeyUp Paste Undo Redo', handleContentChange);

            return function () {
                editor.off('Change KeyUp Paste Undo Redo', handleContentChange);
                editorRef.current = null;
                if (global.wp.editor && typeof global.wp.editor.remove === 'function') {
                    try {
                        global.wp.editor.remove(editorId);
                    } catch (cleanupError) {
                        // ignore cleanup issues
                    }
                }
            };
        }, [editorAvailable, editorId, onChange]);

        useEffect(function () {
            if (!editorAvailable) {
                return;
            }
            var editor = editorRef.current;
            if (editor && editor.initialized) {
                var targetValue = valueRef.current;
                var currentValue = editor.getContent();
                if (targetValue !== currentValue && !editor.hasFocus()) {
                    syncingRef.current = true;
                    editor.setContent(targetValue || '');
                    editor.save();
                    syncingRef.current = false;
                }
            } else if (textareaRef.current && textareaRef.current.value !== valueRef.current) {
                textareaRef.current.value = valueRef.current;
            }
        }, [editorAvailable, editorId, value]);

        if (!editorAvailable) {
            return h('textarea', {
                class: className,
                rows: rows,
                value: valueRef.current,
                onInput: function (event) {
                    valueRef.current = event.target.value;
                    onChange(event.target.value);
                },
            });
        }

        var finalClassName = className ? className + ' wp-editor-area' : 'wp-editor-area';

        return h('textarea', {
            id: editorId,
            ref: textareaRef,
            class: finalClassName,
            rows: rows,
            defaultValue: valueRef.current,
        });
    }

    function EmojiPickerField(props) {
        var value = typeof props.value === 'string' ? props.value : '';
        var onChange = typeof props.onChange === 'function' ? props.onChange : function () {};
        var disabled = !!props.disabled;
        var strings = props.strings || {};
        var emojiHelper = useMemo(function () {
            if (props.emojiHelper && typeof props.emojiHelper.filter === 'function' && typeof props.emojiHelper.getCategories === 'function') {
                return props.emojiHelper;
            }
            if (Array.isArray(props.suggestions) && props.suggestions.length > 0) {
                return createEmojiHelper([{
                    key: 'custom',
                    label: getString(strings, 'eventEmojiSuggestions', 'Suggestions'),
                    items: props.suggestions,
                }]);
            }
            return DEFAULT_EMOJI_HELPER;
        }, [props.emojiHelper, props.suggestions, strings]);

        var categories = useMemo(function () {
            return emojiHelper.getCategories();
        }, [emojiHelper]);

        var categoryOptions = useMemo(function () {
            var base = categories.map(function (category) {
                return { key: category.key, label: category.label };
            });
            return [{ key: 'all', label: getString(strings, 'eventEmojiAllCategory', 'Tout') }].concat(base);
        }, [categories, strings]);

        var _activeCategory = useState('all');
        var activeCategory = _activeCategory[0];
        var setActiveCategory = _activeCategory[1];

        var _searchValue = useState('');
        var searchValue = _searchValue[0];
        var setSearchValue = _searchValue[1];

        var filteredCategories = useMemo(function () {
            var categoryKey = activeCategory === 'all' ? null : activeCategory;
            return emojiHelper.filter({
                query: searchValue,
                category: categoryKey,
            });
        }, [emojiHelper, activeCategory, searchValue]);

        var hasResults = useMemo(function () {
            return filteredCategories.some(function (category) {
                return category.items && category.items.length > 0;
            });
        }, [filteredCategories]);

        var handleSearchChange = useCallback(function (event) {
            setSearchValue(event && event.target ? event.target.value || '' : '');
        }, [setSearchValue]);

        var handleSelectCategory = useCallback(function (key) {
            setActiveCategory(key);
        }, [setActiveCategory]);

        var containerRef = useRef(null);
        var inputRef = useRef(null);
        var pickerIdRef = useRef(null);
        if (!pickerIdRef.current) {
            pickerIdRef.current = 'mj-regmgr-emoji-picker-' + Math.random().toString(36).slice(2);
        }
        var pickerId = pickerIdRef.current;

        useEffect(function () {
            if (activeCategory === 'all') {
                return;
            }
            var exists = categories.some(function (category) { return category.key === activeCategory; });
            if (!exists) {
                setActiveCategory('all');
            }
        }, [categories, activeCategory, setActiveCategory]);


        var _isPickerOpen = useState(false);
        var isPickerOpen = _isPickerOpen[0];
        var setPickerOpen = _isPickerOpen[1];

        useEffect(function () {
            if (!isPickerOpen) {
                setSearchValue('');
            }
        }, [isPickerOpen, setSearchValue]);

        useEffect(function () {
            if (!isPickerOpen) {
                return undefined;
            }
            var handleOutside = function (event) {
                if (!containerRef.current || containerRef.current.contains(event.target)) {
                    return;
                }
                setPickerOpen(false);
            };
            var handleKeydown = function (event) {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    setPickerOpen(false);
                    if (inputRef.current) {
                        inputRef.current.focus();
                    }
                }
            };
            document.addEventListener('mousedown', handleOutside);
            document.addEventListener('touchstart', handleOutside);
            document.addEventListener('keydown', handleKeydown);
            return function () {
                document.removeEventListener('mousedown', handleOutside);
                document.removeEventListener('touchstart', handleOutside);
                document.removeEventListener('keydown', handleKeydown);
            };
        }, [isPickerOpen]);

        useEffect(function () {
            if (!disabled) {
                return;
            }
            if (isPickerOpen) {
                setPickerOpen(false);
            }
        }, [disabled, isPickerOpen]);

        var handleInputChange = useCallback(function (event) {
            var raw = event.target.value;
            var sanitized = sanitizeEmojiValue(raw);
            onChange(sanitized);
        }, [onChange]);

        var handleTogglePicker = useCallback(function () {
            if (disabled) {
                return;
            }
            setPickerOpen(function (prev) {
                return !prev;
            });
        }, [disabled]);

        var handleSelectSuggestion = useCallback(function (emoji) {
            var sanitized = sanitizeEmojiValue(emoji);
            onChange(sanitized);
            setPickerOpen(false);
            if (inputRef.current) {
                inputRef.current.focus();
            }
        }, [onChange]);

        var handleClear = useCallback(function () {
            onChange('');
            if (inputRef.current) {
                inputRef.current.focus();
            }
        }, [onChange]);

        return h('div', { class: 'mj-regmgr-emoji-field', ref: containerRef }, [
            h('div', { class: 'mj-regmgr-emoji-field__control' }, [
                h('input', {
                    ref: inputRef,
                    type: 'text',
                    class: 'mj-regmgr-emoji-field__input',
                    value: value,
                    onChange: handleInputChange,
                    placeholder: getString(strings, 'eventEmojiPlaceholder', 'Ex: 🎉'),
                    disabled: disabled,
                    'aria-label': getString(strings, 'eventEmoji', 'Emoticone'),
                }),
                h('div', { class: 'mj-regmgr-emoji-field__actions' }, [
                    value ? h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--ghost mj-btn--sm',
                        onClick: handleClear,
                        disabled: disabled,
                    }, getString(strings, 'eventEmojiClear', 'Effacer')) : null,
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--ghost mj-btn--sm',
                        onClick: handleTogglePicker,
                        disabled: disabled,
                        'aria-haspopup': 'listbox',
                        'aria-expanded': isPickerOpen ? 'true' : 'false',
                        'aria-controls': pickerId,
                    }, isPickerOpen
                        ? getString(strings, 'eventEmojiPickerClose', 'Fermer')
                        : getString(strings, 'eventEmojiPicker', 'Choisir')),
                ]),
            ]),
            isPickerOpen && h('div', {
                id: pickerId,
                class: 'mj-regmgr-emoji-field__picker',
                role: 'listbox',
            }, [
                h('div', { class: 'mj-regmgr-emoji-field__tools' }, [
                    h('p', { class: 'mj-regmgr-emoji-field__picker-title' }, getString(strings, 'eventEmojiSuggestions', 'Suggestions')),
                    h('input', {
                        type: 'search',
                        class: 'mj-regmgr-emoji-field__search',
                        value: searchValue,
                        onInput: handleSearchChange,
                        placeholder: getString(strings, 'eventEmojiSearchPlaceholder', 'Rechercher un emoji'),
                        'aria-label': getString(strings, 'eventEmojiSearchPlaceholder', 'Rechercher un emoji'),
                    }),
                ]),
                categoryOptions.length > 1 && h('div', { class: 'mj-regmgr-emoji-field__categories' }, categoryOptions.map(function (option) {
                    return h('button', {
                        key: option.key,
                        type: 'button',
                        class: classNames('mj-regmgr-emoji-field__category', {
                            'mj-regmgr-emoji-field__category--active': activeCategory === option.key,
                        }),
                        onClick: function () { handleSelectCategory(option.key); },
                        'aria-pressed': activeCategory === option.key ? 'true' : 'false',
                    }, option.label);
                })),
                h('div', { class: 'mj-regmgr-emoji-field__groups' }, hasResults
                    ? filteredCategories.map(function (category) {
                        if (!category.items || category.items.length === 0) {
                            return null;
                        }
                        return h('div', { key: category.key, class: 'mj-regmgr-emoji-field__group' }, [
                            h('p', { class: 'mj-regmgr-emoji-field__group-title' }, category.label),
                            h('div', { class: 'mj-regmgr-emoji-field__grid' }, category.items.map(function (item) {
                                var labelParts = [];
                                if (item.name) {
                                    labelParts.push(item.name);
                                }
                                if (category.label) {
                                    labelParts.push(category.label);
                                }
                                var optionLabel = labelParts.join(' - ');
                                var ariaLabel = optionLabel ? optionLabel + ' ' + item.symbol : item.symbol;
                                return h('button', {
                                    key: item.symbol + '-' + item.category,
                                    type: 'button',
                                    class: 'mj-regmgr-emoji-field__choice',
                                    onClick: function () { handleSelectSuggestion(item.symbol); },
                                    role: 'option',
                                    'aria-selected': value === item.symbol ? 'true' : 'false',
                                    'aria-label': ariaLabel,
                                    title: optionLabel || item.symbol,
                                }, item.symbol);
                            })),
                        ]);
                    })
                    : h('p', { class: 'mj-regmgr-emoji-field__empty' }, getString(strings, 'eventEmojiSearchNoResult', 'Aucun emoji ne correspond a votre recherche.'))),
            ]),
        ]);
    }

    var sharedEmojiModule = global.MjRegMgrEmojiPicker || global.MjRegMgrEmojiHelper || null;
    if (sharedEmojiModule) {
        if (typeof sharedEmojiModule.sanitizeValue === 'function') {
            sanitizeEmojiValue = sharedEmojiModule.sanitizeValue;
        }
        if (typeof sharedEmojiModule.createHelper === 'function') {
            createEmojiHelper = sharedEmojiModule.createHelper;
        }
        if (typeof sharedEmojiModule.getDefaultHelper === 'function') {
            DEFAULT_EMOJI_HELPER = sharedEmojiModule.getDefaultHelper();
        } else if (typeof sharedEmojiModule.getDefaultLibrary === 'function') {
            DEFAULT_EMOJI_HELPER = createEmojiHelper(sharedEmojiModule.getDefaultLibrary());
        }
        if (sharedEmojiModule.EmojiPickerField) {
            EmojiPickerField = sharedEmojiModule.EmojiPickerField;
        }
    }

    function ScheduleEditor(props) {
        return null;
        /* Planification UI disabled.
        var form = props && props.form ? props.form : {};
        var meta = props && props.meta ? props.meta : {};
        var options = props && props.options ? props.options : {};
        var strings = props && props.strings ? props.strings : {};

        var onChangeForm = typeof props.onChangeForm === 'function' ? props.onChangeForm : function () {};
        var onChangeMeta = typeof props.onChangeMeta === 'function' ? props.onChangeMeta : function () {};
        var onToggleWeekday = typeof props.onToggleWeekday === 'function' ? props.onToggleWeekday : function () {};
        var onOpenExceptionDialog = typeof props.onOpenExceptionDialog === 'function' ? props.onOpenExceptionDialog : function () {};
        var onWeekdayTimeChange = typeof props.onWeekdayTimeChange === 'function' ? props.onWeekdayTimeChange : function () {};
        var onAddSeriesItem = typeof props.onAddSeriesItem === 'function' ? props.onAddSeriesItem : function () {};
        var onUpdateSeriesItem = typeof props.onUpdateSeriesItem === 'function' ? props.onUpdateSeriesItem : function () {};
        var onRemoveSeriesItem = typeof props.onRemoveSeriesItem === 'function' ? props.onRemoveSeriesItem : function () {};

        var seriesItems = Array.isArray(props.seriesItems) ? props.seriesItems : [];

        var metaMode = meta && meta.scheduleMode ? normalizeScheduleMode(meta.scheduleMode) : null;
        var formMode = form && form.event_schedule_mode ? normalizeScheduleMode(form.event_schedule_mode) : null;
        var payloadMode = meta && meta.schedulePayload && meta.schedulePayload.mode ? normalizeScheduleMode(meta.schedulePayload.mode) : null;
        var scheduleMode = normalizeScheduleMode(formMode || metaMode || payloadMode || 'fixed');

        var scheduleWeekdayTimes = meta && meta.scheduleWeekdayTimes && typeof meta.scheduleWeekdayTimes === 'object'
            ? meta.scheduleWeekdayTimes
            : {};
        var scheduleExceptions = normalizeScheduleExceptions(meta && meta.scheduleExceptions);
        var showDateRange = !!(meta && meta.scheduleShowDateRange);

        var weekdayEntries = useMemo(function () {
            var source = options && options.schedule_weekdays && typeof options.schedule_weekdays === 'object'
                ? options.schedule_weekdays
                : null;
            if (source) {
                return Object.keys(source).map(function (key) {
                    return { key: key, label: source[key] }; 
                }).filter(function (entry) { return entry.label !== undefined; });
            }
            return WEEKDAY_KEYS.map(function (key) {
                return { key: key, label: key.charAt(0).toUpperCase() + key.slice(1) };
            });
        }, [options && options.schedule_weekdays]);

        var ordinalEntries = useMemo(function () {
            var source = options && options.schedule_month_ordinals && typeof options.schedule_month_ordinals === 'object'
                ? options.schedule_month_ordinals
                : null;
            if (source) {
                return Object.keys(source).map(function (key) {
                    return { key: key, label: source[key] };
                }).filter(function (entry) { return entry.label !== undefined; });
            }
            return [
                { key: 'first', label: '1er' },
                { key: 'second', label: '2e' },
                { key: 'third', label: '3e' },
                { key: 'fourth', label: '4e' },
                { key: 'last', label: 'Dernier' },
            ];
        }, [options && options.schedule_month_ordinals]);

        var selectedWeekdays = ensureArray(form.event_recurring_weekdays);
        var recurringFrequency = form.event_recurring_frequency === 'monthly' ? 'monthly' : 'weekly';

        var scheduleContext = useMemo(function () {
            return {
                exceptions: scheduleExceptions,
                weekdayTimes: scheduleWeekdayTimes,
                seriesItems: seriesItems,
                showDateRange: showDateRange,
            };
        }, [scheduleExceptions, scheduleWeekdayTimes, seriesItems, showDateRange]);

        var schedulePayload = useMemo(function () {
            var base = createSchedulePayloadSnapshot(scheduleMode, form, scheduleContext);
            var existing = meta && meta.schedulePayload && typeof meta.schedulePayload === 'object'
                ? meta.schedulePayload
                : null;
            if (existing) {
                if (!base.occurrence_generator && existing.occurrence_generator) {
                    base.occurrence_generator = existing.occurrence_generator;
                }
                if (!base.occurrence_generator && existing.occurrenceGenerator) {
                    base.occurrence_generator = existing.occurrenceGenerator;
                }
                if (!base.occurrences && Array.isArray(existing.occurrences) && existing.occurrences.length > 0) {
                    base.occurrences = existing.occurrences.slice();
                }
            }
            return base;
        }, [scheduleMode, form, scheduleContext, meta && meta.schedulePayload]);

        var schedulePayloadJson = useMemo(function () {
            try {
                return JSON.stringify(schedulePayload);
            } catch (serializationError) {
                return '';
            }
        }, [schedulePayload]);

        var metaPayloadJson = useMemo(function () {
            var source = meta && meta.schedulePayload ? meta.schedulePayload : {};
            try {
                return JSON.stringify(source);
            } catch (serializationError) {
                return '';
            }
        }, [meta && meta.schedulePayload]);

        useEffect(function () {
            var currentMetaMode = meta && meta.scheduleMode ? normalizeScheduleMode(meta.scheduleMode) : '';
            if (scheduleMode !== currentMetaMode) {
                onChangeMeta('scheduleMode', scheduleMode);
            }
        }, [meta && meta.scheduleMode, scheduleMode, onChangeMeta]);

        useEffect(function () {
            var currentFormMode = form && form.event_schedule_mode ? normalizeScheduleMode(form.event_schedule_mode) : '';
            if (scheduleMode !== currentFormMode) {
                onChangeForm('event_schedule_mode', scheduleMode);
            }
        }, [form && form.event_schedule_mode, scheduleMode, onChangeForm]);

        useEffect(function () {
            if (schedulePayloadJson !== metaPayloadJson) {
                onChangeMeta('schedulePayload', schedulePayload);
            }
        }, [schedulePayloadJson, metaPayloadJson, onChangeMeta, schedulePayload]);

        var _agendaExpanded = useState(false);
        var agendaExpanded = _agendaExpanded[0];
        var setAgendaExpanded = _agendaExpanded[1];

        useEffect(function () {
            setAgendaExpanded(false);
        }, [scheduleMode, schedulePayloadJson]);

        var recurringAgenda = useMemo(function () {
            if (scheduleMode !== 'recurring') {
                return null;
            }
            var agendaConfig = {
                startDate: schedulePayload.start_date || '',
                untilDate: schedulePayload.until || '',
                interval: schedulePayload.interval || 1,
                startTime: schedulePayload.start_time || '',
                endTime: schedulePayload.end_time || '',
                weekdays: schedulePayload.frequency === 'weekly' ? ensureArray(schedulePayload.weekdays) : [],
                weekdayTimes: schedulePayload.frequency === 'weekly' ? schedulePayload.weekday_times || {} : {},
                frequency: schedulePayload.frequency || 'weekly',
                monthOrdinal: schedulePayload.ordinal || 'first',
                monthWeekday: schedulePayload.weekday || 'saturday',
                exceptions: schedulePayload.exceptions || scheduleExceptions,
                maxOccurrences: MAX_PREVIEW_OCCURRENCES,
            };
            var occurrences = buildRecurringAgenda(agendaConfig);
            return groupRecurringAgendaEntries(occurrences, strings);
        }, [scheduleMode, schedulePayload, scheduleExceptions, strings]);

        var recurringStats = recurringAgenda ? recurringAgenda.stats : { total: 0, cancelled: 0, excluded: 0 };
        var recurringGroups = recurringAgenda ? recurringAgenda.groups : [];

        var handleModeChange = useCallback(function (event) {
            var nextValue = event && event.target ? event.target.value : event;
            var normalized = normalizeScheduleMode(nextValue);
            onChangeForm('event_schedule_mode', normalized);
            onChangeMeta('scheduleMode', normalized);
        }, [onChangeForm, onChangeMeta]);

        var handleShowDateRangeToggle = useCallback(function (event) {
            var checked = !!(event && event.target && event.target.checked);
            onChangeMeta('scheduleShowDateRange', checked);
        }, [onChangeMeta]);

        var syncFixedBoundaries = useCallback(function (dateValue, startValue, endValue) {
            var startDateTime = mergeDateTimeParts(dateValue, startValue);
            var endDateTime = mergeDateTimeParts(dateValue, endValue);
            if (startDateTime !== (form.event_date_start || '')) {
                onChangeForm('event_date_start', startDateTime);
            }
            if (endDateTime !== (form.event_date_end || '')) {
                onChangeForm('event_date_end', endDateTime);
            }
        }, [form.event_date_start, form.event_date_end, onChangeForm]);

        var handleFixedFieldChange = useCallback(function (key, value) {
            onChangeForm(key, value);
            var nextDate = key === 'event_fixed_date' ? value : (form.event_fixed_date || '');
            var nextStart = key === 'event_fixed_start_time' ? value : (form.event_fixed_start_time || '');
            var nextEnd = key === 'event_fixed_end_time' ? value : (form.event_fixed_end_time || '');
            syncFixedBoundaries(nextDate, nextStart, nextEnd);
        }, [form.event_fixed_date, form.event_fixed_start_time, form.event_fixed_end_time, syncFixedBoundaries, onChangeForm]);

        var handleRangeFieldChange = useCallback(function (key, value) {
            onChangeForm(key, value);
            var nextStart = key === 'event_range_start' ? value : (form.event_range_start || '');
            var nextEnd = key === 'event_range_end' ? value : (form.event_range_end || '');
            if (nextStart !== (form.event_date_start || '')) {
                onChangeForm('event_date_start', nextStart);
            }
            if (nextEnd !== (form.event_date_end || '')) {
                onChangeForm('event_date_end', nextEnd);
            }
        }, [form.event_range_start, form.event_range_end, form.event_date_start, form.event_date_end, onChangeForm]);

        var handleRecurringFieldChange = useCallback(function (key, value) {
            onChangeForm(key, value);
            var nextStartDate = key === 'event_recurring_start_date' ? value : (form.event_recurring_start_date || '');
            var nextStartTime = key === 'event_recurring_start_time' ? value : (form.event_recurring_start_time || '');
            var nextEndTime = key === 'event_recurring_end_time' ? value : (form.event_recurring_end_time || '');
            var fallbackStart = mergeDateTimeParts(nextStartDate, nextStartTime);
            if (fallbackStart !== (form.event_date_start || '')) {
                onChangeForm('event_date_start', fallbackStart);
            }
            var fallbackEnd = mergeDateTimeParts(nextStartDate, nextEndTime || nextStartTime);
            if (fallbackEnd !== (form.event_date_end || '')) {
                onChangeForm('event_date_end', fallbackEnd);
            }
        }, [form.event_recurring_start_date, form.event_recurring_start_time, form.event_recurring_end_time, form.event_date_start, form.event_date_end, onChangeForm]);

        var handleWeekdayTimeChange = useCallback(function (weekday, field, event) {
            var value = event && event.target ? event.target.value : event;
            onWeekdayTimeChange(weekday, field, value);
        }, [onWeekdayTimeChange]);

        var toggleAgendaVisibility = useCallback(function () {
            setAgendaExpanded(function (prev) { return !prev; });
        }, []);

        var recurringAgendaSummary = formatTemplate(
            getString(strings, 'recurringAgendaTotalLabel', 'Occurrences') + ': {count}',
            { count: recurringStats.total }
        );
        var recurringCancelledSummary = formatTemplate(
            getString(strings, 'recurringAgendaCancelledLabel', 'Annulees') + ': {count}',
            { count: recurringStats.cancelled }
        );
        var recurringExcludedSummary = formatTemplate(
            getString(strings, 'recurringAgendaExcludedLabel', 'Exclusions') + ': {count}',
            { count: recurringStats.excluded }
        );

        var renderFixedMode = function () {
            if (scheduleMode !== 'fixed') {
                return null;
            }
            return h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'fixedDate', 'Jour')),
                    h('input', {
                        type: 'date',
                        value: form.event_fixed_date || '',
                        onChange: function (e) { handleFixedFieldChange('event_fixed_date', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'fixedStartTime', 'Debut')),
                    h('input', {
                        type: 'time',
                        value: formatTimeValue(form.event_fixed_start_time || ''),
                        onChange: function (e) { handleFixedFieldChange('event_fixed_start_time', e.target.value); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'fixedEndTime', 'Fin')),
                    h('input', {
                        type: 'time',
                        value: formatTimeValue(form.event_fixed_end_time || ''),
                        onChange: function (e) { handleFixedFieldChange('event_fixed_end_time', e.target.value); },
                    }),
                    h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'fixedHint', 'Utilisez cette option pour un evenement sur une seule journee avec un creneau horaire.')),
                ]),
            ]);
        };

        var renderRangeMode = function () {
            if (scheduleMode !== 'range') {
                return null;
            }
            return h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'rangeStart', 'Debut')),
                    h('input', {
                        type: 'datetime-local',
                        value: formatDateTimeLocalValue(form.event_range_start || ''),
                        onChange: function (e) { handleRangeFieldChange('event_range_start', normalizeDateTimeLocalValue(e.target.value)); },
                    }),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'rangeEnd', 'Fin')),
                    h('input', {
                        type: 'datetime-local',
                        value: formatDateTimeLocalValue(form.event_range_end || ''),
                        onChange: function (e) { handleRangeFieldChange('event_range_end', normalizeDateTimeLocalValue(e.target.value)); },
                    }),
                    h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'rangeHint', 'Choisissez cette option pour un evenement etale sur plusieurs jours.')),
                ]),
            ]);
        };

        var renderRecurringWeekdays = function () {
            if (recurringFrequency !== 'weekly') {
                return null;
            }
            return h('div', { class: 'mj-regmgr-recurring-weekdays' }, [
                h('p', { class: 'mj-regmgr-form-label' }, getString(strings, 'recurringWeekly', 'Hebdomadaire')),
                h('div', { class: 'mj-regmgr-recurring-weekdays__list' }, weekdayEntries.map(function (entry) {
                    var isChecked = selectedWeekdays.indexOf(entry.key) !== -1;
                    var timeEntry = scheduleWeekdayTimes[entry.key] || { start: '', end: '' };
                    return h('div', { key: entry.key, class: 'mj-regmgr-recurring-weekday' }, [
                        h('label', { class: 'mj-regmgr-checkbox' }, [
                            h('input', {
                                type: 'checkbox',
                                checked: isChecked,
                                onChange: function () { onToggleWeekday(entry.key); },
                            }),
                            h('span', null, entry.label),
                        ]),
                        h('div', { class: 'mj-regmgr-recurring-weekday__times' }, [
                            h('input', {
                                type: 'time',
                                value: formatTimeValue(timeEntry.start || ''),
                                disabled: !isChecked,
                                onChange: function (e) { handleWeekdayTimeChange(entry.key, 'start', e); },
                                'aria-label': entry.label + ' ' + getString(strings, 'recurringStartTime', 'Debut'),
                            }),
                            h('input', {
                                type: 'time',
                                value: formatTimeValue(timeEntry.end || ''),
                                disabled: !isChecked,
                                onChange: function (e) { handleWeekdayTimeChange(entry.key, 'end', e); },
                                'aria-label': entry.label + ' ' + getString(strings, 'recurringEndTime', 'Fin'),
                            }),
                        ]),
                    ]);
                })),
                h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'recurringWeekdaysHint', "Cochez les jours souhaites et definissez les plages horaires pour chaque jour (optionnel).")),
            ]);
        };

        var renderRecurringMonthly = function () {
            if (recurringFrequency !== 'monthly') {
                return null;
            }
            return h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringOrdinal', 'Ordre')),
                    h('select', {
                        value: form.event_recurring_month_ordinal || 'first',
                        onChange: function (e) { onChangeForm('event_recurring_month_ordinal', e.target.value); },
                    }, ordinalEntries.map(function (option) {
                        return h('option', { key: option.key, value: option.key }, option.label);
                    })),
                ]),
                h('div', { class: 'mj-regmgr-form-field' }, [
                    h('label', null, getString(strings, 'recurringWeekday', 'Jour de semaine')),
                    h('select', {
                        value: form.event_recurring_month_weekday || 'saturday',
                        onChange: function (e) { onChangeForm('event_recurring_month_weekday', e.target.value); },
                    }, weekdayEntries.map(function (option) {
                        return h('option', { key: option.key, value: option.key }, option.label);
                    })),
                ]),
            ]);
        };

        var renderRecurringMode = function () {
            if (scheduleMode !== 'recurring') {
                return null;
            }
            return h('div', { class: 'mj-regmgr-recurring' }, [
                h('div', { class: 'mj-regmgr-form-grid' }, [
                    h('div', { class: 'mj-regmgr-form-field' }, [
                        h('label', null, getString(strings, 'recurringFrequency', 'Frequence')),
                        h('select', {
                            value: recurringFrequency,
                            onChange: function (e) { onChangeForm('event_recurring_frequency', e.target.value === 'monthly' ? 'monthly' : 'weekly'); },
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
                            value: form.event_recurring_interval || 1,
                            onChange: function (e) { onChangeForm('event_recurring_interval', e.target.value === '' ? '' : parseInt(e.target.value, 10) || 1); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-form-field' }, [
                        h('label', null, getString(strings, 'recurringStartDate', 'Jour')),
                        h('input', {
                            type: 'date',
                            value: form.event_recurring_start_date || '',
                            onChange: function (e) { handleRecurringFieldChange('event_recurring_start_date', e.target.value); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-form-field' }, [
                        h('label', null, getString(strings, 'recurringStartTime', 'Debut')),
                        h('input', {
                            type: 'time',
                            value: formatTimeValue(form.event_recurring_start_time || ''),
                            onChange: function (e) { handleRecurringFieldChange('event_recurring_start_time', e.target.value); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-form-field' }, [
                        h('label', null, getString(strings, 'recurringEndTime', 'Fin')),
                        h('input', {
                            type: 'time',
                            value: formatTimeValue(form.event_recurring_end_time || ''),
                            onChange: function (e) { handleRecurringFieldChange('event_recurring_end_time', e.target.value); },
                        }),
                    ]),
                    h('div', { class: 'mj-regmgr-form-field' }, [
                        h('label', null, getString(strings, 'recurringUntil', "Jusqu au (optionnel)")),
                        h('input', {
                            type: 'date',
                            value: form.event_recurring_until || '',
                            onChange: function (e) { onChangeForm('event_recurring_until', e.target.value); },
                        }),
                        h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'recurringUntilHint', 'Laisser vide pour poursuivre la recurrence sans date de fin.')),
                    ]),
                    h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox mj-regmgr-form-field--full' }, [
                        h('label', { class: 'mj-regmgr-checkbox' }, [
                            h('input', {
                                type: 'checkbox',
                                checked: showDateRange,
                                onChange: handleShowDateRangeToggle,
                            }),
                            h('span', null, getString(strings, 'recurringShowDateRange', 'Masquer la periode (date de debut et date de fin) sur la page evenement')),
                        ]),
                    ]),
                ]),
                renderRecurringWeekdays(),
                renderRecurringMonthly(),
                h('div', { class: 'mj-regmgr-recurring-agenda' }, [
                    h('div', { class: 'mj-regmgr-recurring-agenda__header' }, [
                        h('div', null, [
                            h('h3', null, getString(strings, 'recurringAgendaTitle', 'Occurrences planifiees')),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'recurringAgendaHint', 'Cliquez sur une date pour la desactiver ou la reactiver.')),
                        ]),
                        h('div', { class: 'mj-regmgr-recurring-agenda__summary' }, [
                            h('span', null, recurringAgendaSummary),
                            h('span', null, recurringCancelledSummary),
                            h('span', null, recurringExcludedSummary),
                        ]),
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--ghost mj-btn--small',
                            onClick: toggleAgendaVisibility,
                        }, agendaExpanded
                            ? getString(strings, 'recurringAgendaToggleHide', 'Masquer les occurrences')
                            : getString(strings, 'recurringAgendaToggleShow', 'Afficher les occurrences')),
                    ]),
                    agendaExpanded ? (recurringGroups.length > 0
                        ? h('div', { class: 'mj-regmgr-recurring-agenda__groups' }, recurringGroups.map(function (group) {
                            return h('div', { key: group.key, class: 'mj-regmgr-recurring-agenda__group' }, [
                                h('div', { class: 'mj-regmgr-recurring-agenda__group-header' }, [
                                    h('strong', null, group.label),
                                    h('span', null, group.summary),
                                ]),
                                h('div', { class: 'mj-regmgr-recurring-agenda__items' }, group.entries.map(function (entry, entryIndex) {
                                    var isCancelled = !!entry.reason;
                                    var isDisabled = !!entry.disabled;
                                    var statusLabel = '';
                                    if (isDisabled) {
                                        statusLabel = isCancelled
                                            ? getString(strings, 'recurringAgendaCancelled', 'Annule')
                                            : getString(strings, 'recurringAgendaDisabled', 'Exclu');
                                    }
                                    return h('button', {
                                        key: group.key + '-' + entryIndex,
                                        type: 'button',
                                        class: classNames('mj-regmgr-recurring-agenda__item', {
                                            'mj-regmgr-recurring-agenda__item--disabled': isDisabled,
                                            'mj-regmgr-recurring-agenda__item--cancelled': isCancelled,
                                        }),
                                        onClick: function () { onOpenExceptionDialog(entry); },
                                    }, [
                                        h('span', { class: 'mj-regmgr-recurring-agenda__item-date' }, entry.dateLabel || entry.date),
                                        entry.timeLabel ? h('span', { class: 'mj-regmgr-recurring-agenda__item-time' }, entry.timeLabel) : null,
                                        statusLabel ? h('span', { class: 'mj-regmgr-recurring-agenda__item-status' }, statusLabel) : null,
                                        entry.reason ? h('span', { class: 'mj-regmgr-recurring-agenda__item-reason' }, entry.reason) : null,
                                    ]);
                                })),
                            ]);
                        }))
                        : h('p', { class: 'mj-regmgr-recurring-agenda__empty' }, getString(strings, 'recurringAgendaEmpty', 'Configurez la recurrence pour visualiser les occurrences.')))
                        : null,
                ]),
            ]);
        };

        var renderSeriesItems = function () {
            return h('div', { class: 'mj-regmgr-series-items' }, [
                seriesItems.length === 0 ? h('p', { class: 'mj-regmgr-series-items__empty' }, getString(strings, 'seriesEmpty', 'Aucune date ajoutee.')) : null,
                seriesItems.map(function (item, index) {
                    return h('div', { key: index, class: 'mj-regmgr-form-grid mj-regmgr-series-items__row' }, [
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'fixedDate', 'Jour')),
                            h('input', {
                                type: 'date',
                                value: item.date || '',
                                onChange: function (e) { onUpdateSeriesItem(index, 'date', e.target.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'recurringStartTime', 'Debut')),
                            h('input', {
                                type: 'time',
                                value: formatTimeValue(item.start_time || ''),
                                onChange: function (e) { onUpdateSeriesItem(index, 'start_time', e.target.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'recurringEndTime', 'Fin')),
                            h('input', {
                                type: 'time',
                                value: formatTimeValue(item.end_time || ''),
                                onChange: function (e) { onUpdateSeriesItem(index, 'end_time', e.target.value); },
                            }),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--actions' }, [
                            h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--ghost mj-btn--small',
                                onClick: function () { onRemoveSeriesItem(index); },
                            }, getString(strings, 'remove', 'Supprimer')),
                        ]),
                    ]);
                }),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost',
                    onClick: onAddSeriesItem,
                }, getString(strings, 'seriesAdd', 'Ajouter une date')),
            ]);
        };

        var renderSeriesMode = function () {
            if (scheduleMode !== 'series') {
                return null;
            }
            return h('div', { class: 'mj-regmgr-series' }, [
                h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'seriesDescription', 'Ajoutez chaque date de facon individuelle. Utilisez ce mode pour un planning non recurrent.')),
                renderSeriesItems(),
            ]);
        };

        return h('div', { class: 'mj-regmgr-event-editor__section' }, [
            h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                h('h2', null, getString(strings, 'scheduleSection', 'Planification')),
                h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'scheduleSectionHint', "Definissez les dates et la frequence de l evenement.")),
            ]),
            h('div', { class: 'mj-regmgr-form-grid' }, [
                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                    h('label', null, getString(strings, 'scheduleMode', 'Mode de planification')),
                    h('select', {
                        value: scheduleMode,
                        onChange: handleModeChange,
                    }, [
                        h('option', { value: 'fixed' }, getString(strings, 'scheduleModeFixed', 'Date fixe (debut et fin le meme jour)')),
                        h('option', { value: 'range' }, getString(strings, 'scheduleModeRange', 'Plage de dates (plusieurs jours consecutifs)')),
                        h('option', { value: 'recurring' }, getString(strings, 'scheduleModeRecurring', 'Recurrence (hebdomadaire ou mensuelle)')),
                        h('option', { value: 'series' }, getString(strings, 'scheduleModeSeries', 'Serie de dates personnalisees')),
                    ]),
                    h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'scheduleModeHint', 'Choisissez la facon de planifier l evenement; les sections ci dessous s adaptent au mode selectionne.')),
                ]),
            ]),
            renderFixedMode(),
            renderRangeMode(),
            renderRecurringMode(),
            renderSeriesMode(),
        ]);
    */
    }

    // ============================================
    // LOCATION LINKS EDITOR
    // ============================================

    var LOCATION_TYPE_OPTIONS = [
        { value: 'departure', labelKey: 'locationTypeDeparture', defaultLabel: 'Lieu de depart' },
        { value: 'activity', labelKey: 'locationTypeActivity', defaultLabel: "Lieu d activite" },
        { value: 'return', labelKey: 'locationTypeReturn', defaultLabel: 'Lieu de retour' },
        { value: 'other', labelKey: 'locationTypeOther', defaultLabel: 'Autre' },
    ];

    function LocationLinkRow(props) {
        var link = props.link;
        var index = props.index;
        var locationChoices = props.locationChoices || {};
        var strings = props.strings;
        var onUpdate = props.onUpdate;
        var onRemove = props.onRemove;
        var onEditLocation = props.onEditLocation;
        var canManage = props.canManage;

        var handleLocationChange = function (e) {
            var newId = parseInt(e.target.value, 10) || 0;
            onUpdate(index, { locationId: newId, locationType: link.locationType, customLabel: link.customLabel || '', meetingTime: link.meetingTime || '', meetingTimeEnd: link.meetingTimeEnd || '' });
        };

        var handleTypeChange = function (e) {
            var newType = e.target.value;
            // Reset customLabel if not "other"
            var newCustomLabel = newType === 'other' ? (link.customLabel || '') : '';
            onUpdate(index, { locationId: link.locationId, locationType: newType, customLabel: newCustomLabel, meetingTime: link.meetingTime || '', meetingTimeEnd: link.meetingTimeEnd || '' });
        };

        var handleCustomLabelChange = function (e) {
            onUpdate(index, { locationId: link.locationId, locationType: link.locationType, customLabel: e.target.value, meetingTime: link.meetingTime || '', meetingTimeEnd: link.meetingTimeEnd || '' });
        };

        var handleMeetingTimeChange = function (e) {
            onUpdate(index, { locationId: link.locationId, locationType: link.locationType, customLabel: link.customLabel || '', meetingTime: e.target.value, meetingTimeEnd: link.meetingTimeEnd || '' });
        };

        var handleMeetingTimeEndChange = function (e) {
            onUpdate(index, { locationId: link.locationId, locationType: link.locationType, customLabel: link.customLabel || '', meetingTime: link.meetingTime || '', meetingTimeEnd: e.target.value });
        };

        var handleRemove = function () {
            onRemove(index);
        };

        var handleEdit = function () {
            if (link.locationId > 0 && onEditLocation) {
                onEditLocation(link.locationId);
            }
        };

        var locationName = locationChoices[String(link.locationId)] || '';
        var isOtherType = link.locationType === 'other';

        return h('div', { class: 'mj-regmgr-location-link-row' }, [
            h('div', { class: 'mj-regmgr-location-link-row__location' }, [
                h('select', {
                    value: String(link.locationId || 0),
                    onChange: handleLocationChange,
                    class: 'mj-regmgr-location-link-row__select',
                }, [
                    h('option', { value: '0' }, getString(strings, 'locationSelectPlaceholder', 'Choisir un lieu')),
                    Object.keys(locationChoices).map(function (key) {
                        return h('option', { key: key, value: key }, locationChoices[key]);
                    }),
                ]),
            ]),
            h('div', { class: 'mj-regmgr-location-link-row__type' }, [
                h('select', {
                    value: link.locationType || 'activity',
                    onChange: handleTypeChange,
                    class: 'mj-regmgr-location-link-row__type-select',
                }, LOCATION_TYPE_OPTIONS.map(function (opt) {
                    return h('option', { key: opt.value, value: opt.value }, getString(strings, opt.labelKey, opt.defaultLabel));
                })),
            ]),
            isOtherType && h('div', { class: 'mj-regmgr-location-link-row__custom-label' }, [
                h('textarea', {
                    value: link.customLabel || '',
                    onInput: handleCustomLabelChange,
                    placeholder: getString(strings, 'customLabelPlaceholder', 'Précisez le type...'),
                    class: 'mj-regmgr-location-link-row__custom-input',
                    maxLength: 200,
                    rows: 2,
                }),
            ]),
            h('div', { class: 'mj-regmgr-location-link-row__meeting-time' }, [
                h('input', {
                    type: 'time',
                    value: link.meetingTime || '',
                    onChange: handleMeetingTimeChange,
                    class: 'mj-regmgr-location-link-row__time-input',
                    title: getString(strings, 'meetingTimePlaceholder', 'Heure de RDV (début)'),
                }),
                h('span', { class: 'mj-regmgr-location-link-row__time-separator' }, '-'),
                h('input', {
                    type: 'time',
                    value: link.meetingTimeEnd || '',
                    onChange: handleMeetingTimeEndChange,
                    class: 'mj-regmgr-location-link-row__time-input',
                    title: getString(strings, 'meetingTimeEndPlaceholder', 'Heure de fin (optionnel)'),
                }),
            ]),
            h('div', { class: 'mj-regmgr-location-link-row__actions' }, [
                canManage && link.locationId > 0 && h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost mj-btn--tiny',
                    onClick: handleEdit,
                    title: getString(strings, 'editLocation', 'Modifier le lieu'),
                }, [
                    h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                        h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                    ]),
                ]),
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost mj-btn--tiny mj-btn--danger',
                    onClick: handleRemove,
                    title: getString(strings, 'removeLocationLink', 'Retirer'),
                }, [
                    h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                        h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                    ]),
                ]),
            ]),
        ]);
    }

    function LocationLinksEditor(props) {
        var links = props.links || [];
        var locationChoices = props.locationChoices || {};
        var strings = props.strings;
        var onChange = props.onChange;
        var onCreateLocation = props.onCreateLocation;
        var onEditLocation = props.onEditLocation;
        var canManage = props.canManage;

        var handleAddLink = function () {
            var newLinks = links.slice();
            newLinks.push({ locationId: 0, locationType: 'activity', customLabel: '', meetingTime: '', meetingTimeEnd: '', sortOrder: newLinks.length });
            onChange(newLinks);
        };

        var handleUpdateLink = function (index, updatedLink) {
            var newLinks = links.slice();
            newLinks[index] = {
                locationId: updatedLink.locationId,
                locationType: updatedLink.locationType,
                customLabel: updatedLink.customLabel || '',
                meetingTime: updatedLink.meetingTime || '',
                meetingTimeEnd: updatedLink.meetingTimeEnd || '',
                sortOrder: index,
            };
            onChange(newLinks);
        };

        var handleRemoveLink = function (index) {
            var newLinks = links.slice();
            newLinks.splice(index, 1);
            // Re-index sortOrder
            newLinks = newLinks.map(function (link, idx) {
                return { locationId: link.locationId, locationType: link.locationType, customLabel: link.customLabel || '', meetingTime: link.meetingTime || '', meetingTimeEnd: link.meetingTimeEnd || '', sortOrder: idx };
            });
            onChange(newLinks);
        };

        var handleEditLocation = function (locationId) {
            if (onEditLocation) {
                onEditLocation(locationId);
            }
        };

        return h('div', { class: 'mj-regmgr-location-links' }, [
            links.length === 0 && h('p', { class: 'mj-regmgr-location-links__empty' }, getString(strings, 'locationLinksEmpty', 'Aucun lieu associe.')),
            links.length > 0 && h('div', { class: 'mj-regmgr-location-links__list' }, links.map(function (link, idx) {
                return h(LocationLinkRow, {
                    key: idx,
                    link: link,
                    index: idx,
                    locationChoices: locationChoices,
                    strings: strings,
                    onUpdate: handleUpdateLink,
                    onRemove: handleRemoveLink,
                    onEditLocation: handleEditLocation,
                    canManage: canManage,
                });
            })),
            h('div', { class: 'mj-regmgr-location-links__actions' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost mj-btn--small',
                    onClick: handleAddLink,
                }, getString(strings, 'addLocationLink', 'Ajouter un lieu')),
                canManage && h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost mj-btn--small',
                    onClick: onCreateLocation,
                }, getString(strings, 'addLocation', 'Ajouter un nouveau lieu')),
            ]),
            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'locationLinksHint', 'Ajoutez plusieurs lieux avec differents roles: depart, activite, retour, etc.')),
        ]);
    }

    // ============================================
    // EVENT EDITOR
    // ============================================

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

        var _locationOptionsState = useState(function () {
            var baseChoices = {};
            var baseAttributes = {};
            if (initialOptions && initialOptions.locations) {
                Object.keys(initialOptions.locations).forEach(function (key) {
                    baseChoices[String(key)] = initialOptions.locations[key];
                });
            }
            if (initialOptions && initialOptions.location_choice_attributes) {
                Object.keys(initialOptions.location_choice_attributes).forEach(function (key) {
                    baseAttributes[String(key)] = initialOptions.location_choice_attributes[key];
                });
            }
            return {
                choices: baseChoices,
                attributes: baseAttributes,
            };
        });
        var locationOptions = _locationOptionsState[0];
        var setLocationOptions = _locationOptionsState[1];

        var _formState = useState(function () {
            return normalizeEventFormValues(initialValues || {});
        });
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

        var _locationLinks = useState(function () {
            if (initialValues && Array.isArray(initialValues.event_location_links)) {
                return initialValues.event_location_links.map(function (link, idx) {
                    return {
                        locationId: parseInt(link.location_id || link.locationId || 0, 10),
                        locationType: link.location_type || link.locationType || 'activity',
                        customLabel: link.custom_label || link.customLabel || '',
                        meetingTime: link.meeting_time || link.meetingTime || '',
                        meetingTimeEnd: link.meeting_time_end || link.meetingTimeEnd || '',
                        sortOrder: typeof link.sort_order !== 'undefined' ? link.sort_order : idx,
                    };
                });
            }
            return [];
        });
        var locationLinks = _locationLinks[0];
        var setLocationLinks = _locationLinks[1];

        var _exceptionDialogState = useState({
            isOpen: false,
            date: '',
            dateLabel: '',
            timeLabel: '',
            reason: '',
            mode: 'exclude',
            weekLabel: '',
            weekSummary: '',
            initiallyDisabled: false,
        });
        var exceptionDialogState = _exceptionDialogState[0];
        var setExceptionDialogState = _exceptionDialogState[1];

        var _exceptionDialogError = useState('');
        var exceptionDialogError = _exceptionDialogError[0];
        var setExceptionDialogError = _exceptionDialogError[1];

        var mediaFrameRef = useRef(null);
        var previousTypeRef = useRef(initialValues && initialValues.event_type ? initialValues.event_type : '');
        var manageLocationEnabled = !!(props.canManageLocations && typeof props.onManageLocation === 'function');
        var onManageLocation = manageLocationEnabled ? props.onManageLocation : null;
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
        var attendanceAllMembersId = useMemo(function () {
            return 'mj-regmgr-attendance-all-members-' + Math.random().toString(36).slice(2);
        }, []);
        var exceptionRadioName = useMemo(function () {
            return 'mj-regmgr-agenda-exception-' + Math.random().toString(36).slice(2);
        }, []);
        var exceptionReasonFieldId = useMemo(function () {
            return 'mj-regmgr-agenda-reason-' + Math.random().toString(36).slice(2);
        }, []);

        useEffect(function () {
            if (!data) {
                setFormState({});
                setMetaState({});
                setSeriesItems([]);
                setLocationLinks([]);
                setIsDirty(false);
                setCoverPreview('');
                previousTypeRef.current = '';
                return;
            }
            var nextValues = data && data.values ? normalizeEventFormValues(data.values) : {};
            var nextMeta = data.meta ? JSON.parse(JSON.stringify(data.meta)) : {};
            nextMeta.scheduleExceptions = normalizeScheduleExceptions(nextMeta.scheduleExceptions);
            if (nextMeta.schedulePayload && typeof nextMeta.schedulePayload === 'object') {
                var payloadClone = Object.assign({}, nextMeta.schedulePayload);
                if (Array.isArray(payloadClone.exceptions)) {
                    payloadClone.exceptions = normalizeScheduleExceptions(payloadClone.exceptions);
                }
                nextMeta.schedulePayload = payloadClone;
            }
            setFormState(nextValues);
            setMetaState(nextMeta);
            setSeriesItems(parseSeriesItems(nextValues.event_series_items));
            // Parse location links from values
            var nextLocationLinks = [];
            if (nextValues.event_location_links && Array.isArray(nextValues.event_location_links)) {
                nextLocationLinks = nextValues.event_location_links.map(function (link, idx) {
                    return {
                        locationId: parseInt(link.location_id || link.locationId || 0, 10),
                        locationType: link.location_type || link.locationType || 'activity',
                        customLabel: link.custom_label || link.customLabel || '',
                        meetingTime: link.meeting_time || link.meetingTime || '',
                        meetingTimeEnd: link.meeting_time_end || link.meetingTimeEnd || '',
                        sortOrder: typeof link.sort_order !== 'undefined' ? link.sort_order : idx,
                    };
                });
            }
            setLocationLinks(nextLocationLinks);
            setIsDirty(false);
            var nextCover = eventSummary && eventSummary.coverUrl ? eventSummary.coverUrl : '';
            setCoverPreview(nextCover);
            previousTypeRef.current = nextValues.event_type || '';
        }, [data, eventSummary]);

        useEffect(function () {
            if (!data || !data.options) {
                setLocationOptions({ choices: {}, attributes: {} });
                return;
            }
            var baseChoices = {};
            var baseAttributes = {};
            if (data.options.locations) {
                Object.keys(data.options.locations).forEach(function (key) {
                    baseChoices[String(key)] = data.options.locations[key];
                });
            }
            if (data.options.location_choice_attributes) {
                Object.keys(data.options.location_choice_attributes).forEach(function (key) {
                    baseAttributes[String(key)] = data.options.location_choice_attributes[key];
                });
            }
            setLocationOptions({
                choices: baseChoices,
                attributes: baseAttributes,
            });
        }, [data, setLocationOptions]);

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

            var defaultAccent = '';
            if (typeDefaultColors && Object.prototype.hasOwnProperty.call(typeDefaultColors, currentType)) {
                defaultAccent = typeDefaultColors[currentType] || '';
            }

            var normalizedDefault = defaultAccent ? normalizeHexColor(defaultAccent) || defaultAccent : '';
            var currentAccent = formState.event_accent_color || '';
            var normalizedCurrent = currentAccent ? normalizeHexColor(currentAccent) || currentAccent : '';

            if (normalizedDefault !== normalizedCurrent) {
                updateFormValue('event_accent_color', normalizedDefault);
            }
        }, [formState.event_type, typeDefaultColors, updateFormValue, formState.event_accent_color]);

        var updateFormValue = useCallback(function (key, value) {
            setFormState(function (prev) {
                var next = Object.assign({}, prev);
                next[key] = key === 'event_emoji' ? sanitizeEmojiValue(value) : value;
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

        var handleLocationSaved = useCallback(function (result) {
            if (!result || !result.option) {
                return;
            }
            var option = result.option;
            var rawId = option && Object.prototype.hasOwnProperty.call(option, 'id') ? option.id : null;
            var optionId = parseInt(rawId, 10);
            if (isNaN(optionId) || optionId <= 0) {
                return;
            }
            var choiceKey = String(optionId);
            setLocationOptions(function (prev) {
                var nextChoices = Object.assign({}, prev.choices);
                nextChoices[choiceKey] = option.label || nextChoices[choiceKey] || 'Lieu #' + optionId;
                var nextAttributes = Object.assign({}, prev.attributes);
                if (option.attributes && typeof option.attributes === 'object') {
                    nextAttributes[choiceKey] = option.attributes;
                }
                return {
                    choices: nextChoices,
                    attributes: nextAttributes,
                };
            });
            updateFormValue('event_location_id', optionId);
        }, [setLocationOptions, updateFormValue]);

        var handleCreateLocation = useCallback(function () {
            if (!onManageLocation) {
                return;
            }
            onManageLocation({
                mode: 'create',
                onComplete: handleLocationSaved,
            });
        }, [onManageLocation, handleLocationSaved]);

        var handleEditLocation = useCallback(function () {
            if (!onManageLocation) {
                return;
            }
            var currentId = formState.event_location_id ? parseInt(formState.event_location_id, 10) || 0 : 0;
            if (currentId <= 0) {
                return;
            }
            onManageLocation({
                mode: 'edit',
                locationId: currentId,
                onComplete: handleLocationSaved,
            });
        }, [onManageLocation, formState.event_location_id, handleLocationSaved]);

        // Handler for location links changes
        var handleLocationLinksChange = useCallback(function (newLinks) {
            setLocationLinks(newLinks);
            setIsDirty(true);
        }, [setLocationLinks, setIsDirty]);

        // Handler when a new location is created for use in location links
        var handleLocationSavedForLinks = useCallback(function (result) {
            if (!result || !result.option) {
                return;
            }
            var option = result.option;
            var rawId = option && Object.prototype.hasOwnProperty.call(option, 'id') ? option.id : null;
            var optionId = parseInt(rawId, 10);
            if (isNaN(optionId) || optionId <= 0) {
                return;
            }
            var choiceKey = String(optionId);
            setLocationOptions(function (prev) {
                var nextChoices = Object.assign({}, prev.choices);
                nextChoices[choiceKey] = option.label || nextChoices[choiceKey] || 'Lieu #' + optionId;
                var nextAttributes = Object.assign({}, prev.attributes);
                if (option.attributes && typeof option.attributes === 'object') {
                    nextAttributes[choiceKey] = option.attributes;
                }
                return {
                    choices: nextChoices,
                    attributes: nextAttributes,
                };
            });
            // Auto-add the newly created location to links
            setLocationLinks(function (prev) {
                return prev.concat([{
                    locationId: optionId,
                    locationType: 'activity',
                    sortOrder: prev.length,
                }]);
            });
            setIsDirty(true);
        }, [setLocationOptions, setLocationLinks, setIsDirty]);

        var handleCreateLocationForLinks = useCallback(function () {
            if (!onManageLocation) {
                return;
            }
            onManageLocation({
                mode: 'create',
                onComplete: handleLocationSavedForLinks,
            });
        }, [onManageLocation, handleLocationSavedForLinks]);

        var handleEditLocationForLinks = useCallback(function (locationId) {
            if (!onManageLocation) {
                return;
            }
            if (!locationId || locationId <= 0) {
                return;
            }
            onManageLocation({
                mode: 'edit',
                locationId: locationId,
                onComplete: function (result) {
                    // Just update the location options, no need to change links
                    if (!result || !result.option) {
                        return;
                    }
                    var option = result.option;
                    var rawId = option && Object.prototype.hasOwnProperty.call(option, 'id') ? option.id : null;
                    var optionId = parseInt(rawId, 10);
                    if (isNaN(optionId) || optionId <= 0) {
                        return;
                    }
                    var choiceKey = String(optionId);
                    setLocationOptions(function (prev) {
                        var nextChoices = Object.assign({}, prev.choices);
                        nextChoices[choiceKey] = option.label || nextChoices[choiceKey] || 'Lieu #' + optionId;
                        var nextAttributes = Object.assign({}, prev.attributes);
                        if (option.attributes && typeof option.attributes === 'object') {
                            nextAttributes[choiceKey] = option.attributes;
                        }
                        return {
                            choices: nextChoices,
                            attributes: nextAttributes,
                        };
                    });
                },
            });
        }, [onManageLocation, setLocationOptions]);

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

        var applyScheduleException = useCallback(function (date, action, reason) {
            var normalized = normalizeIsoDate(date);
            if (!normalized) {
                return;
            }
            setMetaState(function (prev) {
                var next = Object.assign({}, prev);
                var current = normalizeScheduleExceptions(next.scheduleExceptions);
                var index = findScheduleExceptionIndex(current, normalized);

                if (action === 'keep') {
                    if (index !== -1) {
                        current.splice(index, 1);
                    }
                } else if (action === 'exclude') {
                    if (index === -1) {
                        current.push({ date: normalized });
                    } else {
                        current[index] = { date: normalized };
                    }
                } else if (action === 'cancel') {
                    var sanitized = sanitizeExceptionReason(reason);
                    if (!sanitized) {
                        sanitized = '';
                    }
                    if (index === -1) {
                        current.push({ date: normalized, reason: sanitized });
                    } else {
                        current[index] = { date: normalized, reason: sanitized };
                    }
                }

                next.scheduleExceptions = normalizeScheduleExceptions(current);
                return next;
            });
            setIsDirty(true);
        }, [setMetaState, setIsDirty]);

        var legacyToggleException = useCallback(function (occurrence) {
            if (!occurrence || !occurrence.date) {
                return;
            }
            var normalized = normalizeIsoDate(occurrence.date);
            if (!normalized) {
                return;
            }
            setMetaState(function (prev) {
                var next = Object.assign({}, prev);
                var current = normalizeScheduleExceptions(next.scheduleExceptions);
                var index = findScheduleExceptionIndex(current, normalized);
                if (index === -1) {
                    var reason = '';
                    if (typeof window !== 'undefined' && typeof window.prompt === 'function') {
                        var promptLabel = getString(strings, 'recurringAgendaCancelPrompt', "Motif d'annulation (optionnel)");
                        var response = window.prompt(promptLabel, '');
                        reason = sanitizeExceptionReason(response);
                    }
                    current.push(reason ? { date: normalized, reason: reason } : { date: normalized });
                } else {
                    current.splice(index, 1);
                }
                next.scheduleExceptions = normalizeScheduleExceptions(current);
                return next;
            });
            setIsDirty(true);
        }, [setMetaState, setIsDirty, strings]);

        var closeExceptionDialog = useCallback(function () {
            setExceptionDialogState({
                isOpen: false,
                date: '',
                dateLabel: '',
                timeLabel: '',
                reason: '',
                mode: 'exclude',
                weekLabel: '',
                weekSummary: '',
                initiallyDisabled: false,
            });
            setExceptionDialogError('');
        }, []);

        var openExceptionDialog = useCallback(function (occurrence) {
            if (!occurrence || !occurrence.date) {
                return;
            }
            if (!Modal) {
                legacyToggleException(occurrence);
                return;
            }
            var initialMode = 'exclude';
            if (occurrence.disabled) {
                initialMode = occurrence.reason ? 'cancel' : 'exclude';
            }
            setExceptionDialogState({
                isOpen: true,
                date: occurrence.date,
                dateLabel: occurrence.dateLabel || occurrence.date,
                timeLabel: occurrence.timeLabel || '',
                reason: occurrence.reason || '',
                mode: initialMode,
                weekLabel: occurrence.weekLabel || '',
                weekSummary: occurrence.weekSummary || '',
                initiallyDisabled: !!occurrence.disabled,
            });
            setExceptionDialogError('');
        }, [Modal, legacyToggleException]);

        var handleExceptionModeChange = useCallback(function (mode) {
            setExceptionDialogState(function (prev) {
                return Object.assign({}, prev, { mode: mode });
            });
            setExceptionDialogError('');
        }, []);

        var handleExceptionReasonChange = useCallback(function (value) {
            setExceptionDialogState(function (prev) {
                return Object.assign({}, prev, { reason: value });
            });
            setExceptionDialogError('');
        }, []);

        var handleExceptionRestore = useCallback(function () {
            var state = exceptionDialogState;
            if (!state.isOpen || !state.date) {
                closeExceptionDialog();
                return;
            }
            applyScheduleException(state.date, 'keep', '');
            closeExceptionDialog();
        }, [exceptionDialogState, applyScheduleException, closeExceptionDialog]);

        var handleExceptionSave = useCallback(function () {
            var state = exceptionDialogState;
            if (!state.isOpen || !state.date) {
                closeExceptionDialog();
                return;
            }
            if (state.mode === 'cancel') {
                var sanitizedReason = sanitizeExceptionReason(state.reason);
                if (!sanitizedReason) {
                    setExceptionDialogError(getString(strings, 'recurringAgendaExceptionMissingReason', 'Indiquez un motif pour confirmer l annulation.'));
                    return;
                }
                applyScheduleException(state.date, 'cancel', sanitizedReason);
            } else {
                applyScheduleException(state.date, 'exclude', '');
            }
            closeExceptionDialog();
        }, [exceptionDialogState, applyScheduleException, closeExceptionDialog, strings]);

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
            var activeScheduleMode = normalizeScheduleMode(payloadForm.event_schedule_mode);
            if (activeScheduleMode === 'fixed') {
                var startParts = splitDateTimeParts(payloadForm.event_date_start);
                var endParts = splitDateTimeParts(payloadForm.event_date_end);
                if (!payloadForm.event_fixed_date && startParts.date) {
                    payloadForm.event_fixed_date = startParts.date;
                }
                if (!payloadForm.event_fixed_start_time && startParts.time) {
                    payloadForm.event_fixed_start_time = startParts.time;
                }
                if (!payloadForm.event_fixed_end_time) {
                    if (endParts.time) {
                        payloadForm.event_fixed_end_time = endParts.time;
                    } else if (startParts.time) {
                        payloadForm.event_fixed_end_time = startParts.time;
                    }
                }
            }
            payloadForm.event_series_items = JSON.stringify(seriesItems);
            var normalizedScheduleExceptions = normalizeScheduleExceptions(metaState.scheduleExceptions);
            if (Object.prototype.hasOwnProperty.call(payloadForm, 'event_emoji')) {
                payloadForm.event_emoji = sanitizeEmojiValue(payloadForm.event_emoji);
            }
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
            // Include location links in payload
            payloadForm.event_location_links = locationLinks.map(function (link, idx) {
                return {
                    location_id: parseInt(link.locationId, 10) || 0,
                    location_type: link.locationType || 'activity',
                    custom_label: link.customLabel || '',
                    meeting_time: link.meetingTime || '',
                    meeting_time_end: link.meetingTimeEnd || '',
                    sort_order: idx,
                };
            }).filter(function (link) {
                return link.location_id > 0;
            });
            payloadForm.event_schedule_exceptions = normalizedScheduleExceptions;
            var resolvedRecurringStart = normalizeTime(payloadForm.event_recurring_start_time);
            if (!resolvedRecurringStart) {
                resolvedRecurringStart = pickTimeFromWeekdayTimes(metaState.scheduleWeekdayTimes, 'start', true);
            }
            if (!resolvedRecurringStart && metaState.schedulePayload && typeof metaState.schedulePayload === 'object') {
                resolvedRecurringStart = normalizeTime(metaState.schedulePayload.start_time);
            }
            if (!resolvedRecurringStart) {
                resolvedRecurringStart = extractTimeFromDateValue(payloadForm.event_date_start);
            }
            if (resolvedRecurringStart) {
                payloadForm.event_recurring_start_time = resolvedRecurringStart;
            }

            var resolvedRecurringEnd = normalizeTime(payloadForm.event_recurring_end_time);
            if (!resolvedRecurringEnd) {
                resolvedRecurringEnd = pickTimeFromWeekdayTimes(metaState.scheduleWeekdayTimes, 'end', false);
            }
            if (!resolvedRecurringEnd && metaState.schedulePayload && typeof metaState.schedulePayload === 'object') {
                resolvedRecurringEnd = normalizeTime(metaState.schedulePayload.end_time);
            }
            if (!resolvedRecurringEnd) {
                resolvedRecurringEnd = extractTimeFromDateValue(payloadForm.event_date_end);
            }
            if (!resolvedRecurringEnd && resolvedRecurringStart) {
                resolvedRecurringEnd = resolvedRecurringStart;
            }
            if (resolvedRecurringEnd) {
                payloadForm.event_recurring_end_time = resolvedRecurringEnd;
            }
            var payloadMeta = Object.assign({}, metaState);
            if (payloadMeta && typeof payloadMeta === 'object') {
                var attendanceFlag = !!payloadForm.event_attendance_show_all_members;
                if (payloadMeta.registrationPayload && typeof payloadMeta.registrationPayload === 'object') {
                    payloadMeta.registrationPayload = Object.assign({}, payloadMeta.registrationPayload, {
                        attendance_show_all_members: attendanceFlag,
                    });
                } else {
                    payloadMeta.registrationPayload = {
                        attendance_show_all_members: attendanceFlag,
                    };
                }
                if (resolvedRecurringStart || resolvedRecurringEnd) {
                    var existingPayload = payloadMeta.schedulePayload && typeof payloadMeta.schedulePayload === 'object' ? payloadMeta.schedulePayload : {};
                    payloadMeta.schedulePayload = Object.assign({}, existingPayload);
                    if (resolvedRecurringStart) {
                        payloadMeta.schedulePayload.start_time = resolvedRecurringStart;
                    }
                    if (resolvedRecurringEnd) {
                        payloadMeta.schedulePayload.end_time = resolvedRecurringEnd;
                    }
                    payloadMeta.schedulePayload.exceptions = normalizedScheduleExceptions;
                }
                payloadMeta.scheduleExceptions = normalizedScheduleExceptions;
            }
            var result = onSubmit(payloadForm, payloadMeta);
            if (result && typeof result.then === 'function') {
                result.then(function () {
                    setIsDirty(false);
                }).catch(function () {
                    // keep dirty flag so user can retry
                });
            }
        }, [onSubmit, formState, metaState, seriesItems, locationLinks]);

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
        var locationSelectValue = formState.event_location_id ? String(formState.event_location_id) : '0';
        var currentLocationSelected = parseInt(locationSelectValue, 10) || 0;

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
                            h('label', null, getString(strings, 'eventSlug', 'Slug')),
                            h('input', {
                                type: 'text',
                                value: formState.event_slug || '',
                                onChange: function (e) { updateFormValue('event_slug', e.target.value); },
                                placeholder: 'ex-soiree-jeux-de-societe',
                            }),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'eventSlugHint', 'Identifiant URL. Laissez vide pour generer automatiquement depuis le titre.')),
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
                            h('label', null, getString(strings, 'eventEmoji', 'Emoticone')),
                            h(EmojiPickerField, {
                                value: formState.event_emoji || '',
                                onChange: function (value) { updateFormValue('event_emoji', value); },
                                strings: strings,
                                disabled: loading || saving,
                            }),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'eventEmojiHint', 'Facultatif, s affiche dans les listes et apercus.')),
                        ]),
                        h('div', { class: 'mj-regmgr-form-field' }, [
                            h('label', null, getString(strings, 'accentColor', 'Couleur pastel')),
                            h('div', { class: 'mj-regmgr-color-input' }, [
                                h('input', {
                                    type: 'color',
                                    value: normalizeHexColor(formState.event_accent_color || '') || '#000000',
                                    onChange: function (e) { updateFormValue('event_accent_color', e.target.value); },
                                }),
                                h('input', {
                                    type: 'text',
                                    value: formState.event_accent_color || '',
                                    onChange: function (e) { updateFormValue('event_accent_color', e.target.value); },
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

                h('div', { class: 'mj-regmgr-event-editor__section' }, [
                    h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                        h('h2', null, getString(strings, 'locationSection', 'Lieux')),
                        h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'locationSectionHint', "Associez un ou plusieurs lieux a l'evenement avec leur role.")),
                    ]),
                    h(LocationLinksEditor, {
                        links: locationLinks,
                        locationChoices: locationOptions.choices || {},
                        strings: strings,
                        onChange: handleLocationLinksChange,
                        onCreateLocation: handleCreateLocationForLinks,
                        onEditLocation: handleEditLocationForLinks,
                        canManage: manageLocationEnabled,
                    }),
                ]),

                (animateurOptions.length > 0 || volunteerOptions.length > 0) && h('div', { class: 'mj-regmgr-event-editor__section' }, [
                    h('div', { class: 'mj-regmgr-event-editor__section-header' }, [
                        h('h2', null, getString(strings, 'teamSection', 'Equipe')),
                        h('p', { class: 'mj-regmgr-event-editor__section-hint' }, getString(strings, 'teamSectionHint', "Selectionnez les animateurs et benevoles referents pour cet evenement.")),
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
                        h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full mj-regmgr-form-field--checkbox' }, [
                            h('span', { class: 'mj-regmgr-form-label' }, getString(strings, 'attendanceList', 'Liste de presence')),
                            h('label', { class: 'mj-regmgr-checkbox' }, [
                                h('input', {
                                    type: 'checkbox',
                                    id: attendanceAllMembersId,
                                    checked: !!formState.event_attendance_show_all_members,
                                    onChange: function (e) { updateFormValue('event_attendance_show_all_members', e.target.checked); },
                                }),
                                h('span', null, getString(strings, 'attendanceAllMembersToggle', 'Afficher tous les membres dans la liste de presence')),
                            ]),
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'attendanceAllMembersHint', 'Permet de pointer les membres autorises meme sans inscription prealable.')),
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
                            h('p', { class: 'mj-regmgr-field-hint' }, getString(strings, 'registrationDeadlineHint', "Laisser vide pour ne pas fixer de date limite.")),
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
            ]),

            Modal && h(Modal, {
                isOpen: exceptionDialogState.isOpen,
                onClose: closeExceptionDialog,
                title: getString(strings, 'recurringAgendaExceptionTitle', "Gestion de l occurrence"),
                size: 'medium',
                footer: h('div', { class: 'mj-regmgr-agenda-modal__actions' }, [
                    exceptionDialogState.initiallyDisabled ? h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--ghost',
                        style: { marginRight: 'auto' },
                        onClick: handleExceptionRestore,
                    }, getString(strings, 'recurringAgendaExceptionRestore', "Reactiver l occurrence")) : null,
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--ghost',
                        onClick: closeExceptionDialog,
                    }, getString(strings, 'recurringAgendaExceptionCancel', 'Fermer')),
                    h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--primary',
                        onClick: handleExceptionSave,
                    }, getString(strings, 'recurringAgendaExceptionSave', 'Enregistrer')),
                ]),
            }, [
                h('div', { class: 'mj-regmgr-agenda-modal' }, [
                    h('p', { class: 'mj-regmgr-agenda-modal__subtitle' }, getString(strings, 'recurringAgendaExceptionSubtitle', 'Choisissez comment traiter cette occurrence.')),
                    (exceptionDialogState.weekLabel || exceptionDialogState.weekSummary) && h('p', { class: 'mj-regmgr-agenda-modal__subtitle' }, [
                        exceptionDialogState.weekLabel || '',
                        exceptionDialogState.weekLabel && exceptionDialogState.weekSummary ? ' · ' : '',
                        exceptionDialogState.weekSummary || '',
                    ]),
                    h('div', { class: 'mj-regmgr-agenda-modal__details' }, [
                        h('span', { class: 'mj-regmgr-agenda-modal__details-label' }, getString(strings, 'recurringAgendaExceptionDateLabel', 'Date')),
                        h('span', null, exceptionDialogState.dateLabel || exceptionDialogState.date || ''),
                        exceptionDialogState.timeLabel ? h(Fragment, null, [
                            h('span', { class: 'mj-regmgr-agenda-modal__details-label' }, getString(strings, 'recurringAgendaExceptionTimeLabel', 'Horaire')),
                            h('span', null, exceptionDialogState.timeLabel),
                        ]) : null,
                    ]),
                    h('fieldset', { class: 'mj-regmgr-agenda-modal__options' }, [
                        h('label', {
                            class: classNames('mj-regmgr-agenda-modal__option', {
                                'mj-regmgr-agenda-modal__option--active': exceptionDialogState.mode === 'exclude',
                            }),
                        }, [
                            h('input', {
                                type: 'radio',
                                name: exceptionRadioName,
                                value: 'exclude',
                                checked: exceptionDialogState.mode === 'exclude',
                                onChange: function () { handleExceptionModeChange('exclude'); },
                            }),
                            h('div', { class: 'mj-regmgr-agenda-modal__option-content' }, [
                                h('span', { class: 'mj-regmgr-agenda-modal__option-title' }, getString(strings, 'recurringAgendaExceptionExcludeOption', 'Exclure de la serie (masquer sans motif)')),
                            ]),
                        ]),
                        h('label', {
                            class: classNames('mj-regmgr-agenda-modal__option', {
                                'mj-regmgr-agenda-modal__option--active': exceptionDialogState.mode === 'cancel',
                            }),
                        }, [
                            h('input', {
                                type: 'radio',
                                name: exceptionRadioName,
                                value: 'cancel',
                                checked: exceptionDialogState.mode === 'cancel',
                                onChange: function () { handleExceptionModeChange('cancel'); },
                            }),
                            h('div', { class: 'mj-regmgr-agenda-modal__option-content' }, [
                                h('span', { class: 'mj-regmgr-agenda-modal__option-title' }, getString(strings, 'recurringAgendaExceptionCancelOption', 'Annuler la seance (afficher un motif)')),
                            ]),
                        ]),
                    ]),
                    exceptionDialogState.mode === 'cancel' && h('div', { class: 'mj-regmgr-agenda-modal__reason' }, [
                        h('label', { htmlFor: exceptionReasonFieldId }, getString(strings, 'recurringAgendaExceptionReasonLabel', 'Motif d annulation')),
                        h('textarea', {
                            id: exceptionReasonFieldId,
                            class: 'mj-regmgr-agenda-modal__textarea',
                            value: exceptionDialogState.reason,
                            placeholder: getString(strings, 'recurringAgendaExceptionReasonPlaceholder', 'Animateur absent, meteo, ...'),
                            onInput: function (e) { handleExceptionReasonChange(e.target.value); },
                        }),
                    ]),
                    exceptionDialogError ? h('p', { class: 'mj-regmgr-agenda-modal__error' }, exceptionDialogError) : null,
                ]),
            ]),

        ]);
    }
    global.MjRegMgrEventEditor = {
        EventEditor: EventEditor,
    };

})(window);
