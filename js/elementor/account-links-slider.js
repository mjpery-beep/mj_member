(function () {
    'use strict';

    function initSlider(root) {
        if (!root || root.dataset.mjSliderInit === '1') {
            return;
        }
        root.dataset.mjSliderInit = '1';

        var track = root.querySelector('.mj-member-account-links-slider__track');
        var slides = Array.prototype.slice.call(root.querySelectorAll('.mj-member-account-links-slider__slide'));
        var bullets = Array.prototype.slice.call(root.querySelectorAll('.mj-member-account-links-slider__bullet'));
        var prevBtn = root.querySelector('.mj-member-account-links-slider__arrow--prev');
        var nextBtn = root.querySelector('.mj-member-account-links-slider__arrow--next');

        if (!track || slides.length === 0) {
            return;
        }

        var current = 0;
        var autoplayEnabled = root.dataset.autoplay === '1';
        var autoplayDelay = parseInt(root.dataset.autoplayDelay, 10) || 5000;
        var autoplayTimer = null;

        function goTo(index) {
            if (index < 0) {
                index = slides.length - 1;
            } else if (index >= slides.length) {
                index = 0;
            }

            current = index;
            track.style.transform = 'translateX(-' + (index * 100) + '%)';

            slides.forEach(function (slide, i) {
                slide.classList.toggle('is-active', i === index);
            });
            bullets.forEach(function (bullet, i) {
                bullet.classList.toggle('is-active', i === index);
            });
        }

        function stopAutoplay() {
            if (autoplayTimer) {
                window.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        }

        function startAutoplay() {
            if (!autoplayEnabled || slides.length < 2) {
                return;
            }
            stopAutoplay();
            autoplayTimer = window.setInterval(function () {
                goTo(current + 1);
            }, autoplayDelay);
        }

        bullets.forEach(function (bullet) {
            bullet.addEventListener('click', function () {
                var index = parseInt(bullet.getAttribute('data-slide-index'), 10) || 0;
                goTo(index);
                startAutoplay();
            });
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function () {
                goTo(current - 1);
                startAutoplay();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function () {
                goTo(current + 1);
                startAutoplay();
            });
        }

        root.addEventListener('mouseenter', stopAutoplay);
        root.addEventListener('mouseleave', startAutoplay);
        root.addEventListener('focusin', stopAutoplay);
        root.addEventListener('focusout', startAutoplay);

        var touchStartX = null;
        var stage = root.querySelector('.mj-member-account-links-slider__stage');
        if (stage) {
            stage.addEventListener('touchstart', function (event) {
                touchStartX = event.touches && event.touches.length ? event.touches[0].clientX : null;
                stopAutoplay();
            }, { passive: true });

            stage.addEventListener('touchend', function (event) {
                if (touchStartX === null) {
                    return;
                }
                var touchEndX = event.changedTouches && event.changedTouches.length ? event.changedTouches[0].clientX : touchStartX;
                var delta = touchEndX - touchStartX;
                if (Math.abs(delta) > 40) {
                    goTo(delta < 0 ? current + 1 : current - 1);
                }
                touchStartX = null;
                startAutoplay();
            }, { passive: true });
        }

        goTo(0);
        startAutoplay();
    }

    function initAll() {
        var roots = document.querySelectorAll('.mj-member-account-links-slider');
        for (var i = 0; i < roots.length; i++) {
            initSlider(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    document.addEventListener('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-account-links-slider.default', function ($scope) {
                var el = $scope && $scope[0] ? $scope[0] : $scope;
                if (el) {
                    var found = el.querySelector ? el.querySelector('.mj-member-account-links-slider') : null;
                    initSlider(found || el);
                }
            });
        }
    });
})();
