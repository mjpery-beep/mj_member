<?php

namespace Mj\Member\Admin\Page;

use Mj\Member\Admin\RequestGuard;
use Mj\Member\Classes\Crud\MjMemberHours;
use Mj\Member\Classes\Crud\MjMembers;
use Mj\Member\Classes\Value\MemberData;
use Mj\Member\Core\Config;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

final class HoursPage
{
    private const RECENT_ENTRIES_LIMIT = 50;
    private const PROJECT_EMPTY_KEY = MjMemberHours::PROJECT_EMPTY_KEY;
    public static function slug(): string
    {
        return 'mj_member_hours';
    }

    
    public static function render(): void
    {
        $capability = Config::hoursCapability();
        if ($capability === '') {
            $capability = Config::capability();
        }

        RequestGuard::ensureCapabilityOrDie($capability);

        $currentUserId = get_current_user_id();
        if ($currentUserId <= 0) {
            wp_die(esc_html__('Utilisateur non authentifié.', 'mj-member'));
        }

        $member = MjMembers::getByWpUserId($currentUserId);
        if (!($member instanceof MemberData)) {
            wp_die(esc_html__('Aucun profil membre n’est associé à votre compte. Contactez un administrateur.', 'mj-member'));
        }

        $memberId = (int) ($member->id ?? 0);
        if ($memberId <= 0) {
            wp_die(esc_html__('Profil membre invalide pour l’accès aux heures.', 'mj-member'));
        }

        $canManageOthers = self::canManageOtherMembers();
        if (!$canManageOthers) {
            wp_die(esc_html__('Cette vue est réservée aux gestionnaires.', 'mj-member'));
        }

        $config = self::prepareDashboardConfig();
        self::enqueueDashboardAssets($config);

        $template = Config::path() . 'includes/templates/admin/hours-page.php';
        if (is_readable($template)) {
            require $template;
        }
    }

    private static function prepareDashboardConfig(): array
    {
        $projectWithoutLabel = __('Sans projet', 'mj-member');
        $data = self::prepareDashboardData($projectWithoutLabel);

        return array(
            'data' => $data,
            'i18n' => array(
                'pageTitle' => __('Tableau de bord des heures', 'mj-member'),
                'updatedAtLabel' => __('Mis à jour le %s', 'mj-member'),
                'totalHours' => __('Heures totales encodées', 'mj-member'),
                'membersCount' => __('Membres', 'mj-member'),
                'projectsCount' => __('Projets', 'mj-member'),
                'unassignedHours' => __('Heures sans projet', 'mj-member'),
                'entriesLabel' => __('encodages', 'mj-member'),
                'projectsDonutTitle' => __('Répartition des heures du membre sélectionné par projet', 'mj-member'),
                'memberDonutTitle' => __('Répartition des projets du membre', 'mj-member'),
                'memberSelectLabel' => __('Sélectionnez un membre', 'mj-member'),
                'memberSelectHelper' => __('Le membre sélectionné met à jour tous les graphiques ci-dessous.', 'mj-member'),
                'memberTableTitle' => __('Heures par membre', 'mj-member'),
                'memberColumn' => __('Membre', 'mj-member'),
                'hoursColumn' => __('Heures', 'mj-member'),
                'entriesColumn' => __('Encodages', 'mj-member'),
                'rateColumn' => __('Taux', 'mj-member'),
                'noMemberData' => __('Aucun encodage enregistré pour l’instant.', 'mj-member'),
                'noProjectsForMember' => __('Aucun projet encodé pour ce membre.', 'mj-member'),
                'projectWithoutLabel' => $projectWithoutLabel,
                'monthlyHoursTitle' => __('Heures encodées par mois', 'mj-member'),
                'weeklyHoursTitle' => __('Heures encodées par semaine', 'mj-member'),
                'averageWeeklyHours' => __('Moyenne hebdomadaire encodée', 'mj-member'),
                'weeklyAverageMetaFallback' => __('Aucune semaine récente pour calculer la moyenne.', 'mj-member'),
                'weeklyRequiredLabel' => __('Heures dues', 'mj-member'),
                'weeklyExtraLabel' => __('Heures supplémentaires', 'mj-member'),
                'weeklyExpectedLabel' => __('Heures attendues', 'mj-member'),
                'weeklyDeficitLabel' => __('Heures manquantes', 'mj-member'),
                'weeklyBalanceNetLabel' => __('Solde cumulé', 'mj-member'),
                'barChartEmpty' => __('Aucune donnée disponible pour cette période.', 'mj-member'),
                'renderError' => __('Impossible d’afficher le tableau de bord pour le moment. Merci de rafraîchir la page.', 'mj-member'),
            ),
        );
    }

    private static function enqueueDashboardAssets(array $config): void
    {
        $baseUrl = trailingslashit(Config::url());
        $basePath = trailingslashit(Config::path());

        $stylePath = $basePath . 'css/admin-hours-dashboard.css';
        $styleUrl = $baseUrl . 'css/admin-hours-dashboard.css';
        $styleVersion = file_exists($stylePath) ? (string) filemtime($stylePath) : Config::version();
        wp_enqueue_style('mj-member-admin-hours-dashboard', $styleUrl, array(), $styleVersion);

        $scriptPath = $basePath . 'js/admin-hours-dashboard.js';
        $scriptUrl = $baseUrl . 'js/admin-hours-dashboard.js';
        $scriptVersion = file_exists($scriptPath) ? (string) filemtime($scriptPath) : Config::version();
        wp_enqueue_script('mj-member-admin-hours-dashboard', $scriptUrl, array(), $scriptVersion, true);
        wp_script_add_data('mj-member-admin-hours-dashboard', 'type', 'module');

        $inlineConfig = wp_json_encode($config);
        if (!is_string($inlineConfig)) {
            $inlineConfig = '{}';
        }

        $inlineScript = 'window.mjMemberHoursDashboardConfig = ' . $inlineConfig . ';';
        wp_add_inline_script('mj-member-admin-hours-dashboard', $inlineScript, 'before');
    }

