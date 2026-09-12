/**
 * Plugin settings page - "Contrat" tab - document template library
 * (edit/delete/set-default), plain vanilla JS, no framework dependency.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.getElementById('mj-doctpl-admin');
        if (!root) {
            return;
        }

        var ajaxUrl = root.getAttribute('data-ajax-url') || '';
        var nonce = root.getAttribute('data-nonce') || '';

        function post(action, params) {
            var body = new URLSearchParams(Object.assign({ action: action, nonce: nonce }, params || {}));
            return fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString(),
            }).then(function (response) {
                return response.json();
            });
        }

        function closestRow(el) {
            return el.closest('.mj-doctpl-row');
        }

        root.addEventListener('click', function (event) {
            var target = event.target;

            if (target.classList.contains('mj-doctpl-edit-toggle')) {
                var row = closestRow(target);
                var form = row && row.querySelector('.mj-doctpl-edit-form');
                if (form) {
                    form.style.display = form.style.display === 'none' ? 'block' : 'none';
                }
                return;
            }

            if (target.classList.contains('mj-doctpl-cancel')) {
                var cancelForm = closestRow(target).querySelector('.mj-doctpl-edit-form');
                if (cancelForm) {
                    cancelForm.style.display = 'none';
                }
                return;
            }

            if (target.classList.contains('mj-doctpl-save')) {
                var saveRow = closestRow(target);
                var id = saveRow.getAttribute('data-template-id');
                var name = saveRow.querySelector('.mj-doctpl-edit-name').value;
                var content = saveRow.querySelector('.mj-doctpl-edit-content').value;
                var errorEl = saveRow.querySelector('.mj-doctpl-error');

                errorEl.textContent = '';
                target.disabled = true;

                post('mj_admin_update_document_template', { id: id, name: name, content: content })
                    .then(function (data) {
                        if (data && data.success) {
                            window.location.reload();
                            return;
                        }
                        errorEl.textContent = (data && data.data && data.data.message) || "Impossible d'enregistrer le modèle.";
                        target.disabled = false;
                    })
                    .catch(function () {
                        errorEl.textContent = "Erreur réseau, réessayez.";
                        target.disabled = false;
                    });
                return;
            }

            if (target.classList.contains('mj-doctpl-set-default')) {
                var defaultRow = closestRow(target);
                var defaultId = defaultRow.getAttribute('data-template-id');
                target.disabled = true;

                post('mj_admin_set_default_document_template', { id: defaultId })
                    .then(function (data) {
                        if (data && data.success) {
                            window.location.reload();
                            return;
                        }
                        window.alert((data && data.data && data.data.message) || "Impossible de définir ce modèle par défaut.");
                        target.disabled = false;
                    })
                    .catch(function () {
                        window.alert('Erreur réseau, réessayez.');
                        target.disabled = false;
                    });
                return;
            }

            if (target.classList.contains('mj-doctpl-delete')) {
                if (!window.confirm('Supprimer ce modèle ? Cette action est irréversible.')) {
                    return;
                }
                var deleteRow = closestRow(target);
                var deleteId = deleteRow.getAttribute('data-template-id');
                target.disabled = true;

                post('mj_admin_delete_document_template', { id: deleteId })
                    .then(function (data) {
                        if (data && data.success) {
                            window.location.reload();
                            return;
                        }
                        window.alert((data && data.data && data.data.message) || 'Impossible de supprimer ce modèle.');
                        target.disabled = false;
                    })
                    .catch(function () {
                        window.alert('Erreur réseau, réessayez.');
                        target.disabled = false;
                    });
            }
        });
    });
})();
