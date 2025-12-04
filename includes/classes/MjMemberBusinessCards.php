<?php

namespace Mj\Member\Classes;

use Exception;
use FPDF;
use Mj\Member\Classes\Crud\MjMembers_CRUD;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

class MjMemberBusinessCards
{
    const CARD_WIDTH_MM = 85.0;
    const CARD_HEIGHT_MM = 55.0;
    const CARD_COLUMNS = 2;
    const CARD_ROWS = 5;
    const CARD_MARGIN_LEFT_MM = 12.0;
    const CARD_MARGIN_TOP_MM = 7.0;
    const CARD_GAP_X_MM = 8.0;
    const CARD_GAP_Y_MM = 2.0;
    const ACCENT_HEIGHT_MM = 16.0;

    /**
     * Collect members according to the provided selection mode.
     *
     * @param string $selection_mode
     * @param array  $args
     *
     * @return array<int,array<string,mixed>>
     */
    public static function collect_members($selection_mode, $args = array())
    {
        if (!class_exists(MjMembers_CRUD::class)) {
            return array();
        }

        $selection_mode = sanitize_key($selection_mode);
        $allowed_modes = array('all', 'role', 'custom');
        if (!in_array($selection_mode, $allowed_modes, true)) {
            $selection_mode = 'all';
        }

        $include_inactive = !empty($args['include_inactive']);
        $roles = array();
        if (!empty($args['roles']) && is_array($args['roles'])) {
            foreach ($args['roles'] as $role_key) {
                $role_key = sanitize_key($role_key);
                if ($role_key !== '') {
                    $roles[$role_key] = $role_key;
                }
            }
        }

        $member_ids = array();
        if (!empty($args['member_ids']) && is_array($args['member_ids'])) {
            foreach ($args['member_ids'] as $member_id) {
                $member_id = (int) $member_id;
                if ($member_id > 0) {
                    $member_ids[$member_id] = $member_id;
                }
            }
        }

        $role_labels = method_exists(MjMembers_CRUD::class, 'getRoleLabels') ? MjMembers_CRUD::getRoleLabels() : array();
        $active_status = defined(MjMembers_CRUD::class . '::STATUS_ACTIVE') ? MjMembers_CRUD::STATUS_ACTIVE : 'active';

        $members_map = array();
        $append_member = static function ($member) use (&$members_map, $include_inactive, $role_labels, $active_status) {
            if (!$member || !isset($member->id)) {
                return;
            }

            $member_id = (int) $member->id;
            if ($member_id <= 0) {
                return;
            }

            if (isset($members_map[$member_id])) {
                return;
            }

            if (!$include_inactive && isset($member->status) && $member->status !== $active_status) {
                return;
            }

            $first_name = sanitize_text_field($member->first_name ?? '');
            $last_name = sanitize_text_field($member->last_name ?? '');

            if ($first_name === '' && $last_name === '') {
                return;
            }

            $role_key = sanitize_key($member->role ?? '');
            $role_label = $role_labels[$role_key] ?? ($role_key !== '' ? ucfirst($role_key) : __('Membre', 'mj-member'));

            $date_raw = isset($member->date_inscription) ? trim((string) $member->date_inscription) : '';
            $timestamp = self::parse_timestamp($date_raw);
            $membership_label = $timestamp ? date_i18n(get_option('date_format', 'd/m/Y'), $timestamp) : __('Non renseignée', 'mj-member');

            $full_name = trim($first_name . ' ' . $last_name);
            if ($full_name === '') {
                $full_name = __('Sans nom', 'mj-member');
            }

            $sort_key = strtolower($last_name . ' ' . $first_name . ' ' . $full_name);

            $members_map[$member_id] = array(
                'id' => $member_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'full_name' => $full_name,
                'role' => $role_key,
                'role_label' => $role_label,
                'date_inscription' => $date_raw,
                'membership_label' => $membership_label,
                'sort_key' => $sort_key,
            );
        };

        switch ($selection_mode) {
            case 'role':
                if (empty($roles)) {
                    $results = MjMembers_CRUD::getAll(0, 0, 'last_name', 'ASC');
                    if ($results) {
                        foreach ($results as $entry) {
                            if (empty($entry->role) || !isset($roles[$entry->role])) {
                                continue;
                            }
                            $append_member($entry);
                        }
                    }
                } else {
                    foreach ($roles as $role_key) {
                        $results = MjMembers_CRUD::getAll(0, 0, 'last_name', 'ASC', '', array('role' => $role_key));
                        if ($results) {
                            foreach ($results as $entry) {
                                $append_member($entry);
                            }
                        }
                    }
                }
                break;

            case 'custom':
                if (!empty($member_ids)) {
                    foreach ($member_ids as $member_id) {
                        $member = MjMembers_CRUD::getById($member_id);
                        if ($member) {
                            $append_member($member);
                        }
                    }
                }
                break;

            case 'all':
            default:
                $results = MjMembers_CRUD::getAll(0, 0, 'last_name', 'ASC');
                if ($results) {
                    foreach ($results as $entry) {
                        $append_member($entry);
                    }
                }
                break;
        }

        if (empty($members_map)) {
            return array();
        }

        uasort($members_map, static function ($left, $right) {
            return strcmp($left['sort_key'], $right['sort_key']);
        });

        return array_values($members_map);
    }