    private static function prepareDashboardData(string $projectWithoutLabel): array
    {
        $projectTotals = MjMemberHours::get_project_totals();
        $memberTotals = MjMemberHours::get_totals_by_member();
        $memberProjectTotals = MjMemberHours::get_member_project_totals();

        $memberIds = array_map(static function (array $row): int {
            return isset($row['member_id']) ? (int) $row['member_id'] : 0;
        }, $memberTotals);

        $memberLabels = self::fetchMemberLabels($memberIds);

        $projects = array();
        $totalMinutesFromProjects = 0;
        $totalEntriesFromProjects = 0;
        $unassignedMinutes = 0;

        foreach ($projectTotals as $row) {
            $rawLabel = isset($row['project_label']) ? (string) $row['project_label'] : '';
            $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $projectColor = isset($row['project_color']) ? sanitize_hex_color((string) $row['project_color']) : '';
            if (!is_string($projectColor)) {
                $projectColor = '';
            }
            $totalMinutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;

            if ($totalMinutes <= 0) {
                continue;
            }

            $key = $rawLabel === '' ? self::PROJECT_EMPTY_KEY : $rawLabel;
            $label = $rawLabel === '' ? $projectWithoutLabel : $rawLabel;
            $isUnassigned = ($rawLabel === '');

            if ($isUnassigned) {
                $unassignedMinutes += $totalMinutes;
            }

            $projects[] = array(
                'key' => $key,
                'label' => $label,
                'raw_label' => $rawLabel,
                'project_id' => $projectId,
                'color' => $projectColor,
                'minutes' => $totalMinutes,
                'human' => self::formatDuration($totalMinutes),
                'entries' => $entries,
                'is_unassigned' => $isUnassigned,
            );

            $totalMinutesFromProjects += $totalMinutes;
            $totalEntriesFromProjects += $entries;
        }

        usort($projects, static function (array $a, array $b): int {
            return $b['minutes'] <=> $a['minutes'];
        });

        $memberProjectsMap = array();
        foreach ($memberProjectTotals as $row) {
            $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
            $minutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            if ($memberId <= 0 || $minutes <= 0) {
                continue;
            }

            $rawLabel = isset($row['project_label']) ? (string) $row['project_label'] : '';
            $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $projectColor = isset($row['project_color']) ? sanitize_hex_color((string) $row['project_color']) : '';
            if (!is_string($projectColor)) {
                $projectColor = '';
            }
            $key = $rawLabel === '' ? self::PROJECT_EMPTY_KEY : $rawLabel;
            $label = $rawLabel === '' ? $projectWithoutLabel : $rawLabel;
            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;

            if (!isset($memberProjectsMap[$memberId])) {
                $memberProjectsMap[$memberId] = array();
            }

            $memberProjectsMap[$memberId][] = array(
                'key' => $key,
                'label' => $label,
                'raw_label' => $rawLabel,
                'project_id' => $projectId,
                'color' => $projectColor,
                'minutes' => $minutes,
                'human' => self::formatDuration($minutes),
                'entries' => $entries,
                'is_unassigned' => ($rawLabel === ''),
            );
        }

        foreach ($memberProjectsMap as $memberId => $items) {
            usort($items, static function (array $a, array $b): int {
                return $b['minutes'] <=> $a['minutes'];
            });
            $memberProjectsMap[$memberId] = $items;
        }

        $members = array();
        $totalMinutesFromMembers = 0;
        $totalEntriesFromMembers = 0;
        $contractMinutesByMember = array();

        foreach ($memberTotals as $row) {
            $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
            $minutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            if ($memberId <= 0 || $minutes <= 0) {
                continue;
            }

            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;
            $label = isset($memberLabels[$memberId]['label']) ? (string) $memberLabels[$memberId]['label'] : sprintf(__('Membre #%d', 'mj-member'), $memberId);
            $weeklyContractMinutes = isset($memberLabels[$memberId]['weekly_contract_minutes']) ? (int) $memberLabels[$memberId]['weekly_contract_minutes'] : 0;

            $contractMinutesByMember[$memberId] = $weeklyContractMinutes;

            $members[] = array(
                'id' => $memberId,
                'label' => $label,
                'minutes' => $minutes,
                'human' => self::formatDuration($minutes),
                'entries' => $entries,
                'projects' => $memberProjectsMap[$memberId] ?? array(),
                'weekly_contract_minutes' => $weeklyContractMinutes,
                'weekly_contract_human' => self::formatDuration($weeklyContractMinutes),
            );

            $totalMinutesFromMembers += $minutes;
            $totalEntriesFromMembers += $entries;
        }

        $aggregateWeeklyContractMinutes = (int) array_sum($contractMinutesByMember);

        usort($members, static function (array $a, array $b): int {
            return $b['minutes'] <=> $a['minutes'];
        });

        $totalMinutes = $totalMinutesFromProjects > 0 ? $totalMinutesFromProjects : $totalMinutesFromMembers;
        $totalEntries = $totalEntriesFromProjects > 0 ? $totalEntriesFromProjects : $totalEntriesFromMembers;

        $memberIdsForSeries = array_map(static function (array $member): int {
            return isset($member['id']) ? (int) $member['id'] : 0;
        }, $members);

        $memberIdsForSeries = array_values(array_filter(array_unique($memberIdsForSeries), static function ($value) {
            return (int) $value > 0;
        }));

        $monthlySeriesLimit = 12;
        $weeklySeriesLimit = 12;
        $monthlyCutoff = gmdate('Y-m-01', strtotime('-' . ($monthlySeriesLimit + 6) . ' months'));
        $weeklyCutoff = gmdate('Y-m-d', strtotime('-' . (($weeklySeriesLimit + 4) * 7) . ' days'));

        $monthlyTotals = MjMemberHours::get_monthly_totals(array(
            'member_ids' => $memberIdsForSeries,
            'group_by_member' => true,
            'date_from' => $monthlyCutoff,
        ));

        $weeklyTotals = MjMemberHours::get_weekly_totals_summary(array(
            'member_ids' => $memberIdsForSeries,
            'group_by_member' => true,
            'date_from' => $weeklyCutoff,
        ));

        $monthlySeries = self::prepareMonthlySeries($monthlyTotals, $monthlySeriesLimit);
        $weeklySeries = self::prepareWeeklySeries($weeklyTotals, $weeklySeriesLimit);
        $weeklySeries = self::augmentWeeklySeriesWithExpectations($weeklySeries, $contractMinutesByMember, $aggregateWeeklyContractMinutes);

        $weeklyBalanceByMember = array();
        if (isset($weeklySeries['by_member']) && is_array($weeklySeries['by_member'])) {
            foreach ($weeklySeries['by_member'] as $memberId => $memberRows) {
                $memberId = (int) $memberId;
                if ($memberId <= 0) {
                    continue;
                }
                $weeklyBalanceByMember[$memberId] = self::sumWeeklyDifferenceMinutes(is_array($memberRows) ? $memberRows : array());
            }
        }

        foreach ($members as &$memberRow) {
            $memberId = isset($memberRow['id']) ? (int) $memberRow['id'] : 0;
            if ($memberId <= 0) {
                continue;
            }

            $balanceMinutes = $weeklyBalanceByMember[$memberId] ?? 0;
            $memberRow['weekly_balance_minutes'] = $balanceMinutes;
            $memberRow['weekly_balance_human'] = self::formatSignedDuration($balanceMinutes);
        }
        unset($memberRow);

        $aggregateWeeklyBalanceMinutes = self::sumWeeklyDifferenceMinutes($weeklySeries['all'] ?? array());
        $aggregateWeeklyBalanceHuman = self::formatSignedDuration($aggregateWeeklyBalanceMinutes);

        $weeklyAverageMinutes = 0;
        $weeklyAverageWeeks = count($weeklySeries['all']);
        $weeklyAverageMeta = '';
        $weeklyExtraRecentMinutes = 0;

        if ($weeklyAverageWeeks > 0) {
            $sumWeeklyMinutes = 0;
            foreach ($weeklySeries['all'] as $weekRow) {
                $sumWeeklyMinutes += isset($weekRow['minutes']) ? (int) $weekRow['minutes'] : 0;
                $weeklyExtraRecentMinutes += isset($weekRow['extra_minutes']) ? (int) $weekRow['extra_minutes'] : 0;
            }

            $weeklyAverageMinutes = (int) round($sumWeeklyMinutes / $weeklyAverageWeeks);
            $weeklyAverageMeta = sprintf(
                _n(
                    'Moyenne calculée sur %d semaine récente',
                    'Moyenne calculée sur %d semaines récentes',
                    $weeklyAverageWeeks,
                    'mj-member'
                ),
                $weeklyAverageWeeks
            );
        }

        $timeseries = array(
            'months' => $monthlySeries['all'],
            'weeks' => $weeklySeries['all'],
            'months_by_member' => $monthlySeries['by_member'],
            'weeks_by_member' => $weeklySeries['by_member'],
        );

        $weeklyAverageHuman = self::formatDuration($weeklyAverageMinutes);
        $weeklyExtraRecentHuman = self::formatDuration($weeklyExtraRecentMinutes);

        $generatedTimestamp = current_time('timestamp');

        return array(
            'projects' => $projects,
            'members' => $members,
            'totals' => array(
                'minutes' => $totalMinutes,
                'human' => self::formatDuration($totalMinutes),
                'entries' => $totalEntries,
                'member_count' => count($members),
                'project_count' => count($projects),
                'unassigned_minutes' => $unassignedMinutes,
                'unassigned_human' => self::formatDuration($unassignedMinutes),
                'weekly_average_minutes' => $weeklyAverageMinutes,
                'weekly_average_human' => $weeklyAverageHuman,
                'weekly_average_weeks' => $weeklyAverageWeeks,
                'weekly_average_meta' => $weeklyAverageMeta,
                'weekly_contract_minutes' => $aggregateWeeklyContractMinutes,
                'weekly_contract_human' => self::formatDuration($aggregateWeeklyContractMinutes),
                'weekly_extra_recent_minutes' => $weeklyExtraRecentMinutes,
                'weekly_extra_recent_human' => $weeklyExtraRecentHuman,
                'weekly_balance_minutes' => $aggregateWeeklyBalanceMinutes,
                'weekly_balance_human' => $aggregateWeeklyBalanceHuman,
            ),
            'timeseries' => $timeseries,
            'generated_at' => gmdate('c', $generatedTimestamp),
            'generated_at_display' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $generatedTimestamp),
            'project_without_label' => $projectWithoutLabel,
        );
    }

    public static function formatDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 min';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours === 0) {
            return sprintf('%d min', $rest);
        }

        if ($rest === 0) {
            return sprintf(_n('%d heure', '%d heures', $hours, 'mj-member'), $hours);
        }

        return sprintf('%s %s', sprintf(_n('%d heure', '%d heures', $hours, 'mj-member'), $hours), sprintf('%d min', $rest));
    }

    /**
     * @param array<int,array<string,mixed>> $monthlyTotals
     * @param int $limit
     * @return array{all: array<int,array<string,mixed>>, by_member: array<int,array<int,array<string,mixed>>>}
     */
    private static function prepareMonthlySeries(array $monthlyTotals, int $limit): array
    {
        $groupedRaw = array();
        $aggregateRaw = array();

        foreach ($monthlyTotals as $row) {
            $periodStart = isset($row['period_start']) ? (string) $row['period_start'] : '';
            $minutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            if ($periodStart === '' || $minutes <= 0) {
                continue;
            }

            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;
            $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;

            if ($memberId > 0) {
                if (!isset($groupedRaw[$memberId])) {
                    $groupedRaw[$memberId] = array();
                }
                $groupedRaw[$memberId][] = array(
                    'period_start' => $periodStart,
                    'total_minutes' => $minutes,
                    'entries' => $entries,
                );
            }

            if (!isset($aggregateRaw[$periodStart])) {
                $aggregateRaw[$periodStart] = array(
                    'period_start' => $periodStart,
                    'total_minutes' => 0,
                    'entries' => 0,
                );
            }

            $aggregateRaw[$periodStart]['total_minutes'] += $minutes;
            $aggregateRaw[$periodStart]['entries'] += $entries;
        }

        $byMember = array();
        foreach ($groupedRaw as $memberId => $memberRows) {
            $memberSeries = self::formatMonthlySeries($memberRows, $limit);
            if (!empty($memberSeries)) {
                $byMember[$memberId] = $memberSeries;
            }
        }

        $allSeries = self::formatMonthlySeries(array_values($aggregateRaw), $limit);

        return array(
            'all' => $allSeries,
            'by_member' => $byMember,
        );
    }

    /**
     * @param array<int,array<string,mixed>> $weeklyTotals
     * @param int $limit
     * @return array{all: array<int,array<string,mixed>>, by_member: array<int,array<int,array<string,mixed>>>}
     */
    private static function prepareWeeklySeries(array $weeklyTotals, int $limit): array
    {
        $groupedRaw = array();
        $aggregateRaw = array();

        foreach ($weeklyTotals as $row) {
            $isoYear = isset($row['iso_year']) ? (int) $row['iso_year'] : 0;
            $isoWeek = isset($row['iso_week']) ? (int) $row['iso_week'] : 0;
            $minutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            if ($isoYear <= 0 || $isoWeek <= 0 || $minutes <= 0) {
                continue;
            }

            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;
            $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
            $aggregateKey = sprintf('%04d-%02d', $isoYear, max(1, min($isoWeek, 53)));

            if ($memberId > 0) {
                if (!isset($groupedRaw[$memberId])) {
                    $groupedRaw[$memberId] = array();
                }
                $groupedRaw[$memberId][] = array(
                    'iso_year' => $isoYear,
                    'iso_week' => $isoWeek,
                    'total_minutes' => $minutes,
                    'entries' => $entries,
                );
            }

            if (!isset($aggregateRaw[$aggregateKey])) {
                $aggregateRaw[$aggregateKey] = array(
                    'iso_year' => $isoYear,
                    'iso_week' => $isoWeek,
                    'total_minutes' => 0,
                    'entries' => 0,
                );
            }

            $aggregateRaw[$aggregateKey]['total_minutes'] += $minutes;
            $aggregateRaw[$aggregateKey]['entries'] += $entries;
        }

        $byMember = array();
        foreach ($groupedRaw as $memberId => $memberRows) {
            $memberSeries = self::formatWeeklySeries($memberRows, $limit);
            if (!empty($memberSeries)) {
                $byMember[$memberId] = $memberSeries;
            }
        }

        $allSeries = self::formatWeeklySeries(array_values($aggregateRaw), $limit);

        return array(
            'all' => $allSeries,
            'by_member' => $byMember,
        );
    }

    /**
     * @param array<int,array<string,mixed>> $monthlyTotals
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    private static function formatMonthlySeries(array $monthlyTotals, int $limit = 12): array
    {
        $series = array();

        foreach ($monthlyTotals as $row) {
            $periodStart = isset($row['period_start']) ? (string) $row['period_start'] : '';
            $minutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            if ($periodStart === '' || $minutes <= 0) {
                continue;
            }

            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;
            $timestamp = strtotime($periodStart . ' 00:00:00');

            $label = $timestamp ? wp_date('F Y', $timestamp) : $periodStart;
            $shortLabel = $timestamp ? wp_date('M', $timestamp) : $periodStart;

            $series[] = array(
                'key' => $periodStart,
                'label' => $label,
                'short_label' => $shortLabel,
                'minutes' => $minutes,
                'human' => self::formatDuration($minutes),
                'entries' => $entries,
            );
        }

        usort($series, static function (array $a, array $b): int {
            return strcmp($a['key'], $b['key']);
        });

        if ($limit > 0 && count($series) > $limit) {
            $series = array_slice($series, -$limit);
        }

        return array_values($series);
    }

    /**
     * @param array<int,array<string,mixed>> $weeklyTotals
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    private static function formatWeeklySeries(array $weeklyTotals, int $limit = 12): array
    {
        $series = array();

        foreach ($weeklyTotals as $row) {
            $isoYear = isset($row['iso_year']) ? (int) $row['iso_year'] : 0;
            $isoWeek = isset($row['iso_week']) ? (int) $row['iso_week'] : 0;
            $minutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            if ($isoYear <= 0 || $isoWeek <= 0 || $minutes <= 0) {
                continue;
            }

            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;
            $timestamp = self::createWeekStartTimestamp($isoYear, $isoWeek);
            $key = sprintf('%04d-W%02d', $isoYear, max(1, min($isoWeek, 53)));

            $series[] = array(
                'key' => $key,
                'label' => self::formatWeekLabel($isoYear, $isoWeek, $timestamp),
                'short_label' => self::formatWeekShortLabel($isoWeek),
                'minutes' => $minutes,
                'human' => self::formatDuration($minutes),
                'entries' => $entries,
            );
        }

        usort($series, static function (array $a, array $b): int {
            return strcmp($a['key'], $b['key']);
        });

        if ($limit > 0 && count($series) > $limit) {
            $series = array_slice($series, -$limit);
        }

        return array_values($series);
    }

    private static function augmentWeeklySeriesWithExpectations(array $seriesData, array $contractMinutesByMember, int $aggregateContractMinutes): array
    {
        $seriesData['all'] = self::applyWeeklyExpectationToSeries($seriesData['all'], $aggregateContractMinutes);

        $decoratedByMember = array();
        foreach ($seriesData['by_member'] as $memberId => $memberSeries) {
            $expected = isset($contractMinutesByMember[$memberId]) ? (int) $contractMinutesByMember[$memberId] : 0;
            $decoratedByMember[$memberId] = self::applyWeeklyExpectationToSeries($memberSeries, $expected);
        }

        $seriesData['by_member'] = $decoratedByMember;

        return $seriesData;
    }

    private static function applyWeeklyExpectationToSeries(array $series, int $expectedMinutesPerWeek): array
    {
        $expectedMinutesPerWeek = max(0, $expectedMinutesPerWeek);

        foreach ($series as &$item) {
            $actual = isset($item['minutes']) ? max(0, (int) $item['minutes']) : 0;
            $expected = $expectedMinutesPerWeek;
            $required = $expected > 0 ? min($actual, $expected) : $actual;
            $extra = $actual > $expected ? $actual - $expected : 0;
            $deficit = ($expected > 0 && $actual < $expected) ? $expected - $actual : 0;
            $difference = $actual - $expected;

            $item['expected_minutes'] = $expected;
            $item['expected_human'] = self::formatDuration($expected);
            $item['required_minutes'] = $required;
            $item['required_human'] = self::formatDuration($required);
            $item['extra_minutes'] = $extra;
            $item['extra_human'] = self::formatDuration($extra);
            $item['deficit_minutes'] = $deficit;
            $item['deficit_human'] = self::formatDuration($deficit);
            $item['difference_minutes'] = $difference;
            $item['difference_human'] = $difference === 0 ? '0 min' : sprintf(
                '%s%s',
                $difference > 0 ? '+' : '-',
                self::formatDuration(abs($difference))
            );
        }
        unset($item);

        return $series;
    }

    private static function sumWeeklyDifferenceMinutes(array $series): int
    {
        $sum = 0;

        foreach ($series as $row) {
            if (!is_array($row)) {
                continue;
            }

            if (isset($row['difference_minutes'])) {
                $sum += (int) $row['difference_minutes'];
                continue;
            }

            $actual = isset($row['minutes']) ? (int) $row['minutes'] : 0;
            $expected = isset($row['expected_minutes']) ? (int) $row['expected_minutes'] : 0;
            $sum += $actual - $expected;
        }

        return $sum;
    }

    private static function formatSignedDuration(int $minutes): string
    {
        if ($minutes === 0) {
            return self::formatDuration(0);
        }

        $sign = $minutes > 0 ? '+' : '-';

        return sprintf('%s%s', $sign, self::formatDuration(abs($minutes)));
    }

    private static function createWeekStartTimestamp(int $isoYear, int $isoWeek): ?int
    {
        if ($isoYear <= 0 || $isoWeek <= 0) {
            return null;
        }

        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
            $date = new \DateTimeImmutable('now', $timezone);
            $date = $date->setISODate($isoYear, $isoWeek)->setTime(0, 0, 0);
            return $date->getTimestamp();
        } catch (\Exception $exception) {
            return null;
        }
    }

    private static function formatWeekLabel(int $isoYear, int $isoWeek, ?int $timestamp): string
    {
        $weekNumber = sprintf('%02d', max(1, min($isoWeek, 53)));

        if ($timestamp === null) {
            return sprintf(__('Semaine %1$s · %2$s', 'mj-member'), $weekNumber, (string) $isoYear);
        }

        $startDate = wp_date(get_option('date_format'), $timestamp);
        return sprintf(__('Semaine %1$s · %2$s', 'mj-member'), $weekNumber, $startDate);
    }

    private static function formatWeekShortLabel(int $isoWeek): string
    {
        $weekNumber = sprintf('%02d', max(1, min($isoWeek, 53)));
        return $weekNumber;
    }

    public static function formatDate(?string $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return '';
        }

        return date_i18n(get_option('date_format'), $timestamp);
    }

    public static function formatDateTime(?string $dateTime): string
    {
        if ($dateTime === null || $dateTime === '') {
            return '';
        }

        $timestamp = strtotime($dateTime);
        if ($timestamp === false) {
            return '';
        }

        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }

    public static function formatTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '';
        }

        $timestamp = strtotime('1970-01-01 ' . $time);
        if ($timestamp === false) {
            return '';
        }

        return date_i18n(get_option('time_format'), $timestamp);
    }

    public static function formatTimeRange(?string $start, ?string $end): string
    {
        $startFormatted = self::formatTime($start);
        $endFormatted = self::formatTime($end);

        if ($startFormatted === '' && $endFormatted === '') {
            return '';
        }

        if ($startFormatted === '') {
            return $endFormatted;
        }

        if ($endFormatted === '') {
            return $startFormatted;
        }

        return sprintf('%1$s - %2$s', $startFormatted, $endFormatted);
    }

    /**
     * @param array<int,array<string,mixed>> $memberMap
     * @param int $memberId
     * @return array<int,array<string,mixed>>
     */
    private static function prepareRecentEntries(array $memberMap, int $memberId, array $options = array()): array
    {
        $limit = isset($options['limit']) ? max(1, (int) $options['limit']) : self::RECENT_ENTRIES_LIMIT;
        $projectKey = isset($options['project']) ? (string) $options['project'] : null;

        $queryArgs = array(
            'limit' => $limit,
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        if ($memberId > 0) {
            $queryArgs['member_id'] = $memberId;
        }

        if ($projectKey !== null && $projectKey !== '') {
            $queryArgs['project'] = $projectKey;
        }

        $entries = MjMemberHours::get_all($queryArgs);

        $usersCache = array();

        return array_map(static function (array $entry) use ($memberMap, &$usersCache): array {
            $memberId = isset($entry['member_id']) ? (int) $entry['member_id'] : 0;
            $memberLabel = self::resolveMemberLabel($memberId, $memberMap);

            $duration = isset($entry['duration_minutes']) ? (int) $entry['duration_minutes'] : 0;
            $notes = isset($entry['notes']) && $entry['notes'] !== null ? (string) $entry['notes'] : '';
            $recordedBy = isset($entry['recorded_by']) ? (int) $entry['recorded_by'] : 0;
            $projectId = isset($entry['project_id']) && $entry['project_id'] !== null ? (int) $entry['project_id'] : 0;
            $startTime = isset($entry['start_time']) && $entry['start_time'] !== null ? (string) $entry['start_time'] : '';
            $endTime = isset($entry['end_time']) && $entry['end_time'] !== null ? (string) $entry['end_time'] : '';
            $startTimeDisplay = self::formatTime($startTime);
            $endTimeDisplay = self::formatTime($endTime);
            $timeRangeDisplay = self::formatTimeRange($startTime, $endTime);

            return array(
                'id' => isset($entry['id']) ? (int) $entry['id'] : 0,
                'member_id' => $memberId,
                'member_label' => $memberLabel,
                'task_label' => isset($entry['task_label']) ? (string) $entry['task_label'] : '',
                'activity_date' => isset($entry['activity_date']) ? (string) $entry['activity_date'] : '',
                'activity_date_display' => self::formatDate(isset($entry['activity_date']) ? (string) $entry['activity_date'] : ''),
                'start_time' => $startTime,
                'start_time_display' => $startTimeDisplay,
                'end_time' => $endTime,
                'end_time_display' => $endTimeDisplay,
                'time_range_display' => $timeRangeDisplay,
                'duration_minutes' => $duration,
                'duration_human' => self::formatDuration($duration),
                'notes' => $notes,
                'created_at' => isset($entry['created_at']) ? (string) $entry['created_at'] : '',
                'created_at_display' => self::formatDateTime(isset($entry['created_at']) ? (string) $entry['created_at'] : ''),
                'recorded_by' => $recordedBy,
                'recorded_by_label' => self::resolveUserLabel($recordedBy, $usersCache),
                'project_id' => $projectId,
            );
        }, $entries);
    }

    private static function schedulePresets(): array
    {
        $presets = array(
            array('start' => '08:30', 'end' => '12:00'),
            array('start' => '09:00', 'end' => '12:00'),
            array('start' => '13:00', 'end' => '15:00'),
            array('start' => '15:00', 'end' => '18:00'),
            array('start' => '18:00', 'end' => '21:00'),
        );

        return array_map(static function (array $preset): array {
            $start = $preset['start'];
            $end = $preset['end'];
            $minutes = self::calculateDurationMinutesForSchedule($start, $end);

            return array(
                'start' => $start,
                'end' => $end,
                'label' => self::formatTimeRange($start, $end),
                'duration_minutes' => $minutes,
                'duration_label' => self::formatDuration($minutes),
            );
        }, $presets);
    }

    private static function defaultDuration(array $presets): array
    {
        if (!empty($presets)) {
            $first = $presets[0];
            $minutes = isset($first['duration_minutes']) ? (int) $first['duration_minutes'] : 0;
            if ($minutes <= 0) {
                $minutes = 60;
            }

            $label = isset($first['duration_label']) ? (string) $first['duration_label'] : self::formatDuration($minutes);

            return array(
                'minutes' => $minutes,
                'label' => $label,
            );
        }

        $fallbackMinutes = 60;

        return array(
            'minutes' => $fallbackMinutes,
            'label' => self::formatDuration($fallbackMinutes),
        );
    }

    private static function prepareWeeklySummary(int $memberId, int $weeks = 6, ?string $projectKey = null): array
    {
        if ($memberId <= 0) {
            return array();
        }

        $queryArgs = array(
            'member_id' => $memberId,
            'weeks' => $weeks,
        );

        if ($projectKey !== null && $projectKey !== '') {
            $queryArgs['project'] = $projectKey;
        }

        $rows = MjMemberHours::get_weekly_totals($queryArgs);

        if (empty($rows)) {
            return array();
        }

        return array_map(static function (array $row): array {
            return self::buildWeekSummary($row);
        }, $rows);
    }

    public static function getWeeklySummaryForMember(int $memberId, int $weeks = 6): array
    {
        return self::prepareWeeklySummary($memberId, $weeks);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function buildWeekSummary(array $row): array
    {
        $weekKey = isset($row['week_key']) ? (int) $row['week_key'] : 0;
        $totalMinutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
        $range = self::resolveWeekRange($weekKey, $row['week_start'] ?? '', $row['week_end'] ?? '');

        return array(
            'week_key' => $weekKey,
            'week_number' => $range['week'],
            'week_label' => $range['label'],
            'week_start' => $range['start'],
            'week_start_display' => $range['start_display'],
            'week_end' => $range['end'],
            'week_end_display' => $range['end_display'],
            'total_minutes' => $totalMinutes,
            'duration_human' => self::formatDuration($totalMinutes),
        );
    }

    private static function resolveWeekRange(int $weekKey, string $fallbackStart = '', string $fallbackEnd = ''): array
    {
        $timezone = wp_timezone();
        $week = 0;
        $startDate = $fallbackStart;
        $endDate = $fallbackEnd;

        if ($weekKey > 0) {
            $isoYear = (int) floor($weekKey / 100);
            $isoWeek = $weekKey % 100;

            try {
                $start = (new \DateTimeImmutable('now', $timezone))->setISODate($isoYear, $isoWeek, 1);
                $end = (new \DateTimeImmutable('now', $timezone))->setISODate($isoYear, $isoWeek, 7);

                $startDate = $start->format('Y-m-d');
                $endDate = $end->format('Y-m-d');
                $week = (int) $start->format('W');
            } catch (\Exception $exception) {
                // Fallback to provided dates below.
            }
        }

        if ($week === 0 && $startDate !== '') {
            $timestamp = strtotime($startDate);
            if ($timestamp !== false) {
                $week = (int) gmdate('W', $timestamp);
            }
        }

        $startDisplay = self::formatDate($startDate);
        $endDisplay = self::formatDate($endDate);

        if ($startDisplay === '' && $startDate !== '') {
            $startDisplay = $startDate;
        }
        if ($endDisplay === '' && $endDate !== '') {
            $endDisplay = $endDate;
        }

        $label = $startDisplay !== '' && $endDisplay !== ''
            ? sprintf(__('Semaine %1$d : %2$s - %3$s', 'mj-member'), $week, $startDisplay, $endDisplay)
            : sprintf(__('Semaine %d', 'mj-member'), $week);

        return array(
            'week' => $week,
            'start' => $startDate,
            'start_display' => $startDisplay,
            'end' => $endDate,
            'end_display' => $endDisplay,
            'label' => $label,
        );
    }

    private static function calculateDurationMinutesForSchedule(string $start, string $end): int
    {
        $startTimestamp = strtotime('1970-01-01 ' . $start);
        $endTimestamp = strtotime('1970-01-01 ' . $end);

        if ($startTimestamp === false || $endTimestamp === false) {
            return 0;
        }

        $delta = $endTimestamp - $startTimestamp;
        if ($delta <= 0) {
            return 0;
        }

        return (int) round($delta / 60);
    }

    private static function calculateWeeklyContractMinutesFromSchedule(?string $workScheduleJson): int
    {
        if ($workScheduleJson === null || $workScheduleJson === '') {
            return 0;
        }

        $decoded = json_decode($workScheduleJson, true);
        if (!is_array($decoded)) {
            return 0;
        }

        $total = 0;

        foreach ($decoded as $slot) {
            if (!is_array($slot)) {
                continue;
            }

            $start = isset($slot['start']) ? (string) $slot['start'] : '';
            $end = isset($slot['end']) ? (string) $slot['end'] : '';
            if ($start === '' || $end === '') {
                continue;
            }

            $duration = self::calculateDurationMinutesForSchedule($start, $end);
            if ($duration <= 0) {
                continue;
            }

            $breakMinutesRaw = isset($slot['break_minutes']) ? (int) $slot['break_minutes'] : 0;
            $breakMinutes = max(0, min($duration, $breakMinutesRaw));
            $netDuration = max(0, $duration - $breakMinutes);

            if ($netDuration > 0) {
                $total += $netDuration;
            }
        }

        return max(0, $total);
    }

    public static function prepareCalendarMonth(int $memberId, ?string $monthKey = null): array
    {
        if ($memberId <= 0) {
            return self::emptyCalendarPayload();
        }

        $context = self::resolveCalendarContext($monthKey);
        if ($context === null) {
            return self::emptyCalendarPayload();
        }

        $rows = MjMemberHours::get_all(array(
            'member_id' => $memberId,
            'date_from' => $context['calendar_start']->format('Y-m-d'),
            'date_to' => $context['calendar_end']->format('Y-m-d'),
            'orderby' => 'activity_date',
            'order' => 'ASC',
            'limit' => 500,
        ));

        $entriesByDate = self::indexCalendarEntries($rows);

        $weeks = self::buildCalendarWeeks(
            $context['calendar_start'],
            $context['calendar_end'],
            $context['month_start'],
            $context['start_of_week'],
            $entriesByDate
        );

        $totalMinutes = 0;
        foreach ($weeks as $week) {
            $totalMinutes += isset($week['total_minutes']) ? (int) $week['total_minutes'] : 0;
        }

        return array(
            'month_key' => $context['month_key'],
            'month_label' => $context['month_label'],
            'month_start' => $context['month_start']->format('Y-m-d'),
            'month_end' => $context['month_end']->format('Y-m-d'),
            'range_start' => $context['calendar_start']->format('Y-m-d'),
            'range_end' => $context['calendar_end']->format('Y-m-d'),
            'navigation' => array(
                'previous' => $context['previous_month_key'],
                'next' => $context['next_month_key'],
            ),
            'weekdays' => self::calendarWeekdays($context['start_of_week']),
            'weeks' => $weeks,
            'total_minutes' => $totalMinutes,
            'total_human' => self::formatDuration($totalMinutes),
            'has_entries' => $totalMinutes > 0,
        );
    }

    private static function emptyCalendarPayload(): array
    {
        $startOfWeek = (int) get_option('start_of_week', 1);

        return array(
            'month_key' => '',
            'month_label' => '',
            'month_start' => '',
            'month_end' => '',
            'range_start' => '',
            'range_end' => '',
            'navigation' => array(
                'previous' => '',
                'next' => '',
            ),
            'weekdays' => self::calendarWeekdays($startOfWeek),
            'weeks' => array(),
            'total_minutes' => 0,
            'total_human' => self::formatDuration(0),
            'has_entries' => false,
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function resolveCalendarContext(?string $monthKey): ?array
    {
        $timezone = wp_timezone();
        $startOfWeek = (int) get_option('start_of_week', 1);
        $key = self::sanitizeMonthKey($monthKey);

        try {
            if ($key === '') {
                $monthStart = new \DateTimeImmutable('first day of this month', $timezone);
            } else {
                $monthStart = new \DateTimeImmutable($key . '-01 00:00:00', $timezone);
            }
        } catch (\Exception $exception) {
            return null;
        }

        $monthStart = $monthStart->setTime(0, 0, 0);
        $monthEnd = $monthStart->modify('last day of this month');

        $calendarStart = self::alignToWeekStart($monthStart, $startOfWeek);
        $calendarEnd = self::alignToWeekEnd($monthEnd, $startOfWeek);

        $previousMonthKey = $monthStart->modify('-1 month')->format('Y-m');
        $nextMonthKey = $monthStart->modify('+1 month')->format('Y-m');

        $label = date_i18n('F Y', $monthStart->getTimestamp());

        return array(
            'month_key' => $monthStart->format('Y-m'),
            'month_label' => $label,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'calendar_start' => $calendarStart,
            'calendar_end' => $calendarEnd,
            'previous_month_key' => $previousMonthKey,
            'next_month_key' => $nextMonthKey,
            'start_of_week' => $startOfWeek,
        );
    }

    private static function sanitizeMonthKey(?string $monthKey): string
    {
        if ($monthKey === null) {
            return '';
        }

        $monthKey = trim((string) $monthKey);
        if ($monthKey === '') {
            return '';
        }

        if (!preg_match('/^(\d{4})-(\d{2})$/', $monthKey, $matches)) {
            return '';
        }

        $year = (int) $matches[1];
        $month = (int) $matches[2];

        if ($year < 1970 || $year > 2100 || $month < 1 || $month > 12) {
            return '';
        }

        return sprintf('%04d-%02d', $year, $month);
    }

    private static function alignToWeekStart(\DateTimeImmutable $date, int $startOfWeek): \DateTimeImmutable
    {
        $weekday = (int) $date->format('w');
        $diff = ($weekday - $startOfWeek + 7) % 7;

        if ($diff === 0) {
            return $date;
        }

        return $date->modify(sprintf('-%d days', $diff));
    }

    private static function alignToWeekEnd(\DateTimeImmutable $date, int $startOfWeek): \DateTimeImmutable
    {
        $endOfWeek = ($startOfWeek + 6) % 7;
        $weekday = (int) $date->format('w');
        $diff = ($endOfWeek - $weekday + 7) % 7;

        if ($diff === 0) {
            return $date;
        }

        return $date->modify(sprintf('+%d days', $diff));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function indexCalendarEntries(array $rows): array
    {
        $indexed = array();

        foreach ($rows as $row) {
            $date = isset($row['activity_date']) ? (string) $row['activity_date'] : '';
            if ($date === '') {
                continue;
            }

            $start = isset($row['start_time']) && $row['start_time'] !== null ? (string) $row['start_time'] : '';
            $end = isset($row['end_time']) && $row['end_time'] !== null ? (string) $row['end_time'] : '';

            $entry = array(
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'member_id' => isset($row['member_id']) ? (int) $row['member_id'] : 0,
                'task_label' => isset($row['task_label']) ? (string) $row['task_label'] : '',
                'task_key' => isset($row['task_key']) && $row['task_key'] !== null ? (string) $row['task_key'] : '',
                'activity_date' => $date,
                'duration_minutes' => isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : 0,
                'duration_human' => self::formatDuration(isset($row['duration_minutes']) ? (int) $row['duration_minutes'] : 0),
                'start_time' => $start,
                'end_time' => $end,
                'start_time_display' => self::formatTime($start),
                'end_time_display' => self::formatTime($end),
                'time_range_display' => self::formatTimeRange($start, $end),
                'notes' => isset($row['notes']) && $row['notes'] !== null ? (string) $row['notes'] : '',
            );

            if (!isset($indexed[$date])) {
                $indexed[$date] = array();
            }

            $indexed[$date][] = $entry;
        }

        return $indexed;
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $entriesByDate
     * @return array<int,array<string,mixed>>
     */
    private static function buildCalendarWeeks(
        \DateTimeImmutable $calendarStart,
        \DateTimeImmutable $calendarEnd,
        \DateTimeImmutable $monthStart,
        int $startOfWeek,
        array $entriesByDate
    ): array {
        $weeks = array();
        $week = null;
        $timezone = wp_timezone();
        $today = (new \DateTimeImmutable('now', $timezone))->format('Y-m-d');
        $monthKey = $monthStart->format('Y-m');
        $cursor = $calendarStart;

        while ($cursor <= $calendarEnd) {
            $isoDate = $cursor->format('Y-m-d');
            $weekday = (int) $cursor->format('w');

            if ($week === null || $weekday === $startOfWeek) {
                if ($week !== null) {
                    $week['total_human'] = self::formatDuration((int) $week['total_minutes']);
                    $weeks[] = $week;
                }

                $weekStartIso = $isoDate;
                $weekEndIso = $cursor->modify('+6 days')->format('Y-m-d');
                $isoYear = (int) $cursor->format('o');
                $isoWeek = (int) $cursor->format('W');
                $weekKey = (int) sprintf('%04d%02d', $isoYear, $isoWeek);
                $range = self::resolveWeekRange($weekKey, $weekStartIso, $weekEndIso);

                $week = array(
                    'week_key' => $weekKey,
                    'week_label' => $range['label'],
                    'week_start' => $range['start'],
                    'week_start_display' => $range['start_display'],
                    'week_end' => $range['end'],
                    'week_end_display' => $range['end_display'],
                    'days' => array(),
                    'total_minutes' => 0,
                    'total_human' => self::formatDuration(0),
                );
            }

            $entries = $entriesByDate[$isoDate] ?? array();
            $dayTotal = 0;
            foreach ($entries as $entry) {
                $dayTotal += isset($entry['duration_minutes']) ? (int) $entry['duration_minutes'] : 0;
            }

            $day = array(
                'date' => $isoDate,
                'day_number' => (int) $cursor->format('j'),
                'day_label' => $cursor->format('j'),
                'weekday_index' => $weekday,
                'is_current_month' => $cursor->format('Y-m') === $monthKey,
                'is_today' => $isoDate === $today,
                'entries' => $entries,
                'total_minutes' => $dayTotal,
                'total_human' => self::formatDuration($dayTotal),
                'has_entries' => !empty($entries),
            );

            $week['days'][] = $day;
            $week['total_minutes'] += $dayTotal;

            $cursor = $cursor->modify('+1 day');
        }

        if ($week !== null) {
            $week['total_human'] = self::formatDuration((int) $week['total_minutes']);
            $weeks[] = $week;
        }

        return $weeks;
    }

    private static function calendarWeekdays(int $startOfWeek): array
    {
        $timezone = wp_timezone();
        $base = new \DateTimeImmutable('2023-01-01', $timezone);
        $labels = array();

        for ($offset = 0; $offset < 7; $offset++) {
            $weekdayIndex = ($startOfWeek + $offset) % 7;
            $date = $base->modify(sprintf('+%d days', $weekdayIndex));
            $labels[] = array(
                'weekday_index' => $weekdayIndex,
                'label' => date_i18n('l', $date->getTimestamp()),
                'short_label' => date_i18n('D', $date->getTimestamp()),
            );
        }

        return $labels;
    }

    public static function formatMemberLabel(MemberData $member): string
    {
        $id = (int) ($member->id ?? 0);
        $first = trim((string) ($member->first_name ?? ''));
        $last = trim((string) ($member->last_name ?? ''));
        $label = trim($first . ' ' . $last);

        if ($label === '') {
            $label = sprintf(__('Membre #%d', 'mj-member'), $id);
        }

        return $label;
    }

    private static function resolveMemberLabel(int $memberId, array $memberMap): string
    {
        if (isset($memberMap[$memberId]['label'])) {
            return (string) $memberMap[$memberId]['label'];
        }

        if ($memberId > 0) {
            return sprintf(__('Membre #%d', 'mj-member'), $memberId);
        }

        return __('Membre inconnu', 'mj-member');
    }

    public static function canManageOtherMembers(): bool
    {
        return current_user_can('manage_options') || current_user_can(Config::capability());
    }

    public static function sanitizeProjectKeyRequest(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (strcasecmp($value, self::PROJECT_EMPTY_KEY) === 0) {
            return self::PROJECT_EMPTY_KEY;
        }

        return sanitize_text_field($value);
    }

    public static function getAdminState(int $currentMemberId, string $currentMemberLabel, bool $canManageOthers, array $args = array()): array
    {
        return self::buildAdminState($currentMemberId, $currentMemberLabel, $canManageOthers, $args);
    }

    private static function buildAdminState(int $currentMemberId, string $currentMemberLabel, bool $canManageOthers, array $args): array
    {
        $requestedMemberId = isset($args['member_id']) ? (int) $args['member_id'] : $currentMemberId;
        $requestedProjectKey = isset($args['project_key']) ? (string) $args['project_key'] : '';
        $requestedMonth = isset($args['calendar_month']) ? (string) $args['calendar_month'] : null;
        $includeCalendar = !empty($args['include_calendar']);

        $memberOptions = self::buildMemberOptions($currentMemberId, $currentMemberLabel, $canManageOthers);
        $memberMap = self::buildMemberMap($memberOptions);
        $selectedMemberId = self::determineSelectedMemberId($memberOptions, $currentMemberId, $canManageOthers, $requestedMemberId);

        $projectWithoutLabel = __('Sans projet', 'mj-member');
        $projectTotalsArgs = array();
        if ($selectedMemberId > 0) {
            $projectTotalsArgs['member_id'] = $selectedMemberId;
        }
        $projectTotals = MjMemberHours::get_project_totals($projectTotalsArgs);
        $projectOptions = self::buildProjectOptions($projectTotals, $projectWithoutLabel);
        $selectedProjectKey = self::determineSelectedProjectKey($projectOptions, $requestedProjectKey);

        $recentEntries = self::prepareRecentEntries(
            $memberMap,
            $selectedMemberId > 0 ? $selectedMemberId : 0,
            array(
                'limit' => self::RECENT_ENTRIES_LIMIT,
                'project' => $selectedProjectKey !== '' ? $selectedProjectKey : null,
            )
        );

        $projectSummary = self::buildProjectSummary($projectTotals, $projectWithoutLabel);

        $weeklySummary = array();
        if ($selectedMemberId > 0) {
            $weeklySummary = self::prepareWeeklySummary(
                $selectedMemberId,
                8,
                $selectedProjectKey !== '' ? $selectedProjectKey : null
            );
        }

        $memberLabel = $selectedMemberId === 0
            ? __('Tous les membres', 'mj-member')
            : self::resolveMemberLabel($selectedMemberId, $memberMap);

        $calendar = self::emptyCalendarPayload();
        if ($includeCalendar && $selectedMemberId > 0) {
            $calendar = self::prepareCalendarMonth($selectedMemberId, $requestedMonth);
        }

        return array(
            'selected_member_id' => $selectedMemberId,
            'selected_project_key' => $selectedProjectKey,
            'member_label' => $memberLabel,
            'member_options' => array_values($memberOptions),
            'project_options' => $projectOptions,
            'project_summary' => $projectSummary,
            'project_without_label' => $projectWithoutLabel,
            'recent_entries' => $recentEntries,
            'weekly_summary' => $weeklySummary,
            'calendar' => $calendar,
            'has_calendar' => $selectedMemberId > 0,
        );
    }

    private static function buildMemberOptions(int $currentMemberId, string $currentMemberLabel, bool $canManageOthers): array
    {
        $totals = MjMemberHours::get_member_totals();

        $memberIds = array_map(static function (array $row): int {
            return isset($row['member_id']) ? (int) $row['member_id'] : 0;
        }, $totals);

        if ($currentMemberId > 0 && !in_array($currentMemberId, $memberIds, true)) {
            $memberIds[] = $currentMemberId;
        }

        $labels = self::fetchMemberLabels($memberIds);

        $options = array();
        $totalMinutesAll = 0;
        $entriesAll = 0;

        foreach ($totals as $row) {
            $memberId = isset($row['member_id']) ? (int) $row['member_id'] : 0;
            if ($memberId <= 0) {
                continue;
            }

            $label = $labels[$memberId]['label'] ?? sprintf(__('Membre #%d', 'mj-member'), $memberId);
            $totalMinutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;

            $totalMinutesAll += $totalMinutes;
            $entriesAll += $entries;

            $options[] = array(
                'id' => $memberId,
                'label' => $label,
                'total_minutes' => $totalMinutes,
                'total_human' => self::formatDuration($totalMinutes),
                'entries' => $entries,
            );
        }

        $hasCurrent = false;
        foreach ($options as $option) {
            if ((int) $option['id'] === $currentMemberId) {
                $hasCurrent = true;
                break;
            }
        }

        if (!$hasCurrent && $currentMemberId > 0) {
            $options[] = array(
                'id' => $currentMemberId,
                'label' => $currentMemberLabel,
                'total_minutes' => 0,
                'total_human' => self::formatDuration(0),
                'entries' => 0,
            );
        }

        usort($options, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        if ($canManageOthers) {
            array_unshift($options, array(
                'id' => 0,
                'label' => __('Tous les membres', 'mj-member'),
                'total_minutes' => $totalMinutesAll,
                'total_human' => self::formatDuration($totalMinutesAll),
                'entries' => $entriesAll,
            ));
        }

        return $options;
    }

    private static function fetchMemberLabels(array $memberIds): array
    {
        $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), static function ($value) {
            return $value > 0;
        })));

        if (empty($memberIds)) {
            return array();
        }

        global $wpdb;
        $table = MjMembers::getTableName(MjMembers::TABLE_NAME);
        $placeholders = implode(',', array_fill(0, count($memberIds), '%d'));
        $sql = "SELECT id, first_name, last_name, work_schedule FROM {$table} WHERE id IN ({$placeholders})";
        $prepared = call_user_func_array(array($wpdb, 'prepare'), array_merge(array($sql), $memberIds));
        $rows = $wpdb->get_results($prepared);

        $labels = array();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $id = isset($row->id) ? (int) $row->id : 0;
                if ($id <= 0) {
                    continue;
                }

                $first = isset($row->first_name) ? trim((string) $row->first_name) : '';
                $last = isset($row->last_name) ? trim((string) $row->last_name) : '';
                $label = trim($first . ' ' . $last);
                if ($label === '') {
                    $label = sprintf(__('Membre #%d', 'mj-member'), $id);
                }

                $workSchedule = isset($row->work_schedule) ? (string) $row->work_schedule : '';
                $weeklyContractMinutes = self::calculateWeeklyContractMinutesFromSchedule($workSchedule);

                $labels[$id] = array(
                    'label' => $label,
                    'weekly_contract_minutes' => $weeklyContractMinutes,
                    'weekly_contract_human' => self::formatDuration($weeklyContractMinutes),
                );
            }
        }

        return $labels;
    }

    private static function buildMemberMap(array $memberOptions): array
    {
        $map = array();
        foreach ($memberOptions as $option) {
            $memberId = isset($option['id']) ? (int) $option['id'] : 0;
            if ($memberId <= 0) {
                continue;
            }
            $map[$memberId] = array('label' => (string) ($option['label'] ?? ''));
        }

        return $map;
    }

    private static function determineSelectedMemberId(array $memberOptions, int $currentMemberId, bool $canManageOthers, ?int $requested): int
    {
        if (!$canManageOthers) {
            return $currentMemberId;
        }

        $availableIds = array_map(static function (array $option): int {
            return isset($option['id']) ? (int) $option['id'] : -1;
        }, $memberOptions);

        $requestedId = is_int($requested) ? $requested : $currentMemberId;

        if ($requestedId === 0 && in_array(0, $availableIds, true)) {
            return 0;
        }

        if ($requestedId > 0 && in_array($requestedId, $availableIds, true)) {
            return $requestedId;
        }

        return $currentMemberId;
    }

    private static function determineSelectedProjectKey(array $projectOptions, ?string $requested): string
    {
        if (!is_string($requested) || $requested === '') {
            return '';
        }

        foreach ($projectOptions as $option) {
            if (!is_array($option)) {
                continue;
            }
            $key = isset($option['key']) ? (string) $option['key'] : '';
            if ($key === $requested) {
                return $key;
            }
        }

        return '';
    }

    private static function buildProjectOptions(array $projectTotals, string $projectWithoutLabel): array
    {
        $options = array();
        $totalMinutesAll = 0;
        $entriesAll = 0;

        foreach ($projectTotals as $row) {
            $label = isset($row['project_label']) ? (string) $row['project_label'] : '';
            $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $projectColor = isset($row['project_color']) ? sanitize_hex_color((string) $row['project_color']) : '';
            if (!is_string($projectColor)) {
                $projectColor = '';
            }
            $totalMinutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;
            $entries = isset($row['entries']) ? (int) $row['entries'] : 0;

            $totalMinutesAll += $totalMinutes;
            $entriesAll += $entries;

            $isEmpty = $label === '';
            $options[] = array(
                'key' => $isEmpty ? self::PROJECT_EMPTY_KEY : $label,
                'label' => $isEmpty ? $projectWithoutLabel : $label,
                'project_id' => $projectId,
                'color' => $projectColor,
                'total_minutes' => $totalMinutes,
                'total_human' => self::formatDuration($totalMinutes),
                'entries' => $entries,
            );
        }

        usort($options, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        array_unshift($options, array(
            'key' => '',
            'label' => __('Tous les projets', 'mj-member'),
            'project_id' => 0,
            'color' => '',
            'total_minutes' => $totalMinutesAll,
            'total_human' => self::formatDuration($totalMinutesAll),
            'entries' => $entriesAll,
        ));

        return $options;
    }

    private static function buildProjectSummary(array $projectTotals, string $projectWithoutLabel): array
    {
        return array_map(static function (array $row) use ($projectWithoutLabel): array {
            $label = isset($row['project_label']) ? (string) $row['project_label'] : '';
            $projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
            $projectColor = isset($row['project_color']) ? sanitize_hex_color((string) $row['project_color']) : '';
            if (!is_string($projectColor)) {
                $projectColor = '';
            }
            $isEmpty = $label === '';
            $totalMinutes = isset($row['total_minutes']) ? (int) $row['total_minutes'] : 0;

            return array(
                'key' => $isEmpty ? self::PROJECT_EMPTY_KEY : $label,
                'label' => $isEmpty ? $projectWithoutLabel : $label,
                'project_id' => $projectId,
                'color' => $projectColor,
                'total_minutes' => $totalMinutes,
                'total_human' => self::formatDuration($totalMinutes),
                'entries' => isset($row['entries']) ? (int) $row['entries'] : 0,
            );
        }, $projectTotals);
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<int,array<string,mixed>>
     */
    public static function prepareRecentEntriesPayload(array $entries): array
    {
        return array_map(static function (array $entry): array {
            return array(
                'id' => isset($entry['id']) ? (int) $entry['id'] : 0,
                'member_id' => isset($entry['member_id']) ? (int) $entry['member_id'] : 0,
                'member_label' => isset($entry['member_label']) ? (string) $entry['member_label'] : '',
                'task_label' => isset($entry['task_label']) ? (string) $entry['task_label'] : '',
                'activity_date' => isset($entry['activity_date']) ? (string) $entry['activity_date'] : '',
                'activity_date_display' => isset($entry['activity_date_display']) ? (string) $entry['activity_date_display'] : '',
                'start_time' => isset($entry['start_time']) ? (string) $entry['start_time'] : '',
                'start_time_display' => isset($entry['start_time_display']) ? (string) $entry['start_time_display'] : '',
                'end_time' => isset($entry['end_time']) ? (string) $entry['end_time'] : '',
                'end_time_display' => isset($entry['end_time_display']) ? (string) $entry['end_time_display'] : '',
                'time_range_display' => isset($entry['time_range_display']) ? (string) $entry['time_range_display'] : '',
                'duration_minutes' => isset($entry['duration_minutes']) ? (int) $entry['duration_minutes'] : 0,
                'duration_human' => isset($entry['duration_human']) ? (string) $entry['duration_human'] : '',
                'notes' => isset($entry['notes']) ? (string) $entry['notes'] : '',
                'created_at' => isset($entry['created_at']) ? (string) $entry['created_at'] : '',
                'created_at_display' => isset($entry['created_at_display']) ? (string) $entry['created_at_display'] : '',
                'recorded_by' => isset($entry['recorded_by']) ? (int) $entry['recorded_by'] : 0,
                'recorded_by_label' => isset($entry['recorded_by_label']) ? (string) $entry['recorded_by_label'] : '',
                'project_id' => isset($entry['project_id']) ? (int) $entry['project_id'] : 0,
            );
        }, $entries);
    }

    /**
     * @param array<int,WP_User> $cache
     */
    private static function resolveUserLabel(int $userId, array &$cache): string
    {
        if ($userId <= 0) {
            return '';
        }

        if (isset($cache[$userId])) {
            return $cache[$userId]->display_name;
        }

        $user = get_user_by('id', $userId);
        if ($user instanceof WP_User) {
            $cache[$userId] = $user;
            return $user->display_name;
        }

        return '';
    }

    /**
     * @return string[]
     */
    private static function taskSuggestions(): array
    {
        return array(
            'Accueil libre - ouverture du lieu',
            'Clôture et rangement de la MJ',
            'Animation atelier théâtre',
            'Animation atelier danse hip-hop',
            'Animation atelier musique assistée par ordinateur',
            'Animation atelier percussions afro',
            'Animation atelier chant collectif',
            'Animation atelier d’écriture slam',
            'Animation atelier photo/vidéo',
            'Animation atelier podcast',
            'Animation atelier graffiti',
            'Animation atelier couture',
            'Animation atelier cuisine du monde',
            'Animation atelier pâtisserie',
            'Animation atelier développement web',
            'Animation atelier robotique',
            'Animation atelier jeux de société',
            'Animation atelier jeux de rôle',
            'Animation atelier e-sport',
            'Animation atelier animation 2D',
            'Animation atelier stop-motion',
            'Animation atelier jardinage urbain',
            'Animation atelier repair café',
            'Animation atelier recyclage créatif',
            'Animation atelier bien-être',
            'Animation atelier yoga jeunesse',
            'Animation atelier boxe éducative',
            'Animation atelier self-défense',
            'Animation atelier escalade',
            'Animation atelier skate',
            'Animation atelier roller',
            'Animation atelier vélo BMX',
            'Animation atelier basket',
            'Animation atelier futsal',
            'Animation atelier ping-pong',
            'Animation atelier badminton',
            'Animation atelier parkour',
            'Animation atelier slackline',
            'Animation atelier écriture de CV',
            'Animation atelier recherche d’emploi',
            'Animation atelier gestion de budget',
            'Animation atelier citoyenneté',
            'Animation atelier débat',
            'Animation atelier médias et fake news',
            'Animation atelier sensibilisation environnement',
            'Animation atelier zéro déchet',
            'Animation atelier couture upcycling',
            'Animation atelier customisation textile',
            'Organisation sortie culturelle',
            'Organisation sortie sportive',
            'Organisation résidence artistique',
            'Accompagnement scolaire',
            'Accompagnement projets jeunes',
            'Coaching individuel',
            'Préparation réunion jeunes',
            'Animation conseil de jeunes',
            'Préparation dossier subvention',
            'Suivi administratif bénévoles',
            'Planification équipes animateurs',
            'Gestion prêt matériel',
            'Maintenance équipement son',
            'Maintenance parc informatique',
            'Installation salle concert',
            'Régie lumière spectacle',
            'Régie son répétition',
            'Accueil public événement',
            'Billetterie événement',
            'Sécurité événement',
            'Gestion vestiaire événement',
            'Communication réseaux sociaux',
            'Rédaction newsletter jeunes',
            'Création affiche événement',
            'Création visuels web',
            'Tournage vidéo promotionnelle',
            'Montage vidéo aftermovie',
            'Photographie événement',
            'Publication agenda mensuel',
            'Mise à jour site web',
            'Animation live Twitch',
            'Animation serveur Discord',
            'Animation concours créatif',
            'Coordination ateliers hebdo',
            'Gestion planning salles',
            'Réunion coordination MJ',
            'Réunion partenaires sociaux',
            'Réunion réseau MJ',
            'Visite établissements scolaires',
            'Diffusion flyers quartier',
            'Accueil parents',
            'Médiation familles',
            'Rédaction rapport activité',
            'Archivage documents',
            'Nettoyage salle activités',
            'Désinfection matériel partagé',
            'Inventaire matériel',
            'Commande fournitures atelier',
            'Préparation collation',
            'Gestion frigo solidaire',
            'Atelier cuisine healthy',
            'Atelier mixologie sans alcool',
        );
    }
}
