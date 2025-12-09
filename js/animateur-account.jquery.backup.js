(function ($) {
	'use strict';

	function escapeHtml(value) {
		return String(value === undefined || value === null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function parseConfig($root) {
		var raw = $root.attr('data-config');
		if (!raw) {
			return null;
		}

		try {
			return JSON.parse(raw);
		} catch (error) {
			return null;
		}
	}

	function toInt(value) {
		var parsed = parseInt(value, 10);
		return isNaN(parsed) ? null : parsed;
	}

	function Dashboard($root, config) {
		this.$root = $root;
		this.config = config || {};
		var localizedGlobal = (typeof window !== 'undefined' && window.MjMemberAnimateur) ? window.MjMemberAnimateur : {};
		this.global = $.extend(true, {}, localizedGlobal, this.config.global || {});

		this.settings = this.config.settings || {};
		this.registrationSettings = this.settings.registrations || {};
		this.canRemoveRegistrations = !!this.registrationSettings.canDelete;
		this.canEditMembers = !!this.registrationSettings.canEdit;
		this.registrationRemoveAction = this.global && this.global.actions && this.global.actions.registrationRemove
			? this.global.actions.registrationRemove
			: 'mj_member_animateur_remove_registration';

		this.events = Array.isArray(this.config.events) ? this.config.events.slice() : [];
		this.eventsById = {};
		this.eventOptions = [];
		this.eventOptionLookup = {};
		this.normalizeEvents();

		this.assignedIds = Array.isArray(this.config.assignedEventIds) ? this.cleanIdList(this.config.assignedEventIds) : [];
		if (!this.assignedIds.length) {
			this.assignedIds = this.events.map(function (event) {
				return event.id;
			});
		}
		this.assignedLookup = {};
		for (var i = 0; i < this.assignedIds.length; i += 1) {
			this.assignedLookup[this.assignedIds[i]] = true;
		}

		this.allEvents = this.normalizeSummaryList(this.config.allEvents);
		this.viewAllConfig = this.config.viewAll || {};
		this.viewAllEnabled = !!this.viewAllConfig.enabled;
		this.viewAllMode = this.viewAllEnabled && this.viewAllConfig.mode === 'link' ? 'link' : 'toggle';
		this.viewAllLabels = {
			default: this.viewAllConfig.label || '',
			active: this.viewAllConfig.activeLabel || ''
		};
		if (!this.viewAllLabels.active) {
			this.viewAllLabels.active = this.viewAllLabels.default || '';
		}

		this.pendingEventRequests = {};
		this.pendingClaimRequests = {};
		this.pendingReleaseRequests = {};
		this.memberPicker = null;

		this.claimEnabled = !!(this.global && this.global.actions && this.global.actions.claim);
		this.releaseEnabled = !!(this.global && this.global.actions && this.global.actions.release);

		this.flagSummaryAssignments();
		this.ensureAssignedSummaries();

		this.statusLabels = this.global.attendanceLabels || {
			none: '-',
			present: 'Présent',
			absent: 'Absent',
			pending: 'À confirmer'
		};

		this.paymentLabels = this.config.paymentLabels || {
			unpaid: 'À payer',
			paid: 'Payé'
		};
		this.paymentLinkConfig = this.config.paymentLink || {};
		this.paymentLinkEnabled = !!this.paymentLinkConfig.enabled;
		this.paymentLinkNonce = this.paymentLinkConfig.nonce || '';
		this.paymentLinkModalInitialized = false;
		this.paymentModalLastFocus = null;
		this.$body = $('body');

		this.quickMemberContext = null;
		this.quickCreateConfig = this.config.quickCreate || {};
		this.quickCreateAction = this.global && this.global.actions ? this.global.actions.memberQuickCreate : null;
		this.quickCreateEnabled = !!(this.quickCreateConfig && this.quickCreateConfig.enabled && this.quickCreateAction);

		var hasAssigned = this.events.length > 0;
		var hasToggle = this.viewAllEnabled && this.viewAllMode === 'toggle' && this.allEvents.length > 0;

		if (!hasAssigned && !hasToggle) {
			return;
		}

		this.filterConfig = this.config.filters || {};
		var defaultFilter = typeof this.filterConfig.default === 'string' ? this.filterConfig.default : '';
		if (!defaultFilter) {
			defaultFilter = hasAssigned ? 'assigned' : 'all';
		}
		if (!this.isValidFilter(defaultFilter)) {
			defaultFilter = hasAssigned ? 'assigned' : 'all';
		}

		this.viewAllActive = (defaultFilter === 'all' && hasToggle) || (!hasAssigned && hasToggle);

		var defaultEventId = toInt(this.config.defaultEvent);
		if (defaultEventId === null) {
			if (hasAssigned && this.events.length) {
				defaultEventId = this.events[0].id;
			} else if (this.viewAllActive && this.allEvents.length) {
				defaultEventId = this.allEvents[0].id;
			} else {
				defaultEventId = null;
			}
		}

		this.state = {
			eventId: defaultEventId,
			occurrence: null,
			filter: defaultFilter
		};

		this.eventOptions = [];
		this.filteredEventOptions = [];

		this.cacheDom();
		this.bind();
		this.initPaymentLinkModal();
		this.initMemberPicker();
		this.initQuickMemberCreation();
		this.refreshEventSelect();
		this.refreshOccurrences();
		this.renderParticipants();
		this.updateViewAllButton();
	}

	Dashboard.prototype.normalizeEvents = function () {
		var normalized = [];
		for (var i = 0; i < this.events.length; i += 1) {
			var event = this.events[i];
			var id = toInt(event && event.id);
			if (id === null) {
				continue;
			}
			var statusValue = event && event.status ? event.status : (event && event.meta && event.meta.status ? event.meta.status : '');
			if (!statusValue && event && event.meta && event.meta.statusLabel) {
				statusValue = event.meta.statusLabel;
			}
			var isDraft = this.isDraftStatus(statusValue);
			if (isDraft) {
				event.__isDraft = true;
			}
			event.__statusValue = statusValue || '';
			event.id = id;
			this.eventsById[id] = event;
			normalized.push(event);
		}
		this.events = normalized;
	};

	Dashboard.prototype.cleanIdList = function (list) {
		var cleaned = [];
		var seen = {};
		if (!Array.isArray(list)) {
			return cleaned;
		}
		for (var i = 0; i < list.length; i += 1) {
			var id = toInt(list[i]);
			if (id === null || seen[id]) {
				continue;
			}
			seen[id] = true;
			cleaned.push(id);
		}
		return cleaned;
	};

	Dashboard.prototype.isDraftStatus = function (value) {
		if (!value) {
			return false;
		}
		var normalized = String(value).toLowerCase();
		return normalized === 'brouillon' || normalized === 'draft';
	};

	Dashboard.prototype.isValidFilter = function (value) {
		var available = ['all', 'assigned', 'upcoming', 'past', 'draft'];
		return available.indexOf(value) !== -1;
	};

	Dashboard.prototype.normalizeSummaryList = function (input) {
		var summaries = [];
		var seen = {};
		if (!Array.isArray(input)) {
			return summaries;
		}
		for (var i = 0; i < input.length; i += 1) {
			var raw = input[i];
			if (!raw) {
				continue;
			}
			var id = toInt(raw.id);
			if (id === null || seen[id]) {
				continue;
			}
			var statusValue = raw.status ? raw.status : (raw.statusKey ? raw.statusKey : '');
			var isDraft = this.isDraftStatus(statusValue);
			seen[id] = true;
			var summaryEntry = {
				id: id,
				title: raw.title ? String(raw.title) : '',
				dateLabel: raw.dateLabel ? String(raw.dateLabel) : '',
				status: raw.status ? String(raw.status) : '',
				statusKey: statusValue ? String(statusValue) : '',
				assigned: !!raw.assigned
			};
			if (raw.coverUrl) {
				summaryEntry.coverUrl = String(raw.coverUrl);
			}
			if (raw.locationCoverUrl) {
				summaryEntry.locationCoverUrl = String(raw.locationCoverUrl);
			}
			if (raw.typeLabel) {
				summaryEntry.typeLabel = String(raw.typeLabel);
			}
			if (raw.statusLabel) {
				summaryEntry.statusLabel = String(raw.statusLabel);
			}
			if (raw.priceLabel) {
				summaryEntry.priceLabel = String(raw.priceLabel);
			}
			if (raw.permalink) {
				summaryEntry.permalink = String(raw.permalink);
			}
			if (raw.articlePermalink) {
				summaryEntry.articlePermalink = String(raw.articlePermalink);
			}
			if (raw.start) {
				summaryEntry.start = String(raw.start);
			}
			if (raw.end) {
				summaryEntry.end = String(raw.end);
			}
			if (raw.articleId) {
				summaryEntry.articleId = toInt(raw.articleId);
			}
			summaries.push(summaryEntry);
			if (isDraft) {
				summaryEntry.isDraft = true;
			}
		}
		return summaries;
	};

		Dashboard.prototype.syncSummaryAssignments = function () {
			if (!this.allEvents.length) {
				return;
			}
			for (var i = 0; i < this.allEvents.length; i += 1) {
				var summary = this.allEvents[i];
				var id = toInt(summary && summary.id);
				if (id === null) {
					continue;
				}
				summary.assigned = !!this.assignedLookup[id];
			}
		};

	Dashboard.prototype.flagSummaryAssignments = function () {
		if (!this.allEvents.length) {
			return;
		}
			for (var i = 0; i < this.allEvents.length; i += 1) {
				var summary = this.allEvents[i];
				var id = toInt(summary && summary.id);
				if (id === null) {
					continue;
				}
				if (this.assignedLookup[id]) {
					summary.assigned = true;
				}
			}
	};

	Dashboard.prototype.ensureAssignedSummaries = function () {
		if (!this.viewAllEnabled || this.viewAllMode !== 'toggle') {
			return;
		}

		if (!this.events.length) {
			return;
		}

		if (!this.allEvents.length) {
			this.allEvents = this.getAssignedSummaries();
			return;
		}

		var existing = {};
		for (var i = 0; i < this.allEvents.length; i += 1) {
			existing[this.allEvents[i].id] = true;
		}

		var added = false;
		for (var j = 0; j < this.events.length; j += 1) {
			var event = this.events[j];
			if (!existing[event.id]) {
				this.allEvents.push(this.createSummaryFromEvent(event));
				existing[event.id] = true;
				added = true;
			}
		}

		if (added) {
			this.flagSummaryAssignments();
		}
	};

	Dashboard.prototype.createSummaryFromEvent = function (event) {
		var label = '';
		if (Array.isArray(event.occurrences) && event.occurrences.length) {
			label = event.occurrences[0].label || '';
		}
		if (!label && event.start) {
			label = String(event.start);
		}
		return {
			id: event.id,
			title: event.title ? String(event.title) : 'Événement #' + event.id,
			dateLabel: label,
			status: event.status ? String(event.status) : '',
			assigned: !!this.assignedLookup[event.id]
		};
	};

	Dashboard.prototype.cacheDom = function () {
		this.$eventSelect = this.$root.find('.mj-animateur-dashboard__select--event');
		this.$eventTabs = this.$root.find('[data-role="event-tabs"]');
		this.$occurrenceSelect = this.$root.find('.mj-animateur-dashboard__select--occurrence');
		this.$eventTrack = this.$root.find('[data-role="event-track"]');
		this.$eventNavPrev = this.$root.find('[data-role="event-nav-prev"]');
		this.$eventNavNext = this.$root.find('[data-role="event-nav-next"]');
		this.$tableBody = this.$root.find('.mj-animateur-dashboard__table tbody');
		this.$noData = this.$root.find('[data-role="no-participants"]');
		this.$total = this.$root.find('[data-role="participant-total"]');
		this.$counts = this.$root.find('[data-role="attendance-counts"]');
		this.$feedback = this.$root.find('[data-role="feedback"]');
		this.$smsFeedback = this.$root.find('[data-role="sms-feedback"]');
		this.$smsArea = this.$root.find('.mj-animateur-dashboard__sms-message');
		this.$smsButton = this.$root.find('[data-action="send-sms"]');
		this.$viewAllToggle = this.$root.find('[data-role="toggle-view-all"]');
		this.$summary = this.$root.find('[data-role="summary"]');
		this.$actionsWrapper = this.$root.find('[data-role="actions"]');
		this.$smsWrapper = this.$root.find('[data-role="sms"]');
		this.$unassignedNotice = this.$root.find('[data-role="unassigned-notice"]');
		this.$agenda = this.$root.find('[data-role="occurrence-agenda"]');
		this.$agendaTrack = this.$root.find('[data-role="agenda-track"]');
		this.$agendaNavPrev = this.$root.find('[data-role="agenda-nav-prev"]');
		this.$agendaNavNext = this.$root.find('[data-role="agenda-nav-next"]');
		this.$occurrenceWrapper = this.$root.find('.mj-animateur-dashboard__select-wrapper--occurrence');
		this.$eventDetails = this.$root.find('[data-role="event-details"]');
		this.$eventDetailTitle = this.$root.find('[data-role="event-detail-title"]');
		this.$eventDetailMeta = this.$root.find('[data-role="event-detail-meta"]');
		this.$eventDetailConditionsWrapper = this.$root.find('[data-role="event-detail-conditions-wrapper"]');
		this.$eventDetailConditions = this.$root.find('[data-role="event-detail-conditions"]');
		this.$memberPickerButton = this.$root.find('[data-role="open-member-picker"]');
		this.$memberPickerContainer = this.$root.find('[data-role="member-picker"]');
		this.$quickMemberButton = this.$root.find('[data-role="quick-member-open"]');
		this.$quickMemberModal = this.$root.find('[data-role="quick-member-modal"]');
	};

	Dashboard.prototype.bind = function () {
		var self = this;

	 	if (this.$eventSelect.length) {
	 		this.$eventSelect.on('change', function () {
	 			var selectedId = $(this).val();
	 			self.state.eventId = selectedId ? toInt(selectedId) : null;
				self.onCurrentEventChanged();
	 		});
	 	}

	 	if (this.$occurrenceSelect.length) {
	 		this.$occurrenceSelect.on('change', function () {
	 			self.state.occurrence = $(this).val();
	 			self.renderParticipants();
	 			self.updateAgendaSelection();
	 			self.clearFeedback();
	 		});
	 	}

		if (this.$eventTrack.length) {
	 		this.$eventTrack.on('click', '[data-role="event-card"]', function (event) {
	 			event.preventDefault();
				self.onEventCardClick($(this));
	 		});
	 		this.$eventTrack.on('click', '[data-role="event-link"]', function (event) {
	 			event.stopPropagation();
	 		});
			this.$eventTrack.on('click', '[data-role="assignment-toggle"]', function (event) {
				event.preventDefault();
				event.stopPropagation();
				var $badge = $(this);
				self.onAssignmentToggle($badge);
			});
			this.$eventTrack.on('keydown', '[data-role="assignment-toggle"]', function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					event.stopPropagation();
					$(this).trigger('click');
				}
			});
			this.$eventTrack.on('scroll', function () {
				self.updateEventNavState();
			});
	 	}

	 	if (this.$eventNavPrev && this.$eventNavPrev.length) {
	 		this.$eventNavPrev.on('click', function () {
	 			self.scrollEventTrack('prev');
	 		});
	 	}

	 	if (this.$eventNavNext && this.$eventNavNext.length) {
	 		this.$eventNavNext.on('click', function () {
	 			self.scrollEventTrack('next');
	 		});
	 	}

	 	if (this.$agendaTrack && this.$agendaTrack.length) {
	 		this.$agendaTrack.on('click', '[data-role="agenda-item"]', function (event) {
	 			event.preventDefault();
	 			self.onAgendaItemClick($(this));
	 		});
			this.$agendaTrack.on('scroll', function () {
				self.updateAgendaNavState();
			});
	 	}
		if (this.$eventTabs.length) {
			this.$eventTabs.on('click', '[data-filter]', function (event) {
				event.preventDefault();
				self.onEventTabClick($(this));
			});
		}

	 	if (this.$agendaNavPrev && this.$agendaNavPrev.length) {
	 		this.$agendaNavPrev.on('click', function () {
	 			self.scrollAgenda('prev');
	 		});
	 	}

	 	if (this.$agendaNavNext && this.$agendaNavNext.length) {
	 		this.$agendaNavNext.on('click', function () {
	 			self.scrollAgenda('next');
	 		});
	 	}

	 	if (this.$smsButton.length) {
	 		this.$smsButton.on('click', function () {
	 			self.sendSms();
	 		});
	 	}

	 	if (this.$viewAllToggle.length && this.viewAllEnabled && this.viewAllMode === 'toggle') {
	 		this.$viewAllToggle.on('click', function () {
	 			self.toggleViewAll();
	 		});
	 	}

	 	if (this.$tableBody.length && this.settings.attendance) {
	 		this.$tableBody.on('click', '[data-role="attendance-option"]', function (event) {
	 			event.preventDefault();
	 			self.onAttendanceOptionClick($(this));
	 		});
	 	}

		if (this.$tableBody.length) {
	 		this.$tableBody.on('click', '[data-role="payment-toggle"]', function (event) {
	 			event.preventDefault();
	 			self.onPaymentToggleClick($(this));
	 		});

	 		this.$tableBody.on('click', '[data-role="message-toggle"]', function (event) {
	 			event.preventDefault();
	 			self.onMessageToggleClick($(this));
	 		});

	 		this.$tableBody.on('click', '[data-role="message-send"]', function (event) {
	 			event.preventDefault();
	 			self.onMessageSendClick($(this));
	 		});

	 		this.$tableBody.on('click', '[data-role="message-cancel"]', function (event) {
	 			event.preventDefault();
	 			self.onMessageCancelClick($(this));
	 		});

			this.$tableBody.on('click', '[data-role="registration-remove"]', function (event) {
				event.preventDefault();
				self.onRegistrationRemoveClick($(this));
			});
	 	}

		if (this.$memberPickerButton.length) {
			this.$memberPickerButton.on('click', function (event) {
				event.preventDefault();
				self.onMemberPickerButtonClick();
			});
		}

		if (this.quickCreateEnabled && this.$quickMemberButton.length) {
			this.$quickMemberButton.on('click', function (event) {
				event.preventDefault();
				self.onQuickMemberButtonClick();
			});
		}
	};

	Dashboard.prototype.initMemberPicker = function () {
		if (!this.$memberPickerContainer.length) {
			this.memberPicker = null;
			return;
		}

		this.memberPicker = new MemberPicker(this, {
			container: this.$memberPickerContainer,
			trigger: this.$memberPickerButton
		});

		var initialAssigned = this.isAssignedEvent(this.state.eventId);
		this.updateMemberPickerState(initialAssigned);
	};

	Dashboard.prototype.initQuickMemberCreation = function () {
		if (!this.quickCreateEnabled || !this.$quickMemberModal.length) {
			this.quickMemberModal = null;
			return;
		}

		this.quickMemberModal = new QuickMemberModal(this, {
			container: this.$quickMemberModal,
			trigger: this.$quickMemberButton,
			action: this.quickCreateAction
		});
	};

	Dashboard.prototype.onQuickMemberButtonClick = function () {
		if (!this.quickCreateEnabled || !this.quickMemberModal) {
			return;
		}

		this.quickMemberModal.open();
	};

	Dashboard.prototype.buildQuickMemberSearchTerm = function (member) {
		if (!member) {
			return '';
		}
		var first = member.first_name ? String(member.first_name) : '';
		var last = member.last_name ? String(member.last_name) : '';
		var combined = (first + ' ' + last).replace(/\s+/g, ' ').trim();
		if (!combined && member.email) {
			combined = String(member.email);
		}
		return combined;
	};

	Dashboard.prototype.handleQuickMemberCreated = function (member) {
		if (!member) {
			this.quickMemberContext = null;
			return;
		}

		this.quickMemberContext = {
			member: member,
			search: this.buildQuickMemberSearchTerm(member)
		};
	};

	Dashboard.prototype.canAutoOpenMemberPicker = function () {
		if (!this.memberPicker || !this.memberPicker.enabled) {
			return false;
		}
		if (!this.state || this.state.eventId === null) {
			return false;
		}
		return this.isAssignedEvent(this.state.eventId);
	};

	Dashboard.prototype.openMemberPickerAfterQuickCreate = function () {
		if (!this.canAutoOpenMemberPicker()) {
			return;
		}
		this.onMemberPickerButtonClick();
	};

	Dashboard.prototype.updateMemberPickerState = function (isAssigned) {
		var enabled = !!isAssigned;
		if (this.$memberPickerButton.length) {
			this.$memberPickerButton.prop('disabled', !enabled);
		}
		if (this.memberPicker) {
			this.memberPicker.setEnabled(enabled);
		}
	};

	Dashboard.prototype.onMemberPickerButtonClick = function () {
		if (!this.memberPicker || !this.memberPicker.enabled) {
			return;
		}

		var currentEventId = this.state.eventId;
		var self = this;
		if (currentEventId === null) {
			this.showFeedback('attendance', 'error', this.translate('memberPickerNoEvent', "Sélectionnez un événement avant d'ajouter un participant."));
			return;
		}

		if (!this.isAssignedEvent(currentEventId)) {
			this.showFeedback('attendance', 'error', this.translate('notAssigned', 'Cet événement ne vous est pas attribué.'));
			return;
		}

		this.ensureEventLoaded(currentEventId).done(function () {
			self.memberPicker.open(currentEventId);
		}).fail(function (message) {
			self.showFeedback('attendance', 'error', message || self.translate('eventLoadError', "Impossible de charger cet événement."));
		});
	};

	Dashboard.prototype.toggleOccurrenceUi = function (hasOccurrences) {
		var visible = !!hasOccurrences;
		if (this.$agenda && this.$agenda.length) {
			this.$agenda.toggle(visible);
		}
		if (this.$occurrenceWrapper && this.$occurrenceWrapper.length) {
			this.$occurrenceWrapper.toggle(visible);
		}
		if (this.$occurrenceSelect && this.$occurrenceSelect.length) {
			this.$occurrenceSelect.prop('disabled', !visible);
		}
	};

	Dashboard.prototype.updateEventSnapshot = function (snapshot) {
		if (!snapshot) {
			return;
		}

		var eventId = toInt(snapshot.id);
		if (eventId === null) {
			return;
		}

		var wasCurrent = this.state.eventId !== null && this.state.eventId === eventId;
		var previousOccurrence = wasCurrent ? this.state.occurrence : null;
		var isAssigned = Object.prototype.hasOwnProperty.call(snapshot, 'isAssigned') ? !!snapshot.isAssigned : this.isAssignedEvent(eventId);

		this.eventsById[eventId] = snapshot;

		var existingIndex = -1;
		for (var i = 0; i < this.events.length; i += 1) {
			if (toInt(this.events[i].id) === eventId) {
				existingIndex = i;
				break;
			}
		}

		if (isAssigned) {
			if (existingIndex !== -1) {
				this.events[existingIndex] = snapshot;
			} else {
				this.events.push(snapshot);
			}
			this.assignedLookup[eventId] = true;
		} else if (existingIndex !== -1) {
			this.events.splice(existingIndex, 1);
			delete this.assignedLookup[eventId];
		} else {
			delete this.assignedLookup[eventId];
		}

		if (isAssigned) {
			this.state.eventId = eventId;
		} else if (wasCurrent) {
			this.state.eventId = null;
			this.state.occurrence = null;
		}

		if (isAssigned) {
			this.markEventAssigned(eventId);
		} else {
			this.unmarkEventAssigned(eventId);
		}

		this.refreshEventSelect();

		if (isAssigned && this.state.eventId === eventId && previousOccurrence) {
			this.state.occurrence = previousOccurrence;
		}

		this.refreshOccurrences();
		this.renderParticipants();
		this.updateSummary(this.getCurrentEvent());
		this.updateViewAllButton();
		this.updateMemberPickerState(this.isAssignedEvent(this.state.eventId));
		if (this.memberPicker && this.memberPicker.state.open && toInt(this.memberPicker.state.eventId) === eventId) {
			this.memberPicker.renderConditions();
		}
	};

	Dashboard.prototype.getEvent = function (eventId) {
		var id = toInt(eventId);
		if (id === null) {
			return null;
		}
		return this.eventsById[id] || null;
	};

		Dashboard.prototype.getCurrentOccurrence = function () {
			var occurrence = this.state.occurrence;
			if (occurrence) {
				return String(occurrence);
			}
			var event = this.getCurrentEvent();
			if (!event) {
				return null;
			}
			if (event.defaultOccurrence) {
				return String(event.defaultOccurrence);
			}
			if (Array.isArray(event.occurrences) && event.occurrences.length) {
				var fallback = event.occurrences[0].start || null;
				return fallback ? String(fallback) : null;
			}
			return null;
		};

		Dashboard.prototype.buildRegistrationScope = function (eventId) {
			var scope = { mode: 'all', occurrences: [] };
			var event = this.getEvent(eventId);
			if (!event) {
				return scope;
			}
			var type = event.type ? String(event.type) : '';
			if (type === 'atelier') {
				var occurrence = this.getCurrentOccurrence();
				if (occurrence) {
					scope.mode = 'custom';
					scope.occurrences = [String(occurrence)];
				}
			}
			return scope;
		};

		Dashboard.prototype.participantMatchesOccurrence = function (participant, occurrence) {
			if (!participant) {
				return false;
			}
			var scope = participant.occurrenceScope || {};
			var mode = scope.mode || 'all';
			if (mode !== 'custom') {
				return true;
			}
			var occurrences = Array.isArray(scope.occurrences) ? scope.occurrences : [];
			if (!occurrences.length) {
				return false;
			}
			if (!occurrence) {
				return true;
			}
			var normalized = String(occurrence);
			for (var i = 0; i < occurrences.length; i += 1) {
				if (String(occurrences[i]) === normalized) {
					return true;
				}
			}
			return false;
		};

		Dashboard.prototype.getVisibleParticipants = function (participants, occurrence) {
			if (!Array.isArray(participants)) {
				return [];
			}
			var visible = [];
			for (var i = 0; i < participants.length; i += 1) {
				if (this.participantMatchesOccurrence(participants[i], occurrence)) {
					visible.push(participants[i]);
				}
			}
			return visible;
		};

	Dashboard.prototype.onAttendanceOptionClick = function ($option) {
		if (!this.settings.attendance || !$option || !$option.length) {
			return;
		}

		var status = $option.attr('data-status');
		if (!status) {
			return;
		}

		var $control = $option.closest('[data-role="attendance-control"]');
		if (!$control.length) {
			return;
		}

		var $input = $control.find('input[data-role="attendance"]').first();
		if (!$input.length) {
			return;
		}

		var memberId = toInt($input.attr('data-member-id'));
		if (memberId === null) {
			return;
		}

		var registrationId = toInt($input.attr('data-registration-id'));
		var previousStatus = $input.val() || 'pending';
		if (previousStatus === status) {
			return;
		}

		this.updateAttendanceControlState($control, status);

		this.saveSingleAttendance(
			memberId,
			registrationId === null ? 0 : registrationId,
			status,
			$control,
			previousStatus
		);
	};

	Dashboard.prototype.getParticipant = function (event, memberId) {
		if (!event || !Array.isArray(event.participants)) {
			return null;
		}
		var id = toInt(memberId);
		if (id === null) {
			return null;
		}
		for (var i = 0; i < event.participants.length; i += 1) {
			var participant = event.participants[i];
			if (toInt(participant.memberId) === id) {
				return participant;
			}
		}
		return null;
	};

	Dashboard.prototype.getAssignedSummaries = function () {
		var summaries = [];
		for (var i = 0; i < this.events.length; i += 1) {
			summaries.push(this.createSummaryFromEvent(this.events[i]));
		}
		return summaries;
	};

	Dashboard.prototype.formatEventSelectLabel = function (option) {
		if (!option) {
			if (status === 'pending') {
				return this.translate('attendancePending', 'À confirmer');
			}
			return '';
		}
		var parts = [];
		var title = option.title ? String(option.title) : 'Événement #' + option.id;
		parts.push(title);
		if (option.nextLabel) {
			parts.push(option.nextLabel);
		} else if (option.dateLabel) {
			parts.push(option.dateLabel);
		}
		if (!option.assigned && this.viewAllMode === 'toggle') {
			parts.push(this.translate('eventCardUnassigned', 'Non assigné'));
		}
		return parts.join(' - ');
	};

	Dashboard.prototype.buildEventOptionFromEvent = function (event) {
		if (!event) {
			return null;
		}

		var id = toInt(event.id);
		if (id === null) {
			return null;
		}

		var hasAssignedFlag = Object.prototype.hasOwnProperty.call(this.assignedLookup, id) ? !!this.assignedLookup[id] : false;
		if (Object.prototype.hasOwnProperty.call(event, 'isAssigned')) {
			hasAssignedFlag = !!event.isAssigned;
		}
		var option = {
			id: id,
			title: event.title ? String(event.title) : 'Événement #' + id,
			assigned: hasAssignedFlag,
			coverUrl: '',
			coverAlt: '',
			typeLabel: '',
			statusLabel: '',
			dateLabel: '',
			nextLabel: '',
			locationLabel: '',
			priceLabel: '',
			participantsCount: 0,
			participantsLabel: '',
			permalink: '',
			source: 'event',
			raw: event
		};
		option.status = event.status ? String(event.status) : (event.__statusValue || '');
		option.isDraft = !!event.__isDraft || this.isDraftStatus(option.status);

		var detailLink = event.permalink || event.articlePermalink || '';
		if (detailLink) {
			option.permalink = String(detailLink);
		}

		if (event.cover && event.cover.url) {
			option.coverUrl = String(event.cover.url);
		}
		if (event.cover && event.cover.alt) {
			option.coverAlt = String(event.cover.alt);
		}
		if (!option.coverAlt) {
			option.coverAlt = option.title;
		}

		var meta = event.meta || {};
		if (meta.typeLabel) {
			option.typeLabel = String(meta.typeLabel);
		}
		if (meta.statusLabel) {
			option.statusLabel = String(meta.statusLabel);
		}
		if (meta.dateLabel) {
			option.dateLabel = String(meta.dateLabel);
		}
		if (meta.nextOccurrenceLabel) {
			option.nextLabel = String(meta.nextOccurrenceLabel);
		}
		if (meta.locationLabel) {
			option.locationLabel = String(meta.locationLabel);
		}
		if (meta.priceLabel) {
			option.priceLabel = String(meta.priceLabel);
		}
		if (typeof meta.participantCount === 'number') {
			option.participantsCount = meta.participantCount;
		} else if (typeof event.participantsCount === 'number') {
			option.participantsCount = event.participantsCount;
		}
		if (meta.participantCountLabel) {
			option.participantsLabel = String(meta.participantCountLabel);
		}

		if (!option.nextLabel && event.defaultOccurrenceLabel) {
			option.nextLabel = String(event.defaultOccurrenceLabel);
		}
		if (!option.dateLabel && event.start) {
			option.dateLabel = String(event.start);
		}
		if (!option.priceLabel && typeof event.priceLabel === 'string') {
			option.priceLabel = String(event.priceLabel);
		}
		if (!option.coverUrl && event.cover && event.cover.thumb) {
			option.coverUrl = String(event.cover.thumb);
		}
		if (!option.coverUrl && event.locationCover) {
			option.coverUrl = String(event.locationCover);
		}

		if (!option.participantsLabel) {
			option.participantsLabel = this.translate('eventCardParticipants', 'Participants : %d').replace('%d', option.participantsCount || 0);
		}

		var now = Date.now();
		var occurrences = Array.isArray(event.occurrences) ? event.occurrences : [];
		var nextTimestamp = null;
		var lastTimestamp = null;
		for (var i = 0; i < occurrences.length; i += 1) {
			var occurrence = occurrences[i];
			var occurrenceTimestamp = this.normalizeTimestamp(occurrence && occurrence.timestamp);
			if (occurrenceTimestamp === null) {
				continue;
			}
			if (lastTimestamp === null || occurrenceTimestamp > lastTimestamp) {
				lastTimestamp = occurrenceTimestamp;
			}
			var isPast = !!occurrence.isPast;
			var isNext = !!occurrence.isNext;
			var considerFuture = isNext || !isPast || !!occurrence.isToday;
			if (considerFuture) {
				if (nextTimestamp === null || occurrenceTimestamp < nextTimestamp || isNext) {
					nextTimestamp = occurrenceTimestamp;
				}
			}
		}

		if (nextTimestamp === null && event.start) {
			nextTimestamp = this.parseDateValue(event.start);
		}
		if (lastTimestamp === null && event.end) {
			lastTimestamp = this.parseDateValue(event.end);
		}

		option.nextTimestamp = nextTimestamp;
		option.lastTimestamp = lastTimestamp;
		option.isUpcoming = nextTimestamp !== null && nextTimestamp >= now;
		option.isPast = !option.isUpcoming && lastTimestamp !== null && lastTimestamp < now;
		option.sortKey = nextTimestamp !== null ? nextTimestamp : (lastTimestamp !== null ? lastTimestamp : Number.POSITIVE_INFINITY);

		return option;
	};

	Dashboard.prototype.buildEventOptionFromSummary = function (summary) {
		if (!summary) {
			return null;
		}
		var id = toInt(summary.id);
		if (id === null) {
			return null;
		}

		var event = this.eventsById[id];
		if (event) {
			var enriched = this.buildEventOptionFromEvent(event);
			if (enriched) {
				enriched.source = 'summary';
				enriched.summary = summary;
				return enriched;
			}
		}

		var option = {
			id: id,
			title: summary.title ? String(summary.title) : 'Événement #' + id,
			assigned: !!summary.assigned,
			coverUrl: summary.coverUrl ? String(summary.coverUrl) : (summary.locationCoverUrl ? String(summary.locationCoverUrl) : ''),
			coverAlt: summary.title ? String(summary.title) : '',
			typeLabel: summary.typeLabel ? String(summary.typeLabel) : '',
			statusLabel: summary.statusLabel ? String(summary.statusLabel) : '',
			dateLabel: summary.dateLabel ? String(summary.dateLabel) : '',
			nextLabel: summary.dateLabel ? String(summary.dateLabel) : '',
			locationLabel: '',
			priceLabel: summary.priceLabel ? String(summary.priceLabel) : '',
			participantsCount: 0,
			participantsLabel: this.translate('eventCardParticipants', 'Participants : %d').replace('%d', 0),
			permalink: '',
			source: 'summary',
			summary: summary
		};
		option.status = summary.status ? String(summary.status) : (summary.statusKey || '');
		option.isDraft = !!summary.isDraft || this.isDraftStatus(option.status);

		var summaryDetailLink = summary.permalink || summary.articlePermalink || '';
		if (summaryDetailLink) {
			option.permalink = String(summaryDetailLink);
		}

		if (!option.coverAlt) {
			option.coverAlt = option.title;
		}

		var now = Date.now();
		var startTimestamp = this.parseDateValue(summary.start || summary.dateLabel || '');
		var endTimestamp = this.parseDateValue(summary.end || '');
		option.nextTimestamp = startTimestamp;
		option.lastTimestamp = endTimestamp !== null ? endTimestamp : startTimestamp;
		option.isUpcoming = startTimestamp !== null && startTimestamp >= now;
		if (option.isUpcoming && option.isDraft) {
			option.isUpcoming = false;
		}
		var pastReference = option.lastTimestamp !== null ? option.lastTimestamp : startTimestamp;
		option.isPast = pastReference !== null && pastReference < now && !option.isUpcoming;
		option.sortKey = option.nextTimestamp !== null ? option.nextTimestamp : (option.lastTimestamp !== null ? option.lastTimestamp : Number.POSITIVE_INFINITY);

		return option;
	};

	Dashboard.prototype.normalizeTimestamp = function (value) {
		if (value === null || value === undefined || value === '') {
			return null;
		}
		var numeric = Number(value);
		if (!isFinite(numeric)) {
			return null;
		}
		if (Math.abs(numeric) < 1e12) {
			numeric *= 1000;
		}
		return Math.round(numeric);
	};

	Dashboard.prototype.parseDateValue = function (value) {
		if (value === null || value === undefined || value === '') {
			return null;
		}
		if (typeof value === 'number') {
			return this.normalizeTimestamp(value);
		}
		var stringValue = String(value).trim();
		if (stringValue.indexOf(' ') > 0 && stringValue.indexOf('T') === -1) {
			stringValue = stringValue.replace(' ', 'T');
		}
		var parsed = Date.parse(stringValue);
		if (!isNaN(parsed)) {
			return parsed;
		}
		return null;
	};

	Dashboard.prototype.buildEventOptions = function () {
		var options = [];
		var seen = {};
		this.syncSummaryAssignments();
		for (var i = 0; i < this.events.length; i += 1) {
			var eventOption = this.buildEventOptionFromEvent(this.events[i]);
			if (!eventOption) {
				continue;
			}
			options.push(eventOption);
			seen[eventOption.id] = eventOption;
		}

		var includeSummaries = this.viewAllEnabled || this.allEvents.length;
		if (includeSummaries) {
			for (var j = 0; j < this.allEvents.length; j += 1) {
				var summary = this.allEvents[j];
				var identifier = toInt(summary && summary.id);
				if (identifier === null) {
					continue;
				}
				if (seen[identifier]) {
					this.mergeSummaryIntoOption(seen[identifier], summary);
					continue;
				}
				var summaryOption = this.buildEventOptionFromSummary(summary);
				if (summaryOption) {
					options.push(summaryOption);
					seen[identifier] = summaryOption;
				}
			}
		}

		options.sort(function (a, b) {
			var aKey = typeof a.sortKey === 'number' ? a.sortKey : Number.POSITIVE_INFINITY;
			var bKey = typeof b.sortKey === 'number' ? b.sortKey : Number.POSITIVE_INFINITY;
			if (aKey === bKey) {
				return String(a.title).localeCompare(String(b.title));
			}
			return aKey - bKey;
		});

		return options;
	};

		Dashboard.prototype.getEventOption = function (eventId) {
			var id = toInt(eventId);
			if (id === null) {
				return null;
			}
			return this.eventOptionLookup[id] || null;
		};

		Dashboard.prototype.setEventLoading = function (isLoading, eventId) {
			this.$root.toggleClass('mj-animateur-dashboard--event-loading', !!isLoading);
			if (!this.$eventTrack.length) {
				return;
			}
			var normalizedId = toInt(eventId);
			if (normalizedId === null) {
				normalizedId = this.state.eventId;
			}
			if (normalizedId === null) {
				return;
			}
			this.$eventTrack.find('[data-role="event-card"]').each(function () {
				var $card = $(this);
				var cardId = toInt($card.attr('data-event-id'));
				if (cardId === normalizedId) {
					$card.toggleClass('is-loading', !!isLoading);
				} else if (!isLoading) {
					$card.removeClass('is-loading');
				}
			});
		};

		Dashboard.prototype.ensureEventLoaded = function (eventId) {
			var deferred = $.Deferred();
			var id = toInt(eventId);
			if (id === null) {
				deferred.reject(this.translate('eventLoadError', "Impossible de charger cet événement."));
				return deferred.promise();
			}

			var existing = this.getEvent(id);
			if (existing) {
				deferred.resolve(existing);
				return deferred.promise();
			}

			return this.fetchEvent(id);
		};

		Dashboard.prototype.fetchEvent = function (eventId) {
			var id = toInt(eventId);
			if (id === null) {
				var invalid = $.Deferred();
				invalid.reject(this.translate('eventLoadError', "Impossible de charger cet événement."));
				return invalid.promise();
			}

			if (this.pendingEventRequests[id]) {
				return this.pendingEventRequests[id].promise();
			}

			var deferred = $.Deferred();
			this.pendingEventRequests[id] = deferred;
			this.setEventLoading(true, id);

			var self = this;
			$.ajax({
				url: this.global.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: this.global.actions ? this.global.actions.event : 'mj_member_animateur_get_event',
					nonce: this.global.nonce || '',
					event_id: id
				}
			}).done(function (response) {
				if (response && response.success && response.data && response.data.event) {
					self.updateEventSnapshot(response.data.event);
					deferred.resolve(self.getEvent(id));
					return;
				}
				var message = response && response.data && response.data.message ? String(response.data.message) : self.translate('eventLoadError', "Impossible de charger cet événement.");
				deferred.reject(message);
			}).fail(function (jqXHR) {
				var message = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ? String(jqXHR.responseJSON.data.message) : self.translate('eventLoadError', "Impossible de charger cet événement.");
				deferred.reject(message);
			}).always(function () {
				self.setEventLoading(false, id);
				delete self.pendingEventRequests[id];
			});

			return deferred.promise();
		};

		Dashboard.prototype.onAssignmentToggle = function ($badge) {
			if (!$badge || !$badge.length) {
				return;
			}

			var $card = $badge.closest('[data-role="event-card"]');
			if (!$card.length) {
				return;
			}

			var eventId = toInt($card.attr('data-event-id'));
			if (eventId === null) {
				return;
			}

			var state = String($badge.attr('data-state') || '');
			var isAssigned = state === 'assigned';

			if (isAssigned) {
				if (!this.releaseEnabled) {
					return;
				}
				this.releaseEvent(eventId, $badge);
				return;
			}

			if (!this.claimEnabled) {
				return;
			}
			this.claimEvent(eventId, $badge);
		};

		Dashboard.prototype.claimEvent = function (eventId, $trigger) {
			var id = toInt(eventId);
			if (id === null || !this.claimEnabled || this.isAssignedEvent(id)) {
				return;
			}

			if (this.pendingClaimRequests[id]) {
				return this.pendingClaimRequests[id].promise();
			}

			var deferred = $.Deferred();
			this.pendingClaimRequests[id] = deferred;

			if ($trigger && $trigger.length) {
				$trigger.addClass('is-loading');
			}

			this.setEventLoading(true, id);

			var self = this;
			$.ajax({
				url: this.global.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: this.global.actions ? this.global.actions.claim : '',
					nonce: this.global.nonce || '',
					event_id: id
				}
			}).done(function (response) {
				if (response && response.success && response.data && response.data.event) {
					var snapshot = response.data.event;
					snapshot = snapshot || {};
					snapshot.id = snapshot.id || id;
					snapshot.isAssigned = true;
					self.assignedLookup[id] = true;
					self.markEventAssigned(id);
					self.updateEventSnapshot(snapshot);
					deferred.resolve(self.getEvent(id));
					self.showFeedback('attendance', 'success', self.translate('claimSuccess', "Événement ajouté à vos événements."));
					return;
				}
				var message = response && response.data && response.data.message ? String(response.data.message) : self.translate('claimError', "Impossible d'attribuer cet événement.");
				deferred.reject(message);
				self.showFeedback('attendance', 'error', message);
			}).fail(function (jqXHR) {
				var message = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ? String(jqXHR.responseJSON.data.message) : self.translate('claimError', "Impossible d'attribuer cet événement.");
				deferred.reject(message);
				self.showFeedback('attendance', 'error', message);
			}).always(function () {
				if ($trigger && $trigger.length) {
					$trigger.removeClass('is-loading');
				}
				self.setEventLoading(false, id);
				delete self.pendingClaimRequests[id];
			});

			return deferred.promise();
		};

		Dashboard.prototype.markEventAssigned = function (eventId) {
			var id = toInt(eventId);
			if (id === null) {
				return;
			}
			this.assignedLookup[id] = true;
			if (this.eventsById[id]) {
				this.eventsById[id].isAssigned = true;
			}
			for (var i = 0; i < this.allEvents.length; i += 1) {
				if (toInt(this.allEvents[i].id) === id) {
					this.allEvents[i].assigned = true;
				}
			}
		};

		Dashboard.prototype.unmarkEventAssigned = function (eventId) {
			var id = toInt(eventId);
			if (id === null) {
				return;
			}
			delete this.assignedLookup[id];
			if (this.eventsById[id]) {
				this.eventsById[id].isAssigned = false;
			}
			for (var i = 0; i < this.allEvents.length; i += 1) {
				if (toInt(this.allEvents[i].id) === id) {
					this.allEvents[i].assigned = false;
				}
			}
		};

	Dashboard.prototype.mergeSummaryIntoOption = function (option, summary) {
		if (!option || !summary) {
			return;
		}
		if (!option.coverUrl) {
			if (summary.coverUrl) {
				option.coverUrl = String(summary.coverUrl);
			} else if (summary.locationCoverUrl) {
				option.coverUrl = String(summary.locationCoverUrl);
			}
		}
		if (!option.priceLabel && summary.priceLabel) {
			option.priceLabel = String(summary.priceLabel);
		}
		if (!option.typeLabel && summary.typeLabel) {
			option.typeLabel = String(summary.typeLabel);
		}
		if (!option.statusLabel && summary.statusLabel) {
			option.statusLabel = String(summary.statusLabel);
		}
		if (!option.dateLabel && summary.dateLabel) {
			option.dateLabel = String(summary.dateLabel);
		}
		if (!option.nextLabel && summary.dateLabel) {
			option.nextLabel = String(summary.dateLabel);
		}
		if (!option.permalink && (summary.permalink || summary.articlePermalink)) {
			option.permalink = String(summary.permalink || summary.articlePermalink);
		}
		if (Object.prototype.hasOwnProperty.call(summary, 'assigned')) {
			option.assigned = !!summary.assigned;
		}
		if (!option.status || option.status === '') {
			option.status = summary.status ? String(summary.status) : (summary.statusKey || '');
		}
		if (!option.isDraft) {
			option.isDraft = !!summary.isDraft || this.isDraftStatus(option.status);
		}
		if (!option.participantsLabel) {
			option.participantsLabel = this.translate('eventCardParticipants', 'Participants : %d').replace('%d', option.participantsCount || 0);
		}
		if (!option.nextTimestamp || option.nextTimestamp === null) {
			option.nextTimestamp = this.parseDateValue(summary.start || summary.dateLabel || '');
		}
		if (!option.lastTimestamp || option.lastTimestamp === null) {
			option.lastTimestamp = this.parseDateValue(summary.end || '') || option.nextTimestamp || option.lastTimestamp;
		}
		if (typeof option.sortKey !== 'number' || !isFinite(option.sortKey)) {
			option.sortKey = option.nextTimestamp !== null ? option.nextTimestamp : (option.lastTimestamp !== null ? option.lastTimestamp : Number.POSITIVE_INFINITY);
		}
		var now = Date.now();
		option.isUpcoming = option.nextTimestamp !== null && option.nextTimestamp >= now && !option.isDraft;
		var reference = option.lastTimestamp !== null ? option.lastTimestamp : option.nextTimestamp;
		option.isPast = reference !== null && reference < now && !option.isUpcoming;
	};

	Dashboard.prototype.releaseEvent = function (eventId, $trigger) {
		var id = toInt(eventId);
		if (id === null || !this.releaseEnabled || !this.isAssignedEvent(id)) {
			return;
		}

		if (this.pendingReleaseRequests[id]) {
			return this.pendingReleaseRequests[id].promise();
		}

		var deferred = $.Deferred();
		this.pendingReleaseRequests[id] = deferred;

		if ($trigger && $trigger.length) {
			$trigger.addClass('is-loading');
		}

		this.setEventLoading(true, id);

		var self = this;
		$.ajax({
			url: this.global.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: this.global.actions ? this.global.actions.release : '',
				nonce: this.global.nonce || '',
				event_id: id
			}
		}).done(function (response) {
			if (response && response.success && response.data && response.data.event) {
				var snapshot = response.data.event || {};
				snapshot.id = snapshot.id || id;
				snapshot.isAssigned = false;
				self.unmarkEventAssigned(id);
				self.updateEventSnapshot(snapshot);
				deferred.resolve(self.getEvent(id));
				self.showFeedback('attendance', 'success', self.translate('releaseSuccess', 'Événement retiré de vos événements.'));
				return;
			}
			var message = response && response.data && response.data.message ? String(response.data.message) : self.translate('releaseError', "Impossible de vous désassigner de cet événement.");
			deferred.reject(message);
			self.showFeedback('attendance', 'error', message);
		}).fail(function (jqXHR) {
			var message = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ? String(jqXHR.responseJSON.data.message) : self.translate('releaseError', "Impossible de vous désassigner de cet événement.");
			deferred.reject(message);
			self.showFeedback('attendance', 'error', message);
		}).always(function () {
			if ($trigger && $trigger.length) {
				$trigger.removeClass('is-loading');
			}
			self.setEventLoading(false, id);
			delete self.pendingReleaseRequests[id];
		});

		return deferred.promise();
	};

	Dashboard.prototype.filterEventOptions = function (options, filter) {
		if (!Array.isArray(options)) {
			return [];
		}
		var normalized = this.isValidFilter(filter) ? filter : 'all';
		var filtered = [];
		for (var i = 0; i < options.length; i += 1) {
			var option = options[i];
			if (!option) {
				continue;
			}
			var include = true;
			switch (normalized) {
				case 'assigned':
					include = !!option.assigned && !option.isDraft;
					break;
				case 'upcoming':
					include = !!option.isUpcoming && !option.isDraft;
					break;
				case 'past':
					include = !!option.isPast && !option.isDraft;
					break;
				case 'draft':
					include = !!option.isDraft;
					break;
				case 'all':
				default:
					include = !option.isDraft;
					break;
			}
			if (include) {
				filtered.push(option);
			}
		}
		return filtered;
	};

	Dashboard.prototype.applyEventFilter = function () {
		this.filteredEventOptions = this.filterEventOptions(this.eventOptions, this.state.filter);
	};

	Dashboard.prototype.ensureSelectedEvent = function () {
		var options = this.filteredEventOptions || [];
		if (!options.length) {
			this.state.eventId = null;
			return;
		}
		if (this.state.eventId !== null) {
			for (var i = 0; i < options.length; i += 1) {
				if (options[i].id === this.state.eventId) {
					return;
				}
			}
		}
		this.state.eventId = options[0].id;
	};

	Dashboard.prototype.computeEventStats = function () {
		var stats = {
			all: 0,
			assigned: 0,
			upcoming: 0,
			past: 0,
			draft: 0
		};
		var options = this.eventOptions || [];
		for (var i = 0; i < options.length; i += 1) {
			var option = options[i];
			if (!option) {
				continue;
			}
			if (option.isDraft) {
				stats.draft += 1;
				continue;
			}
			stats.all += 1;
			if (option.assigned) {
				stats.assigned += 1;
			}
			if (option.isUpcoming) {
				stats.upcoming += 1;
			}
			if (option.isPast) {
				stats.past += 1;
			}
		}
		return stats;
	};

	Dashboard.prototype.renderEventTabs = function () {
		if (!this.$eventTabs.length) {
			return;
		}
		var stats = this.computeEventStats();
		var labels = {
			all: this.translate('eventTabAll', 'Tous'),
			assigned: this.translate('eventTabAssigned', 'Assignés à moi'),
			upcoming: this.translate('eventTabUpcoming', 'À venir'),
			past: this.translate('eventTabPast', 'Passés'),
			draft: this.translate('eventTabDraft', 'Brouillons')
		};
		this.$eventTabs.find('[data-filter]').each(function () {
			var $tab = $(this);
			var key = $tab.attr('data-filter');
			var count = stats.hasOwnProperty(key) ? stats[key] : 0;
			var $count = $tab.find('[data-role="tab-count"]');
			if ($count.length) {
				$count.text(count);
			}
			var $label = $tab.find('.mj-animateur-dashboard__tab-label');
			if ($label.length && labels[key]) {
				$label.text(labels[key]);
			}
			if (!$tab.find('.mj-animateur-dashboard__tab-label').length && labels[key]) {
				$tab.append('<span class="mj-animateur-dashboard__tab-label">' + escapeHtml(labels[key]) + '</span>');
			}
			$tab.toggleClass('is-empty', count === 0);
		});
		this.updateEventFilterUi();
	};

	Dashboard.prototype.updateEventFilterUi = function () {
		if (!this.$eventTabs.length) {
			return;
		}
		var active = this.state.filter;
		this.$eventTabs.find('[data-filter]').each(function () {
			var $tab = $(this);
			var key = $tab.attr('data-filter');
			var isActive = key === active;
			$tab.toggleClass('is-active', isActive);
			$tab.attr('aria-pressed', isActive ? 'true' : 'false');
		});
	};

	Dashboard.prototype.setEventFilter = function (filter) {
		if (!this.isValidFilter(filter)) {
			filter = 'all';
		}
		if (this.state.filter === filter) {
			return;
		}
		this.state.filter = filter;
		this.refreshEventSelect();
		this.updateViewAllButton();
		this.onCurrentEventChanged();
	};

		Dashboard.prototype.onCurrentEventChanged = function () {
			var self = this;
			var eventId = this.state.eventId;
			this.clearFeedback();
			this.updateEventSelectionUi();
			this.refreshOccurrences();
			this.renderParticipants();
			var isAssigned = this.isAssignedEvent(eventId);
			this.updateMemberPickerState(isAssigned);
			if (eventId === null || !isAssigned) {
				return;
			}
			this.ensureEventLoaded(eventId).fail(function (message) {
				self.showFeedback('attendance', 'error', message || self.translate('eventLoadError', "Impossible de charger cet événement."));
			});
		};

	Dashboard.prototype.onEventTabClick = function ($tab) {
		if (!$tab || !$tab.length) {
			return;
		}
		var filter = String($tab.attr('data-filter') || '');
		if (!filter) {
			return;
		}
		this.setEventFilter(filter);
	};

	Dashboard.prototype.renderEventCards = function () {
		if (!this.$eventTrack.length) {
			return;
		}

		var options = this.filteredEventOptions || [];
		if (!options.length) {
			this.$eventTrack.html('<div class="mj-animateur-dashboard__event-empty" data-role="event-empty">' + escapeHtml(this.translate('eventTabEmpty', 'Aucun événement trouvé')) + '</div>');
			return;
		}

		var html = '';
		for (var i = 0; i < options.length; i += 1) {
			html += this.buildEventCardHtml(options[i]);
		}
		this.$eventTrack.html(html);
	};

	Dashboard.prototype.buildEventCardHtml = function (option) {
		if (!option) {
			return '';
		}
		var classes = ['mj-animateur-dashboard__event-card'];
		if (!option.assigned) {
			classes.push('is-unassigned');
		}
		if (this.state.eventId !== null && option.id === this.state.eventId) {
			classes.push('is-selected');
		}
		if (option.isUpcoming) {
			classes.push('is-upcoming');
		}
		if (option.isPast) {
			classes.push('is-past');
		}
		if (option.isDraft) {
			classes.push('is-draft');
		}

		var badges = '';
		var assignmentLabel = option.assigned ? this.translate('eventCardAssigned', 'Assigné') : this.translate('eventCardUnassigned', 'Non assigné');
		var assignmentModifier = option.assigned ? 'assigned' : 'unassigned';
		var assignmentTitle = '';
		var assignmentActionable = false;
		if (option.assigned && this.releaseEnabled) {
			assignmentTitle = this.translate('releasePrompt', 'Me désassigner de cet événement');
			assignmentActionable = true;
		} else if (!option.assigned && this.claimEnabled) {
			assignmentTitle = this.translate('claimPrompt', "M'attribuer cet événement");
			assignmentActionable = true;
		}

		if (assignmentActionable) {
			badges += '<span class="mj-animateur-dashboard__event-card-badge mj-animateur-dashboard__event-card-badge--assignment mj-animateur-dashboard__event-card-badge--' + escapeHtml(assignmentModifier) + ' is-actionable" data-role="assignment-toggle" data-state="' + (option.assigned ? 'assigned' : 'unassigned') + '" role="button" tabindex="0"' + (assignmentTitle ? ' title="' + escapeHtml(assignmentTitle) + '"' : '') + ' aria-pressed="' + (option.assigned ? 'true' : 'false') + '">' + escapeHtml(assignmentLabel) + '</span>';
		} else {
			badges += '<span class="mj-animateur-dashboard__event-card-badge mj-animateur-dashboard__event-card-badge--assignment mj-animateur-dashboard__event-card-badge--' + escapeHtml(assignmentModifier) + '" data-state="' + (option.assigned ? 'assigned' : 'unassigned') + '">' + escapeHtml(assignmentLabel) + '</span>';
		}
		if (option.typeLabel) {
			badges += '<span class="mj-animateur-dashboard__event-card-badge mj-animateur-dashboard__event-card-badge--type">' + escapeHtml(option.typeLabel) + '</span>';
		}
		if (option.statusLabel) {
			badges += '<span class="mj-animateur-dashboard__event-card-badge mj-animateur-dashboard__event-card-badge--status">' + escapeHtml(option.statusLabel) + '</span>';
		}

		var coverHtml = '';
		if (option.coverUrl) {
			coverHtml = '<img src="' + escapeHtml(option.coverUrl) + '" alt="' + escapeHtml(option.coverAlt || option.title) + '" loading="lazy" />';
		} else {
			coverHtml = '<div class="mj-animateur-dashboard__event-card-placeholder" aria-hidden="true"></div>';
		}

		var metaParts = [];
		if (option.nextLabel) {
			metaParts.push('<span class="mj-animateur-dashboard__event-card-meta-item mj-animateur-dashboard__event-card-meta-item--next">' + escapeHtml(option.nextLabel) + '</span>');
		} else if (option.dateLabel) {
			metaParts.push('<span class="mj-animateur-dashboard__event-card-meta-item">' + escapeHtml(option.dateLabel) + '</span>');
		}
		if (option.locationLabel) {
			metaParts.push('<span class="mj-animateur-dashboard__event-card-meta-item">' + escapeHtml(option.locationLabel) + '</span>');
		}
		if (option.priceLabel) {
			metaParts.push('<span class="mj-animateur-dashboard__event-card-meta-item">' + escapeHtml(option.priceLabel) + '</span>');
		}

		var metaHtml = metaParts.length ? '<div class="mj-animateur-dashboard__event-card-meta">' + metaParts.join('') + '</div>' : '';

		var participantsLabel = option.participantsLabel || this.translate('eventCardParticipants', 'Participants : %d').replace('%d', option.participantsCount || 0);

		var linkHtml = '';
		if (option.permalink) {
			linkHtml = '<a href="' + escapeHtml(option.permalink) + '" class="mj-animateur-dashboard__event-card-link" data-role="event-link" target="_blank" rel="noopener">' + escapeHtml(this.translate('eventCardViewLink', 'Détails')) + '</a>';
		}

		return '' +
			'<button type="button" class="' + classes.join(' ') + '" data-role="event-card" data-event-id="' + escapeHtml(option.id) + '" data-assigned="' + (option.assigned ? '1' : '0') + '">' +
				'<div class="mj-animateur-dashboard__event-card-media">' + coverHtml + '</div>' +
				'<div class="mj-animateur-dashboard__event-card-body">' +
					'<div class="mj-animateur-dashboard__event-card-badges">' + badges + '</div>' +
					'<h3 class="mj-animateur-dashboard__event-card-title">' + escapeHtml(option.title) + '</h3>' +
					metaHtml +
					'<div class="mj-animateur-dashboard__event-card-footer">' +
						'<span class="mj-animateur-dashboard__event-card-count">' + escapeHtml(participantsLabel) + '</span>' +
						linkHtml +
					'</div>' +
				'</div>' +
			'</button>';
	};

	Dashboard.prototype.refreshEventSelect = function () {
		this.eventOptions = this.buildEventOptions();
		this.applyEventFilter();
		this.viewAllActive = this.state.filter === 'all' && this.viewAllEnabled && this.allEvents.length > 0;
		this.renderEventTabs();
		this.ensureSelectedEvent();

		var options = this.filteredEventOptions;
		this.eventOptionLookup = {};

		var html = '';
		for (var i = 0; i < options.length; i += 1) {
			var option = options[i];
			this.eventOptionLookup[option.id] = option;
			if (this.$eventSelect.length) {
				var isSelected = this.state.eventId !== null && option.id === this.state.eventId;
				html += '<option value="' + escapeHtml(option.id) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(this.formatEventSelectLabel(option)) + '</option>';
			}
		}

		if (!options.length && this.$eventSelect.length) {
			html = '<option value="">' + escapeHtml(this.translate('noEvents', 'Aucun événement disponible')) + '</option>';
		}

		if (this.$eventSelect.length) {
			this.$eventSelect.html(html);
			this.$eventSelect.prop('disabled', options.length === 0);
			if (this.state.eventId !== null) {
				this.$eventSelect.val(String(this.state.eventId));
			}
		}

		this.renderEventCards();
		this.updateEventSelectionUi();
		this.updateMemberPickerState(this.isAssignedEvent(this.state.eventId));
		this.updateEventNavState();
	};

	Dashboard.prototype.updateEventSelectionUi = function () {
		if (this.$eventSelect.length) {
			if (this.state.eventId !== null && this.state.eventId !== undefined) {
				this.$eventSelect.val(String(this.state.eventId));
			} else {
				this.$eventSelect.val('');
			}
		}

		if (!this.$eventTrack.length) {
			return;
		}

		var self = this;
		this.$eventTrack.find('[data-role="event-card"]').each(function () {
			var $card = $(this);
			var cardId = toInt($card.attr('data-event-id'));
			var isSelected = self.state.eventId !== null && cardId !== null && cardId === self.state.eventId;
			$card.toggleClass('is-selected', !!isSelected);
		});

		this.updateEventNavState();
	};

	Dashboard.prototype.onEventCardClick = function ($card) {
		if (!$card || !$card.length) {
			return;
		}

		var eventId = toInt($card.attr('data-event-id'));
		if (eventId === null) {
			return;
		}

		if (this.state.eventId !== null && eventId === this.state.eventId) {
			return;
		}

		this.state.eventId = eventId;
		this.onCurrentEventChanged();
	};

	Dashboard.prototype.scrollEventTrack = function (direction) {
		if (!this.$eventTrack.length) {
			return;
		}

		var node = this.$eventTrack.get(0);
		if (!node) {
			return;
		}

		var distance = this.$eventTrack.outerWidth() || 0;
		if (!distance) {
			distance = 240;
		}

		var current = node.scrollLeft;
		var target = direction === 'prev' ? current - distance : current + distance;
		var self = this;
		this.$eventTrack.stop(true, false).animate({ scrollLeft: target }, 250, function () {
			self.updateEventNavState();
		});
	};

	Dashboard.prototype.updateEventNavState = function () {
		if (!this.$eventTrack.length) {
			return;
		}

		var hasCards = this.$eventTrack.find('[data-role="event-card"]').length > 0;
		var node = this.$eventTrack.get(0);
		var maxScroll = node ? Math.max(0, node.scrollWidth - node.clientWidth) : 0;
		var scrollLeft = node ? node.scrollLeft : 0;
		var atStart = !hasCards || scrollLeft <= 1;
		var atEnd = !hasCards || scrollLeft >= maxScroll - 1;

		if (this.$eventNavPrev && this.$eventNavPrev.length) {
			this.$eventNavPrev.prop('disabled', atStart);
			this.$eventNavPrev.toggleClass('is-disabled', atStart);
		}

		if (this.$eventNavNext && this.$eventNavNext.length) {
			this.$eventNavNext.prop('disabled', atEnd);
			this.$eventNavNext.toggleClass('is-disabled', atEnd);
		}
	};

	Dashboard.prototype.occurrenceExists = function (occurrences, value) {
		if (!Array.isArray(occurrences)) {
			return false;
		}
		for (var i = 0; i < occurrences.length; i += 1) {
			if (occurrences[i].start === value) {
				return true;
			}
		}
		return false;
	};

	Dashboard.prototype.refreshOccurrences = function () {
		var event = this.getEvent(this.state.eventId);

		if (!this.$occurrenceSelect.length) {
			if (!event) {
				this.state.occurrence = null;
				this.toggleOccurrenceUi(false);
				this.renderAgenda();
				return;
			}
			var list = Array.isArray(event.occurrences) ? event.occurrences : [];
			if (!list.length) {
				this.state.occurrence = null;
				this.toggleOccurrenceUi(false);
				this.renderAgenda();
				return;
			}
			if (!this.state.occurrence || !this.occurrenceExists(list, this.state.occurrence)) {
				this.state.occurrence = event.defaultOccurrence || list[0].start || null;
			}
			this.toggleOccurrenceUi(true);
			this.renderAgenda();
			return;
		}

		if (!event) {
			this.state.occurrence = null;
			this.$occurrenceSelect.html('<option value="">' + escapeHtml(this.translate('noOccurrences', 'Aucune occurrence disponible')) + '</option>');
			this.$occurrenceSelect.prop('disabled', true);
			this.toggleOccurrenceUi(false);
			this.renderAgenda();
			return;
		}

		var occurrences = Array.isArray(event.occurrences) ? event.occurrences : [];
		if (!occurrences.length) {
			this.state.occurrence = null;
			this.$occurrenceSelect.html('<option value="">' + escapeHtml(this.translate('noOccurrences', 'Aucune occurrence disponible')) + '</option>');
			this.$occurrenceSelect.prop('disabled', true);
			this.toggleOccurrenceUi(false);
			return;
		}

		var selected = this.state.occurrence;
		if (!selected || !this.occurrenceExists(occurrences, selected)) {
			selected = event.defaultOccurrence || occurrences[0].start || null;
		}

		var optionsHtml = '';
		for (var i = 0; i < occurrences.length; i += 1) {
			var occurrence = occurrences[i];
			var value = occurrence.start || '';
			var isSelected = selected !== null && value === selected;
			optionsHtml += '<option value="' + escapeHtml(value) + '"' + (isSelected ? ' selected' : '') + '>' + escapeHtml(occurrence.label || value || '-') + '</option>';
		}

		this.state.occurrence = selected;
		this.$occurrenceSelect.html(optionsHtml);
		this.$occurrenceSelect.prop('disabled', false);
		if (selected !== null) {
			this.$occurrenceSelect.val(String(selected));
		}
		this.toggleOccurrenceUi(true);

		this.renderAgenda();
	};

	Dashboard.prototype.renderAgenda = function () {
		if (!this.$agendaTrack.length) {
			return;
		}

		var event = this.getEvent(this.state.eventId);
		var occurrences = event && Array.isArray(event.occurrences) ? event.occurrences : [];
		this.toggleOccurrenceUi(occurrences.length > 0);

		if (!occurrences.length) {
			this.$agendaTrack.html('<div class="mj-animateur-dashboard__agenda-empty" data-role="agenda-empty">' + escapeHtml(this.translate('agendaEmpty', 'Aucune occurrence à afficher.')) + '</div>');
			this.updateAgendaSelection();
			this.updateAgendaNavState();
			return;
		}

		var html = '';
		for (var i = 0; i < occurrences.length; i += 1) {
			html += this.buildAgendaItemHtml(occurrences[i]);
		}

		this.$agendaTrack.html(html);
		this.updateAgendaSelection();
		this.updateAgendaNavState();
	};

	Dashboard.prototype.buildAgendaItemHtml = function (occurrence) {
		if (!occurrence) {
			return '';
		}

		var start = occurrence.start || '';
		if (start === '') {
			return '';
		}

		var classes = ['mj-animateur-dashboard__agenda-item'];
		if (occurrence.isPast) {
			classes.push('is-past');
		}
		if (occurrence.isToday) {
			classes.push('is-today');
		}
		if (occurrence.isNext) {
			classes.push('is-next');
		}
		if (this.state.occurrence !== null && start === this.state.occurrence) {
			classes.push('is-selected');
		}

		var badges = '';
		if (occurrence.isToday) {
			badges += '<span class="mj-animateur-dashboard__agenda-item-badge mj-animateur-dashboard__agenda-item-badge--today">' + escapeHtml(this.translate('agendaTodayBadge', "Aujourd'hui")) + '</span>';
		}
		if (occurrence.isNext) {
			badges += '<span class="mj-animateur-dashboard__agenda-item-badge mj-animateur-dashboard__agenda-item-badge--next">' + escapeHtml(this.translate('agendaNextBadge', 'Prochaine')) + '</span>';
		}

		var summary = occurrence.summary ? String(occurrence.summary) : '';
		var label = occurrence.label ? String(occurrence.label) : start;

		return '' +
			'<button type="button" class="' + classes.join(' ') + '" data-role="agenda-item" data-occurrence="' + escapeHtml(start) + '">' +
				'<span class="mj-animateur-dashboard__agenda-item-label">' + escapeHtml(label) + '</span>' +
				(badges ? '<span class="mj-animateur-dashboard__agenda-item-badges">' + badges + '</span>' : '') +
				(summary ? '<span class="mj-animateur-dashboard__agenda-item-summary">' + escapeHtml(summary) + '</span>' : '') +
			'</button>';
	};

	Dashboard.prototype.updateAgendaSelection = function () {
		if (!this.$agendaTrack.length) {
			return;
		}

		var occurrence = this.state.occurrence;
		var selectionFound = false;

		this.$agendaTrack.find('[data-role="agenda-item"]').each(function () {
			var $item = $(this);
			var value = String($item.attr('data-occurrence') || '');
			var isSelected = occurrence !== null && occurrence !== undefined && occurrence !== '' && value === occurrence;
			$item.toggleClass('is-selected', !!isSelected);
			if (isSelected) {
				selectionFound = true;
			}
		});

		if (!selectionFound) {
			var $first = this.$agendaTrack.find('[data-role="agenda-item"]').first();
			if ($first.length) {
				$first.addClass('is-selected');
				occurrence = String($first.attr('data-occurrence') || '');
				this.state.occurrence = occurrence || null;
			}
		}

		if (this.$occurrenceSelect.length) {
			if (occurrence !== null && occurrence !== undefined && occurrence !== '') {
				this.$occurrenceSelect.val(String(occurrence));
			} else {
				this.$occurrenceSelect.val('');
			}
		}

		this.ensureAgendaItemVisible();
		this.updateAgendaNavState();
	};

	Dashboard.prototype.ensureAgendaItemVisible = function () {
		if (!this.$agendaTrack.length) {
			return;
		}

		var $selected = this.$agendaTrack.find('[data-role="agenda-item"].is-selected').first();
		if (!$selected.length) {
			return;
		}

		var trackLeft = this.$agendaTrack.scrollLeft();
		var trackWidth = this.$agendaTrack.innerWidth();
		var itemLeft = $selected.position().left + trackLeft;
		var itemWidth = $selected.outerWidth(true);
		var itemRight = itemLeft + itemWidth;
		var trackRight = trackLeft + trackWidth;

		if (itemLeft < trackLeft) {
			this.$agendaTrack.scrollLeft(itemLeft);
		} else if (itemRight > trackRight) {
			this.$agendaTrack.scrollLeft(itemRight - trackWidth);
		}
	};

	Dashboard.prototype.onAgendaItemClick = function ($item) {
		if (!$item || !$item.length) {
			return;
		}

		var occurrence = String($item.attr('data-occurrence') || '');
		if (occurrence === '') {
			return;
		}

		if (this.state.occurrence === occurrence) {
			return;
		}

		this.state.occurrence = occurrence;
		if (this.$occurrenceSelect.length) {
			this.$occurrenceSelect.val(occurrence);
		}

		this.updateAgendaSelection();
		this.renderParticipants();
		this.clearFeedback();
	};

	Dashboard.prototype.scrollAgenda = function (direction) {
		if (!this.$agendaTrack.length) {
			return;
		}

		var node = this.$agendaTrack.get(0);
		if (!node) {
			return;
		}

		var distance = this.$agendaTrack.outerWidth() || 0;
		if (!distance) {
			distance = 240;
		}

		var current = node.scrollLeft;
		var target = direction === 'prev' ? current - distance : current + distance;
		var self = this;
		this.$agendaTrack.stop(true, false).animate({ scrollLeft: target }, 250, function () {
			self.updateAgendaNavState();
		});
	};

	Dashboard.prototype.updateAgendaNavState = function () {
		if (!this.$agendaTrack.length) {
			return;
		}

		var hasItems = this.$agendaTrack.find('[data-role="agenda-item"]').length > 0;
		var node = this.$agendaTrack.get(0);
		var maxScroll = node ? Math.max(0, node.scrollWidth - node.clientWidth) : 0;
		var scrollLeft = node ? node.scrollLeft : 0;
		var atStart = !hasItems || scrollLeft <= 1;
		var atEnd = !hasItems || scrollLeft >= maxScroll - 1;

		if (this.$agendaNavPrev && this.$agendaNavPrev.length) {
			this.$agendaNavPrev.prop('disabled', atStart);
			this.$agendaNavPrev.toggleClass('is-disabled', atStart);
		}

		if (this.$agendaNavNext && this.$agendaNavNext.length) {
			this.$agendaNavNext.prop('disabled', atEnd);
			this.$agendaNavNext.toggleClass('is-disabled', atEnd);
		}
	};

	Dashboard.prototype.getCurrentEvent = function () {
		return this.getEvent(this.state.eventId);
	};

	Dashboard.prototype.isAssignedEvent = function (eventId) {
		if (eventId === null) {
			return false;
		}
		var id = toInt(eventId);
		if (id === null) {
			return false;
		}
		if (this.assignedLookup[id]) {
			return true;
		}
		var event = this.eventsById[id];
		if (event && Object.prototype.hasOwnProperty.call(event, 'isAssigned')) {
			return !!event.isAssigned;
		}
		var option = this.getEventOption(id);
		if (option && option.assigned) {
			return true;
		}
		return false;
	};

	Dashboard.prototype.renderParticipants = function () {
		var event = this.getCurrentEvent();
		var isAssigned = !!event;
		var self = this;

		this.toggleAssignedState(isAssigned);
		this.updateSummary(event);

		if (!this.$tableBody.length) {
			return;
		}

		this.$tableBody.off('click.mjPaymentLink', '[data-role="payment-link"]');
		this.$tableBody.on('click.mjPaymentLink', '[data-role="payment-link"]', function (event) {
			event.preventDefault();
			self.onPaymentLinkClick($(this));
		});
		if (!isAssigned) {
			this.$tableBody.empty();
			if (this.$noData.length) {
				this.$noData.hide();
			}
			this.updateSmsState([]);
			return;
		}

		var occurrence = this.state.occurrence;
		if (!occurrence && this.$occurrenceSelect.length) {
			occurrence = this.$occurrenceSelect.val() ? String(this.$occurrenceSelect.val()) : null;
			this.state.occurrence = occurrence;
		}

		var participants = Array.isArray(event.participants) ? event.participants : [];
		var visibleParticipants = this.getVisibleParticipants(participants, occurrence);
		var rows = '';
		for (var i = 0; i < visibleParticipants.length; i += 1) {
			rows += this.buildParticipantRow(visibleParticipants[i], occurrence);
		}

		this.$tableBody.html(rows);

		var hasParticipants = visibleParticipants.length > 0;
		if (this.$noData.length) {
			this.$noData.toggle(!hasParticipants);
		}

		this.updateSmsState(visibleParticipants);
	};

	Dashboard.prototype.getEventPriceValue = function (event) {
		if (!event) {
			return null;
		}
		if (Object.prototype.hasOwnProperty.call(event, 'price')) {
			var directPrice = Number(event.price);
			if (isFinite(directPrice)) {
				return directPrice;
			}
		}
		if (event.meta && Object.prototype.hasOwnProperty.call(event.meta, 'price')) {
			var metaPrice = Number(event.meta.price);
			if (isFinite(metaPrice)) {
				return metaPrice;
			}
		}
		return null;
	};

	Dashboard.prototype.buildParticipantRow = function (participant, occurrence) {
		var currentEvent = this.getCurrentEvent();
		var priceValue = this.getEventPriceValue(currentEvent);
		var isFreeEvent = priceValue !== null ? priceValue <= 0 : false;

		var memberId = toInt(participant.memberId) || 0;
		var registrationId = toInt(participant.registrationId) || 0;
		var attendanceMap = participant.attendance || {};
		var status = occurrence && attendanceMap[occurrence] ? String(attendanceMap[occurrence]) : '';
		if (status === '' || status === 'none') {
			status = 'pending';
		}

		var name = participant.fullName || ('#' + memberId);
		var avatar = participant.avatar || {};
		var avatarAlt = avatar.alt || name;
		var avatarHtml = '';
		if (avatar.url) {
			avatarHtml = '<span class="mj-animateur-dashboard__participant-avatar"><img src="' + escapeHtml(avatar.url) + '" alt="' + escapeHtml(avatarAlt || name) + '" loading="lazy" decoding="async"></span>';
		} else {
			var initials = avatar.initials || '';
			if (!initials && name) {
				initials = name.charAt(0).toUpperCase();
			}
			avatarHtml = '<span class="mj-animateur-dashboard__participant-avatar mj-animateur-dashboard__participant-avatar--placeholder" aria-hidden="true">' +
				(initials ? '<span class="mj-animateur-dashboard__participant-avatar-initials">' + escapeHtml(initials) + '</span>' : '') +
			'</span>';
		}

		var metaHtml = this.buildContactCell(participant);
		var mainTextHtml = '<span class="mj-animateur-dashboard__participant-name">' + escapeHtml(name) + '</span>';
		if (metaHtml) {
			mainTextHtml += metaHtml;
		}

		var infoHtml = '<div class="mj-animateur-dashboard__participant-main">' +
			avatarHtml +
			'<div class="mj-animateur-dashboard__participant-main-text">' +
				mainTextHtml +
			'</div>' +
		'</div>';

		var attendanceHtml = '';
		if (this.settings.attendance) {
			attendanceHtml = this.buildAttendanceControl(memberId, registrationId, status);
		} else {
			attendanceHtml = '<div class="mj-animateur-dashboard__attendance-control" data-role="attendance-control">' +
				'<span class="mj-animateur-dashboard__attendance-label">' + escapeHtml(this.getStatusLabel(status)) + '</span>' +
			'</div>';
		}

		var paymentHtml = this.buildPaymentControl(participant, registrationId, isFreeEvent, currentEvent);
		var actionBlocks = [];
		actionBlocks.push('<div class="mj-animateur-dashboard__participant-action mj-animateur-dashboard__participant-action--attendance">' + attendanceHtml + '</div>');
		if (paymentHtml) {
			actionBlocks.push('<div class="mj-animateur-dashboard__participant-action mj-animateur-dashboard__participant-action--payment">' + paymentHtml + '</div>');
		}
		var messageHtml = this.buildMessageControl(participant);
		if (messageHtml) {
			actionBlocks.push('<div class="mj-animateur-dashboard__participant-action mj-animateur-dashboard__participant-action--message">' + messageHtml + '</div>');
		}
		var allowRemoval = this.canRemoveRegistrations && registrationId > 0;
		if (allowRemoval && !this.canEditMembers) {
			allowRemoval = currentEvent ? this.isAssignedEvent(currentEvent.id) : false;
		}
		if (allowRemoval) {
			var removeLabel = this.translate('registrationRemoveLabel', 'Supprimer la réservation');
			var removeButton = '<button type="button" class="mj-animateur-dashboard__participant-delete" data-role="registration-remove" title="' + escapeHtml(removeLabel) + '" aria-label="' + escapeHtml(removeLabel) + '"><span aria-hidden="true">&times;</span></button>';
			actionBlocks.push('<div class="mj-animateur-dashboard__participant-action mj-animateur-dashboard__participant-action--delete">' + removeButton + '</div>');
		}
		var actionsHtml = '<div class="mj-animateur-dashboard__participant-actions">' + actionBlocks.join('') + '</div>';

		return '' +
			'<tr data-member-id="' + escapeHtml(memberId) + '" data-registration-id="' + escapeHtml(registrationId) + '">' +
			'<td><div class="mj-animateur-dashboard__participant-cell">' + infoHtml + '</div></td>' +
			'<td>' + actionsHtml + '</td>' +
			'</tr>';
	};

	Dashboard.prototype.buildAttendanceControl = function (memberId, registrationId, status) {
		var normalized = status || 'pending';
		var statuses = [
			{ value: 'pending', modifier: 'pending' },
			{ value: 'present', modifier: 'present' },
			{ value: 'absent', modifier: 'absent' }
		];
		var buttons = '';
		for (var i = 0; i < statuses.length; i += 1) {
			var entry = statuses[i];
			var value = entry.value;
			var isActive = value === normalized;
			buttons += '<button type="button" class="mj-animateur-dashboard__attendance-option mj-animateur-dashboard__attendance-option--' + escapeHtml(entry.modifier) + (isActive ? ' is-active' : '') + '" data-role="attendance-option" data-status="' + escapeHtml(value) + '">' + escapeHtml(this.getStatusLabel(value)) + '</button>';
		}
		var infoText = this.getAttendanceInfoText(normalized);
		var infoClass = this.getAttendanceInfoClassName(normalized);
		var infoAttributes = 'class="mj-animateur-dashboard__attendance-info ' + escapeHtml(infoClass) + '" data-role="attendance-info" data-status="' + escapeHtml(normalized) + '"';
		if (infoText) {
			infoAttributes += ' aria-live="polite"';
		} else {
			infoAttributes += ' aria-hidden="true"';
		}

		return '' +
			'<div class="mj-animateur-dashboard__attendance-control" data-role="attendance-control">' +
			buttons +
			'<input type="hidden" data-role="attendance" value="' + escapeHtml(normalized) + '" data-member-id="' + escapeHtml(memberId) + '" data-registration-id="' + escapeHtml(registrationId) + '">' +
		//	'<span ' + infoAttributes + '>' + escapeHtml(infoText) + ' OUPS</span>' +
			'</div>';
	};

	Dashboard.prototype.buildPaymentControl = function (participant, registrationId, isFreeEvent, event) {
		if (isFreeEvent) {
			return '';
		}
		var payment = participant && participant.payment ? participant.payment : {};
		var status = this.normalizePaymentStatus(payment.status);
		var method = payment && payment.method ? String(payment.method) : '';
		var infoText = this.formatPaymentInfo(payment);
		var canToggle = registrationId > 0;
		var isPaid = status === 'paid';
		var isStripePayment = this.isStripePaymentMethod(method);
		var shouldLock = !canToggle || (isPaid && isStripePayment);
		var buttonLabel;

		if (shouldLock && isStripePayment && isPaid) {
			buttonLabel = this.getPaymentMethodLabel(method) || this.translate('paymentUnmarkButton', 'Annuler le paiement');
		} else {
			buttonLabel = isPaid ? this.translate('paymentUnmarkButton', 'Annuler le paiement') : this.translate('paymentMarkButton', 'Marquer payé');
		}

		var buttonAttributes = ' type="button" class="mj-animateur-dashboard__payment-toggle' + (isPaid ? ' is-paid' : '') + '" data-role="payment-toggle"';
		if (shouldLock) {
			buttonAttributes += ' disabled';
		}

		var lockedAttr = shouldLock ? ' data-locked="1"' : '';
		var toggleAttr = ' data-can-toggle="' + (canToggle ? '1' : '0') + '"';

		var linkHtml = '';
		if (this.canGeneratePaymentLink(participant, isFreeEvent, event)) {
			var linkLabel = this.translate('paymentLinkButton', 'Lien de paiement');
			var eventId = event && event.id ? toInt(event.id) : null;
			if (eventId === null || eventId <= 0) {
				eventId = this.state.eventId ? toInt(this.state.eventId) : null;
			}
			var memberId = toInt(participant && participant.memberId);
			linkHtml = '<button type="button" class="mj-animateur-dashboard__payment-link" data-role="payment-link" data-event-id="' + escapeHtml(eventId && eventId > 0 ? eventId : '') + '" data-registration-id="' + escapeHtml(registrationId || '') + '" data-member-id="' + escapeHtml(memberId || 0) + '">' + escapeHtml(linkLabel) + '</button>' +
				'<div class="mj-animateur-dashboard__payment-link-output" data-role="payment-link-output" aria-hidden="true"></div>';
		}

		return '' +
			'<div class="mj-animateur-dashboard__payment-control" data-role="payment-control" data-status="' + escapeHtml(status) + '"' + lockedAttr + toggleAttr + '>' +
			'<button' + buttonAttributes + ' aria-live="polite">' + escapeHtml(buttonLabel) + '</button>' +
			'<span class="mj-animateur-dashboard__payment-status" data-role="payment-status" aria-hidden="true"></span>' +
			'<span class="mj-animateur-dashboard__payment-info" data-role="payment-info"' + (infoText ? '' : ' aria-hidden="true"') + '>' + escapeHtml(infoText) + '</span>' +
			linkHtml +
			'</div>';
	};

	Dashboard.prototype.canGeneratePaymentLink = function (participant, isFreeEvent, event) {
		if (!this.paymentLinkEnabled || isFreeEvent) {
			return false;
		}
		if (!participant) {
			return false;
		}
		var registrationId = toInt(participant.registrationId);
		if (registrationId === null || registrationId <= 0) {
			return false;
		}
		if (!event || !event.id) {
			return false;
		}
		if (participant.canGeneratePaymentLink !== undefined && !participant.canGeneratePaymentLink) {
			return false;
		}
		var status = participant.registrationStatus ? String(participant.registrationStatus) : '';
		var blocked = ['waitlist', 'cancelled', 'annule', 'liste_attente'];
		if (status && blocked.indexOf(status) !== -1) {
			return false;
		}
		var paymentStatus = this.normalizePaymentStatus(participant.payment && participant.payment.status);
		return paymentStatus !== 'paid';
	};

	Dashboard.prototype.getParticipantByRegistrationId = function (event, registrationId) {
		if (!event || !Array.isArray(event.participants)) {
			return null;
		}
		var target = toInt(registrationId);
		if (target === null || target <= 0) {
			return null;
		}
		for (var i = 0; i < event.participants.length; i += 1) {
			var participant = event.participants[i];
			if (toInt(participant && participant.registrationId) === target) {
				return participant;
			}
		}
		return null;
	};

	Dashboard.prototype.isIndividualMessagingEnabled = function () {
		if (!this.settings) {
			return false;
		}
		if (Object.prototype.hasOwnProperty.call(this.settings, 'individualMessaging')) {
			return !!this.settings.individualMessaging;
		}
		return !!this.settings.sms;
	};

	Dashboard.prototype.canMessageParticipant = function (participant) {
		if (!this.isIndividualMessagingEnabled()) {
			return false;
		}
		if (!participant) {
			return false;
		}
		if (participant.smsAllowed) {
			return true;
		}
		return !!(participant.guardian && participant.guardian.smsAllowed);
	};

	Dashboard.prototype.buildMessageControl = function (participant) {
		if (!this.isIndividualMessagingEnabled()) {
			return '';
		}

		var memberId = toInt(participant && participant.memberId);
		if (memberId === null) {
			return '';
		}

		var canMessage = this.canMessageParticipant(participant);
		var placeholder = this.translate('messagePlaceholder', 'Votre message au participant');
		var toggleLabel = this.translate('messageToggleOpen', 'Contacter');
		var sendLabel = this.translate('messageSend', 'Envoyer');
		var cancelLabel = this.translate('messageCancel', 'Annuler');
		var noRecipient = this.translate('messageNoRecipient', 'Ce participant ne peut pas recevoir de SMS.');

		var attrs = ' class="mj-animateur-dashboard__participant-message" data-role="participant-message" data-member-id="' + escapeHtml(memberId) + '" data-can-message="' + (canMessage ? '1' : '0') + '"';

		var html = '<div' + attrs + '>' +
			'<button type="button" class="mj-animateur-dashboard__message-toggle" data-role="message-toggle"' + (canMessage ? '' : ' disabled') + '>' + escapeHtml(toggleLabel) + '</button>';

		if (canMessage) {
			html += '<div class="mj-animateur-dashboard__message-editor" data-role="message-editor" style="display:none;">' +
				'<textarea class="mj-animateur-dashboard__message-input" data-role="message-input" rows="3" placeholder="' + escapeHtml(placeholder) + '"></textarea>' +
				'<div class="mj-animateur-dashboard__message-actions">' +
					'<button type="button" class="mj-animateur-dashboard__message-send" data-role="message-send">' + escapeHtml(sendLabel) + '</button>' +
					'<button type="button" class="mj-animateur-dashboard__message-cancel" data-role="message-cancel">' + escapeHtml(cancelLabel) + '</button>' +
				'</div>' +
				'<span class="mj-animateur-dashboard__message-feedback" data-role="message-feedback"></span>' +
			'</div>';
		} else {
			html += '<span class="mj-animateur-dashboard__message-note" data-role="message-note">' + escapeHtml(noRecipient) + '</span>';
		}

		html += '</div>';
		return html;
	};

	Dashboard.prototype.closeAllParticipantMessages = function ($except) {
		if (!this.$tableBody.length) {
			return;
		}

		var self = this;
		this.$tableBody.find('[data-role="participant-message"].is-open').each(function () {
			var $container = $(this);
			if ($except && $container.is($except)) {
				return;
			}
			self.closeParticipantMessage($container, true);
		});
	};

	Dashboard.prototype.openParticipantMessage = function ($container) {
		if (!$container || !$container.length) {
			return;
		}
		if ($container.attr('data-can-message') !== '1') {
			return;
		}

		this.closeAllParticipantMessages($container);
		$container.addClass('is-open');
		var $editor = $container.find('[data-role="message-editor"]');
		if ($editor.length) {
			$editor.show();
		}

		var $toggle = $container.find('[data-role="message-toggle"]').first();
		if ($toggle.length) {
			$toggle.text(this.translate('messageToggleClose', 'Fermer'));
		}

		var $textarea = $container.find('[data-role="message-input"]').first();
		if ($textarea.length) {
			$textarea.trigger('focus');
		}
	};

	Dashboard.prototype.closeParticipantMessage = function ($container, preserveMessage) {
		if (!$container || !$container.length) {
			return;
		}

		$container.removeClass('is-open');
		var $editor = $container.find('[data-role="message-editor"]');
		if ($editor.length) {
			$editor.hide();
			if (!preserveMessage) {
				var $textarea = $editor.find('[data-role="message-input"]').first();
				if ($textarea.length) {
					$textarea.val('');
				}
			}
		}

		var $toggle = $container.find('[data-role="message-toggle"]').first();
		if ($toggle.length) {
			$toggle.text(this.translate('messageToggleOpen', 'Contacter'));
		}

		this.clearMessageFeedback($container);
	};

	Dashboard.prototype.clearMessageFeedback = function ($container) {
		if (!$container || !$container.length) {
			return;
		}

		var $feedback = $container.find('[data-role="message-feedback"]').first();
		if ($feedback.length) {
			$feedback.removeClass('is-error is-success').text('');
		}
	};

	Dashboard.prototype.showMessageFeedback = function ($container, level, message) {
		if (!$container || !$container.length) {
			return;
		}

		var $feedback = $container.find('[data-role="message-feedback"]').first();
		if (!$feedback.length) {
			return;
		}

		$feedback.removeClass('is-error is-success');
		if (level === 'error') {
			$feedback.addClass('is-error');
		} else if (level === 'success') {
			$feedback.addClass('is-success');
		}
		$feedback.text(message || '');
	};

	Dashboard.prototype.setMessageLoading = function ($container, isLoading) {
		if (!$container || !$container.length) {
			return;
		}

		$container.toggleClass('is-loading', !!isLoading);
		var $send = $container.find('[data-role="message-send"]').first();
		var $cancel = $container.find('[data-role="message-cancel"]').first();
		if ($send.length) {
			$send.prop('disabled', !!isLoading);
		}
		if ($cancel.length) {
			$cancel.prop('disabled', !!isLoading);
		}
	};

	Dashboard.prototype.onMessageToggleClick = function ($button) {
		if (!this.isIndividualMessagingEnabled() || !$button || !$button.length) {
			return;
		}

		if ($button.is(':disabled')) {
			return;
		}

		var $container = $button.closest('[data-role="participant-message"]');
		if (!$container.length) {
			return;
		}

		if ($container.hasClass('is-open')) {
			this.closeParticipantMessage($container, true);
		} else {
			this.openParticipantMessage($container);
		}
	};

	Dashboard.prototype.onMessageSendClick = function ($button) {
		if (!this.isIndividualMessagingEnabled() || !$button || !$button.length) {
			return;
		}

		var $container = $button.closest('[data-role="participant-message"]');
		if (!$container.length || $container.attr('data-can-message') !== '1') {
			return;
		}

		var memberId = toInt($container.attr('data-member-id'));
		if (memberId === null) {
			return;
		}

		var event = this.getCurrentEvent();
		if (!event) {
			this.showMessageFeedback($container, 'error', this.translate('notAssigned', 'Cet événement ne vous est pas attribué.'));
			return;
		}

		var $textarea = $container.find('[data-role="message-input"]').first();
		var message = $textarea.length ? String($textarea.val() || '').trim() : '';
		if (message === '') {
			this.showMessageFeedback($container, 'error', this.translate('smsEmpty', "Veuillez saisir un message avant l'envoi."));
			return;
		}

		this.clearMessageFeedback($container);
		this.setMessageLoading($container, true);

		var self = this;
		$.ajax({
			url: this.global.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: this.global.actions ? this.global.actions.sms : 'mj_member_animateur_send_sms',
				nonce: this.global.nonce || '',
				event_id: event.id,
				message: message,
				member_ids: JSON.stringify([memberId])
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				self.showMessageFeedback($container, 'error', self.translate('messageError', "Impossible d'envoyer le SMS au participant."));
				return;
			}

			var summary = response.data.summary;
			if (summary && typeof summary.sent === 'number' && summary.sent === 0) {
				self.showMessageFeedback($container, 'error', self.translate('messageNoRecipient', 'Ce participant ne peut pas recevoir de SMS.'));
				return;
			}

			var successMessage = response.data.message || self.translate('messageSuccess', 'SMS envoyé au participant.');
			self.showMessageFeedback($container, 'success', successMessage);
			if ($textarea.length) {
				$textarea.val('');
			}
		}).fail(function () {
			self.showMessageFeedback($container, 'error', self.translate('messageError', "Impossible d'envoyer le SMS au participant."));
		}).always(function () {
			self.setMessageLoading($container, false);
		});
	};

	Dashboard.prototype.onMessageCancelClick = function ($button) {
		if (!this.isIndividualMessagingEnabled() || !$button || !$button.length) {
			return;
		}

		var $container = $button.closest('[data-role="participant-message"]');
		if (!$container.length) {
			return;
		}

		this.closeParticipantMessage($container, false);
	};

	Dashboard.prototype.buildContactCell = function (participant) {
		var blocks = [];

		if (participant.email) {
			blocks.push('<a class="mj-animateur-dashboard__participant-contact" href="mailto:' + escapeHtml(participant.email) + '">' + escapeHtml(participant.email) + '</a>');
		}

		if (participant.phone) {
			blocks.push('<a class="mj-animateur-dashboard__participant-contact" href="tel:' + escapeHtml(participant.phone) + '">' + escapeHtml(participant.phone) + '</a>');
		}

		if (participant.guardian && participant.guardian.name) {
			var guardian = participant.guardian;
			var guardianParts = '<span class="mj-animateur-dashboard__participant-guardian">' + escapeHtml(guardian.name);
			if (guardian.phone) {
				guardianParts += ' &ndash; <a class="mj-animateur-dashboard__participant-contact" href="tel:' + escapeHtml(guardian.phone) + '">' + escapeHtml(guardian.phone) + '</a>';
			}
			guardianParts += '</span>';
			blocks.push(guardianParts);
		}

		if (!blocks.length) {
			return '';
		}

		return '<div class="mj-animateur-dashboard__participant-meta">' + blocks.join('<span class="mj-animateur-dashboard__participant-meta-separator">•</span>') + '</div>';
	};

	Dashboard.prototype.getStatusLabel = function (status) {
		var normalized = status || 'pending';
		if (this.statusLabels && this.statusLabels[normalized]) {
			return this.statusLabels[normalized];
		}
		return normalized;
	};

	Dashboard.prototype.getAttendanceInfoText = function (status) {
		var normalized = status || 'pending';
		if (normalized !== 'present' && normalized !== 'absent' && normalized !== 'pending') {
			normalized = 'pending';
		}
		var prefix = this.translate('attendanceInfoPrefix', 'Statut :');
		var label = this.getStatusLabel(normalized);
		if (!label) {
			label = this.translate('attendancePending', 'À confirmer');
		}
		return prefix ? prefix + ' ' + label : label;
	};

	Dashboard.prototype.getAttendanceInfoClassName = function (status) {
		var normalized = status || 'pending';
		if (normalized !== 'present' && normalized !== 'absent' && normalized !== 'pending') {
			normalized = 'pending';
		}
		return 'mj-animateur-dashboard__attendance-info--' + normalized;
	};

	Dashboard.prototype.normalizePaymentStatus = function (status) {
		var normalized = status ? String(status) : '';
		if (normalized === 'paid' || normalized === 'unpaid') {
			return normalized;
		}
		return 'unpaid';
	};

	Dashboard.prototype.getPaymentLabel = function (status) {
		var normalized = this.normalizePaymentStatus(status);
		if (this.paymentLabels && this.paymentLabels[normalized]) {
			return this.paymentLabels[normalized];
		}
		return normalized;
	};

		Dashboard.prototype.isStripePaymentMethod = function (method) {
			var normalized = method ? String(method) : '';
			return normalized.indexOf('stripe') === 0;
		};

		Dashboard.prototype.getPaymentMethodLabel = function (method) {
			var normalized = method ? String(method) : '';
			if (!normalized) {
				return '';
			}
			if (this.isStripePaymentMethod(normalized)) {
				return this.translate('paymentMethodStripe', 'Payé via Stripe');
			}
			if (normalized === 'cash') {
				return this.translate('paymentMethodCash', 'Payé en main propre');
			}
			return this.translate('paymentMethodOther', 'Paiement confirmé');
		};

	Dashboard.prototype.formatPaymentInfo = function (payment) {
		if (!payment) {
			return '';
		}
			var parts = [];
			var methodLabel = this.getPaymentMethodLabel(payment.method);
			if (methodLabel) {
				parts.push(methodLabel);
			}
			var detailParts = [];
			if (payment.recorded_by && payment.recorded_by.name) {
				detailParts.push(this.translate('paymentRecordedBy', 'Noté par %s').replace('%s', payment.recorded_by.name));
			}
			if (payment.recorded_at_label) {
				detailParts.push(this.translate('paymentRecordedAt', 'le %s').replace('%s', payment.recorded_at_label));
			}
			if (detailParts.length) {
				parts.push(detailParts.join(' '));
			}
			return parts.join(' - ');
	};

	Dashboard.prototype.updateSummary = function (event) {
		if (this.$eventDetails && this.$eventDetails.length) {
			if (event) {
				this.$eventDetails.removeAttr('hidden');
				this.$eventDetails.show();
			} else {
				this.$eventDetails.attr('hidden', 'hidden');
				this.$eventDetails.hide();
			}
		}

		if (!event) {
			if (this.$summary.length) {
				this.$summary.hide();
			}
			if (this.$total.length) {
				this.$total.text('');
			}
			if (this.$counts.length) {
				this.$counts.text('');
			}
			if (this.$eventDetailTitle && this.$eventDetailTitle.length) {
				this.$eventDetailTitle.text('');
			}
			if (this.$eventDetailMeta && this.$eventDetailMeta.length) {
				this.$eventDetailMeta.text('').hide();
			}
			if (this.$eventDetailConditions && this.$eventDetailConditions.length) {
				this.$eventDetailConditions.empty();
			}
			if (this.$eventDetailConditionsWrapper && this.$eventDetailConditionsWrapper.length) {
				this.$eventDetailConditionsWrapper.attr('hidden', 'hidden');
			}
			return;
		}

		var title = event.title ? String(event.title) : this.translate('eventUntitled', 'Événement');
		if (this.$eventDetailTitle && this.$eventDetailTitle.length) {
			this.$eventDetailTitle.text(title);
		}

		if (this.$eventDetailMeta && this.$eventDetailMeta.length) {
			var metaParts = [];
			if (event.meta) {
				if (event.meta.typeLabel) {
					metaParts.push(String(event.meta.typeLabel));
				}
				if (event.meta.dateLabel) {
					metaParts.push(String(event.meta.dateLabel));
				} else if (event.start) {
					metaParts.push(String(event.start));
				}
				if (event.meta.locationLabel) {
					metaParts.push(String(event.meta.locationLabel));
				} else if (event.locationLabel) {
					metaParts.push(String(event.locationLabel));
				}
			}
			var metaText = metaParts.join(' • ');
			if (metaText) {
				this.$eventDetailMeta.text(metaText).show();
			} else {
				this.$eventDetailMeta.text('').hide();
			}
		}

		if (this.$eventDetailConditions && this.$eventDetailConditions.length) {
			var conditions = Array.isArray(event.conditions) ? event.conditions : [];
			var conditionsHtml = '';
			for (var c = 0; c < conditions.length; c += 1) {
				var condition = conditions[c];
				if (condition === null || condition === undefined) {
					continue;
				}
				var conditionText = String(condition).trim();
				if (!conditionText) {
					continue;
				}
				conditionsHtml += '<li>' + escapeHtml(conditionText) + '</li>';
			}
			if (conditionsHtml) {
				this.$eventDetailConditions.html(conditionsHtml);
				if (this.$eventDetailConditionsWrapper && this.$eventDetailConditionsWrapper.length) {
					this.$eventDetailConditionsWrapper.removeAttr('hidden');
				}
			} else {
				this.$eventDetailConditions.empty();
				if (this.$eventDetailConditionsWrapper && this.$eventDetailConditionsWrapper.length) {
					this.$eventDetailConditionsWrapper.attr('hidden', 'hidden');
				}
			}
		}

		var total = event.counts && typeof event.counts.participants === 'number' ? event.counts.participants : (Array.isArray(event.participants) ? event.participants.length : 0);
		var occurrence = this.state.occurrence;
		var counts = { present: 0, absent: 0, pending: 0 };

		if (occurrence && Array.isArray(event.occurrences)) {
			for (var i = 0; i < event.occurrences.length; i += 1) {
				var occ = event.occurrences[i];
				if (occ.start === occurrence) {
					counts.present = occ.counts && occ.counts.present ? occ.counts.present : 0;
					counts.absent = occ.counts && occ.counts.absent ? occ.counts.absent : 0;
					counts.pending = occ.counts && occ.counts.pending ? occ.counts.pending : 0;
					break;
				}
			}
		}

		if (this.$total.length) {
			this.$total.text(this.translate('totalParticipants', 'Participants : ') + total);
		}

		if (this.$counts.length) {
			var segments = [];
			segments.push(this.getStatusLabel('present') + ' : ' + counts.present);
			segments.push(this.getStatusLabel('absent') + ' : ' + counts.absent);
			segments.push(this.getStatusLabel('pending') + ' : ' + counts.pending);
			this.$counts.text(segments.join(' | '));
		}

		if (this.$summary.length) {
			this.$summary.show();
		}
	};

	Dashboard.prototype.toggleAssignedState = function (isAssigned) {
		if (this.$unassignedNotice.length) {
			this.$unassignedNotice.toggle(!isAssigned);
		}

		if (this.$actionsWrapper.length) {
			this.$actionsWrapper.toggle(isAssigned && !!this.settings.attendance);
		}

		if (this.$smsWrapper.length) {
			this.$smsWrapper.toggle(isAssigned && !!this.settings.sms);
		}

		if (this.$smsButton.length) {
			this.$smsButton.prop('disabled', !isAssigned);
		}

		var pickerAssigned = this.isAssignedEvent(this.state.eventId);
		this.updateMemberPickerState(pickerAssigned);

		this.$root.toggleClass('mj-animateur-dashboard--view-all', this.viewAllActive);
	};

	Dashboard.prototype.updateViewAllButton = function () {
		if (!this.$viewAllToggle.length) {
			return;
		}

		var label = this.viewAllActive ? this.viewAllLabels.active : this.viewAllLabels.default;
		this.$viewAllToggle.text(label || '');
		this.$viewAllToggle.attr('aria-pressed', this.viewAllActive ? 'true' : 'false');
		this.$viewAllToggle.toggleClass('is-active', this.viewAllActive);
	};

	Dashboard.prototype.toggleViewAll = function () {
		if (!this.viewAllEnabled || this.viewAllMode !== 'toggle') {
			return;
		}

		var targetFilter = this.viewAllActive ? 'assigned' : 'all';
		this.setEventFilter(targetFilter);
		this.updateViewAllButton();
	};

	Dashboard.prototype.getSmsRecipients = function (participants) {
		var recipients = [];
		if (!Array.isArray(participants)) {
			return recipients;
		}

		for (var i = 0; i < participants.length; i += 1) {
			var participant = participants[i];
			var memberId = toInt(participant.memberId);
			if (memberId === null) {
				continue;
			}

			if (participant.smsAllowed) {
				recipients.push(memberId);
				continue;
			}

			if (participant.guardian && participant.guardian.smsAllowed && participant.guardian.id) {
				recipients.push(memberId);
			}
		}

		return recipients;
	};

	Dashboard.prototype.updateSmsState = function (participants) {
		if (!this.$smsButton.length) {
			return;
		}

		if (!this.settings.sms) {
			this.$smsButton.prop('disabled', true);
			return;
		}

		var recipients = this.getSmsRecipients(participants);
		this.$smsButton.prop('disabled', recipients.length === 0);
	};

		Dashboard.prototype.setRowRemoving = function ($row, isRemoving) {
			if (!$row || !$row.length) {
				return;
			}

			$row.toggleClass('is-removing', !!isRemoving);
			var $remove = $row.find('[data-role="registration-remove"]').first();
			if ($remove.length) {
				$remove.prop('disabled', !!isRemoving);
			}
		};

		Dashboard.prototype.onRegistrationRemoveClick = function ($button) {
			if (!$button || !$button.length) {
				return;
			}

			if (!this.canRemoveRegistrations || $button.is(':disabled')) {
				return;
			}

			var $row = $button.closest('tr');
			if (!$row.length) {
				return;
			}

			var registrationId = toInt($row.attr('data-registration-id'));
			if (registrationId === null || registrationId <= 0) {
				return;
			}

			var event = this.getCurrentEvent();
			if (!event) {
				this.showFeedback('attendance', 'error', this.translate('notAssigned', 'Cet événement ne vous est pas attribué.'));
				return;
			}

			var confirmMessage = this.translate('registrationRemoveConfirm', 'Voulez-vous retirer cette réservation ?');
			if (!window.confirm(confirmMessage)) {
				return;
			}

			this.clearFeedback();
			this.setRowRemoving($row, true);

			var self = this;
			$.ajax({
				url: this.global.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: this.registrationRemoveAction,
					nonce: this.global.nonce || '',
					event_id: event.id,
					registration_id: registrationId
				}
			}).done(function (response) {
				if (!response || !response.success) {
					var message = response && response.data && response.data.message ? String(response.data.message) : self.translate('registrationRemoveError', "Impossible de supprimer la réservation.");
					self.showFeedback('attendance', 'error', message);
					self.setRowRemoving($row, false);
					return;
				}

				var snapshot = response.data && response.data.event ? response.data.event : null;
				if (snapshot) {
					self.updateEventSnapshot(snapshot);
				} else {
					var removed = response.data && response.data.removed ? response.data.removed : null;
					var removedMemberId = removed && removed.memberId !== undefined ? toInt(removed.memberId) : null;
					self.removeParticipantFromEvent(event.id, registrationId, removedMemberId);
				}

				self.showFeedback('attendance', 'success', self.translate('registrationRemoveSuccess', 'Réservation supprimée.'));
			}).fail(function (jqXHR) {
				var message = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ? String(jqXHR.responseJSON.data.message) : self.translate('registrationRemoveError', "Impossible de supprimer la réservation.");
				self.showFeedback('attendance', 'error', message);
				self.setRowRemoving($row, false);
			});
		};

		Dashboard.prototype.removeParticipantFromEvent = function (eventId, registrationId, memberId) {
			var event = this.getEvent(eventId);
			if (!event || !Array.isArray(event.participants)) {
				this.renderParticipants();
				return;
			}

			var participants = event.participants;
			var normalizedRegistrationId = toInt(registrationId);
			var normalizedMemberId = toInt(memberId);
			var removedParticipant = null;

			for (var i = 0; i < participants.length; i += 1) {
				var participant = participants[i];
				var participantRegistrationId = toInt(participant.registrationId);
				var participantMemberId = toInt(participant.memberId);
				var matchesRegistration = normalizedRegistrationId !== null && normalizedRegistrationId > 0 && participantRegistrationId === normalizedRegistrationId;
				var matchesMember = normalizedRegistrationId === null && normalizedMemberId !== null && participantMemberId === normalizedMemberId;
				if (matchesRegistration || matchesMember) {
					removedParticipant = participants.splice(i, 1)[0];
					break;
				}
			}

			if (!removedParticipant) {
				this.renderParticipants();
				return;
			}

			this.recomputeEventCounts(event);

			var participantCount = participants.length;
			if (!event.counts) {
				event.counts = {};
			}
			event.counts.participants = participantCount;
			event.participantsCount = participantCount;
			event.meta = event.meta || {};
			event.meta.participantCount = participantCount;
			event.meta.participantCountLabel = this.translate('eventCardParticipants', 'Participants : %d').replace('%d', participantCount);

			var currentEventId = this.state.eventId;
			var currentOccurrence = this.state.occurrence;

			this.refreshEventSelect();
			this.state.eventId = currentEventId;
			this.state.occurrence = currentOccurrence;
			this.updateEventSelectionUi();
			this.refreshOccurrences();
			this.renderParticipants();

			var updatedEvent = this.getEvent(currentEventId);
			if (updatedEvent) {
				this.updateSummary(updatedEvent);
				var currentOccurrence = this.getCurrentOccurrence();
				this.updateSmsState(this.getVisibleParticipants(updatedEvent.participants || [], currentOccurrence));
			}

			this.updateViewAllButton();
		};

		Dashboard.prototype.recomputeEventCounts = function (event) {
			if (!event || !Array.isArray(event.occurrences)) {
				return;
			}

			var participants = Array.isArray(event.participants) ? event.participants : [];

			for (var i = 0; i < event.occurrences.length; i += 1) {
				var occurrence = event.occurrences[i];
				if (!occurrence || !occurrence.start) {
					continue;
				}

				var counts = { present: 0, absent: 0, pending: 0 };
				for (var j = 0; j < participants.length; j += 1) {
					var participant = participants[j];
					if (!this.participantMatchesOccurrence(participant, occurrence.start)) {
						continue;
					}
					var attendanceMap = participant && participant.attendance ? participant.attendance : {};
					var status = attendanceMap && Object.prototype.hasOwnProperty.call(attendanceMap, occurrence.start)
						? String(attendanceMap[occurrence.start])
						: 'pending';

					if (status !== 'present' && status !== 'absent') {
						status = 'pending';
					}

					counts[status] += 1;
				}

				occurrence.counts = counts;
			}
		};

	Dashboard.prototype.onPaymentToggleClick = function ($button) {
		if (!$button || !$button.length) {
			return;
		}

		if ($button.prop('disabled')) {
			return;
		}

		var $row = $button.closest('tr');
		if (!$row.length) {
			return;
		}

		var registrationId = toInt($row.attr('data-registration-id'));
		if (registrationId === null || registrationId <= 0) {
			return;
		}

		var event = this.getCurrentEvent();
		if (!event) {
			this.showFeedback('attendance', 'error', this.translate('paymentError', "Impossible de mettre à jour le paiement."));
			return;
		}

		var $control = $button.closest('[data-role="payment-control"]');
		if (!$control.length) {
			return;
		}
		if ($control.attr('data-locked') === '1') {
			return;
		}
		this.clearFeedback();
		this.setPaymentLoading($control, true);

		var self = this;
		$.ajax({
			url: this.global.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: this.global.actions ? this.global.actions.payment : 'mj_member_animateur_toggle_cash_payment',
				nonce: this.global.nonce || '',
				event_id: event.id,
				registration_id: registrationId
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data || !response.data.payment) {
				self.showFeedback('attendance', 'error', self.translate('paymentError', "Impossible de mettre à jour le paiement."));
				return;
			}

			var payment = response.data.payment;
			self.applyPaymentUpdate(event, registrationId, payment);
			self.updatePaymentControl($control, payment);
			var messageKey = payment.status === 'paid' ? 'paymentMarked' : 'paymentReset';
			var fallbackMessage = payment.status === 'paid' ? 'Paiement confirmé.' : 'Paiement réinitialisé.';
			self.showFeedback('attendance', 'success', self.translate(messageKey, fallbackMessage));
		}).fail(function () {
			self.showFeedback('attendance', 'error', self.translate('paymentError', "Impossible de mettre à jour le paiement."));
		}).always(function () {
			self.setPaymentLoading($control, false);
		});
	};

	Dashboard.prototype.onPaymentLinkClick = function ($button) {
		if (!$button || !$button.length) {
			return;
		}

		var event = this.getCurrentEvent();
		if (!event) {
			this.showFeedback('attendance', 'error', this.translate('paymentLinkError', 'Impossible de générer le lien de paiement.'));
			return;
		}

		var registrationId = toInt($button.attr('data-registration-id'));
		if (registrationId === null || registrationId <= 0) {
			this.showFeedback('attendance', 'error', this.translate('paymentLinkError', 'Impossible de générer le lien de paiement.'));
			return;
		}

		var participant = this.getParticipantByRegistrationId(event, registrationId);
		if (!participant) {
			this.showFeedback('attendance', 'error', this.translate('paymentLinkError', 'Impossible de générer le lien de paiement.'));
			return;
		}
		var priceValue = this.getEventPriceValue(event);
		var isFreeEvent = priceValue !== null ? priceValue <= 0 : false;
		if (!this.canGeneratePaymentLink(participant, isFreeEvent, event)) {
			this.showFeedback('attendance', 'error', this.translate('paymentLinkError', 'Impossible de générer le lien de paiement.'));
			return;
		}

		if (!this.paymentLinkNonce || !this.global || !this.global.ajaxUrl) {
			this.showFeedback('attendance', 'error', this.translate('paymentLinkError', 'Impossible de générer le lien de paiement.'));
			return;
		}

		var eventIdAttr = toInt($button.attr('data-event-id'));
		var eventId = eventIdAttr && eventIdAttr > 0 ? eventIdAttr : event.id;
		var $control = $button.closest('[data-role="payment-control"]');
		var $output = $control.length ? $control.find('[data-role="payment-link-output"]').first() : $();
		if ($output.length) {
			$output.removeClass('is-error').text('');
			$output.attr('aria-hidden', 'true');
		}

		var originalLabel = $button.data('originalLabel');
		if (originalLabel === undefined) {
			originalLabel = $.trim($button.text());
			$button.data('originalLabel', originalLabel);
		}

		var generatingLabel = this.translate('paymentLinkGenerating', 'Génération du lien...');
		var errorMessage = this.translate('paymentLinkError', 'Impossible de générer le lien de paiement.');

		$button.prop('disabled', true).text(generatingLabel);

		var self = this;
		$.ajax({
			url: this.global.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: this.global.actions && this.global.actions.paymentLink ? this.global.actions.paymentLink : 'mj_member_animateur_generate_payment_link',
				nonce: this.paymentLinkNonce,
				event_id: eventId,
				registration_id: registrationId
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				self.renderPaymentLinkError($output, errorMessage);
				return;
			}

			self.renderPaymentLinkSuccess($output, response.data);
		}).fail(function () {
			self.renderPaymentLinkError($output, errorMessage);
		}).always(function () {
			var restoredLabel = $button.data('originalLabel') || self.translate('paymentLinkButton', 'Lien de paiement');
			$button.text(restoredLabel);
			var stillEligible = self.canGeneratePaymentLink(participant, isFreeEvent, event);
			$button.prop('disabled', !stillEligible);
		});
	};

	Dashboard.prototype.setPaymentLoading = function ($control, isLoading) {
		if (!$control || !$control.length) {
			return;
		}

		$control.toggleClass('is-loading', !!isLoading);
		var $button = $control.find('[data-role="payment-toggle"]');
		if ($control.attr('data-locked') === '1') {
			$button.prop('disabled', true);
			return;
		}
		$button.prop('disabled', !!isLoading);

		var $linkButton = $control.find('[data-role="payment-link"]');
		if ($linkButton.length) {
			if (isLoading) {
				$linkButton.prop('disabled', true);
			} else {
				var event = this.getCurrentEvent();
				var registrationId = toInt($control.closest('tr').attr('data-registration-id'));
				var priceValue = this.getEventPriceValue(event);
				var isFreeEvent = priceValue !== null ? priceValue <= 0 : false;
				var participant = this.getParticipantByRegistrationId(event, registrationId);
				$linkButton.prop('disabled', !this.canGeneratePaymentLink(participant, isFreeEvent, event));
			}
		}
	};

	Dashboard.prototype.renderPaymentLinkSuccess = function ($output, data) {
		var message = data && data.message ? String(data.message) : this.translate('paymentLinkSuccess', 'Lien prêt à être partagé.');
		var linkLabel = this.translate('paymentLinkOpen', 'Ouvrir');
		var amount = data && data.amount ? String(data.amount) : '';
		var linkUrl = data && data.checkout_url ? String(data.checkout_url) : '';
		var qrUrl = data && data.qr_url ? String(data.qr_url) : '';

		if ($output && $output.length) {
			$output.removeClass('is-error is-visible').empty();
			$output.attr('aria-hidden', 'true');
		}

		if (message) {
			this.showFeedback('attendance', 'success', message);
		}

		var modalShown = this.showPaymentLinkModal({
			message: message,
			amount: amount,
			linkLabel: linkLabel,
			linkUrl: linkUrl,
			qrUrl: qrUrl
		});

		if (!modalShown) {
			this.renderPaymentLinkFallback($output, {
				message: message,
				amount: amount,
				linkLabel: linkLabel,
				linkUrl: linkUrl,
				qrUrl: qrUrl
			});
		}
	};

	Dashboard.prototype.renderPaymentLinkFallback = function ($output, config) {
		if (!$output || !$output.length) {
			return;
		}

		var message = config && config.message ? String(config.message) : this.translate('paymentLinkSuccess', 'Lien prêt à être partagé.');
		var amount = config && config.amount ? String(config.amount) : '';
		var linkLabel = config && config.linkLabel ? String(config.linkLabel) : this.translate('paymentLinkOpen', 'Ouvrir');
		var linkUrl = config && config.linkUrl ? String(config.linkUrl) : '';
		var qrUrl = config && config.qrUrl ? String(config.qrUrl) : '';
		var amountTemplate = this.translate('paymentLinkAmount', 'Montant : %s €');
		var qrAlt = this.translate('paymentLinkQrAlt', 'QR code du paiement');
		var amountText = amount ? amountTemplate.replace('%s', amount) : '';
		var htmlParts = ['<div class="mj-animateur-dashboard__payment-link-card">'];

		if (message || amountText) {
			htmlParts.push('<div class="mj-animateur-dashboard__payment-link-info">');
			if (message) {
				htmlParts.push('<p class="mj-animateur-dashboard__payment-link-text">' + escapeHtml(message) + '</p>');
			}
			if (amountText) {
				htmlParts.push('<p class="mj-animateur-dashboard__payment-link-amount">' + escapeHtml(amountText) + '</p>');
			}
			htmlParts.push('</div>');
		}

		if (linkUrl) {
			var linkText = linkLabel ? linkLabel : linkUrl;
			htmlParts.push('<a class="mj-animateur-dashboard__payment-link-button" href="' + escapeHtml(linkUrl) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(linkText) + '</a>');
		}

		if (qrUrl) {
			htmlParts.push('<div class="mj-animateur-dashboard__payment-link-qr"><img src="' + escapeHtml(qrUrl) + '" alt="' + escapeHtml(qrAlt) + '" loading="lazy"></div>');
		}

		htmlParts.push('</div>');
		$output.html(htmlParts.join(''));
		$output.addClass('is-visible');
		$output.attr('aria-hidden', 'false');
	};

	Dashboard.prototype.renderPaymentLinkError = function ($output, message) {
		var text = message || this.translate('paymentLinkError', 'Impossible de générer le lien de paiement.');
		if ($output && $output.length) {
			$output.removeClass('is-visible').addClass('is-error');
			$output.text(text || '');
			$output.attr('aria-hidden', text ? 'false' : 'true');
		} else if (text) {
			this.showFeedback('attendance', 'error', text);
		}
		this.closePaymentLinkModal();
	};


	Dashboard.prototype.initPaymentLinkModal = function () {
		if (!this.paymentLinkEnabled) {
			return false;
		}

		if (this.paymentLinkModalInitialized && this.$paymentModal && this.$paymentModal.length) {
			return true;
		}

		var modalId = 'mj-animateur-payment-modal';
		var titleId = modalId + '-title';
		var $modal = $('#' + modalId);
		if (!$modal.length) {
			var title = escapeHtml(this.translate('paymentLinkModalTitle', 'Lien de paiement'));
			var closeLabel = escapeHtml(this.translate('paymentLinkModalClose', 'Fermer'));
			var html = '' +
				'<div class="mj-animateur-dashboard__payment-modal" id="' + modalId + '" data-role="payment-modal" hidden aria-hidden="true">' +
					'<div class="mj-animateur-dashboard__payment-modal-overlay" data-role="payment-modal-overlay"></div>' +
					'<div class="mj-animateur-dashboard__payment-modal-dialog" data-role="payment-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="' + titleId + '" tabindex="-1">' +
						'<button type="button" class="mj-animateur-dashboard__payment-modal-close" data-role="payment-modal-close" aria-label="' + closeLabel + '"><span aria-hidden="true">&times;</span></button>' +
						'<h3 class="mj-animateur-dashboard__payment-modal-title" id="' + titleId + '">' + title + '</h3>' +
						'<div class="mj-animateur-dashboard__payment-modal-body" data-role="payment-modal-body">' +
							'<p class="mj-animateur-dashboard__payment-modal-message" data-role="payment-modal-message"></p>' +
							'<p class="mj-animateur-dashboard__payment-modal-amount" data-role="payment-modal-amount"></p>' +
							'<a class="mj-animateur-dashboard__payment-modal-link" data-role="payment-modal-link" target="_blank" rel="noopener noreferrer"></a>' +
							'<div class="mj-animateur-dashboard__payment-modal-qr" data-role="payment-modal-qr" hidden>' +
								'<img data-role="payment-modal-qr-img" src="" alt="' + escapeHtml(this.translate('paymentLinkQrAlt', 'QR code du paiement')) + '" loading="lazy" />' +
							'</div>' +
						'</div>' +
					'</div>' +
				'</div>';

			$modal = $(html);
			$('body').append($modal);
		}

		this.$paymentModal = $modal;
		this.$paymentModalOverlay = $modal.find('[data-role="payment-modal-overlay"]');
		this.$paymentModalDialog = $modal.find('[data-role="payment-modal-dialog"]');
		this.$paymentModalClose = $modal.find('[data-role="payment-modal-close"]');
		this.$paymentModalMessage = $modal.find('[data-role="payment-modal-message"]');
		this.$paymentModalAmount = $modal.find('[data-role="payment-modal-amount"]');
		this.$paymentModalLink = $modal.find('[data-role="payment-modal-link"]');
		this.$paymentModalQr = $modal.find('[data-role="payment-modal-qr"]');
		this.$paymentModalQrImg = $modal.find('[data-role="payment-modal-qr-img"]');

		var self = this;
		this.$paymentModalOverlay.off('click').on('click', function (event) {
			event.preventDefault();
			self.closePaymentLinkModal();
		});
		this.$paymentModalClose.off('click').on('click', function (event) {
			event.preventDefault();
			self.closePaymentLinkModal();
		});

		$(document).off('keyup.mjAnimateurPayment').on('keyup.mjAnimateurPayment', function (event) {
			if (event.key === 'Escape') {
				self.closePaymentLinkModal();
			}
		});

		this.paymentLinkModalInitialized = true;
		return this.$paymentModal && this.$paymentModal.length > 0;
	};

	Dashboard.prototype.showPaymentLinkModal = function (data) {
		if (!this.initPaymentLinkModal()) {
			return false;
		}

		var message = data && data.message ? String(data.message) : this.translate('paymentLinkSuccess', 'Lien prêt à être partagé.');
		var amount = data && data.amount ? String(data.amount) : '';
		var linkLabel = data && data.linkLabel ? String(data.linkLabel) : this.translate('paymentLinkOpen', 'Ouvrir');
		var linkUrl = data && data.linkUrl ? String(data.linkUrl) : '';
		var qrUrl = data && data.qrUrl ? String(data.qrUrl) : '';
		var amountTemplate = this.translate('paymentLinkAmount', 'Montant : %s €');

		this.paymentModalLastFocus = document.activeElement;

		this.$paymentModalMessage.text(message || '');
		if (amount) {
			this.$paymentModalAmount.text(amountTemplate.replace('%s', amount)).show();
		} else {
			this.$paymentModalAmount.text('').hide();
		}

		if (linkUrl) {
			this.$paymentModalLink.text(linkLabel || linkUrl);
			this.$paymentModalLink.attr('href', linkUrl);
			this.$paymentModalLink.show();
		} else {
			this.$paymentModalLink.text('');
			this.$paymentModalLink.attr('href', '#');
			this.$paymentModalLink.hide();
		}

		if (qrUrl) {
			this.$paymentModalQrImg.attr('src', qrUrl);
			this.$paymentModalQr.removeAttr('hidden').show();
		} else {
			this.$paymentModalQrImg.attr('src', '');
			this.$paymentModalQr.attr('hidden', 'hidden').hide();
		}

		this.$paymentModal.removeAttr('hidden').attr('aria-hidden', 'false').addClass('is-open');
		this.$paymentModal.prop('hidden', false);
		if (this.$body && this.$body.length) {
			this.$body.addClass('mj-animateur-dashboard--payment-open');
		}
		if (this.$paymentModalDialog.length && this.$paymentModalDialog[0] && typeof this.$paymentModalDialog[0].focus === 'function') {
			this.$paymentModalDialog[0].focus();
		}
		if (this.$paymentModalClose.length && this.$paymentModalClose[0] && typeof this.$paymentModalClose[0].focus === 'function') {
			this.$paymentModalClose[0].focus();
		}

		return true;
	};

	Dashboard.prototype.closePaymentLinkModal = function () {
		if (!this.$paymentModal || !this.$paymentModal.length) {
			return;
		}
		if (!this.$paymentModal.hasClass('is-open')) {
			return;
		}

		this.$paymentModal.attr('aria-hidden', 'true').attr('hidden', 'hidden').removeClass('is-open');
		this.$paymentModal.prop('hidden', true);
		if (this.$body && this.$body.length) {
			this.$body.removeClass('mj-animateur-dashboard--payment-open');
		}
		this.$paymentModalMessage.text('');
		this.$paymentModalAmount.text('').hide();
		this.$paymentModalLink.text('').attr('href', '#').hide();
		this.$paymentModalQrImg.attr('src', '');
		this.$paymentModalQr.attr('hidden', 'hidden').hide();

		if (this.paymentModalLastFocus && typeof this.paymentModalLastFocus.focus === 'function') {
			try {
				this.paymentModalLastFocus.focus();
			} catch (error) {
				// Ignore focus errors.
			}
		}
		this.paymentModalLastFocus = null;
	};

	Dashboard.prototype.updatePaymentControl = function ($control, payment) {
		if (!$control || !$control.length) {
			return;
		}

		var status = this.normalizePaymentStatus(payment && payment.status);
		$control.attr('data-status', status);
		var isPaid = status === 'paid';
	 	var method = payment && payment.method ? String(payment.method) : '';
		var canToggleAttr = $control.attr('data-can-toggle');
		var canToggle = typeof canToggleAttr === 'undefined' ? true : canToggleAttr !== '0';
		var shouldLock = !canToggle || (isPaid && this.isStripePaymentMethod(method));
		if (shouldLock) {
			$control.attr('data-locked', '1');
		} else {
			$control.removeAttr('data-locked');
		}

		var $button = $control.find('[data-role="payment-toggle"]');
		if ($button.length) {
			$button.toggleClass('is-paid', isPaid);
			$button.prop('disabled', shouldLock);
			var buttonLabel;
			if (shouldLock && isPaid && this.isStripePaymentMethod(method)) {
				buttonLabel = this.getPaymentMethodLabel(method) || this.translate('paymentUnmarkButton', 'Annuler le paiement');
			} else {
				buttonLabel = isPaid ? this.translate('paymentUnmarkButton', 'Annuler le paiement') : this.translate('paymentMarkButton', 'Marquer payé');
			}
			$button.text(buttonLabel);
		}

		var $status = $control.find('[data-role="payment-status"]');
		if ($status.length) {
			$status.text('');
			$status.attr('aria-hidden', 'true');
		}

		var infoText = this.formatPaymentInfo(payment);
		var $info = $control.find('[data-role="payment-info"]');
		if ($info.length) {
			$info.text(infoText);
			if (infoText) {
				$info.attr('aria-hidden', 'false');
			} else {
				$info.attr('aria-hidden', 'true');
			}
		}
	};

	Dashboard.prototype.applyPaymentUpdate = function (event, registrationId, payment) {
		if (!event || !Array.isArray(event.participants)) {
			return;
		}

		var normalizedId = toInt(registrationId);
		if (normalizedId === null) {
			return;
		}

		var normalizedStatus = this.normalizePaymentStatus(payment && payment.status);

		for (var i = 0; i < event.participants.length; i += 1) {
			var participant = event.participants[i];
			if (toInt(participant.registrationId) === normalizedId) {
				participant.payment = participant.payment || {};
				participant.payment.status = normalizedStatus;
				participant.payment.status_label = '';
				participant.payment.method = payment && payment.method ? payment.method : participant.payment.method || '';
				participant.payment.recorded_at = payment && payment.recorded_at ? payment.recorded_at : '';
				participant.payment.recorded_at_label = payment && payment.recorded_at_label ? payment.recorded_at_label : '';
				participant.payment.recorded_by = payment && payment.recorded_by ? payment.recorded_by : { id: 0, name: '' };
				break;
			}
		}
	};
	Dashboard.prototype.updateAttendanceControlState = function ($control, status) {
		if (!$control || !$control.length) {
			return;
		}

		var normalized = status || 'pending';
		$control.find('[data-role="attendance-option"]').each(function () {
			var $button = $(this);
			$button.toggleClass('is-active', $button.attr('data-status') === normalized);
		});

		var $input = $control.find('input[data-role="attendance"]').first();
		if ($input.length) {
			$input.val(normalized);
		}

		this.updateAttendanceInfo($control, normalized);
	};

	Dashboard.prototype.updateAttendanceInfo = function ($control, status) {
		if (!$control || !$control.length) {
			return;
		}

		var $info = $control.find('[data-role="attendance-info"]').first();
		if (!$info.length) {
			return;
		}

		var infoText = this.getAttendanceInfoText(status);
		$info.text(infoText);
		if (infoText) {
			$info.attr('aria-hidden', 'false');
			$info.attr('aria-live', 'polite');
		} else {
			$info.attr('aria-hidden', 'true');
			$info.removeAttr('aria-live');
		}
		$info.attr('data-status', status || 'pending');

		var classes = ['mj-animateur-dashboard__attendance-info--present', 'mj-animateur-dashboard__attendance-info--absent', 'mj-animateur-dashboard__attendance-info--pending'];
		for (var i = 0; i < classes.length; i += 1) {
			$info.removeClass(classes[i]);
		}
		$info.addClass(this.getAttendanceInfoClassName(status || 'pending'));
	};

	Dashboard.prototype.setControlLoading = function ($control, isLoading) {
		if (!$control || !$control.length) {
			return;
		}

		$control.toggleClass('is-loading', !!isLoading);
		$control.find('[data-role="attendance-option"]').prop('disabled', !!isLoading);
	};

	Dashboard.prototype.saveSingleAttendance = function (memberId, registrationId, status, $control, previousStatus) {
		var event = this.getCurrentEvent();
		if (!event) {
			this.updateAttendanceControlState($control, previousStatus);
			this.showFeedback('attendance', 'error', this.translate('notAssigned', 'Cet événement ne vous est pas attribué.'));
			return;
		}

		var occurrence = this.state.occurrence;
		if (!occurrence) {
			this.updateAttendanceControlState($control, previousStatus);
			this.showFeedback('attendance', 'error', this.translate('missingOccurrence', "Veuillez sélectionner une occurrence avant d'enregistrer."));
			return;
		}

		var entries = [{
			member_id: memberId,
			registration_id: registrationId || 0,
			status: status || ''
		}];

		this.clearFeedback();
		this.setControlLoading($control, true);

		var self = this;
		$.ajax({
			url: this.global.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: this.global.actions ? this.global.actions.attendance : 'mj_member_animateur_save_attendance',
				nonce: this.global.nonce || '',
				event_id: event.id,
				occurrence_start: occurrence,
				entries: JSON.stringify(entries)
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				self.updateAttendanceControlState($control, previousStatus);
				self.showFeedback('attendance', 'error', self.translate('attendanceUpdateError', "Impossible de mettre à jour la présence."));
				return;
			}

			var updatedEntries = Array.isArray(response.data.entries) ? response.data.entries : entries;
			self.applyAttendanceUpdate(event, occurrence, updatedEntries, response.data.counts || null);

			if (updatedEntries.length) {
				var newStatus = updatedEntries[0].status || status;
				if (newStatus === '' || newStatus === 'none') {
					newStatus = 'pending';
				}
				self.updateAttendanceControlState($control, newStatus);
			}

			self.updateSummary(event);
			self.showFeedback('attendance', 'success', self.translate('attendanceUpdated', 'Présence mise à jour.'));
		}).fail(function () {
			self.updateAttendanceControlState($control, previousStatus);
			self.showFeedback('attendance', 'error', self.translate('attendanceUpdateError', "Impossible de mettre à jour la présence."));
		}).always(function () {
			self.setControlLoading($control, false);
		});
	};

	Dashboard.prototype.applyAttendanceUpdate = function (event, occurrence, entries, counts) {
		if (!event || !occurrence || !Array.isArray(entries)) {
			return;
		}

		var changes = {};
		for (var i = 0; i < entries.length; i += 1) {
			var entry = entries[i];
			var memberId = toInt(entry.member_id);
			if (memberId === null) {
				continue;
			}
			var status = entry.status || 'pending';
			changes[memberId] = status === 'none' ? 'pending' : status;
		}

		if (Array.isArray(event.participants)) {
			for (var j = 0; j < event.participants.length; j += 1) {
				var participant = event.participants[j];
				var participantId = toInt(participant.memberId);
				if (participantId !== null && changes.hasOwnProperty(participantId)) {
					participant.attendance = participant.attendance || {};
					participant.attendance[occurrence] = changes[participantId];
				}
			}
		}

		if (!Array.isArray(event.occurrences)) {
			return;
		}

		var targetIndex = -1;
		for (var k = 0; k < event.occurrences.length; k += 1) {
			if (event.occurrences[k].start === occurrence) {
				targetIndex = k;
				break;
			}
		}

		if (targetIndex === -1) {
			return;
		}

		var normalizedCounts = {
			present: 0,
			absent: 0,
			pending: 0
		};

		if (counts) {
			normalizedCounts.present = counts.present || 0;
			normalizedCounts.absent = counts.absent || 0;
			normalizedCounts.pending = counts.pending || 0;
		}

		var needsRecompute = !counts || typeof counts.present === 'undefined' || typeof counts.absent === 'undefined' || typeof counts.pending === 'undefined';
		if (needsRecompute && Array.isArray(event.participants)) {
			var present = 0;
			var absent = 0;
			var total = event.participants.length;
			for (var p = 0; p < event.participants.length; p += 1) {
				var participantEntry = event.participants[p];
				var attendanceMap = participantEntry && participantEntry.attendance ? participantEntry.attendance : {};
				var entryStatus = attendanceMap && Object.prototype.hasOwnProperty.call(attendanceMap, occurrence)
					? attendanceMap[occurrence]
					: 'pending';
				if (entryStatus === 'present') {
					present += 1;
				} else if (entryStatus === 'absent') {
					absent += 1;
				}
			}
			var pending = total - present - absent;
			if (pending < 0) {
				pending = 0;
			}
			normalizedCounts.present = present;
			normalizedCounts.absent = absent;
			normalizedCounts.pending = pending;
		}

		event.occurrences[targetIndex].counts = normalizedCounts;
	};

	Dashboard.prototype.clearFeedback = function () {
		if (this.$feedback.length) {
			this.$feedback.removeClass('is-error is-success').text('');
		}
		if (this.$smsFeedback.length) {
			this.$smsFeedback.removeClass('is-error is-success').text('');
		}
	};

	Dashboard.prototype.showFeedback = function (type, level, message) {
		var $target = type === 'sms' ? this.$smsFeedback : this.$feedback;
		if (!$target.length) {
			return;
		}

		$target.removeClass('is-error is-success');
		if (level === 'error') {
			$target.addClass('is-error');
		} else if (level === 'success') {
			$target.addClass('is-success');
		}
		$target.text(message || '');
	};

	Dashboard.prototype.setLoading = function ($element, isLoading) {
		if (!$element || !$element.length) {
			return;
		}

		$element.toggleClass('is-loading', !!isLoading);
		$element.prop('disabled', !!isLoading);
	};

	Dashboard.prototype.sendSms = function () {
		if (!this.settings.sms || !this.$smsButton.length) {
			return;
		}

		var event = this.getCurrentEvent();
		if (!event) {
			this.showFeedback('sms', 'error', this.translate('notAssigned', 'Cet événement ne vous est pas attribué.'));
			return;
		}

		var participants = Array.isArray(event.participants) ? event.participants : [];
		var recipients = this.getSmsRecipients(participants);
		if (!recipients.length) {
			this.showFeedback('sms', 'error', this.translate('noSmsRecipients', 'Aucun participant ne peut recevoir ce SMS.'));
			return;
		}

		var messageRaw = this.$smsArea.length ? String(this.$smsArea.val() || '') : '';
		var message = messageRaw.trim();
		if (message === '') {
			this.showFeedback('sms', 'error', this.translate('smsEmpty', "Veuillez saisir un message avant l'envoi."));
			return;
		}

		this.clearFeedback();
		this.setLoading(this.$smsButton, true);

		var self = this;
		$.ajax({
			url: this.global.ajaxUrl,
			method: 'POST',
			dataType: 'json',
			data: {
				action: this.global.actions ? this.global.actions.sms : 'mj_member_animateur_send_sms',
				nonce: this.global.nonce || '',
				event_id: event.id,
				message: message,
				member_ids: JSON.stringify(recipients)
			}
		}).done(function (response) {
			if (!response || !response.success || !response.data) {
				self.showFeedback('sms', 'error', self.translate('smsError', "Impossible d'envoyer le SMS."));
				return;
			}

			var summary = response.data.summary;
			var feedbackMessage = response.data.message || self.translate('smsSuccess', 'SMS envoyé.');
			if (summary && typeof summary.sent === 'number') {
				var total = summary.sent + (summary.failed || 0);
				feedbackMessage += ' (' + summary.sent + '/' + (total || summary.sent) + ')';
			}
			self.showFeedback('sms', 'success', feedbackMessage);
		}).fail(function () {
			self.showFeedback('sms', 'error', self.translate('smsError', "Impossible d'envoyer le SMS."));
		}).always(function () {
			self.setLoading(self.$smsButton, false);
		});
	};

	Dashboard.prototype.translate = function (key, fallback) {
		var catalogue = this.config.i18n || {};
		if (catalogue.hasOwnProperty(key) && catalogue[key] !== undefined && catalogue[key] !== null) {
			return catalogue[key];
		}
		return fallback;
	};

	function QuickMemberModal(dashboard, options) {
		options = options || {};
		this.dashboard = dashboard;
		this.$container = options.container ? options.container : $();
		this.$trigger = options.trigger ? options.trigger : $();
		this.action = options.action || '';
		this.enabled = !!(this.$container && this.$container.length && this.action);
		this.state = {
			open: false,
			submitting: false
		};
		this.lastFocusedElement = null;

		if (!this.enabled) {
			return;
		}

		this.$dialog = this.$container.find('[data-role="quick-member-dialog"]');
		this.$backdrop = this.$container.find('[data-role="quick-member-backdrop"]');
		this.$form = this.$container.find('[data-role="quick-member-form"]');
		this.$feedback = this.$container.find('[data-role="quick-member-feedback"]');
		this.$submit = this.$container.find('[data-role="quick-member-submit"]');
		this.$cancel = this.$container.find('[data-role="quick-member-cancel"]');
		this.$close = this.$container.find('[data-role="quick-member-close"]');
		this.$inputs = this.$form.find('input');
		this.$firstName = this.$form.find('input[name="first_name"]');
		this.$lastName = this.$form.find('input[name="last_name"]');
		this.$birthDate = this.$form.find('input[name="birth_date"]');
		this.$email = this.$form.find('input[name="email"]');

		this.fieldMap = {};
		var self = this;
		this.$form.find('[data-role="quick-member-field"]').each(function () {
			var $field = $(this);
			var fieldKey = $field.attr('data-field');
			if (fieldKey) {
				self.fieldMap[fieldKey] = $field;
			}
		});

		this.bind();
	}

	QuickMemberModal.prototype.bind = function () {
		var self = this;
		if (this.$backdrop.length) {
			this.$backdrop.on('click', function () {
				if (!self.state.submitting) {
					self.close();
				}
			});
		}

		if (this.$close.length) {
			this.$close.on('click', function (event) {
				event.preventDefault();
				if (!self.state.submitting) {
					self.close();
				}
			});
		}

		if (this.$cancel.length) {
			this.$cancel.on('click', function (event) {
				event.preventDefault();
				if (!self.state.submitting) {
					self.close();
				}
			});
		}

		if (this.$form.length) {
			this.$form.on('submit', function (event) {
				event.preventDefault();
				self.submit();
			});
			this.$form.on('input', 'input', function () {
				var name = $(this).attr('name');
				if (name) {
					self.setFieldError(name, false);
				}
				if (!self.state.submitting) {
					self.showFeedback('', null);
				}
			});
		}

		this.$container.on('keydown', function (event) {
			if (event.key === 'Escape' && !self.state.submitting) {
				self.close();
			}
		});
	};

	QuickMemberModal.prototype.open = function () {
		if (!this.enabled || this.state.open) {
			return;
		}

		this.resetForm();
		this.state.open = true;
		this.lastFocusedElement = document.activeElement;
		this.$container.removeAttr('hidden').attr('aria-hidden', 'false').addClass('is-open');
		$('body').addClass('mj-animateur-dashboard--quick-member-open');

		var self = this;
		if (this.$firstName.length) {
			window.setTimeout(function () {
				self.$firstName.trigger('focus');
			}, 50);
		}
		if (this.$birthDate.length) {
			var today = new Date();
			var isoToday = today.toISOString().slice(0, 10);
			this.$birthDate.attr('max', isoToday);
		}
	};

	QuickMemberModal.prototype.close = function () {
		if (!this.state.open) {
			return;
		}
		if (this.state.submitting) {
			return;
		}

		this.state.open = false;
		this.$container.attr('hidden', 'hidden').attr('aria-hidden', 'true').removeClass('is-open');
		$('body').removeClass('mj-animateur-dashboard--quick-member-open');
		this.resetForm();

		if (this.$trigger && this.$trigger.length) {
			this.$trigger.trigger('focus');
		} else if (this.lastFocusedElement && typeof this.lastFocusedElement.focus === 'function') {
			this.lastFocusedElement.focus();
		}
		this.lastFocusedElement = null;
	};

	QuickMemberModal.prototype.resetForm = function () {
		if (this.$form.length && this.$form[0]) {
			this.$form[0].reset();
		}
		this.clearFieldErrors();
		this.showFeedback('', null);
	};

	QuickMemberModal.prototype.clearFieldErrors = function () {
		for (var key in this.fieldMap) {
			if (Object.prototype.hasOwnProperty.call(this.fieldMap, key)) {
				this.fieldMap[key].removeClass('has-error');
			}
		}
	};

	QuickMemberModal.prototype.setFieldError = function (field, hasError) {
		if (!this.fieldMap[field]) {
			return;
		}
		if (hasError) {
			this.fieldMap[field].addClass('has-error');
		} else {
			this.fieldMap[field].removeClass('has-error');
		}
	};

	QuickMemberModal.prototype.focusField = function (field) {
		if (field === 'first_name' && this.$firstName.length) {
			this.$firstName.trigger('focus');
			return;
		}
		if (field === 'last_name' && this.$lastName.length) {
			this.$lastName.trigger('focus');
			return;
		}
		if (field === 'birth_date' && this.$birthDate.length) {
			this.$birthDate.trigger('focus');
			return;
		}
		if (field === 'email' && this.$email.length) {
			this.$email.trigger('focus');
		}
	};

	QuickMemberModal.prototype.showFeedback = function (message, level) {
		if (!this.$feedback.length) {
			return;
		}
		this.$feedback.removeClass('is-error is-success');
		if (!message) {
			this.$feedback.text('').hide();
			return;
		}
		if (level === 'error') {
			this.$feedback.addClass('is-error');
		} else if (level === 'success') {
			this.$feedback.addClass('is-success');
		}
		this.$feedback.text(message).show();
	};

	QuickMemberModal.prototype.setSubmitting = function (submitting) {
		this.state.submitting = !!submitting;
		if (this.$submit.length) {
			this.$submit.prop('disabled', submitting).toggleClass('is-loading', submitting);
		}
		if (this.$cancel.length) {
			this.$cancel.prop('disabled', submitting);
		}
		if (this.$close.length) {
			this.$close.prop('disabled', submitting);
		}
		if (this.$inputs && this.$inputs.length) {
			this.$inputs.prop('disabled', submitting);
		}
	};

	QuickMemberModal.prototype.submit = function () {
		if (this.state.submitting) {
			return;
		}

		var firstName = this.$firstName.length ? String(this.$firstName.val() || '').trim() : '';
		var lastName = this.$lastName.length ? String(this.$lastName.val() || '').trim() : '';
		var birthDate = this.$birthDate.length ? String(this.$birthDate.val() || '').trim() : '';
		var email = this.$email.length ? String(this.$email.val() || '').trim() : '';

		this.clearFieldErrors();

		if (firstName === '') {
			this.setFieldError('first_name', true);
			this.showFeedback(this.dashboard.translate('quickCreateFirstNameRequired', 'Le prénom est obligatoire.'), 'error');
			this.focusField('first_name');
			return;
		}

		if (lastName === '') {
			this.setFieldError('last_name', true);
			this.showFeedback(this.dashboard.translate('quickCreateLastNameRequired', 'Le nom est obligatoire.'), 'error');
			this.focusField('last_name');
			return;
		}

		if (birthDate === '') {
			this.setFieldError('birth_date', true);
			this.showFeedback(this.dashboard.translate('quickCreateBirthDateRequired', 'La date de naissance est obligatoire.'), 'error');
			this.focusField('birth_date');
			return;
		}

		var birthPattern = /^\d{4}-\d{2}-\d{2}$/;
		if (!birthPattern.test(birthDate)) {
			this.setFieldError('birth_date', true);
			this.showFeedback(this.dashboard.translate('quickCreateInvalidBirthDate', 'La date de naissance est invalide.'), 'error');
			this.focusField('birth_date');
			return;
		}

		var birthDateObj = new Date(birthDate + 'T00:00:00Z');
		if (isNaN(birthDateObj.getTime())) {
			this.setFieldError('birth_date', true);
			this.showFeedback(this.dashboard.translate('quickCreateInvalidBirthDate', 'La date de naissance est invalide.'), 'error');
			this.focusField('birth_date');
			return;
		}

		var today = new Date();
		if (birthDateObj > today) {
			this.setFieldError('birth_date', true);
			this.showFeedback(this.dashboard.translate('quickCreateInvalidBirthDate', 'La date de naissance est invalide.'), 'error');
			this.focusField('birth_date');
			return;
		}

		if (email !== '') {
			var emailPattern = /^[^@\s]+@[^@\s]+\.[^@\s]+$/;
			if (!emailPattern.test(email)) {
				this.setFieldError('email', true);
				this.showFeedback(this.dashboard.translate('quickCreateInvalidEmail', 'L\'adresse email n\'est pas valide.'), 'error');
				this.focusField('email');
				return;
			}
		}

		this.setSubmitting(true);

		var payload = {
			action: this.action,
			nonce: this.dashboard.global ? this.dashboard.global.nonce : '',
			first_name: firstName,
			last_name: lastName,
			birth_date: birthDate
		};
		if (email !== '') {
			payload.email = email;
		}

		var self = this;
		$.ajax({
			url: this.dashboard.global ? this.dashboard.global.ajaxUrl : '',
			method: 'POST',
			dataType: 'json',
			data: payload
		}).done(function (response) {
			if (!response || !response.success) {
				var errorData = response && response.data ? response.data : {};
				var errorMessage = errorData.message || self.dashboard.translate('quickCreateError', 'Impossible de créer le membre.');
				if (errorData.field) {
					self.setFieldError(errorData.field, true);
					self.focusField(errorData.field);
				}
				self.showFeedback(errorMessage, 'error');
				return;
			}

			var data = response.data || {};
			var message = data.message || self.dashboard.translate('quickCreateSuccess', 'Membre créé. Vous pouvez maintenant l\'ajouter aux réservations.');
			var memberData = data.member || null;
			self.dashboard.handleQuickMemberCreated(memberData);
			self.showFeedback(message, 'success');

			window.setTimeout(function () {
				self.close();
				self.dashboard.showFeedback('attendance', 'success', message);
				if (self.dashboard.canAutoOpenMemberPicker()) {
					self.dashboard.openMemberPickerAfterQuickCreate();
				}
			}, 320);
		}).fail(function (jqXHR) {
			var fallback = self.dashboard.translate('quickCreateError', 'Impossible de créer le membre.');
			var responseJSON = jqXHR && jqXHR.responseJSON ? jqXHR.responseJSON : null;
			var failureData = responseJSON && responseJSON.data ? responseJSON.data : {};
			var message = failureData.message || fallback;
			if (failureData.field) {
				self.setFieldError(failureData.field, true);
				self.focusField(failureData.field);
			}
			self.showFeedback(message, 'error');
		}).always(function () {
			self.setSubmitting(false);
		});
	};

		function MemberPicker(dashboard, options) {
			options = options || {};
			this.dashboard = dashboard;
			this.$container = options.container ? options.container : $();

			if (!this.$container || !this.$container.length) {
				this.enabled = false;
				return;
			}

			this.$overlay = this.$container.find('[data-role="member-picker-backdrop"]');
			this.$dialog = this.$container.find('[data-role="member-picker-dialog"]');
			this.$list = this.$container.find('[data-role="member-picker-list"]');
			this.$empty = this.$container.find('[data-role="member-picker-empty"]');
			this.$loading = this.$container.find('[data-role="member-picker-loading"]');
			this.$loadMore = this.$container.find('[data-role="member-picker-load-more"]');
			this.$feedback = this.$container.find('[data-role="member-picker-feedback"]');
			this.$confirm = this.$container.find('[data-role="member-picker-confirm"]');
			this.$cancel = this.$container.find('[data-role="member-picker-cancel"]');
			this.$close = this.$container.find('[data-role="member-picker-close"]');
			this.$search = this.$container.find('[data-role="member-picker-search"]');
			this.$conditionsWrapper = this.$container.find('[data-role="member-picker-conditions"]');
			this.$conditionsList = this.$container.find('[data-role="member-picker-conditions-list"]');
			this.$conditionsTitle = this.$container.find('[data-role="member-picker-conditions-title"]');
			if (this.$conditionsTitle.length) {
				this.$conditionsTitle.text(this.dashboard.translate('memberPickerConditionsTitle', "Conditions de l'événement"));
			}
			this.infiniteScrollEnabled = true;
			this.infiniteScrollThreshold = 160;
			this.scrollRaf = null;

			var perPage = 20;
			if (dashboard.config && dashboard.config.memberPicker && dashboard.config.memberPicker.perPage) {
				var parsedPerPage = parseInt(dashboard.config.memberPicker.perPage, 10);
				if (!isNaN(parsedPerPage) && parsedPerPage > 0) {
					perPage = parsedPerPage;
				}
			}

			this.state = {
				eventId: null,
				page: 1,
				perPage: perPage,
				search: '',
				hasMore: false,
				loading: false,
				submitting: false,
				open: false
			};

			this.enabled = true;
			this.selected = {};
			this.memberIndex = {};
			this.pendingErrors = [];
			this.searchTimer = null;

			this.bind();
			this.updateUiState();
			this.updateLoadMoreVisibility();
		}

		MemberPicker.prototype.setEnabled = function (enabled) {
			this.enabled = !!enabled;
			if (!this.enabled && this.state.open) {
				this.close();
			}
			this.updateUiState();
		};

		MemberPicker.prototype.updateUiState = function () {
			if (!this.$container || !this.$container.length) {
				return;
			}
			this.$container.toggleClass('is-disabled', !this.enabled);
			this.$container.toggleClass('is-open', !!this.state.open);
			if (this.state.open) {
				this.$container.removeAttr('hidden').attr('aria-hidden', 'false');
			} else {
				this.$container.attr('hidden', 'hidden').attr('aria-hidden', 'true');
			}
		};

		MemberPicker.prototype.bind = function () {
			var self = this;

			if (this.$overlay.length) {
				this.$overlay.on('click', function () {
					self.close();
				});
			}
			if (this.$close.length) {
				this.$close.on('click', function (event) {
					event.preventDefault();
					self.close();
				});
			}
			if (this.$cancel.length) {
				this.$cancel.on('click', function (event) {
					event.preventDefault();
					self.close();
				});
			}
			if (this.$confirm.length) {
				this.$confirm.on('click', function (event) {
					event.preventDefault();
					self.submitSelection();
				});
			}
			if (this.$loadMore.length) {
				this.$loadMore.on('click', function (event) {
					event.preventDefault();
					self.loadMore();
				});
			}
			if (this.$list.length) {
				this.$list.on('change', '[data-role="member-picker-checkbox"]', function () {
					self.onCheckboxChange($(this));
				});
				if (this.infiniteScrollEnabled) {
					this.$list.on('scroll', function () {
						self.onListScroll();
					});
				}
			}
			if (this.$search.length) {
				this.$search.on('input', function () {
					self.onSearchInput($(this).val());
				});
			}
		};

		MemberPicker.prototype.open = function (eventId) {
			if (!this.enabled) {
				return;
			}
			var id = toInt(eventId);
			if (id === null) {
				return;
			}

			this.state.eventId = id;
			this.state.page = 1;
			var quickContext = this.dashboard && this.dashboard.quickMemberContext ? this.dashboard.quickMemberContext : null;
			this.state.search = quickContext && typeof quickContext.search === 'string' ? quickContext.search : '';
			this.state.hasMore = false;
			this.state.open = true;
			this.selected = {};
			this.memberIndex = {};
			this.clearFeedback();
			this.resetList();
			if (this.$search.length) {
				this.$search.prop('disabled', false).val(this.state.search);
			}
			if (this.dashboard) {
				this.dashboard.quickMemberContext = null;
			}

			this.updateUiState();
			this.renderConditions();
			$('body').addClass('mj-animateur-dashboard--member-picker-open');

			var self = this;
			$(document).on('keydown.mjMemberPicker', function (event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					self.close();
				}
			});

			this.fetchMembers(true);
			this.updateSelectionState();
			if (this.$search.length) {
				this.$search.trigger('focus');
			}
		};

		MemberPicker.prototype.close = function () {
			if (!this.state.open) {
				return;
			}
			if (this.searchTimer) {
				clearTimeout(this.searchTimer);
				this.searchTimer = null;
			}
			this.state.open = false;
			this.state.eventId = null;
			this.state.page = 1;
			this.state.search = '';
			this.state.hasMore = false;
			this.selected = {};
			this.memberIndex = {};
			this.pendingErrors = [];
			this.setLoading(false);
			this.setSubmitting(false);
			this.clearFeedback();
			this.resetList();
			if (this.$empty.length) {
				this.$empty.prop('hidden', true);
			}
			if (this.$loadMore.length) {
				this.$loadMore.prop('hidden', true);
			}
			if (this.$search.length) {
				this.$search.prop('disabled', false).val('');
			}
			this.updateSelectionState();
			this.clearConditions();
			this.scrollRaf = null;
			this.updateUiState();
			$('body').removeClass('mj-animateur-dashboard--member-picker-open');
			$(document).off('keydown.mjMemberPicker');
		};

		MemberPicker.prototype.resetList = function () {
			if (this.$list.length) {
				this.$list.empty();
				this.$list.scrollTop(0);
			}
		};

		MemberPicker.prototype.setLoading = function (isLoading) {
			this.state.loading = !!isLoading;
			if (this.$loading.length) {
				this.$loading.prop('hidden', !isLoading);
			}
			if (isLoading) {
				if (this.$empty.length) {
					this.$empty.prop('hidden', true);
				}
				if (this.$loadMore.length) {
					this.$loadMore.prop('hidden', true);
				}
			}
			this.updateLoadMoreVisibility();
		};

		MemberPicker.prototype.showFeedback = function (message, level) {
			if (!this.$feedback.length) {
				return;
			}
			this.$feedback.removeClass('is-error is-success is-info');
			if (!message) {
				this.$feedback.text('').hide();
				return;
			}
			if (level === 'error') {
				this.$feedback.addClass('is-error');
			} else if (level === 'success') {
				this.$feedback.addClass('is-success');
			} else if (level === 'info') {
				this.$feedback.addClass('is-info');
			}
			this.$feedback.text(message).show();
		};

		MemberPicker.prototype.clearFeedback = function () {
			if (!this.$feedback.length) {
				return;
			}
			this.$feedback.removeClass('is-error is-success is-info').text('').hide();
		};

		MemberPicker.prototype.getSelectionCount = function () {
			var count = 0;
			for (var key in this.selected) {
				if (Object.prototype.hasOwnProperty.call(this.selected, key)) {
					count += 1;
				}
			}
			return count;
		};

		MemberPicker.prototype.updateSelectionState = function () {
			var self = this;
			var confirmLabel = this.dashboard.translate('memberPickerConfirm', 'Ajouter les membres sélectionnés');
			var confirmCountLabel = this.dashboard.translate('memberPickerConfirmCount', 'Ajouter (%d)');

			function updateConfirmButton(count) {
				if (!self.$confirm.length) {
					return;
				}
				if (count > 0 && !self.state.submitting) {
					self.$confirm.prop('disabled', false);
					self.$confirm.text(confirmCountLabel.replace('%d', count));
				} else {
					self.$confirm.prop('disabled', true);
					self.$confirm.text(confirmLabel);
				}
			}

			var count = this.getSelectionCount();
			updateConfirmButton(count);

			if (!this.$list.length) {
				return;
			}

			var selectionChanged = false;
			this.$list.find('[data-role="member-picker-item"]').each(function () {
				var $item = $(this);
				var memberId = toInt($item.attr('data-member-id'));
				var isSelected = memberId !== null && Object.prototype.hasOwnProperty.call(self.selected, memberId);
				var $checkbox = $item.find('[data-role="member-picker-checkbox"]');
				var assigned = false;
				var eligible = true;
				if ($checkbox.length) {
					assigned = $checkbox.attr('data-assigned') === '1';
					eligible = $checkbox.attr('data-eligible') !== '0';
					if (!eligible && isSelected) {
						delete self.selected[memberId];
						isSelected = false;
						selectionChanged = true;
					}
					$checkbox.prop('checked', isSelected && !assigned && eligible);
				}
				$item.toggleClass('is-selected', !!isSelected);
			});

			if (selectionChanged) {
				updateConfirmButton(this.getSelectionCount());
			}
		};

		MemberPicker.prototype.onListScroll = function () {
			if (!this.infiniteScrollEnabled || !this.$list.length) {
				return;
			}
			if (this.scrollRaf) {
				return;
			}
			var self = this;
			var requestFrame = window.requestAnimationFrame || function (callback) {
				return setTimeout(callback, 16);
			};
			this.scrollRaf = requestFrame(function () {
				self.scrollRaf = null;
				self.evaluateInfiniteScroll();
			});
		};

		MemberPicker.prototype.evaluateInfiniteScroll = function () {
			if (!this.infiniteScrollEnabled || !this.$list.length) {
				return;
			}
			if (!this.state.open || !this.state.hasMore || this.state.loading || this.state.submitting) {
				return;
			}
			var element = this.$list.get(0);
			if (!element) {
				return;
			}
			var remaining = element.scrollHeight - element.scrollTop - element.clientHeight;
			if (remaining <= this.infiniteScrollThreshold) {
				this.loadMore();
			}
		};

		MemberPicker.prototype.autoFillIfNeeded = function () {
			if (!this.infiniteScrollEnabled || !this.$list.length) {
				return;
			}
			if (!this.state.open || !this.state.hasMore || this.state.loading || this.state.submitting) {
				return;
			}
			var element = this.$list.get(0);
			if (!element) {
				return;
			}
			if (element.scrollHeight <= element.clientHeight + this.infiniteScrollThreshold) {
				this.loadMore();
			}
		};

		MemberPicker.prototype.updateLoadMoreVisibility = function () {
			if (!this.$loadMore.length) {
				return;
			}
			if (this.infiniteScrollEnabled) {
				this.$loadMore.attr('hidden', 'hidden').attr('aria-hidden', 'true').prop('hidden', true);
				return;
			}
			if (this.state.hasMore) {
				this.$loadMore.prop('hidden', false).removeAttr('hidden').attr('aria-hidden', 'false');
			} else {
				this.$loadMore.attr('hidden', 'hidden').attr('aria-hidden', 'true').prop('hidden', true);
			}
		};

		MemberPicker.prototype.clearConditions = function () {
			if (!this.$conditionsWrapper.length || !this.$conditionsList.length) {
				return;
			}
			this.$conditionsList.empty();
			this.$conditionsWrapper.attr('hidden', 'hidden');
		};

		MemberPicker.prototype.renderConditions = function () {
			if (!this.$conditionsWrapper.length || !this.$conditionsList.length) {
				return;
			}

			var eventId = toInt(this.state.eventId);
			if (eventId === null) {
				this.clearConditions();
				return;
			}

			var eventData = this.dashboard ? this.dashboard.getEvent(eventId) : null;
			if (!eventData) {
				this.clearConditions();
				return;
			}

			var conditions = [];
			if (Array.isArray(eventData.conditions)) {
				conditions = eventData.conditions.slice(0);
			}

			if (!conditions.length) {
				this.clearConditions();
				return;
			}

			var html = '';
			for (var i = 0; i < conditions.length; i += 1) {
				var line = conditions[i];
				if (line === null || line === undefined) {
					continue;
				}
				line = String(line).trim();
				if (line === '') {
					continue;
				}
				html += '<li>' + escapeHtml(line) + '</li>';
			}

			if (html === '') {
				this.clearConditions();
				return;
			}

			this.$conditionsList.html(html);
			this.$conditionsWrapper.removeAttr('hidden');
		};

		MemberPicker.prototype.onSearchInput = function (value) {
			var term = String(value || '').trim();
			if (term === this.state.search) {
				return;
			}
			this.state.search = term;
			this.scheduleSearch();
		};

		MemberPicker.prototype.scheduleSearch = function () {
			var self = this;
			if (this.searchTimer) {
				clearTimeout(this.searchTimer);
			}
			this.searchTimer = setTimeout(function () {
				self.fetchMembers(true);
			}, 250);
		};

		MemberPicker.prototype.onCheckboxChange = function ($checkbox) {
			if (!$checkbox.length || this.state.submitting) {
				return;
			}
			if ($checkbox.is(':disabled')) {
				return;
			}
			if ($checkbox.attr('data-eligible') === '0') {
				$checkbox.prop('checked', false);
				return;
			}
			var memberId = toInt($checkbox.val());
			if (memberId === null) {
				return;
			}
			if ($checkbox.is(':checked')) {
				this.selected[memberId] = true;
			} else {
				delete this.selected[memberId];
			}
			this.updateSelectionState();
		};

		MemberPicker.prototype.loadMore = function () {
			if (!this.state.hasMore || this.state.loading || this.state.submitting) {
				return;
			}
			this.state.page += 1;
			this.fetchMembers(false);
		};

		MemberPicker.prototype.fetchMembers = function (reset) {
			if (!this.enabled || !this.state.open || this.state.loading) {
				return;
			}

			if (reset) {
				this.state.page = 1;
				if (this.$loadMore.length) {
					this.$loadMore.prop('hidden', true);
				}
				if (this.$empty.length) {
					this.$empty.prop('hidden', true);
				}
				this.resetList();
				this.updateLoadMoreVisibility();
			}

			this.setLoading(true);

			var self = this;
			$.ajax({
				url: this.dashboard.global.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: this.dashboard.global.actions ? this.dashboard.global.actions.memberSearch : 'mj_member_animateur_search_members',
					nonce: this.dashboard.global.nonce || '',
					event_id: this.state.eventId,
					page: this.state.page,
					per_page: this.state.perPage,
					search: this.state.search,
					occurrence: this.dashboard && typeof this.dashboard.getCurrentOccurrence === 'function' ? this.dashboard.getCurrentOccurrence() : null
				}
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					var fallback = self.dashboard.translate('memberPickerFetchError', 'Impossible de charger la liste des membres.');
					var reason = response && response.data && response.data.message ? String(response.data.message) : fallback;
					self.showFeedback(reason, 'error');
					self.dashboard.showFeedback('attendance', 'error', reason);
					if (!self.$list.children().length && self.$empty.length) {
						self.$empty.prop('hidden', false);
					}
					return;
				}

				var data = response.data;
				var members = Array.isArray(data.members) ? data.members : [];
				self.state.hasMore = !!data.hasMore;
				self.state.page = data.page || self.state.page;
				self.updateLoadMoreVisibility();

				var appended = self.renderMembers(members);
				if (appended > 0) {
					self.autoFillIfNeeded();
				}

				if (!self.$list.children().length && self.$empty.length) {
					self.$empty.prop('hidden', false);
				} else if (self.$empty.length) {
					self.$empty.prop('hidden', true);
				}
			}).fail(function (jqXHR) {
				var defaultMessage = self.dashboard.translate('memberPickerFetchError', 'Impossible de charger la liste des membres.');
				var responseText = jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ? String(jqXHR.responseJSON.data.message) : defaultMessage;
				self.showFeedback(responseText, 'error');
				self.dashboard.showFeedback('attendance', 'error', responseText);
				if (!self.$list.children().length && self.$empty.length) {
					self.$empty.prop('hidden', false);
				}
				self.updateLoadMoreVisibility();
			}).always(function () {
				self.setLoading(false);
			});
		};

		MemberPicker.prototype.renderMembers = function (members) {
			if (!this.$list.length || !Array.isArray(members) || !members.length) {
				return 0;
			}

			var html = '';
			var appended = 0;
			for (var i = 0; i < members.length; i += 1) {
				var member = members[i];
				var memberId = toInt(member && member.id);
				if (memberId === null) {
					continue;
				}
				this.memberIndex[memberId] = member;
				html += this.buildMemberItemHtml(member);
				appended += 1;
			}

			if (html) {
				this.$list.append(html);
			} else {
				appended = 0;
			}

			if (this.pendingErrors && this.pendingErrors.length) {
				this.flagSubmissionErrors(this.pendingErrors);
				this.pendingErrors = [];
			}

			this.updateSelectionState();
			return appended;
		};

		MemberPicker.prototype.buildMemberItemHtml = function (member) {
			var memberId = toInt(member && member.id);
			if (memberId === null) {
				return '';
			}

			var alreadyAssigned = !!(member && member.alreadyAssigned);
			var assignedOtherOccurrence = !!(member && member.assignedOtherOccurrence);
			var eligible = !(member && member.eligible === false);
			var rawReasons = member && member.ineligibleReasons ? member.ineligibleReasons : [];
			var reasons = [];
			if (Array.isArray(rawReasons)) {
				for (var i = 0; i < rawReasons.length; i += 1) {
					var reason = rawReasons[i];
					if (typeof reason !== 'string') {
						continue;
					}
					var trimmed = reason.trim();
					if (trimmed) {
						reasons.push(trimmed);
					}
				}
			}
			var isSelected = Object.prototype.hasOwnProperty.call(this.selected, memberId) && !alreadyAssigned && eligible;

			var avatar = member && member.avatar ? member.avatar : {};
			var avatarHtml = '';
			if (avatar.url) {
				avatarHtml = '<span class="mj-animateur-dashboard__member-picker-avatar"><img src="' + escapeHtml(String(avatar.url)) + '" alt="' + escapeHtml(String(avatar.alt || '')) + '" loading="lazy" decoding="async"></span>';
			} else {
				var initials = avatar.initials ? String(avatar.initials) : '';
				avatarHtml = '<span class="mj-animateur-dashboard__member-picker-avatar mj-animateur-dashboard__member-picker-avatar--placeholder">' + (initials ? escapeHtml(initials) : '<span class="mj-animateur-dashboard__member-picker-avatar-dot" aria-hidden="true"></span>') + '</span>';
			}

			var roleLabel = member && member.roleLabel ? String(member.roleLabel) : '';
			var roleKey = member && member.role ? String(member.role) : '';
			if (!roleLabel && roleKey) {
				roleLabel = roleKey;
			}

			var metaParts = [];
			if (roleLabel) {
				metaParts.push(roleLabel);
			}
			if (typeof member.age === 'number' && member.age > 0) {
				metaParts.push(member.age + ' ans');
			}
			if (member && member.city) {
				metaParts.push(String(member.city));
			}

			var metaHtml = metaParts.length ? '<span class="mj-animateur-dashboard__member-picker-meta">' + escapeHtml(metaParts.join(' • ')) + '</span>' : '';

			var assignedLabel = this.dashboard.translate('memberPickerAlreadyAssigned', 'Déjà inscrit');
			var assignedOtherLabel = this.dashboard.translate('memberPickerAssignedOtherOccurrence', 'Inscrit sur une autre séance');
			var ineligibleLabel = this.dashboard.translate('memberPickerIneligible', 'Conditions non respectées');
			var disabled = alreadyAssigned || !eligible;
			var checkboxAttrs = '';
			checkboxAttrs += disabled ? ' disabled' : '';
			checkboxAttrs += alreadyAssigned ? ' data-assigned="1"' : ' data-assigned="0"';
			checkboxAttrs += assignedOtherOccurrence ? ' data-assigned-other="1"' : ' data-assigned-other="0"';
			checkboxAttrs += eligible ? ' data-eligible="1"' : ' data-eligible="0"';
			if (isSelected) {
				checkboxAttrs += ' checked';
			}
			var statusHtml = '';
			if (alreadyAssigned) {
				statusHtml += '<span class="mj-animateur-dashboard__member-picker-status">' + escapeHtml(assignedLabel) + '</span>';
			}
			if (!alreadyAssigned && assignedOtherOccurrence) {
				statusHtml += '<span class="mj-animateur-dashboard__member-picker-status mj-animateur-dashboard__member-picker-status--info">' + escapeHtml(assignedOtherLabel) + '</span>';
			}
			if (!eligible) {
				var joinedReasons = reasons.length ? reasons.join(' • ') : ineligibleLabel;
				var displayReason = reasons.length ? reasons[0] : ineligibleLabel;
				statusHtml += '<span class="mj-animateur-dashboard__member-picker-status mj-animateur-dashboard__member-picker-status--warning" title="' + escapeHtml(joinedReasons) + '">' + escapeHtml(displayReason) + '</span>';
			}

			var classes = ['mj-animateur-dashboard__member-picker-item'];
			if (alreadyAssigned) {
				classes.push('is-disabled');
			}
			if (!eligible) {
				classes.push('is-ineligible');
			}
			if (isSelected) {
				classes.push('is-selected');
			}

			return '' +
				'<li class="' + classes.join(' ') + '" data-role="member-picker-item" data-member-id="' + escapeHtml(String(memberId)) + '"' + (disabled ? ' aria-disabled="true"' : '') + '>' +
					'<label class="mj-animateur-dashboard__member-picker-option">' +
						'<input type="checkbox" class="mj-animateur-dashboard__member-picker-checkbox" data-role="member-picker-checkbox" value="' + escapeHtml(String(memberId)) + '"' + checkboxAttrs + '>' +
						avatarHtml +
						'<span class="mj-animateur-dashboard__member-picker-details">' +
							'<span class="mj-animateur-dashboard__member-picker-name">' + escapeHtml(member && member.fullName ? String(member.fullName) : '#' + memberId) + '</span>' +
							metaHtml +
						'</span>' +
						statusHtml +
					'</label>' +
				'</li>';
		};

		MemberPicker.prototype.setSubmitting = function (isSubmitting) {
			this.state.submitting = !!isSubmitting;
			if (this.$confirm.length) {
				var disabled = this.state.submitting || this.getSelectionCount() === 0;
				this.$confirm.prop('disabled', disabled);
			}
			if (this.$cancel.length) {
				this.$cancel.prop('disabled', this.state.submitting);
			}
			if (this.$close.length) {
				this.$close.prop('disabled', this.state.submitting);
			}
			if (this.$search.length) {
				this.$search.prop('disabled', this.state.submitting);
			}
			if (this.$list.length) {
				this.$list.find('[data-role="member-picker-checkbox"]').each(function () {
					var $checkbox = $(this);
					var assigned = $checkbox.attr('data-assigned') === '1';
					var eligible = $checkbox.attr('data-eligible') !== '0';
					$checkbox.prop('disabled', assigned || !eligible || isSubmitting);
				});
			}
		};

		MemberPicker.prototype.flagSubmissionErrors = function (errors) {
			if (!this.$list.length) {
				return;
			}

			var self = this;
			var errorMap = {};
			if (Array.isArray(errors)) {
				for (var i = 0; i < errors.length; i += 1) {
					var entry = errors[i];
					if (!entry) {
						continue;
					}
					var memberId = toInt(entry.memberId);
					if (memberId === null) {
						continue;
					}
					var message = entry.message ? String(entry.message) : '';
					errorMap[memberId] = message;
				}
			}

			this.$list.find('[data-role="member-picker-item"]').each(function () {
				var $item = $(this);
				var memberId = toInt($item.attr('data-member-id'));
				var $option = $item.find('.mj-animateur-dashboard__member-picker-option');
				var $error = $option.find('[data-role="member-picker-error"]');
				if (memberId !== null && Object.prototype.hasOwnProperty.call(errorMap, memberId)) {
					var text = errorMap[memberId];
					if (!$error.length) {
						$error = $('<span class="mj-animateur-dashboard__member-picker-status mj-animateur-dashboard__member-picker-status--error" data-role="member-picker-error"></span>');
						$option.append($error);
					}
					$error.text(text || self.dashboard.translate('memberPickerSubmitError', "Impossible d'ajouter les membres sélectionnés."));
					$item.addClass('has-error');
				} else {
					if ($error.length) {
						$error.remove();
					}
					$item.removeClass('has-error');
				}
			});
		};

		MemberPicker.prototype.submitSelection = function () {
			if (this.state.submitting || this.state.loading) {
				return;
			}

			var ids = [];
			for (var key in this.selected) {
				if (Object.prototype.hasOwnProperty.call(this.selected, key)) {
					var parsed = toInt(key);
					if (parsed !== null && parsed > 0) {
						ids.push(parsed);
					}
				}
			}

			if (!ids.length) {
				this.showFeedback(this.dashboard.translate('memberPickerSelectionEmpty', 'Sélectionnez au moins un membre.'), 'error');
				return;
			}

			this.setSubmitting(true);
			this.showFeedback('', null);

			var scope = this.dashboard ? this.dashboard.buildRegistrationScope(this.state.eventId) : { mode: 'all', occurrences: [] };
			var self = this;
			$.ajax({
				url: this.dashboard.global.ajaxUrl,
				method: 'POST',
				dataType: 'json',
				data: {
					action: this.dashboard.global.actions ? this.dashboard.global.actions.memberAdd : 'mj_member_animateur_add_members',
					nonce: this.dashboard.global.nonce || '',
					event_id: this.state.eventId,
					member_ids: JSON.stringify(ids),
					occurrence_scope: JSON.stringify(scope)
				}
			}).done(function (response) {
				if (!response || !response.success || !response.data) {
					var defaultError = self.dashboard.translate('memberPickerSubmitError', 'Impossible d\'ajouter les membres sélectionnés.');
					self.showFeedback(defaultError, 'error');
					self.dashboard.showFeedback('attendance', 'error', defaultError);
					return;
				}

				var data = response.data;
				if (data.event) {
					self.dashboard.updateEventSnapshot(data.event);
				}

				var messageKey = data.messageKey || 'memberPickerSubmitSuccess';
				var messageLookup = {
					memberPickerSubmitSuccess: self.dashboard.translate('memberPickerSubmitSuccess', 'Participants ajoutés.'),
					memberPickerSubmitPartial: self.dashboard.translate('memberPickerSubmitPartial', 'Certains membres n\'ont pas pu être ajoutés.'),
					memberPickerSubmitError: self.dashboard.translate('memberPickerSubmitError', 'Impossible d\'ajouter les membres sélectionnés.'),
					memberPickerAlreadyAssigned: self.dashboard.translate('memberPickerAlreadyAssigned', 'Déjà inscrit')
				};
				var message = messageLookup[messageKey] || messageLookup.memberPickerSubmitSuccess;

				var hasAdded = Array.isArray(data.added) && data.added.length > 0;
				var hasErrors = Array.isArray(data.errors) && data.errors.length > 0;
				var hasAlready = Array.isArray(data.alreadyAssigned) && data.alreadyAssigned.length > 0;
				var detailMessages = [];
				if (hasErrors) {
					for (var i = 0; i < data.errors.length; i += 1) {
						var errorEntry = data.errors[i];
						if (errorEntry && errorEntry.message) {
							detailMessages.push(String(errorEntry.message));
						}
					}
					self.pendingErrors = data.errors.slice();
				} else {
					self.pendingErrors = [];
				}

				if (detailMessages.length) {
					message += ' ' + detailMessages.join(' ');
				}

				if (hasAdded && !hasErrors && !hasAlready) {
					self.dashboard.showFeedback('attendance', 'success', message);
					self.selected = {};
					self.pendingErrors = [];
					self.close();
					return;
				}

				if (hasAdded) {
					self.dashboard.showFeedback('attendance', 'success', message);
				} else if (hasErrors || hasAlready) {
					self.dashboard.showFeedback('attendance', 'error', message);
				} else {
					self.dashboard.showFeedback('attendance', 'info', message);
				}

				self.showFeedback(message, hasErrors ? 'error' : (hasAdded ? 'success' : 'info'));

				if (hasErrors) {
					if (!hasAdded) {
						self.flagSubmissionErrors(data.errors);
					}
				} else {
					self.flagSubmissionErrors([]);
				}

				self.selected = {};
				if (hasAdded) {
					self.fetchMembers(true);
				} else {
					self.updateSelectionState();
				}
			}).fail(function () {
				var failureMessage = self.dashboard.translate('memberPickerSubmitError', 'Impossible d\'ajouter les membres sélectionnés.');
				self.showFeedback(failureMessage, 'error');
				self.dashboard.showFeedback('attendance', 'error', failureMessage);
			}).always(function () {
				self.setSubmitting(false);
				self.updateSelectionState();
			});
		};

	$(function () {
		$('.mj-animateur-dashboard').each(function () {
			var $root = $(this);
			var config = parseConfig($root) || {};
			new Dashboard($root, config);
		});
	});

})(jQuery);
