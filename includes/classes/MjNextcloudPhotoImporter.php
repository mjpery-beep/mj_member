<?php

namespace Mj\Member\Classes;

use Mj\Member\Core\Config;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

final class MjNextcloudPhotoImporter
{
    private const OPTION_SOURCE_FOLDER = 'mj_member_photo_import_source_folder';
    private const OPTION_DEFAULT_TAGS = 'mj_member_photo_import_default_tags';
    private const OPTION_TAG_FOLDER_MAP = 'mj_member_photo_import_tag_folder_map';
    private const OPTION_THUMB_WIDTH = 'mj_member_photo_import_thumb_width';
    private const OPTION_THUMB_HEIGHT = 'mj_member_photo_import_thumb_height';
    private const OPTION_DISPLAY_WIDTH = 'mj_member_photo_import_display_width';
    private const OPTION_DISPLAY_HEIGHT = 'mj_member_photo_import_display_height';
    private const OPTION_RUNTIME_STATE = 'mj_member_photo_import_runtime_state';
    private const RUN_LOCK_TTL = 20 * MINUTE_IN_SECONDS;
    private const DEBUG_BUILD_ID = 'photo-import-build-2026-04-03-01';

    private const DEFAULT_SOURCE_FOLDER = 'photos';
    private const DEFAULT_THUMB_WIDTH = 520;
    private const DEFAULT_THUMB_HEIGHT = 360;
    private const DEFAULT_DISPLAY_WIDTH = 1920;
    private const DEFAULT_DISPLAY_HEIGHT = 1280;

    public static function getSettings(): array
    {
        $sourceFolder = trim((string) get_option(self::OPTION_SOURCE_FOLDER, self::DEFAULT_SOURCE_FOLDER), "/\\ \t\n\r\0\x0B");
        if ($sourceFolder === '') {
            $sourceFolder = self::DEFAULT_SOURCE_FOLDER;
        }

        $defaultTags = self::normalizeTags(get_option(self::OPTION_DEFAULT_TAGS, ''));
        $tagFolderMapRaw = (string) get_option(self::OPTION_TAG_FOLDER_MAP, '');
        $tagFolderMap = self::parseTagFolderMap($tagFolderMapRaw);

        $thumbWidth = max(120, (int) get_option(self::OPTION_THUMB_WIDTH, self::DEFAULT_THUMB_WIDTH));
        $thumbHeight = max(120, (int) get_option(self::OPTION_THUMB_HEIGHT, self::DEFAULT_THUMB_HEIGHT));
        $displayWidth = max(320, (int) get_option(self::OPTION_DISPLAY_WIDTH, self::DEFAULT_DISPLAY_WIDTH));
        $displayHeight = max(320, (int) get_option(self::OPTION_DISPLAY_HEIGHT, self::DEFAULT_DISPLAY_HEIGHT));

        return array(
            'source_folder' => $sourceFolder,
            'default_tags' => $defaultTags,
            'tag_folder_map_raw' => $tagFolderMapRaw,
            'tag_folder_map' => $tagFolderMap,
            'thumb_width' => $thumbWidth,
            'thumb_height' => $thumbHeight,
            'display_width' => $displayWidth,
            'display_height' => $displayHeight,
        );
    }

    public static function listAvailableTags()
    {
        $payloads = self::requestJsonPayloadsFromPaths(array(
            '/ocs/v1.php/apps/files/api/v1/systemtags?format=json',
        ), true, 20, false);
        if (is_wp_error($payloads)) {
            return $payloads;
        }

        $tagsById = array();
        foreach ($payloads as $index => $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $parsed = self::extractTagsFromAnyPayload($payload);
            self::debugLog('photo-import parsed tags from payload #' . ($index + 1) . ' => ' . count($parsed));
            foreach ($parsed as $tag) {
                if (!is_array($tag)) {
                    continue;
                }
                $id = isset($tag['id']) ? trim((string) $tag['id']) : '';
                $name = isset($tag['name']) ? trim((string) $tag['name']) : '';
                if ($id === '' || $name === '') {
                    continue;
                }
                $tagsById[$id] = array('id' => $id, 'name' => $name);
            }
        }
        $tags = array_values($tagsById);

        // Some Nextcloud setups expose user-visible labels only via DAV systemtags.
        if (empty($tags)) {
            $davTags = self::listAvailableTagsFromDav();
            if (!is_wp_error($davTags) && !empty($davTags)) {
                $tags = $davTags;
            }
        }

        foreach ($tags as &$tag) {
            if (!is_array($tag)) {
                continue;
            }

            $id = isset($tag['id']) ? trim((string) $tag['id']) : '';
            $name = isset($tag['name']) ? trim((string) $tag['name']) : '';
            if ($id === '') {
                continue;
            }

            // If label is missing or purely numeric, try resolving human name from OCS detail endpoint.
            if ($name === '' || preg_match('/^\d+$/', $name)) {
                $resolvedName = self::resolveTagLabelById($id);
                if ($resolvedName !== '') {
                    $tag['name'] = $resolvedName;
                }
            }
        }
        unset($tag);

        usort($tags, static function (array $left, array $right): int {
            return strcasecmp((string) $left['name'], (string) $right['name']);
        });

        if (empty($tags)) {
            self::debugLog('photo-import tags empty after parsing. payload count=' . count($payloads));
            foreach ($payloads as $i => $payload) {
                $keys = is_array($payload) ? implode(',', array_map('strval', array_keys($payload))) : 'n/a';
                $ocsData = (is_array($payload) && isset($payload['ocs']['data']) && is_array($payload['ocs']['data'])) ? $payload['ocs']['data'] : null;
                $ocsKeys = is_array($ocsData) ? implode(',', array_map('strval', array_keys($ocsData))) : 'n/a';
                self::debugLog('photo-import payload #' . ($i + 1) . ' keys=' . $keys . ' ocs.data keys=' . $ocsKeys);
            }
        }
        self::debugLog('photo-import tags resolved count=' . count($tags));

        return $tags;
    }

    private static function extractTagsFromAnyPayload(array $decoded): array
    {
        $candidates = array();

        $candidates[] = $decoded['ocs']['data'] ?? null;
        $candidates[] = $decoded['ocs']['data']['tags'] ?? null;
        $candidates[] = $decoded['ocs']['data']['systemtags'] ?? null;
        $candidates[] = $decoded['data'] ?? null;
        $candidates[] = $decoded['data']['tags'] ?? null;
        $candidates[] = $decoded['data']['systemtags'] ?? null;
        $candidates[] = $decoded['systemtags'] ?? null;
        $candidates[] = $decoded['tags'] ?? null;
        $candidates[] = $decoded;

        $tags = array();
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                $decodedCandidate = json_decode($candidate, true);
                if (is_array($decodedCandidate)) {
                    $candidate = $decodedCandidate;
                }
            }

            if (!is_array($candidate)) {
                continue;
            }

            // Handle map-like payloads: {"12": {...}, "13": {...}}
            $values = array_values($candidate);
            $items = $values;

            // Some payloads wrap everything into a single list-like key.
            if (count($values) === 1 && is_array($values[0])) {
                $first = $values[0];
                if (self::looksLikeList($first)) {
                    $items = $first;
                }
            }

