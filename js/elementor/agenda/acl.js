/**
 * Agenda widget — client-side ACL mirror.
 *
 * UI-only: greys out actions the current role may not use. The server
 * (MjAgendaAcl::assert) is always authoritative.
 *
 * Exposed as window.MjAgendaAcl.
 */
(function (global) {
    'use strict';

    var ACTIONS = ['view', 'view_others', 'create', 'edit_own', 'edit_others', 'move', 'delete'];

    function createAcl(snapshot) {
        var role = (snapshot && snapshot.role) || 'jeune';
        var layers = (snapshot && snapshot.layers) || {};

        function grants(layer) {
            return (layers && layers[layer]) || [];
        }

        function can(layer, action, isOwner) {
            var g = grants(layer);
            if (ACTIONS.indexOf(action) === -1) {
                return false;
            }
            if (action === 'move' || action === 'delete') {
                if (g.indexOf(action) === -1) {
                    return false;
                }
                return isOwner ? g.indexOf('edit_own') !== -1 : g.indexOf('edit_others') !== -1;
            }
            return g.indexOf(action) !== -1;
        }

        return {
            role: role,
            can: can,
            canViewLayer: function (layer) { return can(layer, 'view'); },
            canCreate: function (layer) { return can(layer, 'create'); },
            visibleLayers: function (requested) {
                return (requested || Object.keys(layers)).filter(function (l) {
                    return can(l, 'view');
                });
            },
            creatableLayers: function (requested) {
                return (requested || Object.keys(layers)).filter(function (l) {
                    return can(l, 'create');
                });
            }
        };
    }

    global.MjAgendaAcl = { createAcl: createAcl };
})(window);
