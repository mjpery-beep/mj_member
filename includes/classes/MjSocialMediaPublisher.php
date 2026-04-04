<?php

namespace Mj\Member\Classes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles direct publishing to Facebook, Instagram, and WhatsApp via their APIs.
 */
final class MjSocialMediaPublisher
{
    private const FACEBOOK_API_BASE = 'https://graph.facebook.com/v18.0';
    private const INSTAGRAM_API_BASE = 'https://graph.facebook.com/v18.0';
    private const WHATSAPP_API_BASE = 'https://graph.facebook.com/v18.0';

    private $facebookPageToken;
    private $facebookPageId;
    private $instagramBusinessAccountId;
    private $instagramAccessToken;
    private $whatsappBusinessPhoneNumberId;
    private $whatsappBusinessAccountId;
    private $whatsappAccessToken;

    public function __construct()
    {
        $this->facebookPageToken = (string) get_option('mj_social_facebook_page_token', '');
        $this->facebookPageId = (string) get_option('mj_social_facebook_page_id', '');
        $this->instagramBusinessAccountId = (string) get_option('mj_social_instagram_business_id', '');
        $this->instagramAccessToken = (string) get_option('mj_social_instagram_access_token', '');
        $this->whatsappBusinessPhoneNumberId = (string) get_option('mj_social_whatsapp_phone_number_id', '');
        $this->whatsappBusinessAccountId = (string) get_option('mj_social_whatsapp_business_id', '');
        $this->whatsappAccessToken = (string) get_option('mj_social_whatsapp_access_token', '');
    }

    /**
     * Publish to Facebook page.
     *
     * @param string $message The message/caption to post.
     * @param string $link The event URL to share.
     * @return array{success: bool, message: string, url?: string}|WP_Error
     */
    public function publishToFacebook($message, $link)
    {
        if (!$this->facebookPageToken || !$this->facebookPageId) {
            return new \WP_Error(
                'mj_facebook_not_configured',
                __('Facebook n\'est pas configuré (token ou ID de page manquant).', 'mj-member')
            );
        }

        $message = trim((string) $message);
        $link = trim((string) $link);

        if ($message === '' && $link === '') {
            return new \WP_Error(
                'mj_facebook_empty_content',
                __('Le message et le lien ne peuvent pas être vides.', 'mj-member')
            );
        }

        $payload = array(
            'message' => $message !== '' ? $message : '',
            'link' => $link !== '' ? $link : '',
        );

        if ($message !== '' && $link !== '') {
            $payload['message'] = $message . "\n\n" . $link;
            unset($payload['link']);
        }

        $endpoint = self::FACEBOOK_API_BASE . '/' . $this->facebookPageId . '/feed';

        return $this->makeApiRequest($endpoint, $payload, $this->facebookPageToken, 'POST');
    }

    /**
     * Publish to Instagram (business account).
     *
     * @param string $caption The caption/description.
     * @param string $link The event URL.
     * @param string $imageUrl Optional image URL for the post.
     * @return array{success: bool, message: string, url?: string}|WP_Error
     */
    public function publishToInstagram($caption, $link, $imageUrl = '')
    {
        if (!$this->instagramAccessToken || !$this->instagramBusinessAccountId) {
            return new \WP_Error(
                'mj_instagram_not_configured',
                __('Instagram n\'est pas configuré (token ou ID de compte manquant).', 'mj-member')
            );
        }

        $caption = trim((string) $caption);
        $link = trim((string) $link);

        if ($caption === '' && $link === '') {
            return new \WP_Error(
                'mj_instagram_empty_content',
                __('La légende et le lien ne peuvent pas être vides.', 'mj-member')
            );
        }

        // Instagram requires image for carousels/reels; text-only posts not supported via API
        // So we combine caption + link
        $fullCaption = $caption !== '' ? $caption : '';
        if ($link !== '') {
            $fullCaption = $fullCaption !== '' 
                ? $fullCaption . "\n\n" . $link 
                : $link;
        }

        $payload = array(
            'caption' => $fullCaption,
        );

        // If image URL provided, include it (requires separate image container creation)
        if ($imageUrl !== '') {
            $payload['image_url'] = $imageUrl;
        }

        $endpoint = self::INSTAGRAM_API_BASE . '/' . $this->instagramBusinessAccountId . '/media';

        return $this->makeApiRequest($endpoint, $payload, $this->instagramAccessToken, 'POST');
    }

