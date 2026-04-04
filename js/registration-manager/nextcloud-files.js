/**
 * Registration Manager - Nextcloud Files Panel
 * Preact component used to browse and manage event/member photos and documents.
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
        if (isPdf(item.mimeType)) { return '📄'; }

        var mime = item.mimeType || '';
        if (mime.indexOf('word') !== -1 || mime.indexOf('document') !== -1) { return '📝'; }
        if (mime.indexOf('spreadsheet') !== -1 || mime.indexOf('excel') !== -1) { return '📊'; }
        if (mime.indexOf('presentation') !== -1 || mime.indexOf('powerpoint') !== -1) { return '📈'; }
        if (mime.indexOf('zip') !== -1 || mime.indexOf('archive') !== -1) { return '🗜️'; }
        if (mime.indexOf('video') !== -1) { return '🎬'; }
        if (mime.indexOf('audio') !== -1) { return '🎵'; }
        return '📎';
    }

    function sortFoldersFirst(items) {
        return (items || []).slice().sort(function (a, b) {
            if (a.type === 'folder' && b.type !== 'folder') { return -1; }
            if (a.type !== 'folder' && b.type === 'folder') { return 1; }
            return String(a.name || '').localeCompare(String(b.name || ''));
        });
    }

    function getLabelForMediaType(mediaType) {
        return mediaType === 'photos' ? 'Photos' : 'Documents';
    }

    function basename(path) {
        var parts = String(path || '').split('/');
        return parts.length ? parts[parts.length - 1] : '';
    }

    function dirname(path) {
        var normalized = String(path || '').replace(/\/+$/, '');
        if (!normalized || normalized.indexOf('/') === -1) {
            return '';
        }
        return normalized.substring(0, normalized.lastIndexOf('/'));
    }

    function buildBreadcrumbSegments(subPath) {
        if (!subPath) { return []; }

        var parts = subPath.split('/').filter(function (part) { return !!part; });
        var current = '';

        return parts.map(function (part) {
            current = current ? current + '/' + part : part;
            return {
                label: part,
                subPath: current,
            };
        });
    }

    function toSubPath(absolutePath, rootPath) {
        var absolute = String(absolutePath || '').replace(/^\/+|\/+$/g, '');
        var root = String(rootPath || '').replace(/^\/+|\/+$/g, '');

        if (!absolute) { return ''; }
        if (!root) { return absolute; }
        if (absolute === root) { return ''; }
        if (absolute.indexOf(root + '/') === 0) {
            return absolute.substring(root.length + 1);
        }
        return absolute;
    }

    function FileUploader(props) {
        var onUpload    = props.onUpload;
        var onReject    = props.onReject;
        var uploading   = props.uploading;
        var mediaType   = props.mediaType;

        var _dragging   = useState(false);
        var dragging    = _dragging[0];
        var setDragging = _dragging[1];
        var inputRef    = useRef(null);

        var acceptAttr = mediaType === 'photos' ? 'image/*' : '*/*';

        function isAcceptedFile(file) {
            if (!file) { return false; }
            if (mediaType !== 'photos') { return true; }

            var fileType = typeof file.type === 'string' ? file.type : '';
            if (fileType.indexOf('image/') === 0) {
                return true;
            }

            var fileName = typeof file.name === 'string' ? file.name.toLowerCase() : '';
            return /\.(avif|bmp|gif|heic|heif|jpe?g|png|svg|webp)$/.test(fileName);
        }

        function handleFiles(files) {
            if (!files || files.length === 0 || typeof onUpload !== 'function') { return; }

            Array.from(files).forEach(function (file) {
                if (!isAcceptedFile(file)) {
                    if (typeof onReject === 'function') {
                        onReject(file);
                    }
                    return;
                }
                onUpload(file);
            });
        }

        function onDrop(event) {
            event.preventDefault();
            setDragging(false);
            handleFiles(event.dataTransfer.files);
        }

        function onDragOver(event) {
            event.preventDefault();
            setDragging(true);
        }

        function onDragLeave() {
            setDragging(false);
        }

        function onInputChange(event) {
            handleFiles(event.target.files);
            event.target.value = '';
        }

        return h('div', {
            class: 'mj-nc-uploader' + (dragging ? ' mj-nc-uploader--drag' : '') + (uploading ? ' mj-nc-uploader--busy' : ''),
            onDrop: onDrop,
            onDragOver: onDragOver,
            onDragLeave: onDragLeave,
            onClick: function () {
                if (!uploading && inputRef.current) {
                    inputRef.current.click();
                }
            },
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

    function PhotoCard(props) {
        var item        = props.item;
        var onDelete    = props.onDelete;
        var onView      = props.onView;
        var deleting    = props.deleting;
        var onDragStart = props.onDragStart;
        var onDragEnd   = props.onDragEnd;
        var isDragging  = props.isDragging;

        return h('div', {
            class: 'mj-nc-photo-card' + (isDragging ? ' mj-nc-photo-card--dragging' : ''),
            draggable: true,
            onDragStart: function (event) { typeof onDragStart === 'function' && onDragStart(item, event); },
            onDragEnd: function () { typeof onDragEnd === 'function' && onDragEnd(); },
        }, [
            h('div', {
                class: 'mj-nc-photo-card__thumb',
                onClick: function () { typeof onView === 'function' && onView(item); },
                title: 'Voir',
            }, [
                (item.thumbnailUrl || item.downloadUrl)
                    ? h('img', { src: item.thumbnailUrl || item.downloadUrl, alt: item.name, loading: 'lazy' })
                    : h('span', { class: 'mj-nc-photo-card__icon' }, fileIcon(item)),
            ]),
            h('div', { class: 'mj-nc-photo-card__footer' }, [
                h('span', { class: 'mj-nc-photo-card__name', title: item.name }, item.name),
                h('div', { class: 'mj-nc-photo-card__actions' }, [
                    h('button', {
                        class: 'mj-nc-btn mj-nc-btn--icon',
                        title: 'Voir',
                        onClick: function (event) {
                            event.stopPropagation();
                            typeof onView === 'function' && onView(item);
                        },
                    }, '👁'),
                    h('button', {
                        class: 'mj-nc-btn mj-nc-btn--icon mj-nc-btn--danger',
                        title: 'Supprimer',
                        disabled: deleting,
                        onClick: function (event) {
                            event.stopPropagation();
                            typeof onDelete === 'function' && onDelete(item);
                        },
                    }, deleting ? '…' : '🗑'),
                ]),
            ]),
        ]);
    }

    function DocumentRow(props) {
        var item        = props.item;
        var onDelete    = props.onDelete;
        var onRename    = props.onRename;
        var deleting    = props.deleting;
        var onDragStart = props.onDragStart;
        var onDragEnd   = props.onDragEnd;
        var isDragging  = props.isDragging;

        var _editing   = useState(false);
        var editing    = _editing[0];
        var setEditing = _editing[1];
        var _newName   = useState(item.name);
        var newName    = _newName[0];
        var setNewName = _newName[1];

        function saveRename() {
            if (newName.trim() !== '' && newName.trim() !== item.name && typeof onRename === 'function') {
                onRename(item, newName.trim());
            }
            setEditing(false);
        }

        function onKeyDown(event) {
            if (event.key === 'Enter') { saveRename(); }
            if (event.key === 'Escape') {
                setEditing(false);
                setNewName(item.name);
            }
        }

        return h('div', {
            class: 'mj-nc-doc-row' + (isDragging ? ' mj-nc-doc-row--dragging' : ''),
            draggable: true,
            onDragStart: function (event) { typeof onDragStart === 'function' && onDragStart(item, event); },
            onDragEnd: function () { typeof onDragEnd === 'function' && onDragEnd(); },
        }, [
            h('span', { class: 'mj-nc-doc-row__icon' }, fileIcon(item)),

            editing
                ? h('input', {
                    class: 'mj-nc-doc-row__rename-input',
                    value: newName,
                    autoFocus: true,
                    onInput: function (event) { setNewName(event.target.value); },
                    onBlur: saveRename,
                    onKeyDown: onKeyDown,
                })
                : h('span', { class: 'mj-nc-doc-row__name', title: item.name }, item.name),

            h('span', { class: 'mj-nc-doc-row__size' }, formatSize(item.size)),

            h('div', { class: 'mj-nc-doc-row__actions' }, [
                (item.editUrl || item.webUrl) && h('a', {
                    class: 'mj-nc-btn mj-nc-btn--icon',
                    href: item.editUrl || item.webUrl,
                    title: 'Éditer dans Nextcloud',
                    onClick: function (event) { event.stopPropagation(); },
                }, '📝'),

                h('button', {
                    class: 'mj-nc-btn mj-nc-btn--icon',
                    title: 'Renommer',
                    onClick: function (event) {
                        event.stopPropagation();
                        setEditing(true);
                        setNewName(item.name);
                    },
                }, '✏️'),

                h('button', {
                    class: 'mj-nc-btn mj-nc-btn--icon mj-nc-btn--danger',
                    title: 'Supprimer',
                    disabled: deleting,
                    onClick: function (event) {
                        event.stopPropagation();
                        typeof onDelete === 'function' && onDelete(item);
                    },
                }, deleting ? '…' : '🗑'),
            ]),
        ]);
    }

    function TreeNode(props) {
        var node             = props.node;
        var treeNodes        = props.treeNodes;
        var expandedFolders  = props.expandedFolders;
        var selectedSubPath  = props.selectedSubPath;
        var dropTargetPath   = props.dropTargetPath;
        var draggingItemPath = props.draggingItemPath;
        var onToggle         = props.onToggle;
        var onSelect         = props.onSelect;
        var onDropItem       = props.onDropItem;
        var setDropTargetPath = props.setDropTargetPath;
        var level            = props.level || 0;

        var isExpanded = !!expandedFolders[node.subPath];
        var isSelected = node.subPath === selectedSubPath;
        var isDroppable = !!draggingItemPath && !!node.absolutePath;
        var isDropTarget = dropTargetPath === node.subPath;
        var hasChildren = node.loaded ? (node.children || []).length > 0 : true;
        var label = node.name || 'Dossier';
        var folderUrl = node.webUrl || '';

        function handleToggle(event) {
            event.stopPropagation();
            typeof onToggle === 'function' && onToggle(node);
        }

        function handleSelect() {
            typeof onSelect === 'function' && onSelect(node.subPath);
        }

        function handleDragOver(event) {
            if (!isDroppable) { return; }
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            setDropTargetPath(node.subPath);
        }

        function handleDragLeave() {
            if (isDropTarget) {
                setDropTargetPath('');
            }
        }

        function handleDrop(event) {
            if (!isDroppable) { return; }
            event.preventDefault();
            setDropTargetPath('');

            var raw = event.dataTransfer.getData('application/x-mj-nc-item');
            if (!raw) { return; }

            try {
                var payload = JSON.parse(raw);
                typeof onDropItem === 'function' && onDropItem(payload, node);
            } catch (error) {
                return;
            }
        }

        return h('div', { class: 'mj-nc-tree-node' }, [
            h('div', {
                class: 'mj-nc-tree-node__row'
                    + (isSelected ? ' mj-nc-tree-node__row--selected' : '')
                    + (isDropTarget ? ' mj-nc-tree-node__row--drop-target' : ''),
                style: { paddingLeft: String(level * 14) + 'px' },
                onDragOver: handleDragOver,
                onDragLeave: handleDragLeave,
                onDrop: handleDrop,
            }, [
                h('button', {
                    class: 'mj-nc-tree-node__toggle',
                    type: 'button',
                    onClick: handleToggle,
                    title: isExpanded ? 'Réduire' : 'Déplier',
                }, hasChildren ? (isExpanded ? '▾' : '▸') : '•'),
                h('button', {
                    class: 'mj-nc-tree-node__label',
                    type: 'button',
                    onClick: handleSelect,
                    title: label,
                }, [
                    h('span', { class: 'mj-nc-tree-node__icon' }, '📁'),
                    h('span', { class: 'mj-nc-tree-node__text' }, label),
                    node.loading && h('span', { class: 'mj-nc-tree-node__loading' }, '…'),
                ]),
                folderUrl && h('a', {
                    class: 'mj-nc-tree-node__action mj-nc-btn mj-nc-btn--icon',
                    href: folderUrl,
                    title: 'Ouvrir dans Nextcloud',
                    onClick: function (event) { event.stopPropagation(); },
                }, '↗'),
            ]),

            isExpanded && node.loaded && (node.children || []).length > 0 && h('div', { class: 'mj-nc-tree-node__children' },
                node.children.map(function (child) {
                    var childNode = treeNodes[child.subPath] || child;
                    return h(TreeNode, {
                        key: child.subPath,
                        node: childNode,
                        treeNodes: treeNodes,
                        expandedFolders: expandedFolders,
                        selectedSubPath: selectedSubPath,
                        dropTargetPath: dropTargetPath,
                        draggingItemPath: draggingItemPath,
                        onToggle: onToggle,
                        onSelect: onSelect,
                        onDropItem: onDropItem,
                        setDropTargetPath: setDropTargetPath,
                        level: level + 1,
                    });
                })
            ),
        ]);
    }

    function Lightbox(props) {
        var item = props.item;
        var onClose = props.onClose;

        if (!item) { return null; }

        return h('div', {
            class: 'mj-nc-lightbox',
            onClick: onClose,
        }, [
            h('div', {
                class: 'mj-nc-lightbox__inner',
                onClick: function (event) { event.stopPropagation(); },
            }, [
                h('button', { class: 'mj-nc-lightbox__close', onClick: onClose }, '×'),
                h('img', { src: item.downloadUrl, alt: item.name, class: 'mj-nc-lightbox__img' }),
                h('p', { class: 'mj-nc-lightbox__caption' }, item.name),
            ]),
        ]);
    }

    function MediaTab(props) {
        var context    = props.context;
        var contextId  = props.contextId;
        var mediaType  = props.mediaType;
        var apiService = props.apiService;

        var _items = useState([]);
        var items = _items[0];
        var setItems = _items[1];
        var _loading = useState(false);
        var loading = _loading[0];
        var setLoading = _loading[1];
        var _uploading = useState(false);
        var uploading = _uploading[0];
        var setUploading = _uploading[1];
        var _error = useState('');
        var error = _error[0];
        var setError = _error[1];
        var _lightbox = useState(null);
        var lightboxItem = _lightbox[0];
        var setLightbox = _lightbox[1];
        var _deletingPath = useState('');
        var deletingPath = _deletingPath[0];
        var setDeletingPath = _deletingPath[1];
        var _movingPath = useState('');
        var movingPath = _movingPath[0];
        var setMovingPath = _movingPath[1];
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
        var _selectedSubPath = useState('');
        var selectedSubPath = _selectedSubPath[0];
        var setSelectedSubPath = _selectedSubPath[1];
        var _rootFolderPath = useState('');
        var rootFolderPath = _rootFolderPath[0];
        var setRootFolderPath = _rootFolderPath[1];
        var _treeNodes = useState({});
        var treeNodes = _treeNodes[0];
        var setTreeNodes = _treeNodes[1];
        var _expandedFolders = useState({ '': true });
        var expandedFolders = _expandedFolders[0];
        var setExpandedFolders = _expandedFolders[1];
        var _dropTargetPath = useState('');
        var dropTargetPath = _dropTargetPath[0];
        var setDropTargetPath = _dropTargetPath[1];
        var _draggingItemPath = useState('');
        var draggingItemPath = _draggingItemPath[0];
        var setDraggingItemPath = _draggingItemPath[1];

        var isPhotos = mediaType === 'photos';
        var mediaLabel = getLabelForMediaType(mediaType);
        var breadcrumbSegments = buildBreadcrumbSegments(selectedSubPath);
        var folderItems = sortFoldersFirst(items.filter(function (item) { return item.type === 'folder'; }));
        var fileItems = sortFoldersFirst(items.filter(function (item) { return item.type === 'file'; }));

        function resetTreeState() {
            setTreeNodes({
                '': {
                    subPath: '',
                    name: mediaLabel,
                    absolutePath: '',
                    loaded: false,
                    loading: false,
                    exists: true,
                    children: [],
                },
            });
            setExpandedFolders({ '': true });
        }

        function ensureExpandedPath(subPath) {
            setExpandedFolders(function (prev) {
                var next = Object.assign({}, prev);
                next[''] = true;

                if (subPath) {
                    var current = '';
                    subPath.split('/').forEach(function (segment) {
                        current = current ? current + '/' + segment : segment;
                        next[current] = true;
                    });
                }

                return next;
            });
        }

        function updateTreeNode(subPath, absoluteFolder, folderWebUrl, fetchedItems, exists) {
            var effectiveRoot = rootFolderPath || (subPath === '' ? absoluteFolder : '');
            var children = exists
                ? sortFoldersFirst((fetchedItems || []).filter(function (item) { return item.type === 'folder'; }))
                    .map(function (item) {
                        return {
                            subPath: toSubPath(item.path, effectiveRoot || absoluteFolder),
                            absolutePath: item.path,
                            name: item.name,
                            webUrl: item.webUrl || '',
                            loaded: false,
                            loading: false,
                            children: [],
                        };
                    })
                : [];

            setTreeNodes(function (prev) {
                var next = Object.assign({}, prev);
                var currentNode = next[subPath] || {
                    subPath: subPath,
                    name: subPath ? basename(absoluteFolder) : mediaLabel,
                    absolutePath: absoluteFolder,
                    children: [],
                };

                next[subPath] = Object.assign({}, currentNode, {
                    subPath: subPath,
                    name: subPath ? basename(absoluteFolder) : mediaLabel,
                    absolutePath: absoluteFolder,
                    webUrl: folderWebUrl || currentNode.webUrl || '',
                    loaded: true,
                    loading: false,
                    exists: exists,
                    children: children,
                });

                children.forEach(function (child) {
                    var previousChild = next[child.subPath] || {};
                    next[child.subPath] = Object.assign({}, previousChild, child, {
                        loaded: !!previousChild.loaded,
                        loading: false,
                        children: previousChild.children || [],
                    });
                });

                return next;
            });
        }

        var loadFolder = useCallback(function (subPath, options) {
            if (!apiService || !contextId) { return Promise.resolve(); }

            var settings = options && typeof options === 'object' ? options : {};
            if (!settings.treeOnly) {
                setLoading(true);
            }
            setError('');

            return apiService.ncListFolder(context, contextId, mediaType, subPath || '')
                .then(function (data) {
                    var absoluteFolder = data.folderPath || '';
                    var exists = data.folderExists !== false;

                    if (!rootFolderPath && subPath === '') {
                        setRootFolderPath(absoluteFolder);
                    }

                    updateTreeNode(subPath || '', absoluteFolder, data.folderWebUrl || '', data.items || [], exists);

                    if (!settings.treeOnly) {
                        setFolderPath(absoluteFolder);
                        setFolderExists(exists);
                        setItems(data.items || []);
                        setLoading(false);
                    }

                    return data;
                })
                .catch(function (err) {
                    if (!settings.treeOnly) {
                        setLoading(false);
                    }
                    setError(err && err.message ? err.message : 'Erreur de chargement');
                    return Promise.reject(err);
                });
        }, [apiService, context, contextId, mediaType, rootFolderPath]);

        useEffect(function () {
            setItems([]);
            setError('');
            setFolderPath('');
            setFolderExists(true);
            setNewFolderName('');
            setSelectedSubPath('');
            setRootFolderPath('');
            setDraggingItemPath('');
            setDropTargetPath('');
            resetTreeState();
        }, [context, contextId, mediaType]);

        useEffect(function () {
            loadFolder(selectedSubPath || '');
        }, [loadFolder, selectedSubPath]);

        var handleRejectedUpload = useCallback(function () {
            setError('Seules les images sont autorisées dans l\'onglet Photos.');
        }, []);

        var handleUpload = useCallback(function (file) {
            if (!folderExists) {
                setError('Le dossier n\'existe pas encore. Créez-le d\'abord.');
                return;
            }

            setUploading(true);
            setError('');

            apiService.ncUpload(context, contextId, mediaType, file, selectedSubPath || '')
                .then(function () {
                    setUploading(false);
                    loadFolder(selectedSubPath || '');
                })
                .catch(function (err) {
                    setUploading(false);
                    setError(err && err.message ? err.message : 'Échec de l\'envoi');
                });
        }, [apiService, context, contextId, folderExists, loadFolder, mediaType, selectedSubPath]);

        var handleCreateFolder = useCallback(function (name) {
            setCreatingFolder(true);
            setError('');

            apiService.ncCreateFolder(context, contextId, mediaType, selectedSubPath || '', name || '')
                .then(function () {
                    setCreatingFolder(false);
                    setNewFolderName('');
                    ensureExpandedPath(selectedSubPath || '');
                    loadFolder(selectedSubPath || '');
                })
                .catch(function (err) {
                    setCreatingFolder(false);
                    setError(err && err.message ? err.message : 'Échec de création du dossier');
                });
        }, [apiService, context, contextId, loadFolder, mediaType, selectedSubPath]);

        var handleDelete = useCallback(function (item) {
            if (!window.confirm('Supprimer « ' + item.name + ' » ?')) { return; }

            setDeletingPath(item.path);
            apiService.ncDelete(item.path)
                .then(function () {
                    setDeletingPath('');
                    loadFolder(selectedSubPath || '');
                })
                .catch(function (err) {
                    setDeletingPath('');
                    setError(err && err.message ? err.message : 'Échec de la suppression');
                });
        }, [apiService, loadFolder, selectedSubPath]);

        var handleRename = useCallback(function (item, newName) {
            apiService.ncRename(item.path, newName)
                .then(function () {
                    loadFolder(selectedSubPath || '');
                })
                .catch(function (err) {
                    setError(err && err.message ? err.message : 'Échec du renommage');
                });
        }, [apiService, loadFolder, selectedSubPath]);

        var handleTreeToggle = useCallback(function (node) {
            var nodeSubPath = node.subPath || '';
            var willExpand = !expandedFolders[nodeSubPath];

            setExpandedFolders(function (prev) {
                var next = Object.assign({}, prev);
                next[nodeSubPath] = willExpand;
                return next;
            });

            if (willExpand && (!treeNodes[nodeSubPath] || !treeNodes[nodeSubPath].loaded)) {
                setTreeNodes(function (prev) {
                    var next = Object.assign({}, prev);
                    var previous = next[nodeSubPath] || node;
                    next[nodeSubPath] = Object.assign({}, previous, { loading: true });
                    return next;
                });
                loadFolder(nodeSubPath, { treeOnly: true });
            }
        }, [expandedFolders, loadFolder, treeNodes]);

        var handleSelectFolder = useCallback(function (subPath) {
            ensureExpandedPath(subPath || '');
            setSelectedSubPath(subPath || '');
        }, []);

        var handleDragStart = useCallback(function (item, event) {
            if (!event || !event.dataTransfer || !item || item.type !== 'file') {
                return;
            }

            var payload = {
                path: item.path,
                name: item.name,
                type: item.type,
            };

            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-mj-nc-item', JSON.stringify(payload));
            event.dataTransfer.setData('text/plain', item.path);
            setDraggingItemPath(item.path);
        }, []);

        var handleDragEnd = useCallback(function () {
            setDraggingItemPath('');
            setDropTargetPath('');
        }, []);

        var handleDropItem = useCallback(function (payload, node) {
            if (!payload || payload.type !== 'file' || !node || !node.absolutePath) {
                return;
            }

            var sourceParent = dirname(payload.path);
            if (sourceParent === node.absolutePath) {
                return;
            }

            setMovingPath(payload.path);
            apiService.ncMove(payload.path, node.absolutePath)
                .then(function () {
                    setMovingPath('');
                    setDraggingItemPath('');
                    setDropTargetPath('');
                    loadFolder(selectedSubPath || '');
                })
                .catch(function (err) {
                    setMovingPath('');
                    setDraggingItemPath('');
                    setDropTargetPath('');
                    setError(err && err.message ? err.message : 'Échec du déplacement');
                });
        }, [apiService, loadFolder, selectedSubPath]);

        return h('div', { class: 'mj-nc-media-tab' }, [
            h('div', { class: 'mj-nc-layout' }, [
                h('section', { class: 'mj-nc-main' }, [
                    h('div', { class: 'mj-nc-breadcrumb' }, [
                        h('button', {
                            class: 'mj-nc-breadcrumb__item' + (selectedSubPath === '' ? ' mj-nc-breadcrumb__item--active' : ''),
                            onClick: function () { handleSelectFolder(''); },
                        }, mediaLabel),
                        breadcrumbSegments.map(function (segment, index) {
                            var isLast = index === breadcrumbSegments.length - 1;
                            return [
                                h('span', { key: 'sep-' + segment.subPath, class: 'mj-nc-breadcrumb__sep' }, '›'),
                                isLast
                                    ? h('span', { key: 'seg-' + segment.subPath, class: 'mj-nc-breadcrumb__item mj-nc-breadcrumb__item--active' }, segment.label)
                                    : h('button', {
                                        key: 'seg-' + segment.subPath,
                                        class: 'mj-nc-breadcrumb__item',
                                        onClick: function () { handleSelectFolder(segment.subPath); },
                                    }, segment.label),
                            ];
                        }),
                    ]),

                    h('div', { class: 'mj-nc-toolbar' }, [
                        h(FileUploader, {
                            onUpload: handleUpload,
                            onReject: handleRejectedUpload,
                            uploading: uploading,
                            mediaType: mediaType,
                        }),
                    ]),

                    error && h('div', { class: 'mj-nc-error' }, error),

                    h('div', { class: 'mj-nc-section-head' }, [
                        h('div', { class: 'mj-nc-section-head__title' }, basename(folderPath) || mediaLabel),
                        h('div', { class: 'mj-nc-section-head__meta' }, [
                            fileItems.length + ' fichier' + (fileItems.length > 1 ? 's' : ''),
                            ' • ',
                            folderItems.length + ' dossier' + (folderItems.length > 1 ? 's' : ''),
                            movingPath && ' • Déplacement…',
                        ]),
                    ]),

                    !folderExists && h('div', { class: 'mj-nc-empty' }, [
                        h('span', null, 'Le dossier Nextcloud est introuvable.'),
                    ]),

                    loading && h('div', { class: 'mj-nc-loading' }, [
                        h('div', { class: 'mj-regmgr-loading__spinner' }),
                    ]),

                    !loading && folderExists && fileItems.length === 0 && h('div', { class: 'mj-nc-empty' }, [
                        h('span', null, isPhotos ? '📸 Aucun fichier image dans ce dossier.' : '📁 Aucun fichier dans ce dossier.'),
                    ]),

                    !loading && folderExists && isPhotos && fileItems.length > 0 && h('div', { class: 'mj-nc-photo-grid' },
                        fileItems.map(function (item) {
                            return h(PhotoCard, {
                                key: item.path,
                                item: item,
                                deleting: deletingPath === item.path || movingPath === item.path,
                                onView: function (currentItem) { setLightbox(currentItem); },
                                onDelete: handleDelete,
                                onDragStart: handleDragStart,
                                onDragEnd: handleDragEnd,
                                isDragging: draggingItemPath === item.path,
                            });
                        })
                    ),

                    !loading && folderExists && !isPhotos && fileItems.length > 0 && h('div', { class: 'mj-nc-doc-list' },
                        fileItems.map(function (item) {
                            return h(DocumentRow, {
                                key: item.path,
                                item: item,
                                deleting: deletingPath === item.path || movingPath === item.path,
                                onDelete: handleDelete,
                                onRename: handleRename,
                                onDragStart: handleDragStart,
                                onDragEnd: handleDragEnd,
                                isDragging: draggingItemPath === item.path,
                            });
                        })
                    ),
                ]),

                h('aside', { class: 'mj-nc-sidebar' }, [
                    h('div', { class: 'mj-nc-sidebar__header' }, [
                        h('h4', { class: 'mj-nc-sidebar__title' }, 'Arborescence'),
                        h('p', { class: 'mj-nc-sidebar__hint' }, 'Cliquez sur un dossier pour lister ses fichiers. Glissez un fichier sur un dossier pour le déplacer.'),
                    ]),
                    h('div', { class: 'mj-nc-sidebar__actions' }, [
                        h('button', {
                            class: 'mj-nc-btn',
                            disabled: creatingFolder,
                            onClick: function () { handleCreateFolder(''); },
                            title: 'Créer le dossier courant s\'il n\'existe pas',
                        }, creatingFolder ? 'Création…' : 'Créer le dossier'),
                        h('input', {
                            class: 'mj-nc-doc-row__rename-input mj-nc-sidebar__input',
                            placeholder: 'Nouveau sous-dossier',
                            value: newFolderName,
                            onInput: function (event) { setNewFolderName(event.target.value); },
                        }),
                        h('button', {
                            class: 'mj-nc-btn',
                            disabled: creatingFolder || !newFolderName.trim(),
                            onClick: function () { handleCreateFolder(newFolderName.trim()); },
                        }, 'Créer sous-dossier'),
                    ]),
                    h('div', { class: 'mj-nc-tree' }, [
                        treeNodes[''] && h(TreeNode, {
                            node: treeNodes[''],
                            treeNodes: treeNodes,
                            expandedFolders: expandedFolders,
                            selectedSubPath: selectedSubPath,
                            dropTargetPath: dropTargetPath,
                            draggingItemPath: draggingItemPath,
                            onToggle: handleTreeToggle,
                            onSelect: handleSelectFolder,
                            onDropItem: handleDropItem,
                            setDropTargetPath: setDropTargetPath,
                            level: 0,
                        }),
                    ]),
                ]),
            ]),

            h(Lightbox, {
                item: lightboxItem,
                onClose: function () { setLightbox(null); },
            }),
        ]);
    }

    function NextcloudFilesPanel(props) {
        var context    = props.context;
        var contextId  = props.contextId;
        var apiService = props.apiService;

        var _activeTab = useState('photos');
        var activeTab = _activeTab[0];
        var setActiveTab = _activeTab[1];

        if (!contextId) {
            return h('div', { class: 'mj-nc-panel mj-nc-panel--empty' }, 'Aucun élément sélectionné.');
        }

        var subTabs = [
            { key: 'photos', label: 'Photos', icon: '📸' },
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

    global.MjRegMgrNextcloudFiles = {
        NextcloudFilesPanel: NextcloudFilesPanel,
        MediaTab: MediaTab,
    };

})(window);
