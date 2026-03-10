<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use Mj\Member\Classes\Crud\MjPushSubscriptions;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Service d'envoi de notifications Web Push via la lib minishlink/web-push.
 */
class MjWebPush
{
    /**
     * Envoie une notification push à un ensemble de member_ids.
     *
     * @param array<int,int>      $member_ids
     * @param array<string,mixed> $payload  {title, body, icon?, url?, tag?, badge?}
     * @return array{sent:int,failed:int,removed:int}
     */
    public static function send_to_members(array $member_ids, array $payload): array
    {
        $subscriptions = array();
        foreach ($member_ids as $member_id) {
            $subs = MjPushSubscriptions::get_for_member((int) $member_id);
            if (empty($subs)) {
                self::log('send_to_members: member_id=' . $member_id . ' has NO active push subscription');
            }
            foreach ($subs as $sub) {
                $subscriptions[] = $sub;
            }
        }

        if (empty($subscriptions)) {
            self::log('send_to_members: 0 total subscriptions for member_ids=' . wp_json_encode($member_ids) . ' — push will NOT be sent');
        }

        return self::dispatch($subscriptions, $payload);
    }

    /**
     * Envoie une notification push à un ensemble de user_ids WordPress.
     *
     * @param array<int,int>      $user_ids
     * @param array<string,mixed> $payload
     * @return array{sent:int,failed:int,removed:int}
     */
    public static function send_to_users(array $user_ids, array $payload): array
    {
        $subscriptions = array();
        foreach ($user_ids as $user_id) {
            $subs = MjPushSubscriptions::get_for_user((int) $user_id);
            if (empty($subs)) {
                self::log('send_to_users: user_id=' . $user_id . ' has NO active push subscription');
            }
            foreach ($subs as $sub) {
                $subscriptions[] = $sub;
            }
        }

        if (empty($subscriptions)) {
            self::log('send_to_users: 0 total subscriptions for user_ids=' . wp_json_encode($user_ids) . ' — push will NOT be sent');
        }

        return self::dispatch($subscriptions, $payload);
    }

    /**
     * @param array<int,object>   $db_subscriptions Rows from mj_push_subscriptions
     * @param array<string,mixed> $payload
     * @return array{sent:int,failed:int,removed:int}
     */
    private static function dispatch(array $db_subscriptions, array $payload): array
    {
        $stats = array('sent' => 0, 'failed' => 0, 'removed' => 0);

        if (empty($db_subscriptions)) {
            self::log('dispatch() SKIP: no subscriptions to send to');
            return $stats;
        }

        if (!Config::webPushIsReady()) {
            self::log('dispatch() SKIP: VAPID keys not configured');
            return $stats;
        }

        if (!class_exists(WebPush::class)) {
            self::log('WebPush class not found – is minishlink/web-push installed?');
            return $stats;
        }

        $auth = array(
            'VAPID' => array(
                'subject'    => Config::vapidSubject(),
                'publicKey'  => Config::vapidPublicKey(),
                'privateKey' => Config::vapidPrivateKey(),
            ),
        );

        try {
            $webPush = new WebPush($auth);
            $webPush->setAutomaticPadding(false);
        } catch (\Exception $e) {
            self::log('WebPush init error: ' . $e->getMessage());
            return $stats;
        }

        $normalized = self::normalize_payload($payload);
        $json_payload = wp_json_encode($normalized);

        self::log('dispatch() payload: ' . $json_payload);
        self::log('dispatch() subscriptions count: ' . count($db_subscriptions));

        $endpoint_map = array(); // endpoint → db row id
        $queued = 0;

        foreach ($db_subscriptions as $sub) {
            $endpoint = isset($sub->endpoint) ? (string) $sub->endpoint : '';
            if ($endpoint === '') {
                self::log('Skipping sub id=' . ($sub->id ?? '?') . ' – empty endpoint');
                continue;
            }

            // éviter les doublons d'endpoint dans le même batch
            if (isset($endpoint_map[$endpoint])) {
                self::log('Skipping duplicate endpoint for sub id=' . ($sub->id ?? '?'));
                continue;
            }
            $endpoint_map[$endpoint] = (int) $sub->id;

            $pub_key = isset($sub->public_key) ? (string) $sub->public_key : '';
            $auth_tok = isset($sub->auth_token) ? (string) $sub->auth_token : '';
            $encoding = isset($sub->content_encoding) ? (string) $sub->content_encoding : 'aesgcm';

            self::log(sprintf(
                'Queuing sub id=%d: encoding=%s p256dh=%d chars auth=%d chars',
                (int) ($sub->id ?? 0),
                $encoding,
                strlen($pub_key),
                strlen($auth_tok)
            ));
            self::log('  FULL endpoint: ' . $endpoint);

            $subscription = Subscription::create(array(
                'endpoint'        => $endpoint,
                'publicKey'       => $pub_key,
                'authToken'       => $auth_tok,
                'contentEncoding' => $encoding,
            ));

            $webPush->queueNotification($subscription, $json_payload);
            $queued++;
        }

        self::log('dispatch() queued: ' . $queued);

        if ($queued === 0) {
            self::log('Nothing queued, returning early.');
            return $stats;
        }

        // Flush et traiter les résultats
        foreach ($webPush->flush() as $report) {
            $ep = $report->getEndpoint();
            $statusCode = 0;
            $responseBody = '';

            if (method_exists($report, 'getResponse') && $report->getResponse()) {
                $statusCode = $report->getResponse()->getStatusCode();
                $responseBody = substr((string) $report->getResponse()->getBody(), 0, 500);
            }

            if ($report->isSuccess()) {
                $stats['sent']++;
                self::log(sprintf(
                    'Push OK [%d]: endpoint=%s... body=%s',
                    $statusCode,
                    substr($ep, 0, 80),
                    $responseBody
                ));
            } else {
                $stats['failed']++;

                // Si 410 Gone ou 404, l'abonnement n'est plus valide → supprimer + blacklister
                if ($report->isSubscriptionExpired() || in_array($statusCode, array(404, 410), true)) {
                    MjPushSubscriptions::delete_by_endpoint($ep);
                    self::blacklist_endpoint($ep);
                    $stats['removed']++;
                }

                self::log(sprintf(
                    'Push FAILED [%d]: %s – endpoint=%s... body=%s',
                    $statusCode,
                    $report->getReason(),
                    substr($ep, 0, 80),
                    $responseBody
                ));
            }
        }

        self::log('dispatch() stats: ' . wp_json_encode($stats));
        return $stats;
    }

