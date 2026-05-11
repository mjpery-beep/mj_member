/* global elementor, elementorCommon, $e */
(function () {
    'use strict';

    /**
     * Écoute le signal « nested-element-type-loaded » émis par le module
     * nested-elements d'Elementor. À ce moment, NestedElementBase et
     * NestedView sont garantis disponibles.
     *
     * Sans ce bloc, Elementor utilise le modèle/vue par défaut et
     * onElementCreate() n'est jamais appelé → aucun container enfant créé
     * au dépôt du widget → Navigator vide.
     *
     * NOTE: NestedElementBase est une classe ES6 compilée par Babel. On ne
     * peut pas appeler `.apply(this, args)` sur elle (erreur "Class constructor
     * cannot be invoked without 'new'"). On utilise Reflect.construct() pour
     * reproduire exactement ce que fait Babel avec _callSuper().
     */
    function registerMjDockTabsType() {
        var NestedElementBase = elementor.modules.elements.types.NestedElementBase;

        /**
         * Constructeur utilisant Reflect.construct pour appeler correctement
         * le super constructeur de la classe ES6 NestedElementBase.
         */
        function MjDockTabsType() {
            return Reflect.construct(NestedElementBase, arguments, MjDockTabsType);
        }

        // Chaîne prototype (même pattern que Babel _inherits)
        MjDockTabsType.prototype = Object.create(NestedElementBase.prototype, {
            constructor: { value: MjDockTabsType, writable: true, configurable: true },
        });
        Object.setPrototypeOf(MjDockTabsType, NestedElementBase);

        MjDockTabsType.prototype.getType = function () {
            return 'mj-member-dock-tabs';
        };

        elementor.elementsManager.registerElementType(new MjDockTabsType());
    }

    elementorCommon.elements.$window.on(
        'elementor/nested-element-type-loaded',
        registerMjDockTabsType
    );
})();
