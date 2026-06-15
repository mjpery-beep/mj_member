<?php

namespace Mj\Member\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gère l'export et l'import des pages cibles des liens Mon compte.
 * Permet de sauvegarder la configuration dans des fichiers JSON
 * et de recréer les pages automatiquement lors de l'installation.
 */
class MjAccountPagesExport {

    /**
     * Chemin vers le dossier de données des pages.
     */
    public static function getDataPath(): string {
        return MJ_MEMBER_PATH . 'data/pages/';
    }

    /**
     * Sauvegarde toutes les pages configurées dans des fichiers JSON.
     *
     * @return array Résultat avec 'success', 'saved', 'errors'
     */
    public static function exportPages(): array {
        $result = array(
            'success' => true,
            'saved' => array(),
            'errors' => array(),
        );

        $data_path = self::getDataPath();

        // Créer le dossier s'il n'existe pas
        if (!file_exists($data_path)) {
            wp_mkdir_p($data_path);
        }

        // Récupérer les liens configurés
        $links = MjAccountLinks::getSettings();

        foreach ($links as $link_key => $link_config) {
            $page_id = isset($link_config['page_id']) ? (int) $link_config['page_id'] : 0;

            if ($page_id <= 0) {
                continue;
            }

            $page = get_post($page_id);
            if (!$page || $page->post_type !== 'page') {
                continue;
            }

            // Préparer les données de la page
            $page_data = array(
                'link_key' => $link_key,
                'slug' => $page->post_name,
                'title' => $page->post_title,
                'content' => $page->post_content,
                'status' => $page->post_status,
                'template' => get_page_template_slug($page_id),
                'meta' => array(),
                'exported_at' => current_time('mysql'),
            );

            // Sauvegarder certaines métadonnées Elementor si présentes
            $elementor_data = get_post_meta($page_id, '_elementor_data', true);
            if ($elementor_data) {
                $page_data['meta']['_elementor_data'] = $elementor_data;
            }

            $elementor_edit_mode = get_post_meta($page_id, '_elementor_edit_mode', true);
            if ($elementor_edit_mode) {
                $page_data['meta']['_elementor_edit_mode'] = $elementor_edit_mode;
            }

            $elementor_template_type = get_post_meta($page_id, '_elementor_template_type', true);
            if ($elementor_template_type) {
                $page_data['meta']['_elementor_template_type'] = $elementor_template_type;
            }

            // Nom du fichier basé sur la clé du lien
            $filename = sanitize_file_name($link_key) . '.json';
            $filepath = $data_path . $filename;

            // Sauvegarder en JSON
            $json = wp_json_encode($page_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                $result['errors'][] = sprintf(
                    __('Erreur lors de l\'encodage JSON pour %s', 'mj-member'),
                    $link_key
                );
                continue;
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
            $written = file_put_contents($filepath, $json);

            if ($written === false) {
                $result['errors'][] = sprintf(
                    __('Impossible d\'écrire le fichier %s', 'mj-member'),
                    $filename
                );
                $result['success'] = false;
            } else {
                $result['saved'][] = array(
                    'link_key' => $link_key,
                    'slug' => $page->post_name,
                    'title' => $page->post_title,
                    'filename' => $filename,
                );
            }
        }

        return $result;
    }

    /**
     * Liste les fichiers d'export disponibles.
     *
     * @return array Liste des exports avec leurs métadonnées
     */
    public static function listExports(): array {
        $data_path = self::getDataPath();
        $exports = array();

        if (!is_dir($data_path)) {
            return $exports;
        }

        $files = glob($data_path . '*.json');

        foreach ($files as $filepath) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content = file_get_contents($filepath);
            if ($content === false) {
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data)) {
                continue;
            }

            $exports[] = array(
                'filename' => basename($filepath),
                'link_key' => $data['link_key'] ?? '',
                'slug' => $data['slug'] ?? '',
                'title' => $data['title'] ?? '',
                'exported_at' => $data['exported_at'] ?? '',
            );
        }

