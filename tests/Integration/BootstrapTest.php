<?php

declare(strict_types=1);

namespace Mj\Member {
    if (!function_exists(__NAMESPACE__ . '\\is_admin')) {
        function is_admin(): bool
        {
            return !empty($GLOBALS['__mj_test_is_admin']);
        }
    }
}

namespace Mj\Member\Tests\Integration {

use Mj\Member\Bootstrap;
use PHPUnit\Framework\TestCase;

final class BootstrapTest extends TestCase
{
    private string $tmpDir;

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/includes/core/Contracts/ModuleInterface.php';
        require_once dirname(__DIR__, 2) . '/includes/core/Contracts/AjaxHandlerInterface.php';
        require_once dirname(__DIR__, 2) . '/includes/core/Config.php';
        require_once dirname(__DIR__, 2) . '/includes/Bootstrap.php';

        if (!defined('MJ_MEMBER_PATH')) {
            define('MJ_MEMBER_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }

        $GLOBALS['__mj_test_actions'] = $GLOBALS['__mj_test_filters'] = $GLOBALS['__mj_test_shortcodes'] = array();
        $GLOBALS['__mj_test_is_admin'] = false;
        $GLOBALS['__mj_test_bootstrap_module_registered'] = array();
        $GLOBALS['__mj_test_bootstrap_ajax_registered'] = array();

        $this->resetBootstrapLoaded();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (glob($this->tmpDir . DIRECTORY_SEPARATOR . 'bootstrap-test-*.php') ?: array() as $file) {
            @unlink($file);
        }

        $this->resetBootstrapLoaded();
        unset($GLOBALS['__mj_test_is_admin']);
        unset($GLOBALS['__mj_test_bootstrap_module_registered'], $GLOBALS['__mj_test_bootstrap_ajax_registered']);
    }

    public function testInitRegistersModuleAndAjaxClassesFromFilteredModules(): void
    {
        $id = uniqid('case', true);
        $moduleClass = 'Mj\\Member\\Tests\\Fixtures\\BootstrapModule_' . preg_replace('/[^A-Za-z0-9_]/', '_', $id);
        $ajaxClass = 'Mj\\Member\\Tests\\Fixtures\\BootstrapAjax_' . preg_replace('/[^A-Za-z0-9_]/', '_', $id);
        $moduleShortClass = $this->shortClassName($moduleClass);
        $ajaxShortClass = $this->shortClassName($ajaxClass);

        $relative = $this->writeModule(
            'module-ajax',
            "<?php\n"
            . "namespace Mj\\Member\\Tests\\Fixtures;\n"
            . "class " . $moduleShortClass . " implements \\Mj\\Member\\Core\\Contracts\\ModuleInterface { public function register(): void { \$GLOBALS['__mj_test_bootstrap_module_registered'][] = self::class; } }\n"
            . "class " . $ajaxShortClass . " implements \\Mj\\Member\\Core\\Contracts\\AjaxHandlerInterface { public function registerHooks(): void { \$GLOBALS['__mj_test_bootstrap_ajax_registered'][] = self::class; } }\n"
        );

        add_filter('mj_member_bootstrap_modules', static function ($modules) use ($relative) {
            return array($relative);
        });

        Bootstrap::init();

        $this->assertSame(array($moduleClass), $GLOBALS['__mj_test_bootstrap_module_registered']);
        $this->assertSame(array($ajaxClass), $GLOBALS['__mj_test_bootstrap_ajax_registered']);
    }

    public function testInitDoesNotLoadAdminModulesOutsideAdminContext(): void
    {
        $frontRelative = $this->writeModule(
            'front-only',
            "<?php\n"
            . "namespace Mj\\Member\\Tests\\Fixtures;\n"
            . "class BootstrapFrontOnlyModuleA implements \\Mj\\Member\\Core\\Contracts\\ModuleInterface { public function register(): void { \$GLOBALS['__mj_test_bootstrap_module_registered'][] = 'front'; } }\n"
        );
        $adminRelative = $this->writeModule(
            'admin-only',
            "<?php\n"
            . "namespace Mj\\Member\\Tests\\Fixtures;\n"
            . "class BootstrapAdminOnlyModuleA implements \\Mj\\Member\\Core\\Contracts\\ModuleInterface { public function register(): void { \$GLOBALS['__mj_test_bootstrap_module_registered'][] = 'admin'; } }\n"
        );

        add_filter('mj_member_bootstrap_modules', static function ($modules) use ($frontRelative) {
            return array($frontRelative);
        });
        add_filter('mj_member_bootstrap_admin_modules', static function ($modules) use ($adminRelative) {
            return array($adminRelative);
        });

        Bootstrap::init();

        $this->assertSame(array('front'), $GLOBALS['__mj_test_bootstrap_module_registered']);
    }

    public function testInitLoadsAdminModulesInAdminContext(): void
    {
        $frontRelative = $this->writeModule(
            'front-admin-mode',
            "<?php\n"
            . "namespace Mj\\Member\\Tests\\Fixtures;\n"
            . "class BootstrapFrontOnlyModuleB implements \\Mj\\Member\\Core\\Contracts\\ModuleInterface { public function register(): void { \$GLOBALS['__mj_test_bootstrap_module_registered'][] = 'front'; } }\n"
        );
        $adminRelative = $this->writeModule(
            'admin-admin-mode',
            "<?php\n"
            . "namespace Mj\\Member\\Tests\\Fixtures;\n"
            . "class BootstrapAdminOnlyModuleB implements \\Mj\\Member\\Core\\Contracts\\ModuleInterface { public function register(): void { \$GLOBALS['__mj_test_bootstrap_module_registered'][] = 'admin'; } }\n"
        );

        $GLOBALS['__mj_test_is_admin'] = true;

        add_filter('mj_member_bootstrap_modules', static function ($modules) use ($frontRelative) {
            return array($frontRelative);
        });
        add_filter('mj_member_bootstrap_admin_modules', static function ($modules) use ($adminRelative) {
            return array($adminRelative);
        });

        Bootstrap::init();

        $this->assertSame(array('front', 'admin'), $GLOBALS['__mj_test_bootstrap_module_registered']);
    }

    public function testInitSkipsMissingModuleAndLoadsReadableOne(): void
    {
        $validRelative = $this->writeModule(
            'valid-after-missing',
            "<?php\n"
            . "namespace Mj\\Member\\Tests\\Fixtures;\n"
            . "class BootstrapValidModule implements \\Mj\\Member\\Core\\Contracts\\ModuleInterface { public function register(): void { \$GLOBALS['__mj_test_bootstrap_module_registered'][] = 'ok'; } }\n"
        );

        add_filter('mj_member_bootstrap_modules', static function ($modules) use ($validRelative) {
            return array('tests/tmp/bootstrap-test-missing-file.php', $validRelative);
        });

        Bootstrap::init();

        $this->assertSame(array('ok'), $GLOBALS['__mj_test_bootstrap_module_registered']);
    }

    private function writeModule(string $label, string $content): string
    {
        $filename = 'bootstrap-test-' . $label . '-' . uniqid('', true) . '.php';
        $path = $this->tmpDir . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $content);

        return 'tests/tmp/' . $filename;
    }

    private function resetBootstrapLoaded(): void
    {
        $reflection = new \ReflectionClass(Bootstrap::class);
        $property = $reflection->getProperty('loaded');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return (string) end($parts);
    }
}
}