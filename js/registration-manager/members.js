/**
 * Registration Manager - Members Components
 * Composants pour la gestion des membres
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var hooks = global.preactHooks;
    var Utils = global.MjRegMgrUtils;
    var RegComps = global.MjRegMgrRegistrations;

    if (!preact || !hooks || !Utils) {
        console.warn('[MjRegMgr] Dépendances manquantes pour members.js');
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useMemo = hooks.useMemo;

    var formatDate = Utils.formatDate;
    var classNames = Utils.classNames;
    var getString = Utils.getString;
    var buildWhatsAppLink = Utils.buildWhatsAppLink;

    var MemberAvatar = RegComps ? RegComps.MemberAvatar : function () { return null; };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    /**
     * Calculate age from birth date
     * @param {string} birthDate - Date string in YYYY-MM-DD format
     * @returns {number|null} Age in years or null if invalid
     */
    function calculateAge(birthDate) {
        if (!birthDate) return null;
        var birth = new Date(birthDate);
        if (isNaN(birth.getTime())) return null;
        
        var today = new Date();
        var age = today.getFullYear() - birth.getFullYear();
        var monthDiff = today.getMonth() - birth.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        
        return age >= 0 ? age : null;
    }

    // ============================================
    // MEMBER CARD (for sidebar list)
    // ============================================

    function MemberCard(props) {
        var member = props.member;
        var isSelected = props.isSelected;
        var onClick = props.onClick;
        var strings = props.strings;

        var roleLabels = {
            'jeune': 'Jeune',
            'animateur': 'Animateur',
            'tuteur': 'Tuteur',
            'benevole': 'Bénévole',
            'coordinateur': 'Coordinateur',
        };

        var membershipLabels = {
            'paid': 'Cotisation OK',
            'expired': 'Cotisation expirée',
            'unpaid': 'Cotisation due',
            'not_required': '', // Ne pas afficher si pas de cotisation requise
        };

        var volunteerLabel = getString(strings, 'volunteerLabel', 'Bénévole');

        return h('div', {
            class: classNames('mj-regmgr-member-card', {
                'mj-regmgr-member-card--selected': isSelected,
            }),
            onClick: function () { onClick(member); },
            role: 'button',
            tabIndex: 0,
            onKeyDown: function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    onClick(member);
                }
            },
        }, [
            h(MemberAvatar, { member: member, size: 'medium' }),
            h('div', { class: 'mj-regmgr-member-card__content' }, [
                h('div', { class: 'mj-regmgr-member-card__name' }, 
                    (member.firstName || '') + ' ' + (member.lastName || '')
                ),
                h('div', { class: 'mj-regmgr-member-card__meta' }, [
                    member.role && h('span', { class: 'mj-regmgr-member-card__role' }, 
                        roleLabels[member.role] || member.role
                    ),
                    member.membershipStatus && member.membershipStatus !== 'not_required' && h('span', { 
                        class: classNames('mj-regmgr-member-card__membership', {
                            'mj-regmgr-member-card__membership--paid': member.membershipStatus === 'paid',
                            'mj-regmgr-member-card__membership--expired': member.membershipStatus === 'expired',
                            'mj-regmgr-member-card__membership--unpaid': member.membershipStatus === 'unpaid',
                        })
                    }, membershipLabels[member.membershipStatus] || ''),
                    member.isVolunteer && h('span', {
                        class: classNames('mj-regmgr-member-card__volunteer', 'mj-regmgr-badge', 'mj-regmgr-badge--volunteer'),
                    }, volunteerLabel),
                ]),
            ]),
        ]);
    }

    // ============================================
    // MEMBERS LIST
    // ============================================

    function MembersList(props) {
        var members = props.members || [];
        var loading = props.loading;
        var selectedMemberId = props.selectedMemberId;
        var onSelectMember = props.onSelectMember;
        var strings = props.strings;
        var onLoadMore = props.onLoadMore;
        var hasMore = props.hasMore;
        var loadingMore = props.loadingMore;

        if (loading && members.length === 0) {
            return h('div', { class: 'mj-regmgr-members-list mj-regmgr-members-list--loading' }, [
                h('div', { class: 'mj-regmgr-spinner' }),
                h('p', null, getString(strings, 'loading', 'Chargement...')),
            ]);
        }

        if (members.length === 0) {
            return h('div', { class: 'mj-regmgr-members-list mj-regmgr-members-list--empty' }, [
                h('div', { class: 'mj-regmgr-members-list__empty-icon' }, [
                    h('svg', {
                        width: 48,
                        height: 48,
                        viewBox: '0 0 24 24',
                        fill: 'none',
                        stroke: 'currentColor',
                        'stroke-width': 1.5,
                    }, [
                        h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                        h('circle', { cx: 9, cy: 7, r: 4 }),
                        h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                        h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                    ]),
                ]),
                h('p', null, getString(strings, 'noMembers', 'Aucun membre trouvé.')),
            ]);
        }

        return h('div', { class: 'mj-regmgr-members-list' }, [
            members.map(function (member) {
                return h(MemberCard, {
                    key: member.id,
                    member: member,
                    isSelected: member.id === selectedMemberId,
                    onClick: onSelectMember,
                    strings: strings,
                });
            }),

            // Bouton "Charger plus"
            hasMore && h('div', { class: 'mj-regmgr-members-list__load-more' }, [
                h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary mj-btn--small',
                    onClick: onLoadMore,
                    disabled: loadingMore,
                }, loadingMore 
                    ? getString(strings, 'loading', 'Chargement...') 
                    : 'Voir plus'
                ),
            ]),
        ]);
    }

    // ============================================
    // MEMBER DETAIL PANEL
    // ============================================

    function MemberDetailPanel(props) {
        var member = props.member;
        var loading = props.loading;
        var strings = props.strings;
        var config = props.config;
        var notes = props.notes || [];
        var registrations = props.registrations || [];
        var onSaveNote = props.onSaveNote;
        var onDeleteNote = props.onDeleteNote;
        var onUpdateMember = props.onUpdateMember;
        var onPayMembershipOnline = props.onPayMembershipOnline;
        var onMarkMembershipPaid = props.onMarkMembershipPaid;
        var onResetPassword = typeof props.onResetPassword === 'function' ? props.onResetPassword : null;
        var onUpdateIdea = typeof props.onUpdateIdea === 'function' ? props.onUpdateIdea : null;
        var onUpdatePhoto = typeof props.onUpdatePhoto === 'function' ? props.onUpdatePhoto : null;
        var onDeletePhoto = typeof props.onDeletePhoto === 'function' ? props.onDeletePhoto : null;
        var onDeleteMessage = typeof props.onDeleteMessage === 'function' ? props.onDeleteMessage : null;
        var onOpenMember = typeof props.onOpenMember === 'function' ? props.onOpenMember : null;
        var pendingEditRequest = props.pendingEditRequest || null;
        var onPendingEditHandled = typeof props.onPendingEditHandled === 'function' ? props.onPendingEditHandled : null;

        var _editMode = useState(false);
        var editMode = _editMode[0];
        var setEditMode = _editMode[1];

        var _editData = useState({});
        var editData = _editData[0];
        var setEditData = _editData[1];

        var _newNote = useState('');
        var newNote = _newNote[0];
        var setNewNote = _newNote[1];

        var _savingNote = useState(false);
        var savingNote = _savingNote[0];
        var setSavingNote = _savingNote[1];

        var _showPaymentModal = useState(false);
        var showPaymentModal = _showPaymentModal[0];
        var setShowPaymentModal = _showPaymentModal[1];

        var _paymentProcessing = useState(false);
        var paymentProcessing = _paymentProcessing[0];
        var setPaymentProcessing = _paymentProcessing[1];

        var _resettingPassword = useState(false);
        var resettingPassword = _resettingPassword[0];
        var setResettingPassword = _resettingPassword[1];

        var _editingNoteId = useState(null);
        var editingNoteId = _editingNoteId[0];
        var setEditingNoteId = _editingNoteId[1];

        var _editingNoteContent = useState('');
        var editingNoteContent = _editingNoteContent[0];
        var setEditingNoteContent = _editingNoteContent[1];

        var _editingNoteSaving = useState(false);
        var editingNoteSaving = _editingNoteSaving[0];
        var setEditingNoteSaving = _editingNoteSaving[1];

        var _editingIdeaId = useState(null);
        var editingIdeaId = _editingIdeaId[0];
        var setEditingIdeaId = _editingIdeaId[1];

        var _ideaDraft = useState({ title: '', content: '', status: 'published' });
        var ideaDraft = _ideaDraft[0];
        var setIdeaDraft = _ideaDraft[1];

        var _ideaSaving = useState(false);
        var ideaSaving = _ideaSaving[0];
        var setIdeaSaving = _ideaSaving[1];

        var _editingPhotoId = useState(null);
        var editingPhotoId = _editingPhotoId[0];
        var setEditingPhotoId = _editingPhotoId[1];

        var _photoDraft = useState({ caption: '', status: 'approved' });
        var photoDraft = _photoDraft[0];
        var setPhotoDraft = _photoDraft[1];

        var _photoDeletingId = useState(null);
        var photoDeletingId = _photoDeletingId[0];
        var setPhotoDeletingId = _photoDeletingId[1];

        var _messageDeletingId = useState(null);
        var messageDeletingId = _messageDeletingId[0];
        var setMessageDeletingId = _messageDeletingId[1];

        var _photoSaving = useState(false);
        var photoSaving = _photoSaving[0];
        var setPhotoSaving = _photoSaving[1];

        var buildInitialEditData = function (sourceMember) {
            var base = sourceMember || {};
            return {
                firstName: base.firstName || '',
                lastName: base.lastName || '',
                nickname: base.nickname || '',
                email: base.email || '',
                phone: base.phone || '',
                birthDate: base.birthDate || '',
                addressLine: base.addressLine || '',
                city: base.city || '',
                postalCode: base.postalCode || '',
                isVolunteer: !!base.isVolunteer,
                isAutonomous: !!base.isAutonomous,
                descriptionShort: base.descriptionShort || '',
                descriptionLong: base.descriptionLong || '',
                newsletterOptIn: typeof base.newsletterOptIn === 'boolean' ? base.newsletterOptIn : true,
                smsOptIn: typeof base.smsOptIn === 'boolean' ? base.smsOptIn : true,
                whatsappOptIn: typeof base.whatsappOptIn === 'boolean' ? base.whatsappOptIn : true,
                photoUsageConsent: !!base.photoUsageConsent,
            };
        };

        // Reset edit data when member changes
        useEffect(function () {
            if (member) {
                setEditData(buildInitialEditData(member));
                setEditMode(false);
                setEditingNoteId(null);
                setEditingNoteContent('');
                setEditingNoteSaving(false);
                setEditingIdeaId(null);
                setIdeaDraft({ title: '', content: '', status: 'published' });
                setIdeaSaving(false);
                setEditingPhotoId(null);
                setPhotoDraft({ caption: '', status: 'approved' });
                setPhotoSaving(false);
                setResettingPassword(false);
            }
        }, [member ? member.id : null]);

        useEffect(function () {
            if (!pendingEditRequest || !member) {
                return;
            }
            if (pendingEditRequest.memberId !== member.id) {
                return;
            }
            setEditMode(true);
            if (onPendingEditHandled) {
                onPendingEditHandled();
            }
        }, [
            pendingEditRequest ? pendingEditRequest.memberId : null,
            pendingEditRequest ? pendingEditRequest.requestId : null,
            member ? member.id : null,
            onPendingEditHandled,
        ]);

        if (loading) {
            return h('div', { class: 'mj-regmgr-member-detail mj-regmgr-member-detail--loading' }, [
                h('div', { class: 'mj-regmgr-loading' }, [
                    h('div', { class: 'mj-regmgr-loading__spinner' }),
                    h('p', { class: 'mj-regmgr-loading__text' }, 'Chargement...'),
                ]),
            ]);
        }

        if (!member) {
            return null;
        }

        var roleLabels = {
            'jeune': 'Jeune',
            'animateur': 'Animateur',
            'tuteur': 'Tuteur',
            'benevole': 'Bénévole',
            'coordinateur': 'Coordinateur',
        };

        var membershipLabels = {
            'paid': 'Cotisation payée',
            'expired': 'Cotisation expirée',
            'unpaid': 'Cotisation due',
            'not_required': 'Non soumis à cotisation',
        };

        var statusLabels = {
            'active': 'Actif',
            'inactive': 'Inactif',
        };

        var ideaStatusLabels = {
            'published': 'Publié',
            'archived': 'Archivé',
        };

        var ideaStatusClasses = {
            'published': 'mj-regmgr-badge--success',
            'archived': 'mj-regmgr-badge--secondary',
        };

        var photoStatusLabels = {
            'approved': 'Validée',
            'pending': 'En attente',
            'rejected': 'Refusée',
        };

        var photoStatusClasses = {
            'approved': 'mj-regmgr-badge--success',
            'pending': 'mj-regmgr-badge--warning',
            'rejected': 'mj-regmgr-badge--danger',
        };

        var communicationEnabledLabel = getString(strings, 'communicationEnabled', 'Activé');
        var communicationDisabledLabel = getString(strings, 'communicationDisabled', 'Désactivé');

        var guardian = member.guardian;
        var guardianEditUrl = guardian && guardian.id && config && config.adminMemberUrl
            ? config.adminMemberUrl + guardian.id
            : null;
        var canEditGuardianInline = !!(guardian && guardian.id && onOpenMember);
        var handleGuardianEditClick = useCallback(function () {
            if (!guardian || !guardian.id) {
                return;
            }
            if (onOpenMember) {
                onOpenMember(guardian, { edit: true });
            } else if (guardianEditUrl) {
                window.open(guardianEditUrl, '_blank', 'noopener');
            }
        }, [guardian, onOpenMember, guardianEditUrl]);

        var handleFieldChange = function (field) {
            return function (e) {
                var newData = Object.assign({}, editData);
                newData[field] = e.target.value;
                setEditData(newData);
            };
        };

        var handleBooleanChange = function (field) {
            return function (e) {
                var newData = Object.assign({}, editData);
                newData[field] = !!e.target.checked;
                setEditData(newData);
            };
        };

        var handleSave = function () {
            onUpdateMember(member.id, editData);
            setEditMode(false);
        };

        var handleAddNote = function () {
            if (!newNote.trim()) return;
            setSavingNote(true);
            onSaveNote(member.id, newNote.trim())
                .then(function () {
                    setNewNote('');
                })
                .finally(function () {
                    setSavingNote(false);
                });
        };

        var handleNoteEditStart = function (note) {
            if (!note || !note.canEdit) {
                return;
            }
            setEditingNoteId(note.id);
            setEditingNoteContent(note.content || '');
        };

        var handleNoteEditCancel = function () {
            setEditingNoteId(null);
            setEditingNoteContent('');
            setEditingNoteSaving(false);
        };

        var handleUpdateNote = function () {
            if (!onSaveNote || !editingNoteId || !member) {
                return;
            }
            var trimmed = editingNoteContent ? editingNoteContent.trim() : '';
            if (!trimmed) {
                return;
            }
            setEditingNoteSaving(true);
            Promise.resolve(onSaveNote(member.id, trimmed, editingNoteId))
                .then(function () {
                    handleNoteEditCancel();
                })
                .finally(function () {
                    setEditingNoteSaving(false);
                });
        };

        var handleIdeaEditStart = function (idea) {
            if (!idea) {
                return;
            }
            setEditingIdeaId(idea.id);
            setIdeaDraft({
                title: idea.title || '',
                content: idea.content || '',
                status: idea.status || 'published',
            });
        };

        var handleIdeaEditCancel = function () {
            setEditingIdeaId(null);
            setIdeaDraft({ title: '', content: '', status: 'published' });
            setIdeaSaving(false);
        };

        var handleSaveIdea = function () {
            if (!onUpdateIdea || !editingIdeaId || !member) {
                return;
            }
            var title = ideaDraft.title ? ideaDraft.title.trim() : '';
            var content = ideaDraft.content ? ideaDraft.content.trim() : '';
            if (!title || !content) {
                return;
            }
            var payload = {
                title: title,
                content: content,
                status: ideaDraft.status || 'published',
            };
            setIdeaSaving(true);
            Promise.resolve(onUpdateIdea(member.id, editingIdeaId, payload))
                .then(function () {
                    handleIdeaEditCancel();
                })
                .finally(function () {
                    setIdeaSaving(false);
                });
        };

        var handlePhotoEditStart = function (photo) {
            if (!photo) {
                return;
            }
            setEditingPhotoId(photo.id);
            setPhotoDraft({
                caption: photo.caption || '',
                status: photo.status || 'approved',
            });
        };

        var handlePhotoEditCancel = function () {
            setEditingPhotoId(null);
            setPhotoDraft({ caption: '', status: 'approved' });
            setPhotoSaving(false);
        };

        var handleSavePhoto = function () {
            if (!onUpdatePhoto || !editingPhotoId || !member) {
                return;
            }
            var payload = {
                caption: photoDraft.caption ? photoDraft.caption.trim() : '',
                status: photoDraft.status || 'approved',
            };
            setPhotoSaving(true);
            Promise.resolve(onUpdatePhoto(member.id, editingPhotoId, payload))
                .then(function () {
                    handlePhotoEditCancel();
                })
                .finally(function () {
                    setPhotoSaving(false);
                });
        };

        var handleDeletePhoto = function (photo) {
            if (!onDeletePhoto || !member || !member.id || !photo || !photo.id) {
                return;
            }
            if (!window.confirm('Supprimer cette photo ?')) {
                return;
            }
            setPhotoDeletingId(photo.id);
            Promise.resolve(onDeletePhoto(member.id, photo.id))
                .then(function () {
                    if (editingPhotoId === photo.id) {
                        handlePhotoEditCancel();
                    }
                })
                .catch(function () {
                    // Notification already gérée en amont
                })
                .finally(function () {
                    setPhotoDeletingId(null);
                });
        };

        var handleDeleteMessage = function (message) {
            if (!onDeleteMessage || !member || !member.id || !message || !message.id) {
                return;
            }
            if (!window.confirm('Supprimer ce message ?')) {
                return;
            }
            setMessageDeletingId(message.id);
            Promise.resolve(onDeleteMessage(member.id, message.id))
                .catch(function () {
                    // Gestion des erreurs dans le handler parent
                })
                .finally(function () {
                    setMessageDeletingId(null);
                });
        };

        var memberId = member && member.id ? member.id : null;
        var hasLinkedAccount = member && member.userId ? true : false;

        var handleResetPassword = useCallback(function () {
            if (!onResetPassword || !memberId || !hasLinkedAccount) {
                return;
            }
            setResettingPassword(true);
            Promise.resolve(onResetPassword(memberId))
                .catch(function () {
                    // Feedback déjà géré côté parent
                })
                .finally(function () {
                    setResettingPassword(false);
                });
        }, [onResetPassword, memberId, hasLinkedAccount]);

        // Calculate age
        var age = null;
        if (member.birthDate) {
            var birth = new Date(member.birthDate);
            var today = new Date();
            age = today.getFullYear() - birth.getFullYear();
            var m = today.getMonth() - birth.getMonth();
            if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) {
                age--;
            }
        }

        var memberPhone = typeof member.phone === 'string' ? member.phone : '';
        var memberWhatsappOptIn = typeof member.whatsappOptIn === 'undefined' ? true : !!member.whatsappOptIn;
        var whatsappLink = '';
        if (memberWhatsappOptIn && memberPhone) {
            whatsappLink = buildWhatsAppLink(memberPhone);
        }

        var addressParts = [];
        if (member.addressLine) {
            addressParts.push(member.addressLine);
        }
        var cityLineParts = [];
        if (member.postalCode) {
            cityLineParts.push(member.postalCode);
        }
        if (member.city) {
            cityLineParts.push(member.city);
        }
        var cityLine = cityLineParts.join(' ');
        if (cityLine) {
            addressParts.push(cityLine);
        }
        var addressDisplay = addressParts.join(' · ');

        var memberPhotos = Array.isArray(member.photos) ? member.photos : [];
        var memberIdeas = Array.isArray(member.ideas) ? member.ideas : [];
        var memberMessages = Array.isArray(member.messages) ? member.messages : [];

        var newsletterLabel = getString(strings, 'chipNewsletter', 'Newsletter');
        var smsLabel = getString(strings, 'chipSMS', 'SMS');
        var whatsappLabel = getString(strings, 'chipWhatsapp', 'WhatsApp');
        var photoConsentLabel = getString(strings, 'chipPhotoConsent', 'Consentement photo');
        var resetPasswordLabel = getString(strings, 'resetPassword', 'Réinitialiser le mot de passe');
        var resetPasswordUnavailableLabel = getString(strings, 'resetPasswordUnavailable', 'Compte WordPress non lié');
        var resetPasswordProcessingLabel = getString(strings, 'resetPasswordProcessing', 'Réinitialisation...');

        var messageStatusLabels = {
            'nouveau': 'Nouveau',
            'en_cours': 'En cours',
            'resolu': 'Résolu',
            'archive': 'Archivé',
        };
        var messageStatusClasses = {
            'nouveau': 'mj-regmgr-badge--warning',
            'en_cours': 'mj-regmgr-badge--info',
            'resolu': 'mj-regmgr-badge--success',
            'archive': 'mj-regmgr-badge--secondary',
        };

        var communicationChips = [];
        if (typeof member.newsletterOptIn !== 'undefined') {
            communicationChips.push({ key: 'newsletter', label: newsletterLabel, enabled: !!member.newsletterOptIn });
        }
        if (typeof member.smsOptIn !== 'undefined') {
            communicationChips.push({ key: 'sms', label: smsLabel, enabled: !!member.smsOptIn });
        }
        if (typeof member.whatsappOptIn !== 'undefined') {
            communicationChips.push({ key: 'whatsapp', label: whatsappLabel, enabled: !!member.whatsappOptIn });
        }
        if (typeof member.photoUsageConsent !== 'undefined') {
            communicationChips.push({ key: 'photo', label: photoConsentLabel, enabled: !!member.photoUsageConsent });
        }

        var contactMessageViewUrl = typeof config.contactMessageViewUrl === 'string' ? config.contactMessageViewUrl : '';
        var contactMessageListUrl = typeof config.contactMessageListUrl === 'string' ? config.contactMessageListUrl : '';

        var hasMemberBio = (member.descriptionShort && member.descriptionShort.trim() !== '') || (member.descriptionLong && member.descriptionLong.trim() !== '');
        var profileTitle = getString(strings, 'memberProfile', 'Profil');
        var messageHistoryLabel = getString(strings, 'messageHistory', 'Historique');
        var fieldIdPrefix = 'member-edit-' + (member && member.id ? member.id : 'current') + '-';

        return h('div', { class: 'mj-regmgr-member-detail' }, [
            // Header avec avatar
            h('div', { class: 'mj-regmgr-member-detail__header' }, [
                h(MemberAvatar, { member: member, size: 'large' }),
                h('div', { class: 'mj-regmgr-member-detail__identity' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__name' }, 
                        (member.firstName || '') + ' ' + (member.lastName || '')
                    ),
                    member.role && h('span', { 
                        class: 'mj-regmgr-badge mj-regmgr-badge--role-' + member.role 
                    }, roleLabels[member.role] || member.role),
                ]),
                h('div', { class: 'mj-regmgr-member-detail__header-actions' }, [
                    whatsappLink && h('a', {
                        href: whatsappLink,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        class: 'mj-regmgr-member-detail__contact',
                        title: getString(strings, 'contactWhatsapp', 'WhatsApp'),
                        'aria-label': getString(strings, 'contactWhatsapp', 'WhatsApp'),
                    }, [
                        h('svg', { class: 'mj-regmgr-member-detail__contact-icon', width: 28, height: 28, viewBox: '0 0 32 32', fill: 'none' }, [
                            h('path', {
                                d: 'M26.576 5.363c-2.69-2.69-6.406-4.354-10.511-4.354-8.209 0-14.865 6.655-14.865 14.865 0 2.732 0.737 5.291 2.022 7.491l-0.038-0.070-2.109 7.702 7.879-2.067c2.051 1.139 4.498 1.809 7.102 1.809h0.006c8.209-0.003 14.862-6.659 14.862-14.868 0-4.103-1.662-7.817-4.349-10.507l0 0zM16.062 28.228h-0.005c-0 0-0.001 0-0.001 0-2.319 0-4.489-0.64-6.342-1.753l0.056 0.031-0.451-0.267-4.675 1.227 1.247-4.559-0.294-0.467c-1.185-1.862-1.889-4.131-1.889-6.565 0-6.822 5.531-12.353 12.353-12.353s12.353 5.531 12.353 12.353c0 6.822-5.53 12.353-12.353 12.353h-0zM22.838 18.977c-0.371-0.186-2.197-1.083-2.537-1.208-0.341-0.124-0.589-0.185-0.837 0.187-0.246 0.371-0.958 1.207-1.175 1.455-0.216 0.249-0.434 0.279-0.805 0.094-1.15-0.466-2.138-1.087-2.997-1.852l0.010 0.009c-0.799-0.74-1.484-1.587-2.037-2.521l-0.028-0.052c-0.216-0.371-0.023-0.572 0.162-0.757 0.167-0.166 0.372-0.434 0.557-0.65 0.146-0.179 0.271-0.384 0.366-0.604l0.006-0.017c0.043-0.087 0.068-0.188 0.068-0.296 0-0.131-0.037-0.253-0.101-0.357l0.002 0.003c-0.094-0.186-0.836-2.014-1.145-2.758-0.302-0.724-0.609-0.625-0.836-0.637-0.216-0.010-0.464-0.012-0.712-0.012-0.395 0.010-0.746 0.188-0.988 0.463l-0.001 0.002c-0.802 0.761-1.3 1.834-1.3 3.023 0 0.026 0 0.053 0.001 0.079l-0-0.004c0.131 1.467 0.681 2.784 1.527 3.857l-0.012-0.015c1.604 2.379 3.742 4.282 6.251 5.564l0.094 0.043c0.548 0.248 1.25 0.513 1.968 0.74l0.149 0.041c0.442 0.14 0.951 0.221 1.479 0.221 0.303 0 0.601-0.027 0.889-0.078l-0.031 0.004c1.069-0.223 1.956-0.868 2.497-1.749l0.009-0.017c0.165-0.366 0.261-0.793 0.261-1.242 0-0.185-0.016-0.366-0.047-0.542l0.003 0.019c-0.092-0.155-0.34-0.247-0.712-0.434z',
                                fill: 'currentColor',
                            }),
                        ]),
                    ]),
                    onResetPassword && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--icon mj-btn--secondary',
                        onClick: handleResetPassword,
                        disabled: !hasLinkedAccount || resettingPassword,
                        title: resettingPassword ? resetPasswordProcessingLabel : (hasLinkedAccount ? resetPasswordLabel : resetPasswordUnavailableLabel),
                        'aria-label': resettingPassword ? resetPasswordProcessingLabel : (hasLinkedAccount ? resetPasswordLabel : resetPasswordUnavailableLabel),
                    }, [
                        resettingPassword
                            ? h('span', { class: 'mj-regmgr-loading__spinner', style: { width: '16px', height: '16px' } })
                            : h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                h('circle', { cx: 7, cy: 12, r: 3 }),
                                h('line', { x1: 10, y1: 12, x2: 21, y2: 12 }),
                                h('line', { x1: 17, y1: 12, x2: 17, y2: 16 }),
                                h('line', { x1: 14, y1: 12, x2: 14, y2: 14 }),
                            ]),
                    ]),
                    !editMode && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--icon mj-btn--secondary',
                        onClick: function () { setEditMode(true); },
                        title: 'Modifier',
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                            h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                        ]),
                    ]),
                ]),
            ]),

            h('div', { class: 'mj-regmgr-member-detail__content' }, [
                // Section informations
                h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 'Informations'),

                    editMode ? [
                        h('div', { class: 'mj-regmgr-event-editor__section mj-regmgr-member-editor' }, [
                            h('div', { class: 'mj-regmgr-form-grid' }, [
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'first-name' }, 'Prénom'),
                                    h('input', {
                                        id: fieldIdPrefix + 'first-name',
                                        type: 'text',
                                        class: 'mj-regmgr-input',
                                        value: editData.firstName,
                                        onInput: handleFieldChange('firstName'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'last-name' }, 'Nom'),
                                    h('input', {
                                        id: fieldIdPrefix + 'last-name',
                                        type: 'text',
                                        class: 'mj-regmgr-input',
                                        value: editData.lastName,
                                        onInput: handleFieldChange('lastName'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'nickname' }, 'Surnom'),
                                    h('input', {
                                        id: fieldIdPrefix + 'nickname',
                                        type: 'text',
                                        class: 'mj-regmgr-input',
                                        value: editData.nickname || '',
                                        onInput: handleFieldChange('nickname'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'email' }, 'Email'),
                                    h('input', {
                                        id: fieldIdPrefix + 'email',
                                        type: 'email',
                                        class: 'mj-regmgr-input',
                                        value: editData.email,
                                        onInput: handleFieldChange('email'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'phone' }, 'Téléphone'),
                                    h('input', {
                                        id: fieldIdPrefix + 'phone',
                                        type: 'tel',
                                        class: 'mj-regmgr-input',
                                        value: editData.phone,
                                        onInput: handleFieldChange('phone'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'birth-date' }, 'Date de naissance'),
                                    h('input', {
                                        id: fieldIdPrefix + 'birth-date',
                                        type: 'date',
                                        class: 'mj-regmgr-input',
                                        value: editData.birthDate,
                                        onInput: handleFieldChange('birthDate'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'address' }, 'Adresse'),
                                    h('input', {
                                        id: fieldIdPrefix + 'address',
                                        type: 'text',
                                        class: 'mj-regmgr-input',
                                        value: editData.addressLine || '',
                                        onInput: handleFieldChange('addressLine'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'postal-code' }, 'Code postal'),
                                    h('input', {
                                        id: fieldIdPrefix + 'postal-code',
                                        type: 'text',
                                        class: 'mj-regmgr-input',
                                        value: editData.postalCode || '',
                                        onInput: handleFieldChange('postalCode'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'city' }, 'Ville'),
                                    h('input', {
                                        id: fieldIdPrefix + 'city',
                                        type: 'text',
                                        class: 'mj-regmgr-input',
                                        value: editData.city || '',
                                        onInput: handleFieldChange('city'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox' }, [
                                    h('label', { class: 'mj-regmgr-checkbox' }, [
                                        h('input', {
                                            id: fieldIdPrefix + 'is-volunteer',
                                            type: 'checkbox',
                                            checked: !!editData.isVolunteer,
                                            onChange: handleBooleanChange('isVolunteer'),
                                        }),
                                        h('span', null, 'Bénévole'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox' }, [
                                    h('label', { class: 'mj-regmgr-checkbox' }, [
                                        h('input', {
                                            id: fieldIdPrefix + 'is-autonomous',
                                            type: 'checkbox',
                                            checked: !!editData.isAutonomous,
                                            onChange: handleBooleanChange('isAutonomous'),
                                        }),
                                        h('span', null, 'Autonome'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox' }, [
                                    h('label', { class: 'mj-regmgr-checkbox' }, [
                                        h('input', {
                                            id: fieldIdPrefix + 'newsletter-optin',
                                            type: 'checkbox',
                                            checked: !!editData.newsletterOptIn,
                                            onChange: handleBooleanChange('newsletterOptIn'),
                                        }),
                                        h('span', null, 'Newsletter'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox' }, [
                                    h('label', { class: 'mj-regmgr-checkbox' }, [
                                        h('input', {
                                            id: fieldIdPrefix + 'sms-optin',
                                            type: 'checkbox',
                                            checked: !!editData.smsOptIn,
                                            onChange: handleBooleanChange('smsOptIn'),
                                        }),
                                        h('span', null, 'SMS'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox' }, [
                                    h('label', { class: 'mj-regmgr-checkbox' }, [
                                        h('input', {
                                            id: fieldIdPrefix + 'whatsapp-optin',
                                            type: 'checkbox',
                                            checked: !!editData.whatsappOptIn,
                                            onChange: handleBooleanChange('whatsappOptIn'),
                                        }),
                                        h('span', null, 'WhatsApp'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--checkbox' }, [
                                    h('label', { class: 'mj-regmgr-checkbox' }, [
                                        h('input', {
                                            id: fieldIdPrefix + 'photo-consent',
                                            type: 'checkbox',
                                            checked: !!editData.photoUsageConsent,
                                            onChange: handleBooleanChange('photoUsageConsent'),
                                        }),
                                        h('span', null, 'Consentement photo'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'bio-short' }, getString(strings, 'memberBioShort', 'Bio courte')),
                                    h('textarea', {
                                        id: fieldIdPrefix + 'bio-short',
                                        class: 'mj-regmgr-textarea',
                                        rows: 3,
                                        value: editData.descriptionShort || '',
                                        onInput: handleFieldChange('descriptionShort'),
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-form-field mj-regmgr-form-field--full' }, [
                                    h('label', { htmlFor: fieldIdPrefix + 'bio-long' }, getString(strings, 'memberBioLong', 'Bio détaillée')),
                                    h('textarea', {
                                        id: fieldIdPrefix + 'bio-long',
                                        class: 'mj-regmgr-textarea',
                                        rows: 4,
                                        value: editData.descriptionLong || '',
                                        onInput: handleFieldChange('descriptionLong'),
                                    }),
                                ]),
                            ]),
                            h('div', { class: 'mj-regmgr-event-editor__actions mj-regmgr-member-editor__actions' }, [
                                h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--secondary',
                                    onClick: function () {
                                        setEditData(buildInitialEditData(member));
                                        setEditMode(false);
                                    },
                                }, getString(strings, 'cancel', 'Annuler')),
                                h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--primary',
                                    onClick: handleSave,
                                }, getString(strings, 'save', 'Enregistrer')),
                            ]),
                        ]),
                    ] : [
                        // Mode lecture
                        h('div', { class: 'mj-regmgr-member-detail__info' }, [
                            member.email && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z' }),
                                    h('polyline', { points: '22,6 12,13 2,6' }),
                                ]),
                                h('span', null, member.email),
                            ]),
                            member.nickname && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2' }),
                                ]),
                                h('span', null, member.nickname),
                            ]),
                            member.phone && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z' }),
                                ]),
                                h('span', null, member.phone),
                            ]),
                            addressDisplay && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z' }),
                                    h('circle', { cx: 12, cy: 10, r: 3 }),
                                ]),
                                h('span', null, addressDisplay),
                            ]),
                            (member.birthDate || age !== null) && h('div', { class: 'mj-regmgr-member-detail__row' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('rect', { x: 3, y: 4, width: 18, height: 18, rx: 2, ry: 2 }),
                                    h('line', { x1: 16, y1: 2, x2: 16, y2: 6 }),
                                    h('line', { x1: 8, y1: 2, x2: 8, y2: 6 }),
                                    h('line', { x1: 3, y1: 10, x2: 21, y2: 10 }),
                                ]),
                                h('span', null, [
                                    member.birthDate && formatDate(member.birthDate),
                                    age !== null && ' (' + age + ' ans)',
                                ]),
                            ]),
                            member.guardianName && h('div', { class: 'mj-regmgr-member-detail__row mj-regmgr-member-detail__row--guardian' }, [
                                h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                                    h('circle', { cx: 9, cy: 7, r: 4 }),
                                    h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                                    h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                                ]),
                                h('div', { class: 'mj-regmgr-guardian' }, [
                                    guardian ? h(MemberAvatar, { member: guardian, size: 'small' }) : null,
                                    h('div', { class: 'mj-regmgr-guardian__info' }, [
                                        h('span', { class: 'mj-regmgr-guardian__label' }, 'Tuteur référent'),
                                        h('span', { class: 'mj-regmgr-guardian__name' }, member.guardianName),
                                    ]),
                                ]),
                                canEditGuardianInline
                                    ? h('button', {
                                        type: 'button',
                                        class: 'mj-regmgr-guardian__edit-link',
                                        onClick: handleGuardianEditClick,
                                    }, [
                                        h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                            h('path', { d: 'M12 20h9' }),
                                            h('path', { d: 'M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z' }),
                                        ]),
                                        h('span', null, 'Modifier'),
                                    ])
                                    : guardianEditUrl && h('a', {
                                        class: 'mj-regmgr-guardian__edit-link',
                                        href: guardianEditUrl,
                                        target: '_blank',
                                        rel: 'noopener noreferrer',
                                    }, [
                                        h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                            h('path', { d: 'M12 20h9' }),
                                            h('path', { d: 'M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z' }),
                                        ]),
                                        h('span', null, 'Modifier'),
                                    ]),
                            ]),
                        ]),
                    ],
                ]),

                // Section Cotisation & Statut
                h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 'Cotisation & Statut'),
                    h('div', { class: 'mj-regmgr-member-detail__membership' }, [
                        h('div', { class: 'mj-regmgr-member-detail__membership-main' }, [
                            h('div', { class: 'mj-regmgr-member-detail__membership-item' }, [
                                h('span', { class: 'mj-regmgr-member-detail__status-label' }, 'Statut du compte'),
                                h('span', {
                                    class: classNames('mj-regmgr-badge', {
                                        'mj-regmgr-badge--success': member.status === 'active',
                                        'mj-regmgr-badge--secondary': member.status !== 'active',
                                    }),
                                }, statusLabels[member.status] || member.status || 'Actif'),
                            ]),
                            h('div', { class: 'mj-regmgr-member-detail__membership-item mj-regmgr-member-detail__membership-item--subscription' }, [
                                h('span', { class: 'mj-regmgr-member-detail__status-label' }, 'Cotisation'),
                                h('div', { class: 'mj-regmgr-member-detail__membership-info' }, [
                                    h('span', {
                                        class: classNames('mj-regmgr-badge', {
                                            'mj-regmgr-badge--success': member.membershipStatus === 'paid',
                                            'mj-regmgr-badge--warning': member.membershipStatus === 'expired',
                                            'mj-regmgr-badge--danger': member.membershipStatus === 'unpaid',
                                            'mj-regmgr-badge--secondary': member.membershipStatus === 'not_required',
                                        }),
                                    }, [
                                        membershipLabels[member.membershipStatus] || 'Inconnu',
                                        member.membershipYear && member.membershipStatus === 'paid' && ' (' + member.membershipYear + ')',
                                    ]),
                                    member.requiresPayment && member.membershipStatus !== 'paid' && h('div', { class: 'mj-regmgr-member-detail__membership-actions' }, [
                                        h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--small mj-btn--primary',
                                            onClick: function () { setShowPaymentModal(true); },
                                            disabled: paymentProcessing,
                                        }, [
                                            h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                                                h('line', { x1: 1, y1: 10, x2: 23, y2: 10 }),
                                            ]),
                                            ' Payer',
                                        ]),
                                        h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--small mj-btn--secondary',
                                            onClick: function () {
                                                if (confirm('Confirmer que la cotisation a été payée en main propre ?')) {
                                                    setPaymentProcessing(true);
                                                    onMarkMembershipPaid(member.id, 'cash')
                                                        .finally(function () { setPaymentProcessing(false); });
                                                }
                                            },
                                            disabled: paymentProcessing,
                                        }, paymentProcessing ? 'Traitement...' : 'Payé en main propre'),
                                    ]),
                                ]),
                            ]),
                            communicationChips.length > 0 && h('div', { class: 'mj-regmgr-member-detail__membership-item mj-regmgr-member-detail__membership-item--communication' }, [
                                h('span', { class: 'mj-regmgr-member-detail__status-label' }, 'Communication'),
                                h('ul', { class: 'mj-regmgr-communication-list' }, communicationChips.map(function (chip) {
                                    var isEnabled = !!chip.enabled;
                                    var chipStateLabel = isEnabled ? communicationEnabledLabel : communicationDisabledLabel;
                                    return h('li', {
                                        key: chip.key,
                                        class: classNames('mj-regmgr-communication-item', {
                                            'mj-regmgr-communication-item--enabled': isEnabled,
                                            'mj-regmgr-communication-item--disabled': !isEnabled,
                                        }),
                                        title: chip.label + ' · ' + chipStateLabel,
                                        'aria-label': chip.label + ' : ' + chipStateLabel,
                                    }, [
                                        h('span', { class: 'mj-regmgr-communication-item__icon', 'aria-hidden': 'true' }, [
                                            isEnabled
                                                ? h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                    h('polyline', { points: '5 13 9 17 19 7' }),
                                                ])
                                                : h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                    h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                                                    h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                                                ]),
                                        ]),
                                        h('span', { class: 'mj-regmgr-communication-item__label' }, chip.label),
                                        h('span', { class: 'mj-regmgr-communication-item__state' }, chipStateLabel),
                                    ]);
                                })),
                            ]),
                        ]),
                        h('aside', { class: 'mj-regmgr-member-detail__membership-meta' }, [
                            member.membershipNumber && h('div', { class: 'mj-regmgr-member-detail__meta-row' }, [
                                h('span', { class: 'mj-regmgr-member-detail__meta-label' }, 'N° de membre'),
                                h('span', { class: 'mj-regmgr-member-detail__meta-value' }, member.membershipNumber),
                            ]),
                            member.dateInscription && h('div', { class: 'mj-regmgr-member-detail__meta-row' }, [
                                h('span', { class: 'mj-regmgr-member-detail__meta-label' }, 'Inscrit depuis'),
                                h('span', { class: 'mj-regmgr-member-detail__meta-value' }, formatDate(member.dateInscription)),
                            ]),
                            member.isVolunteer && h('div', { class: 'mj-regmgr-member-detail__meta-row' }, [
                                h('span', { class: 'mj-regmgr-member-detail__meta-label' }, 'Bénévole'),
                                h('span', { class: 'mj-regmgr-badge mj-regmgr-badge--info' }, 'Oui'),
                            ]),
                            member.role === 'jeune' && h('div', { class: 'mj-regmgr-member-detail__meta-row' }, [
                                h('span', { class: 'mj-regmgr-member-detail__meta-label' }, 'Autonome'),
                                h('span', {
                                    class: classNames('mj-regmgr-badge', {
                                        'mj-regmgr-badge--info': member.isAutonomous,
                                        'mj-regmgr-badge--secondary': !member.isAutonomous,
                                    }),
                                }, member.isAutonomous ? 'Oui' : 'Non'),
                            ]),
                        ]),
                    ]),
                ]),
                hasMemberBio && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, profileTitle),
                    member.descriptionShort && h('p', { class: 'mj-regmgr-member-detail__bio-short' }, member.descriptionShort),
                    member.descriptionLong && h('div', { class: 'mj-regmgr-member-detail__bio-long', dangerouslySetInnerHTML: { __html: member.descriptionLong } }),
                ]),

                memberPhotos.length > 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberPhotos', 'Photos partagées')),
                    h('div', { class: 'mj-regmgr-member-detail__photos' }, memberPhotos.map(function (photo) {
                        var statusKey = photo.status || 'approved';
                        var statusLabel = photo.statusLabel || photoStatusLabels[statusKey] || statusKey;
                        var isEditingPhoto = editingPhotoId === photo.id;
                        return h('div', {
                            key: photo.id,
                            class: classNames('mj-regmgr-member-photo', {
                                'mj-regmgr-member-photo--editing': isEditingPhoto,
                            }),
                        }, [
                            h('figure', { class: 'mj-regmgr-member-photo__figure' }, [
                                h('a', {
                                    href: photo.fullUrl || photo.thumbnailUrl,
                                    target: '_blank',
                                    rel: 'noreferrer',
                                    class: 'mj-regmgr-member-photo__link',
                                }, [
                                    h('img', { src: photo.thumbnailUrl, alt: photo.caption || '', loading: 'lazy' }),
                                ]),
                                h('figcaption', { class: 'mj-regmgr-member-photo__caption' }, [
                                    photo.eventTitle && h('span', { class: 'mj-regmgr-member-photo__event' }, photo.eventTitle),
                                    !isEditingPhoto && photo.caption && h('span', { class: 'mj-regmgr-member-photo__text' }, photo.caption),
                                ]),
                            ]),
                            h('div', { class: 'mj-regmgr-member-photo__meta' }, [
                                    h('span', {
                                        class: classNames('mj-regmgr-badge', photoStatusClasses[statusKey] || ''),
                                    }, statusLabel),
                                onUpdatePhoto && !isEditingPhoto && h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--icon mj-btn--ghost mj-btn--small',
                                    onClick: function () { handlePhotoEditStart(photo); },
                                    title: 'Modifier',
                                    disabled: photoDeletingId === photo.id,
                                }, [
                                    h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                        h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                                        h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                                    ]),
                                ]),
                                onDeletePhoto && !isEditingPhoto && h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--ghost mj-btn--danger mj-btn--small',
                                    onClick: function () { handleDeletePhoto(photo); },
                                    disabled: photoDeletingId === photo.id,
                                }, photoDeletingId === photo.id ? 'Suppression...' : 'Supprimer'),
                            ]),
                            isEditingPhoto && h('div', { class: 'mj-regmgr-member-photo__edit' }, [
                                h('label', { class: 'mj-regmgr-member-photo__edit-label' }, 'Légende'),
                                h('textarea', {
                                    class: 'mj-regmgr-textarea',
                                    rows: 3,
                                    value: photoDraft.caption,
                                    onInput: function (e) {
                                        var value = e.target.value;
                                        setPhotoDraft(function (prev) {
                                            var next = prev ? Object.assign({}, prev) : {};
                                            next.caption = value;
                                            return next;
                                        });
                                    },
                                }),
                                h('label', { class: 'mj-regmgr-member-photo__edit-label' }, 'Statut'),
                                h('select', {
                                    class: 'mj-regmgr-select',
                                    value: photoDraft.status,
                                    onChange: function (e) {
                                        var value = e.target.value;
                                        setPhotoDraft(function (prev) {
                                            var next = prev ? Object.assign({}, prev) : {};
                                            next.status = value;
                                            return next;
                                        });
                                    },
                                }, Object.keys(photoStatusLabels).map(function (key) {
                                    return h('option', { key: key, value: key }, photoStatusLabels[key]);
                                })),
                                h('div', { class: 'mj-regmgr-member-photo__edit-actions' }, [
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--secondary mj-btn--small',
                                        onClick: handlePhotoEditCancel,
                                        disabled: photoSaving,
                                    }, 'Annuler'),
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--primary mj-btn--small',
                                        onClick: handleSavePhoto,
                                        disabled: photoSaving,
                                    }, photoSaving ? 'Enregistrement...' : 'Enregistrer'),
                                ]),
                            ]),
                        ]);
                    })),
                ]),
                memberPhotos.length === 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberPhotos', 'Photos partagées')),
                    h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'memberNoPhotos', 'Aucune photo validée pour ce membre.')),
                ]),

                memberIdeas.length > 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberIdeas', 'Idées proposées')),
                    h('div', { class: 'mj-regmgr-member-detail__ideas' }, memberIdeas.map(function (idea) {
                        var statusKey = idea.status || 'published';
                        var statusLabel = ideaStatusLabels[statusKey] || statusKey;
                        var isEditingIdea = editingIdeaId === idea.id;
                        return h('article', {
                            key: idea.id,
                            class: classNames('mj-regmgr-member-idea', {
                                'mj-regmgr-member-idea--editing': isEditingIdea,
                            }),
                        }, [
                            h('header', { class: 'mj-regmgr-member-idea__header' }, [
                                h('div', { class: 'mj-regmgr-member-idea__heading' }, [
                                    h('span', {
                                        class: classNames('mj-regmgr-badge mj-regmgr-badge--sm', ideaStatusClasses[statusKey] || ''),
                                    }, statusLabel),
                                    !isEditingIdea && h('h2', { class: 'mj-regmgr-member-idea__title' }, idea.title || 'Idée'),
                                    !isEditingIdea && idea.voteCount >= 0 && h('span', { class: 'mj-regmgr-member-idea__votes' }, idea.voteCount + ' ❤'),
                                ]),
                                onUpdateIdea && !isEditingIdea && h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--icon mj-btn--ghost mj-btn--small',
                                    onClick: function () { handleIdeaEditStart(idea); },
                                    title: 'Modifier',
                                }, [
                                    h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                        h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                                        h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                                    ]),
                                ]),
                            ]),
                            !isEditingIdea && h(Fragment, null, [
                                idea.content && h('p', { class: 'mj-regmgr-member-idea__content' }, idea.content),
                                idea.createdAt && h('p', { class: 'mj-regmgr-member-idea__meta' }, formatDate(idea.createdAt)),
                            ]),
                            isEditingIdea && h('div', { class: 'mj-regmgr-member-idea__edit' }, [
                                h('label', { class: 'mj-regmgr-member-idea__edit-label' }, 'Titre'),
                                h('input', {
                                    type: 'text',
                                    class: 'mj-regmgr-input',
                                    value: ideaDraft.title,
                                    onInput: function (e) {
                                        var value = e.target.value;
                                        setIdeaDraft(function (prev) {
                                            var next = prev ? Object.assign({}, prev) : {};
                                            next.title = value;
                                            return next;
                                        });
                                    },
                                }),
                                h('label', { class: 'mj-regmgr-member-idea__edit-label' }, 'Description'),
                                h('textarea', {
                                    class: 'mj-regmgr-textarea',
                                    rows: 4,
                                    value: ideaDraft.content,
                                    onInput: function (e) {
                                        var value = e.target.value;
                                        setIdeaDraft(function (prev) {
                                            var next = prev ? Object.assign({}, prev) : {};
                                            next.content = value;
                                            return next;
                                        });
                                    },
                                }),
                                h('label', { class: 'mj-regmgr-member-idea__edit-label' }, 'Statut'),
                                h('select', {
                                    class: 'mj-regmgr-select',
                                    value: ideaDraft.status,
                                    onChange: function (e) {
                                        var value = e.target.value;
                                        setIdeaDraft(function (prev) {
                                            var next = prev ? Object.assign({}, prev) : {};
                                            next.status = value;
                                            return next;
                                        });
                                    },
                                }, Object.keys(ideaStatusLabels).map(function (key) {
                                    return h('option', { key: key, value: key }, ideaStatusLabels[key]);
                                })),
                                h('div', { class: 'mj-regmgr-member-idea__edit-actions' }, [
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--secondary mj-btn--small',
                                        onClick: handleIdeaEditCancel,
                                        disabled: ideaSaving,
                                    }, 'Annuler'),
                                    h('button', {
                                        type: 'button',
                                        class: 'mj-btn mj-btn--primary mj-btn--small',
                                        onClick: handleSaveIdea,
                                        disabled: ideaSaving || !ideaDraft.title || !ideaDraft.title.trim() || !ideaDraft.content || !ideaDraft.content.trim(),
                                    }, ideaSaving ? 'Enregistrement...' : 'Enregistrer'),
                                ]),
                            ]),
                        ]);
                    })),
                ]),
                memberIdeas.length === 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberIdeas', 'Idées proposées')),
                    h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'memberNoIdeas', 'Aucune idée proposée pour le moment.')),
                ]),

                memberMessages.length > 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('div', { class: 'mj-regmgr-member-detail__section-header' }, [
                        h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberMessages', 'Messages reçus')),
                        contactMessageListUrl && h('a', {
                            href: contactMessageListUrl,
                            target: '_blank',
                            rel: 'noreferrer',
                            class: 'mj-btn mj-btn--ghost mj-btn--small',
                        }, getString(strings, 'viewAllMessages', 'Voir tous les messages')),
                    ]),
                    h('div', { class: 'mj-regmgr-member-detail__messages' }, memberMessages.map(function (message) {
                        var status = message.status || '';
                        return h('article', { key: message.id, class: 'mj-regmgr-member-message' }, [
                            h('header', { class: 'mj-regmgr-member-message__header' }, [
                                h('h2', { class: 'mj-regmgr-member-message__subject' }, message.subject || '(Sans objet)'),
                                h('span', {
                                    class: classNames('mj-regmgr-badge mj-regmgr-badge--sm', messageStatusClasses[status] || 'mj-regmgr-badge--secondary'),
                                }, messageStatusLabels[status] || status || 'N/A'),
                            ]),
                            h('p', { class: 'mj-regmgr-member-message__meta' }, [
                                message.senderName || message.senderEmail || 'Anonyme',
                                message.createdAt ? ' · ' + formatDate(message.createdAt) : '',
                            ]),
                            message.message && h('div', {
                                class: 'mj-regmgr-member-message__content',
                                dangerouslySetInnerHTML: { __html: message.message },
                            }),
                            message.activityLog && message.activityLog.length > 0 && h('details', { class: 'mj-regmgr-member-message__activity' }, [
                                h('summary', null, messageHistoryLabel),
                                h('ul', null, message.activityLog.map(function (entry, index) {
                                    return h('li', { key: index }, [
                                        entry.date ? formatDate(entry.date) + ' · ' : '',
                                        entry.note || '',
                                    ]);
                                })),
                            ]),
                            (contactMessageViewUrl || onDeleteMessage) && h('div', { class: 'mj-regmgr-member-message__actions' }, [
                                contactMessageViewUrl && h('a', {
                                    href: contactMessageViewUrl + message.id,
                                    class: 'mj-btn mj-btn--secondary mj-btn--small',
                                    target: '_blank',
                                    rel: 'noreferrer',
                                }, getString(strings, 'viewMessage', 'Ouvrir le message')),
                                onDeleteMessage && h('button', {
                                    type: 'button',
                                    class: 'mj-btn mj-btn--ghost mj-btn--danger mj-btn--small',
                                    onClick: function () { handleDeleteMessage(message); },
                                    disabled: messageDeletingId === message.id,
                                }, messageDeletingId === message.id ? 'Suppression...' : 'Supprimer'),
                            ]),
                        ]);
                    })),
                ]),
                memberMessages.length === 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberMessages', 'Messages reçus')),
                    h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'memberNoMessages', 'Aucun échange trouvé pour ce membre.')),
                ]),

                // Section notes
                h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 
                        'Notes (' + notes.length + ')'
                    ),

                    // Ajouter une note
                    h('div', { class: 'mj-regmgr-member-detail__add-note' }, [
                        h('textarea', {
                            class: 'mj-regmgr-textarea',
                            placeholder: 'Ajouter une note...',
                            value: newNote,
                            onInput: function (e) { setNewNote(e.target.value); },
                            rows: 3,
                        }),
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--primary mj-btn--small',
                            onClick: handleAddNote,
                            disabled: savingNote || !newNote.trim(),
                        }, savingNote ? 'Enregistrement...' : 'Ajouter'),
                    ]),

                    // Liste des notes
                    notes.length > 0 && h('div', { class: 'mj-regmgr-member-detail__notes' }, 
                        notes.map(function (note) {
                            var isEditing = editingNoteId === note.id;
                            return h('div', { key: note.id, class: 'mj-regmgr-note-card' }, [
                                h('div', { class: 'mj-regmgr-note-card__header' }, [
                                    h('span', { class: 'mj-regmgr-note-card__author' }, note.authorName || 'Anonyme'),
                                    h('span', { class: 'mj-regmgr-note-card__date' }, formatDate(note.createdAt)),
                                    h('div', { class: 'mj-regmgr-note-card__actions' }, [
                                        note.canEdit && !isEditing && h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--icon mj-btn--ghost',
                                            onClick: function () { handleNoteEditStart(note); },
                                            title: 'Modifier',
                                        }, [
                                            h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                                                h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                                            ]),
                                        ]),
                                        isEditing && h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--icon mj-btn--ghost',
                                            onClick: handleNoteEditCancel,
                                            title: 'Annuler',
                                        }, [
                                            h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                                                h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                                            ]),
                                        ]),
                                        h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--icon mj-btn--ghost mj-btn--danger',
                                            onClick: function () { onDeleteNote(note.id); },
                                            title: 'Supprimer',
                                        }, [
                                            h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                h('polyline', { points: '3 6 5 6 21 6' }),
                                                h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2' }),
                                            ]),
                                        ]),
                                    ]),
                                ]),
                                isEditing
                                    ? h('div', { class: 'mj-regmgr-note-card__editor' }, [
                                        h('textarea', {
                                            class: 'mj-regmgr-textarea',
                                            rows: 3,
                                            value: editingNoteContent,
                                            onInput: function (e) { setEditingNoteContent(e.target.value); },
                                        }),
                                        h('div', { class: 'mj-regmgr-note-card__editor-actions' }, [
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-btn mj-btn--secondary mj-btn--small',
                                                onClick: handleNoteEditCancel,
                                                disabled: editingNoteSaving,
                                            }, 'Annuler'),
                                            h('button', {
                                                type: 'button',
                                                class: 'mj-btn mj-btn--primary mj-btn--small',
                                                onClick: handleUpdateNote,
                                                disabled: editingNoteSaving || !editingNoteContent.trim(),
                                            }, editingNoteSaving ? 'Enregistrement...' : 'Enregistrer'),
                                        ]),
                                    ])
                                    : h('div', { class: 'mj-regmgr-note-card__content' }, note.content),
                            ]);
                        })
                    ),
                ]),

                // Section enfants (si le membre est tuteur)
                member.children && member.children.length > 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 
                        'Enfants (' + member.children.length + ')'
                    ),
                    h('div', { class: 'mj-regmgr-member-detail__children' }, 
                        member.children.map(function (child) {
                            var childAge = child.birthDate ? calculateAge(child.birthDate) : null;
                            return h('div', { key: child.id, class: 'mj-regmgr-child-card' }, [
                                h('div', { class: 'mj-regmgr-child-card__avatar' }, [
                                    child.avatarUrl 
                                        ? h('img', { src: child.avatarUrl, alt: '', class: 'mj-regmgr-child-card__img' })
                                        : h('div', { class: 'mj-regmgr-child-card__initials' }, 
                                            ((child.firstName || '')[0] || '') + ((child.lastName || '')[0] || '')
                                        ),
                                ]),
                                h('div', { class: 'mj-regmgr-child-card__info' }, [
                                    h('div', { class: 'mj-regmgr-child-card__name' }, 
                                        (child.firstName || '') + ' ' + (child.lastName || '')
                                    ),
                                    h('div', { class: 'mj-regmgr-child-card__meta' }, [
                                        child.roleLabel && h('span', { class: 'mj-regmgr-badge mj-regmgr-badge--sm' }, child.roleLabel),
                                        childAge !== null && h('span', null, childAge + ' ans'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-child-card__status' }, [
                                    h('span', { 
                                        class: classNames('mj-regmgr-badge mj-regmgr-badge--sm', {
                                            'mj-regmgr-badge--success': child.membershipStatus === 'paid',
                                            'mj-regmgr-badge--warning': child.membershipStatus === 'expired',
                                            'mj-regmgr-badge--danger': child.membershipStatus === 'unpaid',
                                            'mj-regmgr-badge--secondary': child.membershipStatus === 'not_required',
                                        })
                                    }, membershipLabels[child.membershipStatus] || 'N/A'),
                                    child.membershipYear && h('span', { class: 'mj-regmgr-child-card__year' }, child.membershipYear),
                                ]),
                                h('div', { class: 'mj-regmgr-child-card__actions' }, [
                                    config.adminMemberUrl && h('a', {
                                        href: config.adminMemberUrl + child.id,
                                        target: '_blank',
                                        class: 'mj-btn mj-btn--icon mj-btn--ghost',
                                        title: 'Modifier dans l\'admin',
                                    }, [
                                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                            h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                                            h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                                        ]),
                                    ]),
                                ]),
                            ]);
                        })
                    ),
                ]),

                // Section historique inscriptions
                registrations.length > 0 && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 
                        'Historique inscriptions (' + registrations.length + ')'
                    ),
                    h('div', { class: 'mj-regmgr-member-detail__registrations' }, 
                        registrations.map(function (reg) {
                            var statusClasses = {
                                'valide': 'mj-regmgr-badge--success',
                                'en_attente': 'mj-regmgr-badge--warning',
                                'annule': 'mj-regmgr-badge--danger',
                            };

                            var sessions = Array.isArray(reg.occurrenceDetails) ? reg.occurrenceDetails : [];
                            var coversAllSessions = !!reg.coversAllOccurrences;
                            var totalOccurrences = typeof reg.totalOccurrences === 'number' ? reg.totalOccurrences : 0;
                            var sessionsLabel = getString(strings, 'sessions', 'Séances');
                            var allSessionsLabel = getString(strings, 'allSessions', 'Toutes les séances');
                            var noSessionsLabel = getString(strings, 'noSessionsAssigned', 'Aucune séance assignée');

                            var sessionsContent = null;

                            if (sessions.length > 0) {
                                sessionsContent = h('div', { class: 'mj-regmgr-registration-item__sessions' }, [
                                    h('span', { class: 'mj-regmgr-registration-item__sessions-label' }, sessionsLabel + ' :'),
                                    h('div', { class: 'mj-regmgr-registration-item__sessions-list' },
                                        sessions.map(function (session, idx) {
                                            var chipClasses = classNames('mj-regmgr-session-chip', {
                                                'mj-regmgr-session-chip--past': !!session.isPast,
                                            });
                                            var key = session.start ? 'session-' + session.start : 'session-' + idx;
                                            var label = session.label || (session.start ? formatDate(session.start) : '');
                                            return h('span', { key: key, class: chipClasses }, label);
                                        })
                                    ),
                                ]);
                            } else if (coversAllSessions && totalOccurrences > 0) {
                                sessionsContent = h('div', { class: 'mj-regmgr-registration-item__sessions' }, [
                                    h('span', { class: 'mj-regmgr-registration-item__sessions-label' }, sessionsLabel + ' :'),
                                    h('span', { class: 'mj-regmgr-registration-item__sessions-placeholder' }, allSessionsLabel),
                                ]);
                            } else if (!coversAllSessions && totalOccurrences > 0) {
                                sessionsContent = h('div', { class: 'mj-regmgr-registration-item__sessions' }, [
                                    h('span', { class: 'mj-regmgr-registration-item__sessions-label' }, sessionsLabel + ' :'),
                                    h('span', { class: 'mj-regmgr-registration-item__sessions-placeholder' }, noSessionsLabel),
                                ]);
                            }

                            return h('div', { key: reg.id, class: 'mj-regmgr-registration-item' }, [
                                h('div', { class: 'mj-regmgr-registration-item__info' }, [
                                    h('span', { class: 'mj-regmgr-registration-item__event' }, reg.eventTitle || 'Événement'),
                                    h('span', { class: 'mj-regmgr-registration-item__date' }, formatDate(reg.createdAt)),
                                    sessionsContent,
                                ].filter(Boolean)),
                                h('span', { 
                                    class: classNames('mj-regmgr-badge', statusClasses[reg.status] || '')
                                }, reg.statusLabel || reg.status),
                            ]);
                        })
                    ),
                ]),
            ]),

            // Bouton admin
            config.adminMemberUrl && h('div', { class: 'mj-regmgr-member-detail__footer' }, [
                h('a', {
                    href: config.adminMemberUrl + member.id,
                    target: '_blank',
                    class: 'mj-btn mj-btn--secondary',
                }, [
                    h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                        h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                        h('polyline', { points: '15 3 21 3 21 9' }),
                        h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                    ]),
                    'Voir dans l\'admin',
                ]),
            ]),

            // Modal de paiement cotisation
            showPaymentModal && h(MembershipPaymentModal, {
                member: member,
                config: config,
                onClose: function () { 
                    setShowPaymentModal(false); 
                },
                onPayOnline: function (memberId) {
                    // Retourne la promesse pour que la modal puisse gérer l'état
                    return onPayMembershipOnline(memberId);
                },
                onMarkPaid: function (method) {
                    // Retourne la promesse pour que la modal puisse gérer l'état
                    return onMarkMembershipPaid(member.id, method)
                        .then(function () {
                            setShowPaymentModal(false);
                        });
                },
            }),
        ]);
    }

    // ============================================
    // MEMBERSHIP PAYMENT MODAL
    // ============================================

    function MembershipPaymentModal(props) {
        var member = props.member;
        var config = props.config;
        var onClose = props.onClose;
        var onPayOnline = props.onPayOnline;
        var onMarkPaid = props.onMarkPaid;

        var currentYear = new Date().getFullYear();
        var membershipPrice = parseFloat(config.membershipPrice) || 2;
        var membershipPriceManual = parseFloat(config.membershipPriceManual) || membershipPrice;

        // État local pour le processing et le QR code
        var _processing = useState(false);
        var processing = _processing[0];
        var setProcessing = _processing[1];

        var _paymentData = useState(null);
        var paymentData = _paymentData[0];
        var setPaymentData = _paymentData[1];

        var _error = useState(null);
        var error = _error[0];
        var setError = _error[1];

        var handleBackdropClick = function (e) {
            if (e.target === e.currentTarget && !processing) {
                onClose();
            }
        };

        var handlePayOnline = function () {
            setProcessing(true);
            setError(null);
            onPayOnline(member.id)
                .then(function (result) {
                    if (result && result.checkoutUrl) {
                        setPaymentData(result);
                    }
                })
                .catch(function (err) {
                    setError(err.message || 'Erreur lors de la création du lien de paiement');
                })
                .finally(function () {
                    setProcessing(false);
                });
        };

        var handleMarkPaid = function () {
            setProcessing(true);
            setError(null);
            onMarkPaid('cash')
                .finally(function () {
                    setProcessing(false);
                });
        };

        // Vue avec QR code après création du lien
        if (paymentData && paymentData.checkoutUrl) {
            return h('div', { 
                class: 'mj-regmgr-modal-backdrop',
                onClick: handleBackdropClick,
            }, [
                h('div', { class: 'mj-regmgr-modal mj-regmgr-modal--small' }, [
                    h('div', { class: 'mj-regmgr-modal__header' }, [
                        h('h2', { class: 'mj-regmgr-modal__title' }, 'Lien de paiement'),
                        h('button', {
                            type: 'button',
                            class: 'mj-regmgr-modal__close',
                            onClick: onClose,
                        }, [
                            h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                                h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                            ]),
                        ]),
                    ]),
                    h('div', { class: 'mj-regmgr-modal__body' }, [
                        // Info membre
                        h('div', { class: 'mj-regmgr-payment-info' }, [
                            h('div', { class: 'mj-regmgr-payment-info__member' }, [
                                h(MemberAvatar, { member: member, size: 'medium' }),
                                h('div', null, [
                                    h('strong', null, (member.firstName || '') + ' ' + (member.lastName || '')),
                                    h('div', { class: 'mj-regmgr-payment-info__detail' }, membershipPrice.toFixed(2) + ' €'),
                                ]),
                            ]),
                        ]),

                        // QR Code
                        paymentData.qrUrl && h('div', { class: 'mj-regmgr-payment-qr' }, [
                            h('img', {
                                src: paymentData.qrUrl,
                                alt: 'QR Code paiement',
                                class: 'mj-regmgr-payment-qr__image',
                            }),
                            h('p', { class: 'mj-regmgr-payment-qr__text' }, 'Scanner ce QR code pour payer'),
                        ]),

                        // Lien direct
                        h('div', { style: { marginTop: '16px', textAlign: 'center' } }, [
                            h('a', {
                                href: paymentData.checkoutUrl,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                                class: 'mj-btn mj-btn--primary mj-btn--block',
                            }, [
                                'Ouvrir la page de paiement',
                                h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, style: { marginLeft: '8px' } }, [
                                    h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                                    h('polyline', { points: '15 3 21 3 21 9' }),
                                    h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]);
        }

        // Vue initiale - choix du mode de paiement
        return h('div', { 
            class: 'mj-regmgr-modal-backdrop',
            onClick: handleBackdropClick,
        }, [
            h('div', { class: 'mj-regmgr-modal mj-regmgr-modal--small' }, [
                h('div', { class: 'mj-regmgr-modal__header' }, [
                    h('h2', { class: 'mj-regmgr-modal__title' }, 'Paiement cotisation ' + currentYear),
                    h('button', {
                        type: 'button',
                        class: 'mj-regmgr-modal__close',
                        onClick: onClose,
                        disabled: processing,
                    }, [
                        h('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('line', { x1: 18, y1: 6, x2: 6, y2: 18 }),
                            h('line', { x1: 6, y1: 6, x2: 18, y2: 18 }),
                        ]),
                    ]),
                ]),
                h('div', { class: 'mj-regmgr-modal__body' }, [
                    // Erreur éventuelle
                    error && h('div', { 
                        class: 'mj-regmgr-alert mj-regmgr-alert--error',
                        style: { marginBottom: '16px' },
                    }, error),

                    // Info membre
                    h('div', { class: 'mj-regmgr-payment-info' }, [
                        h('div', { class: 'mj-regmgr-payment-info__member' }, [
                            h(MemberAvatar, { member: member, size: 'medium' }),
                            h('div', null, [
                                h('strong', null, (member.firstName || '') + ' ' + (member.lastName || '')),
                                h('div', { class: 'mj-regmgr-payment-info__detail' }, 'Cotisation annuelle ' + currentYear),
                            ]),
                        ]),
                    ]),

                    h('div', { class: 'mj-regmgr-payment-options' }, [
                        // Option 1: Paiement en ligne via Stripe
                        h('div', { class: 'mj-regmgr-payment-option' }, [
                            h('div', { class: 'mj-regmgr-payment-option__header' }, [
                                h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('rect', { x: 1, y: 4, width: 22, height: 16, rx: 2, ry: 2 }),
                                    h('line', { x1: 1, y1: 10, x2: 23, y2: 10 }),
                                ]),
                                h('div', null, [
                                    h('strong', null, 'Paiement en ligne'),
                                    h('div', { class: 'mj-regmgr-payment-option__price' }, membershipPrice.toFixed(2) + ' €'),
                                ]),
                            ]),
                            h('p', { class: 'mj-regmgr-payment-option__desc' }, 
                                'Génère un lien Stripe avec QR code pour paiement par carte.'
                            ),
                            h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--primary mj-btn--block',
                                onClick: handlePayOnline,
                                disabled: processing,
                            }, [
                                processing ? h('span', null, [
                                    h('span', { class: 'mj-regmgr-loading__spinner', style: { width: '16px', height: '16px', marginRight: '8px', display: 'inline-block', verticalAlign: 'middle' } }),
                                    'Création du lien...'
                                ]) : 'Générer le lien de paiement',
                            ]),
                        ]),

                        h('div', { class: 'mj-regmgr-payment-separator' }, [
                            h('span', null, 'ou'),
                        ]),

                        // Option 2: Paiement en main propre
                        h('div', { class: 'mj-regmgr-payment-option mj-regmgr-payment-option--secondary' }, [
                            h('div', { class: 'mj-regmgr-payment-option__header' }, [
                                h('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                    h('line', { x1: 12, y1: 1, x2: 12, y2: 23 }),
                                    h('path', { d: 'M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6' }),
                                ]),
                                h('div', null, [
                                    h('strong', null, 'Payé en main propre'),
                                    h('div', { class: 'mj-regmgr-payment-option__price' }, membershipPriceManual.toFixed(2) + ' €'),
                                ]),
                            ]),
                            h('p', { class: 'mj-regmgr-payment-option__desc' }, 
                                'Espèces, chèque ou virement reçu directement.'
                            ),
                            h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--secondary mj-btn--block',
                                onClick: handleMarkPaid,
                                disabled: processing,
                            }, processing ? 'Enregistrement...' : 'Marquer comme payé'),
                        ]),
                    ]),
                ]),
            ]),
        ]);
    }

    // ============================================
    // EXPORT
    // ============================================

    global.MjRegMgrMembers = {
        MemberCard: MemberCard,
        MembersList: MembersList,
        MemberDetailPanel: MemberDetailPanel,
        MembershipPaymentModal: MembershipPaymentModal,
    };

})(window);