    /**
     * Send message to WhatsApp group.
     *
     * @param string $groupId The WhatsApp group ID (from invite link).
     * @param string $message The message to send.
     * @return array{success: bool, message: string, url?: string}|WP_Error
     */
    public function publishToWhatsApp($groupId, $message)
    {
        if (!$this->whatsappAccessToken) {
            return new \WP_Error(
                'mj_whatsapp_not_configured',
                __('WhatsApp n\'est pas configuré (token d\'accès manquant).', 'mj-member')
            );
        }

        $message = trim((string) $message);
        $groupId = trim((string) $groupId);

        if ($message === '') {
            return new \WP_Error(
                'mj_whatsapp_empty_message',
                __('Le message ne peut pas être vide.', 'mj-member')
            );
        }

        $payload = array(
            'messaging_product' => 'whatsapp',
            'to' => $groupId,
            'type' => 'text',
            'text' => array(
                'preview_url' => true,
                'body' => $message,
            ),
        );

        $endpoint = self::WHATSAPP_API_BASE . '/messages';

        return $this->makeApiRequest($endpoint, $payload, $this->whatsappAccessToken, 'POST');
    }

    /**
     * Generic API request handler.
     *
     * @param string $endpoint Full API endpoint URL.
     * @param array $payload Request body.
     * @param string $token Access token.
     * @param string $method HTTP method (POST, GET, etc.).
     * @return array{success: bool, message: string, url?: string}|WP_Error
     */
    private function makeApiRequest($endpoint, $payload, $token, $method = 'POST')
    {
        // Meta Graph API requires access_token as a query parameter, not in the JSON body.
        $endpoint = add_query_arg('access_token', $token, $endpoint);

        $args = array(
            'method' => $method,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => $method === 'POST' ? wp_json_encode($payload) : null,
            'timeout' => 30,
        );

        $response = wp_remote_request($endpoint, $args);

        if (is_wp_error($response)) {
            return new \WP_Error(
                'mj_social_http_error',
                sprintf(
                    __('Erreur de communication avec l\'API : %s', 'mj-member'),
                    $response->get_error_message()
                )
            );
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorMsg = __('Erreur inconnue lors de la publication.', 'mj-member');

            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    if (isset($decoded['error']['message'])) {
                        $errorMsg = sanitize_text_field((string) $decoded['error']['message']);
                    } elseif (isset($decoded['message'])) {
                        $errorMsg = sanitize_text_field((string) $decoded['message']);
                    }
                }
            }

            return new \WP_Error('mj_social_api_error', $errorMsg, array('status' => $statusCode));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new \WP_Error(
                'mj_social_invalid_response',
                __('Réponse API invalide.', 'mj-member')
            );
        }

        // Extract post/content ID from response
        $postId = isset($decoded['id']) ? (string) $decoded['id'] : '';

        return array(
            'success' => true,
            'message' => __('Publication réussie !', 'mj-member'),
            'postId' => $postId,
        );
    }

    /**
     * Check if Facebook is configured.
     */
    public function isFacebookConfigured()
    {
        return $this->facebookPageToken !== '' && $this->facebookPageId !== '';
    }

    /**
     * Check if Instagram is configured.
     */
    public function isInstagramConfigured()
    {
        return $this->instagramAccessToken !== '' && $this->instagramBusinessAccountId !== '';
    }

    /**
     * Check if WhatsApp is configured.
     */
    public function isWhatsAppConfigured()
    {
        return $this->whatsappAccessToken !== '' && $this->whatsappBusinessPhoneNumberId !== '';
    }
}