    /**
     * Build the PDF with business cards and return the payload ready to stream.
     *
     * @param array<int,array<string,mixed>> $entries
     * @param array<string,mixed>            $options
     *
     * @return array{filename:string,content:string}|WP_Error
     */
    public static function build_cards_pdf(array $entries, array $options = array())
    {
        if (empty($entries)) {
            return new WP_Error('mj_member_cards_pdf_empty', __('Aucun membre sélectionné pour la génération.', 'mj-member'));
        }

        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', Config::path() . 'includes/vendor/font/');
        }

        if (!class_exists('FPDF')) {
            require_once Config::path() . 'includes/vendor/fpdf.php';
        }

        if (!class_exists('FPDF')) {
            return new WP_Error('mj_member_cards_pdf_missing_lib', __('La bibliothèque FPDF est introuvable.', 'mj-member'));
        }

        $defaults = array(
            'background_color' => '#ffffff',
            'accent_color' => '#2563eb',
            'text_color' => '#1f2937',
            'font_family' => 'Helvetica',
            'logo_path' => '',
        );
        $options = array_merge($defaults, is_array($options) ? $options : array());

        $background_rgb = self::parse_color($options['background_color'], array(255, 255, 255));
        $accent_rgb = self::parse_color($options['accent_color'], array(37, 99, 235));
        $text_rgb = self::parse_color($options['text_color'], array(31, 41, 55));
        $border_rgb = self::mix_color($accent_rgb, array(0, 0, 0), 0.35);

        $allowed_fonts = array('Helvetica', 'Arial', 'Courier', 'Times');
        $font_family = in_array($options['font_family'], $allowed_fonts, true) ? $options['font_family'] : 'Helvetica';

        $logo_path = is_string($options['logo_path']) ? trim($options['logo_path']) : '';
        if ($logo_path !== '' && !file_exists($logo_path)) {
            $logo_path = '';
        }

        try {
            $pdf = new FPDF('P', 'mm', 'A4');
        } catch (Exception $exception) {
            return new WP_Error('mj_member_cards_pdf_init', $exception->getMessage());
        }

        $pdf->SetCreator('MJ Member');
        $pdf->SetAuthor('Maison de Jeune', true);
        $pdf->SetTitle('Cartes de visite MJ Member', true);
        $pdf->SetSubject('Cartes de visite des membres', true);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCompression(true);

        $cards_per_page = self::CARD_COLUMNS * self::CARD_ROWS;
        $index = 0;

        foreach ($entries as $entry) {
            if ($index % $cards_per_page === 0) {
                $pdf->AddPage();
            }

            $position = $index % $cards_per_page;
            $row = (int) floor($position / self::CARD_COLUMNS);
            $column = $position % self::CARD_COLUMNS;

            $origin_x = self::CARD_MARGIN_LEFT_MM + ($column * (self::CARD_WIDTH_MM + self::CARD_GAP_X_MM));
            $origin_y = self::CARD_MARGIN_TOP_MM + ($row * (self::CARD_HEIGHT_MM + self::CARD_GAP_Y_MM));

            self::render_card($pdf, $entry, $origin_x, $origin_y, array(
                'font_family' => $font_family,
                'background_rgb' => $background_rgb,
                'accent_rgb' => $accent_rgb,
                'text_rgb' => $text_rgb,
                'border_rgb' => $border_rgb,
                'logo_path' => $logo_path,
            ));

            $index++;
        }

        $filename = 'cartes-mj-member-' . gmdate('Ymd-His') . '.pdf';
        $content = $pdf->Output('S');

