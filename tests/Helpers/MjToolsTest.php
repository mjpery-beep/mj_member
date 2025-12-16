<?php

declare(strict_types=1);

namespace Mj\Member\Tests\Helpers;

use Mj\Member\Classes\MjTools;
use PHPUnit\Framework\TestCase;

final class MjToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
        };
    }

    public function testBrDisplayReplacesSemicolonsWithBreaks(): void
    {
        $value = 'Foo;Bar;Baz';
        $this->assertSame('Foo <br /> Bar <br /> Baz', MjTools::brDisplay($value));
    }

    public function testNameDisplayReplacesSemicolonsWithConjunction(): void
    {
        $value = 'Alice;Bob';
        $this->assertSame('Alice et Bob', MjTools::nameDisplay($value));
    }

    public function testGetTableNameUsesWordPressPrefix(): void
    {
        $this->assertSame('wp_members', MjTools::getTableName('members'));
    }
}
