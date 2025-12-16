/**
 * Helper utilities partagés entre les scripts MJ Member.
 */

export function escapeHtml(value) {
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

export function toInt(value, fallback = null) {
    var parsed = parseInt(value, 10);
    if (isNaN(parsed)) {
        return fallback;
    }

    return parsed;
}

export function createAssignedLookup(source) {
    var lookup = {};
    if (source === null || source === undefined) {
        return lookup;
    }

    if (Array.isArray(source)) {
        for (var i = 0; i < source.length; i += 1) {
            var arrayId = toInt(source[i]);
            if (arrayId !== null) {
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
                if (mapId !== null) {
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
            if (objId !== null) {
                lookup[objId] = true;
            }
        }
        return lookup;
    }

    var scalarId = toInt(source);
    if (scalarId !== null) {
        lookup[scalarId] = true;
    }

    return lookup;
}

export function flagSummaryAssignments(summaries, assignedSource) {
    if (!Array.isArray(summaries) || summaries.length === 0) {
        return summaries || [];
    }

    var lookup = createAssignedLookup(assignedSource);
    var hasAssigned = Object.keys(lookup).length > 0;
    if (!hasAssigned) {
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

export function domReady(callback) {
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

export function toArray(collection) {
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