        return array(
            'filename' => $filename,
            'content' => $content,
        );
    }

    /**
     * Render a single business card on the PDF canvas.
     *
     * @param FPDF  $pdf
     * @param array $entry
     * @param float $origin_x
     * @param float $origin_y
     * @param array $options
     *
     * @return void
     */
    protected static function render_card(FPDF $pdf, array $entry, $origin_x, $origin_y, array $options)
    {
        $font_family = $options['font_family'];
        $background_rgb = $options['background_rgb'];
        $accent_rgb = $options['accent_rgb'];
        $text_rgb = $options['text_rgb'];
        $border_rgb = $options['border_rgb'];
        $logo_path = $options['logo_path'];

        $card_width = self::CARD_WIDTH_MM;
        $card_height = self::CARD_HEIGHT_MM;

        $pdf->SetDrawColor($border_rgb[0], $border_rgb[1], $border_rgb[2]);
        $pdf->SetLineWidth(0.4);
        $pdf->Rect($origin_x, $origin_y, $card_width, $card_height, 'D');

        $pdf->SetFillColor($background_rgb[0], $background_rgb[1], $background_rgb[2]);
        $pdf->Rect($origin_x, $origin_y, $card_width, $card_height, 'F');

        $pdf->SetFillColor($accent_rgb[0], $accent_rgb[1], $accent_rgb[2]);
        $pdf->Rect($origin_x, $origin_y, $card_width, self::ACCENT_HEIGHT_MM, 'F');

        if ($logo_path !== '') {
            try {
                $logo_width = 18.0;
                $logo_height = 10.0;
                $logo_x = $origin_x + $card_width - $logo_width - 4.0;
                $logo_y = $origin_y + 3.0;
                $pdf->Image($logo_path, $logo_x, $logo_y, $logo_width, $logo_height);
            } catch (Exception $exception) {
                // Ignore rendering errors for the logo to avoid breaking the PDF.
            }
        }

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font_family, 'B', 13);
        $pdf->SetXY($origin_x + 6.0, $origin_y + 8.0);
        $pdf->Cell($card_width - 12.0, 4.5, self::to_pdf_text(self::uppercase($entry['full_name'] ?? '')), 0, 2, 'L');

        $pdf->SetFont($font_family, '', 9);
        $pdf->SetXY($origin_x + 6.0, $origin_y + 14.0);
        $pdf->Cell($card_width - 12.0, 4.0, self::to_pdf_text($entry['role_label'] ?? ''), 0, 2, 'L');

        $pdf->SetTextColor($text_rgb[0], $text_rgb[1], $text_rgb[2]);
        $pdf->SetFont($font_family, '', 10);
        $pdf->SetXY($origin_x + 6.0, $origin_y + 26.0);
        $membership_line = sprintf(__('Adhésion : %s', 'mj-member'), $entry['membership_label'] ?? '');
        $pdf->Cell($card_width - 12.0, 5.0, self::to_pdf_text($membership_line), 0, 2, 'L');

        $identifier_line = sprintf(__('Identifiant : #%d', 'mj-member'), isset($entry['id']) ? (int) $entry['id'] : 0);
        $pdf->SetFont($font_family, '', 9);
        $pdf->SetTextColor($border_rgb[0], $border_rgb[1], $border_rgb[2]);
        $pdf->SetXY($origin_x + 6.0, $origin_y + 34.0);
        $pdf->Cell($card_width - 12.0, 4.5, self::to_pdf_text($identifier_line), 0, 2, 'L');
    }

    /**
     * Convert a hex color to RGB array.
     *
     * @param string $hex
     * @param array  $fallback
     *
     * @return array{0:int,1:int,2:int}
     */
    protected static function parse_color($hex, $fallback)
    {
        if (!is_array($fallback) || count($fallback) !== 3) {
            $fallback = array(255, 255, 255);
        }

        $hex = $hex !== null ? sanitize_hex_color($hex) : '';
        if (!is_string($hex) || $hex === '') {
            return array_values(array_map('intval', $fallback));
        }

        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return array_values(array_map('intval', $fallback));
        }

        return array(
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        );
    }

    /**
     * Mix two RGB colors.
     *
     * @param array<int,int> $color
     * @param array<int,int> $with
     * @param float           $ratio
     *
     * @return array{0:int,1:int,2:int}
     */
    protected static function mix_color($color, $with, $ratio)
    {
        $ratio = max(0.0, min(1.0, (float) $ratio));
        $inverse = 1.0 - $ratio;

        $result = array();
        for ($index = 0; $index < 3; $index++) {
            $base = isset($color[$index]) ? (int) $color[$index] : 0;
            $blend = isset($with[$index]) ? (int) $with[$index] : 0;
            $result[$index] = (int) max(0, min(255, round(($base * $inverse) + ($blend * $ratio))));
        }

        return array($result[0], $result[1], $result[2]);
    }

    /**
     * Convert UTF-8 strings to ISO-8859-1 for FPDF.
     *
     * @param string $text
     *
     * @return string
     */
    protected static function to_pdf_text($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
            if ($converted !== false) {
                return $converted;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return utf8_decode($text);
    }

    /**
     * Uppercase helper respecting multibyte strings when available.
     *
     * @param string $text
     *
     * @return string
     */
    protected static function uppercase($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return $text;
        }

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($text, 'UTF-8');
        }

        return strtoupper($text);
    }

    /**
     * Parse a MySQL datetime/date string and return a timestamp.
     *
     * @param string $value
     *
     * @return int|null
     */
    protected static function parse_timestamp($value)
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        $timestamp = strtotime($value);
        return $timestamp ? (int) $timestamp : null;
    }
}

\class_alias(__NAMESPACE__ . '\\MjMemberBusinessCards', 'MjMemberBusinessCards');
