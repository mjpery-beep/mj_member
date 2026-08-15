<?php

namespace Mj\Member\Classes;

use Mj\Member\Classes\Crud\MjInventory;
use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Minimal OpenAI client wrapper used for image and text generation.
 */
final class MjOpenAIClient
{
    private const DEFAULT_MODEL = 'gpt-image-1';
    private const DEFAULT_SIZE = '1024x1024';
    private const ENDPOINT = 'https://api.openai.com/v1/images/edits';
    private const GENERATIONS_ENDPOINT = 'https://api.openai.com/v1/images/generations';
    private const TEXT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const DEFAULT_TEXT_MODEL = 'gpt-4o-mini';

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

    /**
     * Calls the OpenAI Chat Completions API to generate text.
     *
     * @param string $systemPrompt System prompt defining the assistant role/context.
     * @param string $userPrompt   The user message / generation request.
     * @param array<string,mixed> $args Optional overrides (model, max_tokens, temperature).
     * @return array{text: string, model: string, usage: array}|WP_Error
     */
    public function generateText(string $systemPrompt, string $userPrompt, array $args = array())
    {
        if (!$this->isEnabled()) {
            return new WP_Error('mj_openai_disabled', __('La clé API OpenAI est manquante.', 'mj-member'));
        }

        $userPrompt = trim($userPrompt);
        if ($userPrompt === '') {
            return new WP_Error('mj_openai_empty_prompt', __('Le prompt est vide.', 'mj-member'));
        }

        $model = isset($args['model']) && is_string($args['model']) && trim($args['model']) !== ''
            ? trim($args['model'])
            : \apply_filters('mj_member_openai_text_model', self::DEFAULT_TEXT_MODEL);

        $maxTokens = isset($args['max_tokens']) && is_int($args['max_tokens']) && $args['max_tokens'] > 0
            ? $args['max_tokens']
            : (int) \apply_filters('mj_member_openai_text_max_tokens', 1024);

        $temperature = isset($args['temperature']) && is_numeric($args['temperature'])
            ? (float) $args['temperature']
            : (float) \apply_filters('mj_member_openai_text_temperature', 0.7);

        $messages = array();
        $systemPrompt = trim($systemPrompt);
        if ($systemPrompt !== '') {
            $messages[] = array('role' => 'system', 'content' => $systemPrompt);
        }
        $messages[] = array('role' => 'user', 'content' => $userPrompt);

        $payload = array(
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        );

        $requestArgs = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => (int) \apply_filters('mj_member_openai_text_timeout', 30),
        );

        $response = \wp_remote_post(self::TEXT_ENDPOINT, $requestArgs);
        if (is_wp_error($response)) {
            return new WP_Error('mj_openai_http', sprintf(__('Erreur de communication avec OpenAI : %s', 'mj-member'), $response->get_error_message()));
        }

