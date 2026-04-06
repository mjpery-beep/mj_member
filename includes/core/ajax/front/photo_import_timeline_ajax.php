<?php

namespace Mj\Member\Core\Ajax\Front;

use Mj\Member\Core\Contracts\AjaxHandlerInterface;
use Mj\Member\Classes\MjNextcloudPhotoImporter;

if (!defined('ABSPATH')) {
    exit;
}

final class PhotoImportTimelineController implements AjaxHandlerInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_mj_member_photo_timeline_chunk', [$this, 'photoTimelineChunk']);
        add_action('wp_ajax_nopriv_mj_member_photo_timeline_chunk', [$this, 'photoTimelineChunk']);
    }

    public function photoTimelineChunk(): void
    {
        check_ajax_referer('mj-photo-timeline', 'nonce');

        $mode = isset($_POST['mode']) ? sanitize_key((string) wp_unslash($_POST['mode'])) : 'chunk';

        if ($mode === 'meta') {
            $summary = MjNextcloudPhotoImporter::getTimelineYearSummary();
            $totalItems = array_reduce($summary, static function (int $carry, array $year): int {
                return $carry + (int) ($year['count'] ?? 0);
            }, 0);

            wp_send_json_success(array(
                'years' => $summary,
                'total_items' => $totalItems,
            ));
        }

        if ($mode === 'year') {
            $year = isset($_POST['year']) ? (int) $_POST['year'] : 0;
            if ($year <= 0) {
                wp_send_json_error(array('message' => __('Année invalide.', 'mj-member')), 400);
            }

            $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 1500;
            $limit = max(24, min(5000, $limit));

            $items = MjNextcloudPhotoImporter::getTimelineItemsForYear($year, $limit, 0, 'desc');
            $items = array_values(array_filter($items, static function ($item): bool {
                return is_array($item)
                    && !empty($item['thumb_url'])
                    && !empty($item['display_url']);
            }));

            wp_send_json_success(array(
                'year' => $year,
                'items' => $items,
                'count' => count($items),
                'has_more' => false,
            ));
        }

        $offset = isset($_POST['offset']) ? max(0, (int) $_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? (int) $_POST['limit'] : 60;
        $limit = max(12, min(180, $limit));

        // Request one extra item to cheaply determine if another page exists.
        $rawItems = MjNextcloudPhotoImporter::getTimelineItems($limit + 1, 'desc', $offset);
        $hasMore = count($rawItems) > $limit;
        if ($hasMore) {
            $rawItems = array_slice($rawItems, 0, $limit);
        }

        $items = array_values(array_filter($rawItems, static function ($item): bool {
            return is_array($item)
                && !empty($item['thumb_url'])
                && !empty($item['display_url']);
        }));

        wp_send_json_success(array(
            'items' => $items,
            'offset' => $offset,
            'count' => count($items),
            'next_offset' => $offset + count($items),
            'has_more' => $hasMore,
        ));
    }
}
