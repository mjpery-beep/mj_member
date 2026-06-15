<?php

namespace Mj\Member\Module {
    use Mj\Member\Core\Config;
    use Mj\Member\Core\Contracts\ModuleInterface;

    if (!defined('ABSPATH')) {
        exit;
    }

    final class GitHubUpdaterModule implements ModuleInterface
    {
        private const CACHE_KEY = 'mj_member_github_release';
        private const CACHE_TTL = 21600;

        public function register(): void
        {
            \add_filter('pre_set_site_transient_update_plugins', array($this, 'injectUpdate'));
            \add_filter('plugins_api', array($this, 'filterPluginInfo'), 20, 3);
            \add_filter('upgrader_source_selection', array($this, 'normalizeInstallSource'), 10, 4);
            \add_action('upgrader_process_complete', array($this, 'purgeCache'), 10, 2);
        }

        public function injectUpdate($transient)
        {
            if (!is_object($transient) || empty($transient->checked) || !is_array($transient->checked)) {
                return $transient;
            }

            $pluginFile = $this->pluginBasename();
            $currentVersion = $this->pluginData()['Version'] ?: Config::version();
            $release = $this->getLatestRelease();

            if ($release === null) {
                return $transient;
            }

            if (\version_compare($release['version'], $currentVersion, '<=')) {
                if (!isset($transient->no_update) || !is_array($transient->no_update)) {
                    $transient->no_update = array();
                }

                $transient->no_update[$pluginFile] = $this->buildUpdatePayload($release, $currentVersion);
                unset($transient->response[$pluginFile]);

                return $transient;
            }

            if (!isset($transient->response) || !is_array($transient->response)) {
                $transient->response = array();
            }

            $transient->response[$pluginFile] = $this->buildUpdatePayload($release, $currentVersion);
            unset($transient->no_update[$pluginFile]);

            return $transient;
        }

        public function filterPluginInfo($result, string $action, $args)
        {
            if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== $this->pluginSlug()) {
                return $result;
            }

            $pluginData = $this->pluginData();
            $release = $this->getLatestRelease();

            if ($release === null) {
                return $result;
            }

            $info = new \stdClass();
            $info->name = $pluginData['Name'] ?: 'MJ Member';
            $info->slug = $this->pluginSlug();
            $info->version = $release['version'];
            $info->author = $pluginData['Author'] ?: 'Simon';
            $info->author_profile = $pluginData['AuthorURI'] ?: '';
            $info->homepage = $release['url'];
            $info->requires = $pluginData['RequiresAtLeast'] ?: '';
            $info->tested = $pluginData['TestedUpTo'] ?: '';
            $info->requires_php = $pluginData['RequiresPHP'] ?: '';
            $info->last_updated = $release['published_at'];
            $info->download_link = $release['package'];
            $info->sections = array(
                'description' => $pluginData['Description'] ?: 'Plugin MJ Member.',
                'changelog' => $this->formatReleaseNotes($release),
            );

            return $info;
        }

        public function normalizeInstallSource(string $source, string $remoteSource, $upgrader, array $hookExtra)
        {
            if (($hookExtra['plugin'] ?? '') !== $this->pluginBasename()) {
                return $source;
            }

            $expectedDirectory = $this->pluginSlug();
            if (\basename(\wp_normalize_path($source)) === $expectedDirectory) {
                return $source;
            }

            global $wp_filesystem;
            if (!$wp_filesystem) {
                return $source;
            }

            $normalizedRemoteSource = \trailingslashit($remoteSource);
            $destination = $normalizedRemoteSource . $expectedDirectory;

            if ($wp_filesystem->exists($destination)) {
                $wp_filesystem->delete($destination, true);
            }

            if (!$wp_filesystem->move($source, $destination, true)) {
                return new \WP_Error(
                    'mj_member_github_update_move_failed',
                    __('Impossible de preparer le dossier de mise a jour GitHub pour MJ Member.', 'mj-member')
                );
            }

            return $destination;
        }

