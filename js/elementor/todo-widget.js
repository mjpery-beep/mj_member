(function () {
    'use strict';

    var runtimeConfig = window.mjMemberTodoWidget || {};
    var preact = window.preact;
    var hooks = window.preactHooks;

    if (!preact || !hooks) {
        if (typeof console !== 'undefined' && typeof console.warn === 'function') {
            console.warn('[MJ Member] Preact must be loaded before the todo widget.');
        }
        return;
    }

    var h = preact.h;
    var Fragment = preact.Fragment;
    var render = preact.render;
    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useMemo = hooks.useMemo;
    var useCallback = hooks.useCallback;
    var useRef = hooks.useRef;

    function renderNoteContent(text) {
        var value = typeof text === 'string' ? text : '';
        if (value.indexOf('\n') === -1 && value.indexOf('\r') === -1) {
            return value;
        }

        var segments = value.split(/\r?\n/);
        var nodes = [];
        for (var i = 0; i < segments.length; i += 1) {
            if (i > 0) {
                nodes.push(h('br', { key: 'br-' + i }));
            }
            nodes.push(segments[i]);
        }
        return nodes;
    }

    var Utils = window.MjMemberUtils || {};
    var domReady = typeof Utils.domReady === 'function'
        ? Utils.domReady
        : function (callback) {
            if (typeof callback !== 'function') {
                return;
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', callback, { once: true });
            } else {
                callback();
            }
        };

    function toArray(value) {
        if (Array.isArray(value)) {
            return value.slice();
        }
        if (value === undefined || value === null) {
            return [];
        }
        return [value];
    }

    function encodeForm(data) {
        var params = new URLSearchParams();
        Object.keys(data).forEach(function (key) {
            var value = data[key];
            if (value === undefined || value === null) {
                return;
            }
            if (Array.isArray(value)) {
                value.forEach(function (entry) {
                    if (entry === undefined || entry === null) {
                        return;
                    }
                    params.append(key, entry);
                });
            } else {
                params.append(key, value);
            }
        });
        return params.toString();
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

    function getString(i18n, key, fallback) {
        if (i18n && typeof i18n === 'object') {
            var value = i18n[key];
            if (typeof value === 'string' && value !== '') {
                return value;
            }
        }
        return fallback;
    }

    function clampPriorityValue(value) {
        var number = 0;
        if (typeof value === 'number' && !isNaN(value)) {
            number = value;
        } else if (typeof value === 'string' && value !== '') {
            var parsed = parseInt(value, 10);
            number = isNaN(parsed) ? 0 : parsed;
        }
        if (number <= 0) {
            number = 3;
        }
        if (number > 5) {
            number = 5;
        }
        return number;
    }

    function getPriorityValue(todo) {
        if (!todo || typeof todo !== 'object') {
            return 3;
        }
        if (todo.priority !== undefined) {
            return clampPriorityValue(todo.priority);
        }
        if (todo.position !== undefined) {
            return clampPriorityValue(todo.position);
        }
        return 3;
    }

    function compareByPriority(a, b) {
        var priorityA = getPriorityValue(a);
        var priorityB = getPriorityValue(b);
        if (priorityA !== priorityB) {
            return priorityB - priorityA;
        }
        var dueA = a && typeof a.dueDate === 'string' ? a.dueDate : '';
        var dueB = b && typeof b.dueDate === 'string' ? b.dueDate : '';
        if (dueA && dueB && dueA !== dueB) {
            return dueA.localeCompare(dueB);
        }
        if (!dueA && dueB) {
            return 1;
        }
        if (dueA && !dueB) {
            return -1;
        }
        var titleA = a && typeof a.title === 'string' ? a.title.toLowerCase() : '';
        var titleB = b && typeof b.title === 'string' ? b.title.toLowerCase() : '';
        if (titleA !== titleB) {
            return titleA.localeCompare(titleB);
        }
        return String(a && a.id !== undefined ? a.id : '').localeCompare(String(b && b.id !== undefined ? b.id : ''));
    }

    function normalizeTodosList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(function (item) {
            var rawId = item && item.id !== undefined ? item.id : item && item.todo_id !== undefined ? item.todo_id : '';
            var projectIdValue = item && item.projectId !== undefined ? item.projectId : item && item.project_id !== undefined ? item.project_id : '';
            var rawAssignees = item && Array.isArray(item.assignees) ? item.assignees : [];
            var assignees = rawAssignees.map(function (assignee) {
                var assigneeId = assignee && assignee.id !== undefined ? assignee.id : assignee && assignee.member_id !== undefined ? assignee.member_id : '';
                return {
                    id: assigneeId,
                    name: assignee && typeof assignee.name === 'string' ? assignee.name : '',
                    role: assignee && typeof assignee.role === 'string' ? assignee.role : '',
                    isSelf: !!(assignee && assignee.isSelf),
                    assignedAt: assignee && typeof assignee.assignedAt === 'string' ? assignee.assignedAt : assignee && typeof assignee.assigned_at === 'string' ? assignee.assigned_at : '',
                    assignedBy: assignee && assignee.assignedBy !== undefined ? assignee.assignedBy : assignee && assignee.assigned_by !== undefined ? assignee.assigned_by : '',
                };
            }).filter(function (assignee) {
                return assignee && assignee.id !== undefined && assignee.id !== null && assignee.id !== '';
            });
            return {
                id: rawId,
                title: item && typeof item.title === 'string' ? item.title : '',
                description: item && typeof item.description === 'string' ? item.description : '',
                status: item && typeof item.status === 'string' ? item.status : 'open',
                priority: clampPriorityValue(item && item.priority !== undefined ? item.priority : item && item.position !== undefined ? item.position : 0),
                projectId: projectIdValue,
                projectTitle: item && typeof item.projectTitle === 'string' ? item.projectTitle : '',
                dueDate: item && typeof item.dueDate === 'string' ? item.dueDate : '',
                completedAt: item && typeof item.completedAt === 'string' ? item.completedAt : '',
                assignees: assignees,
                media: normalizeMediaList((function () {
                    if (!item || typeof item !== 'object') {
                        return [];
                    }
                    if (Array.isArray(item.media)) {
                        return item.media;
                    }
                    if (Array.isArray(item.mediaEntries)) {
                        return item.mediaEntries;
                    }
                    if (Array.isArray(item.media_list)) {
                        return item.media_list;
                    }
                    return [];
                })()),
                notes: normalizeNotesList(item && item.notes),
            };
        });
    }

    function normalizeProjectsList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(function (item) {
            var rawId = item && item.id !== undefined ? item.id : item && item.project_id !== undefined ? item.project_id : '';
            return {
                id: rawId,
                title: item && typeof item.title === 'string' ? item.title : '',
                color: item && typeof item.color === 'string' ? item.color : '',
            };
        });
    }

    function extractInitials(value) {
        var name = typeof value === 'string' ? value.trim() : '';
        if (name === '') {
            return '';
        }
        var parts = name.split(/\s+/).filter(function (segment) { return segment !== ''; });
        if (parts.length === 0) {
            return '';
        }
        var first = parts[0].charAt(0);
        var second = parts.length > 1 ? parts[parts.length - 1].charAt(0) : (parts[0].length > 1 ? parts[0].charAt(1) : '');
        var initials = (first + second).toUpperCase();
        if (initials.length > 2) {
            initials = initials.slice(0, 2);
        }
        return initials;
    }

    function normalizeMembersList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(function (item) {
            var rawId = item && item.id !== undefined ? item.id : item && item.member_id !== undefined ? item.member_id : '';
            var avatarPayload = item && typeof item.avatar === 'object' && item.avatar !== null ? item.avatar : {};
            var avatarUrl = typeof avatarPayload.url === 'string' ? avatarPayload.url : typeof item.avatarUrl === 'string' ? item.avatarUrl : '';
            var rawInitials = typeof avatarPayload.initials === 'string' ? avatarPayload.initials : typeof item.avatarInitials === 'string' ? item.avatarInitials : '';
            var avatarInitials = typeof rawInitials === 'string' ? rawInitials.trim() : '';
            if (avatarInitials) {
                avatarInitials = avatarInitials.toUpperCase().slice(0, 2);
            }
            var avatarAlt = typeof avatarPayload.alt === 'string' ? avatarPayload.alt : typeof item.avatarAlt === 'string' ? item.avatarAlt : '';
            return {
                id: rawId,
                name: item && typeof item.name === 'string' ? item.name : '',
                role: item && typeof item.role === 'string' ? item.role : '',
                isSelf: !!(item && item.isSelf),
                avatar: {
                    url: typeof avatarUrl === 'string' ? avatarUrl : '',
                    initials: avatarInitials && typeof avatarInitials === 'string' ? avatarInitials : '',
                    alt: typeof avatarAlt === 'string' ? avatarAlt : '',
                },
            };
        }).filter(function (member) {
            if (!member || member.id === undefined || member.id === null || member.id === '') {
                return false;
            }
            if ((!member.avatar || !member.avatar.initials) && member && typeof member.name === 'string') {
                var fallbackInitials = extractInitials(member.name);
                if (!member.avatar) {
                    member.avatar = { url: '', initials: fallbackInitials, alt: '' };
                } else {
                    member.avatar.initials = fallbackInitials;
                }
            }
            if (member && member.avatar && (!member.avatar.alt || member.avatar.alt === '') && typeof member.name === 'string' && member.name !== '') {
                member.avatar.alt = 'Avatar de ' + member.name;
            }
            return true;
        });
    }

    function normalizeNoteEntry(note) {
        if (!note || typeof note !== 'object') {
            return {
                id: '',
                todoId: '',
                memberId: '',
                content: '',
                authorName: '',
                createdAt: '',
            };
        }
        var rawId = note.id !== undefined ? note.id : note.note_id !== undefined ? note.note_id : '';
        var todoIdValue = note.todoId !== undefined ? note.todoId : note.todo_id !== undefined ? note.todo_id : '';
        var memberIdValue = note.memberId !== undefined ? note.memberId : note.member_id !== undefined ? note.member_id : '';
        return {
            id: rawId,
            todoId: todoIdValue,
            memberId: memberIdValue,
            content: typeof note.content === 'string' ? note.content : '',
            authorName: typeof note.authorName === 'string' ? note.authorName : typeof note.author_name === 'string' ? note.author_name : '',
            createdAt: typeof note.createdAt === 'string' ? note.createdAt : typeof note.created_at === 'string' ? note.created_at : '',
        };
    }

    function normalizeNotesList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(normalizeNoteEntry).filter(function (entry) {
            return entry && entry.id !== undefined && entry.id !== null && entry.id !== '';
        });
    }

    function normalizeMediaEntry(entry) {
        if (!entry || typeof entry !== 'object') {
            return {
                id: '',
                todoId: '',
                attachmentId: '',
                title: '',
                filename: '',
                url: '',
                previewUrl: '',
                iconUrl: '',
                mimeType: '',
                type: '',
                addedAt: '',
                addedBy: '',
                addedByUser: '',
            };
        }

        var recordId = entry.id !== undefined ? entry.id : entry.media_id !== undefined ? entry.media_id : '';
        var todoIdValue = entry.todoId !== undefined ? entry.todoId : entry.todo_id !== undefined ? entry.todo_id : '';
        var attachmentIdValue = entry.attachmentId !== undefined ? entry.attachmentId : entry.attachment_id !== undefined ? entry.attachment_id : '';
        var title = typeof entry.title === 'string' ? entry.title : '';
        var filename = typeof entry.filename === 'string' ? entry.filename : '';
        var url = typeof entry.url === 'string' ? entry.url : '';
        var previewUrl = typeof entry.previewUrl === 'string' ? entry.previewUrl : typeof entry.preview_url === 'string' ? entry.preview_url : '';
        var iconUrl = typeof entry.iconUrl === 'string' ? entry.iconUrl : typeof entry.icon_url === 'string' ? entry.icon_url : '';
        var mimeType = typeof entry.mimeType === 'string' ? entry.mimeType : typeof entry.mime_type === 'string' ? entry.mime_type : '';
        var typeValue = typeof entry.type === 'string' ? entry.type : '';

        if (!typeValue && mimeType && mimeType.indexOf('/') !== -1) {
            typeValue = mimeType.split('/')[0];
        }

        var addedAt = typeof entry.addedAt === 'string' ? entry.addedAt : typeof entry.created_at === 'string' ? entry.created_at : '';
        var addedBy = entry.addedBy !== undefined ? entry.addedBy : entry.member_id !== undefined ? entry.member_id : '';
        var addedByUser = entry.addedByUser !== undefined ? entry.addedByUser : entry.wp_user_id !== undefined ? entry.wp_user_id : '';

        return {
            id: recordId,
            todoId: todoIdValue,
            attachmentId: attachmentIdValue,
            title: title,
            filename: filename,
            url: url,
            previewUrl: previewUrl,
            iconUrl: iconUrl,
            mimeType: mimeType,
            type: typeValue,
            addedAt: addedAt,
            addedBy: addedBy,
            addedByUser: addedByUser,
        };
    }

    function normalizeMediaList(list) {
        if (!Array.isArray(list)) {
            return [];
        }
        return list.map(normalizeMediaEntry).filter(function (entry) {
            return entry && entry.attachmentId !== undefined && entry.attachmentId !== null && entry.attachmentId !== '';
        });
    }

    function normalizeMediaSelectionEntry(selection) {
        if (!selection || typeof selection !== 'object') {
            return null;
        }

        var attachmentIdValue = selection.attachmentId !== undefined && selection.attachmentId !== null
            ? selection.attachmentId
            : selection.attachment_id !== undefined && selection.attachment_id !== null
                ? selection.attachment_id
                : selection.ID !== undefined && selection.ID !== null
                    ? selection.ID
                    : selection.id !== undefined && selection.id !== null
                        ? selection.id
                        : '';
        if (attachmentIdValue === '' || attachmentIdValue === null) {
            return null;
        }

        var titleValue = '';
        if (typeof selection.title === 'string') {
            titleValue = selection.title;
        } else if (selection.title && typeof selection.title.rendered === 'string') {
            titleValue = selection.title.rendered;
        }

        var filenameValue = typeof selection.filename === 'string'
            ? selection.filename
            : typeof selection.file === 'string'
                ? selection.file
                : '';
        var urlValue = typeof selection.url === 'string' ? selection.url : typeof selection.link === 'string' ? selection.link : '';
        var iconValue = typeof selection.icon === 'string' ? selection.icon : typeof selection.iconUrl === 'string' ? selection.iconUrl : '';

        var previewValue = '';
        if (typeof selection.preview_url === 'string' && selection.preview_url !== '') {
            previewValue = selection.preview_url;
        } else if (selection.sizes && typeof selection.sizes === 'object') {
            var preferredSizes = ['medium_large', 'medium', 'large', 'thumbnail', 'full'];
            for (var i = 0; i < preferredSizes.length; i += 1) {
                var sizeKey = preferredSizes[i];
                var sizeEntry = selection.sizes[sizeKey];
                if (sizeEntry && typeof sizeEntry.url === 'string' && sizeEntry.url !== '') {
                    previewValue = sizeEntry.url;
                    break;
                }
            }
        }
        if (previewValue === '' && typeof selection.url === 'string') {
            previewValue = selection.url;
        }

        var mimeValue = typeof selection.mime === 'string'
            ? selection.mime
            : typeof selection.mime_type === 'string'
                ? selection.mime_type
                : '';
        var typeValue = typeof selection.type === 'string' ? selection.type : '';
        var createdValue = typeof selection.dateFormatted === 'string'
            ? selection.dateFormatted
            : typeof selection.created_at === 'string'
                ? selection.created_at
                : typeof selection.date === 'string'
                    ? selection.date
                    : '';

        return normalizeMediaEntry({
            attachment_id: attachmentIdValue,
            title: titleValue,
            filename: filenameValue,
            url: urlValue,
            icon_url: iconValue,
            preview_url: previewValue,
            mime_type: mimeValue,
            type: typeValue,
            created_at: createdValue,
        });
    }

    function TodoApp(props) {
        var datasetConfig = props.datasetConfig || {};
        var runtime = props.runtime || {};

        var preview = !!datasetConfig.preview;
        var ajaxUrl = typeof runtime.ajaxUrl === 'string' ? runtime.ajaxUrl : '';
        var actions = runtime.actions && typeof runtime.actions === 'object' ? runtime.actions : {};
        var fetchAction = typeof actions.fetch === 'string' ? actions.fetch : '';
        var toggleAction = typeof actions.toggle === 'string' ? actions.toggle : '';
        var createAction = typeof actions.create === 'string' ? actions.create : '';
        var updateAction = typeof actions.update === 'string' ? actions.update : '';
        var createProjectAction = typeof actions.create_project === 'string' ? actions.create_project : '';
        var addNoteAction = typeof actions.add_note === 'string' ? actions.add_note : '';
        var deleteNoteAction = typeof actions.delete_note === 'string' ? actions.delete_note : '';
        var attachMediaAction = typeof actions.attach_media === 'string' ? actions.attach_media : '';
        var detachMediaAction = typeof actions.detach_media === 'string' ? actions.detach_media : '';
        var archiveAction = typeof actions.archive === 'string' ? actions.archive : '';
        var fetchArchivedAction = typeof actions.fetch_archived === 'string' ? actions.fetch_archived : '';
        var deleteAction = typeof actions.delete === 'string' ? actions.delete : '';
        var nonce = typeof runtime.nonce === 'string' ? runtime.nonce : '';
        var datasetAccess = datasetConfig.hasAccess === undefined ? undefined : !!datasetConfig.hasAccess;
        var runtimeHasAccess = runtime.hasAccess;
        var hasAccess = runtimeHasAccess === undefined ? (datasetAccess === undefined ? false : datasetAccess) : !!runtimeHasAccess;
        var i18n = runtime.i18n && typeof runtime.i18n === 'object' ? runtime.i18n : {};
        // Charger les constantes de rôles depuis la config PHP
        var roles = runtime.roles && typeof runtime.roles === 'object' ? runtime.roles : {};

        var title = typeof datasetConfig.title === 'string' ? datasetConfig.title : '';
        var intro = typeof datasetConfig.intro === 'string' ? datasetConfig.intro : '';
        var initialShowCompleted = datasetConfig.showCompleted === undefined ? true : !!datasetConfig.showCompleted;

        var previewTodos = normalizeTodosList(toArray(datasetConfig.previewData && datasetConfig.previewData.todos));
        var previewProjects = normalizeProjectsList(toArray(datasetConfig.previewData && datasetConfig.previewData.projects));
        var previewAssignables = normalizeMembersList(toArray(datasetConfig.previewData && datasetConfig.previewData.assignableMembers));
        var previewArchivedTodos = normalizeTodosList(toArray(datasetConfig.previewData && datasetConfig.previewData.archivedTodos));
        if (preview && previewAssignables.length === 0) {
            // Utiliser la constante de rôle depuis la config PHP
            var animateurRole = roles && roles.ANIMATEUR ? roles.ANIMATEUR : 'animateur';
            previewAssignables.push({
                id: '1',
                name: 'Jean Dupont',
                role: animateurRole,
                isSelf: true,
                avatar: {
                    url: '',
                    initials: 'JD',
                    alt: 'Avatar de Jean Dupont',
                },
            });
        }
        if (preview && previewArchivedTodos.length === 0) {
            previewArchivedTodos.push({
                id: 'arch-1',
                title: 'Tâche archivée exemple',
                description: 'Ancienne tâche terminée avec son contexte pour test.',
                status: 'archived',
                projectId: previewProjects.length > 0 ? previewProjects[0].id : '',
                projectTitle: previewProjects.length > 0 ? previewProjects[0].title : 'Archives',
                dueDate: '',
                assignees: previewAssignables.slice(0, 1),
                notes: [],
            });
        }

        var runtimeMemberIdValue = runtime.memberId;
        var numericRuntimeMemberId = 0;
        if (typeof runtimeMemberIdValue === 'number') {
            numericRuntimeMemberId = runtimeMemberIdValue;
        } else if (typeof runtimeMemberIdValue === 'string' && runtimeMemberIdValue !== '') {
            var parsedRuntimeId = parseInt(runtimeMemberIdValue, 10);
            numericRuntimeMemberId = isNaN(parsedRuntimeId) ? 0 : parsedRuntimeId;
        }

        var previewViewerId = 0;
        if (previewAssignables.length > 0) {
            var parsedPreviewViewer = parseInt(previewAssignables[0].id, 10);
            previewViewerId = isNaN(parsedPreviewViewer) ? 0 : parsedPreviewViewer;
        }

        var initialViewerId = numericRuntimeMemberId || previewViewerId || 0;

        var effectiveAccess = preview || hasAccess;

        var isMountedRef = useRef(true);
        useEffect(function () {
            return function () {
                isMountedRef.current = false;
            };
        }, []);

        var titleInputRef = useRef(null);
        var projectInputRef = useRef(null);
        var descriptionInputRef = useRef(null);
        var sortSelectIdRef = useRef('mj-todo-sort-' + Math.random().toString(36).slice(2));
        var assigneesGroupIdRef = useRef('mj-todo-assignees-' + Math.random().toString(36).slice(2));
        var projectNameInputIdRef = useRef('mj-todo-project-name-' + Math.random().toString(36).slice(2));
        var descriptionInputIdRef = useRef('mj-todo-description-' + Math.random().toString(36).slice(2));
        var createModalTitleIdRef = useRef('mj-todo-create-title-' + Math.random().toString(36).slice(2));
        var createModalDescriptionIdRef = useRef('mj-todo-create-desc-' + Math.random().toString(36).slice(2));
        var priorityFieldIdRef = useRef('mj-todo-priority-' + Math.random().toString(36).slice(2));
        var initialCollapseAppliedRef = useRef(false);
        var knownTodoIdsRef = useRef(new Set());
        var mediaFrameRef = useRef(null);
        var createMediaFrameRef = useRef(null);

        var _a = useState(preview ? previewTodos : []), todos = _a[0], setTodos = _a[1];
        var _b = useState(preview ? previewProjects : []), projects = _b[0], setProjects = _b[1];
        var initialStatusFilter = initialShowCompleted ? 'all' : 'todo';
        var _c = useState(initialStatusFilter), statusFilter = _c[0], setStatusFilter = _c[1];
        var _d = useState(preview ? false : true), loading = _d[0], setLoading = _d[1];
        var _e = useState(''), error = _e[0], setError = _e[1];
        var _f = useState(''), formTitle = _f[0], setFormTitle = _f[1];
        var _g = useState(''), formProjectId = _g[0], setFormProjectId = _g[1];
        var _h = useState(''), formDueDate = _h[0], setFormDueDate = _h[1];
        var _h1 = useState(''), formDescription = _h1[0], setFormDescription = _h1[1];
        var defaultPriority = 3;
        var _i = useState(defaultPriority), formPriority = _i[0], setFormPriority = _i[1];
        var _j = useState(false), submitting = _j[0], setSubmitting = _j[1];
        var _k = useState(new Set()), pendingToggles = _k[0], setPendingToggles = _k[1];
        var _l = useState(''), selectedProject = _l[0], setSelectedProject = _l[1];
        var _m = useState('position'), sortMode = _m[0], setSortMode = _m[1];
        var _n = useState(preview ? previewAssignables : []), assignableMembers = _n[0], setAssignableMembers = _n[1];
        var _o = useState(function () {
            var seed = new Set();
            if (numericRuntimeMemberId > 0) {
                seed.add(String(numericRuntimeMemberId));
            } else if (previewAssignables.length > 0) {
                var firstAssignee = previewAssignables[0];
                if (firstAssignee && firstAssignee.id !== undefined && firstAssignee.id !== null && firstAssignee.id !== '') {
                    seed.add(String(firstAssignee.id));
                }
            }
            return seed;
        }), assigneeSelection = _o[0], setAssigneeSelection = _o[1];
        var _p = useState({ id: initialViewerId, name: '', role: '' }), viewer = _p[0], setViewer = _p[1];
        var _q = useState(false), showProjectForm = _q[0], setShowProjectForm = _q[1];
        var _r = useState(''), projectFormTitle = _r[0], setProjectFormTitle = _r[1];
        var _s = useState(false), creatingProject = _s[0], setCreatingProject = _s[1];
        var _t = useState({ text: '', kind: '' }), projectFeedback = _t[0], setProjectFeedback = _t[1];
        var _u = useState(function () {
            return new Map();
        }), noteDrafts = _u[0], setNoteDrafts = _u[1];
        var _v = useState(function () {
            return new Set();
        }), openNoteForms = _v[0], setOpenNoteForms = _v[1];
        var _w = useState(function () {
            return new Set();
        }), pendingNotes = _w[0], setPendingNotes = _w[1];
        var _w1 = useState(function () {
            return new Set();
        }), pendingNoteDeletes = _w1[0], setPendingNoteDeletes = _w1[1];
        var _x = useState(function () {
            return new Map();
        }), noteErrors = _x[0], setNoteErrors = _x[1];
        var _x1 = useState(function () {
            return new Map();
        }), mediaErrors = _x1[0], setMediaErrors = _x1[1];
        var _y1 = useState(function () {
            return new Set();
        }), pendingMediaAdds = _y1[0], setPendingMediaAdds = _y1[1];
        var _z1 = useState(function () {
            return new Set();
        }), pendingMediaRemoves = _z1[0], setPendingMediaRemoves = _z1[1];
        var _aa = useState([]), formMedia = _aa[0], setFormMedia = _aa[1];
        var _ab = useState(''), formMediaError = _ab[0], setFormMediaError = _ab[1];
        var _y = useState(function () {
            return new Set();
        }), pendingArchives = _y[0], setPendingArchives = _y[1];
        var _z = useState(preview ? previewArchivedTodos : []), archivedTodos = _z[0], setArchivedTodos = _z[1];
        var _0 = useState(false), loadingArchives = _0[0], setLoadingArchives = _0[1];
        var _1 = useState(''), archivesError = _1[0], setArchivesError = _1[1];
        var _2 = useState(function () {
            return new Set();
        }), pendingDeletes = _2[0], setPendingDeletes = _2[1];
        var _3 = useState(function () {
            return new Map();
        }), archivedProjectsMap = _3[0], setArchivedProjectsMap = _3[1];
        var _4 = useState(function () {
            return new Set();
        }), editingTodos = _4[0], setEditingTodos = _4[1];
        var _5 = useState(function () {
            return new Map();
        }), editDrafts = _5[0], setEditDrafts = _5[1];
        var _6 = useState(function () {
            return new Map();
        }), editErrors = _6[0], setEditErrors = _6[1];
        var _7 = useState(function () {
            return new Set();
        }), pendingUpdates = _7[0], setPendingUpdates = _7[1];
        var _8 = useState(function () {
            if (preview) {
                var previewKeys = previewTodos.map(function (todo) {
                    if (!todo || todo.id === undefined || todo.id === null) {
                        return '';
                    }
                    return String(todo.id);
                }).filter(function (key) { return key !== ''; });
                if (previewKeys.length > 0) {
                    return new Set(previewKeys);
                }
            }
            return new Set();
        }), collapsedTodos = _8[0], setCollapsedTodos = _8[1];
        var _9 = useState(false), createModalOpen = _9[0], setCreateModalOpen = _9[1];
        var _10 = useState(''), createError = _10[0], setCreateError = _10[1];

        useEffect(function () {
            if (!Array.isArray(todos)) {
                return;
            }
            var keys = todos.map(function (todo) {
                if (!todo || todo.id === undefined || todo.id === null) {
                    return '';
                }
                return String(todo.id);
            }).filter(function (key) { return key !== ''; });
            var keysSet = new Set(keys);
            var previousKnown = knownTodoIdsRef.current instanceof Set ? knownTodoIdsRef.current : new Set();

            setCollapsedTodos(function (previous) {
                var previousSet = previous instanceof Set ? previous : new Set();
                var next = new Set();
                var changed = false;

                previousSet.forEach(function (key) {
                    if (keysSet.has(key)) {
                        next.add(key);
                    } else {
                        changed = true;
                    }
                });

                var shouldCollapseAll = !initialCollapseAppliedRef.current;
                keys.forEach(function (key) {
                    var isNew = !previousKnown.has(key);
                    if (shouldCollapseAll || isNew) {
                        if (!next.has(key)) {
                            next.add(key);
                            changed = true;
                        }
                    }
                });

                if (shouldCollapseAll) {
                    initialCollapseAppliedRef.current = true;
                }

                if (!changed && next.size === previousSet.size) {
                    return previousSet;
                }

                return next;
            });

            knownTodoIdsRef.current = keysSet;
        }, [todos, setCollapsedTodos]);

        var resetCreateForm = useCallback(function () {
            setFormTitle('');
            setFormProjectId('');
            setFormDueDate('');
            setFormDescription('');
            setFormPriority(defaultPriority);
            setFormMedia([]);
            setFormMediaError('');
        }, [defaultPriority, setFormTitle, setFormProjectId, setFormDueDate, setFormDescription, setFormPriority, setFormMedia, setFormMediaError]);

        var handleOpenCreateModal = useCallback(function () {
            if (!effectiveAccess) {
                return;
            }
            setCreateError('');
            setFormMediaError('');
            setCreateModalOpen(true);
        }, [effectiveAccess, setCreateModalOpen, setCreateError, setFormMediaError]);

        var handleCloseCreateModal = useCallback(function () {
            if (submitting) {
                return;
            }
            setCreateModalOpen(false);
            resetCreateForm();
            setCreateError('');
        }, [submitting, resetCreateForm, setCreateModalOpen, setCreateError]);

        useEffect(function () {
            if (showProjectForm && projectInputRef.current && typeof projectInputRef.current.focus === 'function') {
                projectInputRef.current.focus();
            }
        }, [showProjectForm]);

        useEffect(function () {
            if (createModalOpen && titleInputRef.current && typeof titleInputRef.current.focus === 'function') {
                titleInputRef.current.focus();
            }
        }, [createModalOpen]);

        useEffect(function () {
            if (!createModalOpen) {
                return undefined;
            }
            setCreateError('');
            var handleKeyDown = function (event) {
                if (event && event.key === 'Escape') {
                    event.preventDefault();
                    handleCloseCreateModal();
                }
            };
            document.addEventListener('keydown', handleKeyDown);
            return function () {
                document.removeEventListener('keydown', handleKeyDown);
            };
        }, [createModalOpen, handleCloseCreateModal, setCreateError]);

        var markPending = useCallback(function (todoId, shouldAdd) {
            var key = String(todoId);
            setPendingToggles(function (previous) {
                var next = new Set(previous);
                if (shouldAdd) {
                    next.add(key);
                } else {
                    next.delete(key);
                }
                return next;
            });
        }, []);

        var refresh = useCallback(function () {
            if (!isMountedRef.current) {
                return Promise.resolve();
            }

            if (preview) {
                setLoading(false);
                return Promise.resolve();
            }

            if (!hasAccess || !fetchAction || !ajaxUrl) {
                setLoading(false);
                return Promise.resolve();
            }

            setError('');
            setLoading(true);

            var includeCompleted = statusFilter === 'todo' ? '0' : '1';
            var body = encodeForm({
                action: fetchAction,
                nonce: nonce || '',
                include_completed: includeCompleted,
            });

            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!isMountedRef.current) {
                        return;
                    }
                    if (!payload || !payload.success || !payload.data) {
                        throw new Error('api_error');
                    }
                    var data = payload.data;
                    var normalizedTodos = normalizeTodosList(data.todos);
                    var normalizedProjects = normalizeProjectsList(data.projects);
                    var normalizedMembers = normalizeMembersList(data.assignableMembers);

                    var memberPayload = data.member && typeof data.member === 'object' ? data.member : null;
                    var memberIdFromResponse = 0;
                    if (memberPayload && memberPayload.id !== undefined && memberPayload.id !== null && memberPayload.id !== '') {
                        var parsedMemberId = parseInt(memberPayload.id, 10);
                        memberIdFromResponse = isNaN(parsedMemberId) ? 0 : parsedMemberId;
                    }

                    setViewer({
                        id: memberIdFromResponse || numericRuntimeMemberId || initialViewerId || 0,
                        name: memberPayload && typeof memberPayload.name === 'string' ? memberPayload.name : '',
                        role: memberPayload && typeof memberPayload.role === 'string' ? memberPayload.role : '',
                    });

                    setTodos(normalizedTodos);
                    setProjects(normalizedProjects);
                    setAssignableMembers(normalizedMembers);
                    setEditingTodos(function () { return new Set(); });
                    setEditDrafts(function () { return new Map(); });
                    setEditErrors(function () { return new Map(); });
                    setPendingUpdates(function () { return new Set(); });

                    var viewerIdForSelection = memberIdFromResponse || numericRuntimeMemberId || initialViewerId || 0;
                    if (normalizedMembers.length > 0) {
                        setAssigneeSelection(function (previous) {
                            if (previous.size > 0) {
                                return new Set(previous);
                            }
                            var next = new Set();
                            var defaultMember = normalizedMembers.find(function (member) { return member && member.isSelf; });
                            if (!defaultMember && viewerIdForSelection) {
                                defaultMember = normalizedMembers.find(function (member) { return String(member.id) === String(viewerIdForSelection); });
                            }
                            if (!defaultMember) {
                                defaultMember = normalizedMembers[0];
                            }
                            if (defaultMember && defaultMember.id !== undefined && defaultMember.id !== null && defaultMember.id !== '') {
                                next.add(String(defaultMember.id));
                            }
                            return next;
                        });
                    } else {
                        setAssigneeSelection(function (previous) {
                            if (previous.size === 0) {
                                return previous;
                            }
                            return new Set();
                        });
                    }
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setError(getString(i18n, 'loadError', 'Impossible de charger les tâches.'));
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setLoading(false);
                });
        }, [preview, hasAccess, fetchAction, ajaxUrl, nonce, statusFilter, i18n]);

        useEffect(function () {
            refresh();
        }, [refresh]);

        var fetchArchived = useCallback(function () {
            if (!isMountedRef.current) {
                return Promise.resolve();
            }

            if (preview) {
                setLoadingArchives(false);
                return Promise.resolve();
            }

            if (!hasAccess || !fetchArchivedAction || !ajaxUrl) {
                setArchivesError(getString(i18n, 'archivesLoadError', 'Impossible de charger les archives.'));
                return Promise.resolve();
            }

            setLoadingArchives(true);
            setArchivesError('');

            var body = encodeForm({
                action: fetchArchivedAction,
                nonce: nonce || '',
            });

            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!isMountedRef.current) {
                        return;
                    }
                    if (!payload || !payload.success || !payload.data) {
                        throw new Error('api_error');
                    }
                    var data = payload.data;
                    var normalizedTodos = normalizeTodosList(data.todos);
                    var normalizedProjects = normalizeProjectsList(data.projects);

                    setArchivedTodos(normalizedTodos);
                    setArchivedProjectsMap(function () {
                        var map = new Map();
                        normalizedProjects.forEach(function (project) {
                            var projectId = project && project.id !== undefined && project.id !== null ? String(project.id) : '';
                            if (projectId !== '') {
                                map.set(projectId, project);
                            }
                        });
                        return map;
                    });
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setArchivesError(getString(i18n, 'archivesLoadError', 'Impossible de charger les archives.'));
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setLoadingArchives(false);
                });
        }, [preview, hasAccess, fetchArchivedAction, ajaxUrl, nonce, i18n, setArchivedTodos, setArchivedProjectsMap, setArchivesError, setLoadingArchives]);

        var projectsMap = useMemo(function () {
            var map = new Map();
            projects.forEach(function (project) {
                var key = project && project.id !== undefined && project.id !== null ? String(project.id) : '';
                if (key !== '') {
                    map.set(key, {
                        id: project && project.id !== undefined ? project.id : key,
                        title: project && typeof project.title === 'string' ? project.title : '',
                        color: project && typeof project.color === 'string' ? project.color : '',
                    });
                }
            });
            return map;
        }, [projects]);

        var visibleTodos = useMemo(function () {
            var list = Array.isArray(todos) ? todos.slice() : [];

            if (statusFilter === 'todo') {
                list = list.filter(function (todo) {
                    var status = typeof todo.status === 'string' ? todo.status : '';
                    return status === 'open';
                });
            } else if (statusFilter === 'completed') {
                list = list.filter(function (todo) {
                    var status = typeof todo.status === 'string' ? todo.status : '';
                    return status === 'completed';
                });
            } else {
                list = list.filter(function (todo) {
                    var status = typeof todo.status === 'string' ? todo.status : '';
                    return status !== 'archived';
                });
            }

            if (selectedProject) {
                list = list.filter(function (todo) {
                    var projectValue = todo && todo.projectId !== undefined && todo.projectId !== null ? String(todo.projectId) : '';
                    return projectValue === String(selectedProject);
                });
            }

            if (sortMode === 'project') {
                list = list.slice().sort(function (a, b) {
                    var projectKeyA = a && a.projectId !== undefined && a.projectId !== null ? String(a.projectId) : '';
                    var projectKeyB = b && b.projectId !== undefined && b.projectId !== null ? String(b.projectId) : '';
                    var entryA = projectKeyA !== '' && projectsMap.has(projectKeyA) ? projectsMap.get(projectKeyA) : null;
                    var entryB = projectKeyB !== '' && projectsMap.has(projectKeyB) ? projectsMap.get(projectKeyB) : null;
                    var titleA = entryA && typeof entryA.title === 'string' ? entryA.title.toLowerCase() : '';
                    var titleB = entryB && typeof entryB.title === 'string' ? entryB.title.toLowerCase() : '';
                    if (titleA !== titleB) {
                        return titleA.localeCompare(titleB);
                    }
                    var priorityA = getPriorityValue(a);
                    var priorityB = getPriorityValue(b);
                    if (priorityA !== priorityB) {
                        return priorityB - priorityA;
                    }
                    var dueA = a && typeof a.dueDate === 'string' ? a.dueDate : '';
                    var dueB = b && typeof b.dueDate === 'string' ? b.dueDate : '';
                    if (dueA !== dueB) {
                        return dueA.localeCompare(dueB);
                    }
                    return String(a && a.id !== undefined ? a.id : '').localeCompare(String(b && b.id !== undefined ? b.id : ''));
                });
            } else {
                list = list.slice().sort(compareByPriority);
            }

            return list;
        }, [todos, statusFilter, selectedProject, sortMode, projectsMap]);

        var visibleArchivedTodos = useMemo(function () {
            var list = Array.isArray(archivedTodos) ? archivedTodos.slice() : [];

            if (selectedProject) {
                list = list.filter(function (todo) {
                    var projectValue = todo && todo.projectId !== undefined && todo.projectId !== null ? String(todo.projectId) : '';
                    return projectValue === String(selectedProject);
                });
            }

            if (sortMode === 'project') {
                list = list.slice().sort(function (a, b) {
                    var projectKeyA = a && a.projectId !== undefined && a.projectId !== null ? String(a.projectId) : '';
                    var projectKeyB = b && b.projectId !== undefined && b.projectId !== null ? String(b.projectId) : '';
                    var entryA = projectKeyA !== '' && archivedProjectsMap.has(projectKeyA) ? archivedProjectsMap.get(projectKeyA) : null;
                    var entryB = projectKeyB !== '' && archivedProjectsMap.has(projectKeyB) ? archivedProjectsMap.get(projectKeyB) : null;
                    var titleA = entryA && typeof entryA.title === 'string' ? entryA.title.toLowerCase() : '';
                    var titleB = entryB && typeof entryB.title === 'string' ? entryB.title.toLowerCase() : '';
                    if (titleA !== titleB) {
                        return titleA.localeCompare(titleB);
                    }
                    var priorityA = getPriorityValue(a);
                    var priorityB = getPriorityValue(b);
                    if (priorityA !== priorityB) {
                        return priorityB - priorityA;
                    }
                    var dueA = a && typeof a.dueDate === 'string' ? a.dueDate : '';
                    var dueB = b && typeof b.dueDate === 'string' ? b.dueDate : '';
                    if (dueA !== dueB) {
                        return dueA.localeCompare(dueB);
                    }
                    return String(a && a.id !== undefined ? a.id : '').localeCompare(String(b && b.id !== undefined ? b.id : ''));
                });
            } else {
                list = list.slice().sort(compareByPriority);
            }

            return list;
        }, [archivedTodos, selectedProject, sortMode, archivedProjectsMap]);

        var handleAssigneeToggle = useCallback(function (memberId, checked) {
            var key = String(memberId);
            setAssigneeSelection(function (previous) {
                var next = new Set(previous);
                if (checked) {
                    next.add(key);
                } else {
                    next.delete(key);
                }
                return next;
            });
        }, []);

        var handleStartEdit = useCallback(function (todo) {
            if (!todo || todo.id === undefined || todo.id === null) {
                return;
            }
            var key = String(todo.id);
            setEditingTodos(function (previous) {
                if (previous.has(key)) {
                    return previous;
                }
                var next = new Set(previous);
                next.add(key);
                return next;
            });
            setCollapsedTodos(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Set(previous);
                next.delete(key);
                return next;
            });
            setEditDrafts(function (previous) {
                var next = new Map(previous);
                var titleValue = typeof todo.title === 'string' ? todo.title : '';
                var descriptionValue = typeof todo.description === 'string' ? todo.description : '';
                next.set(key, {
                    title: titleValue,
                    description: descriptionValue,
                });
                return next;
            });
            setEditErrors(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });
            setOpenNoteForms(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Set(previous);
                next.delete(key);
                return next;
            });
            setNoteDrafts(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });
        }, []);

        var handleCancelEdit = useCallback(function (todoId) {
            var key = String(todoId);
            setEditingTodos(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Set(previous);
                next.delete(key);
                return next;
            });
            setEditDrafts(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });
            setEditErrors(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });
            setPendingUpdates(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Set(previous);
                next.delete(key);
                return next;
            });
        }, []);

        var handleEditDraftChange = useCallback(function (todoId, field, value) {
            var key = String(todoId);
            var nextValue = value === undefined || value === null ? '' : String(value);
            setEditDrafts(function (previous) {
                var next = new Map(previous);
                var draft = next.get(key) || {
                    title: '',
                    description: '',
                };
                if (field === 'title') {
                    draft = Object.assign({}, draft, { title: nextValue });
                } else if (field === 'description') {
                    draft = Object.assign({}, draft, { description: nextValue });
                }
                next.set(key, draft);
                return next;
            });
        }, []);

        var handleSubmitEdit = useCallback(function (todoId) {
            var key = String(todoId);
            var draft = editDrafts.has(key) ? editDrafts.get(key) : null;
            var draftTitle = draft && typeof draft.title === 'string' ? draft.title.trim() : '';
            var draftDescription = draft && typeof draft.description === 'string' ? draft.description : '';

            if (draftTitle === '') {
                setEditErrors(function (previous) {
                    var next = new Map(previous);
                    next.set(key, getString(i18n, 'formError', 'Merci de saisir un titre.'));
                    return next;
                });
                return;
            }

            setEditErrors(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });

            if (preview) {
                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === key) {
                            return Object.assign({}, todo, {
                                title: draftTitle,
                                description: draftDescription,
                            });
                        }
                        return todo;
                    });
                });
                setArchivedTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === key) {
                            return Object.assign({}, todo, {
                                title: draftTitle,
                                description: draftDescription,
                            });
                        }
                        return todo;
                    });
                });
                handleCancelEdit(todoId);
                return;
            }

            if (!effectiveAccess || !updateAction || !ajaxUrl) {
                setEditErrors(function (previous) {
                    var next = new Map(previous);
                    next.set(key, getString(i18n, 'updateError', 'Impossible de mettre à jour la tâche.'));
                    return next;
                });
                return;
            }

            setPendingUpdates(function (previous) {
                var next = new Set(previous);
                next.add(key);
                return next;
            });

            var body = encodeForm({
                action: updateAction,
                nonce: nonce || '',
                todo_id: String(todoId),
                title: draftTitle,
                description: draftDescription,
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.todo) {
                        throw new Error('api_error');
                    }
                    var normalized = normalizeTodosList([payload.data.todo]);
                    if (!normalized.length) {
                        throw new Error('api_error');
                    }
                    var updatedTodo = normalized[0];
                    setTodos(function (previous) {
                        return previous.map(function (todo) {
                            return String(todo.id) === key ? updatedTodo : todo;
                        });
                    });
                    setArchivedTodos(function (previous) {
                        return previous.map(function (todo) {
                            return String(todo.id) === key ? updatedTodo : todo;
                        });
                    });
                    handleCancelEdit(todoId);
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setEditErrors(function (previous) {
                        var next = new Map(previous);
                        next.set(key, getString(i18n, 'updateError', 'Impossible de mettre à jour la tâche.'));
                        return next;
                    });
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setPendingUpdates(function (previous) {
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });
                });
        }, [editDrafts, preview, setTodos, setArchivedTodos, handleCancelEdit, effectiveAccess, updateAction, ajaxUrl, nonce, setEditErrors, i18n, setPendingUpdates]);

        var handleToggleCollapse = useCallback(function (todoId) {
            var key = String(todoId);
            setCollapsedTodos(function (previous) {
                var next = new Set(previous);
                if (next.has(key)) {
                    next.delete(key);
                } else {
                    next.add(key);
                }
                return next;
            });
            setOpenNoteForms(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Set(previous);
                next.delete(key);
                return next;
            });
            setNoteDrafts(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });
            setNoteErrors(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });
        }, [setCollapsedTodos, setOpenNoteForms, setNoteDrafts, setNoteErrors]);

        var handlePriorityChange = useCallback(function (todoId, nextPriority) {
            var key = String(todoId);
            var normalizedPriority = clampPriorityValue(nextPriority);

            if (!effectiveAccess) {
                return;
            }

            if (preview) {
                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === key) {
                            return Object.assign({}, todo, { priority: normalizedPriority });
                        }
                        return todo;
                    });
                });
                return;
            }

            if (!updateAction || !ajaxUrl) {
                setError(getString(i18n, 'priorityUpdateError', 'Impossible de mettre à jour la priorité.'));
                return;
            }

            var currentTodo = null;
            for (var index = 0; index < todos.length; index += 1) {
                var candidate = todos[index];
                if (candidate && String(candidate.id) === key) {
                    currentTodo = candidate;
                    break;
                }
            }
            if (!currentTodo) {
                return;
            }
            var currentPriority = getPriorityValue(currentTodo);
            if (currentPriority === normalizedPriority) {
                return;
            }

            var previousPriority = null;
            setTodos(function (previous) {
                return previous.map(function (todo) {
                    if (String(todo.id) === key) {
                        if (previousPriority === null) {
                            previousPriority = getPriorityValue(todo);
                        }
                        return Object.assign({}, todo, { priority: normalizedPriority });
                    }
                    return todo;
                });
            });

            setPendingUpdates(function (previous) {
                var next = new Set(previous);
                next.add(key);
                return next;
            });

            var body = encodeForm({
                action: updateAction,
                nonce: nonce || '',
                todo_id: String(todoId),
                priority: String(normalizedPriority),
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.todo) {
                        throw new Error('api_error');
                    }
                    var normalized = normalizeTodosList([payload.data.todo]);
                    if (!normalized.length) {
                        throw new Error('api_error');
                    }
                    var updatedTodo = normalized[0];
                    setTodos(function (previous) {
                        return previous.map(function (todo) {
                            return String(todo.id) === key ? updatedTodo : todo;
                        });
                    });
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    if (previousPriority !== null) {
                        setTodos(function (previous) {
                            return previous.map(function (todo) {
                                if (String(todo.id) === key) {
                                    return Object.assign({}, todo, { priority: previousPriority });
                                }
                                return todo;
                            });
                        });
                    }
                    setError(getString(i18n, 'priorityUpdateError', 'Impossible de mettre à jour la priorité.'));
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setPendingUpdates(function (previous) {
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });
                });
        }, [effectiveAccess, preview, updateAction, ajaxUrl, nonce, i18n, setTodos, setPendingUpdates, setError, todos]);

        var handleProjectTabSelect = useCallback(function (value) {
            var nextValue = value === undefined || value === null ? '' : String(value);
            setSelectedProject(nextValue);
        }, []);

        var handleSortChange = useCallback(function (event) {
            var value = event && event.target ? event.target.value : 'position';
            setSortMode(value === 'project' ? 'project' : 'position');
        }, []);

        var handleStatusFilterSelect = useCallback(function (value) {
            var normalized = value;
            if (normalized !== 'todo' && normalized !== 'completed' && normalized !== 'archived' && normalized !== 'all') {
                normalized = 'all';
            }

            setStatusFilter(function (previous) {
                if (previous === normalized) {
                    return previous;
                }
                return normalized;
            });

            if (normalized === 'archived') {
                fetchArchived();
            } else {
                setArchivesError('');
            }
        }, [fetchArchived, setArchivesError]);

        var handleToggle = useCallback(function (todoId, complete) {
            var toggleKey = String(todoId);
            if (!effectiveAccess) {
                return;
            }

            if (preview) {
                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === toggleKey) {
                            return Object.assign({}, todo, {
                                status: complete ? 'completed' : 'open',
                            });
                        }
                        return todo;
                    });
                });
                return;
            }

            if (!toggleAction || !ajaxUrl || pendingToggles.has(toggleKey)) {
                return;
            }

            var previousStatus = null;
            setTodos(function (previous) {
                return previous.map(function (todo) {
                    if (String(todo.id) === toggleKey) {
                        previousStatus = typeof todo.status === 'string' ? todo.status : null;
                        return Object.assign({}, todo, {
                            status: complete ? 'completed' : 'open',
                        });
                    }
                    return todo;
                });
            });

            markPending(todoId, true);
            setError('');

            var body = encodeForm({
                action: toggleAction,
                nonce: nonce || '',
                todo_id: String(todoId),
                complete: complete ? '1' : '0',
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
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
                    return refresh();
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setError(getString(i18n, 'toggleError', 'Impossible de mettre à jour la tâche.'));
                    if (previousStatus !== null) {
                        setTodos(function (previous) {
                            return previous.map(function (todo) {
                                if (String(todo.id) === toggleKey) {
                                    return Object.assign({}, todo, { status: previousStatus });
                                }
                                return todo;
                            });
                        });
                    }
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    markPending(todoId, false);
                });
        }, [effectiveAccess, preview, toggleAction, ajaxUrl, pendingToggles, markPending, nonce, refresh, i18n]);

        var handleCreate = useCallback(function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (!effectiveAccess) {
                return;
            }

            setError('');
            setCreateError('');

            var trimmedTitle = formTitle.trim();
            if (trimmedTitle === '') {
                setCreateError(getString(i18n, 'formError', 'Merci de saisir un titre.'));
                if (titleInputRef.current && typeof titleInputRef.current.focus === 'function') {
                    titleInputRef.current.focus();
                }
                return;
            }

            var projectValue = formProjectId ? String(formProjectId) : '';
            var dueValue = formDueDate ? String(formDueDate) : '';
            var rawDescription = typeof formDescription === 'string' ? formDescription : '';
            var normalizedDescription = rawDescription.replace(/\r\n/g, '\n');
            var hasDescription = normalizedDescription.trim() !== '';
            var selectedAssigneeIds = Array.from(assigneeSelection);
            var priorityValue = clampPriorityValue(formPriority);
            var attachmentIds = formMedia
                .map(function (entry) {
                    if (!entry) {
                        return '';
                    }
                    if (entry.attachmentId !== undefined && entry.attachmentId !== null && entry.attachmentId !== '') {
                        return String(entry.attachmentId);
                    }
                    if (entry.id !== undefined && entry.id !== null && entry.id !== '') {
                        return String(entry.id);
                    }
                    return '';
                })
                .filter(function (value, index, array) {
                    return value !== '' && array.indexOf(value) === index;
                });

            if (assignableMembers.length === 0) {
                setCreateError(getString(i18n, 'assigneesEmpty', 'Aucun membre disponible.'));
                return;
            }

            if (selectedAssigneeIds.length === 0) {
                setCreateError(getString(i18n, 'assigneesRequired', 'Merci de sélectionner au moins une personne.'));
                return;
            }

            if (preview) {
                var previewId = Date.now();
                var projectLabel_1 = '';
                if (projectValue !== '') {
                    projects.forEach(function (project) {
                        if (String(project.id) === projectValue && typeof project.title === 'string') {
                            projectLabel_1 = project.title;
                        }
                    });
                }

                var selectedAssignees = assignableMembers.filter(function (member) {
                    return selectedAssigneeIds.indexOf(String(member.id)) !== -1;
                }).map(function (member) {
                    return {
                        id: member && member.id !== undefined ? member.id : '',
                        name: member && typeof member.name === 'string' ? member.name : '',
                        role: member && typeof member.role === 'string' ? member.role : '',
                        isSelf: !!(member && member.isSelf),
                    };
                });

                var previewTodo = {
                    id: previewId,
                    title: trimmedTitle,
                    description: hasDescription ? normalizedDescription : '',
                    status: 'open',
                    projectId: projectValue,
                    projectTitle: projectLabel_1,
                    dueDate: dueValue,
                    completedAt: '',
                    assignees: selectedAssignees,
                    priority: priorityValue,
                    media: formMedia.map(function (entry) {
                        return entry ? Object.assign({}, entry) : entry;
                    }),
                };

                setTodos(function (previous) {
                    var next = [previewTodo].concat(previous);
                    return next.sort(compareByPriority);
                });
                resetCreateForm();
                setCreateModalOpen(false);
                setCreateError('');
                return;
            }

            if (!createAction || !ajaxUrl) {
                return;
            }

            setSubmitting(true);
            setFormMediaError('');

            var body = encodeForm({
                action: createAction,
                nonce: nonce || '',
                title: trimmedTitle,
                project_id: projectValue,
                due_date: dueValue,
                description: normalizedDescription,
                priority: String(priorityValue),
                'assigned_member_ids[]': selectedAssigneeIds,
                 'attachment_ids[]': attachmentIds,
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
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
                    resetCreateForm();
                    setCreateModalOpen(false);
                    setCreateError('');
                    return refresh();
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setCreateError(getString(i18n, 'createError', 'Impossible d’ajouter la tâche.'));
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setSubmitting(false);
                });
        }, [effectiveAccess, preview, createAction, ajaxUrl, nonce, formTitle, formProjectId, formDueDate, formDescription, formPriority, formMedia, refresh, i18n, projects, assignableMembers, assigneeSelection, resetCreateForm, setCreateModalOpen, setCreateError, setFormMediaError]);

        var toggleProjectForm = useCallback(function () {
            setProjectFeedback({ text: '', kind: '' });
            setShowProjectForm(function (previous) {
                var next = !previous;
                if (!next) {
                    setProjectFormTitle('');
                }
                return next;
            });
        }, []);

        var handleProjectFormSubmit = useCallback(function (event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (!effectiveAccess) {
                return;
            }

            var trimmed = projectFormTitle.trim();
            if (trimmed === '') {
                setProjectFeedback({
                    text: getString(i18n, 'projectCreatePlaceholder', 'Nom du dossier'),
                    kind: 'error',
                });
                if (projectInputRef.current && typeof projectInputRef.current.focus === 'function') {
                    projectInputRef.current.focus();
                }
                return;
            }

            if (preview) {
                var previewProjectId = Date.now();
                var previewProject = { id: previewProjectId, title: trimmed, color: '' };
                setProjects(function (previous) {
                    return previous.concat([previewProject]);
                });
                setProjectFormTitle('');
                setSelectedProject(String(previewProjectId));
                setProjectFeedback({
                    text: getString(i18n, 'projectCreateSuccess', 'Dossier créé.'),
                    kind: 'success',
                });
                setShowProjectForm(false);
                return;
            }

            if (!createProjectAction || !ajaxUrl) {
                return;
            }

            setCreatingProject(true);
            setProjectFeedback({ text: '', kind: '' });

            var body = encodeForm({
                action: createProjectAction,
                nonce: nonce || '',
                title: trimmed,
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.project) {
                        throw new Error('api_error');
                    }

                    var normalized = normalizeProjectsList([payload.data.project]);
                    var createdProject = normalized[0];
                    if (!createdProject) {
                        throw new Error('api_error');
                    }

                    setProjects(function (previous) {
                        var next = previous.slice();
                        var createdId = createdProject.id !== undefined && createdProject.id !== null ? String(createdProject.id) : '';
                        var existingIndex = next.findIndex(function (project) {
                            return String(project.id) === createdId;
                        });
                        if (existingIndex >= 0) {
                            next[existingIndex] = createdProject;
                        } else {
                            next.push(createdProject);
                        }
                        next.sort(function (a, b) {
                            var titleA = a && typeof a.title === 'string' ? a.title.toLowerCase() : '';
                            var titleB = b && typeof b.title === 'string' ? b.title.toLowerCase() : '';
                            return titleA.localeCompare(titleB);
                        });
                        return next;
                    });

                    setProjectFormTitle('');
                    setSelectedProject(createdProject && createdProject.id !== undefined && createdProject.id !== null ? String(createdProject.id) : '');
                    setProjectFeedback({
                        text: getString(i18n, 'projectCreateSuccess', 'Dossier créé.'),
                        kind: 'success',
                    });
                    setShowProjectForm(false);
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setProjectFeedback({
                        text: getString(i18n, 'projectCreateError', 'Impossible de créer le dossier.'),
                        kind: 'error',
                    });
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setCreatingProject(false);
                });
        }, [effectiveAccess, projectFormTitle, preview, createProjectAction, ajaxUrl, nonce, i18n]);

        var toggleNoteForm = useCallback(function (todoId) {
            var key = String(todoId);
            setOpenNoteForms(function (previous) {
                var next = new Set(previous);
                if (next.has(key)) {
                    next.delete(key);
                } else {
                    next.add(key);
                }
                return next;
            });
            setNoteErrors(function (previous) {
                if (!previous.has(key)) {
                    return previous;
                }
                var next = new Map(previous);
                next.delete(key);
                return next;
            });
        }, []);

        var handleNoteDraftChange = useCallback(function (todoId, value) {
            var key = String(todoId);
            setNoteDrafts(function (previous) {
                var next = new Map(previous);
                next.set(key, value);
                return next;
            });
        }, []);

        var setNoteErrorMessage = useCallback(function (todoId, message) {
            var key = String(todoId);
            setNoteErrors(function (previous) {
                var next = new Map(previous);
                if (message) {
                    next.set(key, message);
                } else {
                    next.delete(key);
                }
                return next;
            });
        }, []);

        var setMediaErrorMessage = useCallback(function (todoId, message) {
            var key = String(todoId);
            setMediaErrors(function (previous) {
                var next = new Map(previous);
                if (message) {
                    next.set(key, message);
                } else {
                    next.delete(key);
                }
                return next;
            });
        }, []);

        var handleCreateAttachMedia = useCallback(function (selectionList) {
            if (!Array.isArray(selectionList) || selectionList.length === 0) {
                return;
            }

            var normalized = selectionList.map(normalizeMediaSelectionEntry).filter(Boolean);
            if (normalized.length === 0) {
                setFormMediaError(getString(i18n, 'formMediaError', 'Impossible d’attacher le document.'));
                return;
            }

            setFormMedia(function (previous) {
                var registry = new Map();
                previous.forEach(function (entry) {
                    if (!entry) {
                        return;
                    }
                    var attachmentKey = entry.attachmentId !== undefined && entry.attachmentId !== null
                        ? String(entry.attachmentId)
                        : entry.id !== undefined && entry.id !== null
                            ? String(entry.id)
                            : '';
                    if (attachmentKey === '') {
                        return;
                    }
                    registry.set(attachmentKey, entry);
                });
                normalized.forEach(function (entry) {
                    if (!entry) {
                        return;
                    }
                    var attachmentKey = entry.attachmentId !== undefined && entry.attachmentId !== null
                        ? String(entry.attachmentId)
                        : entry.id !== undefined && entry.id !== null
                            ? String(entry.id)
                            : '';
                    if (attachmentKey === '') {
                        return;
                    }
                    registry.set(attachmentKey, entry);
                });
                return Array.from(registry.values());
            });
            setFormMediaError('');
        }, [setFormMedia, setFormMediaError, i18n]);

        var handleCreateRemoveMedia = useCallback(function (attachmentId) {
            var attachmentKey = attachmentId !== undefined && attachmentId !== null ? String(attachmentId) : '';
            if (attachmentKey === '') {
                return;
            }
            setFormMedia(function (previous) {
                return previous.filter(function (entry) {
                    if (!entry) {
                        return false;
                    }
                    var entryKey = entry.attachmentId !== undefined && entry.attachmentId !== null
                        ? String(entry.attachmentId)
                        : entry.id !== undefined && entry.id !== null
                            ? String(entry.id)
                            : '';
                    if (entryKey === '') {
                        return true;
                    }
                    return entryKey !== attachmentKey;
                });
            });
            setFormMediaError('');
        }, [setFormMedia, setFormMediaError]);

        var handleCreateOpenMediaLibrary = useCallback(function () {
            if (preview) {
                handleCreateAttachMedia([
                    {
                        attachment_id: Date.now(),
                        title: 'Document de démonstration.pdf',
                        filename: 'document-demo.pdf',
                        url: '#',
                        mime_type: 'application/pdf',
                        type: 'application',
                    },
                ]);
                return;
            }

            if (!effectiveAccess) {
                return;
            }

            if (typeof window === 'undefined' || typeof window.wp === 'undefined' || !window.wp.media) {
                setFormMediaError(getString(i18n, 'mediaUnavailable', 'La médiathèque est indisponible.'));
                return;
            }

            var frame = createMediaFrameRef.current;
            if (!frame) {
                var buttonLabel = getString(i18n, 'formMediaAdd', 'Lier un document');
                frame = window.wp.media({
                    title: buttonLabel,
                    button: {
                        text: buttonLabel,
                    },
                    multiple: true,
                });
                frame.on('select', function () {
                    var selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }
                    var selected = [];
                    selection.each(function (model) {
                        if (!model) {
                            return;
                        }
                        if (typeof model.toJSON === 'function') {
                            selected.push(model.toJSON());
                        } else {
                            selected.push(model);
                        }
                    });
                    handleCreateAttachMedia(selected);
                });
                createMediaFrameRef.current = frame;
            }

            frame.open();
        }, [preview, effectiveAccess, i18n, handleCreateAttachMedia, setFormMediaError, createMediaFrameRef]);

        var handleSubmitNote = useCallback(function (todoId) {
            var key = String(todoId);
            var draftValue = noteDrafts.has(key) ? String(noteDrafts.get(key)) : '';
            var trimmed = draftValue.trim();

            if (trimmed === '') {
                setNoteErrorMessage(todoId, getString(i18n, 'noteRequired', 'Merci de saisir une note.'));
                return;
            }

            setNoteErrorMessage(todoId, '');

            if (preview) {
                var previewNote = normalizeNoteEntry({
                    id: 'preview-' + Date.now(),
                    todoId: todoId,
                    memberId: viewer && viewer.id !== undefined ? viewer.id : '',
                    authorName: viewer && viewer.name ? viewer.name : getString(i18n, 'assigneeYou', 'Moi'),
                    content: trimmed,
                    createdAt: new Date().toISOString().replace('T', ' ').slice(0, 19),
                });
                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === key) {
                            var nextNotes = Array.isArray(todo.notes) ? todo.notes.slice() : [];
                            nextNotes.push(previewNote);
                            return Object.assign({}, todo, { notes: nextNotes });
                        }
                        return todo;
                    });
                });
                setNoteDrafts(function (previous) {
                    var next = new Map(previous);
                    next.set(key, '');
                    return next;
                });
                return;
            }

            if (!effectiveAccess || !addNoteAction || !ajaxUrl) {
                return;
            }

            setPendingNotes(function (previous) {
                var next = new Set(previous);
                next.add(key);
                return next;
            });

            var body = encodeForm({
                action: addNoteAction,
                nonce: nonce || '',
                todo_id: String(todoId),
                content: trimmed,
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.note) {
                        throw new Error('api_error');
                    }
                    var note = normalizeNoteEntry(payload.data.note);
                    setTodos(function (previous) {
                        return previous.map(function (todo) {
                            if (String(todo.id) === key) {
                                var nextNotes = Array.isArray(todo.notes) ? todo.notes.slice() : [];
                                nextNotes.push(note);
                                return Object.assign({}, todo, { notes: nextNotes });
                            }
                            return todo;
                        });
                    });
                    setNoteDrafts(function (previous) {
                        var next = new Map(previous);
                        next.set(key, '');
                        return next;
                    });
                    setNoteErrorMessage(todoId, '');
                })
                .catch(function () {
                    setNoteErrorMessage(todoId, getString(i18n, 'noteCreateError', 'Impossible d’enregistrer la note.'));
                })
                .finally(function () {
                    setPendingNotes(function (previous) {
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });
                });
        }, [noteDrafts, preview, viewer, effectiveAccess, addNoteAction, ajaxUrl, nonce, i18n, setTodos, setNoteErrorMessage, setPendingNotes]);

        var handleDeleteNote = useCallback(function (todoId, noteId) {
            var todoKey = String(todoId);
            var noteKey = todoKey + ':' + String(noteId);

            setNoteErrorMessage(todoId, '');

            if (preview) {
                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === todoKey) {
                            var filteredNotes = Array.isArray(todo.notes)
                                ? todo.notes.filter(function (entry) {
                                    var entryId = entry && entry.id !== undefined && entry.id !== null ? String(entry.id) : '';
                                    return entryId !== String(noteId);
                                })
                                : [];
                            return Object.assign({}, todo, { notes: filteredNotes });
                        }
                        return todo;
                    });
                });
                return;
            }

            if (!effectiveAccess || !deleteNoteAction || !ajaxUrl) {
                return;
            }

            setPendingNoteDeletes(function (previous) {
                var next = new Set(previous);
                next.add(noteKey);
                return next;
            });

            var body = encodeForm({
                action: deleteNoteAction,
                nonce: nonce || '',
                todo_id: String(todoId),
                note_id: String(noteId),
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
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
                    setTodos(function (previous) {
                        return previous.map(function (todo) {
                            if (String(todo.id) === todoKey) {
                                var filteredNotes = Array.isArray(todo.notes)
                                    ? todo.notes.filter(function (entry) {
                                        var entryId = entry && entry.id !== undefined && entry.id !== null ? String(entry.id) : '';
                                        return entryId !== String(noteId);
                                    })
                                    : [];
                                return Object.assign({}, todo, { notes: filteredNotes });
                            }
                            return todo;
                        });
                    });
                    setNoteErrorMessage(todoId, '');
                })
                .catch(function () {
                    setNoteErrorMessage(todoId, getString(i18n, 'noteDeleteError', 'Impossible de supprimer la note.'));
                })
                .finally(function () {
                    setPendingNoteDeletes(function (previous) {
                        var next = new Set(previous);
                        next.delete(noteKey);
                        return next;
                    });
                });
        }, [preview, effectiveAccess, deleteNoteAction, ajaxUrl, nonce, setTodos, setNoteErrorMessage, i18n, setPendingNoteDeletes]);

        var handleAttachMedia = useCallback(function (todoId, attachments) {
            var key = String(todoId);

            if (!Array.isArray(attachments) || attachments.length === 0) {
                return;
            }

            var attachmentIds = [];
            attachments.forEach(function (attachment) {
                if (!attachment) {
                    return;
                }
                var sourceId = attachment.id !== undefined && attachment.id !== null
                    ? attachment.id
                    : attachment.ID !== undefined && attachment.ID !== null
                        ? attachment.ID
                        : attachment.attachment_id !== undefined && attachment.attachment_id !== null
                            ? attachment.attachment_id
                            : '';
                if (sourceId === '' && attachment.get && typeof attachment.get === 'function') {
                    sourceId = attachment.get('id');
                }
                var parsed = typeof sourceId === 'number' ? sourceId : parseInt(sourceId, 10);
                if (!isNaN(parsed) && parsed > 0) {
                    attachmentIds.push(String(parsed));
                }
            });

            if (attachmentIds.length === 0) {
                setMediaErrorMessage(todoId, getString(i18n, 'mediaAddError', 'Impossible d’ajouter le média.'));
                return;
            }

            if (preview) {
                var now = new Date();
                var iso = now.toISOString().replace('T', ' ').slice(0, 19);
                var previewEntries = attachmentIds.map(function (attachmentId, index) {
                    var attachment = attachments[index] || {};
                    var title = typeof attachment.title === 'string' ? attachment.title : typeof attachment.filename === 'string' ? attachment.filename : 'Media ' + attachmentId;
                    var filename = typeof attachment.filename === 'string' ? attachment.filename : title;
                    var mimeType = typeof attachment.mime === 'string' ? attachment.mime : typeof attachment.mimeType === 'string' ? attachment.mimeType : '';
                    var typeValue = mimeType && mimeType.indexOf('/') !== -1 ? mimeType.split('/')[0] : (attachment.type || '');
                    var urlValue = typeof attachment.url === 'string' ? attachment.url : '';
                    return {
                        id: 'preview-media-' + attachmentId + '-' + now.getTime(),
                        todoId: todoId,
                        attachmentId: attachmentId,
                        title: title,
                        filename: filename,
                        url: urlValue,
                        previewUrl: urlValue,
                        iconUrl: typeof attachment.icon === 'string' ? attachment.icon : '',
                        mimeType: mimeType,
                        type: typeValue,
                        addedAt: iso,
                        addedBy: viewer && viewer.id !== undefined ? viewer.id : '',
                        addedByUser: '',
                    };
                });

                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === key) {
                            var nextMedia = Array.isArray(todo.media) ? todo.media.slice() : [];
                            Array.prototype.push.apply(nextMedia, previewEntries);
                            return Object.assign({}, todo, { media: nextMedia });
                        }
                        return todo;
                    });
                });
                setMediaErrorMessage(todoId, '');
                return;
            }

            if (!effectiveAccess || !attachMediaAction || !ajaxUrl) {
                setMediaErrorMessage(todoId, getString(i18n, 'mediaAddError', 'Impossible d’ajouter le média.'));
                return;
            }

            setMediaErrorMessage(todoId, '');
            setPendingMediaAdds(function (previous) {
                var next = new Set(previous);
                next.add(key);
                return next;
            });

            var body = encodeForm({
                action: attachMediaAction,
                nonce: nonce || '',
                todo_id: String(todoId),
                'attachment_ids[]': attachmentIds,
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.todo) {
                        throw new Error('api_error');
                    }
                    var normalized = normalizeTodosList([payload.data.todo]);
                    if (!normalized.length) {
                        throw new Error('api_error');
                    }
                    var updatedTodo = normalized[0];
                    setTodos(function (previous) {
                        return previous.map(function (todo) {
                            return String(todo.id) === key ? updatedTodo : todo;
                        });
                    });
                    setArchivedTodos(function (previous) {
                        return previous.map(function (todo) {
                            return String(todo.id) === key ? updatedTodo : todo;
                        });
                    });
                })
                .catch(function () {
                    setMediaErrorMessage(todoId, getString(i18n, 'mediaAddError', 'Impossible d’ajouter le média.'));
                })
                .finally(function () {
                    setPendingMediaAdds(function (previous) {
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });
                });
        }, [viewer, preview, effectiveAccess, attachMediaAction, ajaxUrl, nonce, i18n, setTodos, setArchivedTodos, setMediaErrorMessage, setPendingMediaAdds]);

        var handleOpenMediaLibrary = useCallback(function (todoId) {
            if (preview) {
                handleAttachMedia(todoId, [
                    {
                        id: Date.now(),
                        title: 'Media',
                        filename: 'media-demo.jpg',
                        url: '',
                        mime: 'image/jpeg',
                        type: 'image',
                    },
                ]);
                return;
            }

            if (!effectiveAccess) {
                return;
            }

            if (typeof window === 'undefined' || typeof window.wp === 'undefined' || !window.wp.media) {
                setMediaErrorMessage(todoId, getString(i18n, 'mediaUnavailable', 'La médiathèque est indisponible.'));
                return;
            }

            var frame = mediaFrameRef.current;
            if (!frame) {
                frame = window.wp.media({
                    title: getString(i18n, 'mediaAdd', 'Ajouter un média'),
                    button: {
                        text: getString(i18n, 'mediaAdd', 'Ajouter un média'),
                    },
                    multiple: true,
                });
                frame.on('select', function () {
                    var selection = frame.state().get('selection');
                    if (!selection) {
                        return;
                    }
                    var selected = [];
                    selection.each(function (model) {
                        if (!model) {
                            return;
                        }
                        if (typeof model.toJSON === 'function') {
                            selected.push(model.toJSON());
                        } else {
                            selected.push(model);
                        }
                    });
                    if (!frame.mjTodoTargetId) {
                        return;
                    }
                    handleAttachMedia(frame.mjTodoTargetId, selected);
                });
                mediaFrameRef.current = frame;
            }

            frame.mjTodoTargetId = todoId;
            frame.open();
        }, [preview, effectiveAccess, i18n, mediaFrameRef, handleAttachMedia, setMediaErrorMessage]);

        var handleRemoveMedia = useCallback(function (todoId, attachmentId) {
            var todoKey = String(todoId);
            var attachmentKey = String(attachmentId);

            if (!attachmentKey) {
                return;
            }

            if (preview) {
                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === todoKey) {
                            var nextMedia = Array.isArray(todo.media)
                                ? todo.media.filter(function (entry) {
                                    var entryAttachmentId = entry && entry.attachmentId !== undefined && entry.attachmentId !== null
                                        ? String(entry.attachmentId)
                                        : entry && entry.id !== undefined && entry.id !== null
                                            ? String(entry.id)
                                            : '';
                                    return entryAttachmentId !== attachmentKey;
                                })
                                : [];
                            return Object.assign({}, todo, { media: nextMedia });
                        }
                        return todo;
                    });
                });
                setMediaErrorMessage(todoId, '');
                return;
            }

            if (!effectiveAccess || !detachMediaAction || !ajaxUrl) {
                setMediaErrorMessage(todoId, getString(i18n, 'mediaRemoveError', 'Impossible de retirer le média.'));
                return;
            }

            var pendingKey = todoKey + ':' + attachmentKey;
            setPendingMediaRemoves(function (previous) {
                var next = new Set(previous);
                next.add(pendingKey);
                return next;
            });

            var body = encodeForm({
                action: detachMediaAction,
                nonce: nonce || '',
                todo_id: todoKey,
                attachment_id: attachmentKey,
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data || !payload.data.todo) {
                        throw new Error('api_error');
                    }
                    var normalized = normalizeTodosList([payload.data.todo]);
                    if (!normalized.length) {
                        throw new Error('api_error');
                    }
                    var updatedTodo = normalized[0];
                    setTodos(function (previous) {
                        return previous.map(function (todo) {
                            return String(todo.id) === todoKey ? updatedTodo : todo;
                        });
                    });
                    setArchivedTodos(function (previous) {
                        return previous.map(function (todo) {
                            return String(todo.id) === todoKey ? updatedTodo : todo;
                        });
                    });
                    setMediaErrorMessage(todoId, '');
                })
                .catch(function () {
                    setMediaErrorMessage(todoId, getString(i18n, 'mediaRemoveError', 'Impossible de retirer le média.'));
                })
                .finally(function () {
                    setPendingMediaRemoves(function (previous) {
                        var next = new Set(previous);
                        next.delete(pendingKey);
                        return next;
                    });
                });
        }, [preview, effectiveAccess, detachMediaAction, ajaxUrl, nonce, i18n, setTodos, setArchivedTodos, setMediaErrorMessage, setPendingMediaRemoves]);

        var handleArchive = useCallback(function (todoId) {
            var key = String(todoId);
            if (!effectiveAccess) {
                return;
            }

            if (preview) {
                var archivedSnapshot = null;
                setTodos(function (previous) {
                    return previous.map(function (todo) {
                        if (String(todo.id) === key) {
                            archivedSnapshot = Object.assign({}, todo, { status: 'archived' });
                            return archivedSnapshot;
                        }
                        return todo;
                    });
                });
                if (archivedSnapshot) {
                    setArchivedTodos(function (previous) {
                        var exists = previous.some(function (entry) { return String(entry && entry.id) === key; });
                        if (exists) {
                            return previous.map(function (entry) {
                                if (String(entry && entry.id) === key) {
                                    return archivedSnapshot;
                                }
                                return entry;
                            });
                        }
                        return [archivedSnapshot].concat(previous);
                    });
                }
                setOpenNoteForms(function (previous) {
                    if (!previous.has(key)) {
                        return previous;
                    }
                    var next = new Set(previous);
                    next.delete(key);
                    return next;
                });
                setNoteDrafts(function (previous) {
                    if (!previous.has(key)) {
                        return previous;
                    }
                    var next = new Map(previous);
                    next.delete(key);
                    return next;
                });
                setNoteErrors(function (previous) {
                    if (!previous.has(key)) {
                        return previous;
                    }
                    var next = new Map(previous);
                    next.delete(key);
                    return next;
                });
                setPendingNotes(function (previous) {
                    if (!previous.has(key)) {
                        return previous;
                    }
                    var next = new Set(previous);
                    next.delete(key);
                    return next;
                });
                return;
            }

            if (!archiveAction || !ajaxUrl || pendingArchives.has(key)) {
                return;
            }

            setError('');
            var previousSnapshot = null;
            var archivedSnapshot = null;
            setTodos(function (previous) {
                return previous.map(function (todo) {
                    if (String(todo.id) === key) {
                        previousSnapshot = todo;
                        archivedSnapshot = Object.assign({}, todo, { status: 'archived' });
                        return archivedSnapshot;
                    }
                    return todo;
                });
            });

            setPendingArchives(function (previous) {
                var next = new Set(previous);
                next.add(key);
                return next;
            });

            var body = encodeForm({
                action: archiveAction,
                nonce: nonce || '',
                todo_id: String(todoId),
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
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

                    var archivedTodo = archivedSnapshot;
                    if (payload && payload.data && payload.data.todo) {
                        var normalized = normalizeTodosList([payload.data.todo]);
                        if (normalized.length > 0) {
                            archivedTodo = normalized[0];
                        }
                    }

                    setTodos(function (previous) {
                        return previous.filter(function (todo) { return String(todo.id) !== key; });
                    });

                    setCollapsedTodos(function (previous) {
                        if (!previous || !previous.has(key)) {
                            return previous;
                        }
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });

                    if (archivedTodo) {
                        setArchivedTodos(function (previous) {
                            var source = Array.isArray(previous) ? previous : [];
                            var next = source.filter(function (entry) { return String(entry && entry.id) !== key; });
                            next.unshift(archivedTodo);
                            return next;
                        });

                        var projectKey = archivedTodo && archivedTodo.projectId !== undefined && archivedTodo.projectId !== null
                            ? String(archivedTodo.projectId)
                            : '';
                        if (projectKey !== '') {
                            setArchivedProjectsMap(function (previous) {
                                var base = previous instanceof Map ? previous : new Map();
                                var next = new Map(base);
                                if (!next.has(projectKey)) {
                                    var reference = projectsMap.has(projectKey) ? projectsMap.get(projectKey) : null;
                                    var projectTitle = reference && typeof reference.title === 'string'
                                        ? reference.title
                                        : (typeof archivedTodo.projectTitle === 'string' ? archivedTodo.projectTitle : '');
                                    var projectColor = reference && typeof reference.color === 'string'
                                        ? reference.color
                                        : '';
                                    next.set(projectKey, {
                                        id: archivedTodo.projectId,
                                        title: projectTitle,
                                        color: projectColor,
                                    });
                                }
                                return next;
                            });
                        }
                    }

                    setOpenNoteForms(function (previous) {
                        if (!previous.has(key)) {
                            return previous;
                        }
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });
                    setNoteDrafts(function (previous) {
                        if (!previous.has(key)) {
                            return previous;
                        }
                        var next = new Map(previous);
                        next.delete(key);
                        return next;
                    });
                    setNoteErrors(function (previous) {
                        if (!previous.has(key)) {
                            return previous;
                        }
                        var next = new Map(previous);
                        next.delete(key);
                        return next;
                    });
                    setPendingNotes(function (previous) {
                        if (!previous.has(key)) {
                            return previous;
                        }
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });

                    fetchArchived();
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setError(getString(i18n, 'archiveError', 'Impossible d’archiver la tâche.'));
                    if (previousSnapshot) {
                        setTodos(function (previous) {
                            return previous.map(function (todo) {
                                if (String(todo.id) === key) {
                                    return previousSnapshot;
                                }
                                return todo;
                            });
                        });
                    }
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setPendingArchives(function (previous) {
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });
                });
        }, [effectiveAccess, preview, archiveAction, ajaxUrl, pendingArchives, setTodos, setCollapsedTodos, setArchivedTodos, setArchivedProjectsMap, projectsMap, setOpenNoteForms, setNoteDrafts, setNoteErrors, setPendingNotes, setError, nonce, fetchArchived, i18n, setPendingArchives]);

        var handleDeleteArchived = useCallback(function (todoId) {
            var key = String(todoId);
            if (!effectiveAccess) {
                return;
            }

            if (preview) {
                setTodos(function (previous) {
                    return previous.filter(function (todo) { return String(todo && todo.id) !== key; });
                });
                setArchivedTodos(function (previous) {
                    return previous.filter(function (todo) { return String(todo && todo.id) !== key; });
                });
                return;
            }

            if (!deleteAction || !ajaxUrl || pendingDeletes.has(key)) {
                return;
            }

            var confirmMessage = getString(i18n, 'deleteConfirm', 'Voulez-vous supprimer définitivement cette tâche ?');
            if (typeof window !== 'undefined' && typeof window.confirm === 'function') {
                if (!window.confirm(confirmMessage)) {
                    return;
                }
            }

            setArchivesError('');
            setPendingDeletes(function (previous) {
                var next = new Set(previous);
                next.add(key);
                return next;
            });

            var body = encodeForm({
                action: deleteAction,
                nonce: nonce || '',
                todo_id: String(todoId),
            });

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                },
                body: body,
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('http_error');
                    }
                    return response.json();
                })
                .then(function (payload) {
                    if (!isMountedRef.current) {
                        return;
                    }
                    if (!payload || !payload.success || !payload.data) {
                        throw new Error('api_error');
                    }

                    var removedId = payload.data && payload.data.todoId !== undefined
                        ? String(payload.data.todoId)
                        : key;

                    setArchivedTodos(function (previous) {
                        return previous.filter(function (todo) { return String(todo && todo.id) !== removedId; });
                    });
                    return refresh();
                })
                .catch(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setArchivesError(getString(i18n, 'deleteError', 'Impossible de supprimer la tâche.'));
                })
                .finally(function () {
                    if (!isMountedRef.current) {
                        return;
                    }
                    setPendingDeletes(function (previous) {
                        var next = new Set(previous);
                        next.delete(key);
                        return next;
                    });
                });
        }, [effectiveAccess, preview, deleteAction, ajaxUrl, pendingDeletes, nonce, i18n, setArchivedTodos, setPendingDeletes, setArchivesError, refresh, setTodos]);

        var loader = loading
            ? h('div', { className: 'mj-todo-widget__loading' }, [
                h('span', { className: 'mj-todo-widget__spinner', 'aria-hidden': 'true' }),
                h('span', { className: 'mj-todo-widget__loading-label' }, getString(i18n, 'loading', 'Chargement des tâches…')),
            ])
            : null;

        var header = null;
        if (title || intro) {
            header = h('div', { className: 'mj-todo-widget__header' }, [
                title ? h('h2', { className: 'mj-todo-widget__title' }, title) : null,
                intro ? h('div', {
                    className: 'mj-todo-widget__intro',
                    dangerouslySetInnerHTML: { __html: intro },
                }) : null,
            ]);
        }

        if (!preview && !hasAccess) {
            return h(Fragment, null, [
                header,
                h('p', { className: 'mj-todo-widget__notice' }, getString(i18n, 'accessDenied', 'Accès refusé.')),
            ]);
        }

        var assigneesField = assignableMembers.length > 0
            ? h('div', { className: 'mj-todo-widget__form-assignees' }, [
                h('span', {
                    className: 'mj-todo-widget__form-label',
                    id: assigneesGroupIdRef.current,
                }, getString(i18n, 'assigneesLabel', 'Assigner à')),
                h('div', {
                    className: 'mj-todo-widget__assignees-options',
                    role: 'group',
                    'aria-labelledby': assigneesGroupIdRef.current,
                }, assignableMembers.map(function (member) {
                    var optionValue = member && member.id !== undefined && member.id !== null ? String(member.id) : '';
                    if (optionValue === '') {
                        return null;
                    }
                    var displayName = member && typeof member.name === 'string' && member.name !== '' ? member.name : optionValue;
                    var isSelfMember = !!(member && member.isSelf);
                    var selfLabel = isSelfMember ? getString(i18n, 'assigneeYou', 'Moi') : '';
                    var isChecked = assigneeSelection.has(optionValue);
                    var avatarData = member && member.avatar ? member.avatar : {};
                    var avatarUrl = avatarData && typeof avatarData.url === 'string' ? avatarData.url : '';
                    var avatarInitials = avatarData && typeof avatarData.initials === 'string' ? avatarData.initials : '';
                    var avatarAlt = avatarData && typeof avatarData.alt === 'string' ? avatarData.alt : displayName;
                    var memberRole = member && typeof member.role === 'string' ? member.role : '';
                    var metaSegments = [];
                    if (selfLabel) {
                        metaSegments.push(selfLabel);
                    }
                    if (memberRole && memberRole !== selfLabel) {
                        metaSegments.push(memberRole);
                    }
                    var metaText = metaSegments.join(' • ');
                    var optionClasses = 'mj-todo-widget__assignees-option' + (isChecked ? ' is-selected' : '');
                    return h('label', { className: optionClasses, key: optionValue }, [
                        h('input', {
                            type: 'checkbox',
                            className: 'mj-todo-widget__assignee-checkbox',
                            value: optionValue,
                            checked: isChecked,
                            onChange: function (event) { return handleAssigneeToggle(optionValue, !!event.target.checked); },
                            disabled: submitting,
                        }),
                        h('span', { className: 'mj-todo-widget__assignee-visual' }, [
                            h('span', { className: 'mj-todo-widget__assignee-avatar', 'aria-hidden': avatarUrl === '' ? 'true' : undefined }, avatarUrl
                                ? h('img', {
                                    className: 'mj-todo-widget__assignee-avatar-image',
                                    src: avatarUrl,
                                    alt: avatarAlt || displayName,
                                    loading: 'lazy',
                                })
                                : avatarInitials
                                    ? h('span', { className: 'mj-todo-widget__assignee-initials', 'aria-hidden': 'true' }, avatarInitials)
                                    : h('span', { className: 'mj-todo-widget__assignee-placeholder', 'aria-hidden': 'true' }, '?')),
                            h('span', { className: 'mj-todo-widget__assignee-content' }, [
                                h('span', { className: 'mj-todo-widget__assignee-name' }, displayName),
                                metaText ? h('span', { className: 'mj-todo-widget__assignee-meta' }, metaText) : null,
                            ]),
                        ]),
                    ]);
                })),
            ])
            : h('p', { className: 'mj-todo-widget__form-empty' }, getString(i18n, 'assigneesEmpty', 'Aucun membre disponible.'));

        var creationPriority = clampPriorityValue(formPriority);
        var createPriorityCurrentTemplate = getString(i18n, 'priorityCurrentLabel', 'Priorité actuelle : %s sur 5');
        var createPriorityGroupLabel = createPriorityCurrentTemplate.indexOf('%s') !== -1
            ? createPriorityCurrentTemplate.replace('%s', String(creationPriority))
            : createPriorityCurrentTemplate + ' ' + creationPriority + '/5';
        var createPrioritySetTemplate = getString(i18n, 'prioritySetLabel', 'Définir la priorité à %s sur 5');
        var createPriorityButtons = [];
        for (var createIndex = 1; createIndex <= 5; createIndex += 1) {
            (function (value) {
                var isActive = creationPriority >= value;
                var ariaLabel = createPrioritySetTemplate.indexOf('%s') !== -1
                    ? createPrioritySetTemplate.replace('%s', String(value))
                    : createPrioritySetTemplate + ' ' + value;
                createPriorityButtons.push(h('button', {
                    type: 'button',
                    key: 'create-priority-' + value,
                    className: 'mj-todo-widget__priority-star' + (isActive ? ' is-active' : ''),
                    onClick: function () {
                        if (submitting) {
                            return;
                        }
                        setFormPriority(value);
                    },
                    disabled: submitting,
                    'aria-pressed': isActive ? 'true' : 'false',
                    'aria-label': ariaLabel,
                }, [
                    h('svg', { className: 'mj-todo-widget__priority-icon', viewBox: '0 0 24 24', 'aria-hidden': 'true' }, [
                        h('path', { d: 'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73-1.64 6.99L12 17.27z', fill: 'currentColor' }),
                    ]),
                ]));
            })(createIndex);
        }
        var createPriorityHint = getString(i18n, 'priorityHint', '1 = faible, 5 = urgente');
        var priorityFieldNode = h('div', { className: 'mj-todo-widget__priority-field' }, [
            h('span', { className: 'mj-todo-widget__form-label', id: priorityFieldIdRef.current }, getString(i18n, 'priorityLabel', 'Priorité')),
            h('div', {
                className: 'mj-todo-widget__priority-stars',
                role: 'group',
                'aria-labelledby': priorityFieldIdRef.current,
                'aria-label': createPriorityGroupLabel,
            }, createPriorityButtons),
            h('span', { className: 'mj-todo-widget__priority-value' }, creationPriority + '/5'),
            createPriorityHint ? h('p', { className: 'mj-todo-widget__form-hint' }, createPriorityHint) : null,
        ]);

        var formMediaItems = formMedia.map(function (entry, index) {
            if (!entry) {
                return null;
            }
            var attachmentKey = entry.attachmentId !== undefined && entry.attachmentId !== null && entry.attachmentId !== ''
                ? String(entry.attachmentId)
                : entry.id !== undefined && entry.id !== null && entry.id !== ''
                    ? String(entry.id)
                    : 'form-media-' + index;
            if (attachmentKey === '') {
                return null;
            }
            var displayName = typeof entry.title === 'string' && entry.title !== ''
                ? entry.title
                : typeof entry.filename === 'string' && entry.filename !== ''
                    ? entry.filename
                    : getString(i18n, 'formMediaFallback', 'Document');
            var filename = typeof entry.filename === 'string' ? entry.filename : '';
            var mimeType = typeof entry.mimeType === 'string' ? entry.mimeType : '';
            var addedAt = typeof entry.addedAt === 'string' ? entry.addedAt : '';
            var previewSource = typeof entry.previewUrl === 'string' && entry.previewUrl !== '' ? entry.previewUrl : '';
            var url = typeof entry.url === 'string' && entry.url !== '' ? entry.url : '';
            var iconSource = typeof entry.iconUrl === 'string' && entry.iconUrl !== '' ? entry.iconUrl : '';
            var typeLabel = typeof entry.type === 'string' ? entry.type : '';
            if (!typeLabel && mimeType && mimeType.indexOf('/') !== -1) {
                typeLabel = mimeType.split('/')[0];
            }

            var previewVisual = null;
            if (entry.type === 'image' && (previewSource || url)) {
                var imageSrc = previewSource || url;
                previewVisual = h('img', {
                    className: 'mj-todo-widget__media-thumb',
                    src: imageSrc,
                    alt: getString(i18n, 'mediaPreviewAlt', 'Prévisualisation du média'),
                });
            } else if (iconSource) {
                previewVisual = h('img', {
                    className: 'mj-todo-widget__media-thumb',
                    src: iconSource,
                    alt: '',
                    role: 'presentation',
                });
            } else {
                var badge = typeLabel !== '' ? typeLabel.slice(0, 3).toUpperCase() : 'DOC';
                previewVisual = h('span', { className: 'mj-todo-widget__media-placeholder', 'aria-hidden': 'true' }, badge);
            }

            var details = [];
            if (filename && filename !== displayName) {
                details.push(filename);
            }
            if (mimeType) {
                details.push(mimeType);
            }
            if (addedAt) {
                details.push(addedAt);
            }

            var removeButton = h('button', {
                type: 'button',
                className: 'mj-todo-widget__media-remove',
                onClick: function () { return handleCreateRemoveMedia(attachmentKey); },
                disabled: submitting,
            }, getString(i18n, 'mediaRemove', 'Retirer'));

            return h('li', { key: attachmentKey, className: 'mj-todo-widget__media-item' }, [
                h('div', { className: 'mj-todo-widget__media-preview' }, previewVisual),
                h('div', { className: 'mj-todo-widget__media-meta' }, [
                    url
                        ? h('a', {
                            className: 'mj-todo-widget__media-link',
                            href: url,
                            target: '_blank',
                            rel: 'noopener noreferrer',
                        }, displayName)
                        : h('span', { className: 'mj-todo-widget__media-link' }, displayName),
                    details.length > 0 ? h('span', { className: 'mj-todo-widget__media-details' }, details.join(' • ')) : null,
                ].filter(Boolean)),
                removeButton,
            ]);
        }).filter(Boolean);

        var formMediaListNode = formMediaItems.length > 0
            ? h('ul', { className: 'mj-todo-widget__media-list' }, formMediaItems)
            : h('p', { className: 'mj-todo-widget__media-empty' }, getString(i18n, 'formMediaEmpty', 'Aucun document lié pour le moment.'));

        var formMediaSection = h('div', { className: 'mj-todo-widget__media mj-todo-widget__media--form' }, [
            h('div', { className: 'mj-todo-widget__media-header' }, [
                h('span', { className: 'mj-todo-widget__media-title' }, getString(i18n, 'formMediaTitle', 'Documents liés')),
                h('button', {
                    type: 'button',
                    className: 'mj-todo-widget__media-add',
                    onClick: handleCreateOpenMediaLibrary,
                    disabled: submitting,
                }, getString(i18n, 'formMediaAdd', 'Lier un document')),
            ]),
            formMediaError ? h('p', { className: 'mj-todo-widget__media-error', role: 'alert' }, formMediaError) : null,
            formMediaListNode,
        ].filter(Boolean));

        var form = h('form', {
            className: 'mj-todo-widget__form' + (submitting ? ' is-submitting' : ''),
            onSubmit: handleCreate,
        }, [
            h('div', { className: 'mj-todo-widget__form-fields' }, [
                h('input', {
                    ref: titleInputRef,
                    type: 'text',
                    className: 'mj-todo-widget__input',
                    placeholder: getString(i18n, 'addPlaceholder', 'Nouvelle tâche…'),
                    value: formTitle,
                    onInput: function (event) { return setFormTitle(event.target.value); },
                    disabled: submitting,
                    required: true,
                }),
                h('select', {
                    className: 'mj-todo-widget__select',
                    value: formProjectId,
                    onChange: function (event) { return setFormProjectId(event.target.value); },
                    disabled: submitting,
                }, [
                    h('option', { value: '' }, getString(i18n, 'projectPlaceholder', 'Dossier (optionnel)')),
                    projects.map(function (project) {
                        var optionValue = project && project.id !== undefined && project.id !== null ? String(project.id) : '';
                        var label = project && typeof project.title === 'string' ? project.title : '';
                        return h('option', { value: optionValue }, label);
                    }),
                ]),
                h('input', {
                    type: 'date',
                    className: 'mj-todo-widget__input mj-todo-widget__input--date',
                    value: formDueDate,
                    onInput: function (event) { return setFormDueDate(event.target.value); },
                    placeholder: getString(i18n, 'dueLabel', 'Échéance'),
                    disabled: submitting,
                }),
            ]),
            h('div', { className: 'mj-todo-widget__form-description' }, [
                h('label', {
                    className: 'mj-todo-widget__form-label',
                    htmlFor: descriptionInputIdRef.current,
                }, getString(i18n, 'descriptionLabel', 'Description')),
                h('textarea', {
                    id: descriptionInputIdRef.current,
                    ref: descriptionInputRef,
                    className: 'mj-todo-widget__textarea',
                    value: formDescription,
                    onInput: function (event) { return setFormDescription(event.target.value); },
                    placeholder: getString(i18n, 'descriptionPlaceholder', 'Description (optionnel)'),
                    disabled: submitting,
                    rows: 3,
                }),
            ]),
            formMediaSection,
            priorityFieldNode,
            createError ? h('p', { className: 'mj-todo-widget__form-error', role: 'alert' }, createError) : null,
            assigneesField,
            h('button', {
                type: 'submit',
                className: 'mj-todo-widget__submit',
                disabled: submitting || assignableMembers.length === 0,
            }, getString(i18n, 'submit', 'Ajouter')),
        ]);

        var createButton = effectiveAccess
            ? h('button', {
                type: 'button',
                className: 'mj-todo-widget__create-button',
                onClick: handleOpenCreateModal,
                disabled: submitting || assignableMembers.length === 0,
                title: assignableMembers.length === 0 ? getString(i18n, 'assigneesEmpty', 'Aucun membre disponible.') : undefined,
            }, getString(i18n, 'openCreateModal', 'Ajouter une tâche'))
            : null;

        var createModal = createModalOpen && effectiveAccess
            ? h('div', {
                className: 'mj-todo-widget__modal',
                role: 'dialog',
                'aria-modal': 'true',
                'aria-labelledby': createModalTitleIdRef.current,
            }, [
                h('div', {
                    className: 'mj-todo-widget__modal-overlay',
                    onClick: handleCloseCreateModal,
                }),
                h('div', {
                    className: 'mj-todo-widget__modal-panel',
                    role: 'document',
                    onClick: function (event) { return event.stopPropagation(); },
                    'aria-describedby': createModalDescriptionIdRef.current,
                }, [
                    h('div', { className: 'mj-todo-widget__modal-header' }, [
                        h('h4', { id: createModalTitleIdRef.current, className: 'mj-todo-widget__modal-title' }, getString(i18n, 'createModalTitle', 'Nouvelle tâche')),
                        h('button', {
                            type: 'button',
                            className: 'mj-todo-widget__modal-close',
                            onClick: handleCloseCreateModal,
                            disabled: submitting,
                        }, getString(i18n, 'close', 'Fermer')),
                    ]),
                    h('div', { className: 'mj-todo-widget__modal-body', id: createModalDescriptionIdRef.current }, form),
                ]),
            ])
            : null;

        var projectTabEntries = [{
            id: '',
            title: getString(i18n, 'projectFilterAll', 'Tous les dossiers'),
        }].concat(projects.map(function (project) {
            return {
                id: project && project.id !== undefined && project.id !== null ? String(project.id) : '',
                title: project && typeof project.title === 'string' ? project.title : '',
            };
        }).filter(function (entry) { return entry.id !== ''; }));

        var projectTabsNode = h('div', {
            className: 'mj-todo-widget__project-tabs',
            role: 'tablist',
            'aria-label': getString(i18n, 'projectFilterLabel', 'Filtrer par dossier'),
        }, projectTabEntries.map(function (tab) {
            var isActive = String(selectedProject) === String(tab.id);
            return h('button', {
                type: 'button',
                className: 'mj-todo-widget__project-tab' + (isActive ? ' is-active' : ''),
                onClick: function () { return handleProjectTabSelect(tab.id); },
                disabled: loading && !isActive,
                role: 'tab',
                'aria-selected': isActive ? 'true' : 'false',
            }, tab.title);
        }));

        var statusOptions = [
            { id: 'todo', label: getString(i18n, 'filterTodo', 'À faire') },
            { id: 'completed', label: getString(i18n, 'filterCompleted', 'Terminées') },
            { id: 'archived', label: getString(i18n, 'filterArchived', 'Archivées') },
            { id: 'all', label: getString(i18n, 'filterAll', 'Afficher tout') },
        ];

        var statusTabsNode = h('div', {
            className: 'mj-todo-widget__status-tabs mj-todo-widget__project-tabs',
            role: 'tablist',
            'aria-label': getString(i18n, 'statusFilterLabel', 'Afficher'),
        }, statusOptions.map(function (option) {
            var isActive = statusFilter === option.id;
            return h('button', {
                type: 'button',
                className: 'mj-todo-widget__project-tab' + (isActive ? ' is-active' : ''),
                onClick: function () { return handleStatusFilterSelect(option.id); },
                role: 'tab',
                'aria-selected': isActive ? 'true' : 'false',
            }, option.label);
        }));

        var filtersNode = h('div', { className: 'mj-todo-widget__filters' }, [
            h('div', { className: 'mj-todo-widget__filters-block' }, [
                h('div', { className: 'mj-todo-widget__filters-header' }, [
                    h('div', { className: 'mj-todo-widget__filters-title' }, [
                        h('span', { className: 'mj-todo-widget__filters-label' }, getString(i18n, 'projectFilterLabel', 'Filtrer par dossier')),
                        statusTabsNode,
                    ]),
                    effectiveAccess
                        ? h('button', {
                            type: 'button',
                            className: 'mj-todo-widget__filters-action',
                            onClick: toggleProjectForm,
                        }, getString(i18n, 'projectCreateButton', 'Nouveau dossier'))
                        : null,
                ]),
                projectTabsNode,
            ]),
            h('div', { className: 'mj-todo-widget__filters-block mj-todo-widget__filters-block--sort' }, [
                h('label', {
                    className: 'mj-todo-widget__filters-label',
                    htmlFor: sortSelectIdRef.current,
                }, getString(i18n, 'sortLabel', 'Tri')),
                h('select', {
                    id: sortSelectIdRef.current,
                    className: 'mj-todo-widget__select',
                    value: sortMode,
                    onChange: handleSortChange,
                }, [
                    h('option', { value: 'position' }, getString(i18n, 'sortDefault', 'Par priorité')),
                    h('option', { value: 'project' }, getString(i18n, 'sortByProject', 'Par dossier')),
                ]),
            ]),
        ]);

        var projectFeedbackAlert = projectFeedback && projectFeedback.text
            ? h('div', {
                className: 'mj-todo-widget__project-feedback' + (projectFeedback.kind === 'error' ? ' is-error' : projectFeedback.kind === 'success' ? ' is-success' : ''),
                role: projectFeedback.kind === 'error' ? 'alert' : 'status',
                'aria-live': projectFeedback.kind === 'error' ? 'assertive' : 'polite',
            }, projectFeedback.text)
            : null;

        var projectFormNode = showProjectForm && effectiveAccess
            ? h('form', {
                className: 'mj-todo-widget__project-form' + (creatingProject ? ' is-submitting' : ''),
                onSubmit: handleProjectFormSubmit,
            }, [
                h('div', { className: 'mj-todo-widget__project-fields' }, [
                    h('input', {
                        id: projectNameInputIdRef.current,
                        ref: projectInputRef,
                        type: 'text',
                        className: 'mj-todo-widget__input',
                        value: projectFormTitle,
                        onInput: function (event) { return setProjectFormTitle(event.target.value); },
                        placeholder: getString(i18n, 'projectCreatePlaceholder', 'Nom du dossier'),
                        disabled: creatingProject,
                        required: true,
                    }),
                ]),
                h('div', { className: 'mj-todo-widget__project-actions' }, [
                    h('button', {
                        type: 'submit',
                        className: 'mj-todo-widget__project-submit',
                        disabled: creatingProject,
                    }, getString(i18n, 'projectCreateSubmit', 'Ajouter le dossier')),
                    h('button', {
                        type: 'button',
                        className: 'mj-todo-widget__project-cancel',
                        onClick: toggleProjectForm,
                    }, getString(i18n, 'cancel', 'Annuler')),
                ]),
            ])
            : null;

        var buildTodoListItems = function (todosSource, options) {
            var projectsLookup = options && options.projectsMap ? options.projectsMap : null;
            var emptyMessage = options && typeof options.emptyMessage === 'string'
                ? options.emptyMessage
                : getString(i18n, 'empty', 'Aucune tâche pour le moment.');
            var list = Array.isArray(todosSource) ? todosSource : [];
            if (list.length === 0) {
                return [
                    h('li', { className: 'mj-todo-widget__empty' }, emptyMessage),
                ];
            }

            return list.map(function (todo) {
                if (!todo) {
                    return null;
                }
                var todoId = todo && todo.id !== undefined ? todo.id : '';
                var todoKey = String(todoId);
                var status = typeof todo.status === 'string' ? todo.status : 'open';
                var isEditing = editingTodos.has(todoKey);
                var isCollapsed = collapsedTodos.has(todoKey);
                var draft = editDrafts.has(todoKey) ? editDrafts.get(todoKey) : null;
                var draftTitle = draft && typeof draft.title === 'string'
                    ? draft.title
                    : (todo && typeof todo.title === 'string' ? todo.title : '');
                var draftDescription = draft && typeof draft.description === 'string'
                    ? draft.description
                    : (typeof todo.description === 'string' ? todo.description : '');
                var updatePending = pendingUpdates.has(todoKey);
                var editErrorMessage = editErrors.has(todoKey) ? editErrors.get(todoKey) : '';
                var itemClasses = 'mj-todo-widget__item';
                if (status === 'completed') {
                    itemClasses += ' mj-todo-widget__item--completed';
                }
                if (status === 'archived') {
                    itemClasses += ' mj-todo-widget__item--archived';
                }
                if (isEditing) {
                    itemClasses += ' mj-todo-widget__item--editing';
                }
                if (isCollapsed) {
                    itemClasses += ' mj-todo-widget__item--collapsed';
                }
                var togglePending = pendingToggles.has(todoKey);
                var archivePending = pendingArchives.has(todoKey);
                var deletePending = pendingDeletes.has(todoKey);
                var isPending = togglePending || archivePending || updatePending;
                var isArchived = status === 'archived';
                var projectKey = todo && todo.projectId !== undefined && todo.projectId !== null ? String(todo.projectId) : '';
                var projectLabel = '';
                var projectEntry = projectKey !== '' && projectsLookup && projectsLookup.has(projectKey) ? projectsLookup.get(projectKey) : null;
                if (typeof todo.projectTitle === 'string' && todo.projectTitle !== '') {
                    projectLabel = todo.projectTitle;
                } else if (projectEntry && typeof projectEntry.title === 'string' && projectEntry.title !== '') {
                    projectLabel = projectEntry.title;
                }
                var projectBadgeStyle = undefined;
                if (projectEntry && typeof projectEntry.color === 'string' && projectEntry.color !== '') {
                    if (/^#([0-9a-f]{6})$/i.test(projectEntry.color)) {
                        projectBadgeStyle = {
                            '--mj-todo-badge-color': projectEntry.color,
                            '--mj-todo-badge-bg': projectEntry.color + '1f',
                        };
                    } else {
                        projectBadgeStyle = {
                            '--mj-todo-badge-color': projectEntry.color,
                        };
                    }
                }
                var dueDisplay = typeof todo.dueDate === 'string' ? todo.dueDate : '';
                var completedDisplay = typeof todo.completedAt === 'string' ? todo.completedAt : '';
                var assignees = Array.isArray(todo && todo.assignees) ? todo.assignees : [];
                var assigneeNames = assignees.map(function (assignee) {
                    if (!assignee) {
                        return '';
                    }
                    var name = assignee && typeof assignee.name === 'string' ? assignee.name : '';
                    if (!name) {
                        var fallbackId = assignee && assignee.id !== undefined && assignee.id !== null ? String(assignee.id) : '';
                        name = fallbackId !== '' ? '#' + fallbackId : '';
                    }
                    if (assignee && assignee.isSelf) {
                        name += ' (' + getString(i18n, 'assigneeYou', 'Moi') + ')';
                    }
                    return name;
                }).filter(function (label) { return label && label !== ''; });
                var assigneesLabel = assigneeNames.length > 0
                    ? getString(i18n, 'assigneesLabel', 'Assigner à') + ': ' + assigneeNames.join(', ')
                    : '';
                var assigneeChipNodes = assignees.map(function (assignee, index) {
                    if (!assignee) {
                        return null;
                    }
                    var chipKey = assignee && assignee.id !== undefined && assignee.id !== null
                        ? String(assignee.id)
                        : todoKey + ':assignee-' + index;
                    var chipName = assignee && typeof assignee.name === 'string' && assignee.name !== ''
                        ? assignee.name
                        : '';
                    if (chipName === '') {
                        var fallbackKey = assignee && assignee.id !== undefined && assignee.id !== null ? String(assignee.id) : '';
                        chipName = fallbackKey !== '' ? '#' + fallbackKey : getString(i18n, 'unknownMember', 'Membre inconnu');
                    }
                    var chipMetaSegments = [];
                    if (assignee && assignee.isSelf) {
                        chipMetaSegments.push(getString(i18n, 'assigneeYou', 'Moi'));
                    }
                    if (assignee && typeof assignee.role === 'string' && assignee.role !== '') {
                        chipMetaSegments.push(assignee.role);
                    }
                    var chipMeta = chipMetaSegments.join(' • ');
                    var chipInitials = extractInitials(chipName);
                    if (!chipInitials) {
                        chipInitials = chipMeta ? extractInitials(chipMeta) : '';
                    }
                    if (!chipInitials) {
                        chipInitials = '?';
                    }
                    return h('span', { key: chipKey, className: 'mj-todo-widget__assignee-chip' }, [
                        h('span', { className: 'mj-todo-widget__assignee-chip-avatar', 'aria-hidden': 'true' }, chipInitials),
                        h('span', { className: 'mj-todo-widget__assignee-chip-text' }, [
                            h('span', { className: 'mj-todo-widget__assignee-chip-name' }, chipName),
                            chipMeta ? h('span', { className: 'mj-todo-widget__assignee-chip-role' }, chipMeta) : null,
                        ]),
                    ]);
                }).filter(Boolean);
                var assigneesGroup = assigneeChipNodes.length > 0
                    ? h('div', { className: 'mj-todo-widget__assignees-group', title: assigneesLabel }, [
                        h('span', { className: 'mj-todo-widget__assignees-label' }, getString(i18n, 'assigneesLabel', 'Assigner à')),
                        h('div', { className: 'mj-todo-widget__assignees-chips' }, assigneeChipNodes),
                    ])
                    : null;
                var descriptionValue = isEditing ? draftDescription : (typeof todo.description === 'string' ? todo.description : '');
                var descriptionNode = descriptionValue && descriptionValue.trim() !== ''
                    ? h('div', { className: 'mj-todo-widget__description' }, [
                        h('span', { className: 'mj-todo-widget__description-label' }, getString(i18n, 'descriptionLabel', 'Description')),
                        h('div', { className: 'mj-todo-widget__description-text' }, renderNoteContent(descriptionValue)),
                    ])
                    : null;

                var priorityValue = getPriorityValue(todo);
                var priorityLabel = getString(i18n, 'priorityLabel', 'Priorité');
                var priorityCurrentTemplate = getString(i18n, 'priorityCurrentLabel', 'Priorité actuelle : %s sur 5');
                var priorityGroupLabel = priorityCurrentTemplate.indexOf('%s') !== -1
                    ? priorityCurrentTemplate.replace('%s', String(priorityValue))
                    : priorityCurrentTemplate + ' ' + priorityValue + '/5';
                var prioritySetTemplate = getString(i18n, 'prioritySetLabel', 'Définir la priorité à %s sur 5');
                var canEditPriority = effectiveAccess && !isArchived && !isEditing;
                var priorityButtons = [];
                for (var starIndex = 1; starIndex <= 5; starIndex += 1) {
                    (function (value) {
                        var isActiveStar = priorityValue >= value;
                        var ariaLabel = prioritySetTemplate.indexOf('%s') !== -1
                            ? prioritySetTemplate.replace('%s', String(value))
                            : prioritySetTemplate + ' ' + value;
                        priorityButtons.push(h('button', {
                            type: 'button',
                            key: 'priority-' + value,
                            className: 'mj-todo-widget__priority-star' + (isActiveStar ? ' is-active' : ''),
                            onClick: function () {
                                if (!canEditPriority || updatePending || isArchived || isEditing || priorityValue === value) {
                                    return;
                                }
                                handlePriorityChange(todoId, value);
                            },
                            disabled: !canEditPriority || updatePending || isArchived || isEditing,
                            'aria-pressed': isActiveStar ? 'true' : 'false',
                            'aria-label': ariaLabel,
                        }, [
                            h('svg', { className: 'mj-todo-widget__priority-icon', viewBox: '0 0 24 24', 'aria-hidden': 'true' }, [
                                h('path', { d: 'M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z', fill: 'currentColor' }),
                            ]),
                        ]));
                    })(starIndex);
                }

                var priorityNode = h('div', {
                    className: 'mj-todo-widget__priority' + (canEditPriority ? '' : ' is-readonly'),
                    role: canEditPriority ? 'group' : undefined,
                    'aria-label': priorityGroupLabel,
                }, [
                    h('span', { className: 'mj-todo-widget__priority-label' }, priorityLabel),
                    h('div', { className: 'mj-todo-widget__priority-stars' }, priorityButtons),
                    h('span', { className: 'mj-todo-widget__priority-value' }, priorityValue + '/5'),
                ]);

                var summarySegments = [];
                summarySegments.push(getString(i18n, 'priorityLabel', 'Priorité') + ' ' + priorityValue + '/5');
                if (projectLabel) {
                    summarySegments.push(projectLabel);
                }
                if (dueDisplay) {
                    summarySegments.push(getString(i18n, 'dueLabel', 'Échéance') + ' ' + dueDisplay);
                }
                if (assigneeNames.length > 0) {
                    summarySegments.push(assigneeNames.join(', '));
                }
                if (isArchived && completedDisplay) {
                    summarySegments.push(completedDisplay);
                }
                var collapsedSummaryNode = isCollapsed && summarySegments.length
                    ? h('div', { className: 'mj-todo-widget__collapsed-summary' }, summarySegments.join(' • '))
                    : null;

                var archiveButton = effectiveAccess && !isArchived && !isEditing
                    ? h('button', {
                        type: 'button',
                        className: 'mj-todo-widget__archive-button',
                        onClick: function () { return handleArchive(todoId); },
                        disabled: archivePending || updatePending,
                    }, archivePending ? getString(i18n, 'archivingLabel', 'Archivage…') : getString(i18n, 'archiveLabel', 'Archiver'))
                    : null;

                var editButton = effectiveAccess && !isArchived && !isEditing
                    ? h('button', {
                        type: 'button',
                        className: 'mj-todo-widget__edit-button',
                        onClick: function () { return handleStartEdit(todo); },
                        disabled: archivePending || updatePending,
                    }, getString(i18n, 'editLabel', 'Modifier'))
                    : null;

                var deleteButton = isArchived && effectiveAccess && !isEditing
                    ? h('button', {
                        type: 'button',
                        className: 'mj-todo-widget__archives-delete',
                        onClick: function () { return handleDeleteArchived(todoId); },
                        disabled: deletePending,
                    }, deletePending ? getString(i18n, 'deletePending', 'Suppression…') : getString(i18n, 'deleteLabel', 'Supprimer'))
                    : null;

                var collapseButtonLabel = isCollapsed
                    ? getString(i18n, 'expandLabel', 'Afficher les détails')
                    : getString(i18n, 'collapseLabel', 'Réduire');
                var collapseButton = h('button', {
                    type: 'button',
                    className: 'mj-todo-widget__collapse-button',
                    onClick: function () { return handleToggleCollapse(todoId); },
                    disabled: isEditing || archivePending,
                    'aria-pressed': isCollapsed ? 'true' : 'false',
                }, collapseButtonLabel);

                var actionButtons = [];
                actionButtons.push(collapseButton);
                if (!isEditing && archiveButton) {
                    actionButtons.push(archiveButton);
                }
                if (!isEditing && editButton) {
                    actionButtons.push(editButton);
                }
                if (!isEditing && deleteButton) {
                    actionButtons.push(deleteButton);
                }
                var actionsNode = actionButtons.length > 0
                    ? h('div', { className: 'mj-todo-widget__item-actions' }, actionButtons)
                    : null;

                var statusBadge = null;
                if (isArchived) {
                    statusBadge = h('span', { className: 'mj-todo-widget__badge mj-todo-widget__badge--archived' }, getString(i18n, 'archivedLabel', 'Archivée'));
                }

                var mediaSection = null;
                var notesSection = null;
                if (!isCollapsed) {
                    var mediaEntries = Array.isArray(todo && todo.media) ? todo.media : [];
                    var mediaErrorMessage = mediaErrors.has(todoKey) ? mediaErrors.get(todoKey) : '';
                    var canModifyMedia = effectiveAccess && !isArchived && !isEditing;
                    var mediaAddPending = pendingMediaAdds.has(todoKey);
                    var mediaItems = mediaEntries.map(function (entry, index) {
                        if (!entry) {
                            return null;
                        }
                        var attachmentKey = entry.attachmentId !== undefined && entry.attachmentId !== null && entry.attachmentId !== ''
                            ? String(entry.attachmentId)
                            : entry.id !== undefined && entry.id !== null && entry.id !== ''
                                ? String(entry.id)
                                : 'media-' + index;
                        var itemKey = attachmentKey !== '' ? attachmentKey : 'media-' + index;
                        var displayName = typeof entry.title === 'string' && entry.title !== ''
                            ? entry.title
                            : (typeof entry.filename === 'string' && entry.filename !== '' ? entry.filename : 'Média');
                        var filename = typeof entry.filename === 'string' ? entry.filename : '';
                        var mimeType = typeof entry.mimeType === 'string' ? entry.mimeType : '';
                        var addedAt = typeof entry.addedAt === 'string' ? entry.addedAt : '';
                        var previewSource = typeof entry.previewUrl === 'string' && entry.previewUrl !== '' ? entry.previewUrl : '';
                        var url = typeof entry.url === 'string' && entry.url !== '' ? entry.url : '';
                        var iconSource = typeof entry.iconUrl === 'string' && entry.iconUrl !== '' ? entry.iconUrl : '';
                        var isImageType = entry.type === 'image' && (previewSource !== '' || url !== '');
                        var previewVisual = null;
                        if (isImageType && (previewSource || url)) {
                            var src = previewSource || url;
                            previewVisual = h('img', {
                                className: 'mj-todo-widget__media-thumb',
                                src: src,
                                alt: getString(i18n, 'mediaPreviewAlt', 'Prévisualisation du média'),
                            });
                        } else if (iconSource) {
                            previewVisual = h('img', {
                                className: 'mj-todo-widget__media-thumb',
                                src: iconSource,
                                alt: '',
                                role: 'presentation',
                            });
                        } else {
                            var typeLabel = '';
                            if (typeof entry.type === 'string' && entry.type !== '') {
                                typeLabel = entry.type;
                            } else if (mimeType.indexOf('/') !== -1) {
                                typeLabel = mimeType.split('/')[0];
                            }
                            var badge = typeLabel !== '' ? typeLabel.slice(0, 3).toUpperCase() : 'FILE';
                            previewVisual = h('span', { className: 'mj-todo-widget__media-placeholder', 'aria-hidden': 'true' }, badge);
                        }
                        var previewNode = h('div', { className: 'mj-todo-widget__media-preview' }, previewVisual);
                        var linkNode = url
                            ? h('a', {
                                className: 'mj-todo-widget__media-link',
                                href: url,
                                target: '_blank',
                                rel: 'noopener noreferrer',
                            }, displayName)
                            : h('span', { className: 'mj-todo-widget__media-link' }, displayName);
                        var detailParts = [];
                        if (filename && filename !== displayName) {
                            detailParts.push(filename);
                        }
                        if (mimeType) {
                            detailParts.push(mimeType);
                        }
                        if (addedAt) {
                            detailParts.push(addedAt);
                        }
                        var detailsNode = detailParts.length > 0
                            ? h('span', { className: 'mj-todo-widget__media-details' }, detailParts.join(' • '))
                            : null;
                        var removePending = pendingMediaRemoves.has(todoKey + ':' + attachmentKey);
                        var removeButton = canModifyMedia
                            ? h('button', {
                                type: 'button',
                                className: 'mj-todo-widget__media-remove',
                                onClick: function () {
                                    if (removePending || mediaAddPending || archivePending || updatePending) {
                                        return;
                                    }
                                    handleRemoveMedia(todoId, attachmentKey);
                                },
                                disabled: removePending || mediaAddPending || archivePending || updatePending,
                            }, removePending ? getString(i18n, 'mediaRemoving', 'Suppression…') : getString(i18n, 'mediaRemove', 'Retirer'))
                            : null;
                        var itemChildren = [
                            previewNode,
                            h('div', { className: 'mj-todo-widget__media-meta' }, [
                                linkNode,
                                detailsNode,
                            ].filter(Boolean)),
                        ];
                        if (removeButton) {
                            itemChildren.push(removeButton);
                        }
                        return h('li', { key: itemKey, className: 'mj-todo-widget__media-item' }, itemChildren);
                    }).filter(Boolean);
                    var mediaListNode = mediaItems.length > 0
                        ? h('ul', { className: 'mj-todo-widget__media-list' }, mediaItems)
                        : h('p', { className: 'mj-todo-widget__media-empty' }, getString(i18n, 'mediaEmpty', 'Aucun média pour le moment.'));
                    var mediaAddButton = canModifyMedia
                        ? h('button', {
                            type: 'button',
                            className: 'mj-todo-widget__media-add',
                            onClick: function () {
                                if (mediaAddPending || archivePending || updatePending) {
                                    return;
                                }
                                handleOpenMediaLibrary(todoId);
                            },
                            disabled: mediaAddPending || archivePending || updatePending,
                        }, mediaAddPending ? getString(i18n, 'mediaAdding', 'Ajout en cours…') : getString(i18n, 'mediaAdd', 'Ajouter un média'))
                        : null;
                    var mediaErrorNode = mediaErrorMessage
                        ? h('p', { className: 'mj-todo-widget__media-error', role: 'alert' }, mediaErrorMessage)
                        : null;
                    mediaSection = h('div', { className: 'mj-todo-widget__media' }, [
                        h('div', { className: 'mj-todo-widget__media-header' }, [
                            h('span', { className: 'mj-todo-widget__media-title' }, getString(i18n, 'mediaTitle', 'Médias')),
                            mediaAddButton,
                        ].filter(Boolean)),
                        mediaErrorNode,
                        mediaListNode,
                    ].filter(Boolean));

                    var notes = Array.isArray(todo && todo.notes) ? todo.notes : [];
                    var noteNodes = notes.map(function (note, index) {
                        if (!note) {
                            return null;
                        }
                        var noteId = note.id !== undefined && note.id !== null ? String(note.id) : 'note-' + index;
                        var author = typeof note.authorName === 'string' ? note.authorName : '';
                        var createdAt = typeof note.createdAt === 'string' ? note.createdAt : '';
                        var content = typeof note.content === 'string' ? note.content : '';
                        var headerChildren = [];
                        if (author) {
                            headerChildren.push(h('span', { className: 'mj-todo-widget__note-author' }, author));
                        }
                        if (createdAt) {
                            headerChildren.push(h('span', { className: 'mj-todo-widget__note-date' }, createdAt));
                        }
                        var noteMemberId = note.memberId !== undefined && note.memberId !== null ? String(note.memberId) : '';
                        var viewerMemberId = viewer && viewer.id !== undefined && viewer.id !== null ? String(viewer.id) : '';
                        var canDeleteNote = preview || (effectiveAccess && viewerMemberId !== '' && noteMemberId !== '' && viewerMemberId === noteMemberId);
                        var noteDeleteKey = todoKey + ':' + noteId;
                        var noteDeletePending = pendingNoteDeletes.has(noteDeleteKey);
                        var noteDeleteButton = null;
                        if (canDeleteNote) {
                            noteDeleteButton = h('button', {
                                type: 'button',
                                className: 'mj-todo-widget__note-delete',
                                onClick: function () {
                                    if (noteDeletePending) {
                                        return;
                                    }
                                    handleDeleteNote(todoId, noteId);
                                },
                                disabled: noteDeletePending,
                            }, noteDeletePending ? getString(i18n, 'noteDeleting', 'Suppression…') : getString(i18n, 'noteDeleteLabel', 'Supprimer'));
                        }
                        var headerInfoNode = headerChildren.length > 0
                            ? h('div', { className: 'mj-todo-widget__note-header-info' }, headerChildren)
                            : null;
                        var headerNode = headerInfoNode || noteDeleteButton
                            ? h('div', { className: 'mj-todo-widget__note-header' }, [headerInfoNode, noteDeleteButton].filter(Boolean))
                            : null;
                        return h('li', { key: noteId, className: 'mj-todo-widget__note' }, [
                            headerNode,
                            h('div', { className: 'mj-todo-widget__note-content' }, renderNoteContent(content)),
                        ]);
                    }).filter(Boolean);

                    var notesList = noteNodes.length > 0
                        ? h('ul', { className: 'mj-todo-widget__notes-list' }, noteNodes)
                        : h('p', { className: 'mj-todo-widget__notes-empty' }, getString(i18n, 'notesEmpty', 'Aucune note pour le moment.'));

                    var isNoteFormOpen = openNoteForms.has(todoKey);
                    var noteDraftValue = noteDrafts.has(todoKey) ? String(noteDrafts.get(todoKey)) : '';
                    var noteErrorMessage = noteErrors.has(todoKey) ? noteErrors.get(todoKey) : '';
                    var notePending = pendingNotes.has(todoKey);
                    var canModifyNotes = effectiveAccess && !isArchived && !isEditing;

                    var noteToggleButton = canModifyNotes
                        ? h('button', {
                            type: 'button',
                            className: 'mj-todo-widget__note-toggle',
                            onClick: function () { return toggleNoteForm(todoId); },
                            disabled: notePending || archivePending || updatePending,
                        }, isNoteFormOpen ? getString(i18n, 'noteCancel', 'Fermer') : getString(i18n, 'noteAdd', 'Ajouter une note'))
                        : null;

                    var noteForm = canModifyNotes && isNoteFormOpen
                        ? h('form', {
                            className: 'mj-todo-widget__note-form' + (notePending ? ' is-submitting' : ''),
                            onSubmit: function (event) {
                                if (event && typeof event.preventDefault === 'function') {
                                    event.preventDefault();
                                }
                                handleSubmitNote(todoId);
                            },
                        }, [
                            h('textarea', {
                                className: 'mj-todo-widget__note-textarea',
                                value: noteDraftValue,
                                onInput: function (event) { return handleNoteDraftChange(todoId, event.target.value); },
                                placeholder: getString(i18n, 'notePlaceholder', 'Écrire une note…'),
                                disabled: notePending,
                                rows: 3,
                            }),
                            noteErrorMessage ? h('p', { className: 'mj-todo-widget__note-error', role: 'alert' }, noteErrorMessage) : null,
                            h('div', { className: 'mj-todo-widget__note-actions' }, [
                                h('button', {
                                    type: 'submit',
                                    className: 'mj-todo-widget__note-submit',
                                    disabled: notePending || noteDraftValue.trim() === '',
                                }, notePending ? getString(i18n, 'noteSubmitting', 'Enregistrement…') : getString(i18n, 'noteSubmit', 'Publier')),
                                h('button', {
                                    type: 'button',
                                    className: 'mj-todo-widget__note-cancel',
                                    onClick: function () {
                                        if (notePending || archivePending) {
                                            return;
                                        }
                                        setNoteDrafts(function (previous) {
                                            var next = new Map(previous);
                                            next.set(todoKey, '');
                                            return next;
                                        });
                                        toggleNoteForm(todoId);
                                    },
                                    disabled: notePending || archivePending,
                                }, getString(i18n, 'noteCancel', 'Fermer')),
                            ]),
                        ])
                        : null;

                    notesSection = h('div', { className: 'mj-todo-widget__notes' }, [
                        h('div', { className: 'mj-todo-widget__notes-header' }, [
                            h('span', { className: 'mj-todo-widget__notes-title' }, getString(i18n, 'notesTitle', 'Notes')),
                            noteToggleButton,
                        ]),
                        notesList,
                        noteForm,
                    ]);
                }

                var metaEntries = [];
                if (statusBadge) {
                    metaEntries.push(statusBadge);
                }
                if (projectLabel) {
                    metaEntries.push(h('span', { className: 'mj-todo-widget__badge', style: projectBadgeStyle }, projectLabel));
                }
                if (dueDisplay) {
                    metaEntries.push(h('span', { className: 'mj-todo-widget__deadline' }, dueDisplay));
                }
                if (isArchived && completedDisplay) {
                    metaEntries.push(h('span', { className: 'mj-todo-widget__archives-date' }, completedDisplay));
                }
                var metaNode = metaEntries.length > 0
                    ? h('div', { className: 'mj-todo-widget__meta' }, metaEntries)
                    : null;

                var priorityMetaRow = null;
                if (!isCollapsed && (priorityNode || metaNode)) {
                    var priorityMetaChildren = [];
                    if (priorityNode) {
                        priorityMetaChildren.push(priorityNode);
                    }
                    if (metaNode) {
                        priorityMetaChildren.push(metaNode);
                    }
                    priorityMetaRow = h('div', { className: 'mj-todo-widget__priority-meta-row' }, priorityMetaChildren);
                }

                var titleDisplay = draftTitle !== '' ? draftTitle : getString(i18n, 'untitled', 'Tâche sans titre');
                var titleFieldId = 'mj-todo-edit-title-' + todoKey;
                var descriptionFieldId = 'mj-todo-edit-description-' + todoKey;
                var editFieldsNode = isEditing
                    ? h('div', { className: 'mj-todo-widget__edit-fields' }, [
                        h('div', { className: 'mj-todo-widget__edit-field' }, [
                            h('label', { className: 'mj-todo-widget__edit-label', htmlFor: titleFieldId }, getString(i18n, 'titleLabel', 'Titre')),
                            h('input', {
                                id: titleFieldId,
                                type: 'text',
                                className: 'mj-todo-widget__edit-input',
                                value: draftTitle,
                                onInput: function (event) { return handleEditDraftChange(todoId, 'title', event.target.value); },
                                disabled: updatePending,
                                placeholder: getString(i18n, 'titleLabel', 'Titre'),
                            }),
                        ]),
                        h('div', { className: 'mj-todo-widget__edit-field' }, [
                            h('label', { className: 'mj-todo-widget__edit-label', htmlFor: descriptionFieldId }, getString(i18n, 'descriptionLabel', 'Description')),
                            h('textarea', {
                                id: descriptionFieldId,
                                className: 'mj-todo-widget__edit-textarea',
                                value: draftDescription,
                                onInput: function (event) { return handleEditDraftChange(todoId, 'description', event.target.value); },
                                disabled: updatePending,
                                rows: 3,
                                placeholder: getString(i18n, 'descriptionPlaceholder', 'Description (optionnel)'),
                            }),
                        ]),
                        editErrorMessage ? h('p', { className: 'mj-todo-widget__edit-error', role: 'alert' }, editErrorMessage) : null,
                        h('div', { className: 'mj-todo-widget__edit-actions' }, [
                            h('button', {
                                type: 'button',
                                className: 'mj-todo-widget__edit-save',
                                onClick: function () { return handleSubmitEdit(todoId); },
                                disabled: updatePending,
                            }, updatePending ? getString(i18n, 'savingLabel', 'Enregistrement…') : getString(i18n, 'saveLabel', 'Enregistrer')),
                            h('button', {
                                type: 'button',
                                className: 'mj-todo-widget__edit-cancel',
                                onClick: function () { return handleCancelEdit(todoId); },
                                disabled: updatePending,
                            }, getString(i18n, 'cancel', 'Annuler')),
                        ]),
                    ].filter(Boolean))
                    : null;

                var itemChildren = [
                    h('div', { className: 'mj-todo-widget__item-main' }, [
                        h('label', { className: 'mj-todo-widget__checkbox-row' }, [
                            h('input', {
                                type: 'checkbox',
                                className: 'mj-todo-widget__checkbox-input',
                                checked: status === 'completed',
                                disabled: isPending || submitting || isArchived || isEditing,
                                onChange: function (event) {
                                    if (isArchived || isEditing) {
                                        event.preventDefault();
                                        event.target.checked = false;
                                        return;
                                    }
                                    handleToggle(todoId, !!event.target.checked);
                                },
                            }),
                            h('span', { className: 'mj-todo-widget__label' }, titleDisplay),
                        ]),
                        actionsNode,
                    ]),
                ];

                if (editFieldsNode) {
                    itemChildren.push(editFieldsNode);
                } else if (!isCollapsed && descriptionNode) {
                    itemChildren.push(descriptionNode);
                }

                if (priorityMetaRow) {
                    itemChildren.push(priorityMetaRow);
                }

                if (!isCollapsed && assigneesGroup) {
                    itemChildren.push(assigneesGroup);
                }

                if (isCollapsed && collapsedSummaryNode) {
                    itemChildren.push(collapsedSummaryNode);
                }

                if (!isCollapsed && mediaSection) {
                    itemChildren.push(mediaSection);
                }

                if (!isCollapsed && notesSection) {
                    itemChildren.push(notesSection);
                }

                return h('li', { key: todoKey, className: itemClasses }, itemChildren);
            }).filter(Boolean);
        };

        var listNode = null;
        if (!loading) {
            var listItems = buildTodoListItems(visibleTodos, {
                projectsMap: projectsMap,
                emptyMessage: getString(i18n, 'empty', 'Aucune tâche pour le moment.'),
            });
            listNode = h('ul', { className: 'mj-todo-widget__list' }, listItems);
        }

        var archivesHeaderNode = h('div', { className: 'mj-todo-widget__archives-header' }, [
            h('h4', { className: 'mj-todo-widget__archives-title' }, getString(i18n, 'archivesTitle', 'Tâches archivées')),
        ]);

        var archivesContent = null;
        if (loadingArchives && !preview) {
            archivesContent = h('div', { className: 'mj-todo-widget__loading mj-todo-widget__loading--archives' }, [
                h('span', { className: 'mj-todo-widget__spinner', 'aria-hidden': 'true' }),
                h('span', { className: 'mj-todo-widget__loading-label' }, getString(i18n, 'archivesLoading', 'Chargement des archives…')),
            ]);
        } else {
            var archivedListItems = buildTodoListItems(visibleArchivedTodos, {
                projectsMap: archivedProjectsMap,
                emptyMessage: getString(i18n, 'archivesEmpty', 'Aucune tâche archivée.'),
            });
            archivesContent = h('ul', { className: 'mj-todo-widget__list' }, archivedListItems);
        }

        var archivesErrorNode = archivesError
            ? h('div', { className: 'mj-todo-widget__archives-error', role: 'alert' }, archivesError)
            : null;

        var archivesNode = h('div', { className: 'mj-todo-widget__archives' }, [
            archivesHeaderNode,
            archivesErrorNode,
            archivesContent,
        ]);

        var isArchivedView = statusFilter === 'archived';

        var bodyNode = loading
            ? null
            : h('div', { className: 'mj-todo-widget__body' }, (function () {
                var parts = [];
                if (effectiveAccess && createButton) {
                    parts.push(createButton);
                }
                parts.push(filtersNode);
                if (projectFeedbackAlert) {
                    parts.push(projectFeedbackAlert);
                }
                if (projectFormNode) {
                    parts.push(projectFormNode);
                }
                if (!isArchivedView) {
                    parts.push(listNode);
                }
                return parts;
            })());

        return h(Fragment, null, [
            header,
            createModal,
            !isArchivedView && error ? h('div', { className: 'mj-todo-widget__error' }, error) : null,
            !isArchivedView ? loader : null,
            bodyNode,
            isArchivedView ? archivesNode : null,
        ]);
    }

    function mount(element) {
        if (!element) {
            return;
        }

        if (typeof element.__mjTodoUnmount === 'function') {
            element.__mjTodoUnmount();
        }

        var datasetConfig = parseDatasetConfig(element);
        render(h(TodoApp, { datasetConfig: datasetConfig, runtime: runtimeConfig }), element);

        element.__mjTodoUnmount = function () {
            render(null, element);
            delete element.__mjTodoUnmount;
        };
    }

    function initScope(scope) {
        if (!scope) {
            return;
        }

        var elements = [];
        if (scope.nodeType === 1 && scope.hasAttribute('data-mj-member-todo-widget')) {
            elements.push(scope);
        }

        var descendants = scope.querySelectorAll ? scope.querySelectorAll('[data-mj-member-todo-widget]') : [];
        for (var i = 0; i < descendants.length; i += 1) {
            elements.push(descendants[i]);
        }

        elements.forEach(mount);
    }

    domReady(function () {
        initScope(document);
    });

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-todo.default', function (scope) {
            var target = scope && scope[0] ? scope[0] : scope;
            if (target) {
                initScope(target);
            }
        });
    }
})();
