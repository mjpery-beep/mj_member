<?php

declare(strict_types=1);

namespace Mj\Member\Tests\Crud;

use Mj\Member\Classes\Crud\CrudQueryBuilder;
use PHPUnit\Framework\TestCase;

final class CrudQueryBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';
            public array $escLikeCalls = array();

            public function esc_like($text)
            {
                $this->escLikeCalls[] = (string) $text;
                return addcslashes((string) $text, '%_');
            }
        };
    }

    public function testBuildSelectWithMultipleClauses(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder
            ->where_in_int('id', array(1, '2', 0, -5))
            ->where_not_in_int('guardian_id', array(null, 3, 3))
            ->where_in_strings('status', array('active', '', 'inactive'))
            ->where_like_any(array('first_name', 'last_name'), 'John');

        list($sql, $params) = $builder->build_select('*', 'created_at', 'desc', 10, 5);

        $expectedSql = 'SELECT * FROM wp_mj_members WHERE 1=1 AND id IN (%d,%d) AND guardian_id NOT IN (%d) AND status IN (%s,%s) AND (first_name LIKE %s OR last_name LIKE %s) ORDER BY created_at DESC LIMIT %d OFFSET %d';
        $expectedParams = array(1, 2, 3, 'active', 'inactive', '%John%', '%John%', 10, 5);

        $this->assertSame($expectedSql, $sql);
        $this->assertSame($expectedParams, $params);

        $this->assertSame(array('John'), $GLOBALS['wpdb']->escLikeCalls);
    }

    public function testBuildCountReflectsConditions(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder
            ->where_equals_int('event_id', 42)
            ->where_equals('statut', 'confirmed');

        list($sql, $params) = $builder->build_count('*');

        $this->assertSame('SELECT COUNT(*) FROM wp_events WHERE 1=1 AND event_id = %d AND statut = %s', $sql);
        $this->assertSame(array(42, 'confirmed'), $params);
    }
}
