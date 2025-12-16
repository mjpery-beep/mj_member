(function () {
    'use strict';

    var runtimeConfig = window.mjMemberIdeaBox || {};
    var Utils = window.MjMemberUtils || {};
    var escapeHtml = typeof Utils.escapeHtml === 'function' ? Utils.escapeHtml : function (value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    var domReady = typeof Utils.domReady === 'function' ? Utils.domReady : function (callback) {
        if (typeof callback !== 'function') {
            return;
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    };

    function toInt(value, fallback) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed)) {
            return fallback;
        }
        return parsed;
    }

    function getString(i18n, key, fallback) {
        if (i18n && typeof i18n === 'object' && typeof i18n[key] === 'string' && i18n[key] !== '') {
            return i18n[key];
        }
        return fallback;
    }

    function normalizeRole(value) {
        if (typeof value !== 'string') {
            return '';
        }
        return value.trim().toLowerCase();
    }

    function parseDatasetConfig(root) {
        if (!root || !root.getAttribute) {
            return {};
        }
        var raw = root.getAttribute('data-config');
        if (!raw) {
            return {};
        }
        try {
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function IdeaBox(root, config, runtime) {
        this.root = root;
        this.config = config || {};
        this.runtime = runtime || {};

        this.ajaxUrl = typeof runtime.ajaxUrl === 'string' ? runtime.ajaxUrl : '';
        this.actions = runtime.actions && typeof runtime.actions === 'object' ? runtime.actions : {};
        this.nonce = typeof runtime.nonce === 'string' ? runtime.nonce : '';

        this.i18n = runtime.i18n || {};
        this.allowSubmission = !!(config && (config.allowSubmission === undefined ? true : config.allowSubmission));
        this.hasAccess = !!runtime.hasAccess;
        this.isPreview = !!config.preview;
        if (this.isPreview) {
            this.hasAccess = true;
        }

        this.maxLengths = runtime && runtime.maxLengths && typeof runtime.maxLengths === 'object' ? runtime.maxLengths : {};

        this.viewer = this.normalizeMember(runtime.member || {});
        if (!this.viewer.id && typeof runtime.memberId === 'number') {
            this.viewer.id = runtime.memberId;
        }

        this.contentMaxLength = this.getMaxLength('content');

        this.state = {
            loading: !this.isPreview && this.hasAccess,
            ideas: this.isPreview ? this.normalizeIdeas(config.previewData && config.previewData.ideas) : [],
            error: '',
            submitting: false,
        };

        this.pendingVotes = new Set();
        this.pendingDeletes = new Set();

        this.dom = {
            inner: null,
            title: null,
            intro: null,
            feedback: null,
            list: null,
            form: null,
            titleInput: null,
            contentInput: null,
            contentCounter: null,
            submit: null,
            empty: null,
        };
    }

    IdeaBox.prototype.normalizeMember = function (member) {
        if (!member || typeof member !== 'object') {
            return { id: 0, name: '', role: '' };
        }
        var id = toInt(member.id, 0);
        var name = typeof member.name === 'string' ? member.name : '';
        var role = normalizeRole(member.role);
        return { id: id, name: name, role: role };
    };

    IdeaBox.prototype.normalizeIdea = function (idea) {
        if (!idea || typeof idea !== 'object') {
            return null;
        }

        var id = toInt(idea.id, 0);
        if (!id) {
            return null;
        }

        var voteCount = toInt(idea.voteCount !== undefined ? idea.voteCount : idea.vote_count, 0);
        if (voteCount < 0) {
            voteCount = 0;
        }

        var author = idea.author && typeof idea.author === 'object' ? idea.author : {};
        var normalizedAuthor = {
            id: toInt(author.id, 0),
            name: typeof author.name === 'string' ? author.name : '',
            role: typeof author.role === 'string' ? author.role : '',
        };

        return {
            id: id,
            title: typeof idea.title === 'string' ? idea.title : '',
            content: typeof idea.content === 'string' ? idea.content : '',
            status: typeof idea.status === 'string' ? idea.status : 'published',
            voteCount: voteCount,
            createdAt: typeof idea.createdAt === 'string' ? idea.createdAt : (typeof idea.created_at === 'string' ? idea.created_at : ''),
            updatedAt: typeof idea.updatedAt === 'string' ? idea.updatedAt : (typeof idea.updated_at === 'string' ? idea.updated_at : ''),
            author: normalizedAuthor,
            viewerHasVoted: !!(idea.viewerHasVoted !== undefined ? idea.viewerHasVoted : idea.viewer_has_voted),
            isOwner: !!(idea.isOwner !== undefined ? idea.isOwner : idea.is_owner),
            canDelete: !!(idea.canDelete !== undefined ? idea.canDelete : idea.can_delete),
        };
    };

    IdeaBox.prototype.normalizeIdeas = function (ideas) {
        if (!Array.isArray(ideas)) {
            return [];
        }
        var self = this;
        var normalized = [];
        ideas.forEach(function (idea) {
            var normalizedIdea = self.normalizeIdea(idea);
            if (normalizedIdea) {
                normalized.push(normalizedIdea);
            }
        });
        return self.sortIdeas(normalized);
    };

    IdeaBox.prototype.getMaxLength = function (field) {
        if (typeof field !== 'string') {
            return 0;
        }
        if (this.maxLengths && typeof this.maxLengths[field] === 'number') {
            var candidate = this.maxLengths[field];
            if (!isNaN(candidate) && candidate > 0) {
                return candidate;
            }
        }
        if (field === 'title') {
            return 180;
        }
        if (field === 'content') {
            return 1000;
        }
        return 0;
    };

    IdeaBox.prototype.canViewerDelete = function () {
        if (!this.viewer || typeof this.viewer.role !== 'string') {
            return false;
        }
        var role = normalizeRole(this.viewer.role);
        return role === 'animateur' || role === 'coordinateur';
    };

    IdeaBox.prototype.formatCounter = function (current, max) {
        var template = getString(this.i18n, 'characterCount', '%1$s / %2$s caractères');
        var currentValue = String(Math.max(0, current || 0));
        var maxValue = max && max > 0 ? String(max) : '—';
        return template.replace('%1$s', currentValue).replace('%2$s', maxValue);
    };

    IdeaBox.prototype.updateContentCounter = function () {
        if (!this.dom.contentCounter) {
            return;
        }
        var value = '';
        if (this.dom.contentInput && typeof this.dom.contentInput.value === 'string') {
            value = this.dom.contentInput.value;
        }
        var max = this.getMaxLength('content');
        var length = value ? value.length : 0;
        if (max > 0 && length > max) {
            length = max;
        }
        this.dom.contentCounter.textContent = this.formatCounter(length, max);
    };

    IdeaBox.prototype.removeIdea = function (ideaId) {
        if (!Array.isArray(this.state.ideas) || this.state.ideas.length === 0) {
            return;
        }
        var id = toInt(ideaId, 0);
        if (!id) {
            return;
        }
        this.state.ideas = this.state.ideas.filter(function (idea) {
            return idea.id !== id;
        });
    };

    IdeaBox.prototype.sortIdeas = function (ideas) {
        return ideas.sort(function (a, b) {
            if (b.voteCount !== a.voteCount) {
                return b.voteCount - a.voteCount;
            }
            var dateA = a.updatedAt || a.createdAt;
            var dateB = b.updatedAt || b.createdAt;
            if (dateA && dateB) {
                var parsedA = Date.parse(dateA.replace(' ', 'T')) || 0;
                var parsedB = Date.parse(dateB.replace(' ', 'T')) || 0;
                return parsedB - parsedA;
            }
            return b.id - a.id;
        });
    };

    IdeaBox.prototype.buildBase = function () {
        var inner = document.createElement('div');
        inner.className = 'mj-idea-box__inner';

        var header = document.createElement('header');
        header.className = 'mj-idea-box__header';

        var title = document.createElement('h2');
        title.className = 'mj-idea-box__title';
        header.appendChild(title);

        var intro = document.createElement('div');
        intro.className = 'mj-idea-box__intro';
        header.appendChild(intro);

        inner.appendChild(header);

        var feedback = document.createElement('div');
        feedback.className = 'mj-idea-box__feedback';
        feedback.setAttribute('role', 'alert');
        feedback.setAttribute('aria-live', 'polite');
        inner.appendChild(feedback);

        if (this.allowSubmission) {
            var form = document.createElement('form');
            form.className = 'mj-idea-box__form';
            form.setAttribute('novalidate', 'novalidate');

            var titleField = document.createElement('input');
            titleField.type = 'text';
            titleField.className = 'mj-idea-box__form-title';
            var titleMax = this.getMaxLength('title');
            if (titleMax > 0) {
                titleField.maxLength = titleMax;
            }
            titleField.placeholder = getString(this.i18n, 'titlePlaceholder', 'Titre de votre idée');
            titleField.required = true;
            form.appendChild(titleField);

            var contentField = document.createElement('textarea');
            contentField.className = 'mj-idea-box__form-content';
            contentField.rows = 3;
            if (this.contentMaxLength > 0) {
                contentField.maxLength = this.contentMaxLength;
            }
            contentField.placeholder = getString(this.i18n, 'contentPlaceholder', 'Décrivez votre idée…');
            form.appendChild(contentField);

            var counter = document.createElement('p');
            counter.className = 'mj-idea-box__form-counter';
            form.appendChild(counter);

            var submit = document.createElement('button');
            submit.type = 'submit';
            submit.className = 'mj-idea-box__form-submit';
            submit.textContent = getString(this.i18n, 'submit', 'Partager');
            form.appendChild(submit);

            inner.appendChild(form);

            this.dom.form = form;
            this.dom.titleInput = titleField;
            this.dom.contentInput = contentField;
            this.dom.contentCounter = counter;
            this.dom.submit = submit;
        }

        var empty = document.createElement('p');
        empty.className = 'mj-idea-box__empty';
        inner.appendChild(empty);

        var list = document.createElement('div');
        list.className = 'mj-idea-box__list';
        list.setAttribute('role', 'list');
        inner.appendChild(list);

        this.dom.inner = inner;
        this.dom.title = title;
        this.dom.intro = intro;
        this.dom.feedback = feedback;
        this.dom.list = list;
        this.dom.empty = empty;

        this.root.innerHTML = '';
        this.root.appendChild(inner);

        this.updateContentCounter();
    };

    IdeaBox.prototype.render = function () {
        if (!this.dom.inner) {
            this.buildBase();
            this.bindEvents();
        }

        this.root.classList.toggle('is-loading', !!this.state.loading);

        if (this.dom.title) {
            this.dom.title.textContent = this.config.title ? this.config.title : '';
            this.dom.title.style.display = this.config.title ? '' : 'none';
        }

        if (this.dom.intro) {
            if (this.config.intro) {
                this.dom.intro.innerHTML = this.config.intro;
                this.dom.intro.style.display = '';
            } else {
                this.dom.intro.innerHTML = '';
                this.dom.intro.style.display = 'none';
            }
        }

        if (this.dom.feedback) {
            if (this.state.error) {
                this.dom.feedback.textContent = this.state.error;
                this.dom.feedback.style.display = '';
            } else if (!this.hasAccess && !this.isPreview) {
                this.dom.feedback.textContent = getString(this.i18n, 'accessDenied', 'Vous devez être connecté pour participer.');
                this.dom.feedback.style.display = '';
            } else {
                this.dom.feedback.textContent = '';
                this.dom.feedback.style.display = 'none';
            }
        }

        if (this.dom.form) {
            var canSubmit = this.allowSubmission && this.hasAccess;
            this.dom.form.style.display = canSubmit ? '' : 'none';
            if (this.dom.titleInput) {
                this.dom.titleInput.disabled = !canSubmit || this.state.submitting;
            }
            if (this.dom.contentInput) {
                this.dom.contentInput.disabled = !canSubmit || this.state.submitting;
            }
            if (this.dom.submit) {
                this.dom.submit.disabled = !canSubmit || this.state.submitting;
            }
            if (this.dom.contentCounter) {
                this.dom.contentCounter.style.display = canSubmit ? '' : 'none';
                this.updateContentCounter();
            }
        }

        this.renderIdeas();
    };

    IdeaBox.prototype.renderIdeas = function () {
        if (!this.dom.list) {
            return;
        }

        var ideas = this.state.ideas;
        if (!Array.isArray(ideas) || ideas.length === 0) {
            if (this.dom.empty) {
                this.dom.empty.textContent = this.state.loading
                    ? getString(this.i18n, 'loading', 'Chargement des idées…')
                    : getString(this.i18n, 'empty', 'Aucune idée proposée pour le moment.');
                this.dom.empty.style.display = '';
            }
            this.dom.list.innerHTML = '';
            return;
        }

        if (this.dom.empty) {
            this.dom.empty.textContent = '';
            this.dom.empty.style.display = 'none';
        }

        var canVote = this.hasAccess;
        var voteLabel = getString(this.i18n, 'voteLabel', '+1');
        var votesOne = getString(this.i18n, 'votesOne', '%d soutien');
        var votesMany = getString(this.i18n, 'votesMany', '%d soutiens');
        var deleteLabel = getString(this.i18n, 'deleteLabel', 'Supprimer');

        var markup = ideas.map(function (idea) {
            var titleHtml = idea.title ? '<h3 class="mj-idea-box__item-title">' + escapeHtml(idea.title) + '</h3>' : '';
            var lines = idea.content ? idea.content.split(/\r?\n/) : [];
            var contentHtml = lines.length > 0
                ? '<p class="mj-idea-box__item-content">' + lines.map(function (line) {
                    return escapeHtml(line);
                }).join('<br>') + '</p>'
                : '';

            var authorParts = [];
            if (idea.author && idea.author.name) {
                authorParts.push(escapeHtml(idea.author.name));
            }
            if (idea.author && idea.author.role) {
                authorParts.push('<span class="mj-idea-box__item-role">' + escapeHtml(idea.author.role) + '</span>');
            }
            var metaAuthor = authorParts.length > 0 ? authorParts.join(' · ') : '';

            var formattedDate = idea.createdAt ? formatDate(idea.createdAt, this.i18n) : '';
            var metaInfo = '';
            if (metaAuthor || formattedDate) {
                var pieces = [];
                if (metaAuthor) {
                    pieces.push(metaAuthor);
                }
                if (formattedDate) {
                    pieces.push(escapeHtml(formattedDate));
                }
                metaInfo = '<p class="mj-idea-box__item-meta">' + pieces.join(' • ') + '</p>';
            }

            var count = idea.voteCount || 0;
            var countLabel = count === 1 ? votesOne.replace('%d', '1') : votesMany.replace('%d', String(count));
            var isActive = !!idea.viewerHasVoted;
            var isOwn = !!idea.isOwner;
            var votePending = this.pendingVotes && typeof this.pendingVotes.has === 'function' ? this.pendingVotes.has(idea.id) : false;
            var disabled = !canVote || isOwn || votePending;

            var buttonClasses = ['mj-idea-box__vote-button'];
            if (isActive) {
                buttonClasses.push('is-active');
            }
            if (disabled) {
                buttonClasses.push('is-disabled');
            }

            var ariaPressed = isActive ? 'true' : 'false';
            var buttonAttrs = 'type="button" class="' + buttonClasses.join(' ') + '" data-idea-id="' + idea.id + '" aria-pressed="' + ariaPressed + '"';
            if (disabled) {
                buttonAttrs += ' disabled';
            }

            var canDelete = idea.canDelete !== undefined ? !!idea.canDelete : this.canViewerDelete();
            var isDeleting = this.pendingDeletes && typeof this.pendingDeletes.has === 'function' ? this.pendingDeletes.has(idea.id) : false;
            var deleteButtonHtml = '';
            if (canDelete) {
                var deleteClasses = ['mj-idea-box__delete-button'];
                if (isDeleting) {
                    deleteClasses.push('is-disabled');
                }
                deleteButtonHtml = '<button type="button" class="' + deleteClasses.join(' ') + '" data-idea-id="' + idea.id + '"';
                if (isDeleting) {
                    deleteButtonHtml += ' disabled';
                }
                deleteButtonHtml += '>' + escapeHtml(deleteLabel) + '</button>';
            }

            return (
                '<article class="mj-idea-box__item" role="listitem" data-idea-id="' + idea.id + '">' +
                    '<div class="mj-idea-box__item-header">' +
                        titleHtml +
                        metaInfo +
                    '</div>' +
                    contentHtml +
                    '<div class="mj-idea-box__item-actions">' +
                        '<div class="mj-idea-box__item-vote">' +
                            '<button ' + buttonAttrs + '>' + escapeHtml(voteLabel) + '</button>' +
                            '<span class="mj-idea-box__vote-count" aria-live="polite">' + escapeHtml(countLabel) + '</span>' +
                        '</div>' +
                        deleteButtonHtml +
                    '</div>' +
                '</article>'
            );
        }, this).join('');

        this.dom.list.innerHTML = markup;
    };

    function formatDate(value, i18n) {
        if (!value || typeof value !== 'string') {
            return '';
        }
        var parsed = Date.parse(value.replace(' ', 'T'));
        if (isNaN(parsed)) {
            return value;
        }
        var date = new Date(parsed);
        var now = new Date();
        var diff = now.getTime() - date.getTime();
        if (diff < 60000) {
            return getString(i18n, 'justNow', 'À l’instant');
        }
        try {
            return date.toLocaleString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch (error) {
            return value;
        }
    }

    IdeaBox.prototype.bindEvents = function () {
        var self = this;
        if (this.dom.form) {
            this.dom.form.addEventListener('submit', function (event) {
                event.preventDefault();
                self.handleSubmit();
            });
        }

        if (this.dom.contentInput) {
            this.dom.contentInput.addEventListener('input', function () {
                self.updateContentCounter();
            });
        }

        if (this.dom.list) {
            this.dom.list.addEventListener('click', function (event) {
                var target = event.target;
                if (!target) {
                    return;
                }
                if (typeof target.closest === 'function') {
                    target = target.closest('button');
                }
                if (!target || target.tagName !== 'BUTTON') {
                    return;
                }
                if (target.matches('.mj-idea-box__vote-button')) {
                    var ideaId = toInt(target.getAttribute('data-idea-id'), 0);
                    if (!ideaId) {
                        return;
                    }
                    self.handleVote(ideaId, target);
                    return;
                }
                if (target.matches('.mj-idea-box__delete-button')) {
                    var deleteId = toInt(target.getAttribute('data-idea-id'), 0);
                    if (!deleteId) {
                        return;
                    }
                    self.handleDelete(deleteId, target);
                }
            });
        }
    };

    IdeaBox.prototype.handleSubmit = function () {
        if (!this.hasAccess || !this.allowSubmission || this.state.submitting) {
            return;
        }

        var title = this.dom.titleInput ? this.dom.titleInput.value.trim() : '';
        var content = this.dom.contentInput ? this.dom.contentInput.value.trim() : '';

        if (title === '') {
            this.state.error = getString(this.i18n, 'titleError', 'Merci de saisir un titre.');
            this.render();
            return;
        }

        var maxContentLength = this.getMaxLength('content');
        if (maxContentLength > 0 && content.length > maxContentLength) {
            content = content.slice(0, maxContentLength);
            if (this.dom.contentInput) {
                this.dom.contentInput.value = content;
            }
        }

        if (content === '') {
            this.state.error = getString(this.i18n, 'formError', 'Merci de saisir une idée.');
            this.render();
            return;
        }

        this.state.error = '';
        this.state.submitting = true;
        this.render();

        if (!this.ajaxUrl || !this.actions.create) {
            this.state.error = getString(this.i18n, 'createError', 'Impossible d’enregistrer votre idée.');
            this.state.submitting = false;
            this.render();
            return;
        }

        var params = new URLSearchParams();
        params.append('action', this.actions.create);
        params.append('nonce', this.nonce);
        params.append('title', title);
        params.append('content', content);

        var self = this;
        fetch(this.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: params.toString(),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('http_error');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data || !payload.data.idea) {
                    throw new Error('api_error');
                }
                var idea = self.normalizeIdea(payload.data.idea);
                if (idea) {
                    self.upsertIdea(idea);
                    if (self.dom.titleInput) {
                        self.dom.titleInput.value = '';
                    }
                    if (self.dom.contentInput) {
                        self.dom.contentInput.value = '';
                        self.updateContentCounter();
                    }
                }
            })
            .catch(function () {
                self.state.error = getString(self.i18n, 'createError', 'Impossible d’enregistrer votre idée.');
            })
            .finally(function () {
                self.state.submitting = false;
                self.render();
            });
    };

    IdeaBox.prototype.upsertIdea = function (idea) {
        var ideas = Array.isArray(this.state.ideas) ? this.state.ideas.slice() : [];
        var index = ideas.findIndex(function (entry) {
            return entry.id === idea.id;
        });
        if (index === -1) {
            ideas.push(idea);
        } else {
            ideas[index] = idea;
        }
        this.state.ideas = this.sortIdeas(ideas);
    };

    IdeaBox.prototype.handleVote = function (ideaId, button) {
        if (!this.hasAccess || this.pendingVotes.has(ideaId)) {
            return;
        }

        var idea = this.state.ideas.find(function (entry) {
            return entry.id === ideaId;
        });
        if (!idea) {
            return;
        }

        if (idea.isOwner) {
            this.state.error = getString(this.i18n, 'voteOwnIdea', 'Vous ne pouvez pas voter pour votre propre idée.');
            this.render();
            return;
        }

        if (!this.ajaxUrl || !this.actions.vote) {
            this.state.error = getString(this.i18n, 'voteError', 'Impossible de mettre à jour le vote.');
            this.render();
            return;
        }

        this.state.error = '';
        this.pendingVotes.add(ideaId);
        button.disabled = true;

        var params = new URLSearchParams();
        params.append('action', this.actions.vote);
        params.append('nonce', this.nonce);
        params.append('idea_id', String(ideaId));
        params.append('vote', idea.viewerHasVoted ? 'remove' : 'add');

        var self = this;
        fetch(this.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: params.toString(),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('http_error');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data || !payload.data.idea) {
                    throw new Error('api_error');
                }
                var updated = self.normalizeIdea(payload.data.idea);
                if (updated) {
                    self.upsertIdea(updated);
                }
            })
            .catch(function () {
                self.state.error = getString(self.i18n, 'voteError', 'Impossible de mettre à jour le vote.');
            })
            .finally(function () {
                self.pendingVotes.delete(ideaId);
                self.render();
            });
    };

    IdeaBox.prototype.handleDelete = function (ideaId, button) {
        if (!this.hasAccess || this.isPreview || this.pendingDeletes.has(ideaId)) {
            return;
        }

        if (!this.canViewerDelete()) {
            this.state.error = getString(this.i18n, 'deleteError', 'Impossible de supprimer l’idée.');
            this.render();
            return;
        }

        var idea = this.state.ideas.find(function (entry) {
            return entry.id === ideaId;
        });
        if (!idea) {
            return;
        }

        var confirmMessage = getString(this.i18n, 'deleteConfirm', 'Confirmer la suppression de cette idée ?');
        if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
            if (!window.confirm(confirmMessage)) {
                return;
            }
        }

        if (!this.ajaxUrl || !this.actions.delete) {
            this.state.error = getString(this.i18n, 'deleteError', 'Impossible de supprimer l’idée.');
            this.render();
            return;
        }

        this.state.error = '';
        this.pendingDeletes.add(ideaId);
        button.disabled = true;

        var params = new URLSearchParams();
        params.append('action', this.actions.delete);
        params.append('nonce', this.nonce);
        params.append('idea_id', String(ideaId));

        var self = this;
        fetch(this.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: params.toString(),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('http_error');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success) {
                    throw new Error('api_error');
                }
                self.state.error = '';
                self.removeIdea(ideaId);
            })
            .catch(function () {
                self.state.error = getString(self.i18n, 'deleteError', 'Impossible de supprimer l’idée.');
            })
            .finally(function () {
                self.pendingDeletes.delete(ideaId);
                self.render();
            });
    };

    IdeaBox.prototype.fetchIdeas = function () {
        if (!this.hasAccess || !this.ajaxUrl || !this.actions.fetch) {
            this.state.loading = false;
            if (this.hasAccess) {
                this.state.error = getString(this.i18n, 'loadError', 'Impossible de charger les idées.');
            }
            this.render();
            return;
        }

        var self = this;
        this.state.loading = true;
        this.render();

        var params = new URLSearchParams();
        params.append('action', this.actions.fetch);
        params.append('nonce', this.nonce);

        fetch(this.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            },
            body: params.toString(),
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('http_error');
                }
                return response.json();
            })
            .then(function (payload) {
                if (!payload || !payload.success || !payload.data) {
                    throw new Error('api_error');
                }
                var data = payload.data;
                var ideas = self.normalizeIdeas(data.ideas || []);
                self.state.ideas = ideas;
                if (data.member) {
                    self.viewer = self.normalizeMember(data.member);
                }
                self.state.error = '';
            })
            .catch(function () {
                self.state.error = getString(self.i18n, 'loadError', 'Impossible de charger les idées.');
            })
            .finally(function () {
                self.state.loading = false;
                self.render();
            });
    };

    IdeaBox.prototype.init = function () {
        this.render();

        if (this.isPreview) {
            return;
        }

        if (!this.hasAccess) {
            this.render();
            return;
        }

        this.fetchIdeas();
    };

    domReady(function () {
        var nodes = document.querySelectorAll('[data-mj-member-idea-box]');
        if (!nodes || nodes.length === 0) {
            return;
        }

        for (var i = 0; i < nodes.length; i += 1) {
            var node = nodes[i];
            var config = parseDatasetConfig(node);
            var instance = new IdeaBox(node, config, runtimeConfig);
            instance.init();
        }
    });
})();
