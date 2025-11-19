(function (blocks, element, components, blockEditor, i18n) {
    'use strict';

    var registerBlockType = blocks.registerBlockType;
    var Fragment = element.Fragment;
    var el = element.createElement;
    var InspectorControls = blockEditor.InspectorControls;
    var BlockControls = blockEditor.BlockControls;
    var useBlockProps = blockEditor.useBlockProps;
    var AlignmentToolbar = blockEditor.AlignmentToolbar;
    var PanelBody = components.PanelBody;
    var TextControl = components.TextControl;
    var TextareaControl = components.TextareaControl;

    registerBlockType('mj-member/login-button', {
        title: i18n.__('Bouton Connexion MJ', 'mj-member'),
        description: i18n.__('Affiche un bouton qui ouvre une fenêtre de connexion pour les membres MJ.', 'mj-member'),
        icon: 'admin-users',
        category: 'widgets',
        supports: {
            html: false
        },
        attributes: {
            loginLabel: {
                type: 'string',
                default: i18n.__('Se connecter', 'mj-member')
            },
            accountLabel: {
                type: 'string',
                default: i18n.__('Accéder à mon compte', 'mj-member')
            },
            modalTitle: {
                type: 'string',
                default: i18n.__('Connexion à mon compte', 'mj-member')
            },
            modalDescription: {
                type: 'string',
                default: ''
            },
            modalButtonLabel: {
                type: 'string',
                default: i18n.__('Connexion', 'mj-member')
            },
            registrationLinkLabel: {
                type: 'string',
                default: i18n.__('Pas encore de compte ? Inscrivez-vous', 'mj-member')
            },
            redirect: {
                type: 'string',
                default: ''
            },
            alignment: {
                type: 'string',
                default: ''
            }
        },
        edit: function (props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var blockProps = useBlockProps({
                className: 'mj-member-login-block'
            });

            var defaultRedirect = '';
            if (typeof window !== 'undefined' && window.mjMemberLoginDefaults && window.mjMemberLoginDefaults.redirect) {
                defaultRedirect = window.mjMemberLoginDefaults.redirect;
            }

            return el(Fragment, {},
                el(BlockControls, {},
                    el(AlignmentToolbar, {
                        value: attributes.alignment,
                        onChange: function (value) {
                            setAttributes({ alignment: value || '' });
                        }
                    })
                ),
                el(InspectorControls, {},
                    el(PanelBody, { title: i18n.__('Textes', 'mj-member'), initialOpen: true },
                        el(TextControl, {
                            label: i18n.__('Texte du bouton (déconnecté)', 'mj-member'),
                            value: attributes.loginLabel,
                            onChange: function (value) {
                                setAttributes({ loginLabel: value });
                            }
                        }),
                        el(TextControl, {
                            label: i18n.__('Texte du bouton (connecté)', 'mj-member'),
                            value: attributes.accountLabel,
                            onChange: function (value) {
                                setAttributes({ accountLabel: value });
                            }
                        }),
                        el(TextControl, {
                            label: i18n.__('Titre de la fenêtre', 'mj-member'),
                            value: attributes.modalTitle,
                            onChange: function (value) {
                                setAttributes({ modalTitle: value });
                            }
                        }),
                        el(TextareaControl, {
                            label: i18n.__('Texte d\'introduction', 'mj-member'),
                            help: i18n.__('Affiche au-dessus du formulaire de connexion.', 'mj-member'),
                            value: attributes.modalDescription,
                            onChange: function (value) {
                                setAttributes({ modalDescription: value });
                            }
                        }),
                        el(TextControl, {
                            label: i18n.__('Texte du bouton de connexion', 'mj-member'),
                            value: attributes.modalButtonLabel,
                            onChange: function (value) {
                                setAttributes({ modalButtonLabel: value });
                            }
                        }),
                        el(TextControl, {
                            label: i18n.__('Texte du lien d\'inscription', 'mj-member'),
                            value: attributes.registrationLinkLabel,
                            onChange: function (value) {
                                setAttributes({ registrationLinkLabel: value });
                            }
                        }),
                        el(TextControl, {
                            label: i18n.__('URL de redirection (optionnel)', 'mj-member'),
                            value: attributes.redirect,
                            onChange: function (value) {
                                setAttributes({ redirect: value });
                            },
                            placeholder: defaultRedirect
                        })
                    )
                ),
                el('div', blockProps,
                    el('div', { className: 'mj-member-login-block__preview' },
                        el('button', { type: 'button', className: 'mj-member-login-block__button' }, attributes.loginLabel || i18n.__('Se connecter', 'mj-member')),
                        el('p', { className: 'mj-member-login-block__note' }, i18n.__('La fenetre de connexion s\'ouvrira sur le site.', 'mj-member'))
                    )
                )
            );
        },
        save: function () {
            return null;
        }
    });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.blockEditor, window.wp.i18n);
