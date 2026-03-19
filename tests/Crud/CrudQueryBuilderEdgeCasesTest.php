<?php

declare(strict_types=1);

namespace Mj\Member\Tests\Crud;

use Mj\Member\Classes\Crud\CrudQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Couvre les méthodes non testées dans CrudQueryBuilderTest :
 * where_compare, where_tokenized_search, where_raw
 * ainsi que les cas limites (tableaux vides, valeurs invalides, etc.)
 */
final class CrudQueryBuilderEdgeCasesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wpdb'] = new class {
            public $prefix = 'wp_';

            public function esc_like($text)
            {
                return addcslashes((string) $text, '%_');
            }
        };
    }

    // -------------------------------------------------------------------------
    // where_compare
    // -------------------------------------------------------------------------

    public function testWhereCompareWithStringGreaterThanOrEqual(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_compare('date_debut', '>=', '2025-01-01');

        [$sql, $params] = $builder->build_select();

        $this->assertStringContainsString('date_debut >= %s', $sql);
        $this->assertSame(['2025-01-01'], $params);
    }

    public function testWhereCompareWithIntFormat(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_compare('capacity_total', '>', 10, '%d');

        [$sql, $params] = $builder->build_select();

        $this->assertStringContainsString('capacity_total > %d', $sql);
        $this->assertSame([10], $params);
    }

    public function testWhereCompareIgnoresInvalidOperator(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_compare('status', '=', 'active'); // '=' is not allowed

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_events WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereCompareIgnoresEmptyStringValue(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_compare('title', '>=', '');

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_events WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereCompareIgnoresZeroIntValue(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_compare('age_min', '>=', 0, '%d');

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_events WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereCompareWithLessThanOrEqual(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_compare('prix', '<=', '50.00');

        [$sql, $params] = $builder->build_select();

        $this->assertStringContainsString('prix <= %s', $sql);
        $this->assertSame(['50.00'], $params);
    }

    // -------------------------------------------------------------------------
    // where_tokenized_search
    // -------------------------------------------------------------------------

    public function testWhereTokenizedSearchWithTwoTokens(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_tokenized_search(['first_name', 'last_name'], 'John Doe');

        [$sql, $params] = $builder->build_select();

        // Chaque token génère un groupe AND, chaque colonne un OR à l'intérieur
        $this->assertStringContainsString('(first_name LIKE %s OR last_name LIKE %s)', $sql);
        $this->assertCount(4, $params); // 2 colonnes × 2 tokens
        $this->assertSame('%John%', $params[0]);
        $this->assertSame('%John%', $params[1]);
        $this->assertSame('%Doe%', $params[2]);
        $this->assertSame('%Doe%', $params[3]);
    }

    public function testWhereTokenizedSearchWithSingleToken(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_tokenized_search(['email'], 'alice');

        [$sql, $params] = $builder->build_select();

        $this->assertStringContainsString('email LIKE %s', $sql);
        $this->assertSame(['%alice%'], $params);
    }

    public function testWhereTokenizedSearchIgnoresEmptySearch(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_tokenized_search(['first_name'], '');

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_mj_members WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereTokenizedSearchIgnoresEmptyColumns(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_tokenized_search([], 'Alice');

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_mj_members WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereTokenizedSearchCapsTokensAtMaxTokens(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        // 5 tokens, max_tokens = 3 → seuls les 3 premiers doivent être pris en compte
        $builder->where_tokenized_search(['first_name'], 'a b c d e', 3);

        [$sql, $params] = $builder->build_select();

        $this->assertCount(3, $params);
        $this->assertSame('%a%', $params[0]);
        $this->assertSame('%b%', $params[1]);
        $this->assertSame('%c%', $params[2]);
    }

    // -------------------------------------------------------------------------
    // where_raw
    // -------------------------------------------------------------------------

    public function testWhereRawAppendsClauseAndParams(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_raw('DATE(date_debut) = %s', ['2025-06-01']);

        [$sql, $params] = $builder->build_select();

        $this->assertStringContainsString('DATE(date_debut) = %s', $sql);
        $this->assertSame(['2025-06-01'], $params);
    }

    public function testWhereRawWithoutParamsAddsOnlyClause(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_raw('status IS NOT NULL');

        [$sql, $params] = $builder->build_select();

        $this->assertStringContainsString('status IS NOT NULL', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereRawIgnoresEmptyClause(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_raw('   ');

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_events WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    // -------------------------------------------------------------------------
    // Cas limites — tableaux / valeurs vides ignorés
    // -------------------------------------------------------------------------

    public function testWhereInIntIgnoresEmptyArray(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_in_int('id', []);

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_mj_members WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereInIntIgnoresNonPositiveValues(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_in_int('id', [0, -1, -99]);

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_mj_members WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereEqualsIntIgnoresZeroAndNegative(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder->where_equals_int('location_id', 0);
        $builder->where_equals_int('animateur_id', -5);

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_events WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereInStringsIgnoresEmptyStrings(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_in_strings('status', ['', ' ', '  ']);

        [$sql, $params] = $builder->build_select();

        // sanitize_text_field trim → '' → ignoré
        $this->assertSame('SELECT * FROM wp_mj_members WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    public function testWhereEqualsIgnoresEmptyString(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_mj_members');
        $builder->where_equals('role', '');

        [$sql, $params] = $builder->build_select();

        $this->assertSame('SELECT * FROM wp_mj_members WHERE 1=1', $sql);
        $this->assertSame([], $params);
    }

    // -------------------------------------------------------------------------
    // build_select — comportements LIMIT / OFFSET
    // -------------------------------------------------------------------------

    public function testBuildSelectWithoutLimitOmitsLimitClause(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');

        [$sql] = $builder->build_select('id', 'created_at', 'asc', 0);

        $this->assertStringNotContainsString('LIMIT', $sql);
        $this->assertStringNotContainsString('OFFSET', $sql);
    }

    public function testBuildSelectWithLimitButZeroOffsetOmitsOffset(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');

        [$sql, $params] = $builder->build_select('*', '', 'asc', 5, 0);

        $this->assertStringContainsString('LIMIT %d', $sql);
        $this->assertStringNotContainsString('OFFSET', $sql);
        $this->assertSame([5], $params);
    }

    public function testBuildSelectNormalisesOrderDirectionToDesc(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');

        [$sql] = $builder->build_select('*', 'id', 'random_value');

        $this->assertStringContainsString('ORDER BY id DESC', $sql);
    }

    // -------------------------------------------------------------------------
    // Combinaison de plusieurs clauses
    // -------------------------------------------------------------------------

    public function testCombinedWhereRawAndWhereEqualsInt(): void
    {
        $builder = CrudQueryBuilder::for_table('wp_events');
        $builder
            ->where_equals_int('animateur_id', 3)
            ->where_raw('status != %s', ['cancelled']);

        [$sql, $params] = $builder->build_count();

        $this->assertSame(
            'SELECT COUNT(*) FROM wp_events WHERE 1=1 AND animateur_id = %d AND status != %s',
            $sql
        );
        $this->assertSame([3, 'cancelled'], $params);
    }
}
