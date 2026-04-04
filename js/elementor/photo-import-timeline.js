(function () {
    'use strict';

    function initTimeline(root) {
        var cards = Array.prototype.slice.call(root.querySelectorAll('[data-mj-photo-open]'));
        if (!cards.length) {
            return;
        }

        var modal = root.querySelector('[data-mj-photo-modal]');
        var image = root.querySelector('[data-mj-photo-modal-image]');
        var titleNode = root.querySelector('[data-mj-photo-modal-title]');
        var dateNode = root.querySelector('[data-mj-photo-modal-date]');
        var closeTriggers = root.querySelectorAll('[data-mj-photo-close]');
        var prev = root.querySelector('[data-mj-photo-prev]');
        var next = root.querySelector('[data-mj-photo-next]');
        var currentIndex = 0;

        if (!modal || !image) {
            return;
        }

        function render(index) {
            if (!cards[index]) {
                return;
            }
            currentIndex = index;
            var card = cards[index];
            var full = card.getAttribute('data-full') || '';
            var title = card.getAttribute('data-title') || '';
            var date = card.getAttribute('data-date') || '';

            image.src = full;
            image.alt = title;

            if (titleNode) {
                titleNode.textContent = title;
            }
            if (dateNode) {
                dateNode.textContent = date;
            }
        }

        function open(index) {
            render(index);
            modal.hidden = false;
            document.body.classList.add('mj-photo-timeline-modal-open');
        }

        function close() {
            modal.hidden = true;
            image.src = '';
            document.body.classList.remove('mj-photo-timeline-modal-open');
        }

        function move(step) {
            var target = currentIndex + step;
            if (target < 0) {
                target = cards.length - 1;
            }
            if (target >= cards.length) {
                target = 0;
            }
            render(target);
        }

        cards.forEach(function (card, index) {
            card.addEventListener('click', function () {
                open(index);
            });
        });

        Array.prototype.forEach.call(closeTriggers, function (trigger) {
            trigger.addEventListener('click', close);
        });

        if (prev) {
            prev.addEventListener('click', function () {
                move(-1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                move(1);
            });
        }

        document.addEventListener('keydown', function (event) {
            if (modal.hidden) {
                return;
            }
            if (event.key === 'Escape') {
                close();
                return;
            }
            if (event.key === 'ArrowLeft') {
                move(-1);
                return;
            }
            if (event.key === 'ArrowRight') {
                move(1);
            }
        });
    }

    function bootstrap() {
        var roots = document.querySelectorAll('[data-mj-photo-timeline]');
        Array.prototype.forEach.call(roots, initTimeline);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap, { once: true });
    } else {
        bootstrap();
    }
})();
