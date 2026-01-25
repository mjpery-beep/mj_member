/**
 * Registration Manager - Tabs Helpers
 * Fonctions utilitaires pour les onglets dynamiques
 */

(function (global) {
    'use strict';

    var preact = global.preact;
    var Utils = global.MjRegMgrUtils;

    if (!preact || !Utils) {
        console.warn('[MjRegMgr] Dépendances manquantes pour tabs.js');
        return;
    }

    var h = preact.h;
    var getString = Utils.getString;
    var classNames = Utils.classNames;
    var OCCURRENCE_TAB_KEY = 'occurrence-encoder';

    function Tabs(props) {
        var originalTabs = Array.isArray(props.tabs) ? props.tabs.slice() : [];
        var fallbackRegistrationsTab = props.fallbackRegistrationsTab;
        var activeTab = props.activeTab;
        var onChange = props.onChange;
        var shouldEnsureRegistrationsTab = props.ensureRegistrationsTab !== false;

        var tabs = originalTabs;
        var hasRegistrationsTab = tabs.some(function (tab) {
            return tab && tab.key === 'registrations';
        });

        if (shouldEnsureRegistrationsTab && !hasRegistrationsTab) {
            var fallback = fallbackRegistrationsTab || {
                key: 'registrations',
                label: 'Inscriptions',
                badge: 0,
            };

            tabs = [fallback].concat(tabs);
        }

        return h('div', { class: 'mj-regmgr-tabs', role: 'tablist' },
            tabs.map(function (tab) {
                return h('button', {
                    key: tab.key,
                    type: 'button',
                    class: classNames('mj-regmgr-tab', {
                        'mj-regmgr-tab--active': activeTab === tab.key,
                    }),
                    role: 'tab',
                    'aria-selected': activeTab === tab.key ? 'true' : 'false',
                    'aria-label': tab.label,
                    title: tab.label,
                    onClick: function () {
                        if (typeof onChange === 'function') {
                            onChange(tab.key);
                        }
                    },
                }, [
                    tab.icon && h('span', {
                        class: 'mj-regmgr-tab__icon',
                        'aria-hidden': 'true',
                        dangerouslySetInnerHTML: { __html: tab.icon },
                    }),
                    h('span', { class: 'mj-regmgr-tab__label', 'aria-hidden': 'true' }, tab.label),
                    tab.badge !== undefined && h('span', { class: 'mj-regmgr-tab__badge' }, tab.badge),
                ]);
            })
        );
    }

    function ensureRegistrationsTab(tabs, fallback) {
        var fallbackLabel = fallback && fallback.label ? fallback.label : 'Inscriptions';
        var fallbackBadge = fallback && typeof fallback.badge === 'number' ? fallback.badge : 0;

        var safeTabs = Array.isArray(tabs) ? tabs.filter(function (tab) { return !!tab; }) : [];
        var existingIndex = -1;

        for (var i = 0; i < safeTabs.length; i++) {
            var tab = safeTabs[i];
            if (tab && tab.key === 'registrations') {
                existingIndex = i;
                break;
            }
        }

        if (existingIndex !== -1) {
            var current = safeTabs[existingIndex];
            var normalized = Object.assign({}, current, {
                key: 'registrations',
                label: current && current.label ? current.label : fallbackLabel,
            });

            if (normalized.badge === undefined) {
                normalized.badge = fallbackBadge;
            }

            safeTabs[existingIndex] = normalized;
            return safeTabs;
        }

        var fallbackTab = {
            key: 'registrations',
            label: fallbackLabel,
            badge: fallbackBadge,
        };

        safeTabs.unshift(fallbackTab);
        return safeTabs;
    }

    function createOccurrenceTab(strings, overrides) {
        var label = getString(strings, 'tabOccurrenceEncoder', 'Occurence de date');
        var base = {
            key: OCCURRENCE_TAB_KEY,
            label: label,
        };

        if (overrides && typeof overrides === 'object') {
            return Object.assign({}, base, overrides);
        }

        return base;
    }

    function isOccurrenceTabKey(tabKey) {
        return tabKey === OCCURRENCE_TAB_KEY;
    }

    var exported = global.MjRegMgrTabs || {};
    exported.OCCURRENCE_TAB_KEY = OCCURRENCE_TAB_KEY;
    exported.createOccurrenceTab = createOccurrenceTab;
    exported.isOccurrenceTabKey = isOccurrenceTabKey;
    exported.Tabs = Tabs;
    exported.ensureRegistrationsTab = ensureRegistrationsTab;

    global.MjRegMgrTabs = exported;
})(typeof window !== 'undefined' ? window : this);
