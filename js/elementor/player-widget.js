(function () {
    'use strict';

    var API_BASE = 'https://www.googleapis.com/youtube/v3';
    var iframeApiPromise = null;
    var ICON_PREV = '‹';
    var ICON_NEXT = '›';

    // ── Utilities ────────────────────────────────────────────────────────────

    function parseJsonConfig(raw) {
        if (!raw || typeof raw !== 'string') {
            return {};
        }
        try {
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (e) {
            return {};
        }
    }

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) {
            node.className = cls;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    }

    function loadIframeApi() {
        if (iframeApiPromise) {
            return iframeApiPromise;
        }

        iframeApiPromise = new Promise(function (resolve, reject) {
            if (window.YT && window.YT.Player) {
                resolve(window.YT);
                return;
            }

            var prev = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                if (typeof prev === 'function') {
                    prev();
                }
                if (window.YT && window.YT.Player) {
                    resolve(window.YT);
                } else {
                    reject(new Error('YouTube API unavailable'));
                }
            };

            if (!document.querySelector('script[data-mj-yt-api]')) {
                var s = document.createElement('script');
                s.src = 'https://www.youtube.com/iframe_api';
                s.async = true;
                s.setAttribute('data-mj-yt-api', '1');
                s.onerror = function () {
                    reject(new Error('YouTube API failed to load'));
                };
                document.head.appendChild(s);
            }
        });

        return iframeApiPromise;
    }

    function fetchYT(path, params) {
        return fetch(API_BASE + path + '?' + new URLSearchParams(params).toString())
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            });
    }

    function postForm(url, data) {
        var body = new URLSearchParams();
        Object.keys(data).forEach(function (k) {
            if (data[k] !== null && data[k] !== undefined) {
                body.append(k, data[k]);
            }
        });
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
        }).then(function (r) {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status);
            }
            return r.json();
        });
    }

    function fromSearch(item) {
        var id = item && item.id && item.id.videoId ? String(item.id.videoId) : '';
        if (!id) {
            return null;
        }
        var s = item.snippet || {};
        return {
            id: id,
            title: s.title || 'Video',
            channel: s.channelTitle || '',
            thumb: (s.thumbnails && s.thumbnails.medium && s.thumbnails.medium.url) || '',
        };
    }

    function fromPlaylistItem(item) {
        var s = (item && item.snippet) || {};
        var id = s.resourceId && s.resourceId.videoId ? String(s.resourceId.videoId) : '';
        if (!id || s.title === 'Deleted video' || s.title === 'Private video') {
            return null;
        }
        return {
            id: id,
            title: s.title || 'Video',
            channel: s.videoOwnerChannelTitle || '',
            thumb: (s.thumbnails && s.thumbnails.medium && s.thumbnails.medium.url) || '',
        };
    }

    function safeTrack(t) {
        if (!t || typeof t !== 'object') {
            return null;
        }
        var id = typeof t.id === 'string' ? t.id.trim() : '';
        if (!id) {
            return null;
        }
        return {
            id: id,
            title: typeof t.title === 'string' ? t.title : 'Video',
            channel: typeof t.channel === 'string' ? t.channel : '',
            thumb: typeof t.thumb === 'string' ? t.thumb : '',
        };
    }

    function createTextButton(className, text, title) {
        var button = el('button', className, text);
        button.type = 'button';
        if (title) {
            button.title = title;
        }
        return button;
    }

    // ── PlayerWidget ─────────────────────────────────────────────────────────

    function PlayerWidget(root) {
        this.root = root;
        this.app = root.querySelector('.mj-player-widget__app');

        var cfg = parseJsonConfig(root.getAttribute('data-config'));
        this.apiKey = typeof cfg.apiKey === 'string' ? cfg.apiKey.trim() : '';
        this.defaultQuery = typeof cfg.defaultQuery === 'string' ? cfg.defaultQuery.trim() : '';
        this.maxResults = Math.max(3, Math.min(25, Number(cfg.maxResults) || 8));
        this.autoplay = !!cfg.autoplay;
        this.presets = Array.isArray(cfg.playlists) ? cfg.playlists : [];
        this.headerTitle = typeof cfg.headerTitle === 'string' ? cfg.headerTitle.trim() : '';
        this.headerSub = typeof cfg.headerSub === 'string' ? cfg.headerSub.trim() : '';
        this.screenLabel = typeof cfg.screenLabel === 'string' ? cfg.screenLabel.trim() : '';

        this.persist = window.mjMemberPlayerWidget || {};
        this.storageKey = 'mj_member_player_state';

        this.player = null;
        this.currentIndex = -1;
        this.currentTrack = null;
        this.saveTimer = null;
        this.statusResetTimer = null;

        this.view = 'playlist';
        this.searchResults = [];

        this.state = this._defaultState();
        this.el = {};
    }

    // ── State ────────────────────────────────────────────────────────────────

    PlayerWidget.prototype._genId = function () {
        return 'pl_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
    };

    PlayerWidget.prototype._defaultState = function () {
        return {
            playlists: [{ id: this._genId(), name: 'Ma Playlist', tracks: [] }],
            activePlaylistId: '',
        };
    };

    PlayerWidget.prototype._sanitizeState = function (raw) {
        var self = this;
        var src = raw && typeof raw === 'object' ? raw : {};
        var lists = (Array.isArray(src.playlists) ? src.playlists : [])
            .map(function (pl) {
                if (!pl || typeof pl !== 'object') {
                    return null;
                }
                var id = typeof pl.id === 'string' && pl.id.trim() ? pl.id.trim() : self._genId();
                var name = typeof pl.name === 'string' ? pl.name.trim().slice(0, 40) : '';
                if (!name) {
                    name = 'Playlist';
                }
                var tracks = (Array.isArray(pl.tracks) ? pl.tracks : []).map(safeTrack).filter(Boolean);
                return { id: id, name: name, tracks: tracks };
            })
            .filter(Boolean);

        if (!lists.length) {
            lists.push({ id: self._genId(), name: 'Ma Playlist', tracks: [] });
        }

        var activeId = typeof src.activePlaylistId === 'string' ? src.activePlaylistId : '';
        if (!lists.some(function (pl) { return pl.id === activeId; })) {
            activeId = lists[0].id;
        }

        return { playlists: lists, activePlaylistId: activeId };
    };

    PlayerWidget.prototype._getActive = function () {
        var id = this.state.activePlaylistId;
        for (var i = 0; i < this.state.playlists.length; i++) {
            if (this.state.playlists[i].id === id) {
                return this.state.playlists[i];
            }
        }
        if (this.state.playlists.length) {
            this.state.activePlaylistId = this.state.playlists[0].id;
            return this.state.playlists[0];
        }
        return null;
    };

    PlayerWidget.prototype._getTracks = function () {
        var pl = this._getActive();
        return pl ? pl.tracks : [];
    };

    // ── Persistence ──────────────────────────────────────────────────────────

    PlayerWidget.prototype._canPersist = function () {
        return !!(this.persist && this.persist.ajaxUrl && this.persist.nonce);
    };

    PlayerWidget.prototype._loadFromLocal = function () {
        try {
            var raw = window.localStorage.getItem(this.storageKey);
            if (!raw) {
                return null;
            }
            var parsed = JSON.parse(raw);
            return this._sanitizeState(parsed);
        } catch (e) {
            return null;
        }
    };

    PlayerWidget.prototype._saveToLocal = function () {
        try {
            window.localStorage.setItem(this.storageKey, JSON.stringify(this.state));
        } catch (e) {
            // Ignore quota/private-mode storage failures.
        }
    };

    PlayerWidget.prototype._loadFromDB = function () {
        if (!this._canPersist()) {
            return Promise.resolve(null);
        }
        var self = this;
        return postForm(this.persist.ajaxUrl, {
            action: 'mj_member_player_state_get',
            nonce: this.persist.nonce,
        })
            .then(function (r) {
                return r && r.success && r.data && r.data.state ? self._sanitizeState(r.data.state) : null;
            })
            .catch(function () {
                return null;
            });
    };

    PlayerWidget.prototype._saveNow = function () {
        this._saveToLocal();

        if (this._canPersist()) {
            postForm(this.persist.ajaxUrl, {
                action: 'mj_member_player_state_save',
                nonce: this.persist.nonce,
                state: JSON.stringify(this.state),
            }).catch(function () {});
        }
    };

    PlayerWidget.prototype._scheduleSave = function () {
        if (this.saveTimer) {
            clearTimeout(this.saveTimer);
        }
        var self = this;
        this.saveTimer = setTimeout(function () {
            self.saveTimer = null;
            self._saveNow();
        }, 500);
    };

    // ── Init ─────────────────────────────────────────────────────────────────

    PlayerWidget.prototype.init = function () {
        if (!this.app) {
            return;
        }
        var self = this;

        this._renderLayout();

        this._loadFromDB().then(function (saved) {
            if (saved) {
                self.state = saved;
            } else {
                var localState = self._loadFromLocal();
                if (localState) {
                    self.state = localState;
                } else {
                    self.state = self._sanitizeState(self.state);
                }
            }
            self._renderTabs();
            self._renderContent();

            // On load, keep focus on the first available playlist.
            self.view = 'playlist';
            self._renderContent();

            if (!self.apiKey) {
                self._setStatus('Cle API YouTube manquante - configure-la dans le widget Elementor.', true);
                return;
            }

            self._ensurePlayer().then(function () {
                self._setStatus('Pret - playlist chargee.', false);
            });
        });
    };

    // ── Layout ───────────────────────────────────────────────────────────────

    PlayerWidget.prototype._renderLayout = function () {
        var self = this;
        this.app.innerHTML = '';

        var machine = el('div', 'jb-machine');
        var headerTitle = this.headerTitle || (String.fromCharCode(9834) + ' JUKEBOX');
        var headerSub = this.headerSub || 'Selection musicale';

        // Header chrome strip
        var header = el('div', 'jb-header');
        header.appendChild(el('span', 'jb-header__logo', headerTitle));
        header.appendChild(el('span', 'jb-header__sub', headerSub));
        machine.appendChild(header);

        // Phosphor screen: now playing
        var screen = el('div', 'jb-screen');
        var nowTitle = el('div', 'jb-screen__title', 'Selectionne un morceau');
        var statusLine = el('div', 'jb-screen__status', '');
        screen.appendChild(nowTitle);
        screen.appendChild(statusLine);
        machine.appendChild(screen);

        // Playback controls
        var controls = el('div', 'jb-controls');
        var btnPrev = el('button', 'jb-ctrl jb-ctrl--prev');
        btnPrev.textContent = ICON_PREV;
        var btnPlay = el('button', 'jb-ctrl jb-ctrl--play');
        btnPlay.textContent = '';
        btnPlay.setAttribute('aria-label', 'Lire');
        var btnNext = el('button', 'jb-ctrl jb-ctrl--next');
        btnNext.textContent = ICON_NEXT;
        btnPrev.type = btnPlay.type = btnNext.type = 'button';
        controls.appendChild(btnPrev);
        controls.appendChild(btnPlay);
        controls.appendChild(btnNext);
        machine.appendChild(controls);

        btnPrev.addEventListener('click', function () { self._prev(); });
        btnPlay.addEventListener('click', function () { self._togglePlay(); });
        btnNext.addEventListener('click', function () { self._next(); });

        // Playlist tabs
        var tabsBar = el('div', 'jb-tabs');
        var tabsList = el('div', 'jb-tabs__list');
        var addWrap = el('div', 'jb-tab__add-wrap');
        var addInput = el('input', 'jb-tab__add-input');
        addInput.type = 'text';
        addInput.placeholder = 'Nom playlist...';
        var addBtn = el('button', 'jb-tab__add-btn', '+');
        addBtn.type = 'button';
        addWrap.appendChild(addInput);
        addWrap.appendChild(addBtn);
        tabsBar.appendChild(tabsList);
        tabsBar.appendChild(addWrap);
        machine.appendChild(tabsBar);

        addBtn.addEventListener('click', function () {
            self._createPlaylist(addInput.value);
            addInput.value = '';
        });
        addInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                self._createPlaylist(addInput.value);
                addInput.value = '';
            }
        });

        // Search bar
        var searchBar = el('div', 'jb-search');
        var searchInput = el('input', 'jb-search__input');
        searchInput.type = 'search';
        searchInput.placeholder = 'Rechercher un morceau sur YouTube...';
        var searchBtn = el('button', 'jb-search__btn', 'Chercher');
        searchBtn.type = 'button';
        searchBar.appendChild(searchInput);
        searchBar.appendChild(searchBtn);
        machine.appendChild(searchBar);

        searchBtn.addEventListener('click', function () { self.search(searchInput.value); });
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                self.search(searchInput.value);
            }
        });

        // Content area
        var content = el('div', 'jb-content');
        var nav = el('div', 'jb-content__nav');
        var backBtn = el('button', 'jb-content__back', String.fromCharCode(8592) + ' Selection');
        backBtn.type = 'button';
        backBtn.style.display = 'none';
        var viewLabel = el('span', 'jb-content__view-label', 'Selection');
        var countLabel = el('span', 'jb-content__count', '');
        nav.appendChild(backBtn);
        nav.appendChild(viewLabel);
        nav.appendChild(countLabel);
        var trackList = el('div', 'jb-tracks');
        content.appendChild(nav);
        content.appendChild(trackList);
        machine.appendChild(content);

        backBtn.addEventListener('click', function () {
            self.view = 'playlist';
            self._renderContent();
        });

        // Preset playlists (YouTube source buttons)
        if (this.presets.length > 0) {
            var presetsRow = el('div', 'jb-presets');
            presetsRow.appendChild(el('span', 'jb-presets__label', 'Sources YouTube :'));
            this.presets.forEach(function (preset) {
                var name = typeof preset.name === 'string' ? preset.name : 'Playlist';
                var btn = el('button', 'jb-preset__btn', name);
                btn.type = 'button';
                btn.addEventListener('click', function () { self._loadPreset(preset, true); });
                presetsRow.appendChild(btn);
            });
            machine.appendChild(presetsRow);
        }

        // Hidden YouTube player
        var hiddenPlayer = el('div', 'jb-hidden-player');
        machine.appendChild(hiddenPlayer);

        // Bottom chrome strip
        machine.appendChild(el('div', 'jb-chrome-bottom'));

        this.app.appendChild(machine);

        this.el = {
            nowTitle: nowTitle,
            status: statusLine,
            btnPlay: btnPlay,
            tabsList: tabsList,
            trackList: trackList,
            backBtn: backBtn,
            viewLabel: viewLabel,
            countLabel: countLabel,
            hiddenPlayer: hiddenPlayer,
        };
    };

    // ── Tabs ─────────────────────────────────────────────────────────────────

    PlayerWidget.prototype._renderTabs = function () {
        var self = this;
        var container = this.el.tabsList;
        if (!container) {
            return;
        }
        container.innerHTML = '';

        this.state.playlists.forEach(function (pl) {
            var isActive = pl.id === self.state.activePlaylistId;
            var tab = el('div', 'jb-tab' + (isActive ? ' jb-tab--active' : ''));
            var nameBtn = el('button', 'jb-tab__name', pl.name);
            nameBtn.type = 'button';
            nameBtn.addEventListener('click', function () { self._switchPlaylist(pl.id); });
            var delBtn = el('button', 'jb-tab__del', 'x');
            delBtn.type = 'button';
            delBtn.setAttribute('aria-label', 'Supprimer la playlist');
            delBtn.addEventListener('click', function () { self._deletePlaylist(pl.id); });
            tab.appendChild(nameBtn);
            tab.appendChild(delBtn);
            container.appendChild(tab);
        });
    };

    // ── Content rendering ────────────────────────────────────────────────────

    PlayerWidget.prototype._renderContent = function () {
        var self = this;
        var container = this.el.trackList;
        if (!container) {
            return;
        }
        container.innerHTML = '';

        if (this.view === 'results') {
            this.el.backBtn.style.display = '';
            this.el.viewLabel.textContent = 'Resultats';
            this.el.countLabel.textContent = this.searchResults.length + ' resultat(s)';

            if (!this.searchResults.length) {
                container.appendChild(el('p', 'jb-content__hint', 'Aucun resultat.'));
                return;
            }

            this.searchResults.forEach(function (track) {
                var row = el('div', 'jb-track');
                var thumb = document.createElement('img');
                thumb.className = 'jb-track__thumb';
                thumb.src = track.thumb || '';
                thumb.alt = '';
                var actions = el('div', 'jb-track__actions');
                var info = el('div', 'jb-track__info');
                info.appendChild(el('span', 'jb-track__title', track.title));
                info.appendChild(el('span', 'jb-track__artist', track.channel));
                var playBtn = createTextButton('jb-track__btn jb-track__btn--play', '\u25ba', 'Lire sans ajouter');
                var addBtn = createTextButton('jb-track__btn jb-track__btn--add', '+', 'Ajouter a la playlist active');
                playBtn.addEventListener('click', function () { self._playTrack(track); });
                addBtn.addEventListener('click', function () { self._addTrack(track); });
                row.appendChild(thumb);
                row.appendChild(info);
                actions.appendChild(playBtn);
                actions.appendChild(addBtn);
                row.appendChild(actions);
                container.appendChild(row);
            });
            return;
        }

        // Playlist view
        var pl = this._getActive();
        var plName = pl ? pl.name : 'Selection';
        this.el.backBtn.style.display = 'none';
        this.el.viewLabel.textContent = plName;

        var tracks = this._getTracks();
        this.el.countLabel.textContent = tracks.length + ' titre(s)';

        if (!tracks.length) {
            container.appendChild(el('p', 'jb-content__hint', 'Playlist vide - recherche des morceaux et ajoute-les.'));
            return;
        }

        tracks.forEach(function (track, i) {
            var isActive = i === self.currentIndex;
            var row = el('div', 'jb-track' + (isActive ? ' is-active' : ''));
            var thumb = document.createElement('img');
            thumb.className = 'jb-track__thumb';
            thumb.src = track.thumb || '';
            thumb.alt = '';
            var titleWrap = el('div', 'jb-track__title-wrap');
            var titleText = el('span', 'jb-track__title', track.title);
            var titleInput = el('input', 'jb-track__title-input');
            titleInput.type = 'text';
            titleInput.value = track.title || '';
            titleInput.maxLength = 80;
            titleInput.style.display = 'none';
            var titleEditBtn = createTextButton('jb-track__btn jb-track__btn--rename', '✎', 'Renommer le titre');
            var info = el('div', 'jb-track__info');
            titleWrap.appendChild(titleText);
            titleWrap.appendChild(titleInput);
            titleWrap.appendChild(titleEditBtn);
            info.appendChild(titleWrap);
            info.appendChild(el('span', 'jb-track__artist', track.channel));
            var actions = el('div', 'jb-track__actions');
            var playBtn = createTextButton('jb-track__btn jb-track__btn--play', isActive ? '\u23f8' : '\u25ba', isActive ? 'Mettre en pause' : 'Lire');
            (function (idx, active) {
                playBtn.addEventListener('click', function () {
                    if (active) {
                        self._togglePlay();
                    } else {
                        self._playAt(idx, true);
                    }
                });
            }(i, isActive));
            var delBtn = createTextButton('jb-track__btn jb-track__btn--remove', '×', 'Retirer de la playlist');
            (function (idx) {
                delBtn.addEventListener('click', function () { self._removeTrack(idx); });
            }(i));
            row.appendChild(thumb);
            row.appendChild(info);
            actions.appendChild(playBtn);
            actions.appendChild(titleEditBtn);
            actions.appendChild(delBtn);
            row.appendChild(actions);
            container.appendChild(row);

            var isEditing = false;
            function setEditing(nextEditing) {
                isEditing = nextEditing;
                titleText.style.display = nextEditing ? 'none' : '';
                titleInput.style.display = nextEditing ? '' : 'none';
                titleEditBtn.textContent = nextEditing ? '✓' : '✎';
                titleEditBtn.title = nextEditing ? 'Valider le titre' : 'Renommer le titre';
                if (nextEditing) {
                    titleInput.focus();
                    titleInput.select();
                }
            }

            titleEditBtn.addEventListener('click', function () {
                if (!isEditing) {
                    setEditing(true);
                    return;
                }
                self._renameTrack(i, titleInput.value);
                titleText.textContent = track.title;
                setEditing(false);
            });

            titleInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    self._renameTrack(i, titleInput.value);
                    titleText.textContent = track.title;
                    setEditing(false);
                } else if (e.key === 'Escape') {
                    e.preventDefault();
                    titleInput.value = track.title || '';
                    setEditing(false);
                }
            });

            titleInput.addEventListener('blur', function () {
                if (!isEditing) {
                    return;
                }
                self._renameTrack(i, titleInput.value);
                titleText.textContent = track.title;
                setEditing(false);
            });
        });
    };

    // ── Playlist management ──────────────────────────────────────────────────

    PlayerWidget.prototype._createPlaylist = function (name) {
        var n = typeof name === 'string' ? name.trim().slice(0, 40) : '';
        if (!n) {
            this._setStatus('Saisis un nom de playlist.', true);
            return;
        }
        var id = this._genId();
        this.state.playlists.push({ id: id, name: n, tracks: [] });
        this.state.activePlaylistId = id;
        this.currentIndex = -1;
        this.currentTrack = null;
        this.el.nowTitle.textContent = 'Selectionne un morceau';
        this.view = 'playlist';
        this._renderTabs();
        this._renderContent();
        this._scheduleSave();
        this._setStatus('Playlist cree : ' + n, false);
    };

    PlayerWidget.prototype._switchPlaylist = function (id) {
        if (!this.state.playlists.some(function (pl) { return pl.id === id; })) {
            return;
        }
        this.state.activePlaylistId = id;
        this.currentIndex = -1;
        this.currentTrack = null;
        this.el.nowTitle.textContent = 'Selectionne un morceau';
        this.el.btnPlay.classList.remove('is-playing');
        this.el.btnPlay.setAttribute('aria-label', 'Lire');
        if (this.player && typeof this.player.stopVideo === 'function') {
            this.player.stopVideo();
        }
        this.view = 'playlist';
        this._renderTabs();
        this._renderContent();
    };

    PlayerWidget.prototype._deletePlaylist = function (id) {
        if (this.state.playlists.length <= 1) {
            this._setStatus('Il faut conserver au moins une playlist.', true);
            return;
        }
        this.state.playlists = this.state.playlists.filter(function (pl) { return pl.id !== id; });
        if (this.state.activePlaylistId === id) {
            this.state.activePlaylistId = this.state.playlists[0].id;
            this.currentIndex = -1;
            this.currentTrack = null;
            this.el.nowTitle.textContent = 'Selectionne un morceau';
            this.el.btnPlay.classList.remove('is-playing');
            this.el.btnPlay.setAttribute('aria-label', 'Lire');
            if (this.player && typeof this.player.stopVideo === 'function') {
                this.player.stopVideo();
            }
        }
        this._renderTabs();
        this._renderContent();
        this._scheduleSave();
        this._setStatus('Playlist supprimee.', false);
    };

    PlayerWidget.prototype._addTrack = function (track) {
        var t = safeTrack(track);
        if (!t) {
            return;
        }
        var pl = this._getActive();
        if (!pl) {
            return;
        }
        var shouldPlay = !this.currentTrack;
        pl.tracks.push(t);
        this._scheduleSave();

        if (shouldPlay) {
            this.view = 'playlist';
            this._renderContent();
            this._playAt(pl.tracks.length - 1, true);
        } else {
            if (this.view === 'playlist') {
                this._renderContent();
            }
            this._setStatus('Ajoute a ' + pl.name, false);
        }
    };

    PlayerWidget.prototype._removeTrack = function (idx) {
        var tracks = this._getTracks();
        if (idx < 0 || idx >= tracks.length) {
            return;
        }
        var wasCurrent = idx === this.currentIndex;
        tracks.splice(idx, 1);

        if (!tracks.length) {
            this.currentIndex = -1;
            this.currentTrack = null;
            this.el.nowTitle.textContent = 'Selectionne un morceau';
            this.el.btnPlay.classList.remove('is-playing');
            this.el.btnPlay.setAttribute('aria-label', 'Lire');
            if (this.player && typeof this.player.stopVideo === 'function') {
                this.player.stopVideo();
            }
        } else if (idx < this.currentIndex) {
            this.currentIndex -= 1;
        }

        this._renderContent();
        this._scheduleSave();

        if (wasCurrent && tracks.length) {
            var next = Math.min(this.currentIndex, tracks.length - 1);
            this._playAt(next, true);
        }
    };

    PlayerWidget.prototype._renameTrack = function (idx, title) {
        var tracks = this._getTracks();
        if (idx < 0 || idx >= tracks.length) {
            return;
        }
        var nextTitle = typeof title === 'string' ? title.trim().slice(0, 80) : '';
        if (!nextTitle) {
            return;
        }
        tracks[idx].title = nextTitle;
        this._renderContent();
        this._scheduleSave();
    };

    PlayerWidget.prototype._playTrack = function (track) {
        var t = safeTrack(track);
        if (!t) {
            return;
        }
        this.currentTrack = t;
        this.currentIndex = -1;
        this.el.nowTitle.textContent = t.title;
        this._ensurePlayer().then(function (player) {
            if (!player) {
                return;
            }
            if (typeof player.loadVideoById === 'function') {
                player.loadVideoById(t.id);
            }
            if (typeof player.playVideo === 'function') {
                player.playVideo();
            }
        });
    };

    // ── Playback ─────────────────────────────────────────────────────────────

    PlayerWidget.prototype._ensurePlayer = function () {
        var self = this;
        return loadIframeApi().then(function () {
            if (self.player) {
                return self.player;
            }
            self.player = new window.YT.Player(self.el.hiddenPlayer, {
                height: '1',
                width: '1',
                videoId: '',
                playerVars: {
                    autoplay: 0,
                    controls: 0,
                    disablekb: 1,
                    fs: 0,
                    iv_load_policy: 3,
                    modestbranding: 1,
                    rel: 0,
                },
                events: {
                    onStateChange: function (ev) {
                        if (!window.YT || !window.YT.PlayerState) {
                            return;
                        }
                        var S = window.YT.PlayerState;
                        if (ev.data === S.ENDED) {
                            self._next();
                        }
                        if (ev.data === S.PLAYING) {
                            self.el.btnPlay.classList.add('is-playing');
                            self.el.btnPlay.setAttribute('aria-label', 'Pause');
                        }
                        if (ev.data === S.PAUSED || ev.data === S.CUED) {
                            self.el.btnPlay.classList.remove('is-playing');
                            self.el.btnPlay.setAttribute('aria-label', 'Lire');
                        }
                    },
                },
            });
            return self.player;
        });
    };

    PlayerWidget.prototype._playAt = function (idx, now) {
        var tracks = this._getTracks();
        if (!tracks.length || idx < 0 || idx >= tracks.length) {
            return;
        }
        var track = tracks[idx];
        this.currentIndex = idx;
        this.currentTrack = track;
        this.el.nowTitle.textContent = track.title || '...';
        this._renderContent();

        if (!this.player || typeof this.player.loadVideoById !== 'function') {
            return;
        }
        if (now) {
            this.player.loadVideoById(track.id);
        } else {
            this.player.cueVideoById(track.id);
        }
    };

    PlayerWidget.prototype._prev = function () {
        this._playAt(this.currentIndex - 1, true);
    };

    PlayerWidget.prototype._next = function () {
        this._playAt(this.currentIndex + 1, true);
    };

    PlayerWidget.prototype._togglePlay = function () {
        if (!this.player || typeof this.player.getPlayerState !== 'function') {
            return;
        }
        var s = this.player.getPlayerState();
        if (window.YT && window.YT.PlayerState && s === window.YT.PlayerState.PLAYING) {
            this.player.pauseVideo();
            return;
        }
        if (this.currentTrack) {
            this.player.playVideo();
            return;
        }
        var tracks = this._getTracks();
        if (tracks.length) {
            this._playAt(0, true);
        }
    };

    // ── Search ───────────────────────────────────────────────────────────────

    PlayerWidget.prototype.search = function (query) {
        var q = String(query || '').trim();
        if (!q) {
            this._setStatus('Saisis quelque chose a rechercher.', true);
            return;
        }
        if (!this.apiKey) {
            this._setStatus('Cle API YouTube manquante.', true);
            return;
        }
        var self = this;
        this._setStatus('Recherche en cours...', false);

        fetchYT('/search', {
            key: this.apiKey,
            part: 'snippet',
            type: 'video',
            maxResults: String(this.maxResults),
            q: q,
            videoEmbeddable: 'true',
            safeSearch: 'moderate',
        })
            .then(function (data) {
                var items = data && Array.isArray(data.items) ? data.items : [];
                self.searchResults = items.map(fromSearch).filter(Boolean);
                self.view = 'results';
                self._renderContent();
                self._setStatus(
                    self.searchResults.length
                        ? self.searchResults.length + ' resultats - clique + pour ajouter.'
                        : 'Aucun resultat.',
                    !self.searchResults.length
                );
            })
            .catch(function () {
                self._setStatus('Erreur API YouTube. Verifie la cle et les restrictions de domaine.', true);
            });
    };

    PlayerWidget.prototype._loadPreset = function (preset, play) {
        if (!preset || !this.apiKey) {
            return;
        }
        var id = typeof preset.playlistId === 'string' ? preset.playlistId.trim() : '';
        var name = typeof preset.name === 'string' ? preset.name : 'Playlist';
        if (!id) {
            return;
        }
        var self = this;
        this._setStatus('Chargement de ' + name + '...', false);

        fetchYT('/playlistItems', {
            key: this.apiKey,
            part: 'snippet',
            playlistId: id,
            maxResults: '25',
        })
            .then(function (data) {
                var tracks = (data && Array.isArray(data.items) ? data.items : [])
                    .map(fromPlaylistItem)
                    .filter(Boolean);
                var pl = self._getActive();
                if (!pl) {
                    return;
                }
                pl.tracks = tracks;
                self.currentIndex = -1;
                self.currentTrack = null;
                self.view = 'playlist';
                self._renderContent();
                self._scheduleSave();

                if (!tracks.length) {
                    self._setStatus('Playlist vide ou inaccessible.', true);
                    return;
                }
                self._setStatus(tracks.length + ' titres importes dans ' + pl.name, false);
                if (play || self.autoplay) {
                    self._playAt(0, true);
                }
            })
            .catch(function () {
                self._setStatus('Impossible de charger la playlist YouTube.', true);
            });
    };

    // ── Status ───────────────────────────────────────────────────────────────

    PlayerWidget.prototype._setStatus = function (text, isError) {
        if (!this.el.status) {
            return;
        }

        if (this.statusResetTimer) {
            clearTimeout(this.statusResetTimer);
            this.statusResetTimer = null;
        }

        this.el.status.textContent = text || '';
        this.el.status.classList.toggle('is-error', !!isError);

        if (isError && text) {
            var self = this;
            this.statusResetTimer = setTimeout(function () {
                self.el.status.textContent = '';
                self.el.status.classList.remove('is-error');
                self.statusResetTimer = null;
            }, 3800);
        }
    };

    // ── Mount ────────────────────────────────────────────────────────────────

    function mountAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('[data-mj-player-widget]').forEach(function (widgetRoot) {
            if (widgetRoot.__mjPlayerMounted) {
                return;
            }
            widgetRoot.__mjPlayerMounted = true;
            new PlayerWidget(widgetRoot).init();
        });
    }

    function watchDynamicMounts() {
        if (!window.MutationObserver || watchDynamicMounts.__started) {
            return;
        }
        watchDynamicMounts.__started = true;

        var observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                    if (!node || node.nodeType !== 1) {
                        return;
                    }
                    if (node.matches && node.matches('[data-mj-player-widget]')) {
                        mountAll(node.parentNode || document);
                        return;
                    }
                    if (node.querySelectorAll) {
                        mountAll(node);
                    }
                });
            });
        });

        observer.observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    }

    function bindElementorHooks() {
        if (!window.elementorFrontend || !window.elementorFrontend.hooks || typeof window.elementorFrontend.hooks.addAction !== 'function') {
            return;
        }

        var hookName = 'frontend/element_ready/mj-member-player.default';
        window.elementorFrontend.hooks.addAction(hookName, function ($scope) {
            if ($scope && $scope[0]) {
                mountAll($scope[0]);
            } else {
                mountAll();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountAll);
    } else {
        mountAll();
    }

    bindElementorHooks();
    watchDynamicMounts();
})();