    /**
     * Normalise le payload envoyé au Service Worker.
     *
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function normalize_payload(array $payload): array
    {
        $site_name = get_bloginfo('name');
        $icon = get_site_icon_url(192);
        $badge = get_site_icon_url(96);

        return array(
            'title' => isset($payload['title']) ? (string) $payload['title'] : $site_name,
            'body'  => isset($payload['body']) ? (string) $payload['body'] : '',
            'icon'  => isset($payload['icon']) && $payload['icon'] !== '' ? (string) $payload['icon'] : $icon,
            'badge' => isset($payload['badge']) && $payload['badge'] !== '' ? (string) $payload['badge'] : $badge,
            'url'   => isset($payload['url']) ? (string) $payload['url'] : home_url('/'),
            'tag'   => isset($payload['tag']) ? (string) $payload['tag'] : '',
        );
    }

    /**
     * Ajoute un endpoint à la blacklist temporaire (après 410 Gone).
     * Empêche la ré-inscription immédiate du même endpoint périmé.
     *
     * @param string $endpoint
     * @return void
     */
    public static function blacklist_endpoint(string $endpoint): void
    {
        $key = 'mj_push_bl_' . md5($endpoint);
        set_transient($key, time(), 48 * HOUR_IN_SECONDS);
        self::log('Blacklisted endpoint: ' . substr($endpoint, 0, 90));
    }

    /**
     * Vérifie si un endpoint est blacklisté (récemment 410).
     *
     * @param string $endpoint
     * @return bool
     */
    public static function is_endpoint_blacklisted(string $endpoint): bool
    {
        $key = 'mj_push_bl_' . md5($endpoint);
        return get_transient($key) !== false;
    }

    /**
     * Retire un endpoint de la blacklist.
     *
     * @param string $endpoint
     * @return void
     */
    public static function unblacklist_endpoint(string $endpoint): void
    {
        $key = 'mj_push_bl_' . md5($endpoint);
        delete_transient($key);
    }

    /**
     * @param string $message
     * @return void
     */
    private static function log(string $message): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        // Always write to error_log (debug.log) for visibility
        error_log('[MJ WebPush] ' . $message);

        // Also write to structured Logger if available
        if (class_exists('Mj\Member\Core\Logger')) {
            \Mj\Member\Core\Logger::info('[WebPush] ' . $message, array(), 'web-push');
        }
    }
}
