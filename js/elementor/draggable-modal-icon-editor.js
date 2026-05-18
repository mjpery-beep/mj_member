/* global elementor, elementorCommon */
(function () {
    'use strict';

    function registerDraggableModalIconType() {
        if (!window.elementor || !elementor.modules || !elementor.modules.elements || !elementor.modules.elements.types) {
            return;
        }

        var NestedElementBase = elementor.modules.elements.types.NestedElementBase;

        function MjDraggableModalIconType() {
            return Reflect.construct(NestedElementBase, arguments, MjDraggableModalIconType);
        }

        MjDraggableModalIconType.prototype = Object.create(NestedElementBase.prototype, {
            constructor: { value: MjDraggableModalIconType, writable: true, configurable: true },
        });
        Object.setPrototypeOf(MjDraggableModalIconType, NestedElementBase);

        MjDraggableModalIconType.prototype.getType = function () {
            return 'mj-member-draggable-modal-icon';
        };

        elementor.elementsManager.registerElementType(new MjDraggableModalIconType());
    }

    elementorCommon.elements.$window.on('elementor/nested-element-type-loaded', registerDraggableModalIconType);
})();
