<?php

/* 
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/EmptyPHP.php to edit this template
 */

class MjMail extends MjTools {
    static public $email_destinataire = 'no_reply@mj-pery.be';
    
    const EMAIL_FORMATION = 'simon@perso.be'; // 
    const EMAIL_LISTING =  'simon@perso.be'; //
    const EMAIL_DEBUG = true; // //'simon@perso.be'; // false;
    
    
    const EMAIL_NEW_SUBSCRIBE= 'simon@perso.be'; //'simon@perso.be'; // false;
    
    

    const TEMPLATE_SUBSCRIBE = 'TEMPLATE_SUBSCRIBE';
    const TEMPLATE_SUBSCRIBE_SOUTIEN = 'TEMPLATE_SUBSCRIBE_SOUTIEN';

       

    static $VERBOSE_MAIL = false;
    protected static $guardian_children_cache = array();

    public static function is_test_mode_enabled() {
        return get_option('mj_email_test_mode', '0') === '1';
    }
    
    static public function getContainer($content){
        $styles = '<style type="text/css">
            body { background-color:#f5f5f5; font-family:Arial, sans-serif; color:#1d2327; margin:0; padding:24px; }
            .mj-email-wrapper { max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; padding:24px 28px; box-shadow:0 6px 24px rgba(0,0,0,0.08); }
            .mj-email-header { text-align:center; margin-bottom:24px; }
            .mj-email-header img { max-width:240px; height:auto; }
            .mj-email-content { font-size:15px; line-height:1.6; }
            .mj-email-content p { margin:0 0 16px 0; }
            .mj-button { display:inline-block; padding:12px 22px; background:#0073aa; color:#ffffff !important; border-radius:4px; text-decoration:none; font-weight:600; margin:12px 0; }
            .mj-button:hover { background:#005f8d; }
            .mj-muted { color:#555d66; font-size:13px; }
            .mj-note { background:#f0f6fc; border-left:4px solid #72aee6; padding:12px 16px; border-radius:4px; margin:18px 0; }
        </style>';

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
'.$styles.'
</head>
<body>
    <div class="mj-email-wrapper">
        <div class="mj-email-header">
            <a href="https://www.mj-pery.be">
                <img src="https://www.mj-pery.be/wp-content/uploads/2025/10/logo.svg" alt="MJ Péry">
            </a>
        </div>
        <div class="mj-email-content">
'.$content.'
        </div>
    </div>
</body>
</html>';
    }

    static public function getTemplate($id){
        $table_name =  self::getTableName('template_email');
        return self::getWpdb()->get_row(self::getWpdb()->prepare("SELECT * FROM $table_name WHERE id = %s",$id));
        
    }
    static public function sendMail($remplacements, $destinataire_email, $template=null, $suffix_title='', $simule=false){

        if(self::EMAIL_DEBUG)
            $destinataire_email = self::EMAIL_DEBUG; 
         
        
                /**
            serveur SMTP sortant mail.infomaniak.com
            port SMTP 465 (avec SSL)
            nom d'utilisateur/username adresse mail complète & entière
            mot de passe/password celui attribué à l'adresse mail en question
         */
        // Paramètres SMTP
      /*
       
        $smtpServer = 'mail.infomaniak.com';
        $smtpUsername = 'info@mj-pery.be';
        $smtpPassword = 'Vegan286';
        $smtpPort = 465; // ou 587 pour TLS ou 465 pour SSL
*/
        
        
        // Contenu du message (ici, on utilise le contenu du template)
        if(!is_object($template))
            $template = VMail::getTemplate($template);
        $sujet = $template->sujet . $suffix_title;
        $raw_body = isset($template->text) ? (string) $template->text : '';
        $raw_body = str_replace(array_keys($remplacements), array_values($remplacements), $raw_body);
        $raw_body = do_shortcode($raw_body);
        $final_body = self::finalize_email_body($raw_body, $remplacements, isset($template->text) ? $template->text : '');
        $message = self::getContainer($final_body);

        $from_email = self::$email_destinataire;
        $from_name = !empty($template->name) ? $template->name : get_bloginfo('name');
        $headers = array(
            'From: ' . $from_name . ' <' . $from_email . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        );
        $test_mode = self::is_test_mode_enabled();
        if ($test_mode) {
            $headers[] = 'X-MJ-Test-Mode: 1';
        }

        
/*
        // Envoi de l'e-mail en utilisant le serveur SMTP
        ini_set('SMTP', $smtpServer);
        ini_set('smtp_port', $smtpPort);
        ini_set('sendmail_from', $smtpUsername);
*/
        
        
        if(strpos($destinataire_email,';'))
            $destinataire_email = explode (';', $destinataire_email);
        else 
            $destinataire_email = array($destinataire_email);
        
        foreach($destinataire_email as $destinataire_email_item)
        {
            $destinataire_email_item = trim($destinataire_email_item);
            if(self::$VERBOSE_MAIL || $simule || $test_mode) {
                echo "<div class='history_boxe'>";
                echo "<a href='mailto:$destinataire_email_item'>".$template->name."</a><br />";
                echo "<h4>$sujet</h4>";
                echo $message;
                echo "</div>";
            }
            if($simule)
            {
                die;
                return true;
            }
        
            if ($test_mode) {
                continue;
            }

            if (!wp_mail($destinataire_email_item, $sujet, $message, $headers)) {
                return false;
            }
        }

        if ($test_mode) {
            do_action('mj_member_email_simulated', $destinataire_email, array(
                'subject' => $sujet,
                'body' => $final_body,
                'body_plain' => trim(wp_strip_all_tags($final_body)),
                'message_html' => $message,
                'recipients' => (array) $destinataire_email,
                'headers' => $headers,
                'test_mode' => true,
                'placeholders' => $remplacements,
                'template' => $template,
            ), array());
        }

        return true;
    }

    // Configure PHPMailer with SMTP settings saved in options
    static public function init_smtp() {
        add_action('phpmailer_init', function($phpmailer) {
            $smtp = get_option('mj_smtp_settings', array());
            if (empty($smtp) || empty($smtp['host'])) {
                return;
            }
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp['host'];
            $phpmailer->Port = !empty($smtp['port']) ? intval($smtp['port']) : 587;
            $phpmailer->SMTPAuth = !empty($smtp['auth']);
            if (!empty($smtp['username'])) {
                $phpmailer->Username = $smtp['username'];
            }
            if (!empty($smtp['password'])) {
                $phpmailer->Password = $smtp['password'];
            }
            if (!empty($smtp['secure'])) {
                $phpmailer->SMTPSecure = $smtp['secure'];
            }
            if (!empty($smtp['from_email'])) {
                $phpmailer->From = $smtp['from_email'];
                $phpmailer->FromName = !empty($smtp['from_name']) ? $smtp['from_name'] : $phpmailer->FromName;
            }
        });
    }

    // Get template from new templates table (by id or slug)
    static public function get_template_by($id_or_slug) {
        global $wpdb;
        $table = $wpdb->prefix . 'mj_email_templates';
        if (is_numeric($id_or_slug)) {
            return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", intval($id_or_slug)));
        }
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE slug = %s", sanitize_text_field($id_or_slug)));
    }

    protected static function get_guardian_for_member($member, array $context = array()) {
        if (!empty($context['guardian']) && is_object($context['guardian'])) {
            return $context['guardian'];
        }

        if (empty($member->guardian_id) || !class_exists('MjMembers_CRUD')) {
            return null;
        }

        static $cache = array();
        $guardian_id = (int) $member->guardian_id;
        if ($guardian_id <= 0) {
            return null;
        }

        if (!array_key_exists($guardian_id, $cache)) {
            $cache[$guardian_id] = MjMembers_CRUD::getById($guardian_id);
        }

        return $cache[$guardian_id];
    }

    protected static function first_not_empty($object, array $keys) {
        foreach ($keys as $key) {
            if (isset($object->$key) && $object->$key !== '') {
                return $object->$key;
            }
        }
        return '';
    }

    protected static function build_placeholders($member, array $context = array()) {
        $member_first = self::first_not_empty($member, array('first_name', 'jeune_prenom', 'prenom', 'name'));
        $member_last  = self::first_not_empty($member, array('last_name', 'jeune_nom', 'nom'));
        $member_email = self::first_not_empty($member, array('email', 'jeune_email'));
        $member_phone = self::first_not_empty($member, array('phone', 'jeune_phone'));
        $member_role  = isset($member->role) ? $member->role : '';
        $guardian     = self::get_guardian_for_member($member, $context);

        $guardian_first = $guardian ? self::first_not_empty($guardian, array('first_name', 'jeune_prenom', 'tutor_prenom')) : '';
        $guardian_last  = $guardian ? self::first_not_empty($guardian, array('last_name', 'jeune_nom', 'tutor_nom')) : '';
        $guardian_email = $guardian ? self::first_not_empty($guardian, array('email', 'jeune_email', 'tutor_email')) : '';
        $guardian_phone = $guardian ? self::first_not_empty($guardian, array('phone', 'jeune_phone', 'tutor_phone')) : '';

        $payment_amount = isset($context['payment_amount']) ? $context['payment_amount'] : '';
        $payment_amount_raw = $payment_amount;
        if ($payment_amount !== '' && is_numeric($payment_amount)) {
            $payment_amount = number_format((float) $payment_amount, 2, ',', ' ');
        }

        $payment_date = isset($context['payment_date']) ? $context['payment_date'] : '';
        if ($payment_date === '' && !empty($member->date_last_payement)) {
            $payment_date = $member->date_last_payement;
        }

        $payment_link_raw = isset($context['payment_link']) ? trim((string) $context['payment_link']) : '';
        if ($payment_link_raw !== '') {
            $payment_link_raw = esc_url_raw($payment_link_raw);
        }
        $payment_link_href = $payment_link_raw !== '' ? esc_url($payment_link_raw) : '';

        $payment_qr_raw = isset($context['payment_qr_url']) ? trim((string) $context['payment_qr_url']) : '';
        if ($payment_qr_raw !== '') {
            $payment_qr_raw = esc_url_raw($payment_qr_raw);
        }
        $payment_qr_href = $payment_qr_raw !== '' ? esc_url($payment_qr_raw) : '';

        $payment_reference = isset($context['payment_reference']) ? sanitize_text_field($context['payment_reference']) : '';

        $default_button_label = __('Payer maintenant', 'mj-member');
        $payment_button_label = apply_filters('mj_member_payment_button_label', $default_button_label, $member, $context);
        if (!is_string($payment_button_label)) {
            $payment_button_label = $default_button_label;
        }
        $payment_button_label = trim($payment_button_label);
        if ($payment_button_label === '') {
            $payment_button_label = $default_button_label;
        }

        $payment_button_html = '';
        if ($payment_link_href !== '') {
            $payment_button_html = sprintf(
                '<a href="%s" class="mj-button" target="_blank" rel="noopener">%s</a>',
                $payment_link_href,
                esc_html($payment_button_label)
            );
        }

        $placeholders = array(
            '{{member_first_name}}' => $member_first,
            '{{member_last_name}}' => $member_last,
            '{{member_full_name}}' => trim($member_first . ' ' . $member_last),
            '{{member_email}}' => $member_email,
            '{{member_phone}}' => $member_phone,
            '{{member_role}}' => $member_role,
            '{{member_id}}' => isset($member->id) ? $member->id : '',
            '{{date_inscription}}' => isset($member->date_inscription) ? $member->date_inscription : '',
            '{{date_last_payement}}' => isset($member->date_last_payement) ? $member->date_last_payement : '',
            '{{payment_last_date}}' => isset($member->date_last_payement) ? $member->date_last_payement : '',
            '{{payment_status}}' => isset($member->status) ? $member->status : '',
            '{{payment_amount}}' => $payment_amount,
            '{{payment_amount_raw}}' => $payment_amount_raw,
            '{{payment_link}}' => $payment_link_raw,
            '{{payment_link_raw}}' => $payment_link_raw,
            '{{payment_link_plain}}' => $payment_link_raw,
            '{{payment_link_href}}' => $payment_link_href,
            '{{payment_button}}' => $payment_button_html,
            '{{payment_button_label}}' => $payment_button_label,
            '{{payment_qr_url}}' => $payment_qr_href,
            '{{payment_reference}}' => $payment_reference,
            '{{payment_date}}' => $payment_date,
            '{{guardian_first_name}}' => $guardian_first,
            '{{guardian_last_name}}' => $guardian_last,
            '{{guardian_full_name}}' => trim($guardian_first . ' ' . $guardian_last),
            '{{guardian_email}}' => $guardian_email,
            '{{guardian_phone}}' => $guardian_phone,
            '{{site_name}}' => get_bloginfo('name'),
            '{{site_url}}' => home_url('/'),
            '{{today}}' => date_i18n('Y-m-d'),
            '{{cash_payment_note}}' => '',
            '{{cash_payment_note_plain}}' => '',
            '{{guardian_children_list}}' => '',
            '{{guardian_children_list_plain}}' => '',
            '{{guardian_children_inline}}' => '',
            '{{guardian_children_count}}' => '0',
            '{{guardian_children_note}}' => '',
            '{{guardian_children_note_plain}}' => '',
        );

        // Backward-compatibility placeholders
        $placeholders['{{jeune_prenom}}'] = $member_first;
        $placeholders['{{jeune_nom}}'] = $member_last;
        $placeholders['{{jeune_email}}'] = $member_email;
        $placeholders['{{jeune_phone}}'] = $member_phone;
        $placeholders['{{tutor_prenom}}'] = $guardian_first;
        $placeholders['{{tutor_nom}}'] = $guardian_last;
        $placeholders['{{tutor_email}}'] = $guardian_email;

        $placeholders['{{children_payment_table}}'] = '';
        $placeholders['{{children_payment_list}}'] = '';
        $placeholders['{{children_payment_list_plain}}'] = '';
        $placeholders['{{children_payment_total}}'] = '';
        $placeholders['{{children_payment_total_raw}}'] = '';
        $placeholders['{{children_payment_count}}'] = '0';

        if (!empty($context['children']) && is_array($context['children'])) {
            $rows = array();
            $list_items = array();
            $plain_lines = array();
            $total_amount = 0.0;
            $count = 0;

            foreach ($context['children'] as $child_entry) {
                if (!is_array($child_entry)) {
                    continue;
                }

                $child_member = isset($child_entry['member']) && is_object($child_entry['member']) ? $child_entry['member'] : null;
                $child_name = '';
                if ($child_member) {
                    $child_name = self::format_child_name($child_member);
                } elseif (isset($child_entry['name']) && $child_entry['name'] !== '') {
                    $child_name = sanitize_text_field($child_entry['name']);
                }
                if ($child_name === '') {
                    $child_name = __('Jeune', 'mj-member');
                }

                $amount_numeric = 0.0;
                if (isset($child_entry['payment_amount_numeric'])) {
                    $amount_numeric = (float) $child_entry['payment_amount_numeric'];
                } elseif (!empty($child_entry['payment_amount'])) {
                    $amount_numeric = (float) str_replace(',', '.', (string) $child_entry['payment_amount']);
                }

                $total_amount += $amount_numeric;
                $amount_display = $amount_numeric > 0 ? number_format((float) $amount_numeric, 2, ',', ' ') : '';

                $child_payment_link_raw = isset($child_entry['payment_link']) ? esc_url_raw($child_entry['payment_link']) : '';
                $child_payment_link_href = $child_payment_link_raw !== '' ? esc_url($child_payment_link_raw) : '';
                $child_payment_qr_raw = isset($child_entry['payment_qr_url']) ? esc_url_raw($child_entry['payment_qr_url']) : '';
                $child_payment_qr_href = $child_payment_qr_raw !== '' ? esc_url($child_payment_qr_raw) : '';
                $child_payment_reference = isset($child_entry['payment_reference']) ? sanitize_text_field($child_entry['payment_reference']) : '';

                if ($child_payment_link_href !== '') {
                    $link_html = sprintf(
                        '<a href="%s" target="_blank" rel="noopener">%s</a>',
                        $child_payment_link_href,
                        esc_html($payment_button_label)
                    );
                } elseif ($child_payment_qr_href !== '') {
                    $link_html = sprintf(
                        '<a href="%s" target="_blank" rel="noopener">%s</a>',
                        $child_payment_qr_href,
                        esc_html(__('QR code', 'mj-member'))
                    );
                } else {
                    $link_html = esc_html(__('Lien de paiement indisponible', 'mj-member'));
                }

                $reference_html = $child_payment_reference !== ''
                    ? '<div style="font-size:12px; color:#666; margin-top:4px;">' . esc_html(sprintf(__('Référence : %s', 'mj-member'), $child_payment_reference)) . '</div>'
                    : '';

                $rows[] = sprintf(
                    '<tr><td style="padding:8px 10px; border:1px solid #ddd;">%s</td><td style="padding:8px 10px; border:1px solid #ddd; text-align:right;">%s%s</td><td style="padding:8px 10px; border:1px solid #ddd;">%s%s</td></tr>',
                    esc_html($child_name),
                    $amount_display !== '' ? esc_html($amount_display) : '',
                    $amount_display !== '' ? ' €' : '',
                    $link_html,
                    $reference_html
                );

                $list_item = '<strong>' . esc_html($child_name) . '</strong>';
                if ($amount_display !== '') {
                    $list_item .= ' – ' . esc_html($amount_display) . ' €';
                }
                if ($child_payment_link_href !== '') {
                    $list_item .= ' – <a href="' . $child_payment_link_href . '" target="_blank" rel="noopener">' . esc_html(__('Lien de paiement', 'mj-member')) . '</a>';
                } elseif ($child_payment_qr_href !== '') {
                    $list_item .= ' – <a href="' . $child_payment_qr_href . '" target="_blank" rel="noopener">' . esc_html(__('QR code', 'mj-member')) . '</a>';
                }
                if ($child_payment_reference !== '') {
                    $list_item .= ' <span style="font-size:12px; color:#666;">(' . esc_html(sprintf(__('Réf. %s', 'mj-member'), $child_payment_reference)) . ')</span>';
                }

                $list_items[] = '<li>' . $list_item . '</li>';

                $plain_line_parts = array($child_name);
                if ($amount_display !== '') {
                    $plain_line_parts[] = $amount_display . ' €';
                }
                if ($child_payment_link_raw !== '') {
                    $plain_line_parts[] = $child_payment_link_raw;
                } elseif ($child_payment_qr_raw !== '') {
                    $plain_line_parts[] = $child_payment_qr_raw;
                }
                if ($child_payment_reference !== '') {
                    $plain_line_parts[] = sprintf(__('Réf. %s', 'mj-member'), $child_payment_reference);
                }
                $plain_lines[] = implode(' – ', array_filter($plain_line_parts));

                $count++;
            }

            if ($count > 0) {
                $table_header = '<thead><tr><th style="padding:8px 10px; border:1px solid #ddd; text-align:left;">' . esc_html(__('Jeune', 'mj-member')) . '</th><th style="padding:8px 10px; border:1px solid #ddd; text-align:right;">' . esc_html(__('Montant', 'mj-member')) . '</th><th style="padding:8px 10px; border:1px solid #ddd; text-align:left;">' . esc_html(__('Lien', 'mj-member')) . '</th></tr></thead>';
                $placeholders['{{children_payment_table}}'] = '<table style="width:100%; border-collapse:collapse; margin:10px 0;">' . $table_header . '<tbody>' . implode('', $rows) . '</tbody></table>';
                $placeholders['{{children_payment_list}}'] = '<ul style="padding-left:20px; margin:10px 0;">' . implode('', $list_items) . '</ul>';
                $placeholders['{{children_payment_list_plain}}'] = implode("\n", $plain_lines);
                $placeholders['{{children_payment_total}}'] = number_format((float) $total_amount, 2, ',', ' ');
                $placeholders['{{children_payment_total_raw}}'] = number_format((float) $total_amount, 2, '.', '');
                $placeholders['{{children_payment_count}}'] = (string) $count;
            }
        }

        $guardian_children_names = self::collect_guardian_children_names($member, $guardian, $context);
        if (!empty($guardian_children_names)) {
            $guardian_list_items = array();
            foreach ($guardian_children_names as $child_name) {
                $guardian_list_items[] = '<li>' . esc_html($child_name) . '</li>';
            }
            $guardian_list_html = '<ul style="padding-left:20px; margin:10px 0;">' . implode('', $guardian_list_items) . '</ul>';
            $placeholders['{{guardian_children_list}}'] = $guardian_list_html;
            $placeholders['{{guardian_children_list_plain}}'] = implode("\n", $guardian_children_names);
            $placeholders['{{guardian_children_inline}}'] = implode(', ', $guardian_children_names);
            $placeholders['{{guardian_children_count}}'] = (string) count($guardian_children_names);

            $guardian_note_title_default = __('Jeunes rattachés', 'mj-member');
            $guardian_note_intro_default = __('Voici la liste des jeunes liés à votre compte :', 'mj-member');

            $guardian_note_title = apply_filters('mj_member_guardian_children_note_title', $guardian_note_title_default, $member, $context, $guardian_children_names);
            if (!is_string($guardian_note_title)) {
                $guardian_note_title = $guardian_note_title_default;
            }
            $guardian_note_title = trim($guardian_note_title);
            if ($guardian_note_title === '') {
                $guardian_note_title = $guardian_note_title_default;
            }

            $guardian_note_intro = apply_filters('mj_member_guardian_children_note_intro', $guardian_note_intro_default, $member, $context, $guardian_children_names);
            if (!is_string($guardian_note_intro)) {
                $guardian_note_intro = $guardian_note_intro_default;
            }
            $guardian_note_intro = trim($guardian_note_intro);
            if ($guardian_note_intro === '') {
                $guardian_note_intro = $guardian_note_intro_default;
            }

            $guardian_note_html = sprintf(
                '<div class="mj-note"><strong>%s</strong><br>%s%s</div>',
                esc_html($guardian_note_title),
                esc_html($guardian_note_intro),
                $guardian_list_html
            );
            $guardian_note_html = apply_filters('mj_member_guardian_children_note_html', $guardian_note_html, $member, $context, $guardian_children_names);

            $guardian_note_plain = $guardian_note_title . ' : ' . $guardian_note_intro . ' ' . implode(', ', $guardian_children_names);
            $guardian_note_plain = apply_filters('mj_member_guardian_children_note_plain', $guardian_note_plain, $member, $context, $guardian_children_names);

            $placeholders['{{guardian_children_note}}'] = $guardian_note_html;
            $placeholders['{{guardian_children_note_plain}}'] = $guardian_note_plain;
        }

        $should_add_cash_note = false;
        if (
            $payment_link_href !== '' ||
            $payment_qr_href !== '' ||
            ($payment_amount_raw !== '' && $payment_amount_raw !== null) ||
            ($payment_amount !== '' && $payment_amount !== null) ||
            $placeholders['{{children_payment_count}}'] !== '0'
        ) {
            $should_add_cash_note = true;
        }

        if (array_key_exists('force_cash_note', $context)) {
            $should_add_cash_note = (bool) $context['force_cash_note'];
        }
        if (!empty($context['disable_cash_note'])) {
            $should_add_cash_note = false;
        }

        if ($should_add_cash_note) {
            $cash_note_title_default = __('Paiement en main propre', 'mj-member');
            $cash_note_text_default = __('Vous pouvez aussi remettre le montant en main propre à l\'animateur. Merci de nous prévenir lors de la prochaine activité.', 'mj-member');

            $cash_note_title = apply_filters('mj_member_cash_payment_note_title', $cash_note_title_default, $member, $context);
            if (!is_string($cash_note_title)) {
                $cash_note_title = $cash_note_title_default;
            }
            $cash_note_title = trim($cash_note_title);
            if ($cash_note_title === '') {
                $cash_note_title = $cash_note_title_default;
            }

            $cash_note_text = apply_filters('mj_member_cash_payment_note_text', $cash_note_text_default, $member, $context);
            if (!is_string($cash_note_text)) {
                $cash_note_text = $cash_note_text_default;
            }
            $cash_note_text = trim($cash_note_text);
            if ($cash_note_text === '') {
                $cash_note_text = $cash_note_text_default;
            }

            $cash_note_html = sprintf(
                '<div class="mj-note"><strong>%s</strong><br>%s</div>',
                esc_html($cash_note_title),
                esc_html($cash_note_text)
            );
            $cash_note_html = apply_filters('mj_member_cash_payment_note_html', $cash_note_html, $member, $context, $cash_note_title, $cash_note_text);

            $cash_note_plain = $cash_note_title . ' : ' . $cash_note_text;
            $cash_note_plain = apply_filters('mj_member_cash_payment_note_plain', $cash_note_plain, $member, $context, $cash_note_title, $cash_note_text);

            $placeholders['{{cash_payment_note}}'] = $cash_note_html;
            $placeholders['{{cash_payment_note_plain}}'] = $cash_note_plain;
        }

        if (!empty($context['extra_placeholders']) && is_array($context['extra_placeholders'])) {
            $placeholders = array_merge($placeholders, $context['extra_placeholders']);
        }

        /**
         * Filter the placeholder list before replacement.
         *
         * @param array $placeholders The placeholder/value pairs.
         * @param object $member       The member object.
         * @param array $context       Additional context.
         */
        return apply_filters('mj_member_email_placeholders', $placeholders, $member, $context);
    }

    protected static function format_child_name($child) {
        if (is_array($child)) {
            $child = (object) $child;
        }

        if (!is_object($child)) {
            return '';
        }

        $first = self::first_not_empty($child, array('first_name', 'jeune_prenom', 'prenom', 'name'));
        $last  = self::first_not_empty($child, array('last_name', 'jeune_nom', 'nom'));
        $name  = trim($first . ' ' . $last);

        if ($name === '' && isset($child->email) && $child->email !== '') {
            $name = (string) $child->email;
        }

        if ($name === '' && isset($child->id)) {
            $name = sprintf(__('Jeune #%d', 'mj-member'), (int) $child->id);
        }

        if ($name === '') {
            $name = __('Jeune', 'mj-member');
        }

        return $name;
    }

    protected static function collect_guardian_children_names($member, $guardian, array $context = array()) {
        $names = array();

        if (!empty($context['guardian_children']) && is_array($context['guardian_children'])) {
            foreach ($context['guardian_children'] as $child_entry) {
                if (is_object($child_entry) || is_array($child_entry)) {
                    $names[] = self::format_child_name($child_entry);
                }
            }
        }

        if (!empty($context['children']) && is_array($context['children'])) {
            foreach ($context['children'] as $child_entry) {
                $child_member = null;
                if (is_array($child_entry) && isset($child_entry['member']) && is_object($child_entry['member'])) {
                    $child_member = $child_entry['member'];
                } elseif (is_object($child_entry)) {
                    $child_member = $child_entry;
                }

                if ($child_member) {
                    $names[] = self::format_child_name($child_member);
                } elseif (is_array($child_entry)) {
                    $names[] = self::format_child_name($child_entry);
                }
            }
        }

        $names = array_values(array_unique(array_filter(array_map('trim', $names))));
        if (!empty($names)) {
            return $names;
        }

        $guardian_source = null;
        if (is_object($guardian) && isset($guardian->id) && (int) $guardian->id > 0) {
            $guardian_source = $guardian;
        } elseif (is_object($member) && isset($member->role) && $member->role === MjMembers_CRUD::ROLE_TUTEUR) {
            $guardian_source = $member;
        }

        if (!$guardian_source || !class_exists('MjMembers_CRUD')) {
            return array();
        }

        $guardian_id = isset($guardian_source->id) ? (int) $guardian_source->id : 0;
        if ($guardian_id <= 0) {
            return array();
        }

        if (!isset(self::$guardian_children_cache[$guardian_id])) {
            self::$guardian_children_cache[$guardian_id] = MjMembers_CRUD::getChildrenForGuardian($guardian_id);
        }

        $children = self::$guardian_children_cache[$guardian_id];
        if (!is_array($children) || empty($children)) {
            return array();
        }

        $names = array();
        foreach ($children as $child_member) {
            $names[] = self::format_child_name($child_member);
        }

        $names = array_values(array_unique(array_filter(array_map('trim', $names))));
        return $names;
    }

    protected static function finalize_email_body($body, array $placeholders, $original_content = '') {
        $body = (string) $body;
        $original_content = (string) $original_content;

        $final = trim(wpautop($body));

        $cash_note_html = isset($placeholders['{{cash_payment_note}}']) ? $placeholders['{{cash_payment_note}}'] : '';
        if ($cash_note_html !== '') {
            if (strpos($original_content, '{{cash_payment_note}}') === false) {
                $final .= "\n" . $cash_note_html;
            }
        }

        $guardian_note_html = isset($placeholders['{{guardian_children_note}}']) ? $placeholders['{{guardian_children_note}}'] : '';
        if ($guardian_note_html !== '') {
            $has_placeholder = strpos($original_content, '{{guardian_children_note}}') !== false
                || strpos($original_content, '{{guardian_children_list}}') !== false
                || strpos($original_content, '{{guardian_children_inline}}') !== false
                || strpos($original_content, '{{guardian_children_count}}') !== false;
            if (!$has_placeholder) {
                $final .= "\n" . $guardian_note_html;
            }
        }

        $final = trim($final);
        return apply_filters('mj_member_email_final_body', $final, $placeholders, $original_content);
    }

    protected static function resolve_recipients($member, array $context = array()) {
        $candidates = array();
        if (!empty($context['recipients'])) {
            $candidates = (array) $context['recipients'];
        } else {
            $member_email = self::first_not_empty($member, array('email', 'jeune_email'));
            if ($member_email !== '') {
                $candidates[] = $member_email;
            }

            $include_guardian = true;
            if (array_key_exists('include_guardian', $context)) {
                $include_guardian = (bool) $context['include_guardian'];
            }

            if ($include_guardian) {
                $guardian = self::get_guardian_for_member($member, $context);
                if ($guardian) {
                    $guardian_email = self::first_not_empty($guardian, array('email', 'jeune_email', 'tutor_email'));
                    if ($guardian_email !== '') {
                        $candidates[] = $guardian_email;
                    }
                }
            }
        }

        $recipients = array();
        foreach ($candidates as $email) {
            $email = trim((string) $email);
            if ($email !== '' && is_email($email)) {
                $recipients[] = $email;
            }
        }

        $recipients = array_values(array_unique($recipients));

        /**
         * Filter the resolved recipient list.
         *
         * @param array  $recipients The email addresses.
         * @param object $member     The member object.
         * @param array  $context    Additional context.
         */
        return apply_filters('mj_member_email_recipients', $recipients, $member, $context);
    }

    // Send template to a member object
    static public function send_template_to_member($template_id_or_slug, $member, array $context = array()) {
        $template = self::get_template_by($template_id_or_slug);
        if (!$template || !is_object($member)) {
            return false;
        }

        $subject = $template->subject ?? $template->sujet ?? '';
        $content = $template->content ?? $template->text ?? '';

        $placeholders = self::build_placeholders($member, $context);
        $keys = array_keys($placeholders);
        $values = array_values($placeholders);

        $subject = str_replace($keys, $values, $subject);
        $message_body = str_replace($keys, $values, $content);
        $message_body = do_shortcode($message_body);
        $final_body = self::finalize_email_body($message_body, $placeholders, $content);
        $message = self::getContainer($final_body);
        $plain_body = trim(wp_strip_all_tags($final_body));

        $recipients = self::resolve_recipients($member, $context);
        if (empty($recipients)) {
            return false;
        }

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if (!empty($context['headers']) && is_array($context['headers'])) {
            $headers = array_merge($headers, $context['headers']);
        }

        $test_mode = self::is_test_mode_enabled();
        if ($test_mode) {
            $headers[] = 'X-MJ-Test-Mode: 1';
            do_action('mj_member_email_simulated', $member, array(
                'subject' => $subject,
                'body' => $final_body,
                'body_plain' => $plain_body,
                'message_html' => $message,
                'recipients' => $recipients,
                'headers' => $headers,
                'test_mode' => true,
                'placeholders' => $placeholders,
                'template' => $template,
            ), $context);
            return true;
        }

        $sent = true;
        foreach ($recipients as $recipient) {
            $result = wp_mail($recipient, $subject, $message, $headers);
            if (!$result) {
                $sent = false;
            }
        }

        return $sent;
    }

    public static function prepare_custom_email($member, $subject, $content, array $context = array()) {
        if (!is_object($member)) {
            return false;
        }

        $subject = trim((string) $subject);
        $content = (string) $content;
        if ($subject === '' || $content === '') {
            return false;
        }

        $placeholders = self::build_placeholders($member, $context);
        $keys = array_keys($placeholders);
        $values = array_values($placeholders);

        $resolved_subject = str_replace($keys, $values, $subject);
        $resolved_body = str_replace($keys, $values, $content);
        $resolved_body = do_shortcode($resolved_body);
        $final_body = self::finalize_email_body($resolved_body, $placeholders, $content);
        $message_html = self::getContainer($final_body);
        $plain_body = trim(wp_strip_all_tags($final_body));

        $test_mode = self::is_test_mode_enabled();

        $recipients = self::resolve_recipients($member, $context);
        if (empty($recipients)) {
            return false;
        }

        $headers = array('Content-Type: text/html; charset=UTF-8');
        if (!empty($context['headers']) && is_array($context['headers'])) {
            $headers = array_merge($headers, $context['headers']);
        }
        if ($test_mode) {
            $headers[] = 'X-MJ-Test-Mode: 1';
        }

        return array(
            'subject' => $resolved_subject,
            'body' => $final_body,
            'body_plain' => $plain_body,
            'message_html' => $message_html,
            'recipients' => $recipients,
            'headers' => $headers,
            'test_mode' => $test_mode,
            'placeholders' => $placeholders,
        );
    }

    public static function send_custom_email($member, $subject, $content, array $context = array()) {
        if (!is_object($member)) {
            return false;
        }

        $prepared = self::prepare_custom_email($member, $subject, $content, $context);
        if ($prepared === false) {
            return false;
        }

        if (!empty($prepared['test_mode'])) {
            do_action('mj_member_email_simulated', $member, $prepared, $context);
            return true;
        }

        $sent = true;
        foreach ($prepared['recipients'] as $recipient) {
            $result = wp_mail($recipient, $prepared['subject'], $prepared['message_html'], $prepared['headers']);
            if (!$result) {
                $sent = false;
            }
        }

        return $sent;
    }

    public static function send_registration_notice($member, array $context = array()) {
        return self::send_template_to_member('member_registration', $member, $context);
    }

    public static function send_guardian_registration_notice($guardian, array $context = array()) {
        return self::send_template_to_member('guardian_registration', $guardian, $context);
    }

    public static function send_payment_confirmation($member, array $context = array()) {
        return self::send_template_to_member('payment_confirmation', $member, $context);
    }

    public static function send_payment_reminder($member, array $context = array()) {
        return self::send_template_to_member('payment_reminder', $member, $context);
    }
}

// Initialize SMTP hook
MjMail::init_smtp();