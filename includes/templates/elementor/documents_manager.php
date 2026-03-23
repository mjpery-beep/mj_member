<?php

use Mj\Member\Core\AssetsManager;
use Mj\Member\Core\Config;

if (!defined('ABSPATH')) {
    exit;
}

$title = isset($title) ? (string) $title : '';
$intro = isset($intro) ? (string) $intro : '';
$defaultFolderId = isset($defaultFolderId) ? (string) $defaultFolderId : '';

$backend = function_exists('mj_member_documents_backend') ? mj_member_documents_backend() : '';
$isNextcloudIframe = ($backend === 'nextcloud');

if ($isNextcloudIframe) {
    // JS file manager not needed, but still load CSS (status bar uses it)
    wp_enqueue_style('mj-member-components');
    wp_enqueue_style('mj-member-documents-manager');
} else {
    AssetsManager::requirePackage('documents-manager');
}

$isPreview = function_exists('is_elementor_preview') && is_elementor_preview();
$hasAccess = $isPreview;
if (!$hasAccess && function_exists('mj_member_documents_user_has_access')) {
    $hasAccess = mj_member_documents_user_has_access();
}

$isConfigured = function_exists('mj_member_documents_is_configured') ? mj_member_documents_is_configured() : false;

$introHtml = $intro !== '' ? wp_kses_post($intro) : '';

$nextcloudIframeUrl = '';
$nextcloudIframeAutoUrl = '';
$nextcloudAutoLoginKey = '';
$nextcloudAutoLoginUser = '';
$nextcloudAutoLoginPassword = '';
$nextcloudLoginPostUrl = '';
$nextcloudSessionCheckUrl = '';
if ($isNextcloudIframe) {
    $nextcloudBaseUrl = Config::nextcloudUrl();
    if ($nextcloudBaseUrl !== '') {
        $nextcloudIframeUrl = trailingslashit($nextcloudBaseUrl) . 'apps/files/';
        $nextcloudLoginPostUrl = trailingslashit($nextcloudBaseUrl) . 'login';
        $nextcloudSessionCheckUrl = trailingslashit($nextcloudBaseUrl) . 'apps/mj_session_check/session';

        $nextcloudRootFolder = trim((string) Config::nextcloudRootFolder(), '/');
        if ($nextcloudRootFolder !== '') {
            $nextcloudIframeUrl = add_query_arg(
                array('dir' => '/' . $nextcloudRootFolder),
                $nextcloudIframeUrl
            );
        }

        // First display: best-effort auto-login with saved member credentials.
        if (function_exists('mj_member_documents_get_current_member_nextcloud_credentials')) {
            $memberCreds = mj_member_documents_get_current_member_nextcloud_credentials();
            $memberLogin = isset($memberCreds['login']) ? sanitize_user((string) $memberCreds['login'], true) : '';
            $memberPassword = isset($memberCreds['password']) ? trim((string) $memberCreds['password']) : '';

            if ($memberLogin !== '' && $memberPassword !== '') {
                $nextcloudAutoLoginUser = $memberLogin;
                $nextcloudAutoLoginPassword = $memberPassword;
                $parts = wp_parse_url($nextcloudIframeUrl);
                if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
                    $authority = rawurlencode($memberLogin) . ':' . rawurlencode($memberPassword) . '@' . $parts['host'];
                    if (!empty($parts['port'])) {
                        $authority .= ':' . (int) $parts['port'];
                    }

                    $path = isset($parts['path']) ? (string) $parts['path'] : '/';
                    $query = isset($parts['query']) && $parts['query'] !== '' ? ('?' . (string) $parts['query']) : '';
                    $fragment = isset($parts['fragment']) && $parts['fragment'] !== '' ? ('#' . (string) $parts['fragment']) : '';

                    $nextcloudIframeAutoUrl = $parts['scheme'] . '://' . $authority . $path . $query . $fragment;
                    $nextcloudAutoLoginKey = $memberLogin;
                }
            }
        }
    }
}

$sampleFolder = array(
    'folder' => array(
        'id' => 'preview-root',
        'name' => __('Documents MJ', 'mj-member'),
        'type' => 'folder',
        'modifiedTime' => current_time('mysql'),
    ),
    'breadcrumbs' => array(
        array(
            'id' => 'preview-root',
            'name' => __('Documents MJ', 'mj-member'),
        ),
    ),
    'items' => array(
        array(
            'id' => 'preview-folder-1',
            'name' => __('Comptes rendus', 'mj-member'),
            'type' => 'folder',
            'mimeType' => 'httpd/unix-directory',
            'modifiedTime' => current_time('mysql'),
            'size' => 0,
            'webViewLink' => '',
            'iconLink' => '',
            'parents' => array('preview-root'),
        ),
        array(
            'id' => 'preview-file-1',
            'name' => __('Planning ateliers.pdf', 'mj-member'),
            'type' => 'file',
            'mimeType' => 'application/pdf',
            'modifiedTime' => current_time('mysql'),
            'size' => 1024 * 256,
            'webViewLink' => '#',
            'iconLink' => '',
            'parents' => array('preview-root'),
        ),
        array(
            'id' => 'preview-file-2',
            'name' => __('Budget 2026.xlsx', 'mj-member'),
            'type' => 'file',
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'modifiedTime' => current_time('mysql'),
            'size' => 1024 * 48,
            'webViewLink' => '#',
            'iconLink' => '',
            'parents' => array('preview-root'),
        ),
    ),
);

