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
    var TabsModule = global.MjRegMgrTabs;

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
    var useRef = hooks.useRef;

    var formatDate = Utils.formatDate;
    var classNames = Utils.classNames;
    var getString = Utils.getString;
    var buildWhatsAppLink = Utils.buildWhatsAppLink;

    var MemberAvatar = RegComps ? RegComps.MemberAvatar : function () { return null; };
    var TabsComponent = TabsModule && typeof TabsModule.Tabs === 'function'
        ? TabsModule.Tabs
        : null;

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

    function buildInitialEditData(member) {
        var base = {
            firstName: '',
            lastName: '',
            nickname: '',
            email: '',
            phone: '',
            birthDate: '',
            addressLine: '',
            postalCode: '',
            city: '',
            isVolunteer: false,
            isAutonomous: false,
            newsletterOptIn: false,
            smsOptIn: false,
            whatsappOptIn: false,
            photoUsageConsent: false,
            descriptionShort: '',
            descriptionLong: '',
        };

        if (!member) {
            return base;
        }

        base.firstName = member.firstName || '';
        base.lastName = member.lastName || '';
        base.nickname = member.nickname || '';
        base.email = member.email || '';
        base.phone = member.phone || '';
        base.birthDate = member.birthDate || '';
        base.addressLine = member.addressLine || '';
        base.postalCode = member.postalCode || '';
        base.city = member.city || '';
        if (typeof member.isVolunteer !== 'undefined') {
            base.isVolunteer = !!member.isVolunteer;
        }
        if (typeof member.isAutonomous !== 'undefined') {
            base.isAutonomous = !!member.isAutonomous;
        }
        if (typeof member.newsletterOptIn !== 'undefined') {
            base.newsletterOptIn = !!member.newsletterOptIn;
        }
        if (typeof member.smsOptIn !== 'undefined') {
            base.smsOptIn = !!member.smsOptIn;
        }
        if (typeof member.whatsappOptIn !== 'undefined') {
            base.whatsappOptIn = !!member.whatsappOptIn;
        }
        if (typeof member.photoUsageConsent !== 'undefined') {
            base.photoUsageConsent = !!member.photoUsageConsent;
        }
        base.descriptionShort = member.descriptionShort || '';
        base.descriptionLong = member.descriptionLong || '';

        return base;
    }

    function normalizeBadgeEntry(entry) {
        if (!entry || typeof entry !== 'object') {
            return null;
        }

        var badgeId = 0;
        if (typeof entry.id === 'number') {
            badgeId = entry.id;
        } else if (typeof entry.id === 'string' && entry.id !== '') {
            badgeId = parseInt(entry.id, 10);
            if (isNaN(badgeId)) {
                badgeId = 0;
            }
        }

        var imageId = 0;
        if (typeof entry.imageId === 'number') {
            imageId = entry.imageId;
        } else if (typeof entry.image_id === 'number') {
            imageId = entry.image_id;
        } else if (typeof entry.imageId === 'string' && entry.imageId !== '') {
            var parsedImageId = parseInt(entry.imageId, 10);
            imageId = isNaN(parsedImageId) ? 0 : parsedImageId;
        } else if (typeof entry.image_id === 'string' && entry.image_id !== '') {
            var parsedLegacyImageId = parseInt(entry.image_id, 10);
            imageId = isNaN(parsedLegacyImageId) ? 0 : parsedLegacyImageId;
        }

        var imageUrl = '';
        if (typeof entry.imageUrl === 'string') {
            imageUrl = entry.imageUrl;
        } else if (typeof entry.image_url === 'string') {
            imageUrl = entry.image_url;
        }

        var criteria = Array.isArray(entry.criteria) ? entry.criteria : [];
        var normalizedCriteria = criteria.map(function (criterion) {
            var criterionId = 0;
            if (typeof criterion.id === 'number') {
                criterionId = criterion.id;
            } else if (typeof criterion.id === 'string' && criterion.id !== '') {
                var parsed = parseInt(criterion.id, 10);
                criterionId = isNaN(parsed) ? 0 : parsed;
            }

            var canToggle = typeof criterion.canToggle === 'boolean' ? criterion.canToggle : criterionId > 0;
            var awarded = !!criterion.awarded;
            var status = typeof criterion.status === 'string' && criterion.status !== ''
                ? criterion.status
                : (awarded ? 'awarded' : 'pending');

            return {
                id: criterionId,
                label: typeof criterion.label === 'string' ? criterion.label : '',
                description: typeof criterion.description === 'string' ? criterion.description : '',
                awarded: awarded,
                status: status,
                canToggle: canToggle,
            };
        });

        var toggleableCount = normalizedCriteria.reduce(function (count, criterion) {
            return count + (criterion.canToggle ? 1 : 0);
        }, 0);

        var awardedCount = typeof entry.awardedCount === 'number'
            ? entry.awardedCount
            : normalizedCriteria.reduce(function (count, criterion) {
                if (criterion.canToggle && criterion.awarded) {
                    return count + 1;
                }
                return count;
            }, 0);

        var totalCriteria = typeof entry.totalCriteria === 'number'
            ? entry.totalCriteria
            : toggleableCount;

        if (totalCriteria < toggleableCount) {
            totalCriteria = toggleableCount;
        }

        var progress = 0;
        if (typeof entry.progressPercent === 'number' && !isNaN(entry.progressPercent)) {
            progress = entry.progressPercent;
        } else if (totalCriteria > 0) {
            progress = Math.round((awardedCount / Math.max(totalCriteria, 1)) * 100);
        } else if (typeof entry.status === 'string' && entry.status === 'awarded') {
            progress = 100;
        }

        if (progress < 0) {
            progress = 0;
        } else if (progress > 100) {
            progress = 100;
        }

        return {
            id: badgeId,
            label: typeof entry.label === 'string' ? entry.label : '',
            summary: typeof entry.summary === 'string' ? entry.summary : '',
            description: typeof entry.description === 'string' ? entry.description : '',
            icon: typeof entry.icon === 'string' ? entry.icon : '',
            imageId: imageId,
            imageUrl: imageUrl,
            status: typeof entry.status === 'string' ? entry.status : '',
            awardedAt: typeof entry.awardedAt === 'string' ? entry.awardedAt : (typeof entry.awarded_at === 'string' ? entry.awarded_at : ''),
            revokedAt: typeof entry.revokedAt === 'string' ? entry.revokedAt : (typeof entry.revoked_at === 'string' ? entry.revoked_at : ''),
            totalCriteria: totalCriteria,
            awardedCount: awardedCount,
            progress: progress,
            criteria: normalizedCriteria,
        };
    }

    function normalizeBadgeEntries(member) {
        if (!member || !Array.isArray(member.badges)) {
            return [];
        }

        var entries = [];
        for (var i = 0; i < member.badges.length; i++) {
            var normalized = normalizeBadgeEntry(member.badges[i]);
            if (normalized) {
                entries.push(normalized);
            }
        }
        return entries;
    }

    // ============================================
    // TROPHY NORMALIZATION FUNCTIONS
    // ============================================

    function normalizeTrophyEntry(entry) {
        if (!entry || typeof entry !== 'object') {
            return null;
        }

        var trophyId = 0;
        if (typeof entry.id === 'number') {
            trophyId = entry.id;
        } else if (typeof entry.id === 'string' && entry.id !== '') {
            trophyId = parseInt(entry.id, 10);
            if (isNaN(trophyId)) {
                trophyId = 0;
            }
        }

        var imageId = 0;
        if (typeof entry.imageId === 'number') {
            imageId = entry.imageId;
        } else if (typeof entry.image_id === 'number') {
            imageId = entry.image_id;
        } else if (typeof entry.imageId === 'string' && entry.imageId !== '') {
            var parsedImageId = parseInt(entry.imageId, 10);
            imageId = isNaN(parsedImageId) ? 0 : parsedImageId;
        }

        var imageUrl = '';
        if (typeof entry.imageUrl === 'string') {
            imageUrl = entry.imageUrl;
        } else if (typeof entry.image_url === 'string') {
            imageUrl = entry.image_url;
        }

        var xp = 0;
        if (typeof entry.xp === 'number') {
            xp = entry.xp;
        } else if (typeof entry.xp === 'string' && entry.xp !== '') {
            var parsedXp = parseInt(entry.xp, 10);
            xp = isNaN(parsedXp) ? 0 : parsedXp;
        }

        var autoMode = false;
        if (typeof entry.autoMode === 'boolean') {
            autoMode = entry.autoMode;
        } else if (typeof entry.auto_mode === 'boolean') {
            autoMode = entry.auto_mode;
        } else if (entry.autoMode === 1 || entry.auto_mode === 1) {
            autoMode = true;
        }

        var canToggle = false;
        if (typeof entry.canToggle === 'boolean') {
            canToggle = entry.canToggle;
        } else if (typeof entry.can_toggle === 'boolean') {
            canToggle = entry.can_toggle;
        } else {
            canToggle = !autoMode;
        }

        return {
            id: trophyId,
            title: typeof entry.title === 'string' ? entry.title : '',
            description: typeof entry.description === 'string' ? entry.description : '',
            xp: xp,
            imageId: imageId,
            imageUrl: imageUrl,
            autoMode: autoMode,
            awarded: !!entry.awarded,
            awardedAt: typeof entry.awardedAt === 'string' ? entry.awardedAt : (typeof entry.awarded_at === 'string' ? entry.awarded_at : ''),
            canToggle: canToggle,
        };
    }

    function normalizeTrophyEntries(member) {
        if (!member || !Array.isArray(member.trophies)) {
            return [];
        }

        var entries = [];
        for (var i = 0; i < member.trophies.length; i++) {
            var normalized = normalizeTrophyEntry(member.trophies[i]);
            if (normalized) {
                entries.push(normalized);
            }
        }
        return entries;
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
        var onUpdateIdea = typeof props.onUpdateIdea === 'function' ? props.onUpdateIdea : null;
        var onUpdatePhoto = typeof props.onUpdatePhoto === 'function' ? props.onUpdatePhoto : null;
        var onDeletePhoto = typeof props.onDeletePhoto === 'function' ? props.onDeletePhoto : null;
        var onUpdateAvatar = typeof props.onUpdateAvatar === 'function' ? props.onUpdateAvatar : null;
        var onRemoveAvatar = typeof props.onRemoveAvatar === 'function' ? props.onRemoveAvatar : null;
        var onCaptureAvatar = typeof props.onCaptureAvatar === 'function' ? props.onCaptureAvatar : null;
        var onDeleteMessage = typeof props.onDeleteMessage === 'function' ? props.onDeleteMessage : null;
        var onManageAccount = typeof props.onManageAccount === 'function' ? props.onManageAccount : null;
        var onDeleteMember = typeof props.onDeleteMember === 'function' ? props.onDeleteMember : null;
        var onDeleteRegistration = typeof props.onDeleteRegistration === 'function' ? props.onDeleteRegistration : null;
        var onOpenMember = typeof props.onOpenMember === 'function' ? props.onOpenMember : null;
        var onSyncBadgeCriteria = typeof props.onSyncBadgeCriteria === 'function' ? props.onSyncBadgeCriteria : null;
        var onAdjustXp = typeof props.onAdjustXp === 'function' ? props.onAdjustXp : null;
        var onToggleTrophy = typeof props.onToggleTrophy === 'function' ? props.onToggleTrophy : null;
        var pendingEditRequest = props.pendingEditRequest || null;
        var onPendingEditHandled = typeof props.onPendingEditHandled === 'function' ? props.onPendingEditHandled : null;

        if (!member) {
            var loadingLabel = getString(strings, 'memberLoadingDetails', 'Chargement des informations...');
            var selectPromptLabel = getString(strings, 'memberSelectPrompt', 'Sélectionnez un membre pour afficher les détails.');

            return h('div', { class: 'mj-regmgr-member-detail mj-regmgr-member-detail--placeholder' }, [
                loading
                    ? h('div', { class: 'mj-regmgr-member-detail__placeholder mj-regmgr-member-detail__placeholder--loading' }, [
                        h('div', { class: 'mj-regmgr-spinner' }),
                        h('p', null, loadingLabel),
                    ])
                    : h('div', { class: 'mj-regmgr-member-detail__placeholder mj-regmgr-member-detail__placeholder--empty' }, [
                        h('p', null, selectPromptLabel),
                    ]),
            ]);
        }

        var memberId = member && member.id ? member.id : null;

        var _useStateActiveTab = useState('information');
        var activeTab = _useStateActiveTab[0];
        var setActiveTab = _useStateActiveTab[1];

        var _useStateEditMode = useState(false);
        var editMode = _useStateEditMode[0];
        var setEditMode = _useStateEditMode[1];

        var _useStateEditData = useState(buildInitialEditData(member));
        var editData = _useStateEditData[0];
        var setEditData = _useStateEditData[1];

        var _useStateNewNote = useState('');
        var newNote = _useStateNewNote[0];
        var setNewNote = _useStateNewNote[1];

        var _useStateSavingNote = useState(false);
        var savingNote = _useStateSavingNote[0];
        var setSavingNote = _useStateSavingNote[1];

        var _useStateEditingNoteId = useState(null);
        var editingNoteId = _useStateEditingNoteId[0];
        var setEditingNoteId = _useStateEditingNoteId[1];

        var _useStateEditingNoteContent = useState('');
        var editingNoteContent = _useStateEditingNoteContent[0];
        var setEditingNoteContent = _useStateEditingNoteContent[1];

        var _useStateEditingNoteSaving = useState(false);
        var editingNoteSaving = _useStateEditingNoteSaving[0];
        var setEditingNoteSaving = _useStateEditingNoteSaving[1];

        var _useStateEditingIdeaId = useState(null);
        var editingIdeaId = _useStateEditingIdeaId[0];
        var setEditingIdeaId = _useStateEditingIdeaId[1];

        var _useStateIdeaDraft = useState({ title: '', content: '', status: 'published' });
        var ideaDraft = _useStateIdeaDraft[0];
        var setIdeaDraft = _useStateIdeaDraft[1];

        var _useStateIdeaSaving = useState(false);
        var ideaSaving = _useStateIdeaSaving[0];
        var setIdeaSaving = _useStateIdeaSaving[1];

        var _useStateEditingPhotoId = useState(null);
        var editingPhotoId = _useStateEditingPhotoId[0];
        var setEditingPhotoId = _useStateEditingPhotoId[1];

        var _useStatePhotoDraft = useState({ caption: '', status: 'approved' });
        var photoDraft = _useStatePhotoDraft[0];
        var setPhotoDraft = _useStatePhotoDraft[1];

        var _useStatePhotoSaving = useState(false);
        var photoSaving = _useStatePhotoSaving[0];
        var setPhotoSaving = _useStatePhotoSaving[1];

        var _useStatePhotoDeletingId = useState(null);
        var photoDeletingId = _useStatePhotoDeletingId[0];
        var setPhotoDeletingId = _useStatePhotoDeletingId[1];

        var _useStateMessageDeletingId = useState(null);
        var messageDeletingId = _useStateMessageDeletingId[0];
        var setMessageDeletingId = _useStateMessageDeletingId[1];

        var _useStateAvatarSaving = useState(false);
        var avatarSaving = _useStateAvatarSaving[0];
        var setAvatarSaving = _useStateAvatarSaving[1];

        var _useStateDeletingMember = useState(false);
        var deletingMember = _useStateDeletingMember[0];
        var setDeletingMember = _useStateDeletingMember[1];

        var _useStatePaymentProcessing = useState(false);
        var paymentProcessing = _useStatePaymentProcessing[0];
        var setPaymentProcessing = _useStatePaymentProcessing[1];

        var _useStateShowPaymentModal = useState(false);
        var showPaymentModal = _useStateShowPaymentModal[0];
        var setShowPaymentModal = _useStateShowPaymentModal[1];

        var _useStateBadgeData = useState(normalizeBadgeEntries(member));
        var badgeData = _useStateBadgeData[0];
        var setBadgeData = _useStateBadgeData[1];

        var _useStateBadgeSaving = useState({});
        var badgeSaving = _useStateBadgeSaving[0];
        var setBadgeSaving = _useStateBadgeSaving[1];

        var _useStateTrophyData = useState(normalizeTrophyEntries(member));
        var trophyData = _useStateTrophyData[0];
        var setTrophyData = _useStateTrophyData[1];

        var _useStateTrophySaving = useState({});
        var trophySaving = _useStateTrophySaving[0];
        var setTrophySaving = _useStateTrophySaving[1];

        var avatarFrameRef = useRef(null);

        useEffect(function () {
            setEditData(buildInitialEditData(member));
            setEditMode(false);
            setActiveTab('information');
            setEditingNoteId(null);
            setEditingNoteContent('');
            setEditingNoteSaving(false);
            setIdeaDraft({ title: '', content: '', status: 'published' });
            setEditingIdeaId(null);
            setIdeaSaving(false);
            setEditingPhotoId(null);
            setPhotoDraft({ caption: '', status: 'approved' });
            setPhotoSaving(false);
            setPhotoDeletingId(null);
            setMessageDeletingId(null);
            setPaymentProcessing(false);
            setShowPaymentModal(false);
        }, [memberId]);

        useEffect(function () {
            setBadgeData(normalizeBadgeEntries(member));
            setBadgeSaving({});
        }, [member]);

        useEffect(function () {
            setTrophyData(normalizeTrophyEntries(member));
            setTrophySaving({});
        }, [member]);

        useEffect(function () {
            if (!pendingEditRequest || !memberId) {
                return;
            }

            var targetTab = 'information';
            if (typeof pendingEditRequest === 'string') {
                targetTab = pendingEditRequest;
            } else if (pendingEditRequest && (pendingEditRequest.tab || pendingEditRequest.type)) {
                targetTab = pendingEditRequest.tab || pendingEditRequest.type;
            }

            if (targetTab) {
                setActiveTab(targetTab);
            }
            if (targetTab === 'information') {
                setEditMode(true);
            }
            if (onPendingEditHandled) {
                onPendingEditHandled();
            }
        }, [pendingEditRequest, memberId, onPendingEditHandled]);

        var roleLabels = {
            jeune: getString(strings, 'roleJeune', 'Jeune'),
            animateur: getString(strings, 'roleAnimateur', 'Animateur'),
            tuteur: getString(strings, 'roleTuteur', 'Tuteur'),
            benevole: getString(strings, 'roleBenevole', 'Bénévole'),
            coordinateur: getString(strings, 'roleCoordinateur', 'Coordinateur'),
            parent: getString(strings, 'roleParent', 'Parent'),
            admin: getString(strings, 'roleAdmin', 'Administrateur'),
        };

        var membershipLabels = {
            paid: getString(strings, 'membershipStatusPaid', 'Cotisation OK'),
            expired: getString(strings, 'membershipStatusExpired', 'Cotisation expirée'),
            unpaid: getString(strings, 'membershipStatusUnpaid', 'Cotisation due'),
            not_required: getString(strings, 'membershipStatusNotRequired', 'Non requise'),
        };

        var statusLabels = {
            active: getString(strings, 'memberStatusActive', 'Actif'),
            inactive: getString(strings, 'memberStatusInactive', 'Inactif'),
            pending: getString(strings, 'memberStatusPending', 'En attente'),
            archived: getString(strings, 'memberStatusArchived', 'Archivé'),
        };

        var photoStatusLabels = {
            approved: getString(strings, 'photoStatusApproved', 'Approuvée'),
            pending: getString(strings, 'photoStatusPending', 'En attente'),
            rejected: getString(strings, 'photoStatusRejected', 'Refusée'),
        };
        var photoStatusClasses = {
            approved: 'mj-regmgr-badge--success',
            pending: 'mj-regmgr-badge--warning',
            rejected: 'mj-regmgr-badge--danger',
        };

        var ideaStatusLabels = {
            published: getString(strings, 'ideaStatusPublished', 'Publiée'),
            draft: getString(strings, 'ideaStatusDraft', 'Brouillon'),
            archived: getString(strings, 'ideaStatusArchived', 'Archivée'),
        };
        var ideaStatusClasses = {
            published: 'mj-regmgr-badge--success',
            draft: 'mj-regmgr-badge--warning',
            archived: 'mj-regmgr-badge--secondary',
        };

        var communicationEnabledLabel = getString(strings, 'communicationEnabled', 'Activé');
        var communicationDisabledLabel = getString(strings, 'communicationDisabled', 'Désactivé');

        var avatarMediaUnavailableLabel = getString(strings, 'avatarMediaUnavailable', "La médiathèque WordPress est indisponible.");
        var avatarLibraryTitle = getString(strings, 'avatarLibraryTitle', 'Bibliothèque de médias');
        var avatarLibraryButton = getString(strings, 'avatarLibraryButton', 'Choisir');
        var captureAvatarLabel = getString(strings, 'captureAvatar', 'Capturer');
        var avatarUploadingLabel = getString(strings, 'avatarUploading', 'Téléversement...');
        var changeAvatarLabel = getString(strings, 'changeAvatar', 'Changer');

        var memberRoleRaw = member && typeof member.role === 'string' ? member.role : '';
        var memberRole = memberRoleRaw ? memberRoleRaw.toLowerCase() : '';
        var isGuardianRole = memberRole === 'tuteur';
        var canManageChildren = !!(config && config.canManageChildren && isGuardianRole);
        var allowCreateChild = !!(config && config.allowCreateChild && isGuardianRole);
        var allowAttachChild = !!(config && config.allowAttachChild && isGuardianRole);
        var hasChildren = !!(member && Array.isArray(member.children) && member.children.length > 0);
        var showChildSection = isGuardianRole && (hasChildren || canManageChildren);

        var handleCreateChild = useCallback(function () {
            if (!canManageChildren || !allowCreateChild) {
                return;
            }
            if (!config || typeof config.onCreateChild !== 'function' || !member) {
                return;
            }
            config.onCreateChild(member);
        }, [canManageChildren, allowCreateChild, config, member]);

        var handleAttachChild = useCallback(function () {
            if (!canManageChildren || !allowAttachChild) {
                return;
            }
            if (!config || typeof config.onAttachChild !== 'function' || !member) {
                return;
            }
            config.onAttachChild(member);
        }, [canManageChildren, allowAttachChild, config, member]);
        var removeAvatarLabel = getString(strings, 'removeAvatar', 'Supprimer');
        var removeAvatarConfirmLabel = getString(strings, 'removeAvatarConfirm', 'Supprimer l’avatar personnalisé ?');

        var canChangeAvatar = !!(onUpdateAvatar || onRemoveAvatar || onCaptureAvatar);
        if (config && typeof config.canManageAvatars !== 'undefined') {
            canChangeAvatar = canChangeAvatar && !!config.canManageAvatars;
        }

        var canDeleteMember = !!(onDeleteMember && config && config.canDeleteMember);
        var allowDeleteRegistration = !!(config && config.canDeleteRegistration);

        var memberPhotoId = null;
        if (member.photoId) {
            memberPhotoId = member.photoId;
        } else if (member.photo && member.photo.id) {
            memberPhotoId = member.photo.id;
        }

        var memberHasCustomAvatar = false;
        if (typeof member.hasCustomAvatar !== 'undefined') {
            memberHasCustomAvatar = !!member.hasCustomAvatar;
        } else if (memberPhotoId) {
            memberHasCustomAvatar = memberPhotoId !== 0;
        }

        var guardian = null;
        if (member.guardian) {
            guardian = member.guardian;
        } else if (member.guardianData) {
            guardian = member.guardianData;
        }

        var guardianDisplayName = '';
        if (guardian) {
            guardianDisplayName = ((guardian.firstName || '') + ' ' + (guardian.lastName || '')).trim();
            if (!guardianDisplayName && guardian.displayName) {
                guardianDisplayName = guardian.displayName;
            }
        } else if (member.guardianName) {
            guardianDisplayName = member.guardianName;
        }
        if (!guardianDisplayName) {
            var guardianFirstFromMember = member.guardianFirstName || member.guardian_first_name || '';
            var guardianLastFromMember = member.guardianLastName || member.guardian_last_name || '';
            var guardianNameFromMember = ((guardianFirstFromMember || '') + ' ' + (guardianLastFromMember || '')).trim();
            if (guardianNameFromMember) {
                guardianDisplayName = guardianNameFromMember;
            }
        }

        var guardianId = null;
        if (guardian && typeof guardian.id !== 'undefined') {
            guardianId = guardian.id;
        }
        if (guardianId === null && typeof member.guardianId !== 'undefined' && member.guardianId !== null) {
            guardianId = member.guardianId;
        }
        if ((guardianId === null || typeof guardianId === 'undefined') && typeof member.guardian_id !== 'undefined' && member.guardian_id !== null) {
            guardianId = member.guardian_id;
        }
        if (guardianId !== null) {
            guardianId = parseInt(guardianId, 10);
            if (isNaN(guardianId)) {
                guardianId = null;
            }
        }

        var guardianEditUrl = '';
        if (guardian && guardian.editUrl) {
            guardianEditUrl = guardian.editUrl;
        } else if (member.guardianEditUrl) {
            guardianEditUrl = member.guardianEditUrl;
        } else if (config && config.guardianEditBaseUrl && guardianId) {
            guardianEditUrl = config.guardianEditBaseUrl.replace('%ID%', guardianId);
        }

        var canEditGuardianInline = !!(config && config.canEditGuardianInline);
        if (typeof member.canEditGuardianInline !== 'undefined') {
            canEditGuardianInline = !!member.canEditGuardianInline;
        }

        var guardianReference = null;
        if (guardian && guardian.id) {
            guardianReference = guardian;
        } else if (guardianId) {
            var nameParts = guardianDisplayName ? guardianDisplayName.split(' ') : [];
            var fallbackFirst = guardian && guardian.firstName ? guardian.firstName : '';
            if (!fallbackFirst && member.guardianFirstName) {
                fallbackFirst = member.guardianFirstName;
            }
            if (!fallbackFirst && member.guardian_first_name) {
                fallbackFirst = member.guardian_first_name;
            }
            if (!fallbackFirst && nameParts.length > 0) {
                fallbackFirst = nameParts[0];
            }
            var fallbackLast = guardian && guardian.lastName ? guardian.lastName : '';
            if (!fallbackLast && member.guardianLastName) {
                fallbackLast = member.guardianLastName;
            }
            if (!fallbackLast && member.guardian_last_name) {
                fallbackLast = member.guardian_last_name;
            }
            if (!fallbackLast && nameParts.length > 1) {
                fallbackLast = nameParts.slice(1).join(' ');
            }
            var derivedDisplayName = guardianDisplayName;
            if (!derivedDisplayName) {
                if (guardian && guardian.displayName) {
                    derivedDisplayName = guardian.displayName;
                } else {
                    var combinedFallback = ((fallbackFirst || '') + ' ' + (fallbackLast || '')).trim();
                    if (combinedFallback) {
                        derivedDisplayName = combinedFallback;
                    }
                }
            }
            guardianReference = {
                id: guardianId,
                firstName: fallbackFirst,
                lastName: fallbackLast,
                displayName: derivedDisplayName,
                role: guardian && guardian.role ? guardian.role : 'tuteur',
                roleLabel: guardian && guardian.roleLabel ? guardian.roleLabel : '',
                avatarUrl: guardian && guardian.avatarUrl ? guardian.avatarUrl : '',
                email: guardian && guardian.email ? guardian.email : '',
                phone: guardian && guardian.phone ? guardian.phone : '',
            };
        }
        if (!guardianDisplayName && guardianReference) {
            if (guardianReference.displayName) {
                guardianDisplayName = guardianReference.displayName;
            } else {
                guardianDisplayName = ((guardianReference.firstName || '') + ' ' + (guardianReference.lastName || '')).trim();
            }
        }

        var handleGuardianEditClick = function () {
            if (canEditGuardianInline && typeof config.onEditGuardian === 'function') {
                config.onEditGuardian(member);
                return;
            }
            if (guardianEditUrl) {
                if (typeof window !== 'undefined') {
                    window.open(guardianEditUrl, '_blank', 'noopener');
                }
            }
        };

        var guardianActions = [];
        if (onOpenMember && guardianReference) {
            guardianActions.push(h('button', {
                type: 'button',
                class: 'mj-regmgr-guardian__edit-link mj-regmgr-guardian__view-link',
                onClick: function () {
                    onOpenMember(guardianReference);
                },
                title: getString(strings, 'viewMemberProfile', 'Ouvrir la fiche membre'),
            }, [
                h('svg', {
                    width: 14,
                    height: 14,
                    viewBox: '0 0 24 24',
                    fill: 'none',
                    stroke: 'currentColor',
                    'stroke-width': 2,
                }, [
                    h('path', { d: 'M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6' }),
                    h('polyline', { points: '15 3 21 3 21 9' }),
                    h('line', { x1: 10, y1: 14, x2: 21, y2: 3 }),
                ]),
                h('span', null, getString(strings, 'openMember', 'Fiche')),
            ]));
        }

        if (canEditGuardianInline) {
            guardianActions.push(h('button', {
                type: 'button',
                class: 'mj-regmgr-guardian__edit-link',
                onClick: handleGuardianEditClick,
            }, [
                h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                    h('path', { d: 'M12 20h9' }),
                    h('path', { d: 'M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4z' }),
                ]),
                h('span', null, 'Modifier'),
            ]));
        } else if (guardianEditUrl) {
            guardianActions.push(h('a', {
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
            ]));
        }

        var handleFieldChange = function (field) {
            return function (event) {
                var value = event && event.target ? event.target.value : '';
                setEditData(function (prev) {
                    var next = Object.assign({}, prev);
                    next[field] = value;
                    return next;
                });
            };
        };

        var handleBooleanChange = function (field) {
            return function (event) {
                var checked = !!(event && event.target && event.target.checked);
                setEditData(function (prev) {
                    var next = Object.assign({}, prev);
                    next[field] = checked;
                    return next;
                });
            };
        };

        var handleCaptureClick = function () {
            if (!onCaptureAvatar || !memberId || avatarSaving) {
                return;
            }
            setAvatarSaving(true);
            Promise.resolve(onCaptureAvatar(memberId))
                .catch(function (error) {
                    if (error && error.message) {
                        console.error('[MjRegMgr] Avatar capture failed:', error.message);
                    }
                })
                .finally(function () {
                    setAvatarSaving(false);
                });
        };

        var handleAvatarPick = function () {
            if (!canChangeAvatar || !member || !member.id || !onUpdateAvatar || avatarSaving) {
                return;
            }

            if (typeof window === 'undefined' || !window.wp || !window.wp.media || typeof window.wp.media !== 'function') {
                if (typeof window !== 'undefined' && typeof window.alert === 'function') {
                    window.alert(avatarMediaUnavailableLabel);
                }
                return;
            }

            var frame = avatarFrameRef.current;

            if (!frame) {
                frame = window.wp.media({
                    title: avatarLibraryTitle,
                    button: { text: avatarLibraryButton },
                    library: { type: 'image' },
                    multiple: false,
                });
                avatarFrameRef.current = frame;
            }

            if (frame && typeof frame.off === 'function') {
                frame.off('select');
                frame.off('open');
            }

            if (frame) {
                frame.on('open', function () {
                    var state = typeof frame.state === 'function' ? frame.state() : null;
                    if (!state || typeof state.get !== 'function') {
                        return;
                    }
                    var selection = state.get('selection');
                    if (!selection || typeof selection.reset !== 'function') {
                        return;
                    }
                    if (memberHasCustomAvatar && memberPhotoId) {
                        var attachment = window.wp.media.attachment(memberPhotoId);
                        if (attachment) {
                            attachment.fetch();
                            selection.reset([attachment]);
                            return;
                        }
                    }
                    selection.reset([]);
                });

                frame.on('select', function () {
                    var state = typeof frame.state === 'function' ? frame.state() : null;
                    if (!state || typeof state.get !== 'function') {
                        return;
                    }
                    var selection = state.get('selection');
                    if (!selection || typeof selection.first !== 'function') {
                        return;
                    }
                    var attachmentModel = selection.first();
                    if (!attachmentModel) {
                        return;
                    }
                    var attachmentData = attachmentModel.toJSON ? attachmentModel.toJSON() : attachmentModel;
                    if (!attachmentData || !attachmentData.id) {
                        return;
                    }
                    setAvatarSaving(true);
                    Promise.resolve(onUpdateAvatar(member.id, attachmentData.id))
                        .catch(function (error) {
                            if (error && error.message) {
                                console.error('[MjRegMgr] Avatar update failed:', error.message);
                            }
                        })
                        .finally(function () {
                            setAvatarSaving(false);
                        });
                });

                frame.open();
            }
        };

        var handleAvatarRemove = function () {
            if (!canChangeAvatar || !member || !member.id || !onRemoveAvatar || !memberHasCustomAvatar || avatarSaving) {
                return;
            }

            if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
                if (!window.confirm(removeAvatarConfirmLabel)) {
                    return;
                }
            }

            setAvatarSaving(true);
            Promise.resolve(onRemoveAvatar(member.id))
                .catch(function (error) {
                    if (error && error.message) {
                        console.error('[MjRegMgr] Avatar remove failed:', error.message);
                    }
                })
                .finally(function () {
                    setAvatarSaving(false);
                });
        };

        var renderAvatarActions = function (extraClass) {
            if (!canChangeAvatar) {
                return null;
            }

            var extraClasses = {};
            if (extraClass) {
                extraClasses[extraClass] = true;
            }

            return h('div', {
                class: classNames('mj-regmgr-member-detail__avatar-actions', extraClasses),
            }, [
                onCaptureAvatar && h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--primary mj-btn--small',
                    onClick: handleCaptureClick,
                    disabled: avatarSaving,
                    title: avatarSaving ? avatarUploadingLabel : captureAvatarLabel,
                    'aria-label': avatarSaving ? avatarUploadingLabel : captureAvatarLabel,
                }, avatarSaving ? avatarUploadingLabel : captureAvatarLabel),
                onUpdateAvatar && h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--secondary mj-btn--small',
                    onClick: handleAvatarPick,
                    disabled: avatarSaving,
                    title: avatarSaving ? avatarUploadingLabel : changeAvatarLabel,
                    'aria-label': avatarSaving ? avatarUploadingLabel : changeAvatarLabel,
                }, avatarSaving ? avatarUploadingLabel : changeAvatarLabel),
                memberHasCustomAvatar && onRemoveAvatar && h('button', {
                    type: 'button',
                    class: 'mj-btn mj-btn--ghost mj-btn--small',
                    onClick: handleAvatarRemove,
                    disabled: avatarSaving,
                    title: avatarSaving ? avatarUploadingLabel : removeAvatarLabel,
                    'aria-label': avatarSaving ? avatarUploadingLabel : removeAvatarLabel,
                }, avatarSaving ? avatarUploadingLabel : removeAvatarLabel),
            ]);
        };

        var handleDeleteMember = function () {
            if (!canDeleteMember || !member || !onDeleteMember || deletingMember) {
                return;
            }

            var confirmMessage = getString(strings, 'deleteMemberConfirm', 'Voulez-vous vraiment supprimer ce membre ? Cette action est irréversible.');
            if (typeof window !== 'undefined' && !window.confirm(confirmMessage)) {
                return;
            }

            setDeletingMember(true);
            Promise.resolve(onDeleteMember(member.id))
                .catch(function () {
                    // L'erreur est déjà gérée côté parent via showError
                })
                .finally(function () {
                    setDeletingMember(false);
                });
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

        var handleToggleBadgeCriterion = function (badgeId, criterionId, checked) {
            if (!onSyncBadgeCriteria || !memberId) {
                return;
            }

            var numericBadgeId = typeof badgeId === 'number' ? badgeId : parseInt(badgeId, 10);
            var numericCriterionId = typeof criterionId === 'number' ? criterionId : parseInt(criterionId, 10);
            var numericMemberId = typeof memberId === 'number' ? memberId : parseInt(memberId, 10);

            if (!numericMemberId || numericMemberId <= 0 || !numericBadgeId || numericBadgeId <= 0 || !numericCriterionId || numericCriterionId <= 0) {
                return;
            }

            if (badgeSaving[numericBadgeId]) {
                return;
            }

            var targetBadge = null;
            for (var i = 0; i < badgeData.length; i++) {
                if (badgeData[i].id === numericBadgeId) {
                    targetBadge = badgeData[i];
                    break;
                }
            }

            if (!targetBadge) {
                return;
            }

            var targetCriterion = null;
            for (var j = 0; j < targetBadge.criteria.length; j++) {
                if (targetBadge.criteria[j].id === numericCriterionId) {
                    targetCriterion = targetBadge.criteria[j];
                    break;
                }
            }

            if (!targetCriterion || !targetCriterion.canToggle) {
                return;
            }

            if (!!targetCriterion.awarded === !!checked) {
                return;
            }

            var previousBadges = badgeData.slice();
            var nextSelectedIds = [];

            var nextBadges = badgeData.map(function (badge) {
                if (badge.id !== numericBadgeId) {
                    return badge;
                }

                var updatedCriteria = badge.criteria.map(function (criterion) {
                    if (criterion.id !== numericCriterionId) {
                        return criterion;
                    }
                    return Object.assign({}, criterion, {
                        awarded: checked,
                        status: checked ? 'awarded' : 'pending',
                    });
                });

                var toggleableTotal = 0;
                var awardedCount = 0;
                var selectedIds = [];

                updatedCriteria.forEach(function (criterion) {
                    if (criterion.canToggle) {
                        toggleableTotal++;
                        if (criterion.awarded && criterion.id > 0) {
                            awardedCount++;
                            selectedIds.push(criterion.id);
                        }
                    }
                });

                var totalCriteria = typeof badge.totalCriteria === 'number'
                    ? Math.max(badge.totalCriteria, toggleableTotal)
                    : toggleableTotal;

                var progress = totalCriteria > 0
                    ? Math.round((awardedCount / Math.max(totalCriteria, 1)) * 100)
                    : (badge.status === 'awarded' ? 100 : 0);

                if (progress < 0) {
                    progress = 0;
                } else if (progress > 100) {
                    progress = 100;
                }

                var nextStatus = badge.status;
                if (awardedCount === 0) {
                    nextStatus = 'revoked';
                } else if (totalCriteria > 0 && awardedCount >= totalCriteria) {
                    nextStatus = 'awarded';
                } else if (nextStatus === 'revoked') {
                    nextStatus = '';
                }

                nextSelectedIds = selectedIds;

                return Object.assign({}, badge, {
                    criteria: updatedCriteria,
                    awardedCount: awardedCount,
                    totalCriteria: totalCriteria,
                    progress: progress,
                    status: nextStatus,
                });
            });

            setBadgeData(nextBadges);

            setBadgeSaving(function (prev) {
                var next = Object.assign({}, prev);
                next[numericBadgeId] = true;
                return next;
            });

            Promise.resolve(onSyncBadgeCriteria(numericMemberId, numericBadgeId, nextSelectedIds))
                .then(function (result) {
                    if (result && result.badge) {
                        var normalized = normalizeBadgeEntry(result.badge);
                        if (normalized) {
                            setBadgeData(function (currentBadges) {
                                return currentBadges.map(function (badge) {
                                    return badge.id === numericBadgeId ? normalized : badge;
                                });
                            });
                        }
                    }
                })
                .catch(function () {
                    setBadgeData(previousBadges);
                })
                .finally(function () {
                    setBadgeSaving(function (prev) {
                        var next = Object.assign({}, prev);
                        delete next[numericBadgeId];
                        return next;
                    });
                });
        };

        var handleToggleTrophy = function (trophyId, checked) {
            if (!onToggleTrophy || !memberId) {
                return;
            }

            var numericTrophyId = typeof trophyId === 'number' ? trophyId : parseInt(trophyId, 10);
            var numericMemberId = typeof memberId === 'number' ? memberId : parseInt(memberId, 10);

            if (!numericMemberId || numericMemberId <= 0 || !numericTrophyId || numericTrophyId <= 0) {
                return;
            }

            if (trophySaving[numericTrophyId]) {
                return;
            }

            var targetTrophy = null;
            for (var i = 0; i < trophyData.length; i++) {
                if (trophyData[i].id === numericTrophyId) {
                    targetTrophy = trophyData[i];
                    break;
                }
            }

            if (!targetTrophy || !targetTrophy.canToggle) {
                return;
            }

            if (!!targetTrophy.awarded === !!checked) {
                return;
            }

            var previousTrophies = trophyData.slice();

            // Optimistic update
            var nextTrophies = trophyData.map(function (trophy) {
                if (trophy.id !== numericTrophyId) {
                    return trophy;
                }
                return Object.assign({}, trophy, {
                    awarded: checked,
                    awardedAt: checked ? new Date().toISOString() : '',
                });
            });

            setTrophyData(nextTrophies);

            setTrophySaving(function (prev) {
                var next = Object.assign({}, prev);
                next[numericTrophyId] = true;
                return next;
            });

            Promise.resolve(onToggleTrophy(numericMemberId, numericTrophyId, checked))
                .then(function (result) {
                    if (result && result.trophy) {
                        var normalized = normalizeTrophyEntry(result.trophy);
                        if (normalized) {
                            setTrophyData(function (currentTrophies) {
                                return currentTrophies.map(function (trophy) {
                                    return trophy.id === numericTrophyId ? normalized : trophy;
                                });
                            });
                        }
                    }
                })
                .catch(function () {
                    setTrophyData(previousTrophies);
                })
                .finally(function () {
                    setTrophySaving(function (prev) {
                        var next = Object.assign({}, prev);
                        delete next[numericTrophyId];
                        return next;
                    });
                });
        };

        var memberId = member && member.id ? member.id : null;
        var hasLinkedAccount = member && member.userId ? true : false;

        var handleOpenAccountModal = useCallback(function () {
            if (!onManageAccount || !member) {
                return;
            }
            onManageAccount(member);
        }, [onManageAccount, member]);

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

        var tabInformationLabel = getString(strings, 'tabMemberInformation', 'Informations');
        var tabMembershipLabel = getString(strings, 'tabMemberMembership', 'Statut');
        var tabBadgesLabel = getString(strings, 'tabMemberBadges', 'Badges');
        var tabPhotosLabel = getString(strings, 'tabMemberPhotos', 'Photos');
        var tabIdeasLabel = getString(strings, 'tabMemberIdeas', 'Idées');
        var tabMessagesLabel = getString(strings, 'tabMemberMessages', 'Messages');
        var tabNotesLabel = getString(strings, 'tabMemberNotes', 'Notes');
        var tabHistoryLabel = getString(strings, 'tabMemberHistory', 'Historique');

        var photosCount = memberPhotos.length;
        var ideasCount = memberIdeas.length;
        var messagesCount = memberMessages.length;
        var notesCount = notes.length;
        var registrationsTitle = getString(strings, 'memberRegistrationsHistoryTitle', 'Inscriptions');
        var registrationsEmptyLabel = getString(strings, 'memberNoRegistrations', 'Aucune inscription enregistrée.');
        var sessionsLabel = getString(strings, 'sessions', 'Séances');
        var allSessionsLabel = getString(strings, 'allSessions', 'Toutes les séances');
        var noSessionsLabel = getString(strings, 'noSessionsAssigned', 'Aucune séance assignée');
        var badgesTitle = getString(strings, 'memberBadgesTitle', 'Badges & progression');
        var badgeEmptyLabel = getString(strings, 'memberNoBadges', 'Aucun badge disponible pour ce membre.');
        var badgeNoCriteriaLabel = getString(strings, 'memberBadgeNoCriteria', 'Ce badge ne possède pas encore de critères configurables.');
        var badgeReadonlyHint = getString(strings, 'memberBadgeReadonlyCriterion', 'Critère informatif non modifiable.');
        var badgeSavingLabel = getString(strings, 'memberBadgeSaving', 'Enregistrement...');
        var badgeCompletedLabel = getString(strings, 'memberBadgeCompleted', 'Badge obtenu');
        var badgeNotStartedLabel = getString(strings, 'memberBadgeNotStarted', 'Non commencé');
        var badgeStateLabels = {
            complete: getString(strings, 'memberBadgeStateComplete', 'Obtenu'),
            in_progress: getString(strings, 'memberBadgeStateInProgress', 'En cours'),
            locked: getString(strings, 'memberBadgeStateLocked', 'À faire'),
            revoked: getString(strings, 'memberBadgeStateRevoked', 'Révoqué'),
        };
        var badgesCompletedCount = badgeData.reduce(function (count, badge) {
            if (!badge) {
                return count;
            }
            if (badge.totalCriteria > 0) {
                return count + (badge.awardedCount >= badge.totalCriteria ? 1 : 0);
            }
            if (badge.status === 'awarded') {
                return count + 1;
            }
            return count;
        }, 0);

        var tabIcons = {
            information: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><circle cx="12" cy="8" r="1"></circle></svg>',
            membership: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line><line x1="7" y1="15" x2="9" y2="15"></line></svg>',
            badges: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"></circle><polyline points="9 17 12 15 15 17"></polyline><line x1="12" y1="15" x2="12" y2="22"></line><polyline points="8 22 12 20 16 22"></polyline></svg>',
            photos: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h3l2-2h6l2 2h3a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2z"></path><circle cx="12" cy="12" r="3"></circle></svg>',
            ideas: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M12 2a7 7 0 0 0-4 12.9V17a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-2.1A7 7 0 0 0 12 2z"></path></svg>',
            messages: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-9 8.5A8.38 8.38 0 0 1 3 11.5 8.38 8.38 0 0 1 12 3a8.38 8.38 0 0 1 9 8.5z"></path><polyline points="8 11 12 15 16 11"></polyline></svg>',
            notes: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"></path><polyline points="15 2 15 7 20 7"></polyline><line x1="9" y1="12" x2="15" y2="12"></line><line x1="9" y1="16" x2="13" y2="16"></line></svg>',
            history: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>',
        };

        var memberTabs = [
            { key: 'information', label: tabInformationLabel, icon: tabIcons.information },
            { key: 'membership', label: tabMembershipLabel, icon: tabIcons.membership },
            { key: 'badges', label: tabBadgesLabel, badge: badgesCompletedCount > 0 ? badgesCompletedCount : undefined, icon: tabIcons.badges },
            { key: 'photos', label: tabPhotosLabel, badge: photosCount > 0 ? photosCount : undefined, icon: tabIcons.photos },
            { key: 'ideas', label: tabIdeasLabel, badge: ideasCount > 0 ? ideasCount : undefined, icon: tabIcons.ideas },
            { key: 'messages', label: tabMessagesLabel, badge: messagesCount > 0 ? messagesCount : undefined, icon: tabIcons.messages },
            { key: 'notes', label: tabNotesLabel, badge: notesCount > 0 ? notesCount : undefined, icon: tabIcons.notes },
            { key: 'history', label: tabHistoryLabel, badge: registrations.length > 0 ? registrations.length : undefined, icon: tabIcons.history },
        ];

        var newsletterLabel = getString(strings, 'chipNewsletter', 'Newsletter');
        var smsLabel = getString(strings, 'chipSMS', 'SMS');
        var whatsappLabel = getString(strings, 'chipWhatsapp', 'WhatsApp');
        var photoConsentLabel = getString(strings, 'chipPhotoConsent', 'Consentement photo');
        var accountButtonLinked = getString(strings, 'memberAccountLinked', 'Modifier le compte WordPress');
        var accountButtonUnlinked = getString(strings, 'memberAccountUnlinked', 'Lier un compte WordPress');
        var accountStatusLinked = getString(strings, 'memberAccountStatusLinked', 'Un compte WordPress est lié à ce membre.');
        var accountStatusUnlinked = getString(strings, 'memberAccountStatusUnlinked', 'Aucun compte WordPress n\'est encore lié.');

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

        var tabsNav = null;
        if (memberTabs.length > 0) {
            if (TabsComponent) {
                tabsNav = h(TabsComponent, {
                    tabs: memberTabs,
                    activeTab: activeTab,
                    onChange: function (nextTab) {
                        setActiveTab(nextTab);
                    },
                    ensureRegistrationsTab: false,
                });
            } else {
                tabsNav = h('div', { class: 'mj-regmgr-tabs', role: 'tablist' },
                    memberTabs.map(function (tab) {
                        var isActive = tab.key === activeTab;
                        return h('button', {
                            key: tab.key,
                            type: 'button',
                            class: classNames('mj-regmgr-tab', {
                                'mj-regmgr-tab--active': isActive,
                            }),
                            role: 'tab',
                            'aria-selected': isActive ? 'true' : 'false',
                            'aria-label': tab.label,
                            title: tab.label,
                            onClick: function () {
                                setActiveTab(tab.key);
                            },
                        }, [
                            tab.icon && h('span', {
                                class: 'mj-regmgr-tab__icon',
                                'aria-hidden': 'true',
                                dangerouslySetInnerHTML: { __html: tab.icon },
                            }),
                            h('span', { class: 'mj-regmgr-tab__label', 'aria-hidden': 'true' }, tab.label),
                            tab.badge !== undefined && h('span', { class: 'mj-regmgr-tab__badge' }, tab.badge),
                        ]);
                    })
                );
            }
        }

        var informationSection = h('div', { class: 'mj-regmgr-member-detail__section' }, [
            renderAvatarActions(activeTab === 'information' ? 'mj-regmgr-member-detail__avatar-actions--information' : null),
            h('h2', { class: 'mj-regmgr-member-detail__section-title' }, tabInformationLabel),
            editMode
                ? h('div', { class: 'mj-regmgr-event-editor__section mj-regmgr-member-editor' }, [
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
                ])
                : h('div', { class: 'mj-regmgr-member-detail__info' }, [
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
                    guardianDisplayName && h('div', { class: 'mj-regmgr-member-detail__row mj-regmgr-member-detail__row--guardian' }, [
                        h('svg', { width: 18, height: 18, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('path', { d: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2' }),
                            h('circle', { cx: 9, cy: 7, r: 4 }),
                            h('path', { d: 'M23 21v-2a4 4 0 0 0-3-3.87' }),
                            h('path', { d: 'M16 3.13a4 4 0 0 1 0 7.75' }),
                        ]),
                        h('div', { class: 'mj-regmgr-guardian' }, [
                            (guardianReference && guardianReference.id)
                                ? h(MemberAvatar, { member: guardianReference, size: 'small' })
                                : null,
                            h('div', { class: 'mj-regmgr-guardian__info' }, [
                                h('span', { class: 'mj-regmgr-guardian__label' }, 'Tuteur référent'),
                                h('div', { class: 'mj-regmgr-guardian__name-row' }, [
                                    h('span', { class: 'mj-regmgr-guardian__name' }, guardianDisplayName),
                                    guardianActions.length > 0
                                        ? h('div', { class: 'mj-regmgr-guardian__actions' }, guardianActions)
                                        : null,
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
        ]);

        return h('div', { class: 'mj-regmgr-member-detail' }, [
            // Header avec avatar
            h('div', { class: 'mj-regmgr-member-detail__header' }, [
                h('div', {
                    class: 'mj-regmgr-member-detail__avatar-wrapper',
                    'aria-busy': avatarSaving ? 'true' : 'false',
                }, [
                    h(MemberAvatar, { member: member, size: 'large' }),
                ]),
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
                    onManageAccount && config && config.canManageAccounts && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--icon mj-btn--secondary',
                        onClick: handleOpenAccountModal,
                        title: hasLinkedAccount ? accountButtonLinked : accountButtonUnlinked,
                        'aria-label': hasLinkedAccount ? accountButtonLinked : accountButtonUnlinked,
                        'data-status': hasLinkedAccount ? 'linked' : 'unlinked',
                        'data-tooltip': hasLinkedAccount ? accountStatusLinked : accountStatusUnlinked,
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, [
                            h('circle', { cx: 7, cy: 7, r: 3 }),
                            h('path', { d: 'M12 18c0-3-2.5-5-5-5s-5 2-5 5' }),
                            h('path', { d: 'M15 7.5a2.5 2.5 0 1 1 5 0 2.5 2.5 0 0 1-5 0z' }),
                            h('path', { d: 'M17.5 10v2' }),
                            h('path', { d: 'M16 15l2 2 3-3' }),
                        ]),
                    ]),
                    canDeleteMember && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--ghost mj-btn--danger mj-btn--small',
                        onClick: handleDeleteMember,
                        disabled: deletingMember,
                        title: deletingMember
                            ? getString(strings, 'deleteMemberProcessing', 'Suppression...')
                            : getString(strings, 'deleteMember', 'Supprimer le membre'),
                        'aria-label': deletingMember
                            ? getString(strings, 'deleteMemberProcessing', 'Suppression...')
                            : getString(strings, 'deleteMember', 'Supprimer le membre'),
                    }, [
                        h('svg', { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                            h('polyline', { points: '3 6 5 6 21 6' }),
                            h('path', { d: 'M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6' }),
                            h('path', { d: 'M10 11v6' }),
                            h('path', { d: 'M14 11v6' }),
                            h('path', { d: 'M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2' }),
                        ]),
                        h('span', { class: 'mj-regmgr-member-detail__delete-label' }, deletingMember
                            ? getString(strings, 'deleteMemberProcessing', 'Suppression...')
                            : getString(strings, 'deleteMember', 'Supprimer le membre')
                        ),
                    ]),
                    !editMode && h('button', {
                        type: 'button',
                        class: 'mj-btn mj-btn--icon mj-btn--secondary',
                        onClick: function () {
                            setEditMode(true);
                            setActiveTab('information');
                        },
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
                tabsNav && h('div', { class: 'mj-regmgr-member-detail__tabs-nav' }, [tabsNav]),
                h('div', { class: 'mj-regmgr-member-detail__tabs-content' }, [
                    activeTab === 'information' && informationSection,
                    activeTab === 'membership' && h(Fragment, null, [
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
                    ]),
                    activeTab === 'badges' && h(Fragment, null, [
                        // Level Display
                        member.levelProgression && member.levelProgression.currentLevel && h('div', { class: 'mj-regmgr-member-level' }, [
                            h('div', { class: 'mj-regmgr-member-level__current' }, [
                                member.levelProgression.currentLevel.imageUrl
                                    ? h('div', { class: 'mj-regmgr-member-level__image' }, [
                                        h('img', {
                                            src: member.levelProgression.currentLevel.imageUrl,
                                            alt: member.levelProgression.currentLevel.title || '',
                                            loading: 'lazy',
                                        }),
                                    ])
                                    : h('div', { class: 'mj-regmgr-member-level__badge' }, [
                                        h('span', { class: 'mj-regmgr-member-level__badge-number' }, member.levelProgression.currentLevel.levelNumber),
                                    ]),
                                h('div', { class: 'mj-regmgr-member-level__info' }, [
                                    h('div', { class: 'mj-regmgr-member-level__header' }, [
                                        h('span', { class: 'mj-regmgr-member-level__label' }, getString(strings, 'memberLevelLabel', 'Niveau')),
                                        h('span', { class: 'mj-regmgr-member-level__number' }, member.levelProgression.currentLevel.levelNumber),
                                    ]),
                                    h('h2', { class: 'mj-regmgr-member-level__title' }, member.levelProgression.currentLevel.title || ''),
                                    member.levelProgression.currentLevel.description && h('p', { class: 'mj-regmgr-member-level__description' }, member.levelProgression.currentLevel.description),
                                ]),
                            ]),
                            !member.levelProgression.isMaxLevel && member.levelProgression.nextLevel && h('div', { class: 'mj-regmgr-member-level__progress-section' }, [
                                h('div', { class: 'mj-regmgr-member-level__progress-header' }, [
                                    h('span', { class: 'mj-regmgr-member-level__progress-label' }, [
                                        getString(strings, 'memberLevelNextLabel', 'Prochain niveau:'),
                                        ' ',
                                        h('strong', null, member.levelProgression.nextLevel.title || ('Niveau ' + member.levelProgression.nextLevel.levelNumber)),
                                    ]),
                                    h('span', { class: 'mj-regmgr-member-level__progress-xp' }, [
                                        member.levelProgression.xpRemaining.toLocaleString(),
                                        ' ',
                                        getString(strings, 'memberXpRemainingLabel', 'XP restants'),
                                    ]),
                                ]),
                                h('div', { class: 'mj-regmgr-member-level__progress-bar' }, [
                                    h('div', {
                                        class: 'mj-regmgr-member-level__progress-fill',
                                        style: { width: member.levelProgression.progressPercent + '%' },
                                    }),
                                ]),
                                h('div', { class: 'mj-regmgr-member-level__progress-footer' }, [
                                    h('span', null, member.levelProgression.xpCurrent.toLocaleString() + ' XP'),
                                    h('span', null, member.levelProgression.progressPercent + '%'),
                                    h('span', null, member.levelProgression.xpForNext.toLocaleString() + ' XP'),
                                ]),
                            ]),
                            member.levelProgression.isMaxLevel && h('div', { class: 'mj-regmgr-member-level__max' }, [
                                h('span', { class: 'mj-regmgr-member-level__max-icon' }, '🏆'),
                                h('span', { class: 'mj-regmgr-member-level__max-text' }, getString(strings, 'memberLevelMax', 'Niveau maximum atteint !')),
                            ]),
                        ]),
                        // XP Display
                        h('div', { class: 'mj-regmgr-member-xp' }, [
                            h('div', { class: 'mj-regmgr-member-xp__icon' }, [
                                h('svg', { width: 28, height: 28, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }, [
                                    h('polygon', { points: '12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2' }),
                                ]),
                            ]),
                            h('div', { class: 'mj-regmgr-member-xp__content' }, [
                                h('span', { class: 'mj-regmgr-member-xp__value' }, typeof member.xpTotal === 'number' ? member.xpTotal.toLocaleString() : '0'),
                                h('span', { class: 'mj-regmgr-member-xp__label' }, getString(strings, 'memberXpLabel', 'points XP')),
                            ]),
                            onAdjustXp && h('div', { class: 'mj-regmgr-member-xp__actions' }, [
                                h('button', {
                                    type: 'button',
                                    class: 'mj-regmgr-member-xp__btn mj-regmgr-member-xp__btn--minus',
                                    title: getString(strings, 'memberXpRemove10', 'Retirer 10 XP'),
                                    onClick: function () { onAdjustXp(member.id, -10); },
                                }, '-10'),
                                h('button', {
                                    type: 'button',
                                    class: 'mj-regmgr-member-xp__btn mj-regmgr-member-xp__btn--plus',
                                    title: getString(strings, 'memberXpAdd10', 'Ajouter 10 XP'),
                                    onClick: function () { onAdjustXp(member.id, 10); },
                                }, '+10'),
                            ]),
                        ]),
                        badgeData.length > 0
                            ? h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, badgesTitle),
                                h('div', { class: 'mj-regmgr-member-badges' }, badgeData.map(function (badge) {
                                    var isSaving = !!badgeSaving[badge.id];
                                    var hasCriteria = Array.isArray(badge.criteria) && badge.criteria.length > 0;
                                    var hasBadgeImage = typeof badge.imageUrl === 'string' && badge.imageUrl !== '';
                                    var badgeState = 'locked';
                                    if (badge.status === 'revoked') {
                                        badgeState = 'revoked';
                                    } else if (badge.totalCriteria > 0) {
                                        if (badge.awardedCount >= badge.totalCriteria) {
                                            badgeState = 'complete';
                                        } else if (badge.awardedCount > 0) {
                                            badgeState = 'in_progress';
                                        }
                                    } else if (badge.status === 'awarded') {
                                        badgeState = 'complete';
                                    }

                                    var badgeStateLabel = badgeStateLabels[badgeState] || '';
                                    var progressValue = typeof badge.progress === 'number' ? Math.max(0, Math.min(100, badge.progress)) : 0;
                                    var progressDisplay = badge.totalCriteria > 0
                                        ? badge.awardedCount + ' / ' + badge.totalCriteria
                                        : (badge.status === 'awarded' ? badgeCompletedLabel : badgeNotStartedLabel);

                                    return h('article', {
                                        key: badge.id ? 'badge-' + badge.id : 'badge-' + badge.label,
                                        class: classNames('mj-regmgr-member-badge', 'mj-regmgr-member-badge--state-' + badgeState),
                                    }, [
                                        h('header', { class: 'mj-regmgr-member-badge__header' }, [
                                            h('div', { class: 'mj-regmgr-member-badge__title' }, [
                                                (hasBadgeImage || (typeof badge.icon === 'string' && badge.icon !== '')) && h('span', {
                                                    class: classNames('mj-regmgr-member-badge__icon', {
                                                        'mj-regmgr-member-badge__icon--image': hasBadgeImage,
                                                    }),
                                                }, [
                                                    hasBadgeImage
                                                        ? h('img', {
                                                            src: badge.imageUrl,
                                                            alt: badge.label || '',
                                                            loading: 'lazy',
                                                        })
                                                        : h('i', { class: badge.icon, 'aria-hidden': 'true' }),
                                                ]),
                                                h('div', { class: 'mj-regmgr-member-badge__heading' }, [
                                                    h('h2', { class: 'mj-regmgr-member-badge__name' }, badge.label || 'Badge'),
                                                    badge.summary && h('p', { class: 'mj-regmgr-member-badge__summary' }, badge.summary),
                                                ]),
                                            ]),
                                            h('div', { class: 'mj-regmgr-member-badge__meta' }, [
                                                badgeStateLabel && h('span', { class: classNames('mj-regmgr-member-badge__state', 'mj-regmgr-member-badge__state--' + badgeState) }, badgeStateLabel),
                                                h('span', { class: 'mj-regmgr-member-badge__progress-count' }, progressDisplay),
                                            ]),
                                        ]),
                                        h('div', { class: 'mj-regmgr-member-badge__progress' }, [
                                            h('div', { class: 'mj-regmgr-member-badge__progress-bar' }, [
                                                h('span', {
                                                    class: 'mj-regmgr-member-badge__progress-fill',
                                                    style: { width: progressValue + '%' },
                                                }),
                                            ]),
                                            h('span', { class: 'mj-regmgr-member-badge__progress-label' }, progressValue + '%'),
                                        ]),
                                        hasCriteria
                                            ? h('ul', { class: 'mj-regmgr-member-badge__criteria' }, badge.criteria.map(function (criterion) {
                                                var isChecked = !!criterion.awarded;
                                                var canToggle = !!criterion.canToggle;
                                                return h('li', {
                                                    key: criterion.id ? 'criterion-' + criterion.id : 'criterion-' + badge.id + '-' + criterion.label,
                                                    class: classNames('mj-regmgr-member-badge__criterion', {
                                                        'mj-regmgr-member-badge__criterion--awarded': isChecked,
                                                        'mj-regmgr-member-badge__criterion--readonly': !canToggle,
                                                    }),
                                                }, [
                                                    h('label', { class: 'mj-regmgr-member-badge__criterion-label' }, [
                                                        h('input', {
                                                            type: 'checkbox',
                                                            class: 'mj-regmgr-member-badge__checkbox',
                                                            checked: isChecked,
                                                            disabled: !canToggle || isSaving,
                                                            onChange: function (event) {
                                                                handleToggleBadgeCriterion(badge.id, criterion.id, event.target.checked);
                                                            },
                                                        }),
                                                        h('span', { class: 'mj-regmgr-member-badge__criterion-content' }, [
                                                            h('span', { class: 'mj-regmgr-member-badge__criterion-name' }, criterion.label || getString(strings, 'memberBadgeUnnamedCriterion', 'Critère')),
                                                            criterion.description && h('span', { class: 'mj-regmgr-member-badge__criterion-description' }, criterion.description),
                                                            !canToggle && h('span', { class: 'mj-regmgr-member-badge__criterion-hint' }, badgeReadonlyHint),
                                                        ]),
                                                    ]),
                                                ]);
                                            }))
                                            : h('p', { class: 'mj-regmgr-member-badge__empty' }, badgeNoCriteriaLabel),
                                        isSaving && h('div', { class: 'mj-regmgr-member-badge__saving', 'aria-live': 'polite' }, [
                                            h('span', { class: 'mj-regmgr-spinner mj-regmgr-spinner--inline' }),
                                            h('span', null, badgeSavingLabel),
                                        ]),
                                    ]);
                                })),
                            ])
                            : h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, badgesTitle),
                                h('p', { class: 'mj-regmgr-member-detail__empty' }, badgeEmptyLabel),
                            ]),
                        // Trophies Section
                        trophyData.length > 0
                            ? h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberTrophiesTitle', 'Trophées')),
                                h('div', { class: 'mj-regmgr-member-trophies' }, trophyData.map(function (trophy) {
                                    var isSaving = !!trophySaving[trophy.id];
                                    var hasTrophyImage = typeof trophy.imageUrl === 'string' && trophy.imageUrl !== '';
                                    var isAwarded = trophy.awarded;
                                    var canToggle = trophy.canToggle && !trophy.autoMode;

                                    return h('article', {
                                        key: trophy.id ? 'trophy-' + trophy.id : 'trophy-' + trophy.title,
                                        class: classNames('mj-regmgr-member-trophy', {
                                            'mj-regmgr-member-trophy--awarded': isAwarded,
                                            'mj-regmgr-member-trophy--auto': trophy.autoMode,
                                            'mj-regmgr-member-trophy--saving': isSaving,
                                        }),
                                    }, [
                                        h('label', {
                                            class: classNames('mj-regmgr-member-trophy__container', {
                                                'mj-regmgr-member-trophy__container--disabled': !canToggle,
                                            }),
                                        }, [
                                            canToggle && h('input', {
                                                type: 'checkbox',
                                                class: 'mj-regmgr-member-trophy__checkbox',
                                                checked: isAwarded,
                                                disabled: isSaving,
                                                onChange: function (event) {
                                                    handleToggleTrophy(trophy.id, event.target.checked);
                                                },
                                            }),
                                            h('div', { class: 'mj-regmgr-member-trophy__visual' }, [
                                                hasTrophyImage
                                                    ? h('img', {
                                                        src: trophy.imageUrl,
                                                        alt: trophy.title || '',
                                                        class: 'mj-regmgr-member-trophy__image',
                                                        loading: 'lazy',
                                                    })
                                                    : h('div', { class: 'mj-regmgr-member-trophy__icon' }, [
                                                        h('svg', { width: 32, height: 32, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                            h('path', { d: 'M6 9H4.5a2.5 2.5 0 0 1 0-5H6' }),
                                                            h('path', { d: 'M18 9h1.5a2.5 2.5 0 0 0 0-5H18' }),
                                                            h('path', { d: 'M4 22h16' }),
                                                            h('path', { d: 'M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22' }),
                                                            h('path', { d: 'M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22' }),
                                                            h('path', { d: 'M18 2H6v7a6 6 0 0 0 12 0V2Z' }),
                                                        ]),
                                                    ]),
                                            ]),
                                            h('div', { class: 'mj-regmgr-member-trophy__content' }, [
                                                h('h2', { class: 'mj-regmgr-member-trophy__title' }, trophy.title || getString(strings, 'memberTrophyUntitled', 'Trophée')),
                                                trophy.description && h('p', { class: 'mj-regmgr-member-trophy__description' }, trophy.description),
                                                h('div', { class: 'mj-regmgr-member-trophy__meta' }, [
                                                    trophy.xp > 0 && h('span', { class: 'mj-regmgr-member-trophy__xp' }, '+' + trophy.xp + ' XP'),
                                                    trophy.autoMode && h('span', { class: 'mj-regmgr-member-trophy__auto-badge' }, getString(strings, 'memberTrophyAuto', 'Automatique')),
                                                    isAwarded && !trophy.autoMode && h('span', { class: 'mj-regmgr-member-trophy__awarded-badge' }, getString(strings, 'memberTrophyAwarded', 'Obtenu')),
                                                ]),
                                            ]),
                                        ]),
                                        isSaving && h('div', { class: 'mj-regmgr-member-trophy__saving' }, [
                                            h('span', { class: 'mj-regmgr-spinner mj-regmgr-spinner--inline' }),
                                        ]),
                                    ]);
                                })),
                            ])
                            : h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberTrophiesTitle', 'Trophées')),
                                h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'memberNoTrophies', 'Aucun trophée disponible.')),
                            ]),
                    ]),
                    activeTab === 'photos' && h(Fragment, null, [
                        memberPhotos.length > 0
                            ? h('div', { class: 'mj-regmgr-member-detail__section' }, [
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
                            ])
                            : h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberPhotos', 'Photos partagées')),
                                h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'memberNoPhotos', 'Aucune photo validée pour ce membre.')),
                            ]),
                    ]),
                    activeTab === 'ideas' && h(Fragment, null, [
                        memberIdeas.length > 0
                            ? h('div', { class: 'mj-regmgr-member-detail__section' }, [
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
                            ])
                            : h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberIdeas', 'Idées proposées')),
                                h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'memberNoIdeas', 'Aucune idée proposée pour le moment.')),
                            ]),
                    ]),
                    activeTab === 'messages' && h(Fragment, null, [
                        memberMessages.length > 0
                            ? h('div', { class: 'mj-regmgr-member-detail__section' }, [
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
                            ])
                            : h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'memberMessages', 'Messages reçus')),
                                h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'memberNoMessages', 'Aucun échange trouvé pour ce membre.')),
                            ]),
                    ]),
                    activeTab === 'notes' && h(Fragment, null, [
                        h('div', { class: 'mj-regmgr-member-detail__section' }, [
                            h('h2', { class: 'mj-regmgr-member-detail__section-title' }, 'Notes (' + notes.length + ')'),
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
                    ]),
                    activeTab === 'history' && h(Fragment, null, [
                        registrations.length > 0
                            ? h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, registrationsTitle + ' (' + registrations.length + ')'),
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

                                        var deleteLabel = getString(strings, 'deleteRegistration', 'Supprimer');
                                        var canDelete = allowDeleteRegistration && onDeleteRegistration;

                                        return h('div', { key: reg.id, class: 'mj-regmgr-registration-item' }, [
                                            h('div', { class: 'mj-regmgr-registration-item__info' }, [
                                                h('span', { class: 'mj-regmgr-registration-item__event' }, reg.eventTitle || 'Événement'),
                                                h('span', { class: 'mj-regmgr-registration-item__date' }, formatDate(reg.createdAt)),
                                                sessionsContent,
                                            ].filter(Boolean)),
                                            h('div', { class: 'mj-regmgr-registration-item__meta' }, [
                                                h('span', {
                                                    class: classNames('mj-regmgr-badge', statusClasses[reg.status] || ''),
                                                }, reg.statusLabel || reg.status),
                                                canDelete && h('button', {
                                                    type: 'button',
                                                    class: 'mj-btn mj-btn--ghost mj-btn--danger mj-btn--small',
                                                    onClick: function () { onDeleteRegistration(reg); },
                                                    title: deleteLabel,
                                                    'aria-label': deleteLabel,
                                                }, deleteLabel),
                                            ].filter(Boolean)),
                                        ]);
                                    })
                                ),
                            ])
                            : h('div', { class: 'mj-regmgr-member-detail__section' }, [
                                h('h2', { class: 'mj-regmgr-member-detail__section-title' }, registrationsTitle),
                                h('p', { class: 'mj-regmgr-member-detail__empty' }, registrationsEmptyLabel),
                            ]),
                    ]),
                ]),

                // Section enfants (si le membre est tuteur)
                showChildSection && h('div', { class: 'mj-regmgr-member-detail__section' }, [
                    h('div', { class: 'mj-regmgr-member-detail__section-header' }, [
                        h('h2', { class: 'mj-regmgr-member-detail__section-title' }, getString(strings, 'guardianChildSectionTitle', 'Enfants') + (
                            hasChildren
                                ? ' (' + member.children.length + ')'
                                : ''
                        )),
                        canManageChildren && h('div', { class: 'mj-regmgr-member-detail__section-actions' }, [
                            allowAttachChild && h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--secondary mj-btn--small',
                                onClick: handleAttachChild,
                            }, getString(strings, 'guardianChildAttachExisting', 'Rattacher un enfant')), 
                            allowCreateChild && h('button', {
                                type: 'button',
                                class: 'mj-btn mj-btn--primary mj-btn--small',
                                onClick: handleCreateChild,
                            }, getString(strings, 'guardianChildAddNew', 'Ajouter un enfant')), 
                        ].filter(Boolean)),
                    ]),
                    hasChildren
                        ? h('div', { class: 'mj-regmgr-member-detail__children' },
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
                                            }),
                                        }, membershipLabels[child.membershipStatus] || 'N/A'),
                                        child.membershipYear && h('span', { class: 'mj-regmgr-child-card__year' }, child.membershipYear),
                                    ]),
                                    h('div', { class: 'mj-regmgr-child-card__actions' }, [
                                        onOpenMember && child && child.id && h('button', {
                                            type: 'button',
                                            class: 'mj-btn mj-btn--ghost mj-btn--small',
                                            onClick: function () {
                                                onOpenMember(child, { edit: true });
                                            },
                                            title: 'Éditer le jeune',
                                        }, [
                                            h('svg', { width: 14, height: 14, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', 'stroke-width': 2 }, [
                                                h('path', { d: 'M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7' }),
                                                h('path', { d: 'M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z' }),
                                            ]),
                                            h('span', { class: 'mj-regmgr-child-card__action-label' }, 'Éditer le jeune'),
                                        ]),
                                        config && config.adminMemberUrl && h('a', {
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
                        )
                        : h('p', { class: 'mj-regmgr-member-detail__empty' }, getString(strings, 'guardianChildEmpty', 'Aucun enfant rattaché pour le moment.')),
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
