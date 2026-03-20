<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

if (!function_exists('get_option')) {
    function get_option($name, $default = false)
    {
        $options = $GLOBALS['__mj_test_options'] ?? array();
        return array_key_exists((string) $name, $options) ? $options[(string) $name] : $default;
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post)
    {
        $map = $GLOBALS['__mj_test_permalink_map'] ?? array();
        $id = 0;

        if (is_numeric($post)) {
            $id = (int) $post;
        } elseif (is_object($post)) {
            if (isset($post->ID)) {
                $id = (int) $post->ID;
            } elseif (isset($post->id)) {
                $id = (int) $post->id;
            }
        }

        return $map[$id] ?? false;
    }
}

if (!function_exists('get_page_by_path')) {
    function get_page_by_path($path)
    {
        $pages = $GLOBALS['__mj_test_pages_by_path'] ?? array();
        $key = ltrim((string) $path, '/');
        return $pages[$key] ?? null;
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '')
    {
        $base = (string) ($GLOBALS['__mj_test_home_url'] ?? 'https://example.com');
        $base = rtrim($base, '/');

        $path = (string) $path;
        if ($path === '') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_validate_redirect')) {
    function wp_validate_redirect($location, $default = '')
    {
        $location = trim((string) $location);
        if ($location === '') {
            return (string) $default;
        }

        $callback = $GLOBALS['__mj_test_wp_validate_redirect_callback'] ?? null;
        if (is_callable($callback)) {
            return (string) call_user_func($callback, $location, $default);
        }

        $allowed_host = (string) ($GLOBALS['__mj_test_allowed_redirect_host'] ?? 'example.com');
        $host = (string) parse_url($location, PHP_URL_HOST);

        if ($host === '' || strcasecmp($host, $allowed_host) === 0) {
            return $location;
        }

        return (string) $default;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url)
    {
        return (string) $url;
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url)
    {
        return (string) $url;
    }
}

if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($attachment_id, $size = 'thumbnail')
    {
        $thumbs = $GLOBALS['__mj_test_attachment_thumbs'] ?? array();
        $url = $thumbs[(int) $attachment_id] ?? '';
        if ($url === '') {
            return false;
        }

        return array($url, 96, 96, true);
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id)
    {
        $urls = $GLOBALS['__mj_test_attachment_urls'] ?? array();
        return $urls[(int) $attachment_id] ?? false;
    }
}

if (!function_exists('get_avatar_url')) {
    function get_avatar_url($email, $args = array())
    {
        $avatars = $GLOBALS['__mj_test_gravatar_urls'] ?? array();
        $key = strtolower((string) $email);
        if (isset($avatars[$key])) {
            return (string) $avatars[$key];
        }

        return 'https://example.com/avatar/default.png';
    }
}

if (!function_exists('mj_member_login_component_get_member_avatar')) {
    function mj_member_login_component_get_member_avatar($user = null, $member = null)
    {
        $avatar = $GLOBALS['__mj_test_login_component_avatar'] ?? array();
        return is_array($avatar) ? $avatar : array();
    }
}

final class ShortcodeMemberAccountHelpersTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/templates/elementor/shortcode_member_account.php';
    }

    protected function setUp(): void
    {
        $_REQUEST = array();

        $GLOBALS['__mj_test_options'] = array();
        $GLOBALS['__mj_test_permalink_map'] = array();
        $GLOBALS['__mj_test_pages_by_path'] = array();
        $GLOBALS['__mj_test_home_url'] = 'https://example.com';
        $GLOBALS['__mj_test_allowed_redirect_host'] = 'example.com';
        $GLOBALS['__mj_test_wp_validate_redirect_callback'] = null;

        $GLOBALS['__mj_test_attachment_thumbs'] = array();
        $GLOBALS['__mj_test_attachment_urls'] = array();
        $GLOBALS['__mj_test_gravatar_urls'] = array();
        $GLOBALS['__mj_test_login_component_avatar'] = array();
    }

    public function testGetAccountRedirectReturnsExplicitRedirectWhenValid(): void
    {
        $redirect = mj_member_get_account_redirect(array('redirect' => 'https://example.com/member-area'));

        $this->assertSame('https://example.com/member-area', $redirect);
    }

    public function testGetAccountRedirectRejectsExternalRequestRedirectAndFallsBackToAccountPageOption(): void
    {
        $_REQUEST['redirect_to'] = 'https://evil.test/phishing';
        $GLOBALS['__mj_test_options']['mj_member_account_page_id'] = 44;
        $GLOBALS['__mj_test_permalink_map'][44] = 'https://example.com/mon-compte';

        $redirect = mj_member_get_account_redirect();

        $this->assertSame('https://example.com/mon-compte', $redirect);
    }

    public function testGetAccountRedirectUsesProfilePageIdFromSettings(): void
    {
        $GLOBALS['__mj_test_options']['mj_account_links_settings'] = array(
            'profile' => array(
                'page_id' => 88,
            ),
        );
        $GLOBALS['__mj_test_permalink_map'][88] = 'https://example.com/profil';

        $redirect = mj_member_get_account_redirect();

        $this->assertSame('https://example.com/profil', $redirect);
    }

    public function testGetAccountRedirectUsesProfileSlugWhenPagePermalinkIsMissing(): void
    {
        $GLOBALS['__mj_test_options']['mj_account_links_settings'] = array(
            'profile' => array(
                'page_id' => 101,
                'slug' => 'espace-membre',
            ),
        );

        $redirect = mj_member_get_account_redirect();

        $this->assertSame('https://example.com/espace-membre', $redirect);
    }

    public function testGetAccountRedirectFallsBackToMonComptePathWhenNoConfiguredSourceExists(): void
    {
        $GLOBALS['__mj_test_pages_by_path']['mon-compte'] = (object) array('ID' => 77);
        $GLOBALS['__mj_test_permalink_map'][77] = 'https://example.com/mon-compte-fallback';

        $redirect = mj_member_get_account_redirect();

        $this->assertSame('https://example.com/mon-compte-fallback', $redirect);
    }

    /**
     * @dataProvider provideBirthDateNormalizationCases
     */
    public function testNormalizeBirthDate(string $input, string $expected): void
    {
        $this->assertSame($expected, mj_member_account_normalize_birth_date($input));
    }

    public function provideBirthDateNormalizationCases(): array
    {
        return array(
            'empty string' => array('', ''),
            'zero date' => array('0000-00-00', ''),
            'valid iso' => array('2005-03-21', '2005-03-21'),
            'invalid string' => array('not-a-date', ''),
        );
    }

    public function testGetPhotoPreviewUsesThumbnailWhenAttachmentThumbnailExists(): void
    {
        $member = (object) array(
            'photo_id' => 5,
            'email' => 'camille@example.com',
        );
        $GLOBALS['__mj_test_attachment_thumbs'][5] = 'https://example.com/uploads/thumb-5.jpg';

        $preview = mj_member_account_get_photo_preview($member);

        $this->assertSame(5, $preview['id']);
        $this->assertSame('https://example.com/uploads/thumb-5.jpg', $preview['url']);
    }

    public function testGetPhotoPreviewFallsBackToLoginComponentAvatarWhenNoAttachmentImageExists(): void
    {
        $member = (object) array(
            'photo_id' => 0,
            'email' => 'camille@example.com',
        );
        $GLOBALS['__mj_test_login_component_avatar'] = array(
            'url' => 'https://example.com/uploads/avatar-helper.jpg',
            'id' => 42,
        );

        $preview = mj_member_account_get_photo_preview($member);

        $this->assertSame(42, $preview['id']);
        $this->assertSame('https://example.com/uploads/avatar-helper.jpg', $preview['url']);
    }

    public function testGetPhotoPreviewFallsBackToAttachmentUrlWhenThumbnailMissing(): void
    {
        $member = (object) array(
            'photo_id' => 9,
            'email' => 'camille@example.com',
        );
        $GLOBALS['__mj_test_attachment_urls'][9] = 'https://example.com/uploads/full-9.jpg';

        $preview = mj_member_account_get_photo_preview($member);

        $this->assertSame(9, $preview['id']);
        $this->assertSame('https://example.com/uploads/full-9.jpg', $preview['url']);
    }

    public function testGetPhotoPreviewFallsBackToEmailAvatarWhenNoMediaFound(): void
    {
        $member = (object) array(
            'photo_id' => 0,
            'email' => 'camille@example.com',
        );
        $GLOBALS['__mj_test_gravatar_urls']['camille@example.com'] = 'https://example.com/avatar/camille.png';

        $preview = mj_member_account_get_photo_preview($member);

        $this->assertSame(0, $preview['id']);
        $this->assertSame('https://example.com/avatar/camille.png', $preview['url']);
    }
}