$config = array(
    'title' => $title,
    'intro' => $introHtml,
    'defaultFolderId' => $defaultFolderId,
    // Deprecated per-widget toggles: actions are now governed by backend/capabilities.
    'allowUpload' => true,
    'allowCreateFolder' => true,
    'allowRename' => true,
    'hasAccess' => $hasAccess,
    'isConfigured' => $isConfigured,
    'preview' => $isPreview,
    'previewData' => $isPreview ? $sampleFolder : array(),
);

// Determine root folder per active backend
if ($backend === 'nextcloud') {
    $defaultRoot = Config::nextcloudRootFolder();
} else {
    $defaultRoot = Config::googleDriveRootFolderId();
}
if ($config['defaultFolderId'] === '' && $defaultRoot !== '') {
    $config['defaultFolderId'] = $defaultRoot;
}

$configJson = wp_json_encode($config);
if (!is_string($configJson)) {
    $configJson = '{}';
}

$fallbackNotice = (!$isPreview && (!$hasAccess || !$isConfigured));

// --- Nextcloud status bar ---
$ncSbUid       = 'mj-ncsb-' . wp_unique_id();
$ncCurrStatus  = array('login' => '', 'lastConnection' => null, 'authMode' => 'service');
$ncCurrentPassword = '';

if ($isNextcloudIframe && !$isPreview && $hasAccess) {
    if (function_exists('mj_member_documents_get_nc_current_status')) {
        $ncCurrStatus = mj_member_documents_get_nc_current_status();
    }
    if (function_exists('mj_member_documents_get_current_member_nextcloud_credentials')) {
        $ncCurrentCreds = mj_member_documents_get_current_member_nextcloud_credentials();
        $ncCurrentPassword = isset($ncCurrentCreds['password']) ? trim((string) $ncCurrentCreds['password']) : '';
    }
}
?>
<div class="mj-documents-widget<?php echo $isNextcloudIframe ? ' mj-documents-widget--nextcloud-iframe' : ''; ?>"
    <?php if (!$isNextcloudIframe) : ?>
        data-mj-member-documents-widget data-config="<?php echo esc_attr($configJson); ?>"
    <?php endif; ?>
