jQuery(document).ready(function($) {
    var mediaUploader;
    
    // Ouvrir le media uploader
    $(document).on('click', '.mj-photo-upload-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var memberId = $btn.data('member-id');
        
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Sélectionner une photo de profil',
            button: {
                text: 'Utiliser cette image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });
        
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            
            // Envoyer au serveur
            uploadPhoto(memberId, attachment.id, $btn);
        });
        
        mediaUploader.open();
    });
    
    function uploadPhoto(memberId, attachmentId, $btn) {
        $.post(mjPhotoUpload.ajaxurl, {
            action: 'mj_upload_member_photo',
            nonce: mjPhotoUpload.nonce,
            member_id: memberId,
            attachment_id: attachmentId
        }, function(response) {
            if (response.success) {
                // Mettre à jour l'affichage
                var photoContainer = $btn.closest('.mj-photo-form-container, .mj-photo-container');
                
                // Créer une image ou la mettre à jour
                var photoImg = photoContainer.find('.mj-member-photo');
                if (photoImg.length === 0) {
                    photoContainer.html('<img src="' + response.data.image_url + '" alt="Photo" class="mj-member-photo" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin-bottom: 10px; display: block;"><br><button type="button" class="button button-small mj-photo-upload-btn" data-member-id="' + memberId + '">📷 Changer la photo</button> <button type="button" class="button button-small mj-delete-photo-btn" data-member-id="' + memberId + '">✕ Supprimer</button>');
                } else {
                    photoImg.attr('src', response.data.image_url);
                    if (!photoContainer.find('.mj-delete-photo-btn').length) {
                        photoContainer.append('<br><button type="button" class="button button-small mj-delete-photo-btn" data-member-id="' + memberId + '">✕ Supprimer</button>');
                    }
                }
                
                showNotice('Photo mise à jour avec succès', 'success');
            } else {
                showNotice('Erreur: ' + response.data.message, 'error');
            }
        }).fail(function() {
            showNotice('Erreur de communication', 'error');
        });
    }
    
    // Supprimer la photo
    $(document).on('click', '.mj-delete-photo-btn', function(e) {
        e.preventDefault();
        
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette photo?')) {
            return;
        }
        
        var $btn = $(this);
        var memberId = $btn.data('member-id');
        var photoContainer = $btn.closest('.mj-photo-form-container, .mj-photo-container');
        
        $.post(mjMembers.ajaxurl, {
            action: 'mj_inline_edit_member',
            nonce: mjMembers.nonce,
            member_id: memberId,
            field_name: 'photo_id',
            field_value: ''
        }, function(response) {
            if (response.success) {
                // Vérifier si c'est dans la liste ou dans le formulaire
                if (photoContainer.closest('.mj-photo-form-container').length) {
                    // Formulaire
                    photoContainer.html('<p style="color: #999;">Pas de photo</p><button type="button" class="button button-small mj-photo-upload-btn" data-member-id="' + memberId + '">📷 Ajouter une photo</button>');
                } else {
                    // Liste
                    photoContainer.html('<span class="mj-no-photo" style="color: #999;">Pas de photo</span><br><button class="button button-small mj-photo-upload-btn" data-member-id="' + memberId + '">📷 Ajouter</button>');
                }
                
                showNotice('Photo supprimée', 'success');
            } else {
                showNotice('Erreur: ' + response.data.message, 'error');
            }
        });
    });
    
    function showNotice(message, type) {
        var noticeHtml = '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>';
        $('.mj-members-container').prepend(noticeHtml);
        
        setTimeout(function() {
            $('.mj-members-container .notice').fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
});
