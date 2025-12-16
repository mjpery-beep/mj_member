/**
 * Fichier bundle généré depuis js/modules/utils/index.js.
 * Expose l'API globale window.MjMemberUtils pour les scripts legacy.
 */
(function (global) {
    'use strict';

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }

        return String(value).replace(/[&<>"']/g, function (match) {
            switch (match) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case "'":
                    return '&#039;';
                default:
                    return match;
            }
        });
    }

    function toInt(value, fallback) {
        var parsed = parseInt(value, 10);
        if (isNaN(parsed)) {
            return typeof fallback === 'number' ? fallback : (fallback === null ? null : fallback);
        }

        return parsed;
    }

    function createAssignedLookup(source) {
        var lookup = {};
        if (source === null || source === undefined) {
            return lookup;
        }

        if (Array.isArray(source)) {
            for (var i = 0; i < source.length; i += 1) {
                var arrayId = toInt(source[i]);
                if (arrayId !== null && arrayId !== undefined) {
                    lookup[arrayId] = true;
                }
            }
            return lookup;
        }

        if (typeof source === 'object') {
            if (source instanceof Map) {
                source.forEach(function (value, key) {
                    if (!value) {
                        return;
                    }
                    var mapId = toInt(key);
                    if (mapId !== null && mapId !== undefined) {
                        lookup[mapId] = true;
                    }
                });
                return lookup;
            }

            var keys = Object.keys(source);
            for (var j = 0; j < keys.length; j += 1) {
                var key = keys[j];
                if (!source[key]) {
                    continue;
                }
                var objId = toInt(key);
                if (objId !== null && objId !== undefined) {
                    lookup[objId] = true;
                }
            }
            return lookup;
        }

        var scalarId = toInt(source);
        if (scalarId !== null && scalarId !== undefined) {
            lookup[scalarId] = true;
        }

        return lookup;
    }

    function flagSummaryAssignments(summaries, assignedSource) {
        if (!Array.isArray(summaries) || summaries.length === 0) {
            return summaries || [];
        }

        var lookup = createAssignedLookup(assignedSource);
        if (!lookup || Object.keys(lookup).length === 0) {
            return summaries;
        }

        for (var i = 0; i < summaries.length; i += 1) {
            var summary = summaries[i];
            var id = summary && summary.id !== undefined ? toInt(summary.id) : null;
            if (id === null) {
                continue;
            }
            if (lookup[id]) {
                summary.assigned = true;
            }
        }

        return summaries;
    }

    function domReady(callback) {
        if (typeof callback !== 'function') {
            return;
        }

        if (typeof document === 'undefined') {
            callback();
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    }

    function toArray(collection) {
        if (!collection) {
            return [];
        }

        if (Array.isArray(collection)) {
            return collection.slice();
        }

        try {
            return Array.prototype.slice.call(collection);
        } catch (error) {
            var result = [];
            for (var i = 0; i < collection.length; i += 1) {
                result.push(collection[i]);
            }
            return result;
        }
    }

    var utils = {
        escapeHtml: escapeHtml,
        toInt: function (value, fallback) {
            return toInt(value, fallback === undefined ? null : fallback);
        },
        createAssignedLookup: createAssignedLookup,
        flagSummaryAssignments: flagSummaryAssignments,
        domReady: domReady,
        toArray: toArray,
    };

    if (typeof module === 'object' && typeof module.exports !== 'undefined') {
        module.exports = utils;
    }

    var target = global.MjMemberUtils ? Object.assign({}, global.MjMemberUtils, utils) : utils;
    global.MjMemberUtils = target;

})(typeof window !== 'undefined' ? window : (typeof global !== 'undefined' ? global : this));
