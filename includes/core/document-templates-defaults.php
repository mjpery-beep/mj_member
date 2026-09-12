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
