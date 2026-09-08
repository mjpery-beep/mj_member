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
     * Publish a video to a Facebook page.
     *
     * @param string $message Caption/description.
     * @param string $link Event URL.
    * @param string $videoUrl Public video URL.
    * @param string $videoPath Optional local file path for binary upload.
     * @return array|WP_Error
     */
    public function publishVideoToFacebook($message, $link, $videoUrl)
    {
        if (!$this->facebookPageToken || !$this->facebookPageId) {
            return new \WP_Error(
                'mj_facebook_not_configured',
                __('Facebook n\'est pas configuré (token ou ID de page manquant).', 'mj-member')
            );
        }

        $videoUrl = esc_url_raw((string) $videoUrl);
        if ($videoUrl === '' || !wp_http_validate_url($videoUrl)) {
            return new \WP_Error('mj_facebook_invalid_video', __('URL vidéo invalide.', 'mj-member'));
        }

        $description = trim((string) $message);
        $link = trim((string) $link);
        if ($link !== '' && strpos($description, $link) === false) {
            $description = $description !== '' ? $description . "\n\n" . $link : $link;
        }

        $result = $this->makeApiRequestForm(
            self::FACEBOOK_API_BASE . '/' . $this->facebookPageId . '/videos',
            array(
                'file_url' => $videoUrl,
                'description' => $description,
            ),
            $this->facebookPageToken,
            'POST'
        );

        if (is_wp_error($result)) {
            return $result;
        }

        return array(
            'success' => true,
            'message' => __('Vidéo publiée sur Facebook !', 'mj-member'),
            'postId' => isset($result['id']) ? (string) $result['id'] : '',
        );
    }

    /**
     * Publish a hosted video as a Facebook Page Reel.
     *
     * @param string $message Caption/description.
     * @param string $link Event URL.
     * @param string $videoUrl Public video URL.
     * @return array|WP_Error
     */
    public function publishReelToFacebook($message, $link, $videoUrl, $videoPath = '')
    {
        if (!$this->facebookPageToken || !$this->facebookPageId) {
            return new \WP_Error('mj_facebook_not_configured', __('Facebook n\'est pas configuré.', 'mj-member'));
        }

        $videoUrl = esc_url_raw((string) $videoUrl);
        if ($videoUrl === '' || !wp_http_validate_url($videoUrl)) {
            return new \WP_Error('mj_facebook_invalid_video', __('URL vidéo invalide.', 'mj-member'));
        }

        $description = trim((string) $message);
        $link = trim((string) $link);
        if ($link !== '' && strpos($description, $link) === false) {
            $description = $description !== '' ? $description . "\n\n" . $link : $link;
        }

        $start = $this->makeApiRequestForm(
            self::FACEBOOK_API_BASE . '/' . $this->facebookPageId . '/video_reels',
            array('upload_phase' => 'start'),
            $this->facebookPageToken,
            'POST'
        );
        if (is_wp_error($start)) {
            return $start;
        }

        $videoId = isset($start['video_id']) ? (string) $start['video_id'] : '';
        $uploadUrl = isset($start['upload_url']) ? (string) $start['upload_url'] : '';
        if ($videoId === '' || $uploadUrl === '') {
            return new \WP_Error('mj_facebook_reel_upload_init', __('Facebook n\'a pas fourni de session d\'upload Reel.', 'mj-member'));
        }

        $uploadHeaders = array(
            'Authorization' => 'OAuth ' . $this->facebookPageToken,
            'offset' => '0',
        );
        $uploadArgs = array(
            'headers' => $uploadHeaders,
            'timeout' => 180,
        );
        $videoPath = (string) $videoPath;
        if ($videoPath !== '' && is_readable($videoPath) && filesize($videoPath) > 0) {
            $videoContents = file_get_contents($videoPath);
            if ($videoContents === false) {
                return new \WP_Error('mj_facebook_reel_file_read', __('Le fichier vidéo ne peut pas être lu par le serveur.', 'mj-member'));
            }
            $uploadArgs['headers']['Content-Type'] = 'application/octet-stream';
            $uploadArgs['headers']['file_size'] = (string) filesize($videoPath);
            $uploadArgs['body'] = $videoContents;
        } else {
            $uploadArgs['headers']['file_url'] = $videoUrl;
        }
        $upload = wp_remote_post($uploadUrl, $uploadArgs);
        if (is_wp_error($upload)) {
            return new \WP_Error('mj_facebook_reel_upload', $upload->get_error_message());
        }
        $upload_status = wp_remote_retrieve_response_code($upload);
        $upload_body = wp_remote_retrieve_body($upload);
        $upload_data = json_decode($upload_body, true);
        $upload_error = is_array($upload_data) && isset($upload_data['error']) && is_array($upload_data['error'])
            ? $upload_data['error']
            : array();
        $upload_message = isset($upload_error['message']) ? (string) $upload_error['message'] : '';
        if ($upload_status < 200 || $upload_status >= 300 || (is_array($upload_data) && isset($upload_data['success']) && !$upload_data['success'])) {
            if ($upload_message === '' && is_array($upload_data) && isset($upload_data['message'])) {
                $upload_message = (string) $upload_data['message'];
            }
            if ($upload_message === '') {
                $upload_message = __('Facebook n\'a pas pu téléverser la vidéo du Reel. Vérifiez que l\'URL vidéo est publique et que la vidéo respecte le format Reel.', 'mj-member');
            }
            return new \WP_Error('mj_facebook_reel_upload', sanitize_text_field($upload_message), array(
                'status' => $upload_status,
                'apiCode' => isset($upload_error['code']) ? (int) $upload_error['code'] : 0,
            ));
        }

        $finish = $this->makeApiRequestForm(
            self::FACEBOOK_API_BASE . '/' . $this->facebookPageId . '/video_reels',
            array(
                'video_id' => $videoId,
                'upload_phase' => 'finish',
                'video_state' => 'PUBLISHED',
                'description' => $description,
            ),
            $this->facebookPageToken,
            'POST'
        );
        if (is_wp_error($finish)) {
            return $finish;
        }

        return array(
            'success' => true,
            'message' => __('Reel Facebook publié !', 'mj-member'),
            'postId' => $videoId,
        );
    }

    /**
     * Publish to Instagram (business account) via the new Instagram Graph API.
     * Requires a two-step flow: create media container, then publish it.
     *
     * @param string $caption The caption/description.
     * @param string $link The event URL (appended to caption).
    * @param string $imageUrl Optional image URL for the post.
    * @param string $videoUrl Optional video URL; publishes an Instagram Reel when set.
     * @return array{success: bool, message: string, postId?: string}|WP_Error
     */
    public function publishToInstagram($caption, $link, $imageUrl = '', $videoUrl = '')
    {
        if (!$this->instagramAccessToken || !$this->instagramBusinessAccountId) {
            return new \WP_Error(
                'mj_instagram_not_configured',
                __('Instagram n\'est pas configuré (token ou ID de compte manquant).', 'mj-member')
            );
        }

        $caption = trim((string) $caption);
        $link    = trim((string) $link);

        if ($caption === '' && $link === '') {
            return new \WP_Error(
                'mj_instagram_empty_content',
                __('La légende et le lien ne peuvent pas être vides.', 'mj-member')
            );
        }

        $fullCaption = $caption;
        if ($link !== '') {
            $fullCaption = $fullCaption !== '' ? $fullCaption . "\n\n" . $link : $link;
        }

        $videoUrl = esc_url_raw((string) $videoUrl);
        if ($videoUrl !== '' && !wp_http_validate_url($videoUrl)) {
            return new \WP_Error('mj_instagram_invalid_video', __('URL vidéo invalide.', 'mj-member'));
        }

        // Instagram requires media for posts; a video is published as a Reel.
        $imageUrl = trim((string) $imageUrl);
        if ($videoUrl === '' && $imageUrl === '') {
            return new \WP_Error(
                'mj_instagram_no_image',
                __('Instagram nécessite une photo ou une vidéo pour publier.', 'mj-member')
            );
        }

        $igUserId = $this->instagramBusinessAccountId;

        // Step 1 — Create media container
        $containerEndpoint = self::INSTAGRAM_API_BASE . '/' . $igUserId . '/media';
        $containerPayload = array('caption' => $fullCaption);
        if ($videoUrl !== '') {
            $containerPayload['media_type'] = 'REELS';
            $containerPayload['video_url'] = $videoUrl;
        } else {
            $containerPayload['image_url'] = $imageUrl;
        }

        $containerResult = $this->makeApiRequest($containerEndpoint, $containerPayload, $this->instagramAccessToken, 'POST');
        if (is_wp_error($containerResult)) {
            return $containerResult;
        }

        $creationId = isset($containerResult['id']) ? (string) $containerResult['id'] : '';
        if ($creationId === '') {
            return new \WP_Error(
                'mj_instagram_no_container_id',
                __('Instagram : impossible de créer le container media (ID manquant).', 'mj-member')
            );
        }

        // Step 2 — Publish the container
        $publishEndpoint = self::INSTAGRAM_API_BASE . '/' . $igUserId . '/media_publish';
        $publishPayload  = array('creation_id' => $creationId);

        $publishResult = $this->makeApiRequest($publishEndpoint, $publishPayload, $this->instagramAccessToken, 'POST');
        if (is_wp_error($publishResult)) {
            return $publishResult;
        }

        $postId = isset($publishResult['id']) ? (string) $publishResult['id'] : '';
        return array(
            'success' => true,
            'message' => $videoUrl !== '' ? __('Réel publié sur Instagram !', 'mj-member') : __('Publication réussie !', 'mj-member'),
            'postId'  => $postId,
        );
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

                    if ($apiCode === 190 || strpos($rawMsg, 'Session has expired') !== false) {
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

                    if ($apiCode === 190 || strpos($rawMsg, 'Session has expired') !== false) {
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