        return $exports;
    }

    /**
     * Importe les pages depuis les fichiers d'export.
     * Crée les pages si elles n'existent pas.
     *
     * @param bool $overwrite Écraser les pages existantes
     * @return array Résultat avec 'created', 'skipped', 'errors'
     */
    public static function importPages(bool $overwrite = false): array {
        $result = array(
            'created' => array(),
            'skipped' => array(),
            'updated' => array(),
            'errors' => array(),
        );

        $data_path = self::getDataPath();

        if (!is_dir($data_path)) {
            return $result;
        }

        $files = glob($data_path . '*.json');

        foreach ($files as $filepath) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
            $content = file_get_contents($filepath);
            if ($content === false) {
                $result['errors'][] = sprintf(
                    __('Impossible de lire %s', 'mj-member'),
                    basename($filepath)
                );
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data) || empty($data['slug']) || empty($data['title'])) {
                $result['errors'][] = sprintf(
                    __('Fichier invalide : %s', 'mj-member'),
                    basename($filepath)
                );
                continue;
            }

            $data = self::normalizeImportedValue($data);

            $slug = sanitize_title((string) $data['slug']);
            $title = sanitize_text_field((string) $data['title']);
            $link_key = isset($data['link_key']) ? sanitize_key((string) $data['link_key']) : '';
            $content = isset($data['content']) ? (string) $data['content'] : '';
            $status = isset($data['status']) ? sanitize_key((string) $data['status']) : 'publish';
            $template = isset($data['template']) ? (string) $data['template'] : '';
            $meta = !empty($data['meta']) && is_array($data['meta']) ? $data['meta'] : array();

            // Vérifier si la page existe déjà
            $existing_page = get_page_by_path($slug);

            if ($existing_page) {
                if (!$overwrite) {
                    $result['skipped'][] = array(
                        'slug' => $slug,
                        'title' => $title,
                        'reason' => __('Page existante', 'mj-member'),
                    );
                    continue;
                }

                // Mettre à jour la page existante
                $page_id = wp_update_post(array(
                    'ID' => $existing_page->ID,
                    'post_title' => $title,
                    'post_content' => $content,
                    'post_status' => $status,
                ));

                if (is_wp_error($page_id)) {
                    $result['errors'][] = sprintf(
                        __('Erreur lors de la mise à jour de %s : %s', 'mj-member'),
                        $slug,
                        $page_id->get_error_message()
                    );
                    continue;
                }

                $page_id = $existing_page->ID;
                $result['updated'][] = array(
                    'slug' => $slug,
                    'title' => $title,
                    'page_id' => $page_id,
                );
            } else {
                // Créer une nouvelle page
                $page_id = wp_insert_post(array(
                    'post_type' => 'page',
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => $content,
                    'post_status' => $status,
                ));

                if (is_wp_error($page_id)) {
                    $result['errors'][] = sprintf(
                        __('Erreur lors de la création de %s : %s', 'mj-member'),
                        $slug,
                        $page_id->get_error_message()
                    );
                    continue;
                }

                $result['created'][] = array(
                    'slug' => $slug,
                    'title' => $title,
                    'page_id' => $page_id,
                );
            }

            // Appliquer le template si défini
            if ($template !== '') {
                update_post_meta($page_id, '_wp_page_template', $template);
            }

            // Appliquer les métadonnées Elementor
            if (!empty($meta)) {
                foreach ($meta as $meta_key => $meta_value) {
                    update_post_meta($page_id, $meta_key, $meta_value);
                }
            }

            // Mettre à jour la configuration des liens avec le nouvel ID
            if ($link_key !== '') {
                self::updateLinkPageId($link_key, $page_id, $slug);
            }
        }

        return $result;
    }

    /**
     * Met à jour l'ID de page dans la configuration des liens.
     */
    private static function updateLinkPageId(string $link_key, int $page_id, string $slug): void {
        $saved = get_option('mj_account_links_settings', array());

        if (!is_array($saved)) {
            $saved = array();
        }

        if (!isset($saved[$link_key])) {
            $saved[$link_key] = array();
        }

        $saved[$link_key]['page_id'] = $page_id;
        $saved[$link_key]['page_slug'] = $slug;

        update_option('mj_account_links_settings', $saved);
    }

    /**
     * Appelé à l'activation du plugin pour créer les pages manquantes.
     */
    public static function onPluginActivation(): void {
        $data_path = self::getDataPath();

        if (!is_dir($data_path)) {
            return;
        }

        $files = glob($data_path . '*.json');

        if (empty($files)) {
            return;
        }

        // Importer sans écraser les pages existantes
        self::importPages(false);
    }

    /**
     * Normalise récursivement les données importées pour corriger les anciennes
     * séquences Unicode littérales (\u00e9) ou partiellement corrompues (Tu00e9moignages).
     *
     * @param mixed $value Valeur importée.
     * @return mixed
     */
    private static function normalizeImportedValue($value) {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeImportedValue($item);
            }

            return $value;
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        $value = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static function (array $matches): string {
                return self::decodeUnicodeSequence($matches[1]);
            },
            $value
        ) ?? $value;

        $value = preg_replace_callback(
            '/(?<=\p{L})u([0-9a-fA-F]{4})(?=[\p{L}\p{M}])/u',
            static function (array $matches): string {
                return self::decodeUnicodeSequence($matches[1]);
            },
            $value
        ) ?? $value;

        return $value;
    }

    /**
     * Convertit un code Unicode hexadécimal en caractère UTF-8.
     */
    private static function decodeUnicodeSequence(string $hex): string {
        return html_entity_decode('&#x' . $hex . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