        public function purgeCache($upgrader, array $hookExtra): void
        {
            if (($hookExtra['action'] ?? '') !== 'update' || ($hookExtra['type'] ?? '') !== 'plugin') {
                return;
            }

            $plugins = $hookExtra['plugins'] ?? array();
            if (!is_array($plugins) || !in_array($this->pluginBasename(), $plugins, true)) {
                return;
            }

            \delete_site_transient(self::CACHE_KEY);
            \delete_site_transient('update_plugins');
        }

        private function buildUpdatePayload(array $release, string $currentVersion): \stdClass
        {
            $pluginData = $this->pluginData();
            $payload = new \stdClass();
            $payload->id = $release['url'];
            $payload->slug = $this->pluginSlug();
            $payload->plugin = $this->pluginBasename();
            $payload->new_version = $release['version'];
            $payload->url = $release['url'];
            $payload->package = $release['package'];
            $payload->tested = $pluginData['TestedUpTo'] ?: '';
            $payload->requires = $pluginData['RequiresAtLeast'] ?: '';
            $payload->requires_php = $pluginData['RequiresPHP'] ?: '';
            $payload->icons = array();
            $payload->banners = array();
            $payload->banners_rtl = array();
            $payload->compatibility = new \stdClass();
            $payload->version = $currentVersion;

            return $payload;
        }

        private function getLatestRelease(): ?array
        {
            $cached = \get_site_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }

            $repository = $this->repositoryFromPluginUri();
            if ($repository === null) {
                return null;
            }

            $release = $this->requestLatestRelease($repository);
            if ($release === null) {
                $release = $this->requestLatestTag($repository);
            }
            if ($release === null) {
                $release = $this->requestBranchSnapshot($repository);
            }

            if ($release !== null) {
                \set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
            }

