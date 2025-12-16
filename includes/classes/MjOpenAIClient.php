<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal OpenAI client wrapper used for Grimlins image generation.
 */
final class MjOpenAIClient
{
    private const DEFAULT_MODEL = 'gpt-image-1';
    private const DEFAULT_SIZE = '1024x1024';
    private const ENDPOINT = 'https://api.openai.com/v1/images/edits';

    /** @var string */
    private $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $candidate = is_string($apiKey) ? trim($apiKey) : '';
        if ($candidate === '') {
            $candidate = Config::openAiApiKey();
        }

        $this->apiKey = $candidate;
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Calls OpenAI Images API to generate a Grimlins-styled picture from the given input image.
     *
     * @param string $imagePath Absolute path to the uploaded image.
     * @param array<string,mixed> $args Optional override arguments (prompt, model, size, seed).
     * @return array<string,mixed>|WP_Error
     */
    public function generateGrimlinsImage(string $imagePath, array $args = array())
    {
        if (!$this->isEnabled()) {
            return new WP_Error('mj_openai_disabled', __('La clé API OpenAI est manquante.', 'mj-member'));
        }

        $imagePath = \wp_normalize_path($imagePath);
        if (!file_exists($imagePath) || !is_readable($imagePath)) {
            return new WP_Error('mj_openai_image_missing', __('Image source introuvable ou illisible.', 'mj-member'));
        }

        $fileSize = filesize($imagePath);
        if ($fileSize === false || $fileSize <= 0) {
            return new WP_Error('mj_openai_image_empty', __('Fichier image vide.', 'mj-member'));
        }

        $mimeType = mime_content_type($imagePath);
        if (!is_string($mimeType)) {
            return new WP_Error('mj_openai_image_mime', __('Impossible de déterminer le type MIME.', 'mj-member'));
        }

        $allowedMimes = \apply_filters('mj_member_photo_grimlins_allowed_mimes', array('image/jpeg', 'image/png', 'image/webp'));
        if (!in_array($mimeType, $allowedMimes, true)) {
            return new WP_Error('mj_openai_image_forbidden', __('Format de fichier non pris en charge pour cette transformation.', 'mj-member'));
        }

        $imageContents = file_get_contents($imagePath);
        if ($imageContents === false) {
            return new WP_Error('mj_openai_image_read', __('Impossible de lire le fichier image.', 'mj-member'));
        }

        $prompt = isset($args['prompt']) && is_string($args['prompt']) ? trim($args['prompt']) : '';
        if ($prompt === '') {
            $prompt = \apply_filters('mj_member_photo_grimlins_prompt', __('Transforme cette personne en version "Grimlins" fun et stylisée, avec un rendu illustratif détaillé, sans éléments effrayants.', 'mj-member'));
        }

        $model = isset($args['model']) && is_string($args['model']) ? trim($args['model']) : self::DEFAULT_MODEL;
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }

        $size = isset($args['size']) && is_string($args['size']) ? trim($args['size']) : self::DEFAULT_SIZE;
        if ($size === '') {
            $size = self::DEFAULT_SIZE;
        }

        $seed = null;
        if (isset($args['seed']) && is_numeric($args['seed'])) {
            $seed = (int) $args['seed'];
        }

        $boundary = 'mj-member-openai-' . \wp_generate_uuid4();
        $eol = "\r\n";

        $parts = array();

        $parts[] = '--' . $boundary . $eol
            . 'Content-Disposition: form-data; name="model"' . $eol . $eol
            . $model . $eol;

        $parts[] = '--' . $boundary . $eol
            . 'Content-Disposition: form-data; name="prompt"' . $eol . $eol
            . $prompt . $eol;

        $parts[] = '--' . $boundary . $eol
            . 'Content-Disposition: form-data; name="size"' . $eol . $eol
            . $size . $eol;

        if ($seed !== null) {
            $parts[] = '--' . $boundary . $eol
                . 'Content-Disposition: form-data; name="seed"' . $eol . $eol
                . (string) $seed . $eol;
        }

        $parts[] = '--' . $boundary . $eol
            . 'Content-Disposition: form-data; name="image"; filename="' . basename($imagePath) . '"' . $eol
            . 'Content-Type: ' . $mimeType . $eol . $eol
            . $imageContents . $eol;

        $parts[] = '--' . $boundary . '--' . $eol;

        $body = implode('', $parts);

        $requestArgs = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'body' => $body,
            'timeout' => (int) \apply_filters('mj_member_photo_grimlins_timeout', 45),
        );

        $response = \wp_remote_post(self::ENDPOINT, $requestArgs);
        if (is_wp_error($response)) {
            return new WP_Error('mj_openai_http', sprintf(__('Erreur de communication avec OpenAI : %s', 'mj-member'), $response->get_error_message()));
        }

        $statusCode = \wp_remote_retrieve_response_code($response);
        $rawBody = \wp_remote_retrieve_body($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = __('Erreur inconnue lors de la génération de l’image.', 'mj-member');
            if ($rawBody !== '') {
                $decodedError = json_decode($rawBody, true);
                if (is_array($decodedError) && isset($decodedError['error']['message'])) {
                    $errorMessage = \sanitize_text_field((string) $decodedError['error']['message']);
                }
            }

            return new WP_Error('mj_openai_api_error', $errorMessage, array('status' => $statusCode));
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) || empty($decoded['data'][0])) {
            return new WP_Error('mj_openai_invalid_payload', __('Réponse OpenAI invalide.', 'mj-member'));
        }

        $imageData = $decoded['data'][0];

        $outputBase64 = isset($imageData['b64_json']) ? (string) $imageData['b64_json'] : '';
        $directUrl = isset($imageData['url']) ? \esc_url_raw((string) $imageData['url']) : '';

        $usage = isset($decoded['usage']) && is_array($decoded['usage']) ? $decoded['usage'] : array();

        if ($outputBase64 === '' && $directUrl !== '') {
            $download = \wp_remote_get($directUrl, array('timeout' => 45));
            if (is_wp_error($download)) {
                return new WP_Error('mj_openai_download_failed', sprintf(__('Impossible de télécharger l\'image générée : %s', 'mj-member'), $download->get_error_message()));
            }

            $body = \wp_remote_retrieve_body($download);
            if (!is_string($body) || $body === '') {
                return new WP_Error('mj_openai_download_empty', __('Téléchargement de l\'image générée vide.', 'mj-member'));
            }

            $outputBase64 = base64_encode($body);
        }

        if ($outputBase64 === '') {
            return new WP_Error('mj_openai_empty_image', __('Aucune image générée par OpenAI.', 'mj-member'));
        }

        return array(
            'base64' => $outputBase64,
            'mime_type' => 'image/png',
            'prompt' => $prompt,
            'model' => $model,
            'usage' => $usage,
        );
    }
}
