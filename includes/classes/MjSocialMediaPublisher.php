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
     * @param string               $message   The message/caption to post.
     * @param string               $link      The event URL to share.
     * @param array<int,string>    $imageUrls Optional image URLs to attach directly.
     * @return array{success: bool, message: string, url?: string}|WP_Error
     */
    public function publishToFacebook($message, $link, $imageUrls = array())
    {
        if (!$this->facebookPageToken || !$this->facebookPageId) {
            return new \WP_Error(
                'mj_facebook_not_configured',
                __('Facebook n\'est pas configuré (token ou ID de page manquant).', 'mj-member')
            );
        }

        $message = trim((string) $message);
        $link = trim((string) $link);

        $messageWithEventUrl = $message;
        if ($link !== '' && strpos($messageWithEventUrl, $link) === false) {
            $messageWithEventUrl = $messageWithEventUrl !== ''
                ? ($messageWithEventUrl . "\n\n" . $link)
                : $link;
        }

        if ($messageWithEventUrl === '') {
            return new \WP_Error(
                'mj_facebook_empty_content',
                __('Le message et le lien ne peuvent pas être vides.', 'mj-member')
            );
        }

        $payload = array(
            'message' => $messageWithEventUrl,
        );

        $imageUrls = is_array($imageUrls) ? $imageUrls : array();
        $imageUrls = array_values(array_filter(array_map(function ($url) {
            $candidate = esc_url_raw((string) $url);
            return ($candidate !== '' && wp_http_validate_url($candidate)) ? $candidate : '';
        }, $imageUrls)));
        $imageUrls = array_values(array_unique($imageUrls));

        if (!empty($imageUrls)) {
            $photoEndpoint = self::FACEBOOK_API_BASE . '/' . $this->facebookPageId . '/photos';

            if (count($imageUrls) === 1) {
                $singleMessage = $messageWithEventUrl;

                $singlePhotoPayload = array(
                    'url' => $imageUrls[0],
                    'published' => 'true',
                );
                if ($singleMessage !== '') {
                    $singlePhotoPayload['message'] = $singleMessage;
                }

                $singlePhotoResult = $this->makeApiRequestForm($photoEndpoint, $singlePhotoPayload, $this->facebookPageToken, 'POST');
                if (is_wp_error($singlePhotoResult)) {
                    return $singlePhotoResult;
                }

                $singlePostId = isset($singlePhotoResult['id']) ? (string) $singlePhotoResult['id'] : '';
                return array(
                    'success' => true,
                    'message' => __('Publication réussie !', 'mj-member'),
                    'postId' => $singlePostId,
                );
            }

            $attachedMedia = array();

            foreach ($imageUrls as $imageUrl) {
                $photoResult = $this->makeApiRequestForm($photoEndpoint, array(
                    'url' => $imageUrl,
                    'published' => 'false',
                ), $this->facebookPageToken, 'POST');

                if (is_wp_error($photoResult)) {
                    return $photoResult;
                }

                $photoId = isset($photoResult['id']) ? (string) $photoResult['id'] : '';
                if ($photoId !== '') {
                    $attachedMedia[] = $photoId;
                }
            }

            if (!empty($attachedMedia)) {
                foreach ($attachedMedia as $index => $mediaId) {
                    $payload['attached_media[' . $index . ']'] = wp_json_encode(array('media_fbid' => $mediaId));
                }
            }
        }

        $endpoint = self::FACEBOOK_API_BASE . '/' . $this->facebookPageId . '/feed';

        $feedResult = $this->makeApiRequestForm($endpoint, $payload, $this->facebookPageToken, 'POST');
        if (is_wp_error($feedResult)) {
            return $feedResult;
        }

        $postId = isset($feedResult['id']) ? (string) $feedResult['id'] : '';
        return array(
            'success' => true,
            'message' => __('Publication réussie !', 'mj-member'),
            'postId' => $postId,
        );
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
            $errorMsg     = __('Erreur inconnue lors de la publication.', 'mj-member');
            $apiCode      = 0;
            $tokenExpired = false;
            $permError    = false;

            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $apiError = isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : array();
                    $apiCode  = isset($apiError['code']) ? (int) $apiError['code'] : 0;
                    $rawMsg   = isset($apiError['message']) ? (string) $apiError['message']
                              : (isset($decoded['message']) ? (string) $decoded['message'] : '');

                    if ($apiCode === 190 || strpos($rawMsg, 'Session has expired') !== false || strpos($rawMsg, 'access token') !== false) {
                        // Token expired or invalid
                        $tokenExpired = true;
                        $errorMsg = __('Le token d\'accès a expiré ou est invalide. Renouvelez-le dans Paramètres → Publier sur les réseaux.', 'mj-member');
                    } elseif ($apiCode === 200) {
                        // Insufficient permissions
                        $permError = true;
                        $errorMsg = __('Permissions insuffisantes sur le token. Pour une Page Facebook, le token doit être un Page Access Token avec les permissions pages_read_engagement et pages_manage_posts. Obtenez-le via Graph API Explorer → Génerer → Open in Access Token Tool → "Get Page Access Token".', 'mj-member');
                    } elseif ($rawMsg !== '') {
                        $errorMsg = sanitize_text_field($rawMsg);
                    }
                }
            }

            return new \WP_Error('mj_social_api_error', $errorMsg, array(
                'status'       => $statusCode,
                'apiCode'      => $apiCode,
                'tokenExpired' => $tokenExpired,
                'permError'    => $permError,
            ));
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
     * Graph API form-data request handler (used for Facebook media attachment flow).
     *
     * @param string $endpoint Full API endpoint URL.
     * @param array  $payload Request body fields.
     * @param string $token Access token.
     * @param string $method HTTP method.
     * @return array|WP_Error
     */
    private function makeApiRequestForm($endpoint, $payload, $token, $method = 'POST')
    {
        $endpoint = add_query_arg('access_token', $token, $endpoint);

        $args = array(
            'method' => $method,
            'body' => $method === 'POST' ? $payload : null,
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
            $errorMsg     = __('Erreur inconnue lors de la publication.', 'mj-member');
            $apiCode      = 0;
            $tokenExpired = false;
            $permError    = false;

            if ($body !== '') {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $apiError = isset($decoded['error']) && is_array($decoded['error']) ? $decoded['error'] : array();
                    $apiCode  = isset($apiError['code']) ? (int) $apiError['code'] : 0;
                    $rawMsg   = isset($apiError['message']) ? (string) $apiError['message']
                              : (isset($decoded['message']) ? (string) $decoded['message'] : '');

                    if ($apiCode === 190 || strpos($rawMsg, 'Session has expired') !== false || strpos($rawMsg, 'access token') !== false) {
                        $tokenExpired = true;
                        $errorMsg = __('Le token d\'accès a expiré ou est invalide. Renouvelez-le dans Paramètres → Publier sur les réseaux.', 'mj-member');
                    } elseif ($apiCode === 200) {
                        $permError = true;
                        $errorMsg = __('Permissions insuffisantes sur le token. Pour une Page Facebook, le token doit être un Page Access Token avec les permissions pages_read_engagement et pages_manage_posts. Obtenez-le via Graph API Explorer → Génerer → Open in Access Token Tool → "Get Page Access Token".', 'mj-member');
                    } elseif ($rawMsg !== '') {
                        $errorMsg = sanitize_text_field($rawMsg);
                    }
                }
            }

            return new \WP_Error('mj_social_api_error', $errorMsg, array(
                'status'       => $statusCode,
                'apiCode'      => $apiCode,
                'tokenExpired' => $tokenExpired,
                'permError'    => $permError,
            ));
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return new \WP_Error(
                'mj_social_invalid_response',
                __('Réponse API invalide.', 'mj-member')
            );
        }

        return $decoded;
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