        $statusCode = \wp_remote_retrieve_response_code($response);
        $rawBody = \wp_remote_retrieve_body($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = __('Erreur inconnue lors de la génération du texte.', 'mj-member');
            if ($rawBody !== '') {
                $decodedError = json_decode($rawBody, true);
                if (is_array($decodedError) && isset($decodedError['error']['message'])) {
                    $errorMessage = \sanitize_text_field((string) $decodedError['error']['message']);
                }
            }

            return new WP_Error('mj_openai_api_error', $errorMessage, array('status' => $statusCode));
        }

        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) || empty($decoded['choices'][0]['message']['content'])) {
            return new WP_Error('mj_openai_invalid_payload', __('Réponse OpenAI invalide.', 'mj-member'));
        }

        $text = (string) $decoded['choices'][0]['message']['content'];
        $usageData = isset($decoded['usage']) && is_array($decoded['usage']) ? $decoded['usage'] : array();
        $usedModel = isset($decoded['model']) && is_string($decoded['model']) ? $decoded['model'] : $model;

        return array(
            'text' => $text,
            'model' => $usedModel,
            'usage' => $usageData,
        );
    }

    /**
     * Analyse une photo d'objet et retourne les champs du formulaire inventaire.
     *
     * @param string $imagePath Chemin absolu de l'image temporaire.
     * @param array<int,object|array> $categories Catégories disponibles.
     * @return array<string,mixed>|WP_Error
     */
    public function analyzeInventoryPhoto(string $imagePath, array $categories = array())
    {
        if (!$this->isEnabled() || !is_readable($imagePath) || !function_exists('imagecreatefromstring')) {
            return new WP_Error('mj_inventory_ai_unavailable', __('Analyse IA indisponible.', 'mj-member'));
        }

        $raw = file_get_contents($imagePath);
        $source = $raw === false ? false : imagecreatefromstring($raw);
        if (!$source) {
            return new WP_Error('mj_inventory_ai_image', __('Image invalide.', 'mj-member'));
        }
        $source = $this->correctImageOrientation($source, $imagePath);
        $width = imagesx($source);
        $height = imagesy($source);
        // 512px max côté + qualité 70 : suffisant pour l'identification d'objet, réduit fortement le coût en tokens.
        $scale = min(1, 512 / max($width, $height));
        $target = imagecreatetruecolor(max(1, (int) ($width * $scale)), max(1, (int) ($height * $scale)));
        imagecopyresampled($target, $source, 0, 0, 0, 0, imagesx($target), imagesy($target), $width, $height);
        imagedestroy($source);
        ob_start();
        imagejpeg($target, null, 70);
        $encoded = base64_encode((string) ob_get_clean());
        imagedestroy($target);

        $categoryLines = array();
        foreach ($categories as $category) {
            $category = (array) $category;
            $categoryLines[] = sprintf('%d: %s', (int) ($category['id'] ?? 0), sanitize_text_field((string) ($category['name'] ?? '')));
        }
        $categoryText = $categoryLines ? implode(', ', $categoryLines) : '(aucune catégorie existante)';
        $payload = array(
            'model' => 'gpt-4o',
            'response_format' => array('type' => 'json_object'),
            'messages' => array(
                array('role' => 'system', 'content' => 'Tu es un assistant chargé de cataloguer du matériel pour une maison de jeunes. Retourne uniquement un objet JSON valide avec les clés name, description, status, category_id, new_category_name, new_category_icon, safety_note_long et safety_note_short. status doit être exactement good, damaged ou broken selon l état visible de l objet. Vérifie d abord si une catégorie existante correspond à l objet et utilise son category_id. Ne propose new_category_name et new_category_icon que si aucune catégorie existante ne correspond. N invente pas de marque, de modèle ou de danger non visible.'),
                array('role' => 'user', 'content' => array(
                    array('type' => 'text', 'text' => 'Identifie cet objet. Catégories disponibles: ' . $categoryText),
                    array('type' => 'image_url', 'image_url' => array('url' => 'data:image/jpeg;base64,' . $encoded, 'detail' => 'low')),
                )),
            ),
            'max_tokens' => 800,
            'temperature' => 0.2,
        );
        $response = wp_remote_post(self::TEXT_ENDPOINT, array(
            'headers' => array('Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'),
            'body' => wp_json_encode($payload),
            'timeout' => 45,
        ));
        if (is_wp_error($response)) {
            return $response;
        }
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        $content = $decoded['choices'][0]['message']['content'] ?? '';
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', (string) $content);
        $result = json_decode(trim((string) $content), true);
        if (!is_array($result)) {
            return new WP_Error('mj_inventory_ai_response', __('Réponse IA invalide.', 'mj-member'));
        }
        return array(
            'name' => sanitize_text_field((string) ($result['name'] ?? '')),
            'description' => sanitize_textarea_field((string) ($result['description'] ?? '')),
            'status' => isset(MjInventory::STATUSES[$result['status'] ?? '']) ? (string) $result['status'] : 'good',
            'category_id' => !empty($result['category_id']) ? absint($result['category_id']) : null,
            'new_category_name' => sanitize_text_field((string) ($result['new_category_name'] ?? '')),
            'new_category_icon' => sanitize_text_field((string) ($result['new_category_icon'] ?? '')),
            'safety_note_long' => sanitize_textarea_field((string) ($result['safety_note_long'] ?? '')),
            'safety_note_short' => sanitize_text_field((string) ($result['safety_note_short'] ?? '')),
        );
    }

    private function correctImageOrientation($image, string $path)
    {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) {
            return $image;
        }
        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation === 3) {
            $image = imagerotate($image, 180, 0);
        } elseif ($orientation === 6) {
            $image = imagerotate($image, -90, 0);
        } elseif ($orientation === 8) {
            $image = imagerotate($image, 90, 0);
        }
        return $image;
    }

    /**
     * Calls OpenAI Images API to generate an image from a text prompt.
     *
     * @param string $prompt Prompt describing the requested image.
     * @param array<string,mixed> $args Optional overrides (model, size).
     * @return array<string,mixed>|WP_Error
     */
    public function generateImageFromPrompt(string $prompt, array $args = array())
    {
        if (!$this->isEnabled()) {
            return new WP_Error('mj_openai_disabled', __('La clé API OpenAI est manquante.', 'mj-member'));
        }

        $prompt = trim($prompt);
        if ($prompt === '') {
            return new WP_Error('mj_openai_empty_prompt', __('Le prompt est vide.', 'mj-member'));
        }

        $model = isset($args['model']) && is_string($args['model']) ? trim($args['model']) : self::DEFAULT_MODEL;
        if ($model === '') {
            $model = self::DEFAULT_MODEL;
        }

        $size = isset($args['size']) && is_string($args['size']) ? trim($args['size']) : self::DEFAULT_SIZE;
        if ($size === '') {
            $size = self::DEFAULT_SIZE;
        }

        $payload = array(
            'model' => $model,
            'prompt' => $prompt,
            'size' => $size,
        );

        $requestArgs = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode($payload),
            'timeout' => (int) apply_filters('mj_member_openai_image_timeout', 45),
        );

        $response = wp_remote_post(self::GENERATIONS_ENDPOINT, $requestArgs);
        if (is_wp_error($response)) {
            return new WP_Error('mj_openai_http', sprintf(__('Erreur de communication avec OpenAI : %s', 'mj-member'), $response->get_error_message()));
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $rawBody = wp_remote_retrieve_body($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMessage = __('Erreur inconnue lors de la génération de l’image.', 'mj-member');
            if ($rawBody !== '') {
                $decodedError = json_decode($rawBody, true);
                if (is_array($decodedError) && isset($decodedError['error']['message'])) {
                    $errorMessage = sanitize_text_field((string) $decodedError['error']['message']);
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
        $directUrl = isset($imageData['url']) ? esc_url_raw((string) $imageData['url']) : '';
        $usage = isset($decoded['usage']) && is_array($decoded['usage']) ? $decoded['usage'] : array();

        if ($outputBase64 === '' && $directUrl !== '') {
            $download = wp_remote_get($directUrl, array('timeout' => 45));
            if (is_wp_error($download)) {
                return new WP_Error('mj_openai_download_failed', sprintf(__('Impossible de télécharger l\'image générée : %s', 'mj-member'), $download->get_error_message()));
            }

            $body = wp_remote_retrieve_body($download);
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