            foreach ($items as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $id = isset($entry['id']) ? (string) $entry['id'] : (isset($entry['tagid']) ? (string) $entry['tagid'] : '');
                $name = '';
                if (isset($entry['display-name'])) {
                    $name = (string) $entry['display-name'];
                } elseif (isset($entry['displayName'])) {
                    $name = (string) $entry['displayName'];
                } elseif (isset($entry['name'])) {
                    $name = (string) $entry['name'];
                } elseif (isset($entry['label'])) {
                    $name = (string) $entry['label'];
                }

                $id = trim($id);
                $name = trim(wp_strip_all_tags($name));
                if ($id === '' || $name === '') {
                    continue;
                }

                $tags[$id] = array(
                    'id' => $id,
                    'name' => $name,
                );
            }
        }

        return array_values($tags);
    }

    private static function looksLikeList(array $value): bool
    {
        if (empty($value)) {
            return true;
        }
        return array_keys($value) === range(0, count($value) - 1);
    }

    public static function importByTagNames(array $selectedTags, string $runId = '')
    {
        if (!self::acquireRunLock($runId)) {
            $message = __('Un import est déjà en cours pour cette exécution.', 'mj-member');
            self::runtimePush($runId, $message, array('step' => 'busy', 'level' => 'warning'));
            return new WP_Error('mj_member_photo_import_already_running', $message);
        }

        try {
            self::runtimeBegin($runId, array('step' => 'init'));
            self::debugLog('photo-import build=' . self::DEBUG_BUILD_ID . ' runId=' . $runId);
            $settings = self::getSettings();
            $selectedTags = self::normalizeTags($selectedTags);

            if (empty($selectedTags)) {
                $error = new WP_Error('mj_member_photo_import_no_tag', __('Sélectionnez au moins une étiquette Nextcloud.', 'mj-member'));
                self::runtimeFail($runId, $error->get_error_message());
                return $error;
            }

            $nextcloud = MjNextcloud::make();
            if (is_wp_error($nextcloud)) {
                self::runtimeFail($runId, $nextcloud->get_error_message());
                return $nextcloud;
            }

            $availableTags = self::listAvailableTags();
            if (is_wp_error($availableTags)) {
                self::runtimeFail($runId, $availableTags->get_error_message());
                return $availableTags;
            }

        $tagNameToId = array();
        $tagIdToName = array();
        $knownIds = array();
        foreach ($availableTags as $tag) {
            $tagId = (string) ($tag['id'] ?? '');
            $tagLabel = trim((string) ($tag['name'] ?? ''));
            if ($tagId !== '') {
                $knownIds[$tagId] = true;
                if ($tagLabel !== '') {
                    $tagIdToName[$tagId] = $tagLabel;
                }
            }
            $tagKey = function_exists('mb_strtolower')
                ? mb_strtolower((string) $tag['name'])
                : strtolower((string) $tag['name']);
            $tagNameToId[$tagKey] = $tagId;
        }

        $selectedTagIds = array();
        $resolvedTagNames = array();
        foreach ($selectedTags as $tagName) {
            if (isset($knownIds[$tagName])) {
                $selectedTagIds[] = $tagName;
                $resolvedTagNames[] = isset($tagIdToName[$tagName]) ? $tagIdToName[$tagName] : $tagName;
                continue;
            }

            $key = function_exists('mb_strtolower') ? mb_strtolower($tagName) : strtolower($tagName);
            if (!isset($tagNameToId[$key])) {
                continue;
            }
            $selectedTagIds[] = $tagNameToId[$key];
            $resolvedTagNames[] = $tagName;
        }

        $selectedTagIds = array_values(array_unique(array_filter($selectedTagIds)));
        if (empty($selectedTagIds)) {
            $error = new WP_Error('mj_member_photo_import_unknown_tag', __('Aucune étiquette sélectionnée n’a été trouvée dans Nextcloud.', 'mj-member'));
            self::runtimeFail($runId, $error->get_error_message());
            return $error;
        }
        self::runtimePush($runId, 'Tags sélectionnés: ' . implode(', ', $selectedTagIds), array('step' => 'tags'));

        $sourceFolder = $settings['source_folder'];
        $files = self::listImageFilesRecursively($nextcloud, $sourceFolder);
        if (is_wp_error($files)) {
            self::runtimeFail($runId, $files->get_error_message());
            return $files;
        }

        $dirsReady = self::ensureStorageDirectories();
        if (is_wp_error($dirsReady)) {
            self::runtimeFail($runId, $dirsReady->get_error_message());
            return $dirsReady;
        }

        self::debugLog('photo-import source folder=' . $sourceFolder . ' files found=' . count($files));
        self::runtimePush($runId, 'Fichiers image détectés dans le dossier source: ' . count($files), array('step' => 'scan'));

        $selectors = self::collectFileSelectorsForTags($selectedTagIds);
        if (is_wp_error($selectors)) {
            self::runtimeFail($runId, $selectors->get_error_message());
            return $selectors;
        }

        $allowedFileIds = isset($selectors['ids']) && is_array($selectors['ids']) ? $selectors['ids'] : array();
        $allowedFilePaths = isset($selectors['paths']) && is_array($selectors['paths']) ? $selectors['paths'] : array();
        self::debugLog('photo-import file selectors ids=' . count($allowedFileIds) . ' paths=' . count($allowedFilePaths));
        self::runtimePush(
            $runId,
            'Correspondances initiales API tags->fichiers: ids=' . count($allowedFileIds) . ' paths=' . count($allowedFilePaths),
            array('step' => 'matching')
        );

        // Fast explicit fallback: user-defined mapping Tag => Folder from settings.
        if (empty($allowedFileIds) && empty($allowedFilePaths) && !empty($files)) {
            $mappedSelectors = self::collectFileSelectorsByTagFolderMapping(
                $files,
                $sourceFolder,
                $resolvedTagNames,
                isset($settings['tag_folder_map']) && is_array($settings['tag_folder_map']) ? $settings['tag_folder_map'] : array()
            );
            if (!empty($mappedSelectors['paths'])) {
                $allowedFilePaths = $mappedSelectors['paths'];
                self::runtimePush(
                    $runId,
                    'Mapping manuel tag->dossier: paths=' . count($allowedFilePaths),
                    array('step' => 'matching_folder_map')
                );
            }
        }

        // Fallback DAV for instances where OCS endpoints are available but semantically unusable.
        if (empty($allowedFileIds) && empty($allowedFilePaths)) {
            $davSelectors = self::collectFileSelectorsViaDavTagMembers($selectedTagIds);
            if (!is_wp_error($davSelectors)) {
                $allowedFileIds = isset($davSelectors['ids']) && is_array($davSelectors['ids']) ? $davSelectors['ids'] : array();
                $allowedFilePaths = isset($davSelectors['paths']) && is_array($davSelectors['paths']) ? $davSelectors['paths'] : array();
                self::runtimePush(
                    $runId,
                    'Fallback DAV tags: ids=' . count($allowedFileIds) . ' paths=' . count($allowedFilePaths),
                    array('step' => 'matching_dav')
                );
            }
        }

        // Fallback for Nextcloud instances where tag->files endpoint returns OCS 998.
        if (empty($allowedFileIds) && empty($allowedFilePaths) && !empty($files)) {
            self::debugLog('photo-import fallback file->tags start files=' . count($files));
            $fallbackSelectors = self::collectFileSelectorsViaFileRelations($files, $selectedTagIds, $runId);
            if (!is_wp_error($fallbackSelectors)) {
                $allowedFileIds = isset($fallbackSelectors['ids']) && is_array($fallbackSelectors['ids']) ? $fallbackSelectors['ids'] : array();
                $allowedFilePaths = isset($fallbackSelectors['paths']) && is_array($fallbackSelectors['paths']) ? $fallbackSelectors['paths'] : array();
                self::debugLog('photo-import fallback selectors ids=' . count($allowedFileIds) . ' paths=' . count($allowedFilePaths));
                self::runtimePush(
                    $runId,
                    'Fallback file->tags: ids=' . count($allowedFileIds) . ' paths=' . count($allowedFilePaths),
                    array('step' => 'matching_fallback')
                );
            }
        }

        // Last-resort fallback: query each file via DAV and inspect its tags directly.
        if (empty($allowedFileIds) && empty($allowedFilePaths) && !empty($files)) {
            self::debugLog('photo-import fallback DAV file-props start files=' . count($files));
            self::runtimePush($runId, 'Analyse DAV fichier->tags en cours (peut prendre 1-3 minutes)...', array('step' => 'matching_dav_files'));
            $davFileSelectors = self::collectFileSelectorsViaDavFileProps($files, $selectedTagIds, $resolvedTagNames, $runId);
            if (!is_wp_error($davFileSelectors)) {
                $allowedFileIds = isset($davFileSelectors['ids']) && is_array($davFileSelectors['ids']) ? $davFileSelectors['ids'] : array();
                $allowedFilePaths = isset($davFileSelectors['paths']) && is_array($davFileSelectors['paths']) ? $davFileSelectors['paths'] : array();
                self::runtimePush(
                    $runId,
                    'Fallback DAV fichier->tags: ids=' . count($allowedFileIds) . ' paths=' . count($allowedFilePaths),
                    array('step' => 'matching_dav_files')
                );
            }
        }

        // Operational fallback for servers where tag relations are not queryable:
        // infer matches from subfolders named like selected tags, e.g. Photos/Sport.
        if (empty($allowedFileIds) && empty($allowedFilePaths) && !empty($files)) {
            $folderSelectors = self::collectFileSelectorsByTagNamedSubfolders($files, $sourceFolder, $resolvedTagNames);
            if (!empty($folderSelectors['paths'])) {
                $allowedFilePaths = $folderSelectors['paths'];
                self::runtimePush(
                    $runId,
                    'Fallback dossiers par tag: paths=' . count($allowedFilePaths),
                    array('step' => 'matching_folder_fallback')
                );
            }
        }

        if (empty($allowedFileIds) && empty($allowedFilePaths)) {
            self::debugLog('photo-import no matched selectors for selected tags=' . implode(',', $resolvedTagNames));
            self::runtimeComplete($runId, array(
                'step' => 'done',
                'imported' => 0,
                'already_present' => 0,
                'matched' => 0,
                'skipped' => 0,
            ));
            return array(
                'imported' => 0,
                'already_present' => 0,
                'matched' => 0,
                'skipped' => 0,
                'tags' => $resolvedTagNames,
                'message' => __('Aucune image liée aux étiquettes sélectionnées.', 'mj-member'),
            );
        }

        $manifest = self::loadManifest();
        $matched = 0;
        $imported = 0;
        $alreadyPresent = 0;
        $skipped = 0;
        $processed = 0;
        $matchedPreview = array();
        $candidateFiles = array();
        $seenCandidates = array();
        $folderPathPrefixes = self::buildFolderPrefixesFromSelectors($files, $allowedFilePaths);
        if (!empty($folderPathPrefixes)) {
            self::debugLog('photo-import folder prefix selectors=' . count($folderPathPrefixes));
        }
        self::runtimePush($runId, '🚀 Préparation de la file d\'import...', array('step' => 'download'));

        foreach ($files as $file) {
            $fileId = isset($file['id']) ? (string) $file['id'] : '';
            if ($fileId === '' && isset($file['fileId'])) {
                $fileId = (string) $file['fileId'];
            }
            $filePath = isset($file['path']) ? trim((string) $file['path'], '/') : '';
            $matchById = ($fileId !== '' && isset($allowedFileIds[$fileId]));
            $matchByPath = ($filePath !== '' && isset($allowedFilePaths[$filePath]));
            $matchByFolderPrefix = (!$matchByPath && $filePath !== '' && self::pathMatchesAnyFolderPrefix($filePath, $folderPathPrefixes));

            if (!$matchById && !$matchByPath && !$matchByFolderPrefix) {
                continue;
            }

            $candidateKey = '';
            if ($filePath !== '') {
                $candidateKey = 'path:' . self::normalizeSourcePath($filePath);
            } elseif ($fileId !== '') {
                $candidateKey = 'id:' . trim($fileId);
            } else {
                $candidateKey = 'raw:' . md5(wp_json_encode($file));
            }

            if (isset($seenCandidates[$candidateKey])) {
                continue;
            }

            $seenCandidates[$candidateKey] = true;
            $candidateFiles[] = $file;
            $matched++;
            if (count($matchedPreview) < 5 && $filePath !== '') {
                $matchedPreview[] = $filePath;
            }
        }

        $totalToImport = count($candidateFiles);
        self::runtimePush(
            $runId,
            sprintf('📊 Total=%1$d | Déjà importé=%2$d | Restant=%3$d', $totalToImport, $alreadyPresent, $totalToImport),
            array(
                'step' => 'download',
                'total' => $totalToImport,
                'remaining' => $totalToImport,
                'already_present' => $alreadyPresent,
                'imported' => $imported,
                'skipped' => $skipped,
            )
        );

        foreach ($candidateFiles as $file) {
            $processed++;
            $name = isset($file['name']) ? (string) $file['name'] : ('file-' . $processed);
            self::runtimePush($runId, '[' . $name . '] 🔎 Scan (' . $processed . '/' . $totalToImport . ')', array('step' => 'download'));

            try {
                $itemResult = self::importSingleFile($nextcloud, $file, $settings, $resolvedTagNames, $manifest, $runId);
            } catch (\Throwable $t) {
                $skipped++;
                self::debugLog('photo-import throwable during importSingleFile: ' . $t->getMessage());
                self::runtimePush($runId, 'Erreur import (exception): ' . $t->getMessage(), array('level' => 'error'));
                continue;
            }

            if (is_wp_error($itemResult)) {
                $skipped++;
                self::runtimePush($runId, 'Erreur import: ' . $itemResult->get_error_message(), array('level' => 'error'));
                continue;
            }

            if (!empty($itemResult['imported'])) {
                $imported++;
                self::saveManifest($manifest);
                self::runtimePush($runId, '[' . $name . '] ✅ Imported', array('step' => 'download'));
            } elseif (!empty($itemResult['already_present'])) {
                $alreadyPresent++;
                self::runtimePush($runId, '[' . $name . '] ♻️ Already exist', array('step' => 'download'));
            } else {
                $skipped++;
            }

            $remaining = max(0, $totalToImport - $processed);
            self::runtimePush(
                $runId,
                sprintf('📊 Total=%1$d | Déjà importé=%2$d | Restant=%3$d', $totalToImport, $alreadyPresent, $remaining),
                array(
                    'step' => 'download',
                    'total' => $totalToImport,
                    'remaining' => $remaining,
                    'already_present' => $alreadyPresent,
                    'imported' => $imported,
                    'skipped' => $skipped,
                )
            );
        }

        self::saveManifest($manifest);

        if (!empty($matchedPreview)) {
            self::debugLog('photo-import matched preview=' . implode(' | ', $matchedPreview));
        }

        self::runtimeComplete($runId, array(
            'step' => 'done',
            'imported' => $imported,
            'already_present' => $alreadyPresent,
            'matched' => $matched,
            'skipped' => $skipped,
        ));

            return array(
                'imported' => $imported,
                'already_present' => $alreadyPresent,
                'matched' => $matched,
                'skipped' => $skipped,
                'tags' => $resolvedTagNames,
                'message' => sprintf(
                    __('Import terminé : %1$d importée(s), %2$d déjà présente(s), %3$d ignorée(s), %4$d correspondance(s).', 'mj-member'),
                    $imported,
                    $alreadyPresent,
                    $skipped,
                    $matched
                ),
            );
        } finally {
            self::releaseRunLock($runId);
        }
    }

    private static function acquireRunLock(string $runId): bool
    {
        $runId = trim($runId);
        if ($runId === '') {
            return true;
        }

        $key = 'mj_member_photo_import_lock_' . md5($runId);
        $existing = get_transient($key);
        if (is_array($existing) && !empty($existing['locked'])) {
            return false;
        }

        set_transient($key, array(
            'locked' => true,
            'ts' => time(),
        ), self::RUN_LOCK_TTL);

        return true;
    }

    private static function releaseRunLock(string $runId): void
    {
        $runId = trim($runId);
        if ($runId === '') {
            return;
        }

        $key = 'mj_member_photo_import_lock_' . md5($runId);
        delete_transient($key);
    }

    public static function getTimelineItems(int $limit = 120, string $order = 'desc', int $offset = 0): array
    {
        $manifest = self::loadManifest();
        $items = array_values($manifest['items']);

        usort($items, static function (array $left, array $right): int {
            $leftTs = isset($left['taken_at_ts']) ? (int) $left['taken_at_ts'] : 0;
            $rightTs = isset($right['taken_at_ts']) ? (int) $right['taken_at_ts'] : 0;
            if ($leftTs === $rightTs) {
                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            }
            return $leftTs <=> $rightTs;
        });

        if (strtolower($order) !== 'asc') {
            $items = array_reverse($items);
        }

        if ($offset > 0) {
            $items = array_slice($items, $offset);
        }

        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    public static function getTimelineYearSummary(): array
    {
        $manifest = self::loadManifest();
        $items = isset($manifest['items']) && is_array($manifest['items']) ? array_values($manifest['items']) : array();

        $yearCounts = array();
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ts = isset($item['taken_at_ts']) ? (int) $item['taken_at_ts'] : 0;
            if ($ts <= 0) {
                $ts = isset($item['imported_at_ts']) ? (int) $item['imported_at_ts'] : 0;
            }
            if ($ts <= 0) {
                $ts = time();
            }

            $year = (int) wp_date('Y', $ts);
            if ($year <= 0) {
                continue;
            }

            if (!isset($yearCounts[$year])) {
                $yearCounts[$year] = 0;
            }
            $yearCounts[$year]++;
        }

        if (empty($yearCounts)) {
            return array();
        }

        krsort($yearCounts, SORT_NUMERIC);

        $summary = array();
        foreach ($yearCounts as $year => $count) {
            $summary[] = array(
                'year' => (int) $year,
                'count' => (int) $count,
            );
        }

        return $summary;
    }

    public static function getTimelineItemsForYear(int $year, int $limit = 3000, int $offset = 0, string $order = 'desc'): array
    {
        $year = (int) $year;
        if ($year <= 0) {
            return array();
        }

        $manifest = self::loadManifest();
        $items = isset($manifest['items']) && is_array($manifest['items']) ? array_values($manifest['items']) : array();

        $filtered = array_values(array_filter($items, static function ($item) use ($year): bool {
            if (!is_array($item)) {
                return false;
            }

            $ts = isset($item['taken_at_ts']) ? (int) $item['taken_at_ts'] : 0;
            if ($ts <= 0) {
                $ts = isset($item['imported_at_ts']) ? (int) $item['imported_at_ts'] : 0;
            }
            if ($ts <= 0) {
                return false;
            }

            return (int) wp_date('Y', $ts) === $year;
        }));

        usort($filtered, static function (array $left, array $right): int {
            $leftTs = isset($left['taken_at_ts']) ? (int) $left['taken_at_ts'] : 0;
            $rightTs = isset($right['taken_at_ts']) ? (int) $right['taken_at_ts'] : 0;
            if ($leftTs === $rightTs) {
                return strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
            }
            return $leftTs <=> $rightTs;
        });

        if (strtolower($order) !== 'asc') {
            $filtered = array_reverse($filtered);
        }

        if ($offset > 0) {
            $filtered = array_slice($filtered, $offset);
        }

        if ($limit > 0) {
            $filtered = array_slice($filtered, 0, $limit);
        }

        return $filtered;
    }

    public static function getPreviewTimelineItems(): array
    {
        $now = time();
        return array(
            array(
                'id' => 'preview-1',
                'title' => 'Sortie parc',
                'thumb_url' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=900&q=80',
                'display_url' => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?auto=format&fit=crop&w=1600&q=80',
                'taken_at_ts' => $now - 86400,
                'taken_at_label' => wp_date('d/m/Y H:i', $now - 86400),
                'source_name' => 'sortie-parc.jpg',
            ),
            array(
                'id' => 'preview-2',
                'title' => 'Atelier créatif',
                'thumb_url' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?auto=format&fit=crop&w=900&q=80',
                'display_url' => 'https://images.unsplash.com/photo-1511632765486-a01980e01a18?auto=format&fit=crop&w=1600&q=80',
                'taken_at_ts' => $now - 172800,
                'taken_at_label' => wp_date('d/m/Y H:i', $now - 172800),
                'source_name' => 'atelier-creatif.jpg',
            ),
            array(
                'id' => 'preview-3',
                'title' => 'Soirée jeux',
                'thumb_url' => 'https://images.unsplash.com/photo-1527525443983-6e60c75fff46?auto=format&fit=crop&w=900&q=80',
                'display_url' => 'https://images.unsplash.com/photo-1527525443983-6e60c75fff46?auto=format&fit=crop&w=1600&q=80',
                'taken_at_ts' => $now - 259200,
                'taken_at_label' => wp_date('d/m/Y H:i', $now - 259200),
                'source_name' => 'soiree-jeux.jpg',
            ),
        );
    }

    private static function importSingleFile(MjNextcloud $nextcloud, array $file, array $settings, array $resolvedTagNames, array &$manifest, string $runId = '')
    {
        $sourcePath = isset($file['path']) ? (string) $file['path'] : '';
        $sourceName = isset($file['name']) ? (string) $file['name'] : basename($sourcePath);
        $sourceModified = isset($file['modifiedTime']) ? (string) $file['modifiedTime'] : '';
        $sourceSize = isset($file['size']) ? (int) $file['size'] : 0;
        $sourceId = isset($file['id']) ? (string) $file['id'] : '';
        if ($sourceId === '' && isset($file['fileId'])) {
            $sourceId = (string) $file['fileId'];
        }

        if ($sourcePath === '' || $sourceName === '') {
            return new WP_Error('mj_member_photo_import_invalid_file', __('Fichier source invalide.', 'mj-member'));
        }

        $existingItemId = self::findExistingImportedItemId($manifest, $sourcePath, $sourceId);
        if ($existingItemId !== '') {
            $existingThumb = Config::path() . 'data/photo-import/thumb/' . $existingItemId . '.jpg';
            $existingDisplay = Config::path() . 'data/photo-import/display/' . $existingItemId . '.jpg';
            if (self::isUsableGeneratedImage($existingThumb) && self::isUsableGeneratedImage($existingDisplay)) {
                self::debugLog('photo-import skip already downloaded source=' . $sourcePath . ' item=' . $existingItemId);
                if ($runId !== '') {
                    self::runtimePush($runId, '[' . $sourceName . '] ♻️ Already exist', array('step' => 'download'));
                }
                return array('imported' => false, 'already_present' => true, 'id' => $existingItemId);
            }
        }

        $ext = strtolower(pathinfo($sourceName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = 'jpg';
        }

        $hash = md5($sourcePath . '|' . $sourceModified . '|' . $sourceSize);
        $id = $hash;

        $dirs = self::ensureStorageDirectories();
        if (is_wp_error($dirs)) {
            return $dirs;
        }

        $originalRelative = 'data/photo-import/original/' . $hash . '.' . $ext;
        $thumbRelative = 'data/photo-import/thumb/' . $hash . '.jpg';
        $displayRelative = 'data/photo-import/display/' . $hash . '.jpg';

        $originalAbsolute = Config::path() . $originalRelative;
        $thumbAbsolute = Config::path() . $thumbRelative;
        $displayAbsolute = Config::path() . $displayRelative;

        $alreadyCurrent = isset($manifest['items'][$id])
            && isset($manifest['items'][$id]['source_modified'])
            && (string) $manifest['items'][$id]['source_modified'] === $sourceModified
            && file_exists($thumbAbsolute)
            && file_exists($displayAbsolute);

        if ($alreadyCurrent) {
            if ($runId !== '') {
                self::runtimePush($runId, '[' . $sourceName . '] ♻️ Already exist', array('step' => 'download'));
            }
            return array('imported' => false, 'already_present' => true, 'id' => $id);
        }

        self::debugLog('photo-import importSingleFile start source=' . $sourcePath . ' name=' . $sourceName);

        if ($runId !== '') {
            self::runtimePush($runId, 'Récupération fichier source: ' . $sourceName, array('step' => 'download'));
        }
        $downloaded = $nextcloud->downloadFileToPath($sourcePath, $originalAbsolute);
        if (is_wp_error($downloaded)) {
            return $downloaded;
        }
        self::debugLog('photo-import importSingleFile downloaded source=' . $sourcePath . ' size=' . (string) @filesize($originalAbsolute));

        if ($runId !== '') {
            self::runtimePush($runId, '[' . $sourceName . '] 🧩 Miniatured', array('step' => 'download'));
        }

        if (self::isUsableGeneratedImage($thumbAbsolute) && self::isThumbnailOptimized($thumbAbsolute, (int) $settings['thumb_width'], (int) $settings['thumb_height'])) {
            self::debugLog('photo-import thumb already exists, skip generation source=' . $sourcePath);
        } else {
            // Thumbnail must stay lightweight for grid rendering.
            $thumbDone = self::generateResizedImage($originalAbsolute, $thumbAbsolute, (int) $settings['thumb_width'], (int) $settings['thumb_height'], true, 72);
            if (is_wp_error($thumbDone)) {
                // Last-resort fallback keeps import operational even if image editor fails.
                if (@copy($originalAbsolute, $thumbAbsolute) !== true) {
                    return $thumbDone;
                }
                self::debugLog('photo-import thumb fallback copy used source=' . $sourcePath);
            }
        }

        if ($runId !== '') {
            self::runtimePush($runId, '[' . $sourceName . '] 🖼️ Covered', array('step' => 'download'));
        }

        if (self::isUsableGeneratedImage($displayAbsolute)) {
            self::debugLog('photo-import display already exists, skip generation source=' . $sourcePath);
        } else {
            // Some hosts hang on a second image-editor pass (display resize) for large JPGs.
            // Prefer a direct copy to keep import progressing, then fallback to resize only if copy fails.
            $displayDone = @copy($originalAbsolute, $displayAbsolute);
            if ($displayDone !== true) {
                self::debugLog('photo-import display copy failed, fallback resize source=' . $sourcePath);
                $displayDone = self::generateResizedImage($originalAbsolute, $displayAbsolute, (int) $settings['display_width'], (int) $settings['display_height'], false, 82);
                if (is_wp_error($displayDone)) {
                    return $displayDone;
                }
            } else {
                self::debugLog('photo-import display generated via direct copy source=' . $sourcePath);
            }
        }

        // Originals are only used as a transient processing source.
        if (file_exists($originalAbsolute)) {
            @unlink($originalAbsolute);
        }

        $takenTs = strtotime($sourceModified);
        if ($takenTs === false || $takenTs <= 0) {
            $takenTs = time();
        }

        $manifest['items'][$id] = array(
            'id' => $id,
            'title' => preg_replace('/\.[^.]+$/', '', $sourceName),
            'source_path' => $sourcePath,
            'source_name' => $sourceName,
            'source_file_id' => $sourceId,
            'source_modified' => $sourceModified,
            'source_size' => $sourceSize,
            'tags' => $resolvedTagNames,
            'taken_at_ts' => $takenTs,
            'taken_at_label' => wp_date('d/m/Y H:i', $takenTs),
            'imported_at_ts' => time(),
            'thumb_url' => Config::url() . $thumbRelative,
            'display_url' => Config::url() . $displayRelative,
            'original_url' => '',
        );

        $manifest['updated_at'] = time();

        self::debugLog('photo-import importSingleFile done source=' . $sourcePath);

        return array('imported' => true, 'id' => $id);
    }

    private static function findExistingImportedItemId(array $manifest, string $sourcePath, string $sourceId): string
    {
        $items = isset($manifest['items']) && is_array($manifest['items']) ? $manifest['items'] : array();
        if (empty($items)) {
            return '';
        }

        $normalizedSourcePath = self::normalizeSourcePath($sourcePath);
        $normalizedSourceId = trim((string) $sourceId);

        foreach ($items as $itemId => $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemSourcePath = self::normalizeSourcePath(isset($item['source_path']) ? (string) $item['source_path'] : '');
            $itemSourceId = trim((string) (isset($item['source_file_id']) ? $item['source_file_id'] : ''));
            if ($normalizedSourcePath !== '' && $itemSourcePath !== '' && $itemSourcePath === $normalizedSourcePath) {
                return (string) $itemId;
            }
            if ($normalizedSourceId !== '' && $itemSourceId !== '' && $itemSourceId === $normalizedSourceId) {
                return (string) $itemId;
            }
        }

        return '';
    }

    private static function normalizeSourcePath(string $path): string
    {
        $path = trim(rawurldecode($path));
        $path = trim($path, '/');
        if ($path === '') {
            return '';
        }

        return strtolower($path);
    }

    private static function isUsableGeneratedImage(string $path): bool
    {
        return file_exists($path) && is_file($path) && (int) @filesize($path) > 0;
    }

    public static function getRuntimeState(string $runId): array
    {
        $state = get_option(self::OPTION_RUNTIME_STATE, array());
        if (!is_array($state)) {
            return array();
        }

        $runId = trim($runId);
        if ($runId !== '' && isset($state[$runId]) && is_array($state[$runId])) {
            return $state[$runId];
        }

        return array();
    }

    public static function startRuntimeTracking(string $runId, array $patch = array()): void
    {
        self::runtimeBegin($runId, $patch);
    }

    public static function pushRuntimeMessage(string $runId, string $message, array $patch = array()): void
    {
        self::runtimePush($runId, $message, $patch);
    }

    private static function runtimeBegin(string $runId, array $patch = array()): void
    {
        $runId = trim($runId);
        if ($runId === '') {
            return;
        }

        $state = get_option(self::OPTION_RUNTIME_STATE, array());
        if (!is_array($state)) {
            $state = array();
        }

        $state[$runId] = array_merge(array(
            'runId' => $runId,
            'status' => 'running',
            'step' => 'init',
            'startedAt' => time(),
            'updatedAt' => time(),
            'events' => array(),
        ), $patch);

        update_option(self::OPTION_RUNTIME_STATE, $state, false);
    }

    private static function runtimePush(string $runId, string $message, array $patch = array()): void
    {
        $runId = trim($runId);
        if ($runId === '') {
            return;
        }

        $state = get_option(self::OPTION_RUNTIME_STATE, array());
        if (!is_array($state)) {
            $state = array();
        }

        if (!isset($state[$runId]) || !is_array($state[$runId])) {
            self::runtimeBegin($runId);
            $state = get_option(self::OPTION_RUNTIME_STATE, array());
            if (!is_array($state)) {
                $state = array();
            }
        }

        $entry = $state[$runId] ?? array();
        $events = isset($entry['events']) && is_array($entry['events']) ? $entry['events'] : array();
        $events[] = array(
            'ts' => time(),
            'message' => $message,
            'level' => (string) ($patch['level'] ?? 'info'),
        );

        if (count($events) > 200) {
            $events = array_slice($events, -200);
        }

        unset($patch['level']);
        $nextStatus = isset($patch['status']) ? (string) $patch['status'] : (string) ($entry['status'] ?? 'running');
        $entry = array_merge($entry, $patch, array(
            'events' => $events,
            'status' => $nextStatus,
            'updatedAt' => time(),
        ));

        $state[$runId] = $entry;
        update_option(self::OPTION_RUNTIME_STATE, $state, false);
    }

    private static function runtimeComplete(string $runId, array $patch = array()): void
    {
        $runId = trim($runId);
        if ($runId === '') {
            return;
        }

        self::runtimePush($runId, 'Import terminé.', array_merge($patch, array('status' => 'done')));
    }

    private static function runtimeFail(string $runId, string $message): void
    {
        $runId = trim($runId);
        if ($runId === '') {
            return;
        }

        self::runtimePush($runId, $message, array(
            'status' => 'error',
            'step' => 'error',
            'level' => 'error',
        ));
    }

    private static function collectFileSelectorsForTags(array $tagIds)
    {
        $ids = array();
        $paths = array();

        foreach ($tagIds as $tagId) {
            $encodedTagId = rawurlencode((string) $tagId);
            $preferredPayloads = self::requestJsonPayloadsFromPaths(array(
                '/ocs/v2.php/apps/mj_patch/api/v1/systemtags/' . $encodedTagId . '/members?format=json',
                '/ocs/v2.php/apps/mj_patch/api/v1/systemtags/' . $encodedTagId . '/members',
                '/ocs/v2.php/apps/mj_patch/api/v1/systemtags/' . $encodedTagId . '/members?format=json&objectType=files&include=all&recursive=true',
                '/ocs/v2.php/apps/mj_patch/api/v1/systemtags/' . $encodedTagId . '/members?format=json&objectType=files&include=all&recursive=true&limit=1000&offset=0',
            ), true, 15, false);

            if (is_wp_error($preferredPayloads)) {
                return $preferredPayloads;
            }

            $tagIdsFound = array();
            $tagPathsFound = array();

            foreach ($preferredPayloads as $payload) {
                if (!is_array($payload)) {
                    continue;
                }

                $ocsData = (isset($payload['ocs']['data']) && is_array($payload['ocs']['data']))
                    ? $payload['ocs']['data']
                    : null;
                $filesNode = $payload['ocs']['data']['files']
                    ?? ($payload['ocs']['data']['members'] ?? null)
                    ?? ($payload['ocs']['data']['items'] ?? null)
                    ?? ($payload['members'] ?? null)
                    ?? ($payload['items'] ?? null)
                    ?? ($payload['files'] ?? null)
                    ?? ($ocsData ?? $payload);

                if (isset($payload['ocs']['meta']) && is_array($payload['ocs']['meta'])) {
                    $metaStatus = isset($payload['ocs']['meta']['statuscode']) ? (string) $payload['ocs']['meta']['statuscode'] : 'n/a';
                    $metaMessage = isset($payload['ocs']['meta']['message']) ? (string) $payload['ocs']['meta']['message'] : '';
                    self::debugLog('photo-import tag ' . $tagId . ' meta status=' . $metaStatus . ' message=' . $metaMessage);
                }

                self::collectSelectorsFromNode($filesNode, $tagIdsFound, $tagPathsFound);
            }

            if (!empty($tagIdsFound) || !empty($tagPathsFound)) {
                $ids = array_merge($ids, $tagIdsFound);
                $paths = array_merge($paths, $tagPathsFound);
                self::debugLog('photo-import selectors for tag ' . $tagId . ' => ids=' . count($ids) . ' paths=' . count($paths) . ' (source=mj_patch)');
                continue;
            }

            $payloads = self::requestJsonPayloadsFromPaths(array(
                '/ocs/v2.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/members?format=json&objectType=files&include=all&recursive=true&limit=1000&offset=0',
                '/ocs/v2.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/members?format=json&objectType=files&include=all&recursive=true',
                '/ocs/v2.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/members?format=json',
                '/ocs/v1.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/members?format=json&objectType=files&include=all&recursive=true&limit=1000&offset=0',
                '/ocs/v1.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/members?format=json&objectType=files&include=all&recursive=true',
                '/ocs/v1.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/members?format=json',
                '/ocs/v2.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/files?format=json',
                '/ocs/v2.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/files?format=json&objectType=files',
                '/ocs/v2.php/apps/files/api/v1/systemtags-relations/files/' . $encodedTagId . '?format=json',
                '/ocs/v2.php/apps/files/api/v1/systemtags-relations/files/' . $encodedTagId,
                '/ocs/v1.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/files?format=json',
                '/ocs/v1.php/apps/files/api/v1/systemtags/' . $encodedTagId . '/files?format=json&objectType=files',
                '/ocs/v1.php/apps/files/api/v1/systemtags-relations/files/' . $encodedTagId . '?format=json',
                '/ocs/v1.php/apps/files/api/v1/systemtags-relations/files/' . $encodedTagId,
            ), true, 15, false);
            if (is_wp_error($payloads)) {
                return $payloads;
            }

            foreach ($payloads as $payload) {
                if (!is_array($payload)) {
                    continue;
                }

                $ocsData = (isset($payload['ocs']['data']) && is_array($payload['ocs']['data']))
                    ? $payload['ocs']['data']
                    : null;
                $filesNode = $payload['ocs']['data']['files']
                    ?? ($payload['ocs']['data']['members'] ?? null)
                    ?? ($payload['ocs']['data']['items'] ?? null)
                    ?? ($payload['members'] ?? null)
                    ?? ($payload['items'] ?? null)
                    ?? ($payload['files'] ?? null)
                    ?? ($ocsData ?? $payload);

                if (isset($payload['ocs']['meta']) && is_array($payload['ocs']['meta'])) {
                    $metaStatus = isset($payload['ocs']['meta']['statuscode']) ? (string) $payload['ocs']['meta']['statuscode'] : 'n/a';
                    $metaMessage = isset($payload['ocs']['meta']['message']) ? (string) $payload['ocs']['meta']['message'] : '';
                    self::debugLog('photo-import tag ' . $tagId . ' meta status=' . $metaStatus . ' message=' . $metaMessage);
                }

                self::collectSelectorsFromNode($filesNode, $ids, $paths);
            }

            self::debugLog('photo-import selectors for tag ' . $tagId . ' => ids=' . count($ids) . ' paths=' . count($paths));
        }

        return array(
            'ids' => $ids,
            'paths' => $paths,
        );
    }

    private static function collectSelectorsFromNode($node, array &$ids, array &$paths): void
    {
        if (is_int($node) || is_float($node)) {
            $candidateId = trim((string) $node);
            if ($candidateId !== '' && preg_match('/^\d+$/', $candidateId)) {
                $ids[$candidateId] = true;
            }
            return;
        }

        if (is_string($node)) {
            $value = trim($node);
            if ($value === '') {
                return;
            }

            if (preg_match('/^\d+$/', $value)) {
                $ids[$value] = true;
                return;
            }

            // Accept compact list formats: "1,2,3" / "1;2;3".
            if (preg_match('/^\d+(\s*[,;]\s*\d+)+$/', $value)) {
                $parts = preg_split('/\s*[,;]\s*/', $value);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part = trim((string) $part);
                        if ($part !== '') {
                            $ids[$part] = true;
                        }
                    }
                }
                return;
            }

            $cleanPath = trim(str_replace('\\', '/', $value), '/');
            if ($cleanPath !== '' && strpos($cleanPath, '/') !== false) {
                $paths[$cleanPath] = true;
            }
            return;
        }

        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : '';

            if (is_string($key) && preg_match('/^\d+$/', $key)) {
                $ids[$key] = true;
            }

            if (in_array($lowerKey, array('id', 'fileid', 'file_id'), true) && (is_string($value) || is_int($value))) {
                $candidateId = trim((string) $value);
                if ($candidateId !== '') {
                    $ids[$candidateId] = true;
                }
            }

            if (in_array($lowerKey, array('path', 'source', 'sourcepath', 'filename', 'file', 'href'), true) && is_string($value)) {
                $candidatePath = trim(str_replace('\\', '/', $value), '/');
                if ($candidatePath !== '' && strpos($candidatePath, '/') !== false) {
                    $paths[$candidatePath] = true;
                }
            }

            self::collectSelectorsFromNode($value, $ids, $paths);
        }
    }

    private static function collectFileSelectorsViaFileRelations(array $files, array $selectedTagIds, string $runId = '')
    {
        $selectedTagIds = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $selectedTagIds))));

        if (empty($selectedTagIds)) {
            return array('ids' => array(), 'paths' => array());
        }

        $targetTagMap = array_fill_keys($selectedTagIds, true);
        $ids = array();
        $paths = array();
        $processed = 0;
        $matchedCount = 0;
        $maxFilesToInspect = max(1, min(20000, count($files)));
        $candidateFiles = self::sampleFilesForFallback($files, $maxFilesToInspect);
        self::debugLog('photo-import fallback sample size=' . count($candidateFiles) . ' total=' . count($files));
        $samplePreview = array();
        foreach ($candidateFiles as $sampleFile) {
            if (!is_array($sampleFile) || empty($sampleFile['path'])) {
                continue;
            }
            $samplePreview[] = trim((string) $sampleFile['path'], '/');
            if (count($samplePreview) >= 5) {
                break;
            }
        }
        if (!empty($samplePreview)) {
            self::debugLog('photo-import fallback inspection sample preview (non filtre par tag)=' . implode(' | ', $samplePreview));
        }

        foreach ($candidateFiles as $file) {
            if ($processed >= $maxFilesToInspect) {
                self::debugLog('photo-import fallback limit reached at ' . $maxFilesToInspect . ' files. Narrow source folder or tags to speed up.');
                break;
            }

            if (!is_array($file)) {
                continue;
            }

            $fileId = isset($file['id']) ? trim((string) $file['id']) : '';
            if ($fileId === '' && isset($file['fileId'])) {
                $fileId = trim((string) $file['fileId']);
            }

            if ($fileId === '') {
                continue;
            }

            $processed++;

            $payloads = self::requestJsonPayloadsFromPaths(array(
                '/ocs/v1.php/apps/files/api/v1/systemtags-relations/files/' . rawurlencode($fileId) . '?format=json',
                '/ocs/v1.php/apps/files/api/v1/systemtags-relations/files/' . rawurlencode($fileId),
            ), false, 5);

            if (is_wp_error($payloads) || !is_array($payloads)) {
                continue;
            }

            $fileTagIds = array();
            foreach ($payloads as $payload) {
                if (!is_array($payload)) {
                    continue;
                }

                $relationTagIds = self::extractTagIdsFromFileRelationsPayload($payload);
                foreach ($relationTagIds as $tagId) {
                    $fileTagIds[$tagId] = true;
                }

                if (empty($relationTagIds)) {
                    self::collectNumericIdsFromNode($payload, $fileTagIds);
                }
            }

            if (empty($fileTagIds)) {
                continue;
            }

            $matches = false;
            foreach (array_keys($fileTagIds) as $tagId) {
                if (isset($targetTagMap[(string) $tagId])) {
                    $matches = true;
                    break;
                }
            }

            if (!$matches) {
                continue;
            }

            $ids[$fileId] = true;
            $matchedCount++;
            $filePath = isset($file['path']) ? trim((string) $file['path'], '/') : '';
            if ($filePath !== '') {
                $paths[$filePath] = true;
            }

            if (($processed % 50) === 0) {
                self::debugLog('photo-import fallback progress files=' . $processed . ' matched=' . $matchedCount);
                self::runtimePush($runId, 'Fallback file->tags: progression ' . $processed . '/' . $maxFilesToInspect . ' (matches=' . $matchedCount . ')', array('step' => 'matching_fallback'));
            }
        }

        self::debugLog('photo-import fallback completed files=' . $processed . ' matched=' . $matchedCount);

        return array(
            'ids' => $ids,
            'paths' => $paths,
        );
    }

    private static function collectFileSelectorsViaDavTagMembers(array $selectedTagIds)
    {
        $baseUrl = Config::nextcloudUrl();
        $user = Config::nextcloudUser();
        $password = Config::nextcloudPassword();

        if ($baseUrl === '' || $user === '' || $password === '') {
            return new WP_Error('mj_member_photo_import_nextcloud_missing', __('La connexion Nextcloud n’est pas configurée.', 'mj-member'));
        }

        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return new WP_Error('mj_member_photo_import_dom_missing', __('Le parseur XML PHP est indisponible (DOM).', 'mj-member'));
        }

        $ids = array();
        $paths = array();
        $selectedTagIds = array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $selectedTagIds))));

        foreach ($selectedTagIds as $tagId) {
            $davPaths = array(
                '/remote.php/dav/systemtags/' . rawurlencode($tagId),
                '/remote.php/dav/systemtags-relations/tags/' . rawurlencode($tagId),
            );

            foreach ($davPaths as $davPath) {
                $url = rtrim($baseUrl, '/') . $davPath;
                $body = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:fileid/><d:href/></d:prop></d:propfind>';

                $response = wp_remote_request($url, array(
                    'method' => 'PROPFIND',
                    'timeout' => 30,
                    'sslverify' => true,
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
                        'Depth' => '1',
                        'Content-Type' => 'application/xml; charset=utf-8',
                        'Accept' => 'application/xml,text/xml,*/*',
                    ),
                    'body' => $body,
                ));

                if (is_wp_error($response)) {
                    self::debugLog('photo-import DAV tag members request error for tag ' . $tagId . ' (' . $davPath . '): ' . $response->get_error_message());
                    continue;
                }

                $code = (int) wp_remote_retrieve_response_code($response);
                self::debugLog('photo-import DAV tag members ' . $tagId . ' (' . $davPath . ') => HTTP ' . $code);
                if ($code < 200 || $code >= 300) {
                    continue;
                }

                $xml = (string) wp_remote_retrieve_body($response);
                if ($xml === '') {
                    continue;
                }

                $dom = new \DOMDocument();
                if (!@$dom->loadXML($xml)) {
                    continue;
                }

                $xpath = new \DOMXPath($dom);
                $responses = $xpath->query('//*[local-name()="response"]');
                if (!$responses) {
                    continue;
                }

                foreach ($responses as $responseNode) {
                    $hrefNode = $xpath->query('.//*[local-name()="href"]', $responseNode);
                    $href = ($hrefNode && $hrefNode->length > 0) ? trim((string) $hrefNode->item(0)->textContent) : '';
                    if ($href !== '') {
                        $candidatePath = self::extractSourcePathFromDavHref($href);
                        if ($candidatePath !== '') {
                            $paths[$candidatePath] = true;
                        }

                        $candidateHrefFileId = self::extractNumericFileIdFromDavHref($href);
                        if ($candidateHrefFileId !== '') {
                            $ids[$candidateHrefFileId] = true;
                        }
                    }

                    $fileIdNode = $xpath->query('.//*[local-name()="fileid"]', $responseNode);
                    if ($fileIdNode && $fileIdNode->length > 0) {
                        $candidateFileId = trim((string) $fileIdNode->item(0)->textContent);
                        if ($candidateFileId !== '' && preg_match('/^\d+$/', $candidateFileId)) {
                            $ids[$candidateFileId] = true;
                        }
                    }
                }
            }

            self::debugLog('photo-import DAV selectors for tag ' . $tagId . ' => ids=' . count($ids) . ' paths=' . count($paths));
        }

        return array(
            'ids' => $ids,
            'paths' => $paths,
        );
    }

    private static function extractSourcePathFromDavHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        $path = (string) parse_url($href, PHP_URL_PATH);
        if ($path === '') {
            $path = $href;
        }

        $patterns = array(
            '#/remote\.php/dav/files/[^/]+/(.+)$#i',
            '#/dav/files/[^/]+/(.+)$#i',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path, $matches) && isset($matches[1])) {
                $candidate = trim(rawurldecode((string) $matches[1]), '/');
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        return '';
    }

    private static function extractNumericFileIdFromDavHref(string $href): string
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }

        $path = (string) parse_url($href, PHP_URL_PATH);
        if ($path === '') {
            $path = $href;
        }

        if (preg_match('#/systemtags-relations/files/(\d+)(?:/)?$#i', $path, $matches) && isset($matches[1])) {
            return trim((string) $matches[1]);
        }

        return '';
    }

    private static function buildFolderPrefixesFromSelectors(array $files, array $allowedFilePaths): array
    {
        if (empty($allowedFilePaths) || empty($files)) {
            return array();
        }

        $exactFilePaths = array();
        foreach ($files as $file) {
            if (!is_array($file) || empty($file['path'])) {
                continue;
            }

            $filePath = trim(str_replace('\\', '/', (string) $file['path']), '/');
            if ($filePath === '') {
                continue;
            }

            $exactFilePaths[strtolower($filePath)] = true;
        }

        $prefixes = array();
        foreach ($allowedFilePaths as $path => $flag) {
            if ($flag !== true || !is_string($path)) {
                continue;
            }

            $normalizedPath = trim(str_replace('\\', '/', $path), '/');
            if ($normalizedPath === '') {
                continue;
            }

            $normalizedLower = strtolower($normalizedPath);
            if (isset($exactFilePaths[$normalizedLower])) {
                continue;
            }

            $prefixes[$normalizedLower] = true;
        }

        return $prefixes;
    }

    private static function pathMatchesAnyFolderPrefix(string $filePath, array $folderPrefixes): bool
    {
        if ($filePath === '' || empty($folderPrefixes)) {
            return false;
        }

        $normalizedFilePath = strtolower(trim(str_replace('\\', '/', $filePath), '/'));
        if ($normalizedFilePath === '') {
            return false;
        }

        foreach ($folderPrefixes as $folderPrefix => $flag) {
            if ($flag !== true || !is_string($folderPrefix) || $folderPrefix === '') {
                continue;
            }

            if (str_starts_with($normalizedFilePath, $folderPrefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function collectFileSelectorsViaDavFileProps(array $files, array $selectedTagIds, array $selectedTagNames = array(), string $runId = '')
    {
        $baseUrl = Config::nextcloudUrl();
        $user = Config::nextcloudUser();
        $password = Config::nextcloudPassword();

        if ($baseUrl === '' || $user === '' || $password === '') {
            return new WP_Error('mj_member_photo_import_nextcloud_missing', __('La connexion Nextcloud n’est pas configurée.', 'mj-member'));
        }

        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return new WP_Error('mj_member_photo_import_dom_missing', __('Le parseur XML PHP est indisponible (DOM).', 'mj-member'));
        }

        $targetTagMap = array_fill_keys(array_values(array_unique(array_filter(array_map(static function ($value): string {
            return trim((string) $value);
        }, $selectedTagIds)))), true);

        $targetTagNameMap = array();
        foreach ($selectedTagNames as $selectedTagName) {
            $name = trim(wp_strip_all_tags((string) $selectedTagName));
            if ($name === '') {
                continue;
            }
            $lowerName = function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name);
            $targetTagNameMap[$lowerName] = true;
        }

        if (empty($targetTagMap)) {
            return array('ids' => array(), 'paths' => array());
        }

        $ids = array();
        $paths = array();
        $processed = 0;
        $matched = 0;
        $maxFilesToInspect = max(1, min(20000, count($files)));
        $candidateFiles = self::sampleFilesForFallback($files, $maxFilesToInspect);
        self::debugLog('photo-import DAV file-props sample size=' . count($candidateFiles) . ' total=' . count($files));
        $samplePreview = array();
        foreach ($candidateFiles as $sampleFile) {
            if (!is_array($sampleFile) || empty($sampleFile['path'])) {
                continue;
            }
            $samplePreview[] = trim((string) $sampleFile['path'], '/');
            if (count($samplePreview) >= 5) {
                break;
            }
        }
        if (!empty($samplePreview)) {
            self::debugLog('photo-import DAV file-props inspection sample preview (non filtre par tag)=' . implode(' | ', $samplePreview));
        }

        foreach ($candidateFiles as $file) {
            if ($processed >= $maxFilesToInspect) {
                self::debugLog('photo-import DAV file-props fallback limit reached at ' . $maxFilesToInspect . ' files.');
                break;
            }

            if (!is_array($file)) {
                continue;
            }

            $filePath = isset($file['path']) ? trim((string) $file['path'], '/') : '';
            if ($filePath === '') {
                continue;
            }

            $fileUrl = rtrim($baseUrl, '/') . '/remote.php/dav/files/' . rawurlencode($user) . '/' . str_replace('%2F', '/', rawurlencode($filePath));
            $body = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><oc:tags/><oc:fileid/></d:prop></d:propfind>';

            $response = wp_remote_request($fileUrl, array(
                'method' => 'PROPFIND',
                'timeout' => 2,
                'sslverify' => true,
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
                    'Depth' => '0',
                    'Content-Type' => 'application/xml; charset=utf-8',
                    'Accept' => 'application/xml,text/xml,*/*',
                ),
                'body' => $body,
            ));

            $processed++;

            if (is_wp_error($response)) {
                continue;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                continue;
            }

            $xml = (string) wp_remote_retrieve_body($response);
            if ($xml === '') {
                continue;
            }

            $dom = new \DOMDocument();
            if (!@$dom->loadXML($xml)) {
                continue;
            }

            $xpath = new \DOMXPath($dom);
            $tagNodes = $xpath->query('//*[local-name()="tags"]//*[local-name()="tag"] | //*[local-name()="tags"]/*[local-name()="id"] | //*[local-name()="tags"]');

            $fileTagIds = array();
            $fileTagNames = array();
            if ($tagNodes) {
                foreach ($tagNodes as $node) {
                    $value = trim((string) $node->textContent);
                    if ($value === '') {
                        continue;
                    }

                    $parts = preg_split('/[\s,;\|]+/', $value);
                    if (!is_array($parts) || empty($parts)) {
                        $parts = array($value);
                    }

                    foreach ($parts as $part) {
                        $part = trim((string) $part);
                        if ($part === '') {
                            continue;
                        }

                        if (preg_match('/^\d+$/', $part)) {
                            $fileTagIds[$part] = true;
                            continue;
                        }

                        $cleanName = trim(wp_strip_all_tags($part));
                        if ($cleanName !== '') {
                            $lowerName = function_exists('mb_strtolower') ? mb_strtolower($cleanName) : strtolower($cleanName);
                            $fileTagNames[$lowerName] = true;
                        }
                    }
                }
            }

            if ($processed <= 3 && (!empty($fileTagIds) || !empty($fileTagNames))) {
                self::debugLog(
                    'photo-import DAV file-props tags sample file=' . $filePath
                    . ' ids=' . implode(',', array_keys($fileTagIds))
                    . ' names=' . implode(',', array_keys($fileTagNames))
                );
            }

            if (empty($fileTagIds) && empty($fileTagNames)) {
                continue;
            }

            $hasMatch = false;
            foreach (array_keys($fileTagIds) as $tagId) {
                if (isset($targetTagMap[$tagId])) {
                    $hasMatch = true;
                    break;
                }
            }

            if (!$hasMatch && !empty($targetTagNameMap)) {
                foreach (array_keys($fileTagNames) as $tagName) {
                    if (isset($targetTagNameMap[$tagName])) {
                        $hasMatch = true;
                        break;
                    }
                }
            }

            if (!$hasMatch) {
                continue;
            }

            $matched++;
            $paths[$filePath] = true;

            $fileId = isset($file['id']) ? trim((string) $file['id']) : '';
            if ($fileId === '' && isset($file['fileId'])) {
                $fileId = trim((string) $file['fileId']);
            }
            if ($fileId !== '' && preg_match('/^\d+$/', $fileId)) {
                $ids[$fileId] = true;
            }

            if (($processed % 25) === 0) {
                self::debugLog('photo-import DAV file-props fallback progress files=' . $processed . ' matched=' . $matched);
                self::runtimePush($runId, 'Fallback DAV fichier->tags: progression ' . $processed . '/' . $maxFilesToInspect . ' (matches=' . $matched . ')', array('step' => 'matching_dav_files'));
            }
        }

        self::debugLog('photo-import DAV file-props fallback completed files=' . $processed . ' matched=' . $matched);

        return array(
            'ids' => $ids,
            'paths' => $paths,
        );
    }

    private static function sampleFilesForFallback(array $files, int $maxCount): array
    {
        $total = count($files);
        if ($total <= 0 || $maxCount <= 0) {
            return array();
        }

        if ($total <= $maxCount) {
            return $files;
        }

        $indices = array();
        $span = max(1, $maxCount - 1);
        $last = max(0, $total - 1);

        for ($i = 0; $i < $maxCount; $i++) {
            $idx = (int) round(($i * $last) / $span);
            $indices[$idx] = true;
        }

        ksort($indices);

        $sampled = array();
        foreach (array_keys($indices) as $idx) {
            if (isset($files[$idx])) {
                $sampled[] = $files[$idx];
            }
        }

        return $sampled;
    }

    private static function collectFileSelectorsByTagNamedSubfolders(array $files, string $sourceFolder, array $tagNames): array
    {
        $sourceFolder = trim(str_replace('\\', '/', $sourceFolder), '/');
        if ($sourceFolder === '') {
            return array('ids' => array(), 'paths' => array());
        }

        $folderCandidates = array();
        $tokenCandidates = array();
        $tokenCandidatesNormalized = array();
        foreach ($tagNames as $tagName) {
            $name = trim(wp_strip_all_tags((string) $tagName));
            if ($name === '') {
                continue;
            }

            $variants = array(
                $name,
                sanitize_title($name),
                str_replace('-', ' ', sanitize_title($name)),
            );

            foreach ($variants as $variant) {
                $variant = trim((string) $variant, '/ ');
                if ($variant === '') {
                    continue;
                }

                $folderCandidates[] = strtolower($sourceFolder . '/' . $variant);
                $token = strtolower($variant);
                $tokenCandidates[] = $token;
                $normalizedToken = preg_replace('/[^a-z0-9]+/i', '', $token);
                if (is_string($normalizedToken) && $normalizedToken !== '') {
                    $tokenCandidatesNormalized[] = $normalizedToken;
                }
            }
        }

        $folderCandidates = array_values(array_unique($folderCandidates));
        $tokenCandidates = array_values(array_unique(array_filter($tokenCandidates, static function (string $value): bool {
            return $value !== '';
        })));
        $tokenCandidatesNormalized = array_values(array_unique($tokenCandidatesNormalized));
        if (empty($folderCandidates)) {
            return array('ids' => array(), 'paths' => array());
        }

        $paths = array();
        foreach ($files as $file) {
            if (!is_array($file) || empty($file['path'])) {
                continue;
            }

            $filePath = trim(str_replace('\\', '/', (string) $file['path']), '/');
            if ($filePath === '') {
                continue;
            }

            $lowerPath = strtolower($filePath);
            $matched = false;

            foreach ($folderCandidates as $folderPrefix) {
                if (str_starts_with($lowerPath, $folderPrefix . '/')) {
                    $paths[$filePath] = true;
                    $matched = true;
                    break;
                }
            }

            if ($matched || empty($tokenCandidates)) {
                continue;
            }

            // Soft fallback: accept files under source folder if one path segment contains the tag token.
            $sourcePrefix = strtolower($sourceFolder . '/');
            if (!str_starts_with($lowerPath, $sourcePrefix)) {
                continue;
            }

            $relative = substr($lowerPath, strlen($sourcePrefix));
            $segments = preg_split('#/+?#', (string) $relative);
            if (!is_array($segments) || empty($segments)) {
                continue;
            }

            foreach ($segments as $segment) {
                $segment = trim((string) $segment);
                if ($segment === '') {
                    continue;
                }

                $segmentNormalized = preg_replace('/[^a-z0-9]+/i', '', $segment);
                $segmentNormalized = is_string($segmentNormalized) ? $segmentNormalized : '';

                foreach ($tokenCandidates as $token) {
                    if ($token !== '' && strpos($segment, $token) !== false) {
                        $paths[$filePath] = true;
                        $matched = true;
                        break 2;
                    }
                }

                if ($segmentNormalized !== '' && !empty($tokenCandidatesNormalized)) {
                    foreach ($tokenCandidatesNormalized as $normalizedToken) {
                        if ($normalizedToken !== '' && strpos($segmentNormalized, $normalizedToken) !== false) {
                            $paths[$filePath] = true;
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }
        }

        $matchedPreview = array();
        foreach (array_keys($paths) as $matchedPath) {
            $matchedPreview[] = $matchedPath;
            if (count($matchedPreview) >= 5) {
                break;
            }
        }

        self::debugLog(
            'photo-import folder fallback prefixes=' . implode(',', $folderCandidates)
            . ' tokens=' . implode(',', $tokenCandidates)
            . ' tokens_norm=' . implode(',', $tokenCandidatesNormalized)
            . ' matched paths=' . count($paths)
            . (empty($matchedPreview) ? '' : ' matched preview=' . implode(' | ', $matchedPreview))
        );

        return array(
            'ids' => array(),
            'paths' => $paths,
        );
    }

    private static function collectFileSelectorsByTagFolderMapping(array $files, string $sourceFolder, array $tagNames, array $tagFolderMap): array
    {
        if (empty($files) || empty($tagNames) || empty($tagFolderMap)) {
            return array('ids' => array(), 'paths' => array());
        }

        $sourceFolder = trim(str_replace('\\', '/', $sourceFolder), '/');
        $sourcePrefix = strtolower($sourceFolder);
        $folderCandidates = array();

        foreach ($tagNames as $tagName) {
            $name = trim(wp_strip_all_tags((string) $tagName));
            if ($name === '') {
                continue;
            }

            $lookupKeys = array(
                function_exists('mb_strtolower') ? mb_strtolower($name) : strtolower($name),
                sanitize_title($name),
            );
            $normalized = preg_replace('/[^a-z0-9]+/i', '', sanitize_title($name));
            if (is_string($normalized) && $normalized !== '') {
                $lookupKeys[] = $normalized;
            }

            foreach (array_values(array_unique($lookupKeys)) as $lookupKey) {
                if (!isset($tagFolderMap[$lookupKey]) || !is_array($tagFolderMap[$lookupKey])) {
                    continue;
                }

                foreach ($tagFolderMap[$lookupKey] as $folder) {
                    $folderPath = trim(str_replace('\\', '/', (string) $folder), '/');
                    if ($folderPath === '') {
                        continue;
                    }

                    $folderCandidates[] = strtolower($folderPath);

                    if ($sourcePrefix !== '' && strtolower($folderPath) !== $sourcePrefix && !str_starts_with(strtolower($folderPath), $sourcePrefix . '/')) {
                        $folderCandidates[] = strtolower($sourceFolder . '/' . $folderPath);
                    }
                }
            }
        }

        $folderCandidates = array_values(array_unique(array_filter($folderCandidates, static function (string $value): bool {
            return $value !== '';
        })));

        if (empty($folderCandidates)) {
            return array('ids' => array(), 'paths' => array());
        }

        $paths = array();
        foreach ($files as $file) {
            if (!is_array($file) || empty($file['path'])) {
                continue;
            }

            $filePath = trim(str_replace('\\', '/', (string) $file['path']), '/');
            if ($filePath === '') {
                continue;
            }

            $lowerPath = strtolower($filePath);
            foreach ($folderCandidates as $folderPrefix) {
                if ($lowerPath === $folderPrefix || str_starts_with($lowerPath, $folderPrefix . '/')) {
                    $paths[$filePath] = true;
                    break;
                }
            }
        }

        $matchedPreview = array();
        foreach (array_keys($paths) as $matchedPath) {
            $matchedPreview[] = $matchedPath;
            if (count($matchedPreview) >= 5) {
                break;
            }
        }

        self::debugLog(
            'photo-import mapped folders prefixes=' . implode(',', $folderCandidates)
            . ' matched paths=' . count($paths)
            . (empty($matchedPreview) ? '' : ' matched preview=' . implode(' | ', $matchedPreview))
        );

        return array(
            'ids' => array(),
            'paths' => $paths,
        );
    }

    private static function collectNumericIdsFromNode($node, array &$ids): void
    {
        if (is_int($node) || is_float($node)) {
            $value = trim((string) $node);
            if ($value !== '' && preg_match('/^\d+$/', $value)) {
                $ids[$value] = true;
            }
            return;
        }

        if (is_string($node)) {
            $value = trim($node);
            if ($value === '') {
                return;
            }
            if (preg_match('/^\d+$/', $value)) {
                $ids[$value] = true;
                return;
            }
            if (preg_match('/^\d+(\s*[,;]\s*\d+)+$/', $value)) {
                $parts = preg_split('/\s*[,;]\s*/', $value);
                if (is_array($parts)) {
                    foreach ($parts as $part) {
                        $part = trim((string) $part);
                        if ($part !== '' && preg_match('/^\d+$/', $part)) {
                            $ids[$part] = true;
                        }
                    }
                }
            }
            return;
        }

        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if (is_string($key) && preg_match('/^\d+$/', $key)) {
                $ids[$key] = true;
            }
            self::collectNumericIdsFromNode($value, $ids);
        }
    }

    private static function extractTagIdsFromFileRelationsPayload(array $payload): array
    {
        $found = array();
        $candidates = array(
            $payload['ocs']['data'] ?? null,
            $payload['data'] ?? null,
            $payload,
        );

        foreach ($candidates as $candidate) {
            self::extractTagIdsFromNode($candidate, $found);
        }

        return array_keys($found);
    }

    private static function extractTagIdsFromNode($node, array &$found): void
    {
        if (!is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            $lowerKey = is_string($key) ? strtolower($key) : '';
            if (in_array($lowerKey, array('systemtagid', 'system_tag_id', 'tagid', 'tag_id'), true)) {
                $candidate = trim((string) $value);
                if ($candidate !== '' && preg_match('/^\d+$/', $candidate)) {
                    $found[$candidate] = true;
                }
            }

            if (is_array($value)) {
                self::extractTagIdsFromNode($value, $found);
            }
        }
    }

    private static function listImageFilesRecursively(MjNextcloud $nextcloud, string $sourceFolder)
    {
        $pending = array($sourceFolder);
        $files = array();
        $seenFolders = array();

        while (!empty($pending)) {
            $folder = array_shift($pending);
            $folder = trim((string) $folder, '/');
            if (isset($seenFolders[$folder])) {
                continue;
            }
            $seenFolders[$folder] = true;

            $items = $nextcloud->listFolder($folder, false);
            if (is_wp_error($items)) {
                return $items;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $type = isset($item['type']) ? (string) $item['type'] : '';
                if ($type === 'folder') {
                    if (!empty($item['path'])) {
                        $pending[] = (string) $item['path'];
                    }
                    continue;
                }

                $mimeType = isset($item['mimeType']) ? (string) $item['mimeType'] : '';
                if (strpos($mimeType, 'image/') !== 0) {
                    continue;
                }

                $files[] = $item;
            }
        }

        return $files;
    }

    private static function requestOcs(string $path)
    {
        $baseUrl = Config::nextcloudUrl();
        $user = Config::nextcloudUser();
        $password = Config::nextcloudPassword();

        if ($baseUrl === '' || $user === '' || $password === '') {
            return new WP_Error('mj_member_photo_import_nextcloud_missing', __('La connexion Nextcloud n’est pas configurée.', 'mj-member'));
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => true,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
                'OCS-APIREQUEST' => 'true',
                'Accept' => 'application/json',
            ),
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'mj_member_photo_import_nextcloud_http',
                sprintf(__('Nextcloud a répondu HTTP %d.', 'mj-member'), (int) $code)
            );
        }

        return $response;
    }

    private static function requestJsonFromPaths(array $paths)
    {
        $baseUrl = Config::nextcloudUrl();
        $user = Config::nextcloudUser();
        $password = Config::nextcloudPassword();

        if ($baseUrl === '' || $user === '' || $password === '') {
            return new WP_Error('mj_member_photo_import_nextcloud_missing', __('La connexion Nextcloud n’est pas configurée.', 'mj-member'));
        }

        $bases = array(rtrim($baseUrl, '/'));
        if (preg_match('#/index\.php$#', $bases[0])) {
            $bases[] = preg_replace('#/index\.php$#', '', $bases[0]);
        } else {
            $bases[] = $bases[0] . '/index.php';
        }

        $tried = array();
        $lastError = null;
        $attemptedUrls = array();
        $lastCode = 0;

        foreach ($bases as $base) {
            if (!is_string($base) || $base === '') {
                continue;
            }

            foreach ($paths as $path) {
                $url = rtrim($base, '/') . '/' . ltrim((string) $path, '/');
                $url = str_replace('/index.php/index.php/', '/index.php/', $url);
                if (isset($tried[$url])) {
                    continue;
                }
                $tried[$url] = true;
                $attemptedUrls[] = $url;

                $response = wp_remote_get($url, array(
                    'timeout' => 30,
                    'sslverify' => true,
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
                        'OCS-APIREQUEST' => 'true',
                        'Accept' => 'application/json',
                    ),
                ));

                if (is_wp_error($response)) {
                    $lastError = $response;
                    self::debugLog('photo-import HTTP error for ' . $url . ' => ' . $response->get_error_message());
                    continue;
                }

                $code = (int) wp_remote_retrieve_response_code($response);
                $lastCode = $code;
                self::debugLog('photo-import tried ' . $url . ' => HTTP ' . $code);
                if ($code >= 200 && $code < 300) {
                    $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
                    if (is_array($decoded)) {
                        return $decoded;
                    }

                    $lastError = new WP_Error(
                        'mj_member_photo_import_invalid_json',
                        __('Réponse Nextcloud invalide (JSON attendu).', 'mj-member')
                    );
                    continue;
                }

                if ($code === 404) {
                    $lastError = new WP_Error(
                        'mj_member_photo_import_nextcloud_http_404',
                        sprintf(__('Nextcloud a répondu HTTP %d.', 'mj-member'), $code)
                    );
                    continue;
                }

                return new WP_Error(
                    'mj_member_photo_import_nextcloud_http',
                    sprintf(__('Nextcloud a répondu HTTP %d.', 'mj-member'), $code)
                );
            }
        }

        if (is_wp_error($lastError)) {
            if ($lastCode === 404) {
                self::debugLog('photo-import all endpoints returned 404. attempted=' . wp_json_encode($attemptedUrls));
                return new WP_Error(
                    'mj_member_photo_import_nextcloud_http_404',
                    __('Nextcloud a répondu HTTP 404 (API des étiquettes introuvable sur ce serveur).', 'mj-member')
                );
            }
            return $lastError;
        }

        return new WP_Error(
            'mj_member_photo_import_nextcloud_unreachable',
            __('Impossible de contacter Nextcloud pour lire les étiquettes.', 'mj-member')
        );
    }

    private static function requestJsonPayloadsFromPaths(array $paths, bool $logAttempts = true, int $timeout = 30, bool $includeIndexBase = true)
    {
        $baseUrl = Config::nextcloudUrl();
        $user = Config::nextcloudUser();
        $password = Config::nextcloudPassword();

        if ($baseUrl === '' || $user === '' || $password === '') {
            return new WP_Error('mj_member_photo_import_nextcloud_missing', __('La connexion Nextcloud n’est pas configurée.', 'mj-member'));
        }

        $bases = array(rtrim($baseUrl, '/'));
        if ($includeIndexBase) {
            if (preg_match('#/index\.php$#', $bases[0])) {
                $bases[] = preg_replace('#/index\.php$#', '', $bases[0]);
            } else {
                $bases[] = $bases[0] . '/index.php';
            }
        }

        $payloads = array();
        $tried = array();
        $lastError = null;

        foreach ($bases as $base) {
            if (!is_string($base) || $base === '') {
                continue;
            }

            foreach ($paths as $path) {
                $url = rtrim($base, '/') . '/' . ltrim((string) $path, '/');
                $url = str_replace('/index.php/index.php/', '/index.php/', $url);
                if (isset($tried[$url])) {
                    continue;
                }
                $tried[$url] = true;

                $response = wp_remote_get($url, array(
                    'timeout' => max(2, $timeout),
                    'sslverify' => true,
                    'headers' => array(
                        'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
                        'OCS-APIREQUEST' => 'true',
                        'Accept' => 'application/json',
                    ),
                ));

                if (is_wp_error($response)) {
                    $lastError = $response;
                    if ($logAttempts) {
                        self::debugLog('photo-import payload request error for ' . $url . ' => ' . $response->get_error_message());
                    }
                    continue;
                }

                $code = (int) wp_remote_retrieve_response_code($response);
                if ($logAttempts) {
                    self::debugLog('photo-import payload tried ' . $url . ' => HTTP ' . $code);
                }

                if ($code === 404) {
                    $lastError = new WP_Error('mj_member_photo_import_nextcloud_http_404', sprintf(__('Nextcloud a répondu HTTP %d.', 'mj-member'), $code));
                    continue;
                }

                if ($code < 200 || $code >= 300) {
                    $body = trim((string) wp_remote_retrieve_body($response));
                    if ($logAttempts && $body !== '') {
                        self::debugLog('photo-import payload non-2xx body sample for ' . $url . ' => ' . substr($body, 0, 220));
                    }
                    $lastError = new WP_Error(
                        'mj_member_photo_import_nextcloud_http',
                        sprintf(__('Nextcloud a répondu HTTP %d.', 'mj-member'), $code)
                    );
                    continue;
                }

                $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
                if (is_array($decoded)) {
                    $payloads[] = $decoded;
                    continue;
                }

                $lastError = new WP_Error(
                    'mj_member_photo_import_invalid_json',
                    __('Réponse Nextcloud invalide (JSON attendu).', 'mj-member')
                );
            }
        }

        if (!empty($payloads)) {
            return $payloads;
        }

        if (is_wp_error($lastError)) {
            return $lastError;
        }

        return new WP_Error(
            'mj_member_photo_import_nextcloud_unreachable',
            __('Impossible de contacter Nextcloud pour lire les étiquettes.', 'mj-member')
        );
    }

    private static function debugLog(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[mj-member][photo-import] ' . $message);
        }
    }

    private static function listAvailableTagsFromDav()
    {
        $baseUrl = Config::nextcloudUrl();
        $user = Config::nextcloudUser();
        $password = Config::nextcloudPassword();

        if ($baseUrl === '' || $user === '' || $password === '') {
            return new WP_Error('mj_member_photo_import_nextcloud_missing', __('La connexion Nextcloud n’est pas configurée.', 'mj-member'));
        }

        $url = rtrim($baseUrl, '/') . '/remote.php/dav/systemtags/';
        $body = '<?xml version="1.0"?><d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns"><d:prop><d:displayname/><oc:display-name/><oc:name/><oc:id/></d:prop></d:propfind>';

        $response = wp_remote_request($url, array(
            'method' => 'PROPFIND',
            'timeout' => 30,
            'sslverify' => true,
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($user . ':' . $password),
                'Depth' => '1',
                'Content-Type' => 'application/xml; charset=utf-8',
                'Accept' => 'application/xml,text/xml,*/*',
            ),
            'body' => $body,
        ));

        if (is_wp_error($response)) {
            self::debugLog('photo-import DAV systemtags request error: ' . $response->get_error_message());
            return $response;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        self::debugLog('photo-import DAV systemtags => HTTP ' . $code);
        if ($code < 200 || $code >= 300) {
            return new WP_Error(
                'mj_member_photo_import_nextcloud_dav_http',
                sprintf(__('Nextcloud a répondu HTTP %d.', 'mj-member'), $code)
            );
        }

        $xml = (string) wp_remote_retrieve_body($response);
        if ($xml === '') {
            return array();
        }

        if (!class_exists('DOMDocument') || !class_exists('DOMXPath')) {
            return array();
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return array();
        }

        $xpath = new \DOMXPath($dom);

        // Use local-name() so parsing remains stable across namespace/prefix variants.
        $nodes = $xpath->query('//*[local-name()="response"]');
        if (!$nodes) {
            return array();
        }
        self::debugLog('photo-import DAV response nodes=' . (int) $nodes->length);

        $tags = array();
        foreach ($nodes as $node) {
            $hrefNode = $xpath->query('.//*[local-name()="href"]', $node);
            $href = ($hrefNode && $hrefNode->length > 0) ? trim((string) $hrefNode->item(0)->textContent) : '';

            $idNode = $xpath->query('.//*[local-name()="id"]', $node);
            $id = ($idNode && $idNode->length > 0) ? trim((string) $idNode->item(0)->textContent) : '';
            if ($id === '' && $href !== '') {
                $trimmedHref = trim($href, '/');
                $segments = explode('/', $trimmedHref);
                $last = end($segments);
                if (is_string($last) && $last !== '' && strtolower($last) !== 'systemtags') {
                    $id = rawurldecode($last);
                }
            }

            $nameNode = $xpath->query('.//*[local-name()="display-name"]', $node);
            if (!$nameNode || $nameNode->length === 0) {
                $nameNode = $xpath->query('.//*[local-name()="displayname"]', $node);
            }
            if (!$nameNode || $nameNode->length === 0) {
                $nameNode = $xpath->query('.//*[local-name()="display-name"]', $node);
            }
            if (!$nameNode || $nameNode->length === 0) {
                $nameNode = $xpath->query('.//*[local-name()="name"]', $node);
            }
            if (!$nameNode || $nameNode->length === 0) {
                $nameNode = $xpath->query('.//*[local-name()="label"]', $node);
            }
            $name = ($nameNode && $nameNode->length > 0) ? trim((string) $nameNode->item(0)->textContent) : '';
            $name = trim(wp_strip_all_tags($name));

            if ($id === '' && $name === '') {
                continue;
            }

            if ($id === '' && $name !== '') {
                $id = $name;
            }

            if ($name === '' && $id !== '') {
                $name = $id;
            }

            self::debugLog('photo-import DAV parsed node href=' . $href . ' id=' . $id . ' name=' . $name);

            $tags[] = array(
                'id' => $id,
                'name' => $name,
            );
        }

        return array_values(array_unique($tags, SORT_REGULAR));
    }

    private static function resolveTagLabelById(string $tagId): string
    {
        $tagId = trim($tagId);
        if ($tagId === '') {
            return '';
        }

        $payloads = self::requestJsonPayloadsFromPaths(array(
            '/ocs/v1.php/apps/files/api/v1/systemtags/' . rawurlencode($tagId) . '?format=json',
            '/index.php/ocs/v1.php/apps/files/api/v1/systemtags/' . rawurlencode($tagId) . '?format=json',
            '/ocs/v2.php/apps/files/api/v1/systemtags/' . rawurlencode($tagId) . '?format=json',
            '/index.php/ocs/v2.php/apps/files/api/v1/systemtags/' . rawurlencode($tagId) . '?format=json',
            '/ocs/v1.php/apps/files/api/v1/systemtags/' . rawurlencode($tagId),
            '/index.php/ocs/v1.php/apps/files/api/v1/systemtags/' . rawurlencode($tagId),
        ));

        if (is_wp_error($payloads) || !is_array($payloads)) {
            return '';
        }

        foreach ($payloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            // First, reuse global payload parser and pick current tag.
            $parsedTags = self::extractTagsFromAnyPayload($payload);
            foreach ($parsedTags as $parsedTag) {
                if (!is_array($parsedTag)) {
                    continue;
                }
                $parsedId = isset($parsedTag['id']) ? trim((string) $parsedTag['id']) : '';
                $parsedName = isset($parsedTag['name']) ? trim((string) $parsedTag['name']) : '';
                if ($parsedId === $tagId && $parsedName !== '' && !preg_match('/^\d+$/', $parsedName)) {
                    self::debugLog('photo-import resolved label for tag ' . $tagId . ' => ' . $parsedName . ' (parsedTags)');
                    return $parsedName;
                }
            }

            $entry = $payload['ocs']['data'] ?? ($payload['data'] ?? $payload);
            $name = self::extractLabelFromUnknownEntry($entry, $tagId);
            if ($name !== '') {
                self::debugLog('photo-import resolved label for tag ' . $tagId . ' => ' . $name . ' (entry)');
                return $name;
            }

            $keys = implode(',', array_map('strval', array_keys($payload)));
            self::debugLog('photo-import unresolved label payload keys for tag ' . $tagId . ' => ' . $keys);
        }

        return '';
    }

    private static function extractLabelFromUnknownEntry($entry, string $tagId): string
    {
        if (is_string($entry)) {
            $candidate = trim(wp_strip_all_tags($entry));
            if ($candidate !== '' && !preg_match('/^\d+$/', $candidate)) {
                return $candidate;
            }
            return '';
        }

        if (!is_array($entry)) {
            return '';
        }

        $keysToTry = array('display-name', 'displayName', 'name', 'label', 'title', 'text', 'userVisible');
        foreach ($keysToTry as $key) {
            if (!isset($entry[$key])) {
                continue;
            }
            $candidate = trim(wp_strip_all_tags((string) $entry[$key]));
            if ($candidate !== '' && !preg_match('/^\d+$/', $candidate)) {
                return $candidate;
            }
        }

        // Some responses are maps keyed by tag id.
        if (isset($entry[$tagId])) {
            $fromId = self::extractLabelFromUnknownEntry($entry[$tagId], $tagId);
            if ($fromId !== '') {
                return $fromId;
            }
        }

        foreach ($entry as $value) {
            $candidate = self::extractLabelFromUnknownEntry($value, $tagId);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private static function generateResizedImage(string $sourcePath, string $targetPath, int $width, int $height, bool $crop, ?int $quality = null)
    {
        if (!function_exists('wp_get_image_editor')) {
            if (@copy($sourcePath, $targetPath)) {
                self::debugLog('photo-import resized fallback copy used (no editor) for ' . $targetPath);
                return true;
            }
            return new WP_Error('mj_member_photo_import_image_editor_missing', __('L’éditeur d’image WordPress est indisponible.', 'mj-member'));
        }

        $editor = wp_get_image_editor($sourcePath);
        if (is_wp_error($editor)) {
            if (@copy($sourcePath, $targetPath)) {
                self::debugLog('photo-import resized fallback copy used (editor error) for ' . $targetPath . ' msg=' . $editor->get_error_message());
                return true;
            }
            return $editor;
        }

        if ($quality !== null && method_exists($editor, 'set_quality')) {
            $editor->set_quality(max(30, min(95, (int) $quality)));
        }

        $resize = $editor->resize($width, $height, $crop);
        if (is_wp_error($resize)) {
            self::debugLog('photo-import resize failed primary: ' . $resize->get_error_message() . ' source=' . $sourcePath);

            $retryEditor = wp_get_image_editor($sourcePath);
            if (!is_wp_error($retryEditor)) {
                if ($quality !== null && method_exists($retryEditor, 'set_quality')) {
                    $retryEditor->set_quality(max(30, min(95, (int) $quality)));
                }
                $retryResize = $retryEditor->resize($width, $height, $crop);
                if (!is_wp_error($retryResize)) {
                    $retrySaved = $retryEditor->save($targetPath);
                    if (!is_wp_error($retrySaved)) {
                        return true;
                    }
                    self::debugLog('photo-import resized save failed after resize-retry: ' . $retrySaved->get_error_message() . ' source=' . $sourcePath);
                }
            }

            if (@copy($sourcePath, $targetPath)) {
                self::debugLog('photo-import resized fallback copy used (resize error) for ' . $targetPath);
                return true;
            }

            return $resize;
        }

        $saved = $editor->save($targetPath, 'image/jpeg');
        if (!is_wp_error($saved)) {
            return true;
        }

        self::debugLog('photo-import resized save failed primary: ' . $saved->get_error_message() . ' source=' . $sourcePath);

        // Retry with a fresh editor and default mime inference.
        $retryEditor = wp_get_image_editor($sourcePath);
        if (!is_wp_error($retryEditor)) {
            if ($quality !== null && method_exists($retryEditor, 'set_quality')) {
                $retryEditor->set_quality(max(30, min(95, (int) $quality)));
            }
            $retryResize = $retryEditor->resize($width, $height, $crop);
            if (!is_wp_error($retryResize)) {
                $retrySaved = $retryEditor->save($targetPath);
                if (!is_wp_error($retrySaved)) {
                    return true;
                }
                self::debugLog('photo-import resized save failed retry: ' . $retrySaved->get_error_message() . ' source=' . $sourcePath);
            }
        }

        // Last-resort: keep import operational by copying original binary.
        if (@copy($sourcePath, $targetPath)) {
            self::debugLog('photo-import resized fallback copy used for ' . $targetPath);
            return true;
        }

        return $saved;
    }

    private static function ensureStorageDirectories()
    {
        $folders = array(
            Config::path() . 'data/photo-import',
            Config::path() . 'data/photo-import/original',
            Config::path() . 'data/photo-import/thumb',
            Config::path() . 'data/photo-import/display',
        );

        foreach ($folders as $folder) {
            if (is_dir($folder)) {
                continue;
            }
            if (!wp_mkdir_p($folder)) {
                return new WP_Error(
                    'mj_member_photo_import_storage_unavailable',
                    sprintf(__('Impossible de créer le dossier de stockage : %s', 'mj-member'), $folder)
                );
            }
        }

        return true;
    }

    private static function isThumbnailOptimized(string $path, int $targetWidth, int $targetHeight): bool
    {
        if (!self::isUsableGeneratedImage($path)) {
            return false;
        }

        // Keep grid thumbnails lightweight.
        $maxBytes = 450 * 1024;
        $size = (int) @filesize($path);
        if ($size <= 0 || $size > $maxBytes) {
            return false;
        }

        $imageSize = @getimagesize($path);
        if (!is_array($imageSize) || !isset($imageSize[0], $imageSize[1])) {
            return false;
        }

        $w = (int) $imageSize[0];
        $h = (int) $imageSize[1];
        if ($w <= 0 || $h <= 0) {
            return false;
        }

        $maxW = max(1, $targetWidth * 2);
        $maxH = max(1, $targetHeight * 2);
        return $w <= $maxW && $h <= $maxH;
    }

    private static function getManifestPath(): string
    {
        return Config::path() . 'data/photo-import/manifest.json';
    }

    private static function loadManifest(): array
    {
        $path = self::getManifestPath();
        if (!file_exists($path)) {
            return array('updated_at' => 0, 'items' => array());
        }

        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return array('updated_at' => 0, 'items' => array());
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array('updated_at' => 0, 'items' => array());
        }

        $items = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : array();
        return array(
            'updated_at' => isset($decoded['updated_at']) ? (int) $decoded['updated_at'] : 0,
            'items' => $items,
        );
    }

    private static function saveManifest(array $manifest): void
    {
        $manifest['updated_at'] = time();
        $path = self::getManifestPath();

        $json = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return;
        }

        file_put_contents($path, $json, LOCK_EX);
    }

    private static function normalizeTags($raw): array
    {
        if (is_string($raw)) {
            $parts = preg_split('/[\r\n,;]+/', $raw);
            if (!is_array($parts)) {
                return array();
            }
            $raw = $parts;
        }

        if (!is_array($raw)) {
            return array();
        }

        $normalized = array();
        foreach ($raw as $tag) {
            $name = trim((string) $tag);
            if ($name === '') {
                continue;
            }
            $normalized[] = wp_strip_all_tags($name);
        }

        $normalized = array_values(array_unique(array_filter($normalized)));
        return $normalized;
    }

    private static function parseTagFolderMap(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return array();
        }

        $lines = preg_split('/\r\n|\r|\n/', $raw);
        if (!is_array($lines)) {
            return array();
        }

        $map = array();
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $pair = preg_split('/\s*[:=]\s*/', $line, 2);
            if (!is_array($pair) || count($pair) < 2) {
                continue;
            }

            $tag = trim(wp_strip_all_tags((string) $pair[0]));
            $folder = trim((string) $pair[1], "/\\ \t\n\r\0\x0B");
            if ($tag === '' || $folder === '') {
                continue;
            }

            $keys = array(
                function_exists('mb_strtolower') ? mb_strtolower($tag) : strtolower($tag),
                sanitize_title($tag),
            );
            $normalized = preg_replace('/[^a-z0-9]+/i', '', sanitize_title($tag));
            if (is_string($normalized) && $normalized !== '') {
                $keys[] = $normalized;
            }

            foreach (array_values(array_unique($keys)) as $key) {
                if ($key === '') {
                    continue;
                }
                if (!isset($map[$key])) {
                    $map[$key] = array();
                }
                $map[$key][$folder] = true;
            }
        }

        foreach ($map as $key => $folders) {
            if (!is_array($folders)) {
                unset($map[$key]);
                continue;
            }
            $map[$key] = array_values(array_keys($folders));
        }

        return $map;
    }
}
