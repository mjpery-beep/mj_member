(function () {
    'use strict';

    var API_BASE = 'https://www.googleapis.com/youtube/v3';
    var iframeApiPromise = null;

    function parseJsonConfig(raw) {
        if (!raw || typeof raw !== 'string') {
            return {};
        }

        try {
            var parsed = JSON.parse(raw);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (error) {
            return {};
        }
    }

    function createNode(tagName, className, text) {
        var node = document.createElement(tagName);
        if (className) {
            node.className = className;
        }
        if (typeof text === 'string') {
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

            var previousReady = window.onYouTubeIframeAPIReady;
            window.onYouTubeIframeAPIReady = function () {
                if (typeof previousReady === 'function') {
                    previousReady();
                }
                if (window.YT && window.YT.Player) {
                    resolve(window.YT);
                    return;
                }
                reject(new Error('YouTube API unavailable'));
            };

            var existingScript = document.querySelector('script[data-mj-player-yt-api="1"]');
            if (existingScript) {
                return;
            }

            var script = document.createElement('script');
            script.src = 'https://www.youtube.com/iframe_api';
            script.async = true;
            script.setAttribute('data-mj-player-yt-api', '1');
            script.onerror = function () {
                reject(new Error('Failed to load YouTube iframe API'));
            };
            document.head.appendChild(script);
        });

        return iframeApiPromise;
    }

    function fetchYouTube(path, params) {
        var query = new URLSearchParams(params);
        return fetch(API_BASE + path + '?' + query.toString()).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        });
    }

    function buildTrackFromSearchItem(item) {
        var id = item && item.id && item.id.videoId ? String(item.id.videoId) : '';
        if (!id) {
            return null;
        }

        var snippet = item && item.snippet ? item.snippet : {};
        return {
            id: id,
            title: snippet.title ? String(snippet.title) : 'Video',
            channel: snippet.channelTitle ? String(snippet.channelTitle) : '',
            thumb: snippet.thumbnails && snippet.thumbnails.medium ? snippet.thumbnails.medium.url : '',
        };
    }

    function buildTrackFromPlaylistItem(item) {
        var snippet = item && item.snippet ? item.snippet : {};
        var resource = snippet.resourceId ? snippet.resourceId : {};
        var id = resource.videoId ? String(resource.videoId) : '';
        if (!id || snippet.title === 'Deleted video' || snippet.title === 'Private video') {
            return null;
        }

        return {
            id: id,
            title: snippet.title ? String(snippet.title) : 'Video',
            channel: snippet.videoOwnerChannelTitle ? String(snippet.videoOwnerChannelTitle) : '',
            thumb: snippet.thumbnails && snippet.thumbnails.medium ? snippet.thumbnails.medium.url : '',
        };
    }

    function PlayerWidget(root) {
        this.root = root;
        this.app = root.querySelector('.mj-player-widget__app');
        this.config = parseJsonConfig(root.getAttribute('data-config'));

        this.apiKey = typeof this.config.apiKey === 'string' ? this.config.apiKey.trim() : '';
        this.defaultQuery = typeof this.config.defaultQuery === 'string' ? this.config.defaultQuery.trim() : '';
        this.maxResults = Number(this.config.maxResults) || 8;
        this.autoplay = !!this.config.autoplay;
        this.playlists = Array.isArray(this.config.playlists) ? this.config.playlists : [];

        this.player = null;
        this.queue = [];
        this.currentIndex = -1;
        this.currentTrack = null;

        this.ui = {
            status: null,
            searchInput: null,
            searchButton: null,
            results: null,
            queue: null,
            nowTitle: null,
            nowMeta: null,
            btnPlayPause: null,
            btnNext: null,
            btnPrev: null,
            btnStop: null,
            playlistButtons: null,
            hiddenPlayer: null,
        };
    }

    PlayerWidget.prototype.init = function () {
        if (!this.app) {
            return;
        }

        this.maxResults = Math.max(3, Math.min(25, this.maxResults));
        this.renderLayout();

        if (!this.apiKey) {
            this.setStatus('Ajoute une clé API YouTube Data v3 dans le widget pour activer la recherche et les playlists.', true);
            return;
        }

        this.ensurePlayer().then(function () {
            if (this.playlists.length > 0) {
                this.loadPlaylist(this.playlists[0]);
                return;
            }
            if (this.defaultQuery) {
                this.search(this.defaultQuery);
                return;
            }
            this.setStatus('Prêt. Lance une recherche YouTube.', false);
        }.bind(this)).catch(function () {
            this.setStatus('Impossible de charger le player YouTube.', true);
        }.bind(this));
    };

    PlayerWidget.prototype.renderLayout = function () {
        this.app.innerHTML = '';

        var wrapper = createNode('div', 'mj-player-widget__panel');

        var searchRow = createNode('div', 'mj-player-widget__search');
        var input = createNode('input', 'mj-player-widget__search-input');
        input.type = 'search';
        input.placeholder = 'Rechercher sur YouTube...';
        input.value = this.defaultQuery;

        var button = createNode('button', 'mj-player-widget__search-button', 'Rechercher');
        button.type = 'button';

        searchRow.appendChild(input);
        searchRow.appendChild(button);

        var status = createNode('p', 'mj-player-widget__status', '');

        var playlistsWrap = createNode('div', 'mj-player-widget__playlists');
        if (this.playlists.length > 0) {
            this.playlists.forEach(function (playlist, index) {
                var label = playlist && typeof playlist.name === 'string' ? playlist.name : 'Playlist ' + (index + 1);
                var playlistBtn = createNode('button', 'mj-player-widget__playlist-btn', label);
                playlistBtn.type = 'button';
                playlistBtn.addEventListener('click', function () {
                    this.loadPlaylist(playlist);
                }.bind(this));
                playlistsWrap.appendChild(playlistBtn);
            }.bind(this));
        } else {
            var emptyPlaylists = createNode('p', 'mj-player-widget__hint', 'Aucune playlist prédéfinie configurée.');
            playlistsWrap.appendChild(emptyPlaylists);
        }

        var now = createNode('div', 'mj-player-widget__now');
        var nowTitle = createNode('h4', 'mj-player-widget__now-title', 'Aucune piste en lecture');
        var nowMeta = createNode('p', 'mj-player-widget__now-meta', '');

        var controls = createNode('div', 'mj-player-widget__controls');
        var btnPrev = createNode('button', 'mj-player-widget__ctrl-btn', 'Prec');
        var btnPlayPause = createNode('button', 'mj-player-widget__ctrl-btn mj-player-widget__ctrl-btn--primary', 'Play');
        var btnNext = createNode('button', 'mj-player-widget__ctrl-btn', 'Suiv');
        var btnStop = createNode('button', 'mj-player-widget__ctrl-btn', 'Stop');

        btnPrev.type = 'button';
        btnPlayPause.type = 'button';
        btnNext.type = 'button';
        btnStop.type = 'button';

        controls.appendChild(btnPrev);
        controls.appendChild(btnPlayPause);
        controls.appendChild(btnNext);
        controls.appendChild(btnStop);

        now.appendChild(nowTitle);
        now.appendChild(nowMeta);
        now.appendChild(controls);

        var columns = createNode('div', 'mj-player-widget__columns');
        var resultsCol = createNode('div', 'mj-player-widget__column');
        var queueCol = createNode('div', 'mj-player-widget__column');

        resultsCol.appendChild(createNode('h5', 'mj-player-widget__heading', 'Resultats'));
        queueCol.appendChild(createNode('h5', 'mj-player-widget__heading', 'File de lecture'));

        var resultsList = createNode('div', 'mj-player-widget__list');
        var queueList = createNode('div', 'mj-player-widget__list');

        resultsCol.appendChild(resultsList);
        queueCol.appendChild(queueList);

        columns.appendChild(resultsCol);
        columns.appendChild(queueCol);

        var hiddenPlayer = createNode('div', 'mj-player-widget__hidden-player');

        wrapper.appendChild(searchRow);
        wrapper.appendChild(status);
        wrapper.appendChild(playlistsWrap);
        wrapper.appendChild(now);
        wrapper.appendChild(columns);
        wrapper.appendChild(hiddenPlayer);

        this.app.appendChild(wrapper);

        this.ui.status = status;
        this.ui.searchInput = input;
        this.ui.searchButton = button;
        this.ui.results = resultsList;
        this.ui.queue = queueList;
        this.ui.nowTitle = nowTitle;
        this.ui.nowMeta = nowMeta;
        this.ui.btnPlayPause = btnPlayPause;
        this.ui.btnNext = btnNext;
        this.ui.btnPrev = btnPrev;
        this.ui.btnStop = btnStop;
        this.ui.playlistButtons = playlistsWrap;
        this.ui.hiddenPlayer = hiddenPlayer;

        button.addEventListener('click', function () {
            this.search(this.ui.searchInput.value || '');
        }.bind(this));

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.search(this.ui.searchInput.value || '');
            }
        }.bind(this));

        btnPlayPause.addEventListener('click', function () {
            this.togglePlayPause();
        }.bind(this));

        btnNext.addEventListener('click', function () {
            this.playByIndex(this.currentIndex + 1, true);
        }.bind(this));

        btnPrev.addEventListener('click', function () {
            this.playByIndex(this.currentIndex - 1, true);
        }.bind(this));

        btnStop.addEventListener('click', function () {
            if (this.player && typeof this.player.stopVideo === 'function') {
                this.player.stopVideo();
                this.ui.btnPlayPause.textContent = 'Play';
            }
        }.bind(this));
    };

    PlayerWidget.prototype.ensurePlayer = function () {
        return loadIframeApi().then(function () {
            if (this.player) {
                return this.player;
            }

            this.player = new window.YT.Player(this.ui.hiddenPlayer, {
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
                    onStateChange: function (event) {
                        if (!window.YT || !window.YT.PlayerState) {
                            return;
                        }
                        if (event.data === window.YT.PlayerState.ENDED) {
                            this.playByIndex(this.currentIndex + 1, true);
                        }
                        if (event.data === window.YT.PlayerState.PLAYING) {
                            this.ui.btnPlayPause.textContent = 'Pause';
                        }
                        if (event.data === window.YT.PlayerState.PAUSED || event.data === window.YT.PlayerState.CUED) {
                            this.ui.btnPlayPause.textContent = 'Play';
                        }
                    }.bind(this),
                },
            });

            return this.player;
        }.bind(this));
    };

    PlayerWidget.prototype.setStatus = function (text, isError) {
        if (!this.ui.status) {
            return;
        }
        this.ui.status.textContent = text || '';
        this.ui.status.classList.toggle('is-error', !!isError);
    };

    PlayerWidget.prototype.search = function (query) {
        var cleanQuery = String(query || '').trim();
        if (!cleanQuery) {
            this.setStatus('Saisis une recherche pour trouver des morceaux.', true);
            return;
        }

        this.setStatus('Recherche en cours...', false);

        fetchYouTube('/search', {
            key: this.apiKey,
            part: 'snippet',
            type: 'video',
            maxResults: String(this.maxResults),
            q: cleanQuery,
            videoEmbeddable: 'true',
            safeSearch: 'moderate',
        }).then(function (data) {
            var items = data && Array.isArray(data.items) ? data.items : [];
            var tracks = items.map(buildTrackFromSearchItem).filter(Boolean);
            this.renderResults(tracks);
            if (tracks.length === 0) {
                this.setStatus('Aucun resultat trouve.', true);
            } else {
                this.setStatus(tracks.length + ' resultat(s) trouves.', false);
            }
        }.bind(this)).catch(function () {
            this.renderResults([]);
            this.setStatus('Erreur API YouTube. Verifie la clé API et les restrictions de domaine.', true);
        }.bind(this));
    };

    PlayerWidget.prototype.loadPlaylist = function (playlist) {
        var playlistId = playlist && typeof playlist.playlistId === 'string' ? playlist.playlistId.trim() : '';
        var playlistName = playlist && typeof playlist.name === 'string' ? playlist.name : 'Playlist';

        if (!playlistId) {
            return;
        }

        this.setStatus('Chargement de la playlist: ' + playlistName + '...', false);

        fetchYouTube('/playlistItems', {
            key: this.apiKey,
            part: 'snippet',
            playlistId: playlistId,
            maxResults: '25',
        }).then(function (data) {
            var items = data && Array.isArray(data.items) ? data.items : [];
            var tracks = items.map(buildTrackFromPlaylistItem).filter(Boolean);

            this.queue = tracks;
            this.currentIndex = -1;
            this.renderQueue();

            if (tracks.length === 0) {
                this.setStatus('Playlist vide ou inaccessible.', true);
                return;
            }

            this.setStatus('Playlist chargee: ' + playlistName, false);
            this.playByIndex(0, this.autoplay);
        }.bind(this)).catch(function () {
            this.setStatus('Impossible de charger la playlist YouTube.', true);
        }.bind(this));
    };

    PlayerWidget.prototype.renderResults = function (tracks) {
        this.ui.results.innerHTML = '';

        if (!tracks || tracks.length === 0) {
            this.ui.results.appendChild(createNode('p', 'mj-player-widget__hint', 'Aucun resultat.'));
            return;
        }

        tracks.forEach(function (track) {
            var item = createNode('button', 'mj-player-widget__item');
            item.type = 'button';

            var thumb = createNode('img', 'mj-player-widget__thumb');
            thumb.src = track.thumb || '';
            thumb.alt = '';

            var content = createNode('span', 'mj-player-widget__item-content');
            var title = createNode('strong', 'mj-player-widget__item-title', track.title || 'Video');
            var meta = createNode('small', 'mj-player-widget__item-meta', track.channel || '');

            content.appendChild(title);
            content.appendChild(meta);

            item.appendChild(thumb);
            item.appendChild(content);

            item.addEventListener('click', function () {
                this.queue.push(track);
                this.renderQueue();
                this.playByIndex(this.queue.length - 1, true);
            }.bind(this));

            this.ui.results.appendChild(item);
        }.bind(this));
    };

    PlayerWidget.prototype.renderQueue = function () {
        this.ui.queue.innerHTML = '';

        if (!this.queue.length) {
            this.ui.queue.appendChild(createNode('p', 'mj-player-widget__hint', 'Ajoute des morceaux a la file.'));
            return;
        }

        this.queue.forEach(function (track, index) {
            var item = createNode('button', 'mj-player-widget__item' + (index === this.currentIndex ? ' is-active' : ''));
            item.type = 'button';

            var thumb = createNode('img', 'mj-player-widget__thumb');
            thumb.src = track.thumb || '';
            thumb.alt = '';

            var content = createNode('span', 'mj-player-widget__item-content');
            var title = createNode('strong', 'mj-player-widget__item-title', track.title || 'Video');
            var meta = createNode('small', 'mj-player-widget__item-meta', track.channel || '');

            content.appendChild(title);
            content.appendChild(meta);

            item.appendChild(thumb);
            item.appendChild(content);

            item.addEventListener('click', function () {
                this.playByIndex(index, true);
            }.bind(this));

            this.ui.queue.appendChild(item);
        }.bind(this));
    };

    PlayerWidget.prototype.playByIndex = function (index, shouldPlayNow) {
        if (!this.queue.length || index < 0 || index >= this.queue.length) {
            return;
        }

        var track = this.queue[index];
        this.currentIndex = index;
        this.currentTrack = track;

        this.ui.nowTitle.textContent = track.title || 'Lecture en cours';
        this.ui.nowMeta.textContent = track.channel || '';
        this.renderQueue();

        if (!this.player || typeof this.player.loadVideoById !== 'function') {
            return;
        }

        if (shouldPlayNow) {
            this.player.loadVideoById(track.id);
        } else {
            this.player.cueVideoById(track.id);
        }
    };

    PlayerWidget.prototype.togglePlayPause = function () {
        if (!this.player || typeof this.player.getPlayerState !== 'function') {
            return;
        }

        var state = this.player.getPlayerState();
        if (window.YT && window.YT.PlayerState && state === window.YT.PlayerState.PLAYING) {
            this.player.pauseVideo();
            return;
        }

        if (this.currentTrack && this.currentTrack.id) {
            this.player.playVideo();
            return;
        }

        if (this.queue.length > 0) {
            this.playByIndex(0, true);
        }
    };

    function mountAll() {
        var roots = document.querySelectorAll('[data-mj-player-widget]');
        if (!roots.length) {
            return;
        }

        roots.forEach(function (root) {
            if (root.__mjPlayerMounted) {
                return;
            }
            root.__mjPlayerMounted = true;
            var widget = new PlayerWidget(root);
            widget.init();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountAll);
    } else {
        mountAll();
    }
})();
