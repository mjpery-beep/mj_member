<?php

declare(strict_types=1);

namespace Mj\Member\Classes {
    if (!class_exists(MjPayments::class, false)) {
        class MjPayments
        {
            public static $lastToken = '';
            public static $result = true;

            public static function confirm_payment_by_token($token)
            {
                self::$lastToken = $token;
                return self::$result;
            }
        }
    }
}

namespace Mj\Member\Tests\Integration {

use Mj\Member\Classes\MjPayments;
use PHPUnit\Framework\TestCase;

final class CriticalHooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetHooks();
        $GLOBALS['__mj_last_redirect'] = null;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetHooks();
        $GLOBALS['__mj_last_redirect'] = null;
        $_GET = array();
        MjPayments::$result = true;
        MjPayments::$lastToken = '';
    }

    public function testLoginShortcodeIsRegistered(): void
    {
        require_once $this->pluginPath('includes/templates/elementor/shortcode_member_account.php');
        $this->assertArrayHasKey('mj_member_login', $GLOBALS['__mj_test_shortcodes']);
    }

    public function testPaymentConfirmationHookAndSuccessFlow(): void
    {
        require_once $this->pluginPath('includes/payment_confirmation.php');
        $this->assertArrayHasKey('init', $GLOBALS['__mj_test_actions']);

        MjPayments::$result = true;
        add_filter('mj_member_payment_confirmation_should_exit', static function () {
            return false;
        });

        $_GET['mj_payment_confirm'] = ' token 123 ';
        mj_handle_payment_confirmation();

        $this->assertSame('token 123', MjPayments::$lastToken);
        $this->assertSame('?mj_payment_status=ok', $GLOBALS['__mj_last_redirect']);
    }

    public function testPaymentConfirmationErrorFlow(): void
    {
        require_once $this->pluginPath('includes/payment_confirmation.php');

        MjPayments::$result = false;
        add_filter('mj_member_payment_confirmation_should_exit', static function () {
            return false;
        });

        $_GET['mj_payment_confirm'] = 'abc';
        mj_handle_payment_confirmation();

        $this->assertSame('?mj_payment_status=error', $GLOBALS['__mj_last_redirect']);
    }

    private function resetHooks(): void
    {
        $GLOBALS['__mj_test_actions'] = $GLOBALS['__mj_test_filters'] = $GLOBALS['__mj_test_shortcodes'] = array();
        $GLOBALS['__mj_scheduled_events'] = array();
    }

    private function pluginPath(string $relative): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . ltrim($relative, '/');
    }
}
}
