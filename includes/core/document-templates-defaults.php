<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Default seed content for the document-template library (contract sections).
 *
 * These are drafts only — legal wording (parental authorization, attendance
 * attestation) should be reviewed by a lawyer before real-world use (Belgium,
 * youth association context). They exist so the library isn't empty on
 * install; every row is editable/duplicable from the Registration Manager
 * widget's "Contrat" tab afterwards.
 *
 * Variable tokens match Mj\Member\... buildRegistrationDocumentVariables().
 */
function mj_member_get_default_document_template_texts(): array
{
    return array(
        'parental_authorization' => array(
            'name' => 'Autorisation parentale (standard)',
            'content' => '<p>Je soussigné(e) [guardian_name], responsable légal de [member_name], autorise mon enfant '
                . 'à participer à l\'activité « [event_name] » organisée par [site_name], du [event_date_start] au '
                . '[event_date_end], à [event_location].</p>'
                . '<p>J\'autorise l\'équipe encadrante à prendre les mesures nécessaires en cas d\'urgence médicale, '
                . 'et à contacter les services de secours si besoin.</p>'
                . '<p>Je certifie avoir pris connaissance des modalités de l\'activité et les accepte.</p>',
        ),
        'attendance_attestation' => array(
            'name' => 'Attestation de présence (standard)',
            'content' => '<p>Je soussigné(e) [member_name], atteste être inscrit(e) en tant que participant autonome '
                . 'à l\'activité « [event_name] » organisée par [site_name], du [event_date_start] au [event_date_end], '
                . 'à [event_location].</p>'
                . '<p>Je certifie être en mesure de participer de manière autonome et m\'engage à respecter le règlement '
                . 'de l\'activité.</p>',
        ),
        'signature_guardian' => array(
            'name' => 'Espace signature parentale (standard)',
            'content' => '<p>Fait à _______________, le [current_date].</p>'
                . '<p>Signature du responsable légal ([guardian_name]) :</p>'
                . '<p>_______________________</p>',
        ),
        'signature_autonomous' => array(
            'name' => 'Espace signature membre autonome (standard)',
            'content' => '<p>Fait à _______________, le [current_date].</p>'
                . '<p>Signature du participant ([member_name]) :</p>'
                . '<p>_______________________</p>',
        ),
    );
}

/**
 * Default seed content for the member "fiche d'inscription" contract
 * (Member fiche's "Contrat" tab): header/content/footer, dedicated to
 * member data rather than event data.
 *
 * Variable tokens match buildMemberContractVariables() in
 * includes/core/ajax/admin/registration-manager.php.
 */
function mj_member_get_default_member_contract_template_texts(): array
{
    return array(
        'member_header' => array(
            'name' => 'En-tête fiche membre (par défaut)',
            'content' => '<p><strong>[site_name]</strong></p><p>Fiche d\'inscription</p>',
        ),
        'member_content' => array(
            'name' => 'Fiche d\'inscription (par défaut)',
            'content' => '<p>Je soussigné(e) [guardian_name], responsable légal de [member_name], sollicite son '
                . 'inscription auprès de [site_name].</p>'
                . '<h3>Informations du membre</h3>'
                . '<p>Nom : [member_last_name]<br/>Prénom : [member_first_name]<br/>Date de naissance : [member_birth_date]<br/>'
                . 'Adresse : [member_address]<br/>Téléphone : [member_phone]<br/>E-mail : [member_email]</p>'
                . '<h3>Responsable légal</h3>'
                . '<p>Nom : [guardian_last_name]<br/>Prénom : [guardian_first_name]<br/>Adresse : [guardian_address]<br/>'
                . 'Téléphone : [guardian_phone]<br/>E-mail : [guardian_email]</p>'
                . '<p>Je certifie l\'exactitude des informations ci-dessus et accepte le règlement d\'ordre intérieur de '
                . '[site_name].</p>',
        ),
        'member_footer' => array(
            'name' => 'Pied de page fiche membre (par défaut)',
            'content' => '<p>Fait à _______________, le [current_date].</p>'
                . '<p>Signature :</p>'
                . '<p>_______________________</p>',
        ),
    );
}
