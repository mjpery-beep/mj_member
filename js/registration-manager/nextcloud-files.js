/**
 * Registration Manager – Nextcloud Files Panel
 * Composant Preact pour parcourir et gérer les photos/documents Nextcloud.
 *
 * Expose window.MjRegMgrNextcloudFiles = { NextcloudFilesPanel }
 */
(function (global) {
    'use strict';

    var h            = global.preact ? global.preact.h : null;
    var useState     = global.preactHooks ? global.preactHooks.useState : null;
    var useEffect    = global.preactHooks ? global.preactHooks.useEffect : null;
    var useCallback  = global.preactHooks ? global.preactHooks.useCallback : null;
    var useRef       = global.preactHooks ? global.preactHooks.useRef : null;

    if (!h || !useState) {
        return;
    }

    // -----------------------------------------------------------------------
    // Utilities
    // -----------------------------------------------------------------------

    function formatSize(bytes) {
        if (!bytes || bytes === 0) { return '–'; }
        if (bytes < 1024) { return bytes + ' o'; }
        if (bytes < 1024 * 1024) { return Math.round(bytes / 1024) + ' Ko'; }
        return (bytes / (1024 * 1024)).toFixed(1) + ' Mo';
    }

    function isImage(mimeType) {
        return typeof mimeType === 'string' && mimeType.indexOf('image/') === 0;
    }

    function isPdf(mimeType) {
        return mimeType === 'application/pdf';
    }

    function fileIcon(item) {
        if (item.type === 'folder') { return '📁'; }
        if (isImage(item.mimeType)) { return '🖼️'; }
        if (isPdf(item.mimeType))   { return '📄'; }
        var m = item.mimeType || '';
        if (m.indexOf('word') !== -1 || m.indexOf('document') !== -1) { return '📝'; }
        if (m.indexOf('spreadsheet') !== -1 || m.indexOf('excel') !== -1) { return '📊'; }
        if (m.indexOf('presentation') !== -1 || m.indexOf('powerpoint') !== -1) { return '📊'; }
        if (m.indexOf('zip') !== -1 || m.indexOf('archive') !== -1) { return '🗜️'; }
        if (m.indexOf('video') !== -1) { return '🎬'; }
        if (m.indexOf('audio') !== -1) { return '🎵'; }
        return '📎';
    }

    // -----------------------------------------------------------------------
    // DragDrop Uploader
    // -----------------------------------------------------------------------

    function FileUploader(props) {
        var onUpload    = props.onUpload;
        var uploading   = props.uploading;
        var subFolder   = props.subFolder;
        var mediaType   = props.mediaType;

        var _dragging   = useState(false);
        var dragging    = _dragging[0];
        var setDragging = _dragging[1];
        var inputRef    = useRef(null);

        var acceptAttr = mediaType === 'photos' ? 'image/*' : '*/*';

        function handleFiles(files) {
            if (!files || files.length === 0 || typeof onUpload !== 'function') { return; }
            Array.from(files).forEach(function (f) { onUpload(f, subFolder); });
        }

        function onDrop(e) {
            e.preventDefault();
            setDragging(false);
            handleFiles(e.dataTransfer.files);
        }

        function onDragOver(e) {
            e.preventDefault();
            setDragging(true);
        }

        function onDragLeave() {
            setDragging(false);
        }

        function onInputChange(e) {
            handleFiles(e.target.files);
            e.target.value = '';
        }

        return h('div', {
            class: 'mj-nc-uploader' + (dragging ? ' mj-nc-uploader--drag' : '') + (uploading ? ' mj-nc-uploader--busy' : ''),
            onDrop: onDrop,
            onDragOver: onDragOver,
            onDragLeave: onDragLeave,
            onClick: function () { !uploading && inputRef.current && inputRef.current.click(); },
        }, [
            h('input', {
                ref: inputRef,
                type: 'file',
                accept: acceptAttr,
                multiple: true,
                style: { display: 'none' },
                onChange: onInputChange,
            }),
            uploading
                ? h('span', { class: 'mj-nc-uploader__label' }, [
                    h('span', { class: 'mj-nc-uploader__spinner' }),
                    ' Envoi en cours…',
                ])
                : h('span', { class: 'mj-nc-uploader__label' }, [
                    h('span', { class: 'mj-nc-uploader__icon' }, '☁️'),
                    ' Glisser-déposer ou ',
                    h('u', null, 'cliquer pour envoyer'),
                ]),
        ]);
    }

    // -----------------------------------------------------------------------
    // Photo Thumbnail Card
    // -----------------------------------------------------------------------

    function PhotoCard(props) {
        var item      = props.item;
        var onDelete  = props.onDelete;
        var onView    = props.onView;
        var deleting  = props.deleting;

        return h('div', { class: 'mj-nc-photo-card' }, [
            h('div', {
                class: 'mj-nc-photo-card__thumb',
                onClick: function () { typeof onView === 'function' && onView(item); },
                title: 'Voir',
            }, [
                item.downloadUrl
                    ? h('img', { src: item.downloadUrl, alt: item.name, loading: 'lazy' })
                    : h('span', { class: 'mj-nc-photo-card__icon' }, fileIcon(item)),
            ]),
            h('div', { class: 'mj-nc-photo-card__footer' }, [
                h('span', { class: 'mj-nc-photo-card__name', title: item.name }, item.name),
                h('div', { class: 'mj-nc-photo-card__actions' }, [
                    h('button', {
                        class: 'mj-nc-btn mj-nc-btn--icon',
                        title: 'Voir',
                        onClick: function (e) { e.stopPropagation(); typeof onView === 'function' && onView(item); },
                    }, '👁'),
                    h('button', {
                        class: 'mj-nc-btn mj-nc-btn--icon mj-nc-btn--danger',
                        title: 'Supprimer',
                        disabled: deleting,
                        onClick: function (e) { e.stopPropagation(); typeof onDelete === 'function' && onDelete(item); },
                    }, deleting ? '…' : '🗑'),
                ]),
            ]),
        ]);
    }

    // -----------------------------------------------------------------------
    // Document Row
    // -----------------------------------------------------------------------

    function DocumentRow(props) {
        var item      = props.item;
        var onDelete  = props.onDelete;
        var onRename  = props.onRename;
        var onOpen    = props.onOpen; // navigate into folder
        var onMove    = props.onMove;
        var deleting  = props.deleting;

        var _editing    = useState(false);
        var editing     = _editing[0];
        var setEditing  = _editing[1];
        var _newName    = useState(item.name);
        var newName     = _newName[0];
        var setNewName  = _newName[1];

        function saveRename() {
            if (newName.trim() !== '' && newName.trim() !== item.name && typeof onRename === 'function') {
                onRename(item, newName.trim());
            }
            setEditing(false);
        }

        function onKeyDown(e) {
            if (e.key === 'Enter') { saveRename(); }
            if (e.key === 'Escape') { setEditing(false); setNewName(item.name); }
        }

        return h('div', { class: 'mj-nc-doc-row' + (item.type === 'folder' ? ' mj-nc-doc-row--folder' : '') }, [
            h('span', {
                class: 'mj-nc-doc-row__icon',
                onClick: item.type === 'folder' ? function () { typeof onOpen === 'function' && onOpen(item); } : null,
            }, fileIcon(item)),

            editing
                ? h('input', {
                    class: 'mj-nc-doc-row__rename-input',
                    value: newName,
                    autoFocus: true,
                    onInput: function (e) { setNewName(e.target.value); },
                    onBlur: saveRename,
                    onKeyDown: onKeyDown,
                })
                : h('span', {
                    class: 'mj-nc-doc-row__name',
                    onClick: item.type === 'folder' ? function () { typeof onOpen === 'function' && onOpen(item); } : null,
                    title: item.name,
                }, item.name),

            h('span', { class: 'mj-nc-doc-row__size' }, item.type === 'folder' ? '' : formatSize(item.size)),

            h('div', { class: 'mj-nc-doc-row__actions' }, [
                item.type === 'file' && item.downloadUrl && h('a', {
                    class: 'mj-nc-btn mj-nc-btn--icon',
                    href: item.downloadUrl,
                    target: '_blank',
                    rel: 'noopener',
                    title: 'Ouvrir',
                    onClick: function (e) { e.stopPropagation(); },
                }, '↗'),

                item.type === 'file' && item.webUrl && h('a', {
                    class: 'mj-nc-btn',
                    href: item.webUrl,
                    target: '_blank',
                    rel: 'noopener',
                    title: 'Voir dans Nextcloud',
                    onClick: function (e) { e.stopPropagation(); },
                }, 'Voir dans Nextcloud'),

                item.type === 'file' && item.editUrl && h('a', {
                    class: 'mj-nc-btn',
                    href: item.editUrl,
                    target: '_blank',
                    rel: 'noopener',
                    title: 'Éditer dans Nextcloud',
                    onClick: function (e) { e.stopPropagation(); },
                }, 'Éditer dans Nextcloud'),

                item.type === 'file' && h('button', {
                    class: 'mj-nc-btn mj-nc-btn--icon',
                    title: 'Renommer',
                    onClick: function (e) { e.stopPropagation(); setEditing(true); setNewName(item.name); },
                }, '✏️'),

                h('button', {
                    class: 'mj-nc-btn mj-nc-btn--icon',
                    title: 'Déplacer',
                    onClick: function (e) { e.stopPropagation(); typeof onMove === 'function' && onMove(item); },
                }, '↪'),

                h('button', {
                    class: 'mj-nc-btn mj-nc-btn--icon mj-nc-btn--danger',
                    title: 'Supprimer',
                    disabled: deleting,
                    onClick: function (e) { e.stopPropagation(); typeof onDelete === 'function' && onDelete(item); },
                }, deleting ? '…' : '🗑'),
            ]),
        ]);
    }

    // -----------------------------------------------------------------------
    // Lightbox
    // -----------------------------------------------------------------------

    function Lightbox(props) {
        var item    = props.item;
        var onClose = props.onClose;

        if (!item) { return null; }

        return h('div', {
            class: 'mj-nc-lightbox',
            onClick: onClose,
        }, [
            h('div', {
                class: 'mj-nc-lightbox__inner',
                onClick: function (e) { e.stopPropagation(); },
            }, [
                h('button', { class: 'mj-nc-lightbox__close', onClick: onClose }, '×'),
                h('img', { src: item.downloadUrl, alt: item.name, class: 'mj-nc-lightbox__img' }),
                h('p', { class: 'mj-nc-lightbox__caption' }, item.name),
            ]),
        ]);
    }

    // -----------------------------------------------------------------------
    // Media Tab (Photos or Documents)
    // -----------------------------------------------------------------------

    function MediaTab(props) {
        var context    = props.context;    // 'event' | 'member'
        var contextId  = props.contextId;
        var mediaType  = props.mediaType;  // 'photos' | 'documents'
        var apiService = props.apiService;

        var _items        = useState([]);
        var items         = _items[0];
        var setItems      = _items[1];
        var _loading      = useState(false);
        var loading       = _loading[0];
        var setLoading    = _loading[1];
        var _uploading    = useState(false);
        var uploading     = _uploading[0];
        var setUploading  = _uploading[1];
        var _error        = useState('');
        var error         = _error[0];
        var setError      = _error[1];
        var _lightbox     = useState(null);
        var lightboxItem  = _lightbox[0];
        var setLightbox   = _lightbox[1];
        var _deletingPath = useState('');
        var deletingPath  = _deletingPath[0];
        var setDeletingPath = _deletingPath[1];
        var _folderPath = useState('');
        var folderPath = _folderPath[0];
        var setFolderPath = _folderPath[1];
        var _folderExists = useState(true);
        var folderExists = _folderExists[0];
        var setFolderExists = _folderExists[1];
        var _creatingFolder = useState(false);
        var creatingFolder = _creatingFolder[0];
        var setCreatingFolder = _creatingFolder[1];
        var _newFolderName = useState('');
        var newFolderName = _newFolderName[0];
        var setNewFolderName = _newFolderName[1];

        // Navigation stack for sub-folders: array of folder path strings.
        var _navStack   = useState([]);
        var navStack    = _navStack[0];
        var setNavStack = _navStack[1];

        // Current folder = base folder path (when navStack is empty) or last entry.
        var currentPath = navStack.length > 0 ? navStack[navStack.length - 1] : null;

        var loadFolder = useCallback(function (subPath) {
            if (!apiService || !contextId) { return; }
            setLoading(true);
            setError('');

            var api = apiService;
            var req = subPath
                ? api.ncListFolder(context, contextId, mediaType, subPath)
                : api.ncListFolder(context, contextId, mediaType, '');

            req
                .then(function (data) {
                    setFolderPath(data.folderPath || '');
                    setFolderExists(data.folderExists !== false);
                    setItems(data.items || []);
                    setLoading(false);
                })
                .catch(function (err) {
                    var msg = err && err.message ? err.message : 'Erreur de chargement';
                    setError(msg);
                    setLoading(false);
                });
        }, [context, contextId, mediaType, apiService]);

        useEffect(function () {
            loadFolder(currentPath);
        }, [context, contextId, mediaType, currentPath]);

        var handleUpload = useCallback(function (file) {
            if (!folderExists) {
                setError('Le dossier n\'existe pas encore. Créez-le d\'abord.');
                return;
            }
            setUploading(true);
            setError('');
            apiService.ncUpload(context, contextId, mediaType, file, currentPath)
                .then(function (data) {
                    setUploading(false);
                    var newItem = data.file;
                    if (newItem) {
                        setItems(function (prev) { return prev.concat([newItem]); });
                    } else {
                        loadFolder(currentPath);
                    }
                })
                .catch(function (err) {
                    setUploading(false);
                    setError(err && err.message ? err.message : 'Échec de l\'envoi');
                });
        }, [context, contextId, mediaType, currentPath, apiService, loadFolder, folderExists]);

        var handleCreateFolder = useCallback(function (name) {
            setCreatingFolder(true);
            setError('');
            apiService.ncCreateFolder(context, contextId, mediaType, currentPath || '', name || '')
                .then(function () {
                    setCreatingFolder(false);
                    if (name) {
                        setNewFolderName('');
                    }
                    loadFolder(currentPath);
                })
                .catch(function (err) {
                    setCreatingFolder(false);
                    setError(err && err.message ? err.message : 'Échec de création du dossier');
                });
        }, [context, contextId, mediaType, currentPath, apiService, loadFolder]);

        var handleDelete = useCallback(function (item) {
            if (!window.confirm('Supprimer « ' + item.name + ' » ?')) { return; }
            setDeletingPath(item.path);
            apiService.ncDelete(item.path)
                .then(function () {
                    setDeletingPath('');
                    setItems(function (prev) { return prev.filter(function (i) { return i.path !== item.path; }); });
                })
                .catch(function (err) {
                    setDeletingPath('');
                    setError(err && err.message ? err.message : 'Échec de la suppression');
                });
        }, [apiService]);

        var handleRename = useCallback(function (item, newName) {
            apiService.ncRename(item.path, newName)
                .then(function (data) {
                    var updated = data.file || {};
                    setItems(function (prev) {
                        return prev.map(function (i) {
                            if (i.path !== item.path) { return i; }
                            return Object.assign({}, i, { path: updated.path || i.path, name: updated.name || newName });
                        });
                    });
                })
                .catch(function (err) {
                    setError(err && err.message ? err.message : 'Échec du renommage');
                });
        }, [apiService]);

        var handleMove = useCallback(function (item) {
            var target = window.prompt('Déplacer vers quel dossier ? (chemin complet Nextcloud)', folderPath || '');
            if (!target) { return; }

            apiService.ncMove(item.path, target)
                .then(function () {
                    loadFolder(currentPath);
                })
                .catch(function (err) {
                    setError(err && err.message ? err.message : 'Échec du déplacement');
                });
        }, [apiService, folderPath, loadFolder, currentPath]);

        function handleOpenFolder(item) {
            var subPath = item.path;
            setNavStack(function (prev) { return prev.concat([subPath]); });
        }

        function navigateBack() {
            setNavStack(function (prev) { return prev.slice(0, -1); });
        }

        function navigateTo(index) {
            setNavStack(function (prev) { return prev.slice(0, index + 1); });
        }

        // Sort: folders first, then by name.
        var sorted = items.slice().sort(function (a, b) {
            if (a.type === 'folder' && b.type !== 'folder') { return -1; }
            if (a.type !== 'folder' && b.type === 'folder') { return 1; }
            return a.name.localeCompare(b.name);
        });

        var isPhotos = mediaType === 'photos';

        // Build breadcrumb from navStack.
        var breadcrumb = null;
        if (navStack.length > 0) {
            breadcrumb = h('div', { class: 'mj-nc-breadcrumb' }, [
                h('button', {
                    class: 'mj-nc-breadcrumb__item',
                    onClick: function () { setNavStack([]); },
                }, mediaType === 'photos' ? 'Photos' : 'Documents'),
                navStack.map(function (seg, idx) {
                    var label = seg.split('/').pop();
                    var isLast = idx === navStack.length - 1;
                    return [
                        h('span', { key: 'sep-' + idx, class: 'mj-nc-breadcrumb__sep' }, '›'),
                        isLast
                            ? h('span', { key: 'seg-' + idx, class: 'mj-nc-breadcrumb__item mj-nc-breadcrumb__item--active' }, label)
                            : h('button', { key: 'seg-' + idx, class: 'mj-nc-breadcrumb__item', onClick: function () { navigateTo(idx); } }, label),
                    ];
                }),
            ]);
        }

        return h('div', { class: 'mj-nc-media-tab' }, [
            breadcrumb,

            h(FileUploader, {
                onUpload: handleUpload,
                uploading: uploading,
                mediaType: mediaType,
            }),

            h('div', { class: 'mj-nc-toolbar' }, [
                h('button', {
                    class: 'mj-nc-btn',
                    disabled: creatingFolder,
                    onClick: function () { handleCreateFolder(''); },
                    title: 'Créer le dossier courant s\'il n\'existe pas',
                }, creatingFolder ? 'Création…' : 'Créer le dossier'),

                h('input', {
                    class: 'mj-nc-doc-row__rename-input',
                    placeholder: 'Nouveau sous-dossier',
                    value: newFolderName,
                    onInput: function (e) { setNewFolderName(e.target.value); },
                }),

                h('button', {
                    class: 'mj-nc-btn',
                    disabled: creatingFolder || !newFolderName.trim(),
                    onClick: function () { handleCreateFolder(newFolderName.trim()); },
                }, 'Créer sous-dossier'),
            ]),

            error && h('div', { class: 'mj-nc-error' }, error),

            !folderExists && h('div', { class: 'mj-nc-empty' }, [
                h('span', null, 'Le dossier Nextcloud est introuvable.'),
            ]),

            loading && h('div', { class: 'mj-nc-loading' }, [
                h('div', { class: 'mj-regmgr-loading__spinner' }),
            ]),

            !loading && folderExists && sorted.length === 0 && h('div', { class: 'mj-nc-empty' }, [
                h('span', null, isPhotos ? '📸 Aucune photo.' : '📁 Aucun document.'),
            ]),

            !loading && folderExists && isPhotos && sorted.length > 0 && h('div', { class: 'mj-nc-photo-grid' },
                sorted.map(function (item) {
                    return h(PhotoCard, {
                        key: item.path,
                        item: item,
                        deleting: deletingPath === item.path,
                        onView: function (i) { setLightbox(i); },
                        onDelete: handleDelete,
                    });
                })
            ),

            !loading && folderExists && !isPhotos && sorted.length > 0 && h('div', { class: 'mj-nc-doc-list' },
                sorted.map(function (item) {
                    return h(DocumentRow, {
                        key: item.path,
                        item: item,
                        deleting: deletingPath === item.path,
                        onOpen: handleOpenFolder,
                        onDelete: handleDelete,
                        onRename: handleRename,
                        onMove: handleMove,
                    });
                })
            ),

            h(Lightbox, {
                item: lightboxItem,
                onClose: function () { setLightbox(null); },
            }),
        ]);
    }

    // -----------------------------------------------------------------------
    // Main panel – Photos / Documents sub-tabs
    // -----------------------------------------------------------------------

    function NextcloudFilesPanel(props) {
        var context    = props.context;    // 'event' | 'member'
        var contextId  = props.contextId;
        var apiService = props.apiService;

        var _activeTab   = useState('photos');
        var activeTab    = _activeTab[0];
        var setActiveTab = _activeTab[1];

        if (!contextId) {
            return h('div', { class: 'mj-nc-panel mj-nc-panel--empty' }, 'Aucun élément sélectionné.');
        }

        var subTabs = [
            { key: 'photos',    label: 'Photos',    icon: '📸' },
            { key: 'documents', label: 'Documents', icon: '📁' },
        ];

        return h('div', { class: 'mj-nc-panel' }, [
            h('div', { class: 'mj-nc-subtabs' },
                subTabs.map(function (tab) {
                    return h('button', {
                        key: tab.key,
                        class: 'mj-nc-subtab' + (activeTab === tab.key ? ' mj-nc-subtab--active' : ''),
                        onClick: function () { setActiveTab(tab.key); },
                    }, [
                        h('span', { class: 'mj-nc-subtab__icon' }, tab.icon),
                        ' ',
                        tab.label,
                    ]);
                })
            ),

            h(MediaTab, {
                key: context + '-' + contextId + '-' + activeTab,
                context: context,
                contextId: contextId,
                mediaType: activeTab,
                apiService: apiService,
            }),
        ]);
    }

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------

    global.MjRegMgrNextcloudFiles = {
        NextcloudFilesPanel: NextcloudFilesPanel,
        MediaTab: MediaTab,
    };

})(window);
