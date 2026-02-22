/**
 * Registration Manager - Custom Hooks & Utilities
 * Hooks Preact réutilisables et fonctions utilitaires
 */

(function (global) {
    'use strict';

    var hooks = global.preactHooks;
    if (!hooks) {
        console.warn('[MjRegMgr] preactHooks non disponible');
        return;
    }

    var useState = hooks.useState;
    var useEffect = hooks.useEffect;
    var useCallback = hooks.useCallback;
    var useRef = hooks.useRef;
    var useMemo = hooks.useMemo;

    // ============================================
    // UTILITAIRES
    // ============================================

    /**
     * Échappe le HTML
     */
    function escapeHtml(text) {
        if (typeof text !== 'string') return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Décode les entités HTML
     */
    function decodeHtml(html) {
        if (typeof html !== 'string') return '';
        var div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || div.innerText || '';
    }

    /**
     * Formate une date
     */
    function formatDate(dateStr, withTime) {
        if (!dateStr) return '';
        var date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;

        var day = String(date.getDate()).padStart(2, '0');
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var year = date.getFullYear();

        var result = day + '/' + month + '/' + year;

        if (withTime) {
            var hours = String(date.getHours()).padStart(2, '0');
            var minutes = String(date.getMinutes()).padStart(2, '0');
            result += ' ' + hours + ':' + minutes;
        }

        return result;
    }

    /**
     * Formate une date courte (jour + mois)
     */
    function formatShortDate(dateStr) {
        if (!dateStr) return '';
        var date = new Date(dateStr);
        if (isNaN(date.getTime())) return dateStr;

        var days = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
        var months = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];

        return days[date.getDay()] + ' ' + date.getDate() + ' ' + months[date.getMonth()];
    }

    /**
     * Formate un temps écoulé relatif (ex: "2h", "3j", "1sem")
     */
    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        var date = new Date(dateStr);
        if (isNaN(date.getTime())) return '';

        var now = new Date();
        var diffMs = now.getTime() - date.getTime();
        if (diffMs < 0) return '';

        var diffSec = Math.floor(diffMs / 1000);
        var diffMin = Math.floor(diffSec / 60);
        var diffHour = Math.floor(diffMin / 60);
        var diffDay = Math.floor(diffHour / 24);
        var diffWeek = Math.floor(diffDay / 7);
        var diffMonth = Math.floor(diffDay / 30);

        if (diffMin < 1) return diffSec + 's';
        if (diffMin < 60) return diffMin + 'min';
        if (diffHour < 24) return diffHour + 'h';
        if (diffDay < 7) return diffDay + 'j';
        if (diffWeek < 4) return diffWeek + 'sem';
        return diffMonth + 'mois';
    }

    /**
     * Extrait les initiales d'un nom
     */
    function getInitials(name) {
        if (!name || typeof name !== 'string') return '?';
        var parts = name.trim().split(/\s+/);
        var initials = '';
        for (var i = 0; i < Math.min(parts.length, 2); i++) {
            if (parts[i].length > 0) {
                initials += parts[i].charAt(0).toUpperCase();
            }
        }
        return initials || '?';
    }

    /**
     * Génère une couleur à partir d'une chaîne
     */
    function stringToColor(str) {
        if (!str) return '#6b7280';
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = str.charCodeAt(i) + ((hash << 5) - hash);
        }
        var colors = [
            '#ef4444', '#f97316', '#f59e0b', '#84cc16',
            '#22c55e', '#14b8a6', '#06b6d4', '#3b82f6',
            '#6366f1', '#8b5cf6', '#a855f7', '#ec4899'
        ];
        return colors[Math.abs(hash) % colors.length];
    }

    /**
     * Debounce une fonction
     */
    function debounce(fn, delay) {
        var timer = null;
        return function () {
            var context = this;
            var args = arguments;
            if (timer) clearTimeout(timer);
            timer = setTimeout(function () {
                fn.apply(context, args);
            }, delay);
        };
    }

    /**
     * Classe CSS conditionnelle
     */
    function classNames() {
        var classes = [];
        for (var i = 0; i < arguments.length; i++) {
            var arg = arguments[i];
            if (!arg) continue;
            if (typeof arg === 'string') {
                classes.push(arg);
            } else if (typeof arg === 'object') {
                Object.keys(arg).forEach(function (key) {
                    if (arg[key]) classes.push(key);
                });
            }
        }
        return classes.join(' ');
    }

    /**
     * Récupère une traduction
     */
    function getString(strings, key, fallback) {
        if (strings && typeof strings === 'object' && strings[key]) {
            return strings[key];
        }
        return fallback || key;
    }

    /**
     * Construit un lien WhatsApp à partir d'un numéro
     */
    function buildWhatsAppLink(phone) {
        if (!phone || typeof phone !== 'string') {
            return '';
        }

        var digits = phone.replace(/\D+/g, '');
        if (!digits || digits.length < 6) {
            return '';
        }

        if (digits.indexOf('00') === 0) {
            digits = digits.slice(2);
        }

        return 'https://wa.me/' + digits;
    }

    // ============================================
    // HOOKS PERSONNALISÉS
    // ============================================

    /**
     * Hook pour gérer le chargement async
     */
    function useAsync(asyncFn, deps) {
        var _state = useState({ loading: false, data: null, error: null });
        var state = _state[0];
        var setState = _state[1];
        var mountedRef = useRef(true);

        useEffect(function () {
            mountedRef.current = true;
            return function () {
                mountedRef.current = false;
            };
        }, []);

        var execute = useCallback(function () {
            setState({ loading: true, data: null, error: null });

            return asyncFn()
                .then(function (data) {
                    if (mountedRef.current) {
                        setState({ loading: false, data: data, error: null });
                    }
                    return data;
                })
                .catch(function (error) {
                    if (error.aborted) return;
                    if (mountedRef.current) {
                        setState({ loading: false, data: null, error: error });
                    }
                    throw error;
                });
        }, deps || []);

        return {
            loading: state.loading,
            data: state.data,
            error: state.error,
            execute: execute,
        };
    }

    /**
     * Hook pour la recherche avec debounce
     */
    function useSearch(searchFn, delay) {
        var _query = useState('');
        var query = _query[0];
        var setQuery = _query[1];

        var _results = useState([]);
        var results = _results[0];
        var setResults = _results[1];

        var _loading = useState(false);
        var loading = _loading[0];
        var setLoading = _loading[1];

        var timeoutRef = useRef(null);

        var search = useCallback(function (value) {
            setQuery(value);

            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }

            if (!value || value.length < 2) {
                setResults([]);
                setLoading(false);
                return;
            }

            setLoading(true);

            timeoutRef.current = setTimeout(function () {
                searchFn(value)
                    .then(function (data) {
                        setResults(data);
                        setLoading(false);
                    })
                    .catch(function () {
                        setLoading(false);
                    });
            }, delay || 300);
        }, [searchFn, delay]);

        var clear = useCallback(function () {
            setQuery('');
            setResults([]);
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        }, []);

        return {
            query: query,
            results: results,
            loading: loading,
            search: search,
            clear: clear,
        };
    }

    /**
     * Hook pour gérer les modals
     */
    function useModal() {
        var _isOpen = useState(false);
        var isOpen = _isOpen[0];
        var setIsOpen = _isOpen[1];

        var _data = useState(null);
        var data = _data[0];
        var setData = _data[1];

        var open = useCallback(function (modalData) {
            setData(modalData || null);
            setIsOpen(true);
        }, []);

        var close = useCallback(function () {
            setIsOpen(false);
            setData(null);
        }, []);

        return {
            isOpen: isOpen,
            data: data,
            open: open,
            close: close,
        };
    }

    /**
     * Hook pour les toasts/notifications
     */
    function useToasts() {
        var _toasts = useState([]);
        var toasts = _toasts[0];
        var setToasts = _toasts[1];

        var idRef = useRef(0);

        var addToast = useCallback(function (message, type, duration) {
            var id = ++idRef.current;
            var toast = {
                id: id,
                message: message,
                type: type || 'info',
            };

            setToasts(function (prev) {
                return prev.concat([toast]);
            });

            setTimeout(function () {
                setToasts(function (prev) {
                    return prev.filter(function (t) {
                        return t.id !== id;
                    });
                });
            }, duration || 4000);

            return id;
        }, []);

        var removeToast = useCallback(function (id) {
            setToasts(function (prev) {
                return prev.filter(function (t) {
                    return t.id !== id;
                });
            });
        }, []);

        var success = useCallback(function (message) {
            return addToast(message, 'success');
        }, [addToast]);

        var error = useCallback(function (message) {
            return addToast(message, 'error', 6000);
        }, [addToast]);

        var info = useCallback(function (message) {
            return addToast(message, 'info');
        }, [addToast]);

        return {
            toasts: toasts,
            addToast: addToast,
            removeToast: removeToast,
            success: success,
            error: error,
            info: info,
        };
    }

    /**
     * Hook pour la sélection multiple
     */
    function useSelection() {
        var _selected = useState([]);
        var selected = _selected[0];
        var setSelected = _selected[1];

        var toggle = useCallback(function (id) {
            setSelected(function (prev) {
                var index = prev.indexOf(id);
                if (index === -1) {
                    return prev.concat([id]);
                }
                return prev.filter(function (item) {
                    return item !== id;
                });
            });
        }, []);

        var isSelected = useCallback(function (id) {
            return selected.indexOf(id) !== -1;
        }, [selected]);

        var selectAll = useCallback(function (ids) {
            setSelected(ids);
        }, []);

        var clear = useCallback(function () {
            setSelected([]);
        }, []);

        return {
            selected: selected,
            toggle: toggle,
            isSelected: isSelected,
            selectAll: selectAll,
            clear: clear,
            count: selected.length,
        };
    }

    /**
     * Hook pour le stockage local
     */
    function useLocalStorage(key, initialValue) {
        var _value = useState(function () {
            try {
                var item = window.localStorage.getItem(key);
                return item ? JSON.parse(item) : initialValue;
            } catch (e) {
                return initialValue;
            }
        });
        var value = _value[0];
        var setValue = _value[1];

        var setStoredValue = useCallback(function (newValue) {
            try {
                var valueToStore = typeof newValue === 'function' ? newValue(value) : newValue;
                setValue(valueToStore);
                window.localStorage.setItem(key, JSON.stringify(valueToStore));
            } catch (e) {
                console.error('useLocalStorage error:', e);
            }
        }, [key, value]);

        return [value, setStoredValue];
    }

    // ============================================
    // EXPORT
    // ============================================

    global.MjRegMgrUtils = {
        // Utilitaires
        escapeHtml: escapeHtml,
        decodeHtml: decodeHtml,
        formatDate: formatDate,
        formatShortDate: formatShortDate,
        formatTimeAgo: formatTimeAgo,
        getInitials: getInitials,
        stringToColor: stringToColor,
        debounce: debounce,
        classNames: classNames,
        getString: getString,
        buildWhatsAppLink: buildWhatsAppLink,

        // Hooks
        useAsync: useAsync,
        useSearch: useSearch,
        useModal: useModal,
        useToasts: useToasts,
        useSelection: useSelection,
        useLocalStorage: useLocalStorage,
    };

})(window);
