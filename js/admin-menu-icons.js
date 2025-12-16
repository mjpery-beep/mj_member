(function ($) {
    'use strict';

    function setPreview($preview, url) {
        var placeholder = mjMemberMenuIcons ? mjMemberMenuIcons.placeholder : '';
        if (url) {
            $preview.html('<img src="' + url + '" alt="" class="mj-member-menu-icon-preview-image" />');
        } else {
            $preview.html('<span class="mj-member-menu-icon-placeholder">' + placeholder + '</span>');
        }
    }

    function initField($container) {
        if (!$container || !$container.length) {
            return;
        }

        var $input = $container.find('.mj-member-menu-icon-input');
        var $preview = $container.find('.mj-member-menu-icon-preview');
        var $select = $container.find('.mj-member-menu-icon-select');
        var $remove = $container.find('.mj-member-menu-icon-remove');
        var currentUrl = $preview.data('image-url') || '';
        var frame = null;

        function updateState(url, id) {
            setPreview($preview, url);
            $preview.data('image-url', url || '');
            $input.val(id ? id : '');

            if (url) {
                $remove.show();
                if (mjMemberMenuIcons && mjMemberMenuIcons.replace) {
                    $select.text(mjMemberMenuIcons.replace);
                }
            } else {
                $remove.hide();
                if (mjMemberMenuIcons && mjMemberMenuIcons.choose) {
                    $select.text(mjMemberMenuIcons.choose);
                }
            }
        }

        $select.on('click', function (event) {
            event.preventDefault();

            if (typeof wp === 'undefined' || typeof wp.media === 'undefined') {
                return;
            }

            if (frame) {
                frame.open();
                return;
            }

            frame = wp.media({
                title: mjMemberMenuIcons ? mjMemberMenuIcons.modalTitle : '',
                button: {
                    text: mjMemberMenuIcons ? mjMemberMenuIcons.modalButton : ''
                },
                library: {
                    type: 'image'
                },
                multiple: false
            });

            frame.on('select', function () {
                var selection = frame.state().get('selection');
                var attachment = selection.first();

                if (!attachment) {
                    return;
                }

                attachment = attachment.toJSON();

                var id = attachment.id || '';
                var url = '';

                if (attachment.sizes && attachment.sizes.thumbnail) {
                    url = attachment.sizes.thumbnail.url;
                }

                if (!url && attachment.url) {
                    url = attachment.url;
                }

                updateState(url, id);
            });

            frame.open();
        });

        $remove.on('click', function (event) {
            event.preventDefault();
            updateState('', '');
        });

        updateState(currentUrl, $input.val());
    }

    function initAll() {
        $('[data-mj-member-menu-icon]').each(function () {
            initField($(this));
        });
    }

    $(document).ready(function () {
        initAll();

        $(document).on('menu-item-added', function (event, menuItem) {
            var $item = $(menuItem);
            if (!$item.length) {
                return;
            }
            var $field = $item.find('[data-mj-member-menu-icon]');
            initField($field);
        });
    });
})(jQuery);
