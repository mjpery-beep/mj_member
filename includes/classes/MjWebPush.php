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
            foreach ($subs as $sub) {
                $subscriptions[] = $sub;
            }
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
            foreach ($subs as $sub) {
                $subscriptions[] = $sub;
            }
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

        if (empty($db_subscriptions) || !Config::webPushIsReady()) {
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

        $json_payload = wp_json_encode(self::normalize_payload($payload));

        $endpoint_map = array(); // endpoint → db row id

        foreach ($db_subscriptions as $sub) {
            $endpoint = isset($sub->endpoint) ? (string) $sub->endpoint : '';
            if ($endpoint === '') {
                continue;
            }

            // éviter les doublons d'endpoint dans le même batch
            if (isset($endpoint_map[$endpoint])) {
                continue;
            }
            $endpoint_map[$endpoint] = (int) $sub->id;

            $subscription = Subscription::create(array(
                'endpoint'        => $endpoint,
                'publicKey'       => isset($sub->public_key) ? (string) $sub->public_key : '',
                'authToken'       => isset($sub->auth_token) ? (string) $sub->auth_token : '',
                'contentEncoding' => isset($sub->content_encoding) ? (string) $sub->content_encoding : 'aesgcm',
            ));

            $webPush->queueNotification($subscription, $json_payload);
        }

        // Flush et traiter les résultats
        foreach ($webPush->flush() as $report) {
            $ep = $report->getEndpoint();

            if ($report->isSuccess()) {
                $stats['sent']++;
            } else {
                $stats['failed']++;

                // Si 410 Gone ou 404, l'abonnement n'est plus valide → supprimer
                $statusCode = 0;
                if (method_exists($report, 'getResponse') && $report->getResponse()) {
                    $statusCode = $report->getResponse()->getStatusCode();
                }
                if ($report->isSubscriptionExpired() || in_array($statusCode, array(404, 410), true)) {
                    MjPushSubscriptions::delete_by_endpoint($ep);
                    $stats['removed']++;
                }

                self::log(sprintf(
                    'Push failed [%d]: %s – endpoint: %s',
                    $statusCode,
                    $report->getReason(),
                    $ep
                ));
            }
        }

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
     * @param string $message
     * @return void
     */
    private static function log(string $message): void
    {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (class_exists('Mj\Member\Core\Logger')) {
            \Mj\Member\Core\Logger::info('[WebPush] ' . $message, array(), 'web-push');
        } else {
            error_log('[MJ WebPush] ' . $message);
        }
    }
}
