/**
 * EventPage App - Composant Preact pour les inscriptions événement
 * 
 * Architecture :
 * - State management centralisé
 * - Services AJAX isolés
 * - Composants UI réutilisables
 * - Gestion propre du cycle de vie
 */

(function() {
    'use strict';

    // Récupérer Preact depuis le global
    var preact = window.preact || window.Preact;
    var hooks = window.preactHooks;

    if (!preact) {
        console.warn('[EventPage] Preact non disponible');
        return;
    }

    var h = preact.h;
    var render = preact.render;
    var Fragment = preact.Fragment;

    // Hooks - avec fallback si non disponibles
    var useState = hooks && hooks.useState;
    var useEffect = hooks && hooks.useEffect;
    var useCallback = hooks && hooks.useCallback;
    var useRef = hooks && hooks.useRef;

    if (!h || !render || !useState) {
        console.warn('[EventPage] Preact ou hooks non disponibles', { h: !!h, render: !!render, useState: !!useState });
        return;
    }

    // =========================================================================
    // Configuration et utilitaires
    // =========================================================================

    /**
     * Récupère la configuration depuis le DOM
     * @returns {Object}
     */
    function getConfig() {
        const configEl = document.getElementById('mj-event-page-config');
        if (!configEl) {
            return {};
        }

        try {
            return JSON.parse(configEl.textContent || '{}');
        } catch (e) {
            console.error('[EventPage] Erreur parsing config:', e);
            return {};
        }
    }

    /**
     * Échappe le HTML
     * @param {string} str
     * @returns {string}
     */
    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // =========================================================================
    // Services AJAX
    // =========================================================================

    const RegistrationService = {
        /**
         * @type {AbortController|null}
         */
        _controller: null,

        /**
         * Annule les requêtes en cours
         */
        abort() {
            if (this._controller) {
                this._controller.abort();
                this._controller = null;
            }
        },

        /**
         * Enregistre ou met à jour une inscription
         * @param {Object} params
         * @returns {Promise<Object>}
         */
        async register(params) {
            this.abort();
            this._controller = new AbortController();

            const config = getConfig();
            const { ajax } = config;

            if (!ajax || !ajax.url || !ajax.nonce) {
                throw new Error('Configuration AJAX manquante');
            }

            const formData = new FormData();
            formData.append('action', 'mj_member_register_event');
            formData.append('nonce', ajax.nonce);
            formData.append('event_id', params.eventId || 0);
            formData.append('member_id', params.memberId || 0);

            if (params.occurrenceIds && params.occurrenceIds.length > 0) {
                // Convertir les timestamps en dates strings pour le serveur
                const occurrenceDates = params.occurrenceIds.map(ts => {
                    const d = new Date(ts * 1000);
                    const pad = n => String(n).padStart(2, '0');
                    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                });
                formData.append('occurrences', JSON.stringify(occurrenceDates));
            }

            if (params.note) {
                formData.append('note', params.note);
            }

            try {
                const response = await fetch(ajax.url, {
                    method: 'POST',
                    body: formData,
                    signal: this._controller.signal,
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.data?.message || 'Erreur lors de l\'inscription');
                }

                return data.data;
            } finally {
                this._controller = null;
            }
        },

        /**
         * Annule une inscription
         * @param {Object} params
         * @returns {Promise<Object>}
         */
        async cancel(params) {
            this.abort();
            this._controller = new AbortController();

            const config = getConfig();
            const { ajax } = config;

            if (!ajax || !ajax.url || !ajax.nonce) {
                throw new Error('Configuration AJAX manquante');
            }

            const formData = new FormData();
            formData.append('action', 'mj_member_unregister_event');
            formData.append('nonce', ajax.nonce);
            formData.append('event_id', params.eventId || 0);
            formData.append('member_id', params.memberId || 0);

            try {
                const response = await fetch(ajax.url, {
                    method: 'POST',
                    body: formData,
                    signal: this._controller.signal,
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.data?.message || 'Erreur lors de l\'annulation');
                }

                return data.data;
            } finally {
                this._controller = null;
            }
        },
    };

    // =========================================================================
    // Composants UI
    // =========================================================================

    /**
     * Spinner de chargement
     */
    function Spinner() {
        return h('span', { className: 'mj-event-page__spinner', 'aria-hidden': 'true' });
    }

    /**
     * Message de feedback
     */
    function Feedback({ type, message }) {
        if (!message) return null;

        const typeClass = type === 'error' ? 'is-error' : type === 'success' ? 'is-success' : '';

        return h('div', {
            className: `mj-event-page__feedback ${typeClass}`,
            role: type === 'error' ? 'alert' : 'status',
        }, message);
    }

    /**
     * Sélecteur de participant
     */
    function ParticipantSelector({ participants, selectedId, onChange, disabled, reservations }) {
        if (!participants || participants.length === 0) {
            return null;
        }

        // Statuts considérés comme "annulé" (ne pas afficher comme inscrit)
        const CANCELLED_STATUSES = ['annule', 'cancelled'];
        // Statuts considérés comme "en attente de validation"
        const PENDING_STATUSES = ['en_attente', 'pending'];

        // Helper pour récupérer la réservation d'un participant
        const getReservation = (participantId) => {
            if (!reservations || !Array.isArray(reservations)) return null;
            return reservations.find(r => 
                (r.member_id === participantId || parseInt(r.member_id, 10) === participantId) &&
                !CANCELLED_STATUSES.includes(r.status)
            );
        };

        // Helper pour vérifier si un participant est inscrit
        const isRegistered = (participantId) => {
            return getReservation(participantId) !== null;
        };

        // Helper pour vérifier si l'inscription est en attente de validation
        const isPending = (participantId) => {
            const reservation = getReservation(participantId);
            return reservation && PENDING_STATUSES.includes(reservation.status);
        };

        // Rendu du badge de statut
        const renderStatusBadge = (participantId) => {
            const reservation = getReservation(participantId);
            if (!reservation) return null;
            
            if (PENDING_STATUSES.includes(reservation.status)) {
                return h('span', { className: 'mj-event-page__participant-badge mj-event-page__participant-badge--pending' }, '⏳ En cours de validation');
            }
            return h('span', { className: 'mj-event-page__participant-badge mj-event-page__participant-badge--registered' }, '✓ Inscrit');
        };

        // Un seul participant, pas besoin de sélecteur
        if (participants.length === 1) {
            const p = participants[0];
            const registered = isRegistered(p.id);
            return h('div', { className: 'mj-event-page__registration-summary' },
                `Inscription pour ${p.full_name || p.name}`,
                registered && renderStatusBadge(p.id)
            );
        }

        return h('fieldset', { className: 'mj-event-page__registration-fieldset' },
            h('legend', null, 'Choisir le participant'),
            h('div', { className: 'mj-event-page__participants' },
                participants.map(p => {
                    const registered = isRegistered(p.id);
                    const pending = isPending(p.id);
                    // DEBUG
                    console.log('[ParticipantSelector] participant:', p.id, p.full_name, 'registered:', registered, 'pending:', pending, 'reservations:', reservations);
                    return h('label', {
                        key: p.id,
                        className: `mj-event-page__registration-participant ${selectedId === p.id ? 'is-selected' : ''} ${registered ? 'is-registered' : ''} ${pending ? 'is-pending' : ''}`,
                    },
                        h('input', {
                            type: 'radio',
                            name: 'participant_id',
                            value: p.id,
                            checked: selectedId === p.id,
                            disabled: disabled,
                            onChange: () => onChange(p.id),
                        }),
                        h('span', null, p.full_name || p.name),
                        p.is_child && h('span', { className: 'mj-event-page__participant-badge' }, 'Enfant'),
                        registered && renderStatusBadge(p.id)
                    );
                })
            )
        );
    }

    /**
     * Calendrier de sélection des occurrences - Vue agenda mensuel
     */
    function OccurrenceCalendar({ occurrences, selectedIds, onChange, disabled, allowsSelection }) {
        var currentMonth = useState(function() {
            // Trouver le premier mois avec des occurrences futures
            var now = new Date();
            for (var i = 0; i < occurrences.length; i++) {
                var occ = occurrences[i];
                if (!occ.is_past && occ.timestamp) {
                    var d = new Date(occ.timestamp * 1000);
                    return new Date(d.getFullYear(), d.getMonth(), 1);
                }
            }
            return new Date(now.getFullYear(), now.getMonth(), 1);
        });
        var viewMonth = currentMonth[0];
        var setViewMonth = currentMonth[1];

        if (!occurrences || occurrences.length === 0) {
            return null;
        }

        // Créer un index des occurrences par date (YYYY-MM-DD)
        var occurrencesByDate = {};
        occurrences.forEach(function(occ) {
            if (occ.timestamp) {
                var d = new Date(occ.timestamp * 1000);
                var key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                occurrencesByDate[key] = occ;
            }
        });

        // Navigation mois
        var goToPrevMonth = function() {
            setViewMonth(new Date(viewMonth.getFullYear(), viewMonth.getMonth() - 1, 1));
        };
        var goToNextMonth = function() {
            setViewMonth(new Date(viewMonth.getFullYear(), viewMonth.getMonth() + 1, 1));
        };

        // Générer les jours du mois
        var year = viewMonth.getFullYear();
        var month = viewMonth.getMonth();
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var startDayOfWeek = (firstDay.getDay() + 6) % 7; // Lundi = 0
        var daysInMonth = lastDay.getDate();

        var days = [];
        // Jours vides avant le 1er
        for (var i = 0; i < startDayOfWeek; i++) {
            days.push({ empty: true });
        }
        // Jours du mois
        for (var d = 1; d <= daysInMonth; d++) {
            var dateKey = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(d).padStart(2, '0');
            var occ = occurrencesByDate[dateKey] || null;
            var today = new Date();
            var isToday = (today.getFullYear() === year && today.getMonth() === month && today.getDate() === d);
            days.push({
                day: d,
                dateKey: dateKey,
                occurrence: occ,
                isToday: isToday,
                isPast: occ ? occ.is_past : false,
                timestamp: occ ? occ.timestamp : null
            });
        }

        var monthNames = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                          'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        var dayNames = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

        // DEBUG: voir les timestamps
        console.log('[OccurrenceCalendar] selectedIds:', selectedIds);
        console.log('[OccurrenceCalendar] occurrences timestamps:', occurrences.map(o => ({ date: o.date, ts: o.timestamp })));

        // Convertir les selectedIds (timestamps) en dates YYYY-MM-DD pour comparaison
        var selectedDates = {};
        selectedIds.forEach(function(ts) {
            var d = new Date(ts * 1000);
            var key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            selectedDates[key] = ts;
        });
        console.log('[OccurrenceCalendar] selectedDates:', selectedDates);

        // Lookup: date (YYYY-MM-DD) → timestamp de l'occurrence
        var occurrencesByDate = {};
        occurrences.forEach(function(o) {
            if (o.date) {
                occurrencesByDate[o.date] = o.timestamp;
            }
        });

        var handleToggle = function(dateKey) {
            if (disabled || !allowsSelection) return;
            var occTimestamp = occurrencesByDate[dateKey];
            if (!occTimestamp) return;
            
            // Vérifier si cette date est déjà sélectionnée
            var isCurrentlySelected = dateKey in selectedDates;
            var newSelected;
            
            if (isCurrentlySelected) {
                // Retirer: filtrer par date
                newSelected = selectedIds.filter(function(ts) {
                    var d = new Date(ts * 1000);
                    var key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                    return key !== dateKey;
                });
            } else {
                // Ajouter le timestamp de l'occurrence
                newSelected = selectedIds.concat([occTimestamp]);
            }
            onChange(newSelected);
        };

        // Compter les sélections
        var selectedCount = selectedIds.length;
        var futureOccurrences = occurrences.filter(function(o) { return !o.is_past; });

        return h('div', { className: 'mj-event-page__calendar' },
            // Header avec navigation
            h('div', { className: 'mj-event-page__calendar-header' },
                h('button', {
                    type: 'button',
                    className: 'mj-event-page__calendar-nav',
                    onClick: goToPrevMonth,
                    'aria-label': 'Mois précédent'
                }, '‹'),
                h('span', { className: 'mj-event-page__calendar-title' },
                    monthNames[month] + ' ' + year
                ),
                h('button', {
                    type: 'button',
                    className: 'mj-event-page__calendar-nav',
                    onClick: goToNextMonth,
                    'aria-label': 'Mois suivant'
                }, '›')
            ),

            // Jours de la semaine
            h('div', { className: 'mj-event-page__calendar-weekdays' },
                dayNames.map(function(name) {
                    return h('span', { key: name, className: 'mj-event-page__calendar-weekday' }, name);
                })
            ),

            // Grille des jours
            h('div', { className: 'mj-event-page__calendar-grid' },
                days.map(function(day, i) {
                    if (day.empty) {
                        return h('span', { key: 'empty-' + i, className: 'mj-event-page__calendar-day is-empty' });
                    }

                    var hasOccurrence = !!day.occurrence;
                    // Comparer par date (dateKey = YYYY-MM-DD) au lieu de timestamp
                    var isSelected = hasOccurrence && (day.dateKey in selectedDates);
                    var isPast = day.isPast;
                    var canSelect = hasOccurrence && !isPast && allowsSelection;

                    var classes = ['mj-event-page__calendar-day'];
                    if (day.isToday) classes.push('is-today');
                    if (hasOccurrence) classes.push('has-occurrence');
                    if (isSelected) classes.push('is-selected');
                    if (isPast) classes.push('is-past');
                    if (canSelect) classes.push('is-selectable');

                    var dayContent = h('span', { className: 'mj-event-page__calendar-day-number' }, day.day);

                    if (canSelect) {
                        return h('button', {
                            key: day.dateKey,
                            type: 'button',
                            className: classes.join(' '),
                            onClick: function() { handleToggle(day.dateKey); },
                            disabled: disabled,
                            'aria-pressed': isSelected,
                            'aria-label': day.day + ' ' + monthNames[month] + (isSelected ? ' (sélectionné)' : '')
                        }, dayContent);
                    }

                    return h('span', {
                        key: day.dateKey,
                        className: classes.join(' ')
                    }, dayContent);
                })
            ),

            // Résumé de sélection
            allowsSelection && h('div', { className: 'mj-event-page__calendar-summary' },
                selectedCount > 0
                    ? h('span', null, selectedCount + ' date' + (selectedCount > 1 ? 's' : '') + ' sélectionnée' + (selectedCount > 1 ? 's' : ''))
                    : h('span', { className: 'mj-event-page__calendar-hint' }, 'Clique sur les dates pour sélectionner')
            ),

            // Légende
            h('div', { className: 'mj-event-page__calendar-legend' },
                h('span', { className: 'mj-event-page__calendar-legend-item' },
                    h('span', { className: 'mj-event-page__calendar-legend-dot has-occurrence' }),
                    ' Disponible'
                ),
                h('span', { className: 'mj-event-page__calendar-legend-item' },
                    h('span', { className: 'mj-event-page__calendar-legend-dot is-selected' }),
                    ' Sélectionné'
                )
            )
        );
    }

    /**
     * Popup de paiement
     */
    function PaymentPopup({ isOpen, onClose, priceDisplay, onContinue }) {
        if (!isOpen) return null;

        return h('div', {
            className: 'mj-event-page__popup-overlay',
            onClick: (e) => e.target === e.currentTarget && onClose(),
        },
            h('div', {
                className: 'mj-event-page__popup',
                role: 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': 'mj-event-page-payment-title',
            },
                h('h3', { id: 'mj-event-page-payment-title' }, 'Paiement'),
                h('p', { className: 'mj-event-page__popup-price' },
                    'Montant à régler : ',
                    h('strong', null, priceDisplay)
                ),
                h('p', { className: 'mj-event-page__popup-notice' },
                    'Vous pouvez effectuer le paiement maintenant ou plus tard dans votre espace membre ou en main propre à un animateur.'
                ),
                h('div', { className: 'mj-event-page__popup-actions' },
                    h('button', {
                        type: 'button',
                        className: 'mj-event-page__btn mj-event-page__btn--secondary',
                        onClick: onClose,
                    }, 'Plus tard'),
                    h('button', {
                        type: 'button',
                        className: 'mj-event-page__btn mj-event-page__btn--primary',
                        onClick: onContinue,
                    }, 'Payer maintenant')
                )
            )
        );
    }

    // =========================================================================
    // Composant principal
    // =========================================================================

    // Statuts considérés comme "annulé"
    const CANCELLED_STATUSES = ['annule', 'cancelled'];

    function RegistrationApp({ containerConfig }) {
        const config = getConfig();
        const registration = config.registration || {};
        const occurrencesConfig = config.occurrences || {};
        const i18n = config.i18n || {};

        // State
        const [isLoading, setIsLoading] = useState(false);
        const [feedback, setFeedback] = useState({ type: '', message: '' });
        const [selectedParticipant, setSelectedParticipant] = useState(
            registration.participants?.[0]?.id || 0
        );
        const [selectedOccurrences, setSelectedOccurrences] = useState([]);
        const [note, setNote] = useState('');
        const [showPaymentPopup, setShowPaymentPopup] = useState(false);
        const [stripeCheckoutUrl, setStripeCheckoutUrl] = useState(null);
        
        // Stocker les réservations localement pour mise à jour après inscription
        const [localReservations, setLocalReservations] = useState(
            registration.user_reservations || []
        );

        // Helper pour trouver la réservation d'un participant (utilise localReservations)
        const getReservationForParticipant = (participantId) => {
            if (!localReservations || !Array.isArray(localReservations)) {
                return null;
            }
            return localReservations.find(r => 
                (r.member_id === participantId || parseInt(r.member_id, 10) === participantId) &&
                !CANCELLED_STATUSES.includes(r.status)
            ) || null;
        };

        // Calculer si le participant sélectionné est inscrit
        const currentReservation = getReservationForParticipant(selectedParticipant);
        const isParticipantRegistered = !!currentReservation;

        // Refs
        const formRef = useRef(null);

        // Cleanup on unmount
        useEffect(() => {
            return () => {
                RegistrationService.abort();
            };
        }, []);

        // Initialiser les occurrences sélectionnées depuis les réservations existantes du participant
        useEffect(() => {
            const res = getReservationForParticipant(selectedParticipant);
            if (res && res.occurrence_ids && Array.isArray(res.occurrence_ids)) {
                const existingIds = res.occurrence_ids.map(id => parseInt(id, 10));
                setSelectedOccurrences(existingIds);
            } else {
                setSelectedOccurrences([]);
            }
            // Reset le feedback quand on change de participant
            setFeedback({ type: '', message: '' });
        }, [selectedParticipant]);

        // Handlers
        const handleSubmit = useCallback(async (e) => {
            e?.preventDefault();

            if (isLoading) return;

            setIsLoading(true);
            setFeedback({ type: '', message: '' });

            try {
                const result = await RegistrationService.register({
                    eventId: config.event?.id,
                    memberId: selectedParticipant,
                    occurrenceIds: selectedOccurrences,
                    note: note,
                });

                setFeedback({
                    type: 'success',
                    message: result.message || i18n.success || 'Inscription confirmée !',
                });

                // Ajouter la réservation locale pour le participant
                setLocalReservations(prev => {
                    const filtered = prev.filter(r => 
                        parseInt(r.member_id, 10) !== selectedParticipant
                    );
                    return [...filtered, {
                        id: result.registration_id,
                        member_id: selectedParticipant,
                        status: result.is_waitlist ? 'waitlist' : 'pending',
                        occurrence_ids: selectedOccurrences,
                    }];
                });

                // Afficher popup paiement si nécessaire (nouvelle inscription ou mise à jour avec nouveau paiement)
                if ((registration.payment_required || result.payment_required) && result.payment?.checkout_url) {
                    setStripeCheckoutUrl(result.payment.checkout_url);
                    setShowPaymentPopup(true);
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                setFeedback({
                    type: 'error',
                    message: error.message || i18n.error || 'Une erreur est survenue.',
                });
            } finally {
                setIsLoading(false);
            }
        }, [isLoading, selectedParticipant, selectedOccurrences, note, config.event?.id, registration.paymentRequired, i18n]);

        const handleCancel = useCallback(async () => {
            if (isLoading) return;

            if (!window.confirm('Êtes-vous sûr de vouloir annuler cette inscription ?')) {
                return;
            }

            setIsLoading(true);
            setFeedback({ type: '', message: '' });

            try {
                await RegistrationService.cancel({
                    eventId: config.event?.id,
                    memberId: selectedParticipant,
                });

                setFeedback({
                    type: 'success',
                    message: 'Inscription annulée.',
                });
                
                // Retirer la réservation locale pour le participant
                setLocalReservations(prev => 
                    prev.filter(r => parseInt(r.member_id, 10) !== selectedParticipant)
                );
                setSelectedOccurrences([]);
            } catch (error) {
                if (error.name === 'AbortError') {
                    return;
                }
                setFeedback({
                    type: 'error',
                    message: error.message || i18n.error || 'Une erreur est survenue.',
                });
            } finally {
                setIsLoading(false);
            }
        }, [isLoading, config.event?.id, selectedParticipant, i18n]);

        // Déterminer le label du bouton
        const ctaLabel = isParticipantRegistered
            ? (i18n.update_registration || 'Mettre à jour')
            : (i18n.register || "S'inscrire");

        // Vérifier si le formulaire est valide
        const isFormValid = selectedParticipant > 0 && (
            !occurrencesConfig.allows_selection || selectedOccurrences.length > 0
        );

        // Masquer le bouton principal si déjà inscrit et pas de sélection possible
        const showSubmitButton = !isParticipantRegistered || occurrencesConfig.allows_selection;

        return h(Fragment, null,
            h('form', {
                ref: formRef,
                className: 'mj-event-page__registration-form',
                onSubmit: handleSubmit,
            },
                // Sélecteur de participant
                h(ParticipantSelector, {
                    participants: registration.participants,
                    selectedId: selectedParticipant,
                    onChange: setSelectedParticipant,
                    disabled: isLoading,
                    reservations: localReservations,
                }),

                // Calendrier des occurrences
                h(OccurrenceCalendar, {
                    occurrences: occurrencesConfig.items,
                    selectedIds: selectedOccurrences,
                    onChange: setSelectedOccurrences,
                    disabled: isLoading,
                    allowsSelection: occurrencesConfig.allows_selection,
                }),

                // Champ note optionnel (masqué si déjà inscrit sans sélection possible)
                showSubmitButton && h('div', { className: 'mj-event-page__registration-note-field' },
                    h('label', { htmlFor: 'mj-event-page-note' }, 'Message pour l\'équipe (optionnel)'),
                    h('textarea', {
                        id: 'mj-event-page-note',
                        name: 'note',
                        value: note,
                        onChange: (e) => setNote(e.target.value),
                        maxLength: 400,
                        placeholder: 'Précise une allergie, une contrainte horaire…',
                        disabled: isLoading,
                    })
                ),

                // Boutons d'action
                h('div', { className: 'mj-event-page__registration-actions' },
                    showSubmitButton && h('button', {
                        type: 'submit',
                        className: 'mj-event-page__btn mj-event-page__btn--primary',
                        disabled: isLoading || !isFormValid,
                    },
                        isLoading && h(Spinner),
                        isLoading ? (i18n.loading || 'Chargement…') : ctaLabel
                    ),
                    isParticipantRegistered && h('button', {
                        type: 'button',
                        className: 'mj-event-page__btn mj-event-page__btn--secondary',
                        onClick: handleCancel,
                        disabled: isLoading,
                    }, i18n.cancel_registration || 'Annuler l\'inscription')
                ),

                // Feedback
                h(Feedback, { type: feedback.type, message: feedback.message })
            ),

            // Popup paiement
            h(PaymentPopup, {
                isOpen: showPaymentPopup,
                onClose: () => setShowPaymentPopup(false),
                priceDisplay: registration.price_display,
                onContinue: () => {
                    // Rediriger vers Stripe Checkout
                    if (stripeCheckoutUrl) {
                        window.location.href = stripeCheckoutUrl;
                    } else {
                        // Fallback vers l'espace membre si pas d'URL Stripe
                        window.location.href = '/mon-compte/paiements/';
                    }
                },
            })
        );
    }

    // =========================================================================
    // Initialisation
    // =========================================================================

    function init() {
        const containers = document.querySelectorAll('[data-mj-event-registration-app]');

        containers.forEach(container => {
            // Récupérer la config depuis data-config si disponible
            let containerConfig = {};
            try {
                containerConfig = JSON.parse(container.dataset.config || '{}');
            } catch (e) {
                // Ignore
            }

            // Masquer le loading placeholder
            const loading = container.querySelector('[data-mj-event-registration-loading]');
            if (loading) {
                loading.hidden = true;
            }

            // Rendre le composant
            render(
                h(RegistrationApp, { containerConfig }),
                container
            );
        });
    }

    // Initialiser au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Observer pour Elementor / navigation SPA
    if (typeof MutationObserver !== 'undefined') {
        const observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.addedNodes.length) {
                    const hasNew = Array.from(mutation.addedNodes).some(node =>
                        node.nodeType === 1 && (
                            node.matches?.('[data-mj-event-registration-app]') ||
                            node.querySelector?.('[data-mj-event-registration-app]')
                        )
                    );
                    if (hasNew) {
                        init();
                        break;
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

})();