>
    <?php if ($title !== '') : ?>
        <h3 class="mj-documents-widget__title"><?php echo esc_html($title); ?></h3>
    <?php endif; ?>

    <?php if ($introHtml !== '') : ?>
        <div class="mj-documents-widget__intro"><?php echo $introHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
    <?php endif; ?>

    <?php if ($isNextcloudIframe) : ?>
        <?php if ($fallbackNotice || $nextcloudIframeUrl === '') : ?>
            <p class="mj-documents-widget__notice mj-documents-widget__notice--fallback">
                <?php
                if (!$hasAccess) {
                    esc_html_e('Vous n’avez pas accès à cette section.', 'mj-member');
                } elseif (!$isConfigured || $nextcloudIframeUrl === '') {
                    esc_html_e('Le stockage de documents Nextcloud n\'est pas encore configuré.', 'mj-member');
                }
                ?>
            </p>
        <?php else : ?>
            <?php if (!$isPreview) : ?>
            <div class="mj-documents-status-bar">
                <div class="mj-documents-status-bar__info">
                    <span id="<?php echo esc_attr($ncSbUid . '-status-dot'); ?>"
                          class="mj-documents-status-bar__dot<?php echo !empty($ncCurrStatus['browserSessionVerified']) ? ' mj-documents-status-bar__dot--active' : (($ncCurrStatus['login'] !== '' || !empty($ncCurrStatus['apiCredentialsValid'])) ? ' mj-documents-status-bar__dot--warning' : ' mj-documents-status-bar__dot--service'); ?>"></span>
                    <span class="mj-documents-status-bar__text">
                        <?php if ($ncCurrStatus['login'] !== '') : ?>
                            <span id="<?php echo esc_attr($ncSbUid . '-status-primary'); ?>">
                                <?php esc_html_e('Compte Nextcloud sélectionné :', 'mj-member'); ?>
                                <strong><?php echo esc_html($ncCurrStatus['login']); ?></strong>
                                <span class="mj-documents-status-bar__sep">·</span>
                                <?php if (!empty($ncCurrStatus['apiCredentialsValid'])) : ?>
                                    <?php esc_html_e('Identifiants valides (API)', 'mj-member'); ?>
                                <?php else : ?>
                                    <?php esc_html_e('Identifiants invalides ou non vérifiés', 'mj-member'); ?>
                                <?php endif; ?>
                            </span>
                            <?php if ($ncCurrentPassword !== '') : ?>
                                <button type="button"
                                        id="<?php echo esc_attr($ncSbUid . '-show-password'); ?>"
                                        class="mj-documents-status-bar__connect-btn">
                                    <?php esc_html_e('Afficher le password', 'mj-member'); ?>
                                </button>
                                <code id="<?php echo esc_attr($ncSbUid . '-password-value'); ?>"
                                      class="mj-documents-status-bar__inline-password mj-documents-status-bar__inline-password--masked"
                                      data-password="<?php echo esc_attr($ncCurrentPassword); ?>"
                                      hidden>
                                    &#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;
                                </code>
                            <?php endif; ?>
                            <button type="button"
                                    id="<?php echo esc_attr($ncSbUid . '-connect'); ?>"
                                    class="mj-documents-status-bar__connect-btn">
                                <?php esc_html_e('Connect', 'mj-member'); ?>
                            </button>
                        <?php else : ?>
                            <span id="<?php echo esc_attr($ncSbUid . '-status-primary'); ?>">
                                <?php esc_html_e('Compte de service Nextcloud', 'mj-member'); ?>
                                <?php if (!empty($ncCurrStatus['apiCredentialsValid'])) : ?>
                                    <span class="mj-documents-status-bar__sep">·</span>
                                    <?php esc_html_e('Connexion API valide', 'mj-member'); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($ncCurrStatus['apiStatusMessage'])) : ?>
                        <span class="mj-documents-status-bar__sep">·</span>
                        <span class="mj-documents-status-bar__meta"><?php echo esc_html($ncCurrStatus['apiStatusMessage']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($ncCurrStatus['lastConnection'])) :
                        $ncTs = strtotime($ncCurrStatus['lastConnection']);
                    ?>
                        <span class="mj-documents-status-bar__sep">·</span>
                        <span class="mj-documents-status-bar__meta">
                            <?php echo $ncTs ? esc_html(sprintf(__('Dernière connexion : %s', 'mj-member'), date_i18n('d/m/Y H:i', $ncTs))) : ''; ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="mj-documents-status-bar__actions">
                    <div class="mj-documents-status-bar__view-switch" role="group" aria-label="<?php esc_attr_e('Mode d\'affichage', 'mj-member'); ?>">
                        <span class="mj-documents-status-bar__view-label"><?php esc_html_e('Affichage', 'mj-member'); ?></span>
                        <button type="button"
                                class="mj-documents-status-bar__view-btn is-active"
                                id="<?php echo esc_attr($ncSbUid . '-view-normal'); ?>"
                                aria-pressed="true">
                            <?php esc_html_e('Normal', 'mj-member'); ?>
                        </button>
                        <button type="button"
                                class="mj-documents-status-bar__view-btn"
                                id="<?php echo esc_attr($ncSbUid . '-view-below'); ?>"
                                aria-pressed="false">
                            <?php esc_html_e('Sous bannière', 'mj-member'); ?>
                        </button>
                        <button type="button"
                                class="mj-documents-status-bar__view-btn"
                                id="<?php echo esc_attr($ncSbUid . '-view-over'); ?>"
                                aria-pressed="false">
                            <?php esc_html_e('Sur header', 'mj-member'); ?>
                        </button>
                    </div>
                    <a href="<?php echo esc_url($nextcloudIframeUrl); ?>"
                       id="<?php echo esc_attr($ncSbUid . '-open-page'); ?>"
                       class="mj-documents-status-bar__connect-btn mj-documents-status-bar__open-link"
                       target="_blank"
                       rel="noopener noreferrer">
                        <svg class="mj-documents-status-bar__open-icon" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                            <path d="M8.75 2.25H11.75V5.25" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M6 8L11.5 2.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M11 7.5V10.25C11 10.9404 10.4404 11.5 9.75 11.5H3.75C3.05964 11.5 2.5 10.9404 2.5 10.25V4.25C2.5 3.55964 3.05964 3 3.75 3H6.5" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?php esc_html_e('Ouvrir dans une autre page', 'mj-member'); ?>
                    </a>
                </div>

                <div id="<?php echo esc_attr($ncSbUid . '-session-prompt'); ?>"
                     class="mj-documents-widget__session-prompt"
                     hidden>
                    <p class="mj-documents-widget__notice mj-documents-widget__notice--session">
                        <?php esc_html_e('Votre session Nextcloud n’est pas connectée. Cliquez sur le bouton pour ouvrir l’écran de connexion.', 'mj-member'); ?>
                    </p>
                    <button type="button"
                            id="<?php echo esc_attr($ncSbUid . '-session-connect'); ?>"
                            class="mj-documents-status-bar__connect-btn mj-documents-widget__session-connect-btn">
                        <?php esc_html_e('Se connecter à Nextcloud', 'mj-member'); ?>
                    </button>
                </div>

                <script>
                (function() {
                    var UID     = <?php echo wp_json_encode($ncSbUid); ?>;
                    var sessionCheckUrl = <?php echo wp_json_encode(esc_url_raw($nextcloudSessionCheckUrl)); ?>;

                    var $ = function(id) { return document.getElementById(id); };
                    var viewNormalBtn = $(UID + '-view-normal');
                    var viewBelowBtn  = $(UID + '-view-below');
                    var viewOverBtn   = $(UID + '-view-over');
                    var rootWidget = viewNormalBtn ? viewNormalBtn.closest('.mj-documents-widget') : null;
                    var statusDot = $(UID + '-status-dot');
                    var statusPrimary = $(UID + '-status-primary');
                    var iframeEl = $(UID + '-iframe');
                    var immersiveMode = '';
                    var currentLogin = <?php echo wp_json_encode((string) ($ncCurrStatus['login'] ?? '')); ?>;
                    var apiCredentialsValid = <?php echo !empty($ncCurrStatus['apiCredentialsValid']) ? 'true' : 'false'; ?>;
                    var initialStatusPrimaryHtml = statusPrimary ? statusPrimary.innerHTML : '';
                    var initialStatusDotClassName = statusDot ? statusDot.className : '';
                    var lastKnownBrowserState = 'unknown';
                    var showPasswordBtn = $(UID + '-show-password');
                    var inlinePasswordEl = $(UID + '-password-value');
                    var connectBtn = $(UID + '-connect');
                    var openPageBtn = $(UID + '-open-page');
                    var sessionPrompt = $(UID + '-session-prompt');
                    var sessionConnectBtn = $(UID + '-session-connect');
                    var sessionCheckTimer = null;
                    var manualLoginRequested = false;

                    function escHtml(s) {
                        var d = document.createElement('div');
                        d.appendChild(document.createTextNode(String(s)));
                        return d.innerHTML;
                    }

                    function setIframeVisible(visible) {
                        if (!iframeEl) {
                            return;
                        }

                        iframeEl.hidden = !visible;
                    }

                    function setSessionPromptVisible(visible) {
                        if (!sessionPrompt) {
                            return;
                        }

                        sessionPrompt.hidden = !visible;
                    }

                    function setStatusDotMode(mode) {
                        if (!statusDot) {
                            return;
                        }

                        statusDot.className = 'mj-documents-status-bar__dot';
                        if (mode === 'active') {
                            statusDot.classList.add('mj-documents-status-bar__dot--active');
                        } else if (mode === 'warning') {
                            statusDot.classList.add('mj-documents-status-bar__dot--warning');
                        } else {
                            statusDot.classList.add('mj-documents-status-bar__dot--service');
                        }
                    }

                    function setStatusPrimaryHtml(html) {
                        if (!statusPrimary) {
                            return;
                        }

                        statusPrimary.innerHTML = html;
                    }

                    function renderConnectedStatus(detectedUser) {
                        var label = detectedUser || currentLogin || '';
                        var html = 'Session navigateur Nextcloud active';
                        if (label) {
                            html += ' : <strong>' + escHtml(label) + '</strong>';
                        }

                        manualLoginRequested = false;
                        lastKnownBrowserState = 'connected';
                        setStatusDotMode('active');
                        setStatusPrimaryHtml(html);
                        setSessionPromptVisible(false);
                        setIframeVisible(true);
                    }

                    function renderDisconnectedStatus() {
                        var html = 'Session navigateur Nextcloud non connectée';
                        if (currentLogin) {
                            html = 'Compte Nextcloud sélectionné : <strong>' + escHtml(currentLogin) + '</strong>'
                                + '<span class="mj-documents-status-bar__sep">·</span>Session navigateur non connectée';
                        } else if (apiCredentialsValid) {
                            html += '<span class="mj-documents-status-bar__sep">·</span>API valide';
                        }

                        lastKnownBrowserState = 'disconnected';
                        setStatusDotMode('warning');
                        setStatusPrimaryHtml(html);

                        if (manualLoginRequested) {
                            setSessionPromptVisible(false);
                            setIframeVisible(true);
                        } else {
                            setSessionPromptVisible(true);
                            setIframeVisible(false);
                        }
                    }

                    function renderConnectingStatus() {
                        var html = 'Tentative de connexion Nextcloud…';
                        if (currentLogin) {
                            html = 'Tentative de connexion Nextcloud : <strong>' + escHtml(currentLogin) + '</strong>';
                        }

                        setStatusDotMode('warning');
                        setStatusPrimaryHtml(html);
                        setSessionPromptVisible(false);
                        setIframeVisible(true);
                    }

                    function renderUnknownStatus() {
                        if (lastKnownBrowserState !== 'unknown') {
                            return;
                        }

                        if (statusDot) {
                            statusDot.className = initialStatusDotClassName;
                        }
                        setStatusPrimaryHtml(initialStatusPrimaryHtml);
                        setSessionPromptVisible(false);
                        setIframeVisible(true);
                    }

                    function getNextcloudOrigin() {
                        var candidate = sessionCheckUrl || (iframeEl ? (iframeEl.dataset.baseSrc || iframeEl.getAttribute('src') || '') : '');
                        if (!candidate) {
                            return '';
                        }

                        try {
                            return new URL(candidate, window.location.href).origin;
                        } catch (_err) {
                            return '';
                        }
                    }

                    function resolveIframeExternalUrl() {
                        if (!iframeEl) {
                            return '';
                        }

                        try {
                            if (iframeEl.contentWindow && iframeEl.contentWindow.location) {
                                var liveHref = String(iframeEl.contentWindow.location.href || '');
                                if (liveHref && liveHref !== 'about:blank') {
                                    return liveHref;
                                }
                            }
                        } catch (_err) {
                            // Ignore cross-origin access errors and fall back to known source URL.
                        }

                        return iframeEl.getAttribute('src') || iframeEl.dataset.baseSrc || '';
                    }

                    function updateOpenPageHref() {
                        if (!openPageBtn) {
                            return;
                        }

                        var nextHref = resolveIframeExternalUrl();
                        if (nextHref) {
                            openPageBtn.href = nextHref;
                        }
                    }

                    function applyServerFallbackUi() {
                        manualLoginRequested = false;
                        setSessionPromptVisible(false);
                        setIframeVisible(true);
                        applyIframeBrowserSessionStatus();
                    }

                    function applyEndpointSessionStatus(payload) {
                        if (!payload || payload.success !== true) {
                            throw new Error('Invalid Nextcloud session payload');
                        }

                        if (payload.authenticated === true) {
                            renderConnectedStatus(payload.displayName || payload.userId || '');
                            return;
                        }

                        renderDisconnectedStatus();
                    }

                    function fetchEndpointSessionStatus(trigger) {
                        if (!sessionCheckUrl || typeof window.fetch !== 'function') {
                            applyServerFallbackUi();
                            return Promise.resolve(false);
                        }

                        return fetch(sessionCheckUrl, {
                            method: 'GET',
                            credentials: 'include',
                            mode: 'cors',
                            headers: {
                                'Accept': 'application/json'
                            },
                            cache: 'no-store'
                        }).then(function(response) {
                            if (!response.ok) {
                                throw new Error('HTTP ' + response.status);
                            }
                            return response.json();
                        }).then(function(payload) {
                            if (!payload || payload.success !== true) {
                                var message = payload && payload.message ? String(payload.message) : 'Nextcloud session endpoint returned success=false';
                                throw new Error(message);
                            }

                            applyEndpointSessionStatus(payload);
                            return true;
                        }).catch(function(error) {
                            console.error('[mj-member] Nextcloud session check failed (' + trigger + '):', error);
                            applyServerFallbackUi();
                            return false;
                        });
                    }

                    function startEndpointSessionPolling() {
                        if (sessionCheckTimer || !sessionCheckUrl) {
                            return;
                        }

                        sessionCheckTimer = window.setInterval(function() {
                            if (document.hidden) {
                                return;
                            }

                            fetchEndpointSessionStatus('interval');
                        }, 60000);
                    }

                    function isSessionMessagePayload(data) {
                        if (!data) {
                            return false;
                        }

                        var marker = '';
                        if (typeof data === 'string') {
                            marker = data;
                        } else if (typeof data === 'object') {
                            marker = String(data.type || data.event || data.action || '');
                        }

                        return /(session|auth|login|logout|connected|disconnected)/i.test(marker);
                    }

                    function inspectIframeBrowserSession() {
                        if (!iframeEl) {
                            return { state: 'unknown' };
                        }

                        var doc = null;
                        var frameWindow = null;
                        try {
                            frameWindow = iframeEl.contentWindow || null;
                            doc = iframeEl.contentDocument || (frameWindow ? frameWindow.document : null);
                        } catch (_err) {
                            return { state: 'unknown' };
                        }

                        if (!doc || !doc.documentElement) {
                            return { state: 'unknown' };
                        }

                        var body = doc.body;
                        var path = '';
                        var title = doc.title ? String(doc.title) : '';
                        try {
                            path = frameWindow && frameWindow.location ? String(frameWindow.location.pathname || '') : '';
                        } catch (_locErr) {
                            path = '';
                        }

                        var hasLoginForm = !!doc.querySelector('form[data-login-form], form[name="login"], form.login-form, #body-login form, input[data-login-form-input-password], input[type="password"][name="password"]');
                        var hasFilesUi = !!doc.querySelector('#app-navigation, #app-content, #filestable, #app-sidebar, #content.app-files, [data-cy-files-content], .files-list');
                        var detectedUser = body ? String(body.getAttribute('data-user') || '') : '';
                        var looksLikeLoginPage = hasLoginForm
                            || !!(body && (body.id === 'body-login' || body.classList.contains('guest')))
                            || /\/login(?:\/|$|\?)/.test(path)
                            || /connexion|login/i.test(title);
                        var looksLikeAppPage = hasFilesUi
                            || !!(body && (body.id === 'body-user' || body.classList.contains('logged-in') || body.getAttribute('data-user')))
                            || /\/apps\/(?:files|dashboard)(?:\/|$|\?)/.test(path);

                        if (looksLikeLoginPage) {
                            return { state: 'disconnected' };
                        }

                        if (looksLikeAppPage) {
                            return { state: 'connected', user: detectedUser };
                        }

                        return { state: 'unknown' };
                    }

                    function applyIframeBrowserSessionStatus() {
                        var result = inspectIframeBrowserSession();
                        if (result.state === 'connected') {
                            renderConnectedStatus(result.user || '');
                            return;
                        }
                        if (result.state === 'disconnected') {
                            renderDisconnectedStatus();
                            return;
                        }
                        renderUnknownStatus();
                    }

                    function scheduleIframeBrowserSessionChecks() {
                        if (!iframeEl) {
                            return;
                        }

                        [0, 200, 700, 1500, 2500].forEach(function(delay) {
                            setTimeout(function() {
                                applyIframeBrowserSessionStatus();
                            }, delay);
                        });
                    }

                    function attemptNextcloudAutoLoginOnce() {
                        if (!iframeEl) {
                            return;
                        }

                        var baseSrc = iframeEl.dataset.baseSrc || iframeEl.getAttribute('src') || '';
                        var autoSrc = iframeEl.dataset.autologinSrc || '';
                        var autoKey = iframeEl.dataset.autologinKey || '';

                        if (!baseSrc || !autoSrc || !autoKey || !window.sessionStorage) {
                            return;
                        }

                        var storageKey = 'mj_member_nc_autologin_' + autoKey;
                        if (sessionStorage.getItem(storageKey) === '1') {
                            return;
                        }

                        sessionStorage.setItem(storageKey, '1');
                        iframeEl.setAttribute('src', autoSrc);

                        // Ensure we return to the clean URL after the initial login attempt.
                        setTimeout(function() {
                            iframeEl.setAttribute('src', baseSrc);
                        }, 1500);
                    }

                    function attemptIframeLoginFormAutoFillOnce() {
                        if (!iframeEl || !window.sessionStorage) {
                            return;
                        }

                        var autoKey = iframeEl.dataset.autologinKey || '';
                        var loginValue = iframeEl.dataset.autologinUser || '';
                        var passwordValue = iframeEl.dataset.autologinPassword || '';
                        if (!autoKey || !loginValue || !passwordValue) {
                            return;
                        }

                        var storageKey = 'mj_member_nc_iframe_form_autofill_' + autoKey;
                        if (sessionStorage.getItem(storageKey) === '1') {
                            return;
                        }

                        var setNativeInputValue = function(input, value) {
                            if (!input) {
                                return;
                            }
                            var descriptor = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
                            if (descriptor && typeof descriptor.set === 'function') {
                                descriptor.set.call(input, value);
                            } else {
                                input.value = value;
                            }
                            input.dispatchEvent(new Event('input', { bubbles: true }));
                            input.dispatchEvent(new Event('change', { bubbles: true }));
                        };

                        var tryFillAndSubmit = function() {
                            var doc = null;
                            try {
                                doc = iframeEl.contentDocument || (iframeEl.contentWindow ? iframeEl.contentWindow.document : null);
                            } catch (_err) {
                                // Cross-origin iframe: JS access is blocked by browser security.
                                return false;
                            }

                            if (!doc) {
                                return false;
                            }

                            var loginInput = doc.querySelector('input[data-login-form-input-user], input[name="user"], input#user, input[name="username"]');
                            var passwordInput = doc.querySelector('input[data-login-form-input-password], input[type="password"][name="password"], input#password, input[type="password"]');
                            if (!loginInput || !passwordInput) {
                                return false;
                            }

                            setNativeInputValue(loginInput, loginValue);
                            setNativeInputValue(passwordInput, passwordValue);

                            var form = loginInput.form
                                || passwordInput.form
                                || doc.querySelector('form[data-login-form], form[name="login"], form.login-form, form');
                            if (!form) {
                                return false;
                            }

                            var submitBtn = form.querySelector('button[data-login-form-submit], button[type="submit"], input[type="submit"]');
                            sessionStorage.setItem(storageKey, '1');

                            try {
                                if (submitBtn && typeof submitBtn.click === 'function') {
                                    submitBtn.click();
                                } else if (typeof form.requestSubmit === 'function') {
                                    form.requestSubmit();
                                } else {
                                    form.submit();
                                }
                            } catch (_submitErr) {
                                // Do nothing if submit is blocked.
                            }

                            return true;
                        };

                        var onLoad = function() {
                            // Vue login DOM can mount asynchronously after iframe load.
                            var delays = [0, 200, 600, 1200, 1800];
                            delays.forEach(function(delay) {
                                setTimeout(function() {
                                    if (sessionStorage.getItem(storageKey) === '1') {
                                        return;
                                    }
                                    tryFillAndSubmit();
                                }, delay);
                            });
                        };

                        iframeEl.addEventListener('load', onLoad);
                        onLoad();
                    }

                    function attemptIframeLoginPostFallbackOnce() {
                        if (!iframeEl || !window.sessionStorage) {
                            return;
                        }

                        var autoKey = iframeEl.dataset.autologinKey || '';
                        var loginValue = iframeEl.dataset.autologinUser || '';
                        var passwordValue = iframeEl.dataset.autologinPassword || '';
                        var loginUrl = iframeEl.dataset.loginPostUrl || '';
                        var redirectUrl = iframeEl.dataset.baseSrc || iframeEl.getAttribute('src') || '';
                        if (!autoKey || !loginValue || !passwordValue || !loginUrl || !redirectUrl) {
                            return;
                        }

                        var storageKey = 'mj_member_nc_iframe_login_post_' + autoKey;
                        if (sessionStorage.getItem(storageKey) === '1') {
                            return;
                        }

                        if (!iframeEl.name) {
                            iframeEl.name = 'mjNcFrame_' + autoKey.replace(/[^a-zA-Z0-9_\-]/g, '_');
                        }

                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = loginUrl;
                        form.target = iframeEl.name;
                        form.style.display = 'none';

                        var addField = function(name, value) {
                            var input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = name;
                            input.value = value;
                            form.appendChild(input);
                        };

                        addField('user', loginValue);
                        addField('password', passwordValue);
                        addField('redirect_url', redirectUrl);
                        addField('timezone', (Intl && Intl.DateTimeFormat ? Intl.DateTimeFormat().resolvedOptions().timeZone : '') || 'Europe/Brussels');
                        addField('timezone_offset', String(new Date().getTimezoneOffset() / -60));

                        document.body.appendChild(form);
                        sessionStorage.setItem(storageKey, '1');
                        form.submit();

                        setTimeout(function() {
                            if (form && form.parentNode) {
                                form.parentNode.removeChild(form);
                            }
                        }, 1000);
                    }

                    function forceConnectNow() {
                        if (!iframeEl || !window.sessionStorage) {
                            return;
                        }

                        var autoKey = iframeEl.dataset.autologinKey || '';
                        manualLoginRequested = true;
                        lastKnownBrowserState = 'unknown';
                        renderConnectingStatus();

                        if (autoKey) {
                            sessionStorage.removeItem('mj_member_nc_autologin_' + autoKey);
                            sessionStorage.removeItem('mj_member_nc_iframe_form_autofill_' + autoKey);
                            sessionStorage.removeItem('mj_member_nc_iframe_login_post_' + autoKey);
                        }

                        var loginUrl = iframeEl.dataset.loginPostUrl || '';
                        var baseSrc = iframeEl.dataset.baseSrc || iframeEl.getAttribute('src') || '';

                        // Manual connect: open login form first, then autofill + submit.
                        if (loginUrl) {
                            var loginPageUrl = loginUrl;
                            if (baseSrc) {
                                var sep = loginPageUrl.indexOf('?') === -1 ? '?' : '&';
                                loginPageUrl = loginPageUrl + sep + 'redirect_url=' + encodeURIComponent(baseSrc);
                            }
                            iframeEl.setAttribute('src', loginPageUrl);
                            updateOpenPageHref();
                        }

                        // Wait a bit for iframe navigation, then try DOM autofill and POST fallback.
                        setTimeout(function() {
                            attemptIframeLoginFormAutoFillOnce();
                        }, 120);
                        setTimeout(function() {
                            attemptIframeLoginPostFallbackOnce();
                        }, 500);
                        setTimeout(function() {
                            fetchEndpointSessionStatus('manual-connect');
                        }, 1500);
                    }

                    function getImmersiveTopOffset() {
                        var top = 0;
                        var adminBar = document.getElementById('wpadminbar');
                        if (adminBar && window.getComputedStyle(adminBar).position === 'fixed') {
                            top += adminBar.offsetHeight;
                        }

                        var banner = document.querySelector('.main-banner, .hero-banner, .elementor-location-header, #masthead, .site-header, header');
                        if (banner) {
                            var rect = banner.getBoundingClientRect();
                            if (rect.bottom > top && rect.bottom < window.innerHeight) {
                                top = Math.max(top, Math.round(rect.bottom));
                            }
                        }

                        return Math.max(0, top);
                    }

                    function updateViewButtons(mode) {
                        if (!viewNormalBtn || !viewBelowBtn || !viewOverBtn) {
                            return;
                        }

                        var normalActive = mode === '';
                        var belowActive = mode === 'below-header';
                        var overActive = mode === 'over-header';

                        viewNormalBtn.classList.toggle('is-active', normalActive);
                        viewBelowBtn.classList.toggle('is-active', belowActive);
                        viewOverBtn.classList.toggle('is-active', overActive);

                        viewNormalBtn.setAttribute('aria-pressed', normalActive ? 'true' : 'false');
                        viewBelowBtn.setAttribute('aria-pressed', belowActive ? 'true' : 'false');
                        viewOverBtn.setAttribute('aria-pressed', overActive ? 'true' : 'false');
                    }

                    function setImmersiveMode(mode) {
                        if (!rootWidget) {
                            return;
                        }

                        if (mode === 'below-header' || mode === 'over-header') {
                            var topOffset = (mode === 'below-header') ? getImmersiveTopOffset() : (function() {
                                var top = 0;
                                var adminBar = document.getElementById('wpadminbar');
                                if (adminBar && window.getComputedStyle(adminBar).position === 'fixed') {
                                    top = adminBar.offsetHeight;
                                }
                                return top;
                            })();

                            rootWidget.style.setProperty('--mj-documents-immersive-top', String(topOffset) + 'px');
                            rootWidget.classList.add('mj-documents-widget--immersive');
                            rootWidget.classList.toggle('mj-documents-widget--immersive-over-header', mode === 'over-header');
                            document.body.classList.add('mj-documents-immersive-open');
                            immersiveMode = mode;
                        } else {
                            rootWidget.classList.remove('mj-documents-widget--immersive');
                            rootWidget.classList.remove('mj-documents-widget--immersive-over-header');
                            rootWidget.style.removeProperty('--mj-documents-immersive-top');
                            document.body.classList.remove('mj-documents-immersive-open');
                            immersiveMode = '';
                        }

                        updateViewButtons(immersiveMode);
                    }

                    if (viewNormalBtn && viewBelowBtn && viewOverBtn && rootWidget) {
                        viewNormalBtn.addEventListener('click', function() { setImmersiveMode(''); });
                        viewBelowBtn.addEventListener('click', function() { setImmersiveMode('below-header'); });
                        viewOverBtn.addEventListener('click', function() { setImmersiveMode('over-header'); });

                        updateViewButtons('');

                        window.addEventListener('resize', function() {
                            if (immersiveMode) {
                                setImmersiveMode(immersiveMode);
                            }
                        });

                        window.addEventListener('scroll', function() {
                            if (immersiveMode) {
                                setImmersiveMode(immersiveMode);
                            }
                        }, { passive: true });

                        document.addEventListener('keydown', function(e) {
                            if (e.key === 'Escape' && immersiveMode) {
                                setImmersiveMode('');
                            }
                        });
                    }

                    if (iframeEl) {
                        iframeEl.addEventListener('load', function() {
                            updateOpenPageHref();
                            scheduleIframeBrowserSessionChecks();
                            fetchEndpointSessionStatus('iframe-load');
                        });
                        updateOpenPageHref();
                    }

                    window.addEventListener('message', function(event) {
                        var nextcloudOrigin = getNextcloudOrigin();
                        if (nextcloudOrigin && event.origin !== nextcloudOrigin) {
                            return;
                        }

                        if (!isSessionMessagePayload(event.data)) {
                            return;
                        }

                        fetchEndpointSessionStatus('postmessage');
                    });

                    fetchEndpointSessionStatus('initial');
                    startEndpointSessionPolling();

                    if (connectBtn) {
                        connectBtn.addEventListener('click', function() {
                            forceConnectNow();
                        });
                    }

                    if (sessionConnectBtn) {
                        sessionConnectBtn.addEventListener('click', function() {
                            forceConnectNow();
                        });
                    }

                    if (showPasswordBtn && inlinePasswordEl) {
                        showPasswordBtn.addEventListener('click', function() {
                            var isMasked = inlinePasswordEl.classList.contains('mj-documents-status-bar__inline-password--masked');
                            inlinePasswordEl.hidden = false;
                            if (isMasked) {
                                inlinePasswordEl.textContent = inlinePasswordEl.dataset.password || '';
                                inlinePasswordEl.classList.remove('mj-documents-status-bar__inline-password--masked');
                                showPasswordBtn.textContent = 'Masquer le password';
                            } else {
                                inlinePasswordEl.textContent = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022';
                                inlinePasswordEl.classList.add('mj-documents-status-bar__inline-password--masked');
                                showPasswordBtn.textContent = 'Afficher le password';
                            }
                        });
                    }
                })();
                </script>
            </div>
            <?php endif; // !$isPreview ?>
            <iframe
                id="<?php echo esc_attr($ncSbUid . '-iframe'); ?>"
                name="<?php echo esc_attr($ncSbUid . '-iframe-target'); ?>"
                class="mj-documents-widget__nextcloud-iframe"
                src="<?php echo esc_url($nextcloudIframeUrl); ?>"
                data-base-src="<?php echo esc_url($nextcloudIframeUrl); ?>"
                data-autologin-src="<?php echo esc_attr($nextcloudIframeAutoUrl); ?>"
                data-autologin-key="<?php echo esc_attr($nextcloudAutoLoginKey); ?>"
                data-autologin-user="<?php echo esc_attr($nextcloudAutoLoginUser); ?>"
                data-autologin-password="<?php echo esc_attr($nextcloudAutoLoginPassword); ?>"
                data-login-post-url="<?php echo esc_url($nextcloudLoginPostUrl); ?>"
                title="<?php esc_attr_e('Espace documents Nextcloud', 'mj-member'); ?>"
                loading="lazy"
                referrerpolicy="strict-origin-when-cross-origin"
                style="width:100%; min-height:820px; border:1px solid #e2e8f0; border-radius:12px; background:#fff;"
            ></iframe>
        <?php endif; ?>
    <?php elseif ($fallbackNotice) : ?>
        <p class="mj-documents-widget__notice mj-documents-widget__notice--fallback">
            <?php
            if (!$hasAccess) {
                esc_html_e('Vous n’avez pas accès à cette section.', 'mj-member');
            } elseif (!$isConfigured) {
                esc_html_e('Le stockage de documents Nextcloud n\'est pas encore configuré.', 'mj-member');
            }
            ?>
        </p>
    <?php endif; ?>

    <noscript>
        <p class="mj-documents-widget__notice">
            <?php
            if ($isNextcloudIframe) {
                esc_html_e('Cette vue Nextcloud nécessite JavaScript pour certaines interactions.', 'mj-member');
            } else {
                esc_html_e('Ce module nécessite JavaScript pour afficher les documents.', 'mj-member');
            }
            ?>
        </p>
    </noscript>
</div>