            return $release;
        }

        private function requestLatestRelease(string $repository): ?array
        {
            $response = $this->requestGitHubJson('https://api.github.com/repos/' . $repository . '/releases/latest');
            if (!is_array($response)) {
                return null;
            }

            $tag = isset($response['tag_name']) && is_string($response['tag_name']) ? $response['tag_name'] : '';
            if ($tag === '') {
                return null;
            }

            $package = isset($response['zipball_url']) && is_string($response['zipball_url']) ? $response['zipball_url'] : '';
            if ($package === '') {
                return null;
            }

            return array(
                'version' => $this->normalizeVersion($tag),
                'package' => $package,
                'url' => isset($response['html_url']) && is_string($response['html_url']) ? $response['html_url'] : $this->pluginData()['PluginURI'],
                'body' => isset($response['body']) && is_string($response['body']) ? $response['body'] : '',
                'published_at' => isset($response['published_at']) && is_string($response['published_at']) ? $response['published_at'] : '',
                'source' => 'release',
            );
        }

        private function requestLatestTag(string $repository): ?array
        {
            $response = $this->requestGitHubJson('https://api.github.com/repos/' . $repository . '/tags?per_page=1');
            if (!is_array($response) || !isset($response[0]) || !is_array($response[0])) {
                return null;
            }

            $tag = isset($response[0]['name']) && is_string($response[0]['name']) ? $response[0]['name'] : '';
            if ($tag === '') {
                return null;
            }

            $package = isset($response[0]['zipball_url']) && is_string($response[0]['zipball_url']) ? $response[0]['zipball_url'] : '';
            if ($package === '') {
                return null;
            }

            return array(
                'version' => $this->normalizeVersion($tag),
                'package' => $package,
                'url' => rtrim((string) $this->pluginData()['PluginURI'], '/') . '/tree/' . rawurlencode($tag),
                'body' => '',
                'published_at' => '',
                'source' => 'tag',
            );
        }

        private function requestBranchSnapshot(string $repository): ?array
        {
            foreach ($this->branchCandidates() as $branch) {
                $version = $this->requestBranchVersion($repository, $branch);
                if ($version === null) {
                    continue;
                }

                return array(
                    'version' => $version,
                    'package' => 'https://codeload.github.com/' . $repository . '/zip/refs/heads/' . rawurlencode($branch),
                    'url' => rtrim((string) $this->pluginData()['PluginURI'], '/') . '/tree/' . rawurlencode($branch),
                    'body' => sprintf(
                        __('Mise a jour basee sur la branche GitHub %s.', 'mj-member'),
                        esc_html($branch)
                    ),
                    'published_at' => '',
                    'source' => 'branch',
                );
            }

            return null;
        }

        private function requestBranchVersion(string $repository, string $branch): ?string
        {
            $url = sprintf(
                'https://raw.githubusercontent.com/%s/%s/%s',
                $repository,
                rawurlencode($branch),
                rawurlencode(basename(Config::mainFile()))
            );

            $response = \wp_remote_get(
                $url,
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'User-Agent' => 'MJ Member Updater/' . Config::version(),
                    ),
                )
            );

            if (\is_wp_error($response)) {
                return null;
            }

            $statusCode = (int) \wp_remote_retrieve_response_code($response);
            if ($statusCode < 200 || $statusCode >= 300) {
                return null;
            }

            $body = \wp_remote_retrieve_body($response);
            if (!is_string($body) || $body === '') {
                return null;
            }

            if (!preg_match('/^\s*Version:\s*(.+)$/mi', $body, $matches)) {
                return null;
            }

            return trim((string) $matches[1]);
        }

        private function requestGitHubJson(string $url): ?array
        {
            $response = \wp_remote_get(
                $url,
                array(
                    'timeout' => 15,
                    'headers' => array(
                        'Accept' => 'application/vnd.github+json',
                        'User-Agent' => 'MJ Member Updater/' . Config::version(),
                    ),
                )
            );

            if (\is_wp_error($response)) {
                return null;
            }

            $statusCode = (int) \wp_remote_retrieve_response_code($response);
            if ($statusCode < 200 || $statusCode >= 300) {
                return null;
            }

            $body = \wp_remote_retrieve_body($response);
            if (!is_string($body) || $body === '') {
                return null;
            }

            $decoded = \json_decode($body, true);

            return is_array($decoded) ? $decoded : null;
        }

        private function repositoryFromPluginUri(): ?string
        {
            if (defined('MJ_MEMBER_GITHUB_REPOSITORY')) {
                $repository = trim((string) constant('MJ_MEMBER_GITHUB_REPOSITORY'));
                if ($repository !== '') {
                    return trim($repository, '/');
                }
            }

            $pluginUri = (string) $this->pluginData()['PluginURI'];
            if ($pluginUri === '') {
                return null;
            }

            $parts = \wp_parse_url($pluginUri);
            if (!is_array($parts) || ($parts['host'] ?? '') !== 'github.com') {
                return null;
            }

            $path = trim((string) ($parts['path'] ?? ''), '/');
            if ($path === '') {
                return null;
            }

            $segments = explode('/', $path);
            if (count($segments) < 2) {
                return null;
            }

            return $segments[0] . '/' . $segments[1];
        }

        private function pluginData(): array
        {
            static $pluginData = null;

            if (is_array($pluginData)) {
                return $pluginData;
            }

            $pluginData = \get_file_data(
                Config::mainFile(),
                array(
                    'Name' => 'Plugin Name',
                    'Version' => 'Version',
                    'Description' => 'Description',
                    'Author' => 'Author',
                    'AuthorURI' => 'Author URI',
                    'PluginURI' => 'Plugin URI',
                    'RequiresAtLeast' => 'Requires at least',
                    'TestedUpTo' => 'Tested up to',
                    'RequiresPHP' => 'Requires PHP',
                ),
                'plugin'
            );

            return is_array($pluginData) ? $pluginData : array();
        }

        private function pluginBasename(): string
        {
            return \plugin_basename(Config::mainFile());
        }

        private function pluginSlug(): string
        {
            return \basename(\dirname(Config::mainFile()));
        }

        private function normalizeVersion(string $version): string
        {
            return ltrim(trim($version), "vV \t\n\r\0\x0B");
        }

        private function branchCandidates(): array
        {
            if (defined('MJ_MEMBER_GITHUB_BRANCH')) {
                $branch = trim((string) constant('MJ_MEMBER_GITHUB_BRANCH'));
                if ($branch !== '') {
                    return array($branch);
                }
            }

            return array('main', 'master');
        }

        private function formatReleaseNotes(array $release): string
        {
            $notes = trim((string) ($release['body'] ?? ''));
            if ($notes === '') {
                return __('Aucune note de version disponible sur GitHub pour cette mise a jour.', 'mj-member');
            }

            return nl2br(esc_html($notes));
        }
    }
}